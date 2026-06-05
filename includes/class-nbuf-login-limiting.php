<?php
/**
 * Login Attempt Limiting
 *
 * Tracks failed login attempts and enforces rate limiting to
 * prevent brute force attacks.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for login attempt tracking.
 * Custom nbuf_login_attempts table stores security data and cannot use
 * WordPress's standard APIs. Time-sensitive data not suitable for caching.
 */

/**
 * Class NBUF_Login_Limiting
 *
 * Handles login attempt limiting.
 */
class NBUF_Login_Limiting {


	/**
	 * Initialize login limiting hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		/*
		 * Priority 30: Must run AFTER WordPress's wp_authenticate_username_password (priority 20).
		 * If we run earlier, WordPress's auth filter overwrites our lockout WP_Error
		 * with its own error (incorrect_password, etc.). By running at 30, our lockout
		 * error becomes the final result that gets returned to the user.
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_login_attempts' ), 30, 3 );
		/* 2 args: the WP_Error is needed to skip non-credential (state-block) failures. */
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ), 10, 2 );
		add_action( 'wp_login', array( __CLASS__, 'clear_attempts_on_success' ), 10, 2 );
		add_action( 'after_password_reset', array( __CLASS__, 'clear_attempts_on_password_reset' ), 10, 2 );
	}

	/**
	 * Check if login attempts should be limited before authentication.
	 *
	 * @param  WP_User|WP_Error|null $user     User object or error.
	 * @param  string                $username Username.
	 * @param  string                $password Password.
	 * @return WP_User|WP_Error Modified user object or error.
	 */
	public static function check_login_attempts( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $password required by WordPress authenticate filter signature
		/* Check if login limiting is enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( ! $enabled ) {
			return $user;
		}

		/* Skip if no username provided */
		if ( empty( $username ) ) {
			return $user;
		}

		/* Get settings */
		$lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );

		/* Get IP address */
		$ip_address = self::get_ip_address();

		/*
		 * ALWAYS check if IP or username is locked out, even if $user is already an error.
		 * This prevents bypassing rate limiting if another plugin returns an error first.
		 */
		if ( self::is_locked_out( $ip_address, $username, $lockout_duration ) ) {
			/* Log to security log (upsert to aggregate repeated blocked attempts) */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log_or_update(
					'login_blocked',
					'critical',
					'Login attempt blocked due to rate limiting',
					array(
						'ip_address' => $ip_address,
						'username'   => $username,
						'reason'     => 'too_many_attempts',
					)
				);
			}

			return new WP_Error(
				'too_many_attempts',
				sprintf(
				/* translators: %d: number of minutes */
					__( 'Too many failed login attempts from this IP address. For security reasons, please wait %d minutes before trying again.', 'nobloat-user-foundry' ),
					$lockout_duration
				)
			);
		}

		return $user;
	}

	/**
	 * Record a failed login attempt.
	 *
	 * @param string $username Username used in failed attempt.
	 * @return void
	 */
	public static function record_failed_attempt( string $username, $error = null ): void {
		/* Check if login limiting is enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( ! $enabled ) {
			return;
		}

		/*
		 * SECURITY: do not count non-credential failures toward the rate-limit
		 * counters. Several `authenticate` filters return a WP_Error for a user
		 * whose PASSWORD WAS CORRECT but whose account is in a blocking state
		 * (disabled, unverified, pending approval, expired, weak/forced password
		 * change). WordPress core fires `wp_login_failed` for ANY authenticate
		 * WP_Error, so without this guard a correct-password attempt would burn a
		 * failed-attempt slot — letting an attacker lock any known, state-blocked
		 * username out of the (cross-IP) per-username limiter, and causing
		 * legitimate unverified users to lock themselves out in a few clicks.
		 * Only genuine credential failures should feed the brute-force counters;
		 * unknown codes still count (fail toward recording).
		 */
		if ( $error instanceof WP_Error ) {
			$non_credential_codes = array(
				'user_disabled',
				'account_expired',
				'email_not_verified',
				'nbuf_unverified',
				'awaiting_approval',
				'weak_password_expired',
				'nbuf_password_change_required',
				'too_many_attempts',
				'ip_blocked',
				/* Defensive: today intercept_login exits before returning this, but
				   if that ever changes a correct-password 2FA user must not count. */
				'2fa_required',
			);
			if ( in_array( $error->get_error_code(), $non_credential_codes, true ) ) {
				return;
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$ip_address = self::get_ip_address();

		/*
		 * Insert failed attempt record - use GMT for consistent timezone handling.
		 * Limit username to 255 characters to match database column size.
		 *
		 * SECURITY: lower-case the username before persistence. WordPress's
		 * `get_user_by( 'login', ... )` is case-insensitive, so `Admin`,
		 * `ADMIN`, and `admin` all resolve to the same account. Without this
		 * normalisation, an attacker can stay below the per-username threshold
		 * (10/hr by default) by varying the case of the typed username while
		 * still attacking the same actual account.
		 */
		/*
		 * Resolve the typed value to the canonical account so the counter key
		 * matches what clear_attempts_on_success() / clear_attempts_on_password_reset()
		 * delete (both key on the resolved user_login/email). Keying on the raw
		 * typed string diverged for inputs where sanitize_user (login form) and
		 * sanitize_text_field (here) disagree, leaving some users locked out even
		 * after a successful reset. Unknown users fall back to the typed value.
		 */
		$resolved_user    = self::resolve_login_user( $username );
		$counter_username = $resolved_user
			? self::normalize_username( $resolved_user->user_login )
			: self::normalize_username( $username );

		/*
		 * SECURITY: 2FA code failures feed the per-IP brute-force counters
		 * (intentional) but must NOT feed the cross-IP per-USERNAME counter, or an
		 * attacker holding the password but stopped at 2FA could lock the victim
		 * out from every IP. Store such failures with an empty username; the real
		 * username is still recorded in the security log below. Match BOTH the
		 * '2fa_*' convention (email/TOTP codes) AND the 'nbuf_2fa_*' family (the
		 * backup-code path returns nbuf_2fa_invalid_backup_code / _no_backup_codes,
		 * which would otherwise slip past a bare '2fa' prefix test and pollute the
		 * per-username counter -> self/victim cross-IP lockout). Every code reaching
		 * here in either family is a post-password 2FA-stage failure.
		 */
		$err_code_2fa = $error instanceof WP_Error ? (string) $error->get_error_code() : '';
		if ( '' !== $err_code_2fa && ( 0 === strpos( $err_code_2fa, '2fa' ) || 0 === strpos( $err_code_2fa, 'nbuf_2fa' ) ) ) {
			$counter_username = '';
		}

		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => $ip_address,
				'username'     => $counter_username,
				'attempt_time' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s' )
		);

		/*
		 * Layer-4 backstop bump. Behind a trusted proxy the stored ip_address is
		 * a FORWARDED (forgeable) client IP, so an attacker can rotate it to get a
		 * fresh per-(IP) / per-(IP+username) bucket every request. Also count this
		 * failure against the IMMUTABLE upstream connection (REMOTE_ADDR = the
		 * proxy), which the attacker cannot rotate. Only when a trusted proxy is
		 * configured AND this request actually came through it.
		 */
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$trusted_px  = (array) NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		if ( '' !== $remote_addr && ! empty( $trusted_px ) && class_exists( 'NBUF_IP' ) && NBUF_IP::is_trusted_proxy( $remote_addr, $trusted_px ) ) {
			self::bump_proxy_backstop( $remote_addr, (int) NBUF_Options::get( 'nbuf_login_lockout_duration', 10 ) );
		}

		/* Log to security log only (using upsert to reduce log pollution) */
		$user_id = $resolved_user ? $resolved_user->ID : 0;
		$message = $resolved_user ? 'Failed login attempt' : 'Failed login attempt (unknown user)';

		/*
		 * Use log_or_update() to aggregate repeated failures from same IP
		 * into a single record with occurrence count, reducing log pollution.
		 * Always log as 'warning' - 'critical' is reserved for blocked attempts.
		 */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::log_or_update(
				'login_failed',
				'warning',
				$message,
				array(
					'ip_address'  => $ip_address,
					'username'    => $username,
					'user_exists' => $resolved_user ? true : false,
				),
				$user_id
			);
		}

		/*
		 * Prune old rows opportunistically (~1% of failures), not on EVERY failed
		 * login. The per-failure DELETE caused heavy write amplification + index
		 * lock contention under a brute-force flood (exactly when the table is
		 * hottest), which could induce COUNT timeouts and, via the fail-closed
		 * count handling, lock out legitimate users. The cron job is the primary
		 * reaper; this is a cheap backstop.
		 */
		if ( 0 === wp_rand( 0, 99 ) ) {
			$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE attempt_time < %s',
					$table_name,
					$cutoff_time
				)
			);
		}
	}

	/**
	 * Clear login attempts for a user on successful login.
	 *
	 * @param string  $username Username.
	 * @param WP_User $user     User object.
	 * @return void
	 */
	public static function clear_attempts_on_success( $username, $user ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $user required by WordPress wp_login action signature.
		unset( $user ); /* WP signature contract; not needed here. */

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$ip_address = self::get_ip_address();

		/* Delete all attempts for this IP and (case-folded) username. */
		$wpdb->delete(
			$table_name,
			array(
				'ip_address' => $ip_address,
				'username'   => self::normalize_username( (string) $username ),
			),
			array( '%s', '%s' )
		);

		/*
		 * Also drop this IP's 2FA-failure rows (stored with an empty username) so
		 * a successful login resets the IP's contribution to the all-usernames
		 * backstop; otherwise normal 2FA mistypes would linger up to 24h.
		 */
		$wpdb->delete(
			$table_name,
			array( 'ip_address' => $ip_address, 'username' => '' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * Clear failed login attempts after a successful password reset.
	 *
	 * Without this, a user who triggered the lockout by mistyping their
	 * password and then went through the reset flow would still be blocked
	 * by the rate limiter when trying to log in with the new password.
	 *
	 * Fires on WordPress's `after_password_reset` action so it covers both
	 * this plugin's reset flow and any reset performed via wp-login.php.
	 *
	 * @param  WP_User $user     The user whose password was reset.
	 * @param  string  $new_pass The new password (unused).
	 * @return void
	 */
	public static function clear_attempts_on_password_reset( $user, $new_pass ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $new_pass required by after_password_reset action signature.
		if ( ! $user instanceof WP_User ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		/*
		 * Use the same case-folded normalisation as record_failed_attempt()
		 * so rows persisted there are reliably cleared.
		 */
		$user_login_key = self::normalize_username( $user->user_login );
		$user_email_key = self::normalize_username( $user->user_email );

		/* Clear by username (the value users typed in the login form). */
		$wpdb->delete(
			$table_name,
			array( 'username' => $user_login_key ),
			array( '%s' )
		);

		/* Users may have entered their email instead of username at login. */
		if ( ! empty( $user_email_key ) && $user_email_key !== $user_login_key ) {
			$wpdb->delete(
				$table_name,
				array( 'username' => $user_email_key ),
				array( '%s' )
			);
		}

		/*
		 * Note: we deliberately do NOT clear all rows for the requester's IP.
		 * An attacker who brute-forces several usernames from one IP and then
		 * completes a reset on a single compromised victim must not have
		 * lockouts cleared for the other targets they are still attacking.
		 * The username-keyed deletes above already unblock the legitimate
		 * "user reset their own password" flow for any IP they choose.
		 */
	}

	/**
	 * Check if IP/username is locked out.
	 *
	 * Implements dual-layer rate limiting:
	 * 1. IP-based: Prevents single-IP attacks (5 attempts per 15 mins)
	 * 2. Username-based: Prevents distributed brute force across multiple IPs (10 attempts per hour)
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  string $username         Username to check.
	 * @param  int    $lockout_duration Lockout duration in minutes (for IP-based check).
	 * @return bool True if locked out, false otherwise.
	 */
	private static function is_locked_out( $ip_address, $username, $lockout_duration ) {
		$max_attempts_per_ip = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );

		/*
		 * Resolve the typed value to the canonical account key so the per-(IP+
		 * username) and per-username COUNT queries match the rows
		 * record_failed_attempt() wrote (it keys on the resolved user_login).
		 * Without this, a user logging in by EMAIL is recorded under their
		 * user_login but checked under the email string, so the per-username
		 * limits would silently never trip for email-based login.
		 */
		$counter_username = self::counter_key_for( (string) $username );

		/*
		 * Layer 1 — this IP attacking THIS username (the precise brute-force
		 * shape). Previously the per-IP count summed EVERY username, so a few
		 * unrelated users fumbling passwords behind one shared NAT/CGNAT/CDN
		 * egress IP collectively tripped the lock and locked everyone out.
		 */
		$ip_user_count = self::get_recent_attempt_count( $ip_address, $counter_username, $lockout_duration );
		if ( $ip_user_count >= $max_attempts_per_ip ) {
			return true;
		}

		/*
		 * Layer 2 — this IP across ALL usernames (password spray / one IP
		 * hammering many accounts). Higher threshold so legitimate shared-IP
		 * traffic is not collateral-damaged. Filterable; defaults to 6x the
		 * per-(IP+username) limit (min 20).
		 */
		$max_attempts_per_ip_global = (int) apply_filters(
			'nbuf_login_max_attempts_per_ip_global',
			max( (int) $max_attempts_per_ip * 6, 20 )
		);
		$ip_global_count = self::get_recent_attempt_count_by_ip( $ip_address, $lockout_duration );
		if ( $ip_global_count >= $max_attempts_per_ip_global ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log_or_update(
					'ip_spray_detected',
					'critical',
					'High volume of failed logins from a single IP across multiple usernames',
					array( 'ip_address' => $ip_address, 'attempts' => $ip_global_count )
				);
			}
			return true;
		}

		/* Layer 3 — cross-IP per-username (prevents distributed brute force) */
		$max_attempts_per_username = NBUF_Options::get( 'nbuf_login_max_attempts_per_username', 10 );
		$username_lockout_duration = NBUF_Options::get( 'nbuf_login_username_lockout_window', 60 );

		$username_count = self::get_recent_attempt_count_by_username( $counter_username, $username_lockout_duration );
		if ( $username_count >= $max_attempts_per_username ) {
			/* Log distributed brute force detection */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'distributed_brute_force_detected',
					'critical',
					'Distributed brute force attack detected on username',
					array(
						'username'       => $username,
						'attempts'       => $username_count,
						'window_minutes' => $username_lockout_duration,
						'ip_address'     => $ip_address,
					)
				);
			}
			return true;
		}

		/*
		 * Layer 4 — proxy-connection backstop. Behind a trusted proxy the
		 * resolved client IP is forgeable, so Layers 1-2 can be evaded by
		 * rotating the forwarded IP. Throttle by the immutable upstream
		 * (REMOTE_ADDR) at a HIGH, filterable threshold so one hostile connection
		 * spraying thousands of forged client IPs is stopped — while normal
		 * aggregate traffic through the proxy (which all shares this REMOTE_ADDR)
		 * never reaches it. Set the filter to 0 to disable. Only active when a
		 * trusted proxy is configured and this request came through it.
		 */
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$trusted_px  = (array) NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		if ( '' !== $remote_addr && ! empty( $trusted_px ) && class_exists( 'NBUF_IP' ) && NBUF_IP::is_trusted_proxy( $remote_addr, $trusted_px ) ) {
			$proxy_threshold = (int) apply_filters( 'nbuf_login_max_attempts_per_proxy', 200 );
			$proxy_count     = self::proxy_backstop_count( $remote_addr, (int) $lockout_duration );
			if ( $proxy_threshold > 0 && $proxy_count >= $proxy_threshold ) {
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log_or_update(
						'proxy_spray_detected',
						'critical',
						'High volume of failed logins from one upstream proxy connection (forwarded-IP rotation suspected)',
						array( 'remote_addr' => $remote_addr, 'attempts' => $proxy_count )
					);
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Increment the per-upstream-connection (REMOTE_ADDR) failure counter.
	 *
	 * A sliding fixed-window transient counter (mirrors the 2FA per-IP counter):
	 * resets once the window elapses; the TTL never extends past the window so a
	 * sustained attacker cannot hold the counter open indefinitely.
	 *
	 * @param  string $remote_addr     The upstream connection IP (REMOTE_ADDR).
	 * @param  int    $window_minutes  Window length in minutes.
	 * @return int New count.
	 */
	private static function bump_proxy_backstop( string $remote_addr, int $window_minutes ): int {
		$key    = 'nbuf_login_proxy_rl_' . md5( $remote_addr );
		$window = max( 60, $window_minutes * 60 );
		$state  = get_transient( $key );
		if ( ! is_array( $state ) || empty( $state['first_at'] ) || ( time() - (int) $state['first_at'] ) > $window ) {
			$state = array( 'count' => 0, 'first_at' => time() );
		}
		++$state['count'];
		$ttl = max( 60, $window - ( time() - (int) $state['first_at'] ) );
		set_transient( $key, $state, $ttl );
		return (int) $state['count'];
	}

	/**
	 * Current per-upstream-connection failure count (0 if none / expired).
	 *
	 * @param  string $remote_addr Upstream connection IP.
	 * @return int Count.
	 */
	private static function proxy_backstop_count( string $remote_addr, int $window_minutes ): int {
		$state = get_transient( 'nbuf_login_proxy_rl_' . md5( $remote_addr ) );
		if ( ! is_array( $state ) || empty( $state['first_at'] ) ) {
			return 0;
		}
		/* Honour the logical window even though the transient TTL has a 60s floor,
		 * so a threshold-crossed counter cannot over-block past the window's end. */
		if ( ( time() - (int) $state['first_at'] ) > max( 60, $window_minutes * 60 ) ) {
			return 0;
		}
		return (int) $state['count'];
	}

	/**
	 * Get count of recent failed login attempts by IP address.
	 *
	 * SECURITY: Only counts attempts from the specific IP address.
	 * This blocks the attacker's IP without affecting the legitimate account holder
	 * who may be trying to log in from a different IP. This prevents denial-of-service
	 * attacks where an attacker could lock out a victim by intentionally failing logins.
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  string $username         Username (unused, kept for backwards compatibility).
	 * @param  int    $lockout_duration Lockout duration in minutes.
	 * @return int Number of recent attempts from this IP.
	 */
	private static function get_recent_attempt_count( $ip_address, $username, $lockout_duration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );

		/*
		 * Count attempts from this IP AGAINST THIS USERNAME. Scoping to the
		 * (IP, username) pair blocks the real single-account brute force without
		 * locking out unrelated accounts that share the same egress IP. The
		 * all-usernames-per-IP backstop and the cross-IP per-username layer are
		 * handled separately in is_locked_out().
		 */
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ip_address = %s AND username = %s AND attempt_time > %s',
				$table_name,
				$ip_address,
				self::normalize_username( (string) $username ),
				$cutoff_time
			)
		);

		return self::handle_count_result( $count, $wpdb->last_error, 'ip_user' );
	}

	/**
	 * Count recent failed attempts from an IP across ALL usernames.
	 *
	 * Backstop for password spray / one IP hammering many accounts. Uses a
	 * higher threshold than the per-(IP+username) layer so shared-IP traffic is
	 * not collateral-damaged.
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  int    $lockout_duration Window in minutes.
	 * @return int Number of recent attempts from this IP.
	 */
	private static function get_recent_attempt_count_by_ip( $ip_address, $lockout_duration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ip_address = %s AND attempt_time > %s',
				$table_name,
				$ip_address,
				$cutoff_time
			)
		);

		return self::handle_count_result( $count, $wpdb->last_error, 'ip_global' );
	}

	/**
	 * Map a COUNT() result to a usable attempt count.
	 *
	 * Distinguishes "table missing" from "DB error" so that a missing
	 * nbuf_login_attempts table (typically a botched plugin upgrade) does
	 * NOT lock every user out of the site. A genuine DB error still
	 * fail-closes to PHP_INT_MAX so an attack cannot bypass rate limits by
	 * inducing query failures.
	 *
	 * @since  1.6.4
	 * @param  mixed  $count       Result of $wpdb->get_var(). Null on error/missing.
	 * @param  string $last_error  $wpdb->last_error captured immediately after the query.
	 * @param  string $context     Either 'ip' or 'username' — used for the security log entry.
	 * @return int Attempt count, or PHP_INT_MAX to fail-closed, or 0 to fail-open on missing table.
	 */
	private static function handle_count_result( $count, string $last_error, string $context ): int {
		if ( null !== $count ) {
			return (int) $count;
		}

		/*
		 * MySQL "Table 'X' doesn't exist" error 1146. Treat as a deployment
		 * issue, not an attack: fail open (allow login) and surface a
		 * critical-severity log entry so operators see it.
		 */
		$is_missing_table = false !== stripos( $last_error, "doesn't exist" )
			|| false !== stripos( $last_error, 'no such table' );

		if ( $is_missing_table ) {
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log_or_update(
					'login_attempts_table_missing',
					'critical',
					'nbuf_login_attempts table does not exist; rate limiting is currently disabled',
					array(
						'context'  => $context,
						'db_error' => $last_error,
					)
				);
			}
			return 0;
		}

		/*
		 * Genuine DB error — fail closed so an attacker cannot bypass rate
		 * limiting by inducing query failures.
		 */
		return PHP_INT_MAX;
	}

	/**
	 * Get count of recent failed login attempts for a specific username (all IPs).
	 *
	 * This method prevents distributed brute force attacks where an attacker
	 * uses multiple IP addresses to attack a single username. By tracking attempts
	 * per username globally (regardless of IP), we can detect and block these attacks.
	 *
	 * @param  string $username         Username to check.
	 * @param  int    $lockout_duration Lockout duration in minutes.
	 * @return int Number of recent attempts for this username.
	 */
	private static function get_recent_attempt_count_by_username( $username, $lockout_duration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE username = %s AND attempt_time > %s',
				$table_name,
				self::normalize_username( (string) $username ),
				$cutoff_time
			)
		);

		return self::handle_count_result( $count, $wpdb->last_error, 'username' );
	}

	/**
	 * Get user's IP address.
	 *
	 * @return string IP address.
	 */
	private static function get_ip_address(): string {
		return NBUF_IP::get_client_ip( true );
	}

	/**
	 * Normalize a username for comparison and storage in the rate-limit table.
	 *
	 * SECURITY: lowercases the input and clamps to the column width. The
	 * lowercasing is essential — WordPress resolves user_login and user_email
	 * case-insensitively, so without normalization an attacker could bypass
	 * the per-username rate limit by varying case (`Admin`, `ADMIN`, `admin`).
	 *
	 * @since  1.6.4
	 * @param  string $username Raw value the user typed in the login form.
	 * @return string Sanitized, lowercased, length-clamped username.
	 */
	/**
	 * Resolve a typed login value to its WordPress account (by login, then email).
	 *
	 * @param  string $typed Raw value typed in the login form.
	 * @return WP_User|null The matching user, or null if none.
	 */
	private static function resolve_login_user( string $typed ): ?WP_User {
		$user = get_user_by( 'login', $typed );
		if ( ! $user ) {
			$user = get_user_by( 'email', $typed );
		}
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Canonical rate-limit counter key for a typed login value.
	 *
	 * Resolves to the account's normalized user_login when the value matches a
	 * user (so login-by-email and login-by-username hit the same counter and
	 * match what record/clear store), else the normalized typed value. RECORD,
	 * CHECK, and CLEAR must all use this so the keys agree.
	 *
	 * @param  string $typed Raw value typed in the login form.
	 * @return string Normalized counter key.
	 */
	private static function counter_key_for( string $typed ): string {
		$user = self::resolve_login_user( $typed );
		return $user ? self::normalize_username( $user->user_login ) : self::normalize_username( $typed );
	}

	private static function normalize_username( string $username ): string {
		$value = sanitize_text_field( $username );

		/*
		 * mb_strtolower is preferred for any unicode locales; fall back to
		 * strtolower if mbstring is unavailable. Username and email lookups
		 * in WP go through PHP comparisons on the DB side, so byte-equal
		 * lowercase is sufficient for the rate-limit join.
		 */
		if ( function_exists( 'mb_strtolower' ) ) {
			$value = mb_strtolower( $value, 'UTF-8' );
		} else {
			$value = strtolower( $value );
		}

		if ( strlen( $value ) > 255 ) {
			$value = substr( $value, 0, 255 );
		}

		return $value;
	}

	/**
	 * Get remaining lockout time for IP address.
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  string $username         Username (unused, kept for backwards compatibility).
	 * @param  int    $lockout_duration Lockout duration in minutes.
	 * @return int Minutes remaining in lockout, 0 if not locked out.
	 */
	public static function get_lockout_time_remaining( $ip_address, $username, $lockout_duration ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed -- $username kept for compatibility
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );
		/* Get most recent attempt from this IP within lockout window */
		$last_attempt = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(attempt_time) FROM %i WHERE ip_address = %s AND attempt_time > %s',
				$table_name,
				$ip_address,
				$cutoff_time
			)
		);

		if ( ! $last_attempt ) {
			return 0;
		}

		$lockout_end = strtotime( $last_attempt ) + ( $lockout_duration * 60 );
		$remaining   = $lockout_end - time();

		return $remaining > 0 ? ceil( $remaining / 60 ) : 0;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/* Note: Initialization now handled in main plugin file for performance optimization */
