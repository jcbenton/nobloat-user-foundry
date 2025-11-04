<?php
/**
 * Two-Factor Authentication Core
 *
 * Main 2FA orchestration class handling email codes, TOTP verification,
 * backup codes, device trust, and enforcement logic.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for 2FA data management.
 * Custom nbuf_user_2fa table stores security-sensitive 2FA data and cannot use
 * WordPress's standard meta APIs. Caching is not implemented for security data
 * to prevent stale authentication states.
 */

/**
 * NBUF_2FA class.
 *
 * Handles all 2FA operations including verification, enforcement, and management.
 */
class NBUF_2FA {


	/* TOTP (Time-based One-Time Password) constants per RFC 6238 */
	const TOTP_TIME_WINDOW         = 30;  // Seconds per time step (RFC 6238 standard).
	const TOTP_TOLERANCE_STEPS     = 1;   // Number of time steps to check before/after current.
	const TOTP_DEFAULT_CODE_LENGTH = 6; // Standard TOTP code length.

	/* Email 2FA code constants */
	const EMAIL_CODE_LENGTH     = 6;   // Default email verification code length.
	const EMAIL_CODE_EXPIRATION = 300; // Email code expiration in seconds (5 minutes).
	const EMAIL_CODE_COOLDOWN   = 60;  // Cooldown between code requests in seconds.

	/* Backup code constants */
	const BACKUP_CODE_COUNT  = 10;  // Default number of backup codes to generate.
	const BACKUP_CODE_LENGTH = 8;   // Length of each backup code.

	/* Device trust constants */
	const DEVICE_TRUST_DURATION = 2592000; // 30 days in seconds (30 * 24 * 60 * 60)

