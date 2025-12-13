<?php
/**
 * NoBloat User Foundry - Activator
 *
 * Handles plugin activation tasks including database setup,
 * template loading, and page creation.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class.
 *
 * Handles all activation tasks for the plugin.
 */
class NBUF_Activator {


	/**
	 * Run activation tasks.
	 *
	 * Creates database tables, loads templates, sets defaults,
	 * and creates required pages.
	 */
	public static function run() {

		// 1. Create DB tables.
		if ( class_exists( 'NBUF_Database' ) ) {
			NBUF_Database::create_table();
			NBUF_Database::create_user_data_table();
			NBUF_Database::create_options_table();
			NBUF_Database::create_user_profile_table();
			NBUF_Database::create_login_attempts_table();
			NBUF_Database::create_user_2fa_table();
			NBUF_Database::create_user_audit_log_table();
			NBUF_Database::create_admin_audit_log_table();
			NBUF_Database::create_user_notes_table();
			NBUF_Database::create_import_history_table();
			NBUF_Database::create_menu_restrictions_table();
			NBUF_Database::create_content_restrictions_table();
			NBUF_Database::create_user_roles_table();

			/* Create security log table */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::create_table();
			}

			/* Update user_data table for privacy controls (add columns if needed) */
			NBUF_Database::update_user_data_table_for_privacy();

			/* Update user_data table for profile/cover photos (add columns if needed) */
			NBUF_Database::update_user_data_table_for_photos();

			/* Update user_data table for password expiration (add columns if needed) */
			NBUF_Database::update_user_data_table_for_password_expiration();

			/* Update user_profile table for account merging (add tertiary_email column) */
			NBUF_Database::update_user_profile_table_for_account_merging();

			/* Create profile versions table for version history */
			NBUF_Database::create_profile_versions_table();

			/* Migrate audit log indexes for existing installations (v1.4.0+) */
			NBUF_Database::migrate_audit_log_indexes();
		}

		// 2. Migrate options from wp_options to custom table (run once).
		self::migrate_to_custom_options();

