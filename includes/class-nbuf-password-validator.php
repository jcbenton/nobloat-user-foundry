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
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', false );
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
		$min_length = NBUF_Options::get( 'nbuf_password_min_length', 8 );
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
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', false );
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
	 * Check if user has weak password
	 *
	 * Determines if a user's current password meets the strength
	 * requirements. Used for weak password migration feature.
	 *
	 * @param  int $user_id User ID to check.
	 * @return bool True if password is weak and should be changed.
	 */
	public static function has_weak_password( $user_id ) {
		/* Check if weak password migration is enabled */
		$force_change = NBUF_Options::get( 'nbuf_password_force_weak_change', false );
		if ( ! $force_change ) {
			return false;
		}

		/* Check if user has already been flagged or changed password */
		$weak_password_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user_id );
		$password_changed_at   = NBUF_User_Data::get_password_changed_at( $user_id );

		$check_timing = NBUF_Options::get( 'nbuf_password_check_timing', 'once' );

		/* If check timing is "once" and user was already flagged */
		if ( 'once' === $check_timing && $weak_password_flagged ) {
			/* Check if password was changed after flagging */
			if ( $password_changed_at && strtotime( $password_changed_at ) > strtotime( $weak_password_flagged ) ) {
				/* Password was changed, remove flag */
				NBUF_User_Data::clear_weak_password_flag( $user_id );
				return false;
			}
			return true; // Still flagged and not changed.
		}

		/*
		Note: We cannot validate existing password hashes
		*/
		/* This feature requires tracking password changes moving forward */

		return false;
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
		$enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', false );
		if ( ! $enabled ) {
			return array();
		}

		$requirements = array();

		/* Minimum length */
		$min_length = NBUF_Options::get( 'nbuf_password_min_length', 8 );
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