	/**
	 * Check if user should be challenged with 2FA
	 *
	 * Determines if the user needs to enter a 2FA code based on:
	 * - Whether they have 2FA enabled
	 * - Whether 2FA is required for their role
	 * - Whether their device is trusted
	 * - Grace period status
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if 2FA challenge required.
	 */
	public static function should_challenge( int $user_id ): bool {
		/* Check if user has 2FA enabled */
		if ( ! self::is_enabled( $user_id ) ) {
			/* Check if 2FA is required but user hasn't set it up */
			if ( self::is_required( $user_id ) ) {
				$grace_remaining = self::get_grace_period_remaining( $user_id );

				/* If grace period expired, challenge anyway to force setup */
				if ( $grace_remaining <= 0 ) {
					return true;
				}
			}

			return false;
		}

		/* Check if device is trusted */
		if ( self::is_device_trusted( $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if 2FA is required for user
	 *
	 * Determines if 2FA is mandatory for the user based on role and settings.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if 2FA is required.
	 */
	public static function is_required( int $user_id ): bool {
		$email_method = NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
		$totp_method  = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );

		/* Check if either method requires 2FA for all users */
		if ( 'required_all' === $email_method || 'required_all' === $totp_method ) {
			return true;
		}

		/* Check if user is admin and admin 2FA is required */
		if ( user_can( $user_id, 'manage_options' ) ) {
			if ( 'required_admin' === $email_method || 'required_admin' === $totp_method ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user has 2FA enabled
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if user has 2FA active.
	 */
	public static function is_enabled( int $user_id ): bool {
		return NBUF_User_2FA_Data::is_enabled( $user_id );
	}

	/**
	 * Get user's 2FA method
	 *
	 * @param  int $user_id User ID.
	 * @return string|false 'email', 'totp', 'both', or false if not enabled.
	 */
	public static function get_user_method( int $user_id ) {
		if ( ! self::is_enabled( $user_id ) ) {
			return false;
		}

		return NBUF_User_2FA_Data::get_method( $user_id );
	}

	/**
	 * Generate cryptographically secure random numeric code
	 *
	 * Uses random_int() for CSPRNG security, consistent with backup codes.
	 *
	 * @param  int $code_length Number of digits (default EMAIL_CODE_LENGTH).
	 * @return string|false Numeric code padded with leading zeros, or false on failure.
	 */
	private static function generate_numeric_code( $code_length = self::EMAIL_CODE_LENGTH ) {
		/* Validate and clamp code length to sane range */
		$code_length = max( 4, min( 10, (int) $code_length ) );

		/* Calculate maximum value (e.g., 999999 for 6 digits) */
		$max_value = (int) ( pow( 10, $code_length ) - 1 );

		/* Generate cryptographically secure random integer */
		try {
			$code = random_int( 0, $max_value );
		} catch ( Exception $e ) {
			/* Extremely rare - indicates broken random source */
			error_log( 'NBUF 2FA: random_int() failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Exception logging for fallback random generation.
			return false;
		}

		/* Pad with leading zeros to ensure consistent length */
		return str_pad( (string) $code, $code_length, '0', STR_PAD_LEFT );
	}

	/**
	 * Generate and send email 2FA code
	 *
	 * Creates a random numeric code and emails it to the user.
	 * Code is hashed with bcrypt before storage for security.
	 *
	 * SECURITY: Codes are hashed before storage to protect against database compromise.
	 * Even if an attacker gains database access, they cannot retrieve usable codes.
	 *
	 * @param  int $user_id User ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_email_code( int $user_id ) {
		/* Get user */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			/* SECURITY: Generic error message to prevent user enumeration */
			return new WP_Error(
				'nbuf_2fa_error',
				__( 'Verification failed. Please try again.', 'nobloat-user-foundry' )
			);
		}

		global $wpdb;

		/* SECURITY: Atomic lock using MySQL GET_LOCK() to prevent race conditions */
		$lock_name = 'nbuf_2fa_' . $user_id;
		$locked    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 1)', $lock_name ) );

		if ( ! $locked ) {
			return new WP_Error(
				'nbuf_2fa_rate_limited',
				__( 'Please wait before requesting another verification code.', 'nobloat-user-foundry' )
			);
		}

		/*
		* SECURITY: Atomic rate limiting check using add_transient()
		*
		* add_transient() returns false if transient already exists, providing
		* atomic check-and-set operation that prevents race conditions.
		* This ensures only one code generation request succeeds within the 60-second window.
		*/
		$rate_limit_key = 'nbuf_2fa_email_rate_limit_' . $user_id;
		if ( ! add_transient( $rate_limit_key, time(), self::EMAIL_CODE_COOLDOWN ) ) {
			/* Release lock before returning */
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
			/* Transient already exists - user is rate limited */
			return new WP_Error(
				'nbuf_2fa_rate_limited',
				__( 'Please wait before requesting another verification code.', 'nobloat-user-foundry' )
			);
		}

		/* Get settings */
		$code_length        = NBUF_Options::get( 'nbuf_2fa_email_code_length', self::EMAIL_CODE_LENGTH );
		$expiration_minutes = NBUF_Options::get( 'nbuf_2fa_email_expiration', self::EMAIL_CODE_EXPIRATION / 60 );
		$expiration_seconds = $expiration_minutes * 60;

		/* Generate cryptographically secure random code */
		$code = self::generate_numeric_code( $code_length );

		if ( false === $code ) {
			return new WP_Error(
				'nbuf_2fa_generation_failed',
				__( 'Failed to generate verification code. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* SECURITY: Hash code before storage (defense-in-depth against database compromise) */
		$hashed_code = wp_hash_password( $code );

		if ( empty( $hashed_code ) || ! is_string( $hashed_code ) ) {
			error_log( sprintf( 'NBUF 2FA: wp_hash_password() failed for user %d', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security-critical error logging.
			return new WP_Error(
				'nbuf_2fa_hash_failed',
				__( 'Security error. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Store hashed code in transient */
		$transient_key = 'nbuf_2fa_email_code_' . $user_id;
		$stored        = set_transient( $transient_key, $hashed_code, $expiration_seconds );

		if ( ! $stored ) {
			error_log( sprintf( 'NBUF 2FA: Failed to store code for user %d', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security-critical error logging.
			return new WP_Error(
				'nbuf_2fa_storage_failed',
				__( 'Failed to store verification code. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Release lock after successful generation */
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

		/* Send email with plain text code (user needs to read it) */
		$subject = sprintf(
		/* translators: %s: site name */
			__( 'Your verification code for %s', 'nobloat-user-foundry' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
		/* translators: 1: user display name, 2: verification code, 3: expiration minutes, 4: site name */
			__(
				'Hello %1$s,

Your verification code is: %2$s

This code will expire in %3$d minutes.

If you did not request this code, please ignore this email.

- %4$s',
				'nobloat-user-foundry'
			),
			$user->display_name,
			$code,
			$expiration_minutes,
			get_bloginfo( 'name' )
		);

		/* Use NBUF_Email if available, otherwise wp_mail */
		if ( class_exists( 'NBUF_Email' ) ) {
			return NBUF_Email::send_email( $user->user_email, $subject, $message );
		}

		return wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Verify email 2FA code
	 *
	 * Checks if the provided code matches the stored hashed code.
	 * Implements rate limiting, attempt tracking, and timing attack protection.
	 *
	 * SECURITY: Uses bcrypt verification for constant-time comparison and to prevent
	 * brute force attacks. Even if database is compromised, hashed codes cannot be
	 * reversed to obtain usable verification codes.
	 *
	 * @param  int    $user_id User ID.
	 * @param  string $code    User-entered code.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_email_code( int $user_id, string $code ) {
		/* Check for lockout */
		if ( self::is_locked_out( $user_id ) ) {
			return new WP_Error(
				'2fa_locked_out',
				__( 'Too many failed attempts. Please try again later.', 'nobloat-user-foundry' )
			);
		}

		/* Get stored hashed code */
		$transient_key = 'nbuf_2fa_email_code_' . $user_id;
		$stored_hash   = get_transient( $transient_key );

		/*
		 * SECURITY: Sanitize and normalize input for constant-time comparison.
		 * Remove all non-numeric characters first.
		 */
		$code            = preg_replace( '/[^0-9]/', '', $code );
		$expected_length = NBUF_Options::get( 'nbuf_2fa_email_code_length', 6 );

		/*
		 * SECURITY: Normalize code length to prevent timing attack.
		 * Pad or truncate to expected length BEFORE bcrypt verification.
		 * This ensures all code paths perform the expensive bcrypt operation.
		 */
		$code_length = strlen( $code );
		if ( $code_length < $expected_length ) {
			/* Pad short codes with zeros to expected length */
			$code = str_pad( $code, $expected_length, '0' );
		} elseif ( $code_length > $expected_length ) {
			/* Truncate long codes to expected length */
			$code = substr( $code, 0, $expected_length );
		}

		/*
		 * SECURITY: Always perform bcrypt operation for timing attack protection.
		 *
		 * Both code paths (expired vs valid) must take the same time (~50-100ms).
		 * If we return early without hashing, timing differences reveal code validity.
		 *
		 * Solution: Use dummy hash when code expired, real hash when valid.
		 * Both paths perform bcrypt verification for constant-time behavior.
		 */
		$stored_hash = get_transient( $transient_key );
		$code_valid  = false;
		$code_exists = false !== $stored_hash;

		if ( ! $code_exists ) {
			/*
			 * SECURITY: Generate dummy hash with same bcrypt cost factor.
			 * Ensures timing protection even if WordPress changes cost in future.
			 */
			static $dummy_hash = null;
			if ( null === $dummy_hash ) {
				$dummy_hash = wp_hash_password( 'dummy_2fa_timing_protection_' . wp_salt() );
			}
			$stored_hash = $dummy_hash;
		}

		/*
		 * SECURITY: Always perform bcrypt verification regardless of whether code exists.
		 * This makes timing identical for both expired and valid codes.
		 */
		$code_valid = wp_check_password( $code, $stored_hash );

		/*
		 * Now check results AFTER timing-consistent operations.
		 * Return appropriate error based on what actually failed.
		 */
		if ( ! $code_exists ) {
			self::record_failed_attempt( $user_id );
			return new WP_Error(
				'2fa_code_expired',
				__( 'Verification code has expired. Please request a new code.', 'nobloat-user-foundry' )
			);
		}

		/* Check if the provided code was invalid (wrong length or wrong digits) */
		if ( $code_length !== $expected_length || ! $code_valid ) {
			self::record_failed_attempt( $user_id );
			return new WP_Error(
				'2fa_invalid_code',
				__( 'Invalid verification code. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Success - code was valid, delete it immediately (single use) and clear attempts */
		delete_transient( $transient_key );
		self::clear_attempts( $user_id );

		/* Update last used timestamp for statistics */
		NBUF_User_2FA_Data::set_last_used( $user_id );

		return true;
	}

	/**
	 * Verify TOTP code
	 *
	 * Verifies a TOTP code from an authenticator app.
	 *
	 * @param  int    $user_id User ID.
	 * @param  string $code    User-entered code.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_totp_code( int $user_id, string $code ) {
		/* Check for lockout */
		if ( self::is_locked_out( $user_id ) ) {
			return new WP_Error(
				'2fa_locked_out',
				__( 'Too many failed attempts. Please try again later.', 'nobloat-user-foundry' )
			);
		}

		/* Get user's TOTP secret */
		$secret = NBUF_User_2FA_Data::get_totp_secret( $user_id );

		if ( empty( $secret ) ) {
			/* SECURITY: Generic error message to prevent configuration enumeration */
			return new WP_Error(
				'2fa_verification_failed',
				__( 'Verification failed. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Get settings */
		$code_length = NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
		$time_window = NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
		$tolerance   = NBUF_Options::get( 'nbuf_2fa_totp_tolerance', 1 );

		/* Verify code */
		$valid = NBUF_TOTP::verify_code( $secret, $code, $tolerance, $code_length, $time_window );

		if ( ! $valid ) {
			self::record_failed_attempt( $user_id );
			return new WP_Error(
				'2fa_invalid_code',
				__( 'Invalid verification code. Please try again.', 'nobloat-user-foundry' )
			);
		}

		/* Success - clear attempts */
		self::clear_attempts( $user_id );

		/* Update last used timestamp */
		NBUF_User_2FA_Data::set_last_used( $user_id );

		return true;
	}

	/**
	 * Generate backup codes for user
	 *
	 * Creates a set of one-time use backup codes.
	 * Codes are bcrypt hashed before storage.
	 *
	 * @param  int $user_id User ID.
	 * @param  int $count   Number of codes to generate (default BACKUP_CODE_COUNT).
	 * @return array Plain text codes (only shown once).
	 */
	public static function generate_backup_codes( int $user_id, int $count = self::BACKUP_CODE_COUNT ): array {
		$code_length = NBUF_Options::get( 'nbuf_2fa_backup_length', self::BACKUP_CODE_LENGTH );
		$codes       = array();
		$hashed      = array();

		/* Generate codes */
		for ( $i = 0; $i < $count; $i++ ) {
			/* Generate alphanumeric code */
			$code         = '';
			$chars        = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid confusing characters.
			$chars_length = strlen( $chars );

			for ( $j = 0; $j < $code_length; $j++ ) {
				/* Use cryptographically secure random */
				$code .= $chars[ random_int( 0, $chars_length - 1 ) ];
			}

			$codes[]  = $code;
			$hashed[] = wp_hash_password( $code );
		}

		/* Store hashed codes */
		NBUF_User_2FA_Data::set_backup_codes( $user_id, $hashed );

		return $codes;
	}

	/**
	 * Verify backup code
	 *
	 * Checks if a backup code is valid and not already used.
	 * Marks code as used on successful verification.
	 *
	 * @param  int    $user_id User ID.
	 * @param  string $code    User-entered backup code.
	 * @return bool|WP_Error True if code is valid and unused, WP_Error on failure.
	 */
	public static function verify_backup_code( int $user_id, string $code ) {
		/* Get stored codes and used indexes */
		$stored_codes = NBUF_User_2FA_Data::get_backup_codes( $user_id );
		$used_indexes = NBUF_User_2FA_Data::get_backup_codes_used( $user_id );

		if ( ! is_array( $stored_codes ) || empty( $stored_codes ) ) {
			return new WP_Error(
				'nbuf_2fa_no_backup_codes',
				__( 'No backup codes have been generated for this account.', 'nobloat-user-foundry' )
			);
		}

		if ( ! is_array( $used_indexes ) ) {
			$used_indexes = array();
		}

		/* Sanitize input */
		$code = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $code ) );

		/* Check each code */
		foreach ( $stored_codes as $index => $hashed_code ) {
			/* Skip if already used */
			if ( in_array( $index, $used_indexes, true ) ) {
				continue;
			}

			/* Verify code */
			if ( wp_check_password( $code, $hashed_code ) ) {
				/* Mark as used */
				NBUF_User_2FA_Data::mark_backup_code_used( $user_id, $index );

				/* Update last used timestamp */
				NBUF_User_2FA_Data::set_last_used( $user_id );

				return true;
			}
		}

		/* No matching code found */
		return new WP_Error(
			'nbuf_2fa_invalid_backup_code',
			__( 'Invalid backup code or code has already been used.', 'nobloat-user-foundry' )
		);
	}

	/**
	 * Check if device is trusted
	 *
	 * Verifies if the current device has a valid trust cookie.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if device is trusted.
	 */
	public static function is_device_trusted( int $user_id ): bool {
		/* Check if device trust is enabled */
		if ( ! NBUF_Options::get( 'nbuf_2fa_device_trust', true ) ) {
			return false;
		}

		/* Check for trust cookie */
		$cookie_name = 'nbuf_2fa_trust_' . $user_id;

		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );

		/* Get stored trusted devices */
		$trusted_devices = NBUF_User_2FA_Data::get_trusted_devices( $user_id );

		if ( ! is_array( $trusted_devices ) ) {
			return false;
		}

		/* Check if token exists and is not expired */
		if ( isset( $trusted_devices[ $token ] ) ) {
			$expires = $trusted_devices[ $token ];

			if ( $expires > time() ) {
				/* Rotate token for enhanced security (prevents long-term token theft). */
				$new_token = bin2hex( random_bytes( 32 ) );
				/* SECURITY: Calculate remaining time from old token and apply to new one */
				$remaining_time = max( 0, $expires - time() );
				$new_expires    = time() + $remaining_time;

				/* Remove old token and add new one */
				NBUF_User_2FA_Data::remove_trusted_device( $user_id, $token );
				NBUF_User_2FA_Data::add_trusted_device( $user_id, $new_token, $new_expires );

				/*
				Update cookie with new token
				*/
				/* SECURITY: 2FA cookies should only be used over HTTPS */
				if ( ! is_ssl() ) {
					error_log( 'NBUF Warning: 2FA device trust should only be used over HTTPS connections' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security warning for HTTPS requirement.
				}

				$cookie_name = 'nbuf_2fa_trust_' . $user_id;
				setcookie(
					$cookie_name,
					$new_token,
					array(
						'expires'  => $new_expires,
						'path'     => COOKIEPATH,
						'domain'   => COOKIE_DOMAIN,
						'secure'   => true, /* Always use secure flag for 2FA cookies */
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);

				return true;
			}

			/* Token expired - remove it */
			NBUF_User_2FA_Data::remove_trusted_device( $user_id, $token );
		}

		return false;
	}

	/**
	 * Trust current device
	 *
	 * Sets a cookie to remember this device for future logins.
	 *
	 * @param  int $user_id  User ID.
	 * @param  int $duration Duration in seconds (default DEVICE_TRUST_DURATION = 30 days).
	 * @return bool True on success.
	 */
	public static function trust_device( int $user_id, int $duration = self::DEVICE_TRUST_DURATION ): bool {
		/* SECURITY: 2FA cookies should only be used over HTTPS */
		if ( ! is_ssl() ) {
			error_log( 'NBUF Warning: 2FA device trust should only be used over HTTPS connections' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security warning for HTTPS requirement.
		}

		/* Generate secure token */
		$token = bin2hex( random_bytes( 32 ) );

		/* Set cookie */
		$cookie_name = 'nbuf_2fa_trust_' . $user_id;
		$expires     = time() + $duration;

		setcookie(
			$cookie_name,
			$token,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => true, /* Always use secure flag for 2FA cookies */
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		/* Store token in database */
		NBUF_User_2FA_Data::add_trusted_device( $user_id, $token, $expires );

		return true;
	}

	/**
	 * Record failed attempt and check for lockout
	 *
	 * Increments failed attempt counter and applies lockout if threshold exceeded.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if now locked out.
	 */
	public static function record_failed_attempt( int $user_id ): bool {
		$attempts_key = 'nbuf_2fa_attempts_' . $user_id;
		$attempts     = (int) get_transient( $attempts_key );

		++$attempts;

		$rate_window = NBUF_Options::get( 'nbuf_2fa_email_rate_window', 15 ) * 60;
		set_transient( $attempts_key, $attempts, $rate_window );

		/* Check for lockout */
		$max_attempts = NBUF_Options::get( 'nbuf_2fa_lockout_attempts', 5 );

		if ( $attempts >= $max_attempts ) {
			$lockout_key = 'nbuf_2fa_lockout_' . $user_id;
			set_transient( $lockout_key, true, $rate_window );
			return true;
		}

		return false;
	}

	/**
	 * Check if user is locked out
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if locked out.
	 */
	public static function is_locked_out( int $user_id ): bool {
		$lockout_key = 'nbuf_2fa_lockout_' . $user_id;
		return (bool) get_transient( $lockout_key );
	}

	/**
	 * Clear failed attempts counter
	 *
	 * @param int $user_id User ID.
	 */
	public static function clear_attempts( int $user_id ): void {
		$attempts_key = 'nbuf_2fa_attempts_' . $user_id;
		$lockout_key  = 'nbuf_2fa_lockout_' . $user_id;

		delete_transient( $attempts_key );
		delete_transient( $lockout_key );
	}

	/**
	 * Get grace period remaining
	 *
	 * Returns number of days remaining for user to set up required 2FA.
	 *
	 * @param  int $user_id User ID.
	 * @return int Days remaining (0 if expired or not applicable).
	 */
	public static function get_grace_period_remaining( int $user_id ): int {
		$forced_at = NBUF_User_2FA_Data::get_forced_at( $user_id );

		if ( empty( $forced_at ) ) {
			/* Set forced timestamp if this is first check */
			if ( self::is_required( $user_id ) && ! self::is_enabled( $user_id ) ) {
				$forced_at = current_time( 'mysql' );
				NBUF_User_2FA_Data::set_forced_at( $user_id, $forced_at );
			} else {
				return 0;
			}
		}

		$grace_days = NBUF_Options::get( 'nbuf_2fa_grace_period', 7 );
		$grace_end  = strtotime( $forced_at ) + ( $grace_days * DAY_IN_SECONDS );
		$days_left  = ceil( ( $grace_end - time() ) / DAY_IN_SECONDS );

		return max( 0, $days_left );
	}

	/**
	 * Enable 2FA for user
	 *
	 * Activates 2FA with the specified method.
	 *
	 * @param  int         $user_id     User ID.
	 * @param  string      $method      'email', 'totp', or 'both'.
	 * @param  string|null $totp_secret Required if method includes 'totp'.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function enable_for_user( int $user_id, string $method, ?string $totp_secret = null ) {
		$valid_methods = array( 'email', 'totp', 'both' );

		if ( ! in_array( $method, $valid_methods, true ) ) {
			return new WP_Error( '2fa_invalid_method', __( 'Invalid 2FA method.', 'nobloat-user-foundry' ) );
		}

		/* SECURITY: TOTP secrets must only be transmitted over HTTPS to prevent MITM attacks */
		if ( ( 'totp' === $method || 'both' === $method ) && ! is_ssl() ) {
			return new WP_Error(
				'2fa_https_required',
				__( 'Two-Factor Authentication (Authenticator) requires HTTPS to be enabled on your site for secure transmission of authentication secrets.', 'nobloat-user-foundry' )
			);
		}

		/* If TOTP is included, secret is required */
		if ( ( 'totp' === $method || 'both' === $method ) && empty( $totp_secret ) ) {
			return new WP_Error( '2fa_missing_secret', __( 'TOTP secret is required.', 'nobloat-user-foundry' ) );
		}

		/* Enable 2FA in database */
		NBUF_User_2FA_Data::enable( $user_id, $method, $totp_secret );

		/* Clear forced timestamp */
		NBUF_User_2FA_Data::set_forced_at( $user_id, null );

		return true;
	}

	/**
	 * Disable 2FA for user
	 *
	 * Removes all 2FA data for the user.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True on success.
	 */
	public static function disable_for_user( int $user_id ): bool {
		return NBUF_User_2FA_Data::disable( $user_id );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
