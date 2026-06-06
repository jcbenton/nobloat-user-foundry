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
	 *
	 * @return void
	 */
	public static function init(): void {
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

		/*
		 * Clear the weak-password lockout flag whenever a password is genuinely
		 * changed. The weak gate (NBUF_Hooks::enforce_verification_before_login,
		 * priority 25) blocks login when weak_password_flagged_at is set past the
		 * grace period, and it runs BEFORE check_password_at_login (priority 29) —
		 * the only path that clears the flag for a now-compliant password. So once
		 * grace expires, a user who resets to a strong password is still locked out
		 * because the clearing code never runs. Clearing on the password-change
		 * events themselves closes that loop. (The account-page change uses
		 * wp_set_password(), which fires neither event, so it clears the flag
		 * inline — see NBUF_Shortcodes password change handler.)
		 */
		add_action( 'after_password_reset', array( __CLASS__, 'clear_lockout_flags_on_reset' ), 10, 2 );
		add_action( 'profile_update', array( __CLASS__, 'clear_lockout_flags_on_profile_update' ), 10, 2 );
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

		/* Admin bypass — shared rule with the front-door and out-of-band weak gates. */
		if ( self::admin_bypasses_weak_gate( $user->ID ) ) {
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
			/*
			 * Record that this user's CURRENT password is policy-compliant, so
			 * out-of-band login paths (passkey/magic link) don't route them to
			 * the change form. See NBUF_Password_Expiration::maybe_get_change_redirect.
			 */
			update_user_meta( $user->ID, '_nbuf_pw_strength_confirmed', 1 );
			return $user;
		}

		/* Password is weak - handle based on check timing setting */
		$check_timing = NBUF_Options::get( 'nbuf_password_check_timing', 'once' );
		$weak_flagged = NBUF_User_Data::get_weak_password_flagged_at( $user->ID );

		/*
		 * Set the "weak password since" timestamp on first detection only.
		 *
		 * SECURITY: previously the "every" timing branch unconditionally
		 * re-flagged on every login, which reset the grace-period clock.
		 * A weak-password user who logged in once a day could keep the
		 * grace window indefinitely open and never be forced to change
		 * their password. The `nbuf_password_check_timing` setting governs
		 * how often we *re-check* the password's strength, NOT how often
		 * we restart the grace clock. The clock starts the first time
		 * weakness is detected and only ever advances when password is
		 * actually changed.
		 */
		if ( ! $weak_flagged ) {
			NBUF_User_Data::flag_weak_password( $user->ID );
			$weak_flagged = current_time( 'mysql', true );
		} elseif ( 'once' === $check_timing ) {
			/*
			 * On "once" timing, also handle the rare case where the user
			 * changed their password after being flagged but the new one
			 * still fails strength (e.g., admin tightened requirements).
			 * Re-anchor the clock to that change so they get a fresh grace
			 * window from the new attempt rather than from the old flag.
			 */
			$password_changed = NBUF_User_Data::get_password_changed_at( $user->ID );
			if ( $password_changed && strtotime( $password_changed . ' GMT' ) > strtotime( $weak_flagged . ' GMT' ) ) {
				NBUF_User_Data::flag_weak_password( $user->ID );
				$weak_flagged = current_time( 'mysql', true );
			}
		}

		/* Check grace period */
		$grace_days = (int) NBUF_Options::get( 'nbuf_password_grace_period', 7 );

		if ( $grace_days > 0 && $weak_flagged ) {
			/* Timestamps stored in GMT - append GMT for consistent interpretation */
			$flagged_timestamp = strtotime( $weak_flagged . ' GMT' );
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
		/*
		 * Only persist the forced-change flag when the expiration subsystem
		 * (the priority-30 `authenticate` filter that consumes it) is actually
		 * active. With it disabled, nothing reads the flag during login, so
		 * writing it here would be a pointless DB mutation on an authentication
		 * this subsystem cannot enforce. The weak-password block is still
		 * applied independently by NBUF_Hooks::enforce_verification_before_login
		 * (priority 25, 'weak_password_expired') on the next login attempt.
		 */
		if ( class_exists( 'NBUF_Password_Expiration' ) && NBUF_Options::get( 'nbuf_password_expiration_enabled', false ) ) {
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
		if ( mb_strlen( $password, 'UTF-8' ) < $min_length ) {
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
		/* Both timestamps stored in GMT - append GMT for consistent interpretation */
		if ( $password_changed && strtotime( $password_changed . ' GMT' ) > strtotime( $weak_flagged . ' GMT' ) ) {
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
			/* Timestamps stored in GMT - append GMT for consistent interpretation */
			$flagged_timestamp = strtotime( $weak_flagged . ' GMT' );
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

		$grace_days = (int) NBUF_Options::get( 'nbuf_password_grace_period', 7 );
		/* Timestamps stored in GMT - append GMT for consistent interpretation */
		$flagged_timestamp = strtotime( $weak_flagged . ' GMT' );
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
	 * @return void
	 */
	public static function flag_weak_password( int $user_id ): void {
		NBUF_User_Data::flag_weak_password( $user_id );
	}

	/**
	 * Clear weak password flag
	 *
	 * Removes the weak password flag after user changes password.
	 *
	 * @param int $user_id User ID to clear flag.
	 * @return void
	 */
	public static function clear_weak_password_flag( int $user_id ): void {
		NBUF_User_Data::set_password_changed( $user_id );
	}

	/**
	 * Clear the password-policy lockout flags after a genuine password change.
	 *
	 * Nulls weak_password_flagged_at (consumed by the priority-25 weak gate) and
	 * the force_password_change flag (priority-28 expiration gate), so a reset or
	 * change actually lets the user back in. Safe to call unconditionally: if the
	 * flags are already clear it is a no-op write, and re-flagging still happens
	 * at the next login if the new password is genuinely weak (with a fresh grace
	 * window), so enforcement is preserved.
	 *
	 * @param int $user_id User whose password just changed.
	 * @return void
	 */
	public static function clear_lockout_flags( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		NBUF_User_Data::clear_weak_password_flag( $user_id );

		if ( class_exists( 'NBUF_Password_Expiration' ) ) {
			NBUF_Password_Expiration::clear_force_password_change( $user_id );
		}
	}

	/**
	 * Record that a user's CURRENT password is policy-compliant.
	 *
	 * Single source of truth for "a validated, compliant password was just set":
	 * clears the lockout flags AND writes the _nbuf_pw_strength_confirmed marker
	 * that out-of-band login paths (passkey / magic link) consult via
	 * NBUF_Password_Expiration::maybe_get_change_redirect(). Every password-change
	 * site that validates strength must call this rather than clearing flags and
	 * setting the marker separately — doing only one of the two is what produced
	 * both the post-reset lockout and the account-page re-prompt divergence.
	 *
	 * Call ONLY after the new password has passed validation (e.g. inside a
	 * should_enforce() branch). For an unvalidated change (admin edit, or a
	 * context with enforcement off) call clear_lockout_flags() instead, which
	 * resolves any stale lockout without falsely asserting compliance.
	 *
	 * @param int $user_id User whose password was just validated compliant.
	 * @return void
	 */
	public static function mark_compliant( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		self::clear_lockout_flags( $user_id );
		update_user_meta( $user_id, '_nbuf_pw_strength_confirmed', 1 );
	}

	/**
	 * Single source of truth for "is this admin exempt from the weak-password gate".
	 *
	 * Keyed on the nbuf_password_admin_bypass option (default false = admins ARE
	 * subject to the weak-password policy). Used identically by the three weak
	 * enforcement sites — the front-door gate (NBUF_Hooks::enforce_verification_
	 * before_login, priority 25), this validator (priority 29), and the
	 * out-of-band gate (NBUF_Password_Expiration::maybe_get_change_redirect) — so
	 * an admin is treated the same whether they log in by password, passkey,
	 * magic link, or 2FA. (Distinct from nbuf_password_expiration_admin_bypass,
	 * which governs the forced/expired gate.)
	 *
	 * @param int $user_id User to test.
	 * @return bool True if the weak gate should be skipped for this admin.
	 */
	public static function admin_bypasses_weak_gate( int $user_id ): bool {
		return (bool) NBUF_Options::get( 'nbuf_password_admin_bypass', false )
			&& user_can( $user_id, 'manage_options' );
	}

	/**
	 * Adapter for the after_password_reset action (reset-link flow).
	 *
	 * @param WP_User|null $user     User whose password was reset.
	 * @param string       $new_pass New password (unused; required by signature).
	 * @return void
	 */
	public static function clear_lockout_flags_on_reset( $user, $new_pass = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $new_pass required by after_password_reset signature.
		if ( $user instanceof WP_User ) {
			self::clear_lockout_flags( $user->ID );
		}
	}

	/**
	 * Adapter for the profile_update action (admin user edits via wp_update_user).
	 *
	 * Only clears when the password hash actually changed, so an ordinary profile
	 * save does not reset the lockout state.
	 *
	 * @param int     $user_id       Updated user ID.
	 * @param WP_User $old_user_data Pre-update user object.
	 * @return void
	 */
	public static function clear_lockout_flags_on_profile_update( $user_id, $old_user_data ): void {
		$new_user = get_userdata( $user_id );

		if ( $new_user && isset( $old_user_data->user_pass ) && $new_user->user_pass !== $old_user_data->user_pass ) {
			self::clear_lockout_flags( (int) $user_id );
		}
	}

	/**
	 * Get password requirements as formatted text string.
	 *
	 * Returns a single-line text description of password requirements
	 * suitable for inline display (e.g., "Minimum 12 characters. Must include: uppercase letter, number").
	 *
	 * @return string Formatted requirements text.
	 */
	public static function get_requirements_text(): string {
		$min_length   = absint( NBUF_Options::get( 'nbuf_password_min_length', 12 ) );
		$requirements = array();

		/* translators: %d: minimum password length */
		$requirements[] = sprintf( __( 'Minimum %d characters', 'nobloat-user-foundry' ), $min_length );

		/* Add character type requirements if password strength is enabled */
		if ( NBUF_Options::get( 'nbuf_password_requirements_enabled', true ) ) {
			if ( NBUF_Options::get( 'nbuf_password_require_uppercase', false ) ) {
				$requirements[] = __( 'uppercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_lowercase', false ) ) {
				$requirements[] = __( 'lowercase letter', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_numbers', false ) ) {
				$requirements[] = __( 'number', 'nobloat-user-foundry' );
			}
			if ( NBUF_Options::get( 'nbuf_password_require_special', false ) ) {
				$requirements[] = __( 'special character', 'nobloat-user-foundry' );
			}
		}

		/* Format requirements text */
		if ( count( $requirements ) > 1 ) {
			$first_req = array_shift( $requirements );
			return $first_req . '. ' . __( 'Must include:', 'nobloat-user-foundry' ) . ' ' . implode( ', ', $requirements );
		}

		return $requirements[0];
	}

	/**
	 * Get password requirements as array
	 *
	 * Returns human-readable list of current password requirements
	 * for display to users.
	 *
	 * @return array<int, string> List of requirement strings.
	 */
	public static function get_requirements_list(): array {
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
