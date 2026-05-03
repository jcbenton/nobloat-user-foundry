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
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ) );
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
	public static function record_failed_attempt( string $username ): void {
		/* Check if login limiting is enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( ! $enabled ) {
			return;
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
		$sanitized_username = self::normalize_username( $username );

		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => $ip_address,
				'username'     => $sanitized_username,
				'attempt_time' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s' )
		);

		/* Log to security log only (using upsert to reduce log pollution) */
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		$user_id = $user ? $user->ID : 0;
		$message = $user ? 'Failed login attempt' : 'Failed login attempt (unknown user)';

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
					'user_exists' => $user ? true : false,
				),
				$user_id
			);
		}

		/*
		Clean up old attempts (older than 24 hours)
		*/
		/* NOTE: gmdate() is server-generated so this is safe, but pre-calculate for best practice */
		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE attempt_time < %s',
				$table_name,
				$cutoff_time
			)
		);
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

		/* Check IP-based lockout */
		$ip_count = self::get_recent_attempt_count( $ip_address, $username, $lockout_duration );
		if ( $ip_count >= $max_attempts_per_ip ) {
			return true;
		}

		/* Check username-based lockout (prevents distributed brute force) */
		$max_attempts_per_username = NBUF_Options::get( 'nbuf_login_max_attempts_per_username', 10 );
		$username_lockout_duration = NBUF_Options::get( 'nbuf_login_username_lockout_window', 60 );

		$username_count = self::get_recent_attempt_count_by_username( $username, $username_lockout_duration );
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

		return false;
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
	private static function get_recent_attempt_count( $ip_address, $username, $lockout_duration ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed -- $username kept for compatibility
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );

		/*
		 * SECURITY: Count attempts from this IP only.
		 * This blocks the attacker's IP without locking out the legitimate user.
		 * Distributed brute force protection is handled separately by
		 * get_recent_attempt_count_by_username() with a higher threshold.
		 */
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ip_address = %s AND attempt_time > %s',
				$table_name,
				$ip_address,
				$cutoff_time
			)
		);

		return self::handle_count_result( $count, $wpdb->last_error, 'ip' );
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
