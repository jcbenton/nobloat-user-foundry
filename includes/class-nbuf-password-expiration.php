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
	 */
	public static function init() {
		/* Check if password expiration is enabled */
		$enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );

		if ( ! $enabled ) {
			return;
		}

		/* Track password changes automatically */
		add_action( 'password_reset', array( __CLASS__, 'track_password_change' ), 10, 2 );
		add_action( 'profile_update', array( __CLASS__, 'track_password_change_on_profile_update' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'track_password_change_on_registration' ), 10, 1 );

		/* Login interception - check if password expired or forced change */
		add_filter( 'authenticate', array( __CLASS__, 'check_password_on_login' ), 30, 3 );

		/* Password change form handler */
		add_action( 'login_form_nbuf_change_expired_password', array( __CLASS__, 'handle_password_change_form' ) );

		/* AJAX handler for force logout */
		add_action( 'wp_ajax_nbuf_force_logout_user', array( __CLASS__, 'ajax_force_logout_user' ) );
	}

	/**
	 * Track password change on password reset.
	 *
	 * Fires when password is reset via email link.
	 *
	 * @param WP_User $user     User object.
	 * @param string  $new_pass New password.
	 */
	public static function track_password_change( $user, $new_pass ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $new_pass required by WordPress password_reset action signature
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
	 */
	public static function track_password_change_on_profile_update( $user_id, $old_user_data ) {
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
	 */
	public static function track_password_change_on_registration( $user_id ) {
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
	public static function update_password_changed_date( $user_id ) {
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
			/* Update existing record */
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

		/* Check if password change is forced */
		$force_change = self::is_password_change_forced( $user->ID );

		/* Check if password is expired */
		$is_expired = self::is_password_expired( $user->ID );

		/* If either condition is true, store user ID and redirect */
		if ( $force_change || $is_expired ) {
			/* Store user ID in transient for password change form */
			set_transient( 'nbuf_password_change_user_' . $user->ID, $user->ID, 600 );

			/* Create error with redirect flag */
			$message = $force_change
			? __( 'Your password must be changed before you can continue.', 'nobloat-user-foundry' )
			: __( 'Your password has expired. Please choose a new password.', 'nobloat-user-foundry' );

			return new WP_Error( 'nbuf_password_change_required', $message );
		}

		return $user;
	}

	/**
	 * Check if password is expired for a user.
	 *
	 * @param  int $user_id User ID.
	 * @return bool True if password is expired.
	 */
	public static function is_password_expired( $user_id ) {
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
	public static function is_password_change_forced( $user_id ) {
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
	public static function force_password_change( $user_id ) {
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
	public static function clear_force_password_change( $user_id ) {
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
	public static function get_password_age( $user_id ) {
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
	public static function get_days_until_expiration( $user_id ) {
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
	public static function force_logout_all_devices( $user_id ) {
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
	 */
	public static function handle_password_change_form() {
		/* Check if user ID is in transient */
		$user_id = get_current_user_id();

		/* If no user ID, try to get from URL parameter (for logged out users) */
		if ( ! $user_id && isset( $_GET['user_id'] ) ) {
			$user_id = (int) $_GET['user_id'];

			/* Verify transient exists for this user */
			$transient = get_transient( 'nbuf_password_change_user_' . $user_id );
			if ( ! $transient ) {
				wp_safe_redirect( wp_login_url() );
				exit;
			}
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

		/* Verify authorization BEFORE rendering form - prevent IDOR */
		$current_user_id = get_current_user_id();
		if ( $current_user_id && $current_user_id !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not authorized to change this password.', 'nobloat-user-foundry' ), 403 );
		}

		/* Handle form submission */
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nbuf_change_password_nonce'] ) ) {
			check_admin_referer( 'nbuf_change_password_' . $user_id, 'nbuf_change_password_nonce' );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized upfront.

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized upfront.
			$new_password = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords validated by wp_set_password, not sanitized.
			$confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';

			$errors = array();

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

				/* Clear transient */
				delete_transient( 'nbuf_password_change_user_' . $user_id );

				/* Regenerate session to prevent session fixation */
				wp_clear_auth_cookie();
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- wp_login is a core WordPress hook.
				do_action( 'wp_login', $user->user_login, $user );

				/* Redirect to admin or home */
				$redirect_to = user_can( $user, 'edit_posts' ) ? admin_url() : home_url();
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		/* Display password change form */
		self::render_password_change_form( $user, $errors ?? array() );
		exit;
	}

	/**
	 * Render password change form.
	 *
	 * @param WP_User $user   User object.
	 * @param array   $errors Array of error messages.
	 */
	public static function render_password_change_form( $user, $errors = array() ) {
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

			<form name="nbuf_change_password_form" id="nbuf_change_password_form" action="<?php echo esc_url( site_url( 'wp-login.php?action=nbuf_change_expired_password&user_id=' . $user->ID, 'login_post' ) ); ?>" method="post">
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
	 */
	public static function ajax_force_logout_user() {
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
	public static function recalculate_all_expirations() {
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
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