		// 3. Load default email templates (to custom options table).
		$templates = array(
			'nbuf_email_template_html'         => 'email-verification.html',
			'nbuf_email_template_text'         => 'email-verification.txt',
			'nbuf_welcome_email_html'          => 'welcome-email.html',
			'nbuf_welcome_email_text'          => 'welcome-email.txt',
			'nbuf_expiration_warning_html'     => 'expiration-warning.html',
			'nbuf_expiration_warning_text'     => 'expiration-warning.txt',
			'nbuf_login_form_template'         => 'login-form.html',
			'nbuf_registration_form_template'  => 'registration-form.html',
			'nbuf_account_page_template'       => 'account-page.html',
			'nbuf_request_reset_form_template' => 'request-reset-form.html',
			'nbuf_reset_form_template'         => 'reset-form.html',
			'nbuf_2fa_email_code_html'         => '2fa-email-code.html',
			'nbuf_2fa_email_code_text'         => '2fa-email-code.txt',
			'nbuf_2fa_verify_template'         => '2fa-verify.html',
			'nbuf_2fa_setup_totp_template'     => '2fa-setup-totp.html',
			'nbuf_2fa_backup_codes_template'   => '2fa-backup-codes.html',
			'nbuf_policy_privacy_html'         => 'policy-privacy.html',
			'nbuf_policy_terms_html'           => 'policy-terms.html',
		);
		foreach ( $templates as $option => $file ) {
			$path = NBUF_TEMPLATES_DIR . $file;
			if ( ! NBUF_Options::get( $option ) && file_exists( $path ) ) {
				NBUF_Options::update( $option, file_get_contents( $path ), false, 'templates' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		// 4. Load default CSS templates (to custom options table).
		$css_templates = array(
			'nbuf_reset_page_css'        => 'reset-page.css',
			'nbuf_login_page_css'        => 'login-page.css',
			'nbuf_registration_page_css' => 'registration-page.css',
			'nbuf_account_page_css'      => 'account-page.css',
		);
		foreach ( $css_templates as $option => $file ) {
			$path = NBUF_TEMPLATES_DIR . $file;
			if ( ! NBUF_Options::get( $option ) && file_exists( $path ) ) {
				NBUF_Options::update( $option, file_get_contents( $path ), false, 'css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		// 5. Default settings (to custom options table).
		$is_first_install = ! NBUF_Options::get( 'nbuf_settings' );

		if ( $is_first_install ) {
			/* Master toggle - DISABLED on fresh install to allow migration */
			NBUF_Options::update( 'nbuf_user_manager_enabled', false, true, 'settings' );

			NBUF_Options::update(
				'nbuf_settings',
				array(
					'hooks'                    => array( 'register_new_user' ),
					'hooks_custom'             => '',
					'custom_hook_enabled'      => 0,
					'reverify_on_email_change' => 1,
					'cleanup'                  => array(),
					'auto_verify_existing'     => 1,
				),
				true,
				'settings'
			);

			/* Feature toggle defaults */
			NBUF_Options::update( 'nbuf_require_verification', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_enable_login', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_enable_registration', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_notify_admin_registration', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_enable_password_reset', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_enable_custom_roles', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_redirect_default_login', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_redirect_default_register', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_redirect_default_logout', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_redirect_default_lostpassword', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_redirect_default_resetpass', true, true, 'settings' );

			/* Login attempt limiting defaults */
			NBUF_Options::update( 'nbuf_enable_login_limiting', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_login_max_attempts', 5, true, 'settings' );
			NBUF_Options::update( 'nbuf_login_lockout_duration', 10, true, 'settings' );

			/* Logout behavior defaults */
			NBUF_Options::update( 'nbuf_logout_behavior', 'immediate', true, 'settings' );
			NBUF_Options::update( 'nbuf_logout_redirect', 'home', true, 'settings' );
			NBUF_Options::update( 'nbuf_logout_redirect_custom', '', true, 'settings' );

			/* CSS optimization defaults */
			NBUF_Options::update( 'nbuf_css_load_on_pages', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_css_use_minified', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_css_combine_files', true, true, 'settings' );

			/* Expiration feature defaults */
			NBUF_Options::update( 'nbuf_enable_expiration', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_expiration_warning_days', 7, true, 'settings' );
			NBUF_Options::update( 'nbuf_wc_prevent_active_subs', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_wc_prevent_recent_orders', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_wc_recent_order_days', 90, true, 'settings' );

			/*
			 * Registration field defaults
			 */
			// phpcs:disable -- Comment blocks within array cause indentation conflicts with PHPCS
			NBUF_Options::update(
				'nbuf_registration_fields',
				array(

					/*
					 * Username generation
					 *
					 * phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					 * phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect
					 * Preserved for reference during development.
					 */
					'username_method'      => 'auto_random', // 'user_entered', 'auto_email', 'auto_random'
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Preserved for reference during development
					'login_method'         => 'email_or_username',  // 'email_only', 'username_only', 'email_or_username'
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Preserved for reference during development
					'address_mode'         => 'simplified',  // 'simplified', 'full'

				/* Field configuration: enabled, required, label */
					'first_name_enabled'   => true,
					'first_name_required'  => true,
					'first_name_label'     => 'First Name',

					'last_name_enabled'    => true,
					'last_name_required'   => true,
					'last_name_label'      => 'Last Name',

					'phone_enabled'        => false,
					'phone_required'       => false,
					'phone_label'          => 'Phone Number',

					'company_enabled'      => false,
					'company_required'     => false,
					'company_label'        => 'Company',

					'job_title_enabled'    => false,
					'job_title_required'   => false,
					'job_title_label'      => 'Job Title',

					'address_enabled'      => false,
					'address_required'     => false,
					'address_label'        => 'Address',

					'city_enabled'         => false,
					'city_required'        => false,
					'city_label'           => 'City',

					'state_enabled'        => false,
					'state_required'       => false,
					'state_label'          => 'State/Province',

					'postal_code_enabled'  => false,
					'postal_code_required' => false,
					'postal_code_label'    => 'Postal Code',

					'country_enabled'      => false,
					'country_required'     => false,
					'country_label'        => 'Country',

					'bio_enabled'          => false,
					'bio_required'         => false,
					'bio_label'            => 'Bio',

					'website_enabled'      => false,
					'website_required'     => false,
					'website_label'        => 'Website',
				),
				true,
				'settings'
			);
			// phpcs:enable

			/* Password strength settings */
			NBUF_Options::update( 'nbuf_password_requirements_enabled', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_min_strength', 'medium', true, 'settings' );
			NBUF_Options::update( 'nbuf_password_min_length', 12, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_require_uppercase', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_require_lowercase', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_require_numbers', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_require_special', false, true, 'settings' );

			/* Password enforcement settings */
			NBUF_Options::update( 'nbuf_password_enforce_registration', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_enforce_profile_change', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_enforce_reset', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_admin_bypass', false, true, 'settings' );

			/* Weak password migration settings */
			NBUF_Options::update( 'nbuf_password_force_weak_change', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_check_timing', 'once', true, 'settings' );
			NBUF_Options::update( 'nbuf_password_grace_period', 7, true, 'settings' );

			/* Password Expiration defaults */
			NBUF_Options::update( 'nbuf_password_expiration_enabled', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_expiration_days', 365, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_expiration_admin_bypass', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_password_expiration_warning_days', 7, true, 'settings' );

			/* Two-Factor Authentication - Email defaults */
			NBUF_Options::update( 'nbuf_2fa_email_method', 'disabled', true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_email_code_length', 6, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_email_expiration', 5, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_email_rate_limit', 5, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_email_rate_window', 15, true, 'settings' );

			/* Two-Factor Authentication - TOTP defaults */
			NBUF_Options::update( 'nbuf_2fa_totp_method', 'disabled', true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_totp_code_length', 6, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_totp_time_window', 30, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_totp_tolerance', 1, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_totp_qr_size', 200, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_qr_method', 'external', true, 'settings' );

			/* Two-Factor Authentication - Backup codes defaults */
			NBUF_Options::update( 'nbuf_2fa_backup_enabled', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_backup_count', 10, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_backup_length', 8, true, 'settings' );

			/* Two-Factor Authentication - General defaults */
			NBUF_Options::update( 'nbuf_2fa_device_trust', true, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_admin_bypass', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_lockout_attempts', 5, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_grace_period', 7, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_notify_lockout', false, true, 'settings' );
			NBUF_Options::update( 'nbuf_2fa_notify_disable', false, true, 'settings' );

			/* Audit Log defaults */
			NBUF_Options::update( 'nbuf_audit_log_enabled', true, true, 'audit_log' );
			NBUF_Options::update( 'nbuf_audit_log_retention', '90days', true, 'audit_log' );
			NBUF_Options::update(
				'nbuf_audit_log_events',
				array(
					'authentication' => true,
					'verification'   => true,
					'passwords'      => true,
					'2fa'            => true,
					'account_status' => true,
					'profile'        => false,
				),
				true,
				'audit_log'
			);
			NBUF_Options::update( 'nbuf_audit_log_store_user_agent', true, true, 'audit_log' );
			NBUF_Options::update( 'nbuf_audit_log_anonymize_ip', false, true, 'audit_log' );
			NBUF_Options::update( 'nbuf_audit_log_max_message_length', 500, true, 'audit_log' );

			/*
			 * Enterprise Logging System defaults (v1.4.0+).
			 * Master logging toggles.
			 */
			NBUF_Options::update( 'nbuf_logging_user_audit_enabled', true, true, 'logging' );
			NBUF_Options::update( 'nbuf_logging_admin_audit_enabled', true, true, 'logging' );
			NBUF_Options::update( 'nbuf_logging_security_enabled', true, true, 'logging' );

			/* User Audit Log settings */
			NBUF_Options::update( 'nbuf_logging_user_audit_retention', '365', true, 'logging' );
			NBUF_Options::update(
				'nbuf_logging_user_audit_categories',
				array(
					'authentication' => true,
					'verification'   => true,
					'passwords'      => true,
					'2fa'            => true,
					'account_status' => true,
					'profile'        => false,
				),
				true,
				'logging'
			);

			/* Admin Audit Log settings */
			NBUF_Options::update( 'nbuf_logging_admin_audit_retention', 'forever', true, 'logging' );
			NBUF_Options::update(
				'nbuf_logging_admin_audit_categories',
				array(
					'user_deletion'        => true,
					'role_changes'         => true,
					'settings_changes'     => true,
					'bulk_actions'         => true,
					'manual_verifications' => true,
					'password_resets'      => true,
					'profile_edits'        => true,
				),
				true,
				'logging'
			);

			/* Security Log settings */
			NBUF_Options::update( 'nbuf_logging_security_retention', '90', true, 'logging' );
			NBUF_Options::update(
				'nbuf_logging_security_categories',
				array(
					'file_operations'      => true,
					'csrf_attempts'        => true,
					'privilege_escalation' => true,
					'login_limiting'       => true,
					'import_errors'        => true,
				),
				true,
				'logging'
			);

			/* Privacy settings (all logs) */
			NBUF_Options::update( 'nbuf_logging_anonymize_ip', false, true, 'logging' );
			NBUF_Options::update( 'nbuf_logging_store_user_agent', true, true, 'logging' );

			/* GDPR settings */
			NBUF_Options::update( 'nbuf_logging_include_in_export', true, true, 'logging' );
			NBUF_Options::update( 'nbuf_logging_user_deletion_action', 'anonymize', true, 'logging' );

			/* GDPR defaults */
			NBUF_Options::update( 'nbuf_gdpr_delete_audit_logs', 'anonymize', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_gdpr_include_audit_logs', true, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_gdpr_include_2fa_data', true, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_gdpr_include_login_attempts', false, true, 'gdpr' );

			/* Policy display defaults */
			NBUF_Options::update( 'nbuf_policy_login_enabled', true, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_login_position', 'right', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_registration_enabled', true, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_registration_position', 'right', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_verify_enabled', false, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_verify_position', 'right', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_request_reset_enabled', false, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_request_reset_position', 'right', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_reset_enabled', false, true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_reset_position', 'right', true, 'gdpr' );
			NBUF_Options::update( 'nbuf_policy_account_tab_enabled', false, true, 'gdpr' );

			/* Access Restrictions defaults */
			NBUF_Options::update( 'nbuf_restrictions_enabled', true, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrictions_menu_enabled', true, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrictions_content_enabled', true, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrict_content_shortcode_enabled', false, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrict_widgets_enabled', false, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrict_taxonomies_enabled', false, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrictions_post_types', array( 'post', 'page' ), true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrict_taxonomies_list', array( 'category', 'post_tag' ), true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrictions_hide_from_queries', false, true, 'restrictions' );
			NBUF_Options::update( 'nbuf_restrict_taxonomies_filter_queries', false, true, 'restrictions' );

			/* Profile & Cover Photos defaults */
			NBUF_Options::update( 'nbuf_enable_profiles', false, true, 'profiles' );
			NBUF_Options::update( 'nbuf_enable_public_profiles', false, true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_enable_gravatar', false, true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_page_slug', 'nobloat-profile', true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_default_privacy', 'private', true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_allow_cover_photos', true, true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_max_photo_size', 5, true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_max_cover_size', 10, true, 'profiles' );
			NBUF_Options::update( 'nbuf_profile_custom_css', '', true, 'profiles' );

			/* CSV Import defaults */
			NBUF_Options::update( 'nbuf_import_require_email', true, true, 'tools' );
			NBUF_Options::update( 'nbuf_import_send_welcome', false, true, 'tools' );
			NBUF_Options::update( 'nbuf_import_verify_emails', true, true, 'tools' );
			NBUF_Options::update( 'nbuf_import_default_role', 'subscriber', true, 'tools' );
			NBUF_Options::update( 'nbuf_import_batch_size', 50, true, 'tools' );
			NBUF_Options::update( 'nbuf_import_update_existing', false, true, 'tools' );

			/* Configuration Export/Import defaults */
			NBUF_Options::update( 'nbuf_config_allow_import', true, true, 'tools' );
			NBUF_Options::update( 'nbuf_config_export_sensitive', false, true, 'tools' );

			/* Profile Change Notifications defaults */
			NBUF_Options::update( 'nbuf_notify_profile_changes', false, true, 'notifications' );
			NBUF_Options::update( 'nbuf_notify_new_registrations', false, true, 'notifications' );
			NBUF_Options::update( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ), true, 'notifications' );
			NBUF_Options::update( 'nbuf_notify_profile_changes_fields', array( 'user_email', 'display_name' ), true, 'notifications' );
			NBUF_Options::update( 'nbuf_notify_profile_changes_digest', 'immediate', true, 'notifications' );

			/* Profile Version History defaults */
			NBUF_Options::update( 'nbuf_version_history_enabled', true, true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_user_visible', false, true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_allow_user_revert', false, true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_retention_days', 365, true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_max_versions', 50, true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_ip_tracking', 'anonymized', true, 'version_history' );
			NBUF_Options::update( 'nbuf_version_history_auto_cleanup', true, true, 'version_history' );
		}

		// 6. Auto-verify existing users if enabled.
		$settings = NBUF_Options::get( 'nbuf_settings', array() );
		if ( ! empty( $settings['auto_verify_existing'] ) ) {
			self::verify_all_existing_users();
		}

		// 7. Schedule cleanup and expiration checks.
		if ( class_exists( 'NBUF_Cron' ) ) {
			NBUF_Cron::activate();
		}
		if ( class_exists( 'NBUF_Expiration' ) ) {
			NBUF_Expiration::activate();
		}

		// 8. Create uploads directory structure.
		self::create_upload_directories();

		// 9. Create functional pages with standardized slugs.

		// Verification page.
		self::create_page( 'nbuf_page_verification', 'NoBloat Verification', array( 'nobloat-verify' ), '[nbuf_verify_page]' );

		// Password reset page.
		self::create_page( 'nbuf_page_password_reset', 'NoBloat Password Reset', array( 'nobloat-reset' ), '[nbuf_reset_form]' );

		// Request password reset page.
		self::create_page( 'nbuf_page_request_reset', 'NoBloat Request Password Reset', array( 'nobloat-forgot-password' ), '[nbuf_request_reset_form]' );

		// Login page.
		self::create_page( 'nbuf_page_login', 'NoBloat Login', array( 'nobloat-login' ), '[nbuf_login_form]' );

		// Registration page.
		self::create_page( 'nbuf_page_registration', 'NoBloat Register', array( 'nobloat-register' ), '[nbuf_registration_form]' );

		// Account page.
		self::create_page( 'nbuf_page_account', 'NoBloat User Account', array( 'nobloat-account' ), '[nbuf_account_page]' );

		// Public Profile page.
		self::create_page( 'nbuf_page_profile', 'NoBloat Profile', array( 'nobloat-profile' ), '[nbuf_profile]' );

		// Logout page.
		self::create_page( 'nbuf_page_logout', 'NoBloat Logout', array( 'nobloat-logout' ), '[nbuf_logout]' );

		// Two-Factor Authentication pages.
		self::create_page( 'nbuf_page_2fa_verify', 'NoBloat 2FA Verify', array( 'nobloat-2fa-verify', '2fa-verify' ), '[nbuf_2fa_verify]' );
		self::create_page( 'nbuf_page_2fa_setup', 'NoBloat 2FA Setup', array( 'nobloat-2fa-setup', '2fa-setup' ), '[nbuf_2fa_setup]' );

		// Member Directory page.
		self::create_page( 'nbuf_page_member_directory', 'NoBloat Members', array( 'nobloat-members' ), '[nbuf_members]' );
	}

	/**
	 * Verify all existing users.
	 *
	 * Marks all existing users as verified on first activation.
	 * Includes admins so they retain verified status if downgraded.
	 */
	private static function verify_all_existing_users() {
		if ( ! class_exists( 'NBUF_User_Data' ) ) {
			return;
		}

		$users = get_users( array( 'fields' => 'ID' ) );

		foreach ( $users as $user_id ) {
			// Check if already verified.
			if ( ! NBUF_User_Data::is_verified( $user_id ) ) {
				NBUF_User_Data::set_verified( $user_id );
			}
		}
	}

	/**
	 * Create upload directories.
	 *
	 * Creates the /wp-content/uploads/nobloat/ directory structure
	 * for storing user photos and other plugin uploads.
	 */
	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return; // WordPress upload directory not available.
		}

		$nobloat_dir = trailingslashit( $upload_dir['basedir'] ) . 'nobloat';
		$users_dir   = trailingslashit( $nobloat_dir ) . 'users';

		/* Create main nobloat directory */
		if ( ! file_exists( $nobloat_dir ) ) {
			wp_mkdir_p( $nobloat_dir );
		}

		/* Create users subdirectory */
		if ( ! file_exists( $users_dir ) ) {
			wp_mkdir_p( $users_dir );
		}

		/* Add index.php for directory listing protection */
		$index_content = '<?php // Silence is golden.';
		if ( ! file_exists( $nobloat_dir . '/index.php' ) ) {
			file_put_contents( $nobloat_dir . '/index.php', $index_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $users_dir . '/index.php' ) ) {
			file_put_contents( $users_dir . '/index.php', $index_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Create page.
	 *
	 * Creates a page with specified slug and content, trying multiple
	 * slug options until one succeeds. Checks for existing pages to
	 * prevent duplicates on reactivation.
	 *
	 * @param string $option_key Option key to store page ID.
	 * @param string $title      Page title.
	 * @param array  $slugs      Array of slug options to try.
	 * @param string $content    Page content (shortcode).
	 */
	private static function create_page( $option_key, $title, $slugs, $content ) {
		$user_id = get_current_user_id() ? get_current_user_id() : 1;

		// Check if a page with correct slug already exists.
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				// Page exists at this slug - use it.
				NBUF_Options::update( $option_key, $page->ID, true, 'settings' );
				return;
			}
		}

		// No existing page found, create new one.
		foreach ( $slugs as $slug ) {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_author'  => $user_id,
				)
			);
			if ( ! is_wp_error( $page_id ) ) {
				NBUF_Options::update( $option_key, $page_id, true, 'settings' );
				// Hide page title on NoBloat pages for cleaner appearance.
				update_post_meta( $page_id, '_nbuf_hide_title', '1' );
				break;
			}
		}
	}

	/**
	 * Migrate options from wp_options to custom nbuf_options table
	 *
	 * Runs once during plugin activation/upgrade. Moves all plugin
	 * options from WordPress wp_options table to custom table to
	 * eliminate bloat on non-plugin pages.
	 *
	 * @since 1.0.0
	 */
	private static function migrate_to_custom_options() {
		/* Check if migration already completed */
		if ( NBUF_Options::get( 'nbuf_options_migrated' ) ) {
			return;
		}

		/* Ensure NBUF_Options class is available */
		if ( ! class_exists( 'NBUF_Options' ) ) {
			return;
		}

		/* Define options to migrate with their groups and autoload settings */
		$options_to_migrate = array(
			/* Settings (autoload=1, small, frequently used) */
			'nbuf_user_manager_enabled'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_settings'                         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_password_reset'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_verification'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_login'                       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_registration'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_account'                     => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_logout'                      => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_request_reset'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_require_verification'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_login'                     => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_registration'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_notify_admin_registration'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_password_reset'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_login_limiting'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_login_max_attempts'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_login_lockout_duration'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_behavior'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_redirect'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_redirect_custom'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_load_on_pages'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_use_minified'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_combine_files'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_login'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_register'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_logout'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_lostpassword'    => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_resetpass'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_expiration'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_expiration_warning_days'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_prevent_active_subs'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_prevent_recent_orders'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_recent_order_days'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_requirements_enabled'    => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_min_strength'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_min_length'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_uppercase'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_lowercase'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_numbers'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_special'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_registration'    => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_profile_change'  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_reset'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_admin_bypass'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_force_weak_change'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_check_timing'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_grace_period'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Password Expiration settings */
			'nbuf_password_expiration_enabled'      => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_days'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_admin_bypass' => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_warning_days' => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Two-Factor Authentication settings */
			'nbuf_2fa_email_method'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_code_length'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_expiration'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_rate_limit'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_rate_window'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_method'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_code_length'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_time_window'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_tolerance'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_qr_size'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_qr_method'                    => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_enabled'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_count'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_length'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_device_trust'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_admin_bypass'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_lockout_attempts'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_grace_period'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_notify_lockout'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_notify_disable'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_2fa_verify'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_2fa_setup'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_member_directory'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Templates (autoload=0, large, load on-demand) */
			'nbuf_email_template_html'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_email_template_text'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_welcome_email_html'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_welcome_email_text'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_expiration_warning_html'          => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_expiration_warning_text'          => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_login_form_template'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_registration_form_template'       => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_account_page_template'            => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_request_reset_form_template'      => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_reset_form_template'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_email_code_html'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_email_code_text'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_verify_template'              => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_setup_totp_template'          => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_backup_codes_template'        => array(
				'group'    => 'templates',
				'autoload' => false,
			),

			/* CSS (autoload=0, load on-demand) */
			'nbuf_reset_page_css'                   => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_login_page_css'                   => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_registration_page_css'            => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_account_page_css'                 => array(
				'group'    => 'css',
				'autoload' => false,
			),

			/* System tokens (autoload=0, context-specific) */
			'nbuf_last_cleanup'                     => array(
				'group'    => 'system',
				'autoload' => false,
			),
			'nbuf_css_write_failed'                 => array(
				'group'    => 'system',
				'autoload' => false,
			),
			'nbuf_template_write_failed'            => array(
				'group'    => 'system',
				'autoload' => false,
			),
		);

		/* Migrate each option */
		foreach ( $options_to_migrate as $key => $settings ) {
			$value = get_option( $key );

			/* Only migrate if option exists in wp_options */
			if ( false !== $value ) {
				NBUF_Options::update( $key, $value, $settings['autoload'], $settings['group'] );

				/* Delete from wp_options after successful migration */
				delete_option( $key );
			}
		}

		/* Mark migration as complete */
		NBUF_Options::update( 'nbuf_options_migrated', 1, false, 'system' );
	}
}
