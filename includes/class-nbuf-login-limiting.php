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
	 */
	public static function init() {
		/*
		 * Priority 30: Must run AFTER WordPress's wp_authenticate_username_password (priority 20).
		 * If we run earlier, WordPress's auth filter overwrites our lockout WP_Error
		 * with its own error (incorrect_password, etc.). By running at 30, our lockout
		 * error becomes the final result that gets returned to the user.
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_login_attempts' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ) );
		add_action( 'wp_login', array( __CLASS__, 'clear_attempts_on_success' ), 10, 2 );
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
	 */
	public static function record_failed_attempt( $username ) {
		/* Check if login limiting is enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( ! $enabled ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$ip_address = self::get_ip_address();

		/* Insert failed attempt record - use GMT for consistent timezone handling */
		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => $ip_address,
				'username'     => sanitize_text_field( $username ),
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
	 */
	public static function clear_attempts_on_success( $username, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $user required by WordPress wp_login action signature
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$ip_address = self::get_ip_address();

		/* Delete all attempts for this IP and username */
		$wpdb->delete(
			$table_name,
			array(
				'ip_address' => $ip_address,
				'username'   => sanitize_text_field( $username ),
			),
			array( '%s', '%s' )
		);
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
		$username_lockout_duration = 60; // 1 hour window for username-based limiting

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

		return (int) $count;
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
				sanitize_text_field( $username ),
				$cutoff_time
			)
		);

		return (int) $count;
	}

	/**
	 * Get user's IP address.
	 *
	 * @return string IP address.
	 */
	private static function get_ip_address() {
		$ip = '';

		/*
		* SECURITY: Prevent IP spoofing via X-Forwarded-For header
		*
		* Only trust proxy headers if request originates from a trusted proxy.
		* This prevents attackers from bypassing rate limiting by sending fake
		* X-Forwarded-For headers.
		*
		* To configure trusted proxies, add them to plugin settings (empty = don't trust proxies).
		*/
		$trusted_proxies = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/* Only trust X-Forwarded-For if request comes from trusted proxy */
		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			}
		}

		/* Fallback to REMOTE_ADDR (cannot be spoofed) */
		if ( empty( $ip ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/* Handle multiple IPs from proxy (take first one) */
		if ( strpos( $ip, ',' ) !== false ) {
			$ip_array = explode( ',', $ip );
			$ip       = trim( $ip_array[0] );
		}

		/* Validate and normalize IP address */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			/* Invalid IP - use REMOTE_ADDR as fallback */
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		}

		/* Normalize IPv6 addresses to canonical form */
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			/*
			 * SECURITY: Normalize IPv6 to canonical form.
			 * Prevents rate limit bypass via IPv6 representation variations.
			 * Example: 2001:0db8::1 and 2001:db8::1 and 2001:DB8::1 are the same address.
			 */
			$normalized = inet_ntop( inet_pton( $ip ) );
			if ( false !== $normalized ) {
				$ip = $normalized;
			}
		}

		/* Lowercase for consistency */
		return strtolower( $ip );
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
