<?php
/**
 * NoBloat User Foundry - Password Expiration System
 *
 * Handles password expiration tracking, enforcement, and forced password changes.
 *
 * Features:
 * - Automatic password change tracking
 * - Configurable password expiration (X days)
 * - Force password change flag (admin-initiated)
 * - Login interception for expired passwords
 * - Force logout all devices
 * - Password age calculation
 * - Bulk actions support
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for password expiration tracking.
 * Custom nbuf_user_data table stores password metadata and cannot use
 * WordPress's standard meta APIs. Caching is not implemented as password
 * data is time-sensitive and caching would create security risks.
 */

/**
 * Class NBUF_Password_Expiration
 *
 * Handles password expiration logic.
 */
class NBUF_Password_Expiration {


	/**
	 * Initialize password expiration system.
	 *
	 * Registers hooks for:
	 * - Tracking password changes
	 * - Login interception
	 * - Enforcement
	 *
	 * @return void
	 */
	public static function init(): void {
		/* Check if age-based password expiration is enabled */
		$enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );

		/*
		 * Forced-change enforcement is INDEPENDENT of the age-based expiration
		 * feature. An admin "require password change" (and the weak-password flow)
		 * sets force_password_change, and that mandate must be honored at login
		 * even when password aging is turned off — otherwise the force is a silent
		 * no-op. So the login interception, the change-form handler, the
		 * login-error redirect, and the force AJAX are registered unconditionally;
		 * check_password_on_login / maybe_get_change_redirect only evaluate the
		 * age-based expiry when $enabled.
		 *
		 * Priority 28: run AFTER WP credential validation (20) and the
		 * verification gate (25), but BEFORE the rate limiter (30) and the 2FA
		 * intercept (31), so the limiter remains the final lockout verdict.
		 * check_password_on_login no-ops on a WP_Error input, so a locked-out
		 * user still sees the lockout.
		 */
		add_filter( 'authenticate', array( __CLASS__, 'check_password_on_login' ), 28, 3 );
		add_action( 'login_form_nbuf_change_expired_password', array( __CLASS__, 'handle_password_change_form' ) );
		add_filter( 'wp_login_errors', array( __CLASS__, 'maybe_redirect_to_password_change' ), 10, 2 );
		add_action( 'wp_ajax_nbuf_force_logout_user', array( __CLASS__, 'ajax_force_logout_user' ) );

		if ( ! $enabled ) {
			return;
		}

