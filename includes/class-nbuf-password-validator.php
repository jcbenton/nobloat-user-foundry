<?php
/**
 * Password Strength Validation
 *
 * Validates password strength based on configured requirements.
 * Uses simple regex checks for character requirements following
 * WordPress coding standards and best practices.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Password_Validator class.
 *
 * Handles password strength validation and enforcement logic.
 */
class NBUF_Password_Validator {


	/**
	 * Initialize password validation system.
	 *
	 * Registers hooks for weak password migration feature.
	 * Called from main plugin file during initialization.
	 */
	public static function init() {
		/* Check if weak password migration is enabled */
		$force_change = NBUF_Options::get( 'nbuf_password_force_weak_change', false );
		if ( ! $force_change ) {
			return;
		}

		/*
		 * Hook into authenticate filter to check password at login.
		 * Priority 29: Must run BEFORE password expiration check (priority 30)
		 * so we can set the force_password_change flag for the expiration
		 * handler to detect and redirect.
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_password_at_login' ), 29, 3 );
	}

	/**
	 * Check password strength at login.
	 *
	 * Validates the user's password against current requirements during login.
	 * If the password is weak and grace period has expired, sets the
	 * force_password_change flag to trigger password change flow.
	 *
	 * @param  WP_User|WP_Error|null $user     User object or error.
	 * @param  string                $username Username.
	 * @param  string                $password Password (plain text).
	 * @return WP_User|WP_Error User object or error (unchanged).
	 */
	public static function check_password_at_login( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $username required by WordPress authenticate filter signature.
		/* Only proceed if we have a valid user and password */
		if ( ! $user instanceof WP_User || empty( $password ) ) {
			return $user;
		}

		/* Check if password requirements are enabled */
		$requirements_enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', true );
		if ( ! $requirements_enabled ) {
			return $user;
		}

		/* Admin bypass check */
		$admin_bypass = NBUF_Options::get( 'nbuf_password_admin_bypass', false );
		if ( $admin_bypass && user_can( $user->ID, 'manage_options' ) ) {
			return $user;
		}

		/* Validate the password against current requirements */
		$validation = self::validate( $password, $user->ID );

		/* If password meets requirements, clear any existing flag and return */
		if ( true === $validation ) {
			$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user->ID );
			if ( $weak_flagged ) {
				NBUF_User_Data::clear_weak_password_flag( $user->ID );
			}
			return $user;
		}

		/* Password is weak - handle based on check timing setting */
		$check_timing = NBUF_Options::get( 'nbuf_password_check_timing', 'once' );
		$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user->ID );

		/*
		 * For "once" timing: Flag user if not already flagged.
		 * For "every" timing: Always process (re-flag on each login).
		 */
		if ( 'once' === $check_timing && $weak_flagged ) {
			/* Already flagged - check if they changed password since flagging */
			$password_changed = NBUF_User_Data::get_password_changed_at( $user->ID );
			if ( $password_changed && strtotime( $password_changed ) > strtotime( $weak_flagged ) ) {
				/*
				 * Password was changed after flagging but still doesn't meet requirements.
				 * This shouldn't happen normally (password change form validates),
				 * but could occur if requirements were tightened after they changed.
				 * Re-flag them with new timestamp.
				 */
				NBUF_User_Data::flag_weak_password( $user->ID );
				$weak_flagged = current_time( 'mysql', true );
			}
		} else {
			/* Flag user (first time for "once", or every time for "every") */
			NBUF_User_Data::flag_weak_password( $user->ID );
			$weak_flagged = current_time( 'mysql', true );
		}

		/* Check grace period */
		$grace_days = (int) NBUF_Options::get( 'nbuf_password_grace_period', 7 );

		if ( $grace_days > 0 && $weak_flagged ) {
			$flagged_timestamp = strtotime( $weak_flagged );
			$grace_expires     = $flagged_timestamp + ( $grace_days * DAY_IN_SECONDS );

			if ( time() < $grace_expires ) {
				/*
				 * Still within grace period - allow login.
				 * Set a transient to show warning message after login.
				 */
				$days_remaining = ceil( ( $grace_expires - time() ) / DAY_IN_SECONDS );
				set_transient(
					'nbuf_weak_password_warning_' . $user->ID,
					array(
						'days_remaining' => $days_remaining,
						'requirements'   => self::get_requirements_list(),
					),
					HOUR_IN_SECONDS
				);
				return $user;
			}
		}

