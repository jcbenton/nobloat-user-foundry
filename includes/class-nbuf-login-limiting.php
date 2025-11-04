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
		add_filter( 'authenticate', array( __CLASS__, 'check_login_attempts' ), 5, 3 );
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

		/* Skip if already an error or no username provided */
		if ( is_wp_error( $user ) || empty( $username ) ) {
			return $user;
		}

		/* Get settings */
		$max_attempts     = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );
		$lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );

		/* Get IP address */
		$ip_address = self::get_ip_address();

		/* Check if IP or username is locked out */
		if ( self::is_locked_out( $ip_address, $username, $lockout_duration ) ) {
			$attempts = self::get_recent_attempt_count( $ip_address, $username, $lockout_duration );

			return new WP_Error(
				'too_many_attempts',
				sprintf(
				/* translators: %d: number of minutes */
					__( 'Too many failed login attempts. Please try again in %d minutes.', 'nobloat-user-foundry' ),
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

		/* Insert failed attempt record */
		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => $ip_address,
				'username'     => sanitize_text_field( $username ),
				'attempt_time' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		/* Log failed login attempt */
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		if ( $user ) {
			$attempt_count = self::get_recent_attempt_count( $ip_address, $username, NBUF_Options::get( 'nbuf_login_lockout_duration', 10 ) );
			NBUF_Audit_Log::log(
				$user->ID,
				'login_failed',
				'failure',
				'Failed login attempt',
				array(
					'username'      => $username,
					'attempt_count' => $attempt_count,
				)
			);
		}

		/*
		Clean up old attempts (older than 24 hours)
		*/
		/* NOTE: gmdate() is server-generated so this is safe, but pre-calculate for best practice */
		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, safe
				"DELETE FROM {$table_name} WHERE attempt_time < %s",
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
	 * Get count of recent failed login attempts.
	 *
	 * SECURITY NOTE: Uses OR logic for IP and username checks.
	 * This protects against single-IP attacks but allows distributed brute force
	 * using multiple IPs. For enhanced protection against distributed attacks,
	 * consider implementing per-username rate limiting with stricter thresholds.
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  string $username         Username to check.
	 * @param  int    $lockout_duration Lockout duration in minutes.
	 * @return int Number of recent attempts.
	 */
	private static function get_recent_attempt_count( $ip_address, $username, $lockout_duration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );

		/*
		* SECURITY: Count attempts for this IP OR username within lockout window
		*
		* Future enhancement: Add separate per-username global rate limiting
		* to prevent distributed brute force attacks across multiple IPs.
		* Suggested: MAX 10 attempts per username per hour regardless of IP.
		*/
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, safe
				"SELECT COUNT(*) FROM {$table_name}
				WHERE (ip_address = %s OR username = %s)
				AND attempt_time > %s",
				$ip_address,
				sanitize_text_field( $username ),
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
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, safe
				"SELECT COUNT(*) FROM {$table_name}
				WHERE username = %s
				AND attempt_time > %s",
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

		/* Validate IP address */
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
			/* Use REMOTE_ADDR as last resort */
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Get remaining lockout time for IP/username.
	 *
	 * @param  string $ip_address       IP address to check.
	 * @param  string $username         Username to check.
	 * @param  int    $lockout_duration Lockout duration in minutes.
	 * @return int Minutes remaining in lockout, 0 if not locked out.
	 */
	public static function get_lockout_time_remaining( $ip_address, $username, $lockout_duration ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_login_attempts';

		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lockout_duration} minutes" ) );
		/* Get most recent attempt within lockout window */
		$last_attempt = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, safe
				"SELECT MAX(attempt_time) FROM {$table_name}
				WHERE (ip_address = %s OR username = %s)
				AND attempt_time > %s",
				$ip_address,
				sanitize_text_field( $username ),
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