		/* Age-based expiration tracking — only when the feature is enabled. */
		add_action( 'password_reset', array( __CLASS__, 'track_password_change' ), 10, 2 );
		add_action( 'profile_update', array( __CLASS__, 'track_password_change_on_profile_update' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'track_password_change_on_registration' ), 10, 1 );
	}

	/**
	 * Track password change on password reset.
	 *
	 * Fires when password is reset via email link.
	 *
	 * @param WP_User $user     User object.
	 * @param string  $new_pass New password.
	 * @return void
	 */
	public static function track_password_change( $user, $new_pass ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $new_pass required by WordPress password_reset action signature
		if ( $user instanceof WP_User ) {
			self::update_password_changed_date( $user->ID );
		}
	}

	/**
	 * Track password change on profile update.
	 *
	 * Fires when user updates their profile. We check if password was actually changed.
	 *
	 * @param int     $user_id       User ID.
	 * @param WP_User $old_user_data Old user data object.
	 * @return void
	 */
	public static function track_password_change_on_profile_update( $user_id, $old_user_data ): void {
		/* Get new user data */
		$new_user = get_userdata( $user_id );

		/* If password hash changed, update timestamp */
		if ( $new_user && isset( $old_user_data->user_pass ) && $new_user->user_pass !== $old_user_data->user_pass ) {
			self::update_password_changed_date( $user_id );
		}
	}

	/**
	 * Track password change on new user registration.
	 *
	 * Sets initial password_changed_at date for new users.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function track_password_change_on_registration( $user_id ): void {
		self::update_password_changed_date( $user_id );
	}

	/**
	 * Update password changed date in database.
	 *
	 * Also clears force_password_change flag and password_expires_at.
	 *
	 * @param  int $user_id User ID.
	 * @return bool Success.
	 */
	public static function update_password_changed_date( int $user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';
		$now        = current_time( 'mysql', true );

		/* Calculate new expiration date if enabled */
		$expiration_days = (int) NBUF_Options::get( 'nbuf_password_expiration_days', 365 );
		$expires_at      = null;

		if ( $expiration_days > 0 ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiration_days} days", strtotime( $now ) ) );
		}

		/*
		* Check if user exists in nbuf_user_data.
		*/
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		if ( $exists ) {
			/*
			 * Clear `force_password_change` here because every caller of this
			 * method gates on an *actual* password change having occurred:
			 *   - track_password_change()                 (password_reset action)
			 *   - track_password_change_on_profile_update (only when the
			 *     user_pass hash actually changed)
			 *   - track_password_change_on_registration   (brand-new user)
			 * A forced-change requirement ("you must change your password")
			 * is satisfied the moment the user genuinely sets a new password,
			 * regardless of which form they used to do it.
			 *
			 * REGRESSION FIX: v1.6.4 stopped clearing this flag to stop an
			 * admin's force-rotate from being "silently undone." But the only
			 * thing that triggers this code path IS a real password change, so
			 * leaving the flag set locked users out permanently: they would
			 * receive a reset email, set a new password, and still be blocked
			 * by check_password_on_login() on the next attempt — with the
			 * front-end login form (wp_signon) having no route to the dedicated
			 * forced-change form (that redirect only fires via wp_login_errors
			 * on wp-login.php). Re-clearing here restores the recovery path.
			 */
			$result = $wpdb->update(
				$table_name,
				array(
					'password_changed_at'   => $now,
					'password_expires_at'   => $expires_at,
					'force_password_change' => 0,
				),
				array( 'user_id' => $user_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			/* Insert new record */
			$result = $wpdb->insert(
				$table_name,
				array(
					'user_id'               => $user_id,
					'password_changed_at'   => $now,
					'password_expires_at'   => $expires_at,
					'force_password_change' => 0,
					'is_verified'           => 0,
					'is_disabled'           => 0,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d' )
			);
		}

		return false !== $result;
	}

	/**
	 * Check password on login.
	 *
	 * Intercepts login to check if:
	 * 1. Password has expired
	 * 2. Admin has forced password change
	 *
	 * If either is true, redirect to password change form.
	 *
	 * @param  WP_User|WP_Error|null $user     User object or error.
	 * @param  string                $username Username.
	 * @param  string                $password Password.
	 * @return WP_User|WP_Error User object or error.
	 */
	public static function check_password_on_login( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $username, $password required by WordPress authenticate filter signature
		/* Only proceed if we have a valid user */
		if ( ! $user instanceof WP_User ) {
			return $user;
		}

		/* Skip for administrators if bypass enabled */
		$admin_bypass = NBUF_Options::get( 'nbuf_password_expiration_admin_bypass', true );
		if ( $admin_bypass && user_can( $user, 'manage_options' ) ) {
			return $user;
		}

		/* Forced change is honored regardless of the age-based expiration toggle. */
		$force_change = self::is_password_change_forced( $user->ID );

		/* Age-based expiry only applies when the expiration feature is enabled. */
		$is_expired = NBUF_Options::get( 'nbuf_password_expiration_enabled', false )
			? self::is_password_expired( $user->ID )
			: false;

		/* If either condition is true, store a random token and redirect */
		if ( $force_change || $is_expired ) {
			$change_token = bin2hex( random_bytes( 32 ) );
			/* 5-minute TTL: ample to complete the change form, short enough to limit replay of a leaked URL. */
			set_transient( 'nbuf_password_change_token_' . $change_token, $user->ID, 300 );

			/* Create error with redirect flag — include token for the password change form */
			$message = $force_change
			? __( 'Your password must be changed before you can continue.', 'nobloat-user-foundry' )
			: __( 'Your password has expired. Please choose a new password.', 'nobloat-user-foundry' );

			/* Store the token so the login page can build the correct redirect URL */
			set_transient( 'nbuf_password_change_redirect_' . $user->ID, $change_token, 300 );

			return new WP_Error( 'nbuf_password_change_required', $message );
		}

		return $user;
	}

	/**
	 * Redirect to the password change form when the login error indicates it's required.
	 *
	 * @param WP_Error $errors   Login errors.
	 * @param string   $redirect Redirect URL.
	 * @return WP_Error Errors (may not return if redirecting).
	 */
	public static function maybe_redirect_to_password_change( $errors, $redirect ) {
		if ( is_wp_error( $errors ) && $errors->get_error_code() === 'nbuf_password_change_required' ) {
			// Find the token from the login attempt — look up by submitted username.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reading login field for redirect only.
			$login = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '';
			$user  = get_user_by( 'login', $login );
			if ( ! $user ) {
				$user = get_user_by( 'email', $login );
			}
			if ( $user ) {
				$token = get_transient( 'nbuf_password_change_redirect_' . $user->ID );
				if ( $token ) {
					wp_safe_redirect( site_url( 'wp-login.php?action=nbuf_change_expired_password&change_token=' . $token ) );
					exit;
				}
			}
		}
		return $errors;
	}

	/**
	 * Check if password is expired for a user.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if password is expired.
	 */
	public static function is_password_expired( int $user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$expires_at = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT password_expires_at FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		/* If no expiration date set, password is not expired */
		if ( empty( $expires_at ) ) {
			return false;
		}

		/* Compare with current time */
		$now = current_time( 'mysql', true );
		return $expires_at < $now;
	}

	/**
	 * Check if password change is forced for a user.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if password change is forced.
	 */
	public static function is_password_change_forced( int $user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$force_change = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT force_password_change FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		return (bool) $force_change;
	}

	/**
	 * Force password change for a user.
	 *
	 * Sets force_password_change flag to 1.
	 *
	 * @param  int $user_id User ID.
	 * @return bool Success.
	 */
	public static function force_password_change( int $user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/*
		* Check if user exists in nbuf_user_data.
		*/
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		if ( $exists ) {
			/* Update existing record */
			$result = $wpdb->update(
				$table_name,
				array( 'force_password_change' => 1 ),
				array( 'user_id' => $user_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			/* Insert new record */
			$result = $wpdb->insert(
				$table_name,
				array(
					'user_id'               => $user_id,
					'force_password_change' => 1,
					'is_verified'           => 0,
					'is_disabled'           => 0,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}

		return false !== $result;
	}

	/**
	 * Clear force password change flag for a user.
	 *
	 * @param  int $user_id User ID.
	 * @return bool Success.
	 */
	public static function clear_force_password_change( int $user_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		return $wpdb->update(
			$table_name,
			array( 'force_password_change' => 0 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Get password age in days.
	 *
	 * @param  int $user_id User ID.
	 * @return int|null Password age in days, or null if never changed.
	 */
	public static function get_password_age( int $user_id ): ?int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$changed_at = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT password_changed_at FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		if ( empty( $changed_at ) ) {
			return null;
		}

		$now               = time();
		$changed_timestamp = strtotime( $changed_at );
		$diff_seconds      = $now - $changed_timestamp;
		$diff_days         = floor( $diff_seconds / DAY_IN_SECONDS );

		return (int) $diff_days;
	}

	/**
	 * Get days until password expires.
	 *
	 * @param  int $user_id User ID.
	 * @return int|null Days until expiration, negative if expired, null if no expiration.
	 */
	public static function get_days_until_expiration( int $user_id ): ?int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		$expires_at = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT password_expires_at FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		if ( empty( $expires_at ) ) {
			return null;
		}

		$now               = time();
		$expires_timestamp = strtotime( $expires_at );
		$diff_seconds      = $expires_timestamp - $now;
		$diff_days         = ceil( $diff_seconds / DAY_IN_SECONDS );

		return (int) $diff_days;
	}

	/**
	 * Force logout all devices for a user.
	 *
	 * Destroys all active sessions for a user, forcing re-authentication.
	 *
	 * @param  int $user_id User ID.
	 * @return bool Success.
	 */
	public static function force_logout_all_devices( int $user_id ): bool {
		/* Get user's session manager */
		$sessions = WP_Session_Tokens::get_instance( $user_id );

		/* Destroy all sessions */
		$sessions->destroy_all();

		/* Log the action */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'password_logout_all_devices',
				'success',
				__( 'All devices logged out by administrator.', 'nobloat-user-foundry' )
			);
		}

		return true;
	}

	/**
	 * Handle password change form.
	 *
	 * Displays password change form and processes submission.
	 *
	 * @return void
	 */
	public static function handle_password_change_form(): void {
		/* Check if user is logged in */
		$user_id   = get_current_user_id();
		$via_token = false;

		/* If not logged in, verify via cryptographic token (not predictable user_id) */
		if ( ! $user_id && isset( $_GET['change_token'] ) ) {
			$change_token = sanitize_text_field( wp_unslash( $_GET['change_token'] ) );
			$token_user   = get_transient( 'nbuf_password_change_token_' . $change_token );
			if ( ! $token_user ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}
			$user_id   = (int) $token_user;
			$via_token = true;

			/*
			 * Token is NOT consumed here — it must survive until the form POST.
			 * The short (5-minute) TTL limits replay of a leaked URL.
			 * Token is consumed after successful password change (line ~556).
			 */
		}

		if ( ! $user_id ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		/*
		 * OOB session-mint gate. The UNAUTHENTICATED token-redeem path below mints
		 * a full login session (wp_set_auth_cookie) WITHOUT running the authenticate
		 * filter chain, so the priority-1 IP gate and the account-state gates never
		 * re-evaluate at redeem time -- the same out-of-band bypass class closed for
		 * magic-link / passkey / 2FA-completion in v1.7.31. Enforce the same single
		 * source of truth (NBUF_Auth::enforce_login_status) BEFORE any password
		 * change or session mint. Only the token path is gated; an already
		 * logged-in user keeps the session they already hold, so this adds no new
		 * wrong-block. No-op on default installs (IP restriction off, account ok).
		 */
		if ( $via_token && class_exists( 'NBUF_Auth' ) ) {
			$oob_status = NBUF_Auth::enforce_login_status( (int) $user_id );
			if ( is_wp_error( $oob_status ) ) {
				wp_safe_redirect( add_query_arg( 'nbuf_login_error', $oob_status->get_error_code(), wp_login_url() ) );
				exit;
			}
		}

		/* Verify authorization BEFORE rendering form - prevent IDOR */
		$current_user_id = get_current_user_id();
		if ( $current_user_id && $current_user_id !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not authorized to change this password.', 'nobloat-user-foundry' ), 403 );
		}

		/* Handle form submission */
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $request_method && isset( $_POST['nbuf_change_password_nonce'] ) ) {
			check_admin_referer( 'nbuf_change_password_' . $user_id, 'nbuf_change_password_nonce' );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized upfront.
			$new_password = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized.
			$confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';

			$errors = array();

			if ( empty( $new_password ) ) {
				$errors[] = __( 'Password cannot be empty.', 'nobloat-user-foundry' );
			}

			/* Validate passwords match */
			if ( $new_password !== $confirm_password ) {
				$errors[] = __( 'Passwords do not match.', 'nobloat-user-foundry' );
			}

			/* Validate password strength (if enabled) */
			if ( class_exists( 'NBUF_Password_Validator' ) ) {
				$strength_check = NBUF_Password_Validator::validate( $new_password, $user_id );
				if ( is_wp_error( $strength_check ) ) {
					$errors[] = $strength_check->get_error_message();
				}
			}

			/* If no errors, update password */
			if ( empty( $errors ) ) {
				wp_set_password( $new_password, $user_id );

				/*
				 * The new password passed validation above. Record it compliant in
				 * one step: clears weak_password_flagged_at AND force_password_change
				 * AND sets _nbuf_pw_strength_confirmed. wp_set_password() fires none of
				 * the password-change hooks that normally clear these, so without this
				 * a user who just set a STRONG password stays hard-blocked by the
				 * priority-25 weak_password_expired gate (which reads
				 * weak_password_flagged_at raw) -> permanent lockout + redirect loop
				 * back to this very form. (mark_compliant subsumes the previous
				 * separate clear_weak_password_flag + clear_force_password_change +
				 * marker writes.)
				 */
				if ( class_exists( 'NBUF_Password_Validator' ) ) {
					NBUF_Password_Validator::mark_compliant( $user_id );
				} else {
					/* Validator unavailable: still clear the force flag we set. */
					self::clear_force_password_change( $user_id );
				}

				/*
				 * SECURITY: clean up the change-token transient regardless of
				 * which path the user reached the form through. If $user_id
				 * came from get_current_user_id() (logged-in path), the
				 * change_token transient pointed at by the redirect-key
				 * transient was being orphaned for up to 10 minutes — leaving
				 * a usable URL that could re-enter the form unauthenticated
				 * after the user logged out (e.g., from browser history /
				 * shared-device replay).
				 */
				if ( isset( $change_token ) && '' !== $change_token ) {
					delete_transient( 'nbuf_password_change_token_' . $change_token );
				} else {
					$pending_token = get_transient( 'nbuf_password_change_redirect_' . $user_id );
					if ( $pending_token ) {
						delete_transient( 'nbuf_password_change_token_' . $pending_token );
					}
				}
				delete_transient( 'nbuf_password_change_redirect_' . $user_id );

				/* (force_password_change was cleared above via mark_compliant.) */

				/*
				 * 2FA PARITY (token path only): the UNAUTHENTICATED token-redeem
				 * path mints a full session out-of-band (no authenticate filter
				 * runs), so the priority-31 2FA interception never fired and an
				 * OOB user (magic-link / passkey / front-door bounce) redirected
				 * here could obtain a session WITHOUT a second factor. Hand off to
				 * the 2FA challenge BEFORE minting the session. The password is
				 * already changed and the lockout flags cleared, so once the user
				 * clears 2FA, complete_login mints the final session normally.
				 * Mirrors NBUF_2FA_Login::intercept_login (incl. admin-bypass).
				 * An already-logged-in user is NOT re-challenged — they
				 * authenticated (and passed any 2FA) earlier this session.
				 * begin_2fa_challenge() redirects + exits.
				 */
				if ( $via_token && class_exists( 'NBUF_2FA_Login' ) && class_exists( 'NBUF_2FA' )
					&& ! ( NBUF_Options::get( 'nbuf_2fa_admin_bypass', false ) && user_can( $user_id, 'manage_options' ) )
					&& NBUF_2FA::should_challenge( $user_id ) ) {
					NBUF_2FA_Login::begin_2fa_challenge( $user );
				}

				/* Regenerate session to prevent session fixation */
				wp_clear_auth_cookie();
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- wp_login is a core WordPress hook.
				do_action( 'wp_login', $user->user_login, $user );

				/* Redirect to admin or home */
				$redirect_to = user_can( $user, 'edit_posts' ) ? admin_url() : home_url();
				/* Apply admin-access restriction (non-admin → /wp-admin/ rewrite). */
				if ( class_exists( 'NBUF_Hooks' ) && method_exists( 'NBUF_Hooks', 'sanitize_post_login_redirect' ) ) {
					$redirect_to = NBUF_Hooks::sanitize_post_login_redirect( (string) $redirect_to, (int) $user->ID );
				}
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		/* Display password change form */
		self::render_password_change_form( $user, $errors ?? array(), $change_token ?? '' );
		exit;
	}

	/**
	 * Render password change form.
	 *
	 * @param WP_User            $user         User object.
	 * @param array<int, string> $errors       Array of error messages.
	 * @param string             $change_token Single-use token bound to this password-change session.
	 * @return void
	 */
	public static function render_password_change_form( WP_User $user, array $errors = array(), string $change_token = '' ): void {
		/* Load WordPress login header */
		login_header( __( 'Password Change Required', 'nobloat-user-foundry' ), '', $errors );

		/* Check why password change is required */
		$is_forced  = self::is_password_change_forced( $user->ID );
		$is_expired = self::is_password_expired( $user->ID );

		$message = $is_forced
		? __( 'Your administrator has required you to change your password before continuing.', 'nobloat-user-foundry' )
		: __( 'Your password has expired. Please choose a new password to continue.', 'nobloat-user-foundry' );

		?>
		<div class="nbuf-password-change-form">
			<p class="message"><?php echo esc_html( $message ); ?></p>

		<?php if ( ! empty( $errors ) ) : ?>
				<div id="login_error">
			<?php foreach ( $errors as $error ) : ?>
						<p><?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
				</div>
		<?php endif; ?>

			<form name="nbuf_change_password_form" id="nbuf_change_password_form" action="<?php echo esc_url( site_url( 'wp-login.php?action=nbuf_change_expired_password&change_token=' . $change_token, 'login_post' ) ); ?>" method="post">
		<?php wp_nonce_field( 'nbuf_change_password_' . $user->ID, 'nbuf_change_password_nonce' ); ?>

				<p>
					<label for="new_password"><?php esc_html_e( 'New Password', 'nobloat-user-foundry' ); ?><br />
					<input type="password" name="new_password" id="new_password" class="input" value="" size="20" autocomplete="off" required /></label>
				</p>

				<p>
					<label for="confirm_password"><?php esc_html_e( 'Confirm Password', 'nobloat-user-foundry' ); ?><br />
					<input type="password" name="confirm_password" id="confirm_password" class="input" value="" size="20" autocomplete="off" required /></label>
				</p>

				<p class="submit">
					<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Change Password', 'nobloat-user-foundry' ); ?>" />
				</p>
			</form>

			<p id="nav">
				<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in as different user', 'nobloat-user-foundry' ); ?></a>
			</p>
		</div>

		<script type="text/javascript">
		document.getElementById('new_password').focus();
		</script>

		<?php
		login_footer();
	}

	/**
	 * AJAX handler for force logout user.
	 *
	 * Called from user edit screen when admin clicks "Force Logout All Devices" button.
	 *
	 * @return void
	 */
	public static function ajax_force_logout_user(): void {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_force_logout', 'nonce' );

		/* Check capabilities */
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'nobloat-user-foundry' ) ) );
		}

		/* Get user ID */
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'nobloat-user-foundry' ) ) );
		}

		/*
		 * SECURITY: enforce per-target capability and super-admin protection.
		 *  - edit_user (singular, with $user_id) is the WP meta-cap that maps
		 *    through map_meta_cap so plugins can deny editing of specific users.
		 *  - On multisite, only a super admin may terminate another super
		 *    admin's sessions.
		 */
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage this user.', 'nobloat-user-foundry' ) ) );
		}

		if ( is_multisite() && is_super_admin( $user_id ) && ! is_super_admin( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage this user.', 'nobloat-user-foundry' ) ) );
		}

		/* Force logout */
		$result = self::force_logout_all_devices( $user_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'All devices logged out successfully.', 'nobloat-user-foundry' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to logout devices.', 'nobloat-user-foundry' ) ) );
		}
	}

	/**
	 * Recalculate password expiration for all users.
	 *
	 * Called when settings are changed. Updates password_expires_at based on
	 * password_changed_at + expiration_days setting.
	 *
	 * @return int Number of users updated.
	 */
	public static function recalculate_all_expirations(): int {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_data';
		$expiration_days = (int) NBUF_Options::get( 'nbuf_password_expiration_days', 365 );

		if ( $expiration_days <= 0 ) {
			/* Clear all expirations */
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET password_expires_at = NULL WHERE password_expires_at IS NOT NULL', $table_name ) );
			return 0;
		}

		/* Update all users with password_changed_at */
		return $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET password_expires_at = DATE_ADD(password_changed_at, INTERVAL %d DAY) WHERE password_changed_at IS NOT NULL',
				$table_name,
				$expiration_days
			)
		);
	}

	/**
	 * One-time cleanup of stale force_password_change flags.
	 *
	 * Between v1.6.4 and the fix in update_password_changed_date(), a genuine
	 * password change (reset link, profile change) updated password_changed_at
	 * but failed to clear force_password_change. Affected users were therefore
	 * permanently blocked at login by check_password_on_login() no matter how
	 * many times they reset their password.
	 *
	 * This clears the flag ONLY where there is positive evidence that the user
	 * already changed their password after being flagged: the weak-password
	 * migration set weak_password_flagged_at, and password_changed_at is newer.
	 * That is exactly the "stale" definition has_weak_password() uses, so there
	 * are no false positives — we never undo a still-pending requirement.
	 *
	 * Admin-initiated forces with no weak_password_flagged_at carry no reliable
	 * "changed since forced" signal in the schema and are intentionally left
	 * alone; those now self-heal on the user's next password change thanks to
	 * the update_password_changed_date() fix.
	 *
	 * Idempotent and safe to call on every upgrade; the caller gates it behind
	 * a one-shot option so it normally runs once.
	 *
	 * @return int Number of users whose stale flag was cleared.
	 */
	public static function migrate_clear_stale_force_flags(): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/*
		 * Guard: the repair predicate references weak_password_flagged_at. On a
		 * very old schema that predates the column the UPDATE would error and
		 * (int) cast the false result to 0 — silently reporting "0 cleared" and
		 * leaving affected users locked out with no operator signal. Skip
		 * cleanly when the column is absent.
		 */
		$has_column = $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, 'weak_password_flagged_at' )
		);
		if ( ! $has_column ) {
			return 0;
		}

		$cleared = (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET force_password_change = 0
				 WHERE force_password_change = 1
				   AND weak_password_flagged_at IS NOT NULL
				   AND password_changed_at IS NOT NULL
				   AND password_changed_at > weak_password_flagged_at',
				$table_name
			)
		);

		if ( $cleared > 0 && class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				0,
				'force_password_flag_cleanup',
				'info',
				/* translators: %d: number of users whose stale forced-change flag was cleared. */
				sprintf( __( 'Cleared stale forced-password-change flag for %d user(s) after upgrade.', 'nobloat-user-foundry' ), $cleared )
			);
		}

		return $cleared;
	}

	/**
	 * Resolve a forced-password-change redirect for a user, if one is required.
	 *
	 * Centralizes the post-authentication password gates that the password
	 * login path enforces via the `authenticate` filter (check_password_on_login
	 * at priority 28 + check_password_at_login at priority 29). Out-of-band
	 * session-minting paths (magic links, passkeys) never run `authenticate`,
	 * so they must call this to avoid silently bypassing an admin-mandated or
	 * policy-mandated password change. Mirrors both filters' admin-bypass logic.
	 *
	 * @param  int $user_id User ID.
	 * @return string|null  URL of the forced-change form, or null if no change is required.
	 */
	public static function maybe_get_change_redirect( int $user_id ): ?string {
		$needs_change = false;

		/*
		 * Forced change / expiration (mirrors check_password_on_login, priority 28).
		 * A forced change (admin "require change" / weak flow) is honored regardless
		 * of the age-based expiration toggle; password aging only when enabled.
		 */
		$forced_admin_bypass = NBUF_Options::get( 'nbuf_password_expiration_admin_bypass', true );
		$expiration_enabled  = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
		if ( ! ( $forced_admin_bypass && user_can( $user_id, 'manage_options' ) ) ) {
			if ( self::is_password_change_forced( $user_id )
				|| ( $expiration_enabled && self::is_password_expired( $user_id ) ) ) {
				$needs_change = true;
			}
		}

		/*
		 * Weak-password migration. OOB login paths (passkey, magic link) never
		 * run the priority-29 validator, so the weak flag is never set for
		 * OOB-only users and is_password_change_required() can't see them. When
		 * force_weak_change is on, ALSO route a user who has never had their
		 * password strength confirmed (no _nbuf_pw_strength_confirmed marker)
		 * through the change form once, so they establish a policy-compliant
		 * password. The marker is set on a strong password-login, a completed
		 * forced change, or a policy-validated reset — so this fires at most
		 * once per user.
		 */
		if ( ! $needs_change
			&& NBUF_Options::get( 'nbuf_password_force_weak_change', false )
			&& class_exists( 'NBUF_Password_Validator' ) ) {
			if ( ! NBUF_Password_Validator::admin_bypasses_weak_gate( $user_id ) ) {
				if ( NBUF_Password_Validator::is_password_change_required( $user_id )
					|| ! get_user_meta( $user_id, '_nbuf_pw_strength_confirmed', true ) ) {
					$needs_change = true;
				}
			}
		}

		if ( ! $needs_change ) {
			return null;
		}

		/* Mint the same single-user cryptographic token the password path uses (5-minute TTL). */
		$change_token = bin2hex( random_bytes( 32 ) );
		set_transient( 'nbuf_password_change_token_' . $change_token, $user_id, 300 );

		return site_url( 'wp-login.php?action=nbuf_change_expired_password&change_token=' . rawurlencode( $change_token ) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