		/*
		 * Grace period expired (or grace period is 0) - force password change.
		 * Set the force_password_change flag so the password expiration
		 * handler (priority 30) will redirect to password change form.
		 */
		if ( class_exists( 'NBUF_Password_Expiration' ) ) {
			NBUF_Password_Expiration::force_password_change( $user->ID );
		}

		/* Log the enforcement */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user->ID,
				'weak_password_enforcement',
				'info',
				__( 'Password change required due to weak password.', 'nobloat-user-foundry' )
			);
		}

		return $user;
	}

	/**
	 * Validate password against requirements
	 *
	 * Checks password against all enabled strength requirements
	 * including length, character types, and minimum strength level.
	 *
	 * @param  string $password Password to validate.
	 * @param  int    $user_id  User ID (for admin bypass check).
	 * @return true|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate( $password, $user_id = 0 ) {

		/* Check if enabled */
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', true );
		if ( ! $enabled ) {
			return true;
		}

		/* Admin bypass check */
		$admin_bypass = NBUF_Options::get( 'nbuf_password_admin_bypass', false );
		if ( $admin_bypass && $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$errors = array();

		/* Minimum length */
		$min_length = NBUF_Options::get( 'nbuf_password_min_length', 12 );
		if ( strlen( $password ) < $min_length ) {
			/* translators: %d: minimum password length */
			$errors[] = sprintf( __( 'Password must be at least %d characters long.', 'nobloat-user-foundry' ), $min_length );
		}

		/* Uppercase requirement */
		$require_uppercase = NBUF_Options::get( 'nbuf_password_require_uppercase', false );
		if ( $require_uppercase && ! preg_match( '/[A-Z]/', $password ) ) {
			$errors[] = __( 'Password must contain at least one uppercase letter (A-Z).', 'nobloat-user-foundry' );
		}

		/* Lowercase requirement */
		$require_lowercase = NBUF_Options::get( 'nbuf_password_require_lowercase', false );
		if ( $require_lowercase && ! preg_match( '/[a-z]/', $password ) ) {
			$errors[] = __( 'Password must contain at least one lowercase letter (a-z).', 'nobloat-user-foundry' );
		}

		/* Number requirement */
		$require_numbers = NBUF_Options::get( 'nbuf_password_require_numbers', false );
		if ( $require_numbers && ! preg_match( '/[0-9]/', $password ) ) {
			$errors[] = __( 'Password must contain at least one number (0-9).', 'nobloat-user-foundry' );
		}

		/* Special character requirement */
		$require_special = NBUF_Options::get( 'nbuf_password_require_special', false );
		if ( $require_special && ! preg_match( '/[!@#$%^&*(),.?":{}|<>\-_=+\[\]\/\\\\]/', $password ) ) {
			$errors[] = __( 'Password must contain at least one special character (!@#$%^&*).', 'nobloat-user-foundry' );
		}

		/* Return errors if any */
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'password_requirements_not_met', implode( ' ', $errors ) );
		}

		return true;
	}

	/**
	 * Should enforce for this context?
	 *
	 * Checks if password requirements should be enforced for the
	 * specified context based on plugin settings.
	 *
	 * @param  string $context Context: 'registration', 'profile_change', or 'reset'.
	 * @return bool True if enforcement enabled for context.
	 */
	public static function should_enforce( $context ) {
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', true );
		if ( ! $enabled ) {
			return false;
		}

		switch ( $context ) {
			case 'registration':
				return NBUF_Options::get( 'nbuf_password_enforce_registration', true );
			case 'profile_change':
				return NBUF_Options::get( 'nbuf_password_enforce_profile_change', true );
			case 'reset':
				return NBUF_Options::get( 'nbuf_password_enforce_reset', true );
			default:
				return false;
		}
	}

	/**
	 * Check if user has weak password flag
	 *
	 * Determines if a user has been flagged for having a weak password.
	 * The actual password validation happens at login time in check_password_at_login().
	 * This method checks the flag status for display purposes and grace period checks.
	 *
	 * @param  int $user_id User ID to check.
	 * @return bool True if user is flagged for weak password.
	 */
	public static function has_weak_password( $user_id ) {
		/* Check if weak password migration is enabled */
		$force_change = NBUF_Options::get( 'nbuf_password_force_weak_change', false );
		if ( ! $force_change ) {
			return false;
		}

		/* Check if user has been flagged */
		$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user_id );
		if ( ! $weak_flagged ) {
			return false;
		}

		/* Check if password was changed after flagging */
		$password_changed = NBUF_User_Data::get_password_changed_at( $user_id );
		if ( $password_changed && strtotime( $password_changed ) > strtotime( $weak_flagged ) ) {
			/* Password was changed after flagging - clear flag */
			NBUF_User_Data::clear_weak_password_flag( $user_id );
			return false;
		}

		return true;
	}

	/**
	 * Check if user needs to change password due to weak password
	 *
	 * Returns true if user is flagged AND grace period has expired.
	 * Used to determine if user should be blocked from accessing the site.
	 *
	 * @param  int $user_id User ID to check.
	 * @return bool True if password change is required now.
	 */
	public static function is_password_change_required( $user_id ) {
		/* Must have weak password flag */
		if ( ! self::has_weak_password( $user_id ) ) {
			return false;
		}

		/* Check grace period */
		$grace_days   = (int) NBUF_Options::get( 'nbuf_password_grace_period', 7 );
		$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user_id );

		if ( $grace_days > 0 && $weak_flagged ) {
			$flagged_timestamp = strtotime( $weak_flagged );
			$grace_expires     = $flagged_timestamp + ( $grace_days * DAY_IN_SECONDS );

			if ( time() < $grace_expires ) {
				return false; /* Still in grace period */
			}
		}

		return true; /* Grace period expired or is 0 */
	}

	/**
	 * Get days remaining in grace period
	 *
	 * Returns the number of days remaining before weak password
	 * enforcement kicks in. Returns 0 if grace period expired.
	 *
	 * @param  int $user_id User ID to check.
	 * @return int Days remaining, or 0 if expired/not flagged.
	 */
	public static function get_grace_period_remaining( $user_id ) {
		$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user_id );
		if ( ! $weak_flagged ) {
			return 0;
		}

		$grace_days        = (int) NBUF_Options::get( 'nbuf_password_grace_period', 7 );
		$flagged_timestamp = strtotime( $weak_flagged );
		$grace_expires     = $flagged_timestamp + ( $grace_days * DAY_IN_SECONDS );
		$remaining         = $grace_expires - time();

		return max( 0, (int) ceil( $remaining / DAY_IN_SECONDS ) );
	}

	/**
	 * Mark user for password change
	 *
	 * Flags a user as having a weak password that needs to be changed.
	 * Used for weak password migration enforcement.
	 *
	 * @param int $user_id User ID to flag.
	 */
	public static function flag_weak_password( $user_id ) {
		NBUF_User_Data::flag_weak_password( $user_id );
	}

	/**
	 * Clear weak password flag
	 *
	 * Removes the weak password flag after user changes password.
	 *
	 * @param int $user_id User ID to clear flag.
	 */
	public static function clear_weak_password_flag( $user_id ) {
		NBUF_User_Data::set_password_changed( $user_id );
	}

	/**
	 * Get password requirements as array
	 *
	 * Returns human-readable list of current password requirements
	 * for display to users.
	 *
	 * @return array List of requirement strings.
	 */
	public static function get_requirements_list() {
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', true );
		if ( ! $enabled ) {
			return array();
		}

		$requirements = array();

		/* Minimum length */
		$min_length = NBUF_Options::get( 'nbuf_password_min_length', 12 );
		/* translators: %d: minimum password length */
		$requirements[] = sprintf( __( 'At least %d characters long', 'nobloat-user-foundry' ), $min_length );

		/* Character type requirements */
		if ( NBUF_Options::get( 'nbuf_password_require_uppercase', false ) ) {
			$requirements[] = __( 'One uppercase letter (A-Z)', 'nobloat-user-foundry' );
		}

		if ( NBUF_Options::get( 'nbuf_password_require_lowercase', false ) ) {
			$requirements[] = __( 'One lowercase letter (a-z)', 'nobloat-user-foundry' );
		}

		if ( NBUF_Options::get( 'nbuf_password_require_numbers', false ) ) {
			$requirements[] = __( 'One number (0-9)', 'nobloat-user-foundry' );
		}

		if ( NBUF_Options::get( 'nbuf_password_require_special', false ) ) {
			$requirements[] = __( 'One special character (!@#$%^&*)', 'nobloat-user-foundry' );
		}

		return $requirements;
	}
}
