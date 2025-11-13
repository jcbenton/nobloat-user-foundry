<?php
/**
 * Admin Action Hooks
 *
 * Registers WordPress action hooks to capture admin-initiated actions
 * and log them to the admin audit log for accountability and compliance.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Includes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Action Hooks Class
 *
 * @since 1.4.0
 */
class NBUF_Admin_Action_Hooks {

	/**
	 * Initialize admin action hooks
	 *
	 * @return void
	 */
	public static function init() {
		/* Tier 1: Critical Logging */
		add_action( 'delete_user', array( __CLASS__, 'log_user_deletion' ), 10, 3 );
		add_action( 'set_user_role', array( __CLASS__, 'log_role_change' ), 10, 3 );

		/* Tier 2: High Priority Logging */
		add_action( 'after_password_reset', array( __CLASS__, 'log_password_reset' ), 10, 2 );

		/* Tier 3: Medium Priority Logging */
		add_action( 'profile_update', array( __CLASS__, 'log_profile_update' ), 10, 2 );

		/* Critical settings changes (hooked in settings class) */
		add_action( 'update_option', array( __CLASS__, 'log_critical_setting_change' ), 10, 3 );
	}

	/**
	 * Log user deletion
	 *
	 * Tier 1 - Critical
	 * Logs when an admin deletes a user account.
	 *
	 * @param int      $user_id  User ID being deleted.
	 * @param int|null $reassign User ID to reassign posts to (null = delete posts).
	 * @param WP_User  $user     User object being deleted.
	 * @return void
	 */
	public static function log_user_deletion( $user_id, $reassign, $user ) {
		/* Get current admin */
		$admin_id = get_current_user_id();

		/* Skip if user is deleting themselves (not an admin action) */
		if ( $admin_id === $user_id ) {
			return;
		}

		/* Skip if no admin user (CLI/cron deletion) */
		if ( ! $admin_id ) {
			return;
		}

		/* Log the deletion */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_USER_DELETED,
			'success',
			sprintf( 'User "%s" (ID: %d) deleted', $user->user_login, $user_id ),
			$user_id,
			array(
				'user_email'  => $user->user_email,
				'user_roles'  => $user->roles,
				'reassign_to' => $reassign,
				'registered'  => $user->user_registered,
			)
		);
	}

	/**
	 * Log role changes
	 *
	 * Tier 1 - Critical
	 * Logs when an admin changes a user's role.
	 *
	 * @param int    $user_id   User ID whose role is being changed.
	 * @param string $role      New role being assigned.
	 * @param array  $old_roles Previous roles.
	 * @return void
	 */
	public static function log_role_change( $user_id, $role, $old_roles ) {
		/* Get current admin */
		$admin_id = get_current_user_id();

		/* Skip if user is changing their own role (unlikely but possible) */
		if ( $admin_id === $user_id ) {
			return;
		}

		/* Skip if no admin user (programmatic change) */
		if ( ! $admin_id ) {
			return;
		}

		/* Get user */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Format old roles */
		$old_roles_str = ! empty( $old_roles ) ? implode( ', ', $old_roles ) : 'none';

		/* Log the role change */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_ROLE_CHANGED,
			'success',
			sprintf( 'Role changed from %s to %s for user "%s"', $old_roles_str, $role, $user->user_login ),
			$user_id,
			array(
				'old_roles' => $old_roles,
				'new_role'  => $role,
			)
		);
	}

	/**
	 * Log password reset by admin
	 *
	 * Tier 2 - High Priority
	 * Logs when an admin resets another user's password.
	 *
	 * @param WP_User $user     User whose password was reset.
	 * @param string  $new_pass New password (not logged for security).
	 * @return void
	 */
	public static function log_password_reset( $user, $new_pass ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress hook signature, not logged for security.
		/* Get current admin */
		$admin_id = get_current_user_id();

		/* Check if admin reset another user's password */
		if ( ! $admin_id || $admin_id === $user->ID ) {
			/* User reset their own password - logged to user audit log instead */
			return;
		}

		/* Log the admin password reset */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_PASSWORD_RESET_BY_ADMIN,
			'success',
			sprintf( 'Password reset for user "%s"', $user->user_login ),
			$user->ID,
			array(
				'user_email' => $user->user_email,
			)
		);
	}

	/**
	 * Log profile update by admin
	 *
	 * Tier 3 - Medium Priority
	 * Logs when an admin edits another user's profile.
	 *
	 * @param int          $user_id       User ID being updated.
	 * @param WP_User|null $old_user_data Old user data before update.
	 * @return void
	 */
	public static function log_profile_update( $user_id, $old_user_data ) {
		/* Get current admin */
		$admin_id = get_current_user_id();

		/* Skip if user is updating their own profile */
		if ( ! $admin_id || $admin_id === $user_id ) {
			return;
		}

		/* Get new user data */
		$new_user_data = get_userdata( $user_id );
		if ( ! $new_user_data || ! $old_user_data ) {
			return;
		}

		/* Detect changed fields */
		$changes = array();

		/* Check email change */
		if ( $old_user_data->user_email !== $new_user_data->user_email ) {
			$changes['email'] = array(
				'old' => $old_user_data->user_email,
				'new' => $new_user_data->user_email,
			);
		}

		/* Check display name change */
		if ( $old_user_data->display_name !== $new_user_data->display_name ) {
			$changes['display_name'] = array(
				'old' => $old_user_data->display_name,
				'new' => $new_user_data->display_name,
			);
		}

		/* Check first name change */
		if ( $old_user_data->first_name !== $new_user_data->first_name ) {
			$changes['first_name'] = array(
				'old' => $old_user_data->first_name,
				'new' => $new_user_data->first_name,
			);
		}

		/* Check last name change */
		if ( $old_user_data->last_name !== $new_user_data->last_name ) {
			$changes['last_name'] = array(
				'old' => $old_user_data->last_name,
				'new' => $new_user_data->last_name,
			);
		}

		/* Check user URL change */
		if ( $old_user_data->user_url !== $new_user_data->user_url ) {
			$changes['user_url'] = array(
				'old' => $old_user_data->user_url,
				'new' => $new_user_data->user_url,
			);
		}

		/* Skip if no changes detected */
		if ( empty( $changes ) ) {
			return;
		}

		/* Determine event type based on changes */
		$event_type = isset( $changes['email'] )
			? NBUF_Admin_Audit_Log::EVENT_EMAIL_CHANGED_BY_ADMIN
			: NBUF_Admin_Audit_Log::EVENT_PROFILE_EDITED_BY_ADMIN;

		/* Build change summary message */
		$changed_fields = array_keys( $changes );
		$message        = sprintf(
			'Profile edited for user "%s" - Changed: %s',
			$new_user_data->user_login,
			implode( ', ', $changed_fields )
		);

		/* Log the profile update */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			$event_type,
			'success',
			$message,
			$user_id,
			array( 'changes' => $changes )
		);
	}

	/**
	 * Log critical setting changes
	 *
	 * Tier 1 - Critical
	 * Logs changes to critical plugin settings.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public static function log_critical_setting_change( $option, $old_value, $new_value ) {
		/* Define critical settings to log */
		$critical_settings = array(
			'nbuf_user_manager_enabled'        => 'Master Toggle',
			'nbuf_require_verification'        => 'Email Verification Required',
			'nbuf_enable_login_limiting'       => 'Login Limiting',
			'nbuf_2fa_email_method'            => '2FA Email Method',
			'nbuf_2fa_authenticator_method'    => '2FA Authenticator Method',
			'nbuf_enable_custom_roles'         => 'Custom Roles',
			'nbuf_logging_user_audit_enabled'  => 'User Audit Logging',
			'nbuf_logging_admin_audit_enabled' => 'Admin Audit Logging',
			'nbuf_logging_security_enabled'    => 'Security Logging',
			'nbuf_password_min_length'         => 'Password Minimum Length',
			'nbuf_password_require_uppercase'  => 'Password Require Uppercase',
			'nbuf_password_require_lowercase'  => 'Password Require Lowercase',
			'nbuf_password_require_numbers'    => 'Password Require Numbers',
			'nbuf_password_require_special'    => 'Password Require Special Chars',
		);

		/* Check if this is a critical setting */
		if ( ! isset( $critical_settings[ $option ] ) ) {
			return;
		}

		/* Skip if values are the same */
		if ( $old_value === $new_value ) {
			return;
		}

		/* Get current admin */
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			/* Programmatic change, not admin action */
			return;
		}

		/* Format values for logging */
		$old_value_str = self::format_setting_value( $old_value );
		$new_value_str = self::format_setting_value( $new_value );

		/* Log the setting change */
		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_SETTINGS_CHANGED,
			'success',
			sprintf(
				'Setting "%s" changed from %s to %s',
				$critical_settings[ $option ],
				$old_value_str,
				$new_value_str
			),
			null,
			array(
				'setting'   => $option,
				'label'     => $critical_settings[ $option ],
				'old_value' => $old_value,
				'new_value' => $new_value,
			)
		);
	}

	/**
	 * Log bulk action
	 *
	 * Tier 2 - High Priority
	 * Helper method for logging bulk operations on users.
	 *
	 * @param string $action   Action name (verify, delete, etc.).
	 * @param array  $user_ids Array of affected user IDs.
	 * @param string $status   'success' or 'failure'.
	 * @param array  $results  Detailed results per user.
	 * @return void
	 */
	public static function log_bulk_action( $action, $user_ids, $status = 'success', $results = array() ) {
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			return;
		}

		NBUF_Admin_Audit_Log::log_bulk_action( $action, $user_ids, $status, $results );
	}

	/**
	 * Log manual verification
	 *
	 * Called when admin manually verifies a user's email.
	 *
	 * @param int $user_id User ID being verified.
	 * @return void
	 */
	public static function log_manual_verification( $user_id ) {
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_MANUAL_VERIFY,
			'success',
			sprintf( 'Manually verified user "%s"', $user->user_login ),
			$user_id,
			array( 'user_email' => $user->user_email )
		);
	}

	/**
	 * Log manual unverification
	 *
	 * Called when admin manually unverifies a user's email.
	 *
	 * @param int $user_id User ID being unverified.
	 * @return void
	 */
	public static function log_manual_unverification( $user_id ) {
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_MANUAL_UNVERIFY,
			'success',
			sprintf( 'Manually unverified user "%s"', $user->user_login ),
			$user_id,
			array( 'user_email' => $user->user_email )
		);
	}

	/**
	 * Log 2FA reset by admin
	 *
	 * Called when admin resets a user's 2FA settings.
	 *
	 * @param int $user_id User ID whose 2FA is being reset.
	 * @return void
	 */
	public static function log_2fa_reset( $user_id ) {
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_2FA_RESET_BY_ADMIN,
			'success',
			sprintf( '2FA reset for user "%s"', $user->user_login ),
			$user_id,
			array( 'user_email' => $user->user_email )
		);
	}

	/**
	 * Log account merge
	 *
	 * Called when admin merges two user accounts.
	 *
	 * @param int   $primary_user_id   Primary user ID (kept).
	 * @param int   $secondary_user_id Secondary user ID (merged and deleted).
	 * @param array $merge_data      Data about what was merged.
	 * @return void
	 */
	public static function log_account_merge( $primary_user_id, $secondary_user_id, $merge_data = array() ) {
		$admin_id = get_current_user_id();
		if ( ! $admin_id ) {
			return;
		}

		$primary_user   = get_userdata( $primary_user_id );
		$secondary_user = get_userdata( $secondary_user_id );

		if ( ! $primary_user || ! $secondary_user ) {
			return;
		}

		NBUF_Admin_Audit_Log::log(
			$admin_id,
			NBUF_Admin_Audit_Log::EVENT_ACCOUNT_MERGE,
			'success',
			sprintf(
				'Merged account "%s" into "%s"',
				$secondary_user->user_login,
				$primary_user->user_login
			),
			$primary_user_id,
			array(
				'primary_user'   => array(
					'id'    => $primary_user_id,
					'login' => $primary_user->user_login,
					'email' => $primary_user->user_email,
				),
				'secondary_user' => array(
					'id'    => $secondary_user_id,
					'login' => $secondary_user->user_login,
					'email' => $secondary_user->user_email,
				),
				'merge_data'     => $merge_data,
			)
		);
	}

	/**
	 * Format setting value for display
	 *
	 * Converts various data types to human-readable strings.
	 *
	 * @param mixed $value Setting value.
	 * @return string Formatted value.
	 */
	private static function format_setting_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'enabled' : 'disabled';
		}

		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}

		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( '' === $value ) {
			return '(empty)';
		}

		return (string) $value;
	}
}
