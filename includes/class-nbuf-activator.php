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
	 *
	 * @return void
	 */
	public static function run(): void {
		$debug_timing = defined( 'NBUF_DEBUG_ACTIVATION' ) && NBUF_DEBUG_ACTIVATION;
		$timings      = array();
		$start_time   = microtime( true );

		// 1. Create DB tables.
		if ( class_exists( 'NBUF_Database' ) ) {
			NBUF_Database::create_table();
			NBUF_Database::create_user_data_table();
			NBUF_Database::create_options_table();
			NBUF_Database::create_user_profile_table();
			NBUF_Database::create_login_attempts_table();
			NBUF_Database::create_user_2fa_table();
			NBUF_Database::create_user_passkeys_table();
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

			/* Update user_data table for last login tracking */
			NBUF_Database::update_user_data_table_for_last_login();

			/* Create profile versions table for version history */
			NBUF_Database::create_profile_versions_table();

			/* Create webhooks tables */
			NBUF_Database::create_webhooks_table();
			NBUF_Database::create_webhook_log_table();

			/* Create Terms of Service tables */
			NBUF_Database::create_tos_versions_table();
			NBUF_Database::create_tos_acceptances_table();

			/* Migrate audit log indexes for existing installations (v1.4.0+) */
			NBUF_Database::migrate_audit_log_indexes();

			/* Migrate tokens table indexes (v1.5.0+) */
			NBUF_Database::migrate_tokens_indexes();

			/* Migrate tokens table type column for magic links (v1.5.2+) */
			NBUF_Database::migrate_tokens_type_column();

			/* Add columns for usermeta migration (v1.5.2+) */
			NBUF_Database::update_tables_for_usermeta_migration();
		}

		/* Schedule usermeta migration via cron (v1.5.2+) */
		if ( class_exists( 'NBUF_Cron' ) ) {
			NBUF_Cron::schedule_usermeta_migration();
		}
		if ( $debug_timing ) {
			$timings['1_database_tables'] = round( microtime( true ) - $start_time, 3 );
		}

		// 2. Migrate options from wp_options to custom table (run once).
		self::migrate_to_custom_options();
		if ( $debug_timing ) {
			$timings['2_migrate_options'] = round( microtime( true ) - $start_time, 3 );
		}

		// 3. Load default email templates (to custom options table).
		// OPTIMIZED: Uses batch operations to minimize Galera sync overhead.
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

		/* Check which templates already exist in a single query */
		$existing_templates  = NBUF_Options::get_existing_keys( array_keys( $templates ) );
		$templates_to_insert = array();

		/* Load only missing templates from disk */
		foreach ( $templates as $option => $file ) {
			if ( in_array( $option, $existing_templates, true ) ) {
				continue; /* Already exists, skip */
			}
			$path = NBUF_TEMPLATES_DIR . $file;
			if ( file_exists( $path ) ) {
				$templates_to_insert[ $option ] = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		/* Batch insert all missing templates in a single query */
		if ( ! empty( $templates_to_insert ) ) {
			NBUF_Options::batch_insert( $templates_to_insert, false, 'templates' );
		}

		if ( $debug_timing ) {
			$timings['3_email_templates'] = round( microtime( true ) - $start_time, 3 );
		}

		// 4. Load default CSS templates (to custom options table).
		$css_templates = array(
			'nbuf_reset_page_css'        => 'reset-page.css',
			'nbuf_login_page_css'        => 'login-page.css',
			'nbuf_registration_page_css' => 'registration-page.css',
			'nbuf_account_page_css'      => 'account-page.css',
			'nbuf_2fa_page_css'          => '2fa-setup.css',
		);

		/* Check which CSS templates already exist in a single query */
		$existing_css  = NBUF_Options::get_existing_keys( array_keys( $css_templates ) );
		$css_to_insert = array();

		/* Load only missing CSS from disk */
		foreach ( $css_templates as $option => $file ) {
			if ( in_array( $option, $existing_css, true ) ) {
				continue; /* Already exists, skip */
			}
			$path = NBUF_TEMPLATES_DIR . $file;
			if ( file_exists( $path ) ) {
				$css_to_insert[ $option ] = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		/* Batch insert all missing CSS in a single query */
		if ( ! empty( $css_to_insert ) ) {
			NBUF_Options::batch_insert( $css_to_insert, false, 'css' );
		}

		if ( $debug_timing ) {
			$timings['4_css_templates'] = round( microtime( true ) - $start_time, 3 );
		}

		// 4b. Create minified live CSS files from default templates.
		self::create_live_css_files();
		if ( $debug_timing ) {
			$timings['4b_css_live_files'] = round( microtime( true ) - $start_time, 3 );
		}

		// 5. Default settings (to custom options table).
		// OPTIMIZED: Uses batch insert to minimize Galera sync overhead on first install.
		if ( $debug_timing ) {
			$timings['5a_check_start'] = round( microtime( true ) - $start_time, 3 );
		}
		$is_first_install = ! NBUF_Options::get( 'nbuf_settings' );
		if ( $debug_timing ) {
			$timings['5b_check_done'] = round( microtime( true ) - $start_time, 3 );
			$timings['5c_is_first']   = $is_first_install ? 'YES' : 'NO';
		}

		if ( $is_first_install ) {
			/*
			 * Build all default options grouped by category.
			 * Uses batch_insert() to reduce 80+ individual writes to ~10 batch writes.
			 */

			/* Settings group (autoload=true) */
			$settings_defaults = array(
				'nbuf_user_manager_enabled'             => false,
				'nbuf_settings'                         => array(
					'hooks'                    => array( 'register_new_user' ),
					'hooks_custom'             => '',
					'custom_hook_enabled'      => 0,
					'reverify_on_email_change' => 1,
					'cleanup'                  => array(),
					'auto_verify_existing'     => 1,
				),
				'nbuf_require_verification'             => true,
				'nbuf_enable_login'                     => true,
				'nbuf_enable_registration'              => true,
				'nbuf_notify_admin_registration'        => false,
				'nbuf_enable_password_reset'            => true,
				'nbuf_allow_email_change'               => 'disabled',
				'nbuf_verify_email_change'              => true,
				'nbuf_enable_custom_roles'              => true,
				'nbuf_redirect_default_login'           => true,
				'nbuf_redirect_default_register'        => true,
				'nbuf_redirect_default_logout'          => true,
				'nbuf_redirect_default_lostpassword'    => true,
				'nbuf_redirect_default_resetpass'       => true,
				'nbuf_enable_login_limiting'            => true,
				'nbuf_login_max_attempts'               => 5,
				'nbuf_login_lockout_duration'           => 10,
				'nbuf_login_max_attempts_per_username'  => 10,
				'nbuf_login_username_lockout_window'    => 60,
				'nbuf_login_trusted_proxies'            => array(),

				/* IP Restrictions */
				'nbuf_ip_restriction_enabled'           => false,
				'nbuf_ip_restriction_mode'              => 'whitelist',
				'nbuf_ip_restriction_list'              => '',
				'nbuf_ip_restriction_admin_bypass'      => true,

				'nbuf_logout_behavior'                  => 'immediate',
				'nbuf_logout_redirect'                  => 'home',
				'nbuf_logout_redirect_custom'           => '',
				'nbuf_css_load_on_pages'                => true,
				'nbuf_css_use_minified'                 => true,
				'nbuf_css_combine_files'                => true,
				'nbuf_enable_expiration'                => false,
				'nbuf_expiration_warning_days'          => 7,

				/* WooCommerce Integration */
				'nbuf_wc_require_verification'          => false,
				'nbuf_wc_prevent_active_subs'           => false,
				'nbuf_wc_prevent_recent_orders'         => false,
				'nbuf_wc_recent_order_days'             => 90,

				/* Email domain restrictions */
				'nbuf_email_restriction_mode'           => 'none',
				'nbuf_email_restriction_domains'        => '',
				'nbuf_email_restriction_message'        => '',

				/* Anti-bot protection */
				'nbuf_antibot_enabled'                  => true,
				'nbuf_antibot_honeypot'                 => true,
				'nbuf_antibot_time_check'               => true,
				'nbuf_antibot_min_time'                 => 3,
				'nbuf_antibot_js_token'                 => true,
				'nbuf_antibot_interaction'              => true,
				'nbuf_antibot_min_interactions'         => 3,
				'nbuf_antibot_pow'                      => true,
				'nbuf_antibot_pow_difficulty'           => 'medium',

				'nbuf_registration_fields'              => array(
					'username_method'      => 'auto_random',
					'address_mode'         => 'simplified',
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
					'website_label'        => 'Secondary Website',
				),

				/* Profile Fields configuration */
				'nbuf_registration_profile_fields'      => array(),
				'nbuf_required_profile_fields'          => array(),
				'nbuf_account_profile_fields'           => array(),
				'nbuf_profile_field_labels'             => array(),
				'nbuf_show_description_field'           => false,

				'nbuf_password_requirements_enabled'    => true,
				'nbuf_password_min_length'              => 12,
				'nbuf_password_require_uppercase'       => false,
				'nbuf_password_require_lowercase'       => false,
				'nbuf_password_require_numbers'         => false,
				'nbuf_password_require_special'         => false,
				'nbuf_password_enforce_registration'    => true,
				'nbuf_password_enforce_profile_change'  => true,
				'nbuf_password_enforce_reset'           => true,
				'nbuf_password_admin_bypass'            => false,
				'nbuf_password_force_weak_change'       => false,
				'nbuf_password_check_timing'            => 'once',
				'nbuf_password_grace_period'            => 7,
				'nbuf_password_expiration_enabled'      => false,
				'nbuf_password_expiration_days'         => 365,
				'nbuf_password_expiration_admin_bypass' => true,
				'nbuf_password_expiration_warning_days' => 7,
				'nbuf_2fa_email_method'                 => 'disabled',
				'nbuf_2fa_email_code_length'            => 6,
				'nbuf_2fa_email_expiration'             => 10,
				'nbuf_2fa_email_rate_limit'             => 5,
				'nbuf_2fa_email_rate_window'            => 15,
				'nbuf_2fa_totp_method'                  => 'disabled',
				'nbuf_2fa_totp_code_length'             => 6,
				'nbuf_2fa_totp_time_window'             => 30,
				'nbuf_2fa_totp_tolerance'               => 1,
				'nbuf_2fa_totp_qr_size'                 => 200,
				'nbuf_2fa_backup_enabled'               => true,
				'nbuf_2fa_backup_count'                 => 4,
				'nbuf_2fa_backup_length'                => 32,
				'nbuf_app_passwords_enabled'            => false,
				'nbuf_passkeys_enabled'                 => false,
				'nbuf_passkey_prompt_enabled'           => true,
				'nbuf_passkeys_max_per_user'            => 10,
				'nbuf_passkeys_user_verification'       => 'preferred',
				'nbuf_passkeys_attestation'             => 'none',
				'nbuf_passkeys_timeout'                 => 60000,
				'nbuf_magic_links_enabled'              => false,
				'nbuf_magic_links_expiration'           => 15,
				'nbuf_magic_links_rate_limit'           => 3,
				'nbuf_2fa_device_trust'                 => true,
				'nbuf_2fa_admin_bypass'                 => false,
				'nbuf_2fa_lockout_attempts'             => 5,
				'nbuf_2fa_grace_period'                 => 7,
				'nbuf_2fa_notify_lockout'               => true,
				'nbuf_2fa_notify_disable'               => false,
				'nbuf_universal_mode_enabled'           => true,
				'nbuf_universal_base_slug'              => 'user-foundry',
				'nbuf_universal_default_view'           => 'account',
				'nbuf_legacy_redirects_enabled'         => true,

				/* Login redirect settings */
				'nbuf_login_redirect'                   => 'account',
				'nbuf_login_redirect_custom'            => '',

				/* Admin Users List columns */
				'nbuf_users_column_posts'               => false,
				'nbuf_users_column_company'             => false,
				'nbuf_users_column_location'            => false,

				/* WordPress Toolbar */
				'nbuf_admin_bar_visibility'             => 'show_admin',

				/* Admin Dashboard Access */
				'nbuf_restrict_admin_access'            => false,
				'nbuf_admin_redirect_url'               => '',

				/* Account Page Features */
				'nbuf_session_management_enabled'       => true,
				'nbuf_activity_dashboard_enabled'       => true,

				/* Terms of Service */
				'nbuf_tos_enabled'                      => false,
				'nbuf_tos_require_on_login'             => true,
				'nbuf_tos_grace_period_hours'           => 24,

				/* User Impersonation */
				'nbuf_impersonation_enabled'            => false,
				'nbuf_impersonation_capability'         => 'edit_users',
			);
			NBUF_Options::batch_insert( $settings_defaults, true, 'settings' );

			/* Audit log group */
			$audit_defaults = array(
				'nbuf_audit_log_enabled'            => true,
				'nbuf_audit_log_retention'          => '90days',
				'nbuf_audit_log_events'             => array(
					'authentication' => true,
					'verification'   => true,
					'passwords'      => true,
					'2fa'            => true,
					'account_status' => true,
					'profile'        => false,
				),
				'nbuf_audit_log_max_message_length' => 500,
			);
			NBUF_Options::batch_insert( $audit_defaults, true, 'audit_log' );

			/* Logging group - Admin Audit Log */
			$logging_defaults = array(
				'nbuf_logging_admin_audit_enabled'    => true,
				'nbuf_logging_admin_audit_retention'  => 'forever',
				'nbuf_logging_admin_audit_categories' => array(
					'user_deletion'        => true,
					'role_changes'         => true,
					'settings_changes'     => true,
					'bulk_actions'         => true,
					'manual_verifications' => true,
					'password_resets'      => true,
					'profile_edits'        => true,
				),
				'nbuf_logging_anonymize_ip'           => false,
				'nbuf_logging_store_user_agent'       => true,
			);
			NBUF_Options::batch_insert( $logging_defaults, true, 'logging' );

			/* Security Log group */
			$security_log_defaults = array(
				'nbuf_security_log_enabled'        => true,
				'nbuf_security_log_retention'      => '365days',
				'nbuf_security_log_alerts_enabled' => false,
				'nbuf_security_log_recipient_type' => 'admin',
				'nbuf_security_log_custom_email'   => '',
			);
			NBUF_Options::batch_insert( $security_log_defaults, true, 'security_log' );

			/* GDPR group */
			$gdpr_defaults = array(
				'nbuf_gdpr_delete_user_photos'       => true,
				'nbuf_gdpr_delete_audit_logs'        => 'anonymize',
				'nbuf_gdpr_include_audit_logs'       => true,
				'nbuf_gdpr_include_2fa_data'         => true,
				'nbuf_gdpr_include_login_attempts'   => false,
				'nbuf_policy_login_enabled'          => true,
				'nbuf_policy_login_position'         => 'right',
				'nbuf_policy_registration_enabled'   => true,
				'nbuf_policy_registration_position'  => 'right',
				'nbuf_policy_verify_enabled'         => false,
				'nbuf_policy_verify_position'        => 'right',
				'nbuf_policy_request_reset_enabled'  => false,
				'nbuf_policy_request_reset_position' => 'right',
				'nbuf_policy_reset_enabled'          => false,
				'nbuf_policy_reset_position'         => 'right',
				'nbuf_policy_account_tab_enabled'    => false,
			);
			NBUF_Options::batch_insert( $gdpr_defaults, true, 'gdpr' );

			/* Restrictions group */
			$restrictions_defaults = array(
				'nbuf_restrictions_enabled'               => false,
				'nbuf_restrictions_menu_enabled'          => false,
				'nbuf_restrictions_content_enabled'       => false,
				'nbuf_restrict_content_shortcode_enabled' => false,
				'nbuf_restrict_widgets_enabled'           => false,
				'nbuf_restrict_taxonomies_enabled'        => false,
				'nbuf_restrictions_post_types'            => array( 'post', 'page' ),
				'nbuf_restrict_taxonomies_list'           => array( 'category', 'post_tag' ),
				'nbuf_restrictions_hide_from_queries'     => false,
				'nbuf_restrict_taxonomies_filter_queries' => false,
			);
			NBUF_Options::batch_insert( $restrictions_defaults, true, 'restrictions' );

			/* Profiles group */
			$profiles_defaults = array(
				'nbuf_enable_profiles'            => false,
				'nbuf_enable_public_profiles'     => false,
				'nbuf_profile_enable_gravatar'    => false,
				'nbuf_profile_default_privacy'    => 'private',
				'nbuf_profile_allow_cover_photos' => true,
				'nbuf_profile_max_photo_size'     => 5,
				'nbuf_profile_max_cover_size'     => 10,
				'nbuf_profile_custom_css'         => '',
			);
			NBUF_Options::batch_insert( $profiles_defaults, true, 'profiles' );

			/* Member Directory group */
			$directory_defaults = array(
				'nbuf_enable_member_directory'       => false,
				'nbuf_directory_default_view'        => 'grid',
				'nbuf_directory_per_page'            => 20,
				'nbuf_directory_show_search'         => true,
				'nbuf_directory_show_filters'        => true,
				'nbuf_directory_default_privacy'     => 'private',
				'nbuf_allow_user_privacy_control'    => false,
				'nbuf_display_privacy_when_disabled' => false,
				'nbuf_directory_roles'               => array( 'author', 'contributor', 'subscriber' ),
			);
			NBUF_Options::batch_insert( $directory_defaults, true, 'member_directory' );

			/* Tools group */
			$tools_defaults = array(
				'nbuf_import_require_email'    => true,
				'nbuf_import_send_welcome'     => false,
				'nbuf_import_verify_emails'    => true,
				'nbuf_import_default_role'     => 'subscriber',
				'nbuf_import_batch_size'       => 50,
				'nbuf_import_update_existing'  => false,
				'nbuf_config_allow_import'     => true,
				'nbuf_config_export_sensitive' => false,
			);
			NBUF_Options::batch_insert( $tools_defaults, true, 'tools' );

			/* Notifications group */
			$notifications_defaults = array(
				'nbuf_notify_profile_changes'        => false,
				'nbuf_notify_profile_changes_to'     => get_option( 'admin_email' ),
				'nbuf_notify_profile_changes_fields' => array( 'user_email', 'display_name' ),
				'nbuf_notify_profile_changes_digest' => 'immediate',
			);
			NBUF_Options::batch_insert( $notifications_defaults, true, 'notifications' );

			/* Version history group */
			$version_history_defaults = array(
				'nbuf_version_history_enabled'           => true,
				'nbuf_version_history_user_visible'      => false,
				'nbuf_version_history_allow_user_revert' => false,
				'nbuf_version_history_retention_days'    => 365,
				'nbuf_version_history_max_versions'      => 50,
				'nbuf_version_history_ip_tracking'       => 'anonymized',
				'nbuf_version_history_auto_cleanup'      => true,
			);
			NBUF_Options::batch_insert( $version_history_defaults, true, 'version_history' );

			/* Integration group */
			$integration_defaults = array(
				'nbuf_webhooks_enabled' => false,
			);
			NBUF_Options::batch_insert( $integration_defaults, true, 'integration' );

			if ( $debug_timing ) {
				$timings['5d_batches_done'] = round( microtime( true ) - $start_time, 3 );
			}

			/*
			 * Verify critical settings were saved.
			 * If batch_insert failed (disk full, permissions, etc.), show admin notice.
			 */
			$verification_check = NBUF_Options::get( 'nbuf_settings' );
			if ( empty( $verification_check ) ) {
				set_transient(
					'nbuf_activation_warning',
					__( 'NoBloat User Foundry: Some default settings may not have been saved. Please check your database connection and try deactivating/reactivating the plugin.', 'nobloat-user-foundry' ),
					60
				);
			}
		}
		if ( $debug_timing ) {
			$timings['5_default_settings'] = round( microtime( true ) - $start_time, 3 );
		}

		// 6. Auto-verify existing users if enabled.
		// Uses background processing for large sites to avoid PHP timeout.
		$settings = NBUF_Options::get( 'nbuf_settings', array() );
		if ( ! empty( $settings['auto_verify_existing'] ) ) {
			if ( $debug_timing ) {
				$timings['6_verify_users_count'] = count( get_users( array( 'fields' => 'ID' ) ) );
			}

			/*
			 * Try background processing for large user counts.
			 * Falls back to immediate processing for small sites.
			 */
			$background_started = false;
			if ( class_exists( 'NBUF_Background_Activation' ) ) {
				$background_started = NBUF_Background_Activation::start_if_needed( array( 'verify_users' ) );
			}

			/* If background processing didn't start (small site or class unavailable), do it now */
			if ( ! $background_started ) {
				self::verify_all_existing_users();
			}
		}
		if ( $debug_timing ) {
			$timings['6_verify_existing_users'] = round( microtime( true ) - $start_time, 3 );
		}

		// 7. Schedule cleanup and expiration checks.
		if ( class_exists( 'NBUF_Cron' ) ) {
			NBUF_Cron::activate();
		}
		if ( class_exists( 'NBUF_Expiration' ) ) {
			NBUF_Expiration::activate();
		}
		if ( $debug_timing ) {
			$timings['7_cron_activation'] = round( microtime( true ) - $start_time, 3 );
		}

		// 8. Create uploads directory structure.
		self::create_upload_directories();
		if ( $debug_timing ) {
			$timings['8_upload_directories'] = round( microtime( true ) - $start_time, 3 );
		}

		// 9. Create Universal Page.
		// Only the Universal Page is auto-created. Users can create individual
		// pages with legacy shortcodes manually if they prefer that approach.
		self::create_page( 'nbuf_universal_page_id', 'NoBloat User Hub', array( 'user-foundry', 'nobloat-hub' ), '[nbuf_universal]' );
		if ( $debug_timing ) {
			$timings['9_create_page'] = round( microtime( true ) - $start_time, 3 );
		}

		// Flush rewrite rules if Universal Mode is enabled.
		if ( NBUF_Options::get( 'nbuf_universal_mode_enabled', false ) ) {
			if ( class_exists( 'NBUF_Universal_Router' ) ) {
				NBUF_Universal_Router::flush_rules();
			} else {
				flush_rewrite_rules();
			}
		}
		if ( $debug_timing ) {
			$timings['10_flush_rewrite_rules'] = round( microtime( true ) - $start_time, 3 );
			$timings['total_seconds']          = round( microtime( true ) - $start_time, 3 );

			/* Write timing log to uploads directory */
			$upload_dir = wp_upload_dir();
			$log_file   = trailingslashit( $upload_dir['basedir'] ) . 'nbuf-activation-timing.log';
			$log_entry  = gmdate( 'Y-m-d H:i:s' ) . " - Activation Timing:\n";
			foreach ( $timings as $step => $time ) {
				$log_entry .= "  {$step}: {$time}\n";
			}
			$log_entry .= "\n";
			file_put_contents( $log_file, $log_entry, FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Verify all existing users.
	 *
	 * Marks all existing users as verified on first activation.
	 * Includes admins so they retain verified status if downgraded.
	 *
	 * Uses batch processing for performance - reduces thousands of individual
	 * queries to just a handful of batch queries.
	 *
	 * @return void
	 */
	private static function verify_all_existing_users(): void {
		if ( ! class_exists( 'NBUF_User_Data' ) ) {
			return;
		}

		/* Get all user IDs */
		$user_ids = get_users( array( 'fields' => 'ID' ) );

		if ( empty( $user_ids ) ) {
			return;
		}

		/*
		 * Use batch verification for performance.
		 * This reduces N*2 queries (check + update per user) to just
		 * ceil(N/500) batch queries using INSERT ... ON DUPLICATE KEY UPDATE.
		 */
		NBUF_User_Data::batch_verify_users( $user_ids );
	}

	/**
	 * Create minified live CSS files from default templates.
	 *
	 * Ensures all users get minified CSS even if they never customize styles.
	 * Creates -live.css and -live.min.css files in assets/css/frontend/.
	 *
	 * @return void
	 */
	private static function create_live_css_files(): void {
		if ( ! class_exists( 'NBUF_CSS_Manager' ) ) {
			return;
		}

		$css_files = array(
			'reset-page'        => 'nbuf_css_write_failed_reset',
			'login-page'        => 'nbuf_css_write_failed_login',
			'registration-page' => 'nbuf_css_write_failed_registration',
			'account-page'      => 'nbuf_css_write_failed_account',
			'2fa-setup'         => 'nbuf_css_write_failed_2fa',
			'profile'           => 'nbuf_css_write_failed_profile',
		);

		$frontend_dir = NBUF_PLUGIN_DIR . 'assets/css/frontend/';

		foreach ( $css_files as $filename => $token_key ) {
			$min_path = $frontend_dir . $filename . '-live.min.css';

			/* Skip if minified file already exists */
			if ( file_exists( $min_path ) ) {
				continue;
			}

			/* Load CSS from default template */
			$css = NBUF_CSS_Manager::load_default_css( $filename );
			if ( empty( $css ) ) {
				continue;
			}

			/* Write live files (creates both .css and .min.css) */
			NBUF_CSS_Manager::save_css_to_disk( $css, $filename, $token_key );
		}
	}

	/**
	 * Create upload directories.
	 *
	 * Creates the /wp-content/uploads/nobloat/ directory structure
	 * for storing user photos and other plugin uploads.
	 *
	 * @return void
	 */
	private static function create_upload_directories(): void {
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
	 * @param string   $option_key Option key to store page ID.
	 * @param string   $title      Page title.
	 * @param string[] $slugs      Array of slug options to try.
	 * @param string   $content    Page content (shortcode).
	 * @return void
	 */
	private static function create_page( string $option_key, string $title, array $slugs, string $content ): void {
		$user_id = get_current_user_id() ? get_current_user_id() : 1;

		/* Extract shortcode name from content for verification */
		$shortcode_name = '';
		if ( preg_match( '/\[(\w+)/', $content, $matches ) ) {
			$shortcode_name = $matches[1];
		}

		/* Check if a page with correct slug AND correct shortcode already exists */
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				/* Only reuse if page contains the correct shortcode */
				if ( $shortcode_name && false !== strpos( $page->post_content, '[' . $shortcode_name ) ) {
					NBUF_Options::update( $option_key, $page->ID, true, 'settings' );
					return;
				}
				/* Page exists but has wrong content - skip this slug */
			}
		}

		/* Create new page */
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
				update_post_meta( $page_id, '_nbuf_hide_title', '1' );
				break;
			}
		}
	}

	/**
	 * Migrate options from wp_options to custom nbuf_options table.
	 *
	 * Runs once during plugin activation/upgrade. Moves all plugin
	 * options from WordPress wp_options table to custom table to
	 * eliminate bloat on non-plugin pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function migrate_to_custom_options(): void {
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
			'nbuf_user_manager_enabled'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_settings'                          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_password_reset'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_verification'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_login'                        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_registration'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_account'                      => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_logout'                       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_request_reset'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_require_verification'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_login'                      => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_registration'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_notify_admin_registration'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_password_reset'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_allow_email_change'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_verify_email_change'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_login_limiting'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_login_max_attempts'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_login_lockout_duration'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_behavior'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_redirect'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_logout_redirect_custom'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_load_on_pages'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_use_minified'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_css_combine_files'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_login'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_register'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_logout'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_lostpassword'     => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_redirect_default_resetpass'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_enable_expiration'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_expiration_warning_days'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_require_verification'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_prevent_active_subs'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_prevent_recent_orders'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_wc_recent_order_days'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_email_restriction_mode'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_email_restriction_domains'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_email_restriction_message'         => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_registration_fields'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Profile Fields configuration */
			'nbuf_registration_profile_fields'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_required_profile_fields'           => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_account_profile_fields'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_profile_field_labels'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_show_description_field'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			'nbuf_antibot_enabled'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_honeypot'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_time_check'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_min_time'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_js_token'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_interaction'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_min_interactions'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_pow'                       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_antibot_pow_difficulty'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_requirements_enabled'     => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_min_length'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_uppercase'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_lowercase'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_numbers'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_require_special'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_registration'     => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_profile_change'   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_enforce_reset'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_admin_bypass'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_force_weak_change'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_check_timing'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_grace_period'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Password Expiration settings */
			'nbuf_password_expiration_enabled'       => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_days'          => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_admin_bypass'  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_password_expiration_warning_days'  => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Two-Factor Authentication settings */
			'nbuf_2fa_email_method'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_code_length'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_expiration'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_rate_limit'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_email_rate_window'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_method'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_code_length'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_time_window'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_tolerance'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_totp_qr_size'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_enabled'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_count'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_backup_length'                 => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_app_passwords_enabled'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkeys_enabled'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkey_prompt_enabled'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkeys_max_per_user'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkeys_user_verification'        => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkeys_attestation'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_passkeys_timeout'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_magic_links_enabled'               => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_magic_links_expiration'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_magic_links_rate_limit'            => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_device_trust'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_admin_bypass'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_lockout_attempts'              => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_grace_period'                  => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_notify_lockout'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_2fa_notify_disable'                => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_2fa_verify'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_totp_setup'                   => array(
				'group'    => 'settings',
				'autoload' => true,
			),
			'nbuf_page_member_directory'             => array(
				'group'    => 'settings',
				'autoload' => true,
			),

			/* Member Directory settings */
			'nbuf_enable_member_directory'           => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_default_view'            => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_per_page'                => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_show_search'             => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_show_filters'            => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_default_privacy'         => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_allow_user_privacy_control'        => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_display_privacy_when_disabled'     => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),
			'nbuf_directory_roles'                   => array(
				'group'    => 'member_directory',
				'autoload' => true,
			),

			/* Logging settings - User Audit Log */
			'nbuf_audit_log_enabled'                 => array(
				'group'    => 'audit_log',
				'autoload' => true,
			),
			'nbuf_audit_log_retention'               => array(
				'group'    => 'audit_log',
				'autoload' => true,
			),
			'nbuf_audit_log_events'                  => array(
				'group'    => 'audit_log',
				'autoload' => true,
			),

			/* Logging settings - Admin Audit Log */
			'nbuf_logging_admin_audit_enabled'       => array(
				'group'    => 'logging',
				'autoload' => true,
			),
			'nbuf_logging_admin_audit_retention'     => array(
				'group'    => 'logging',
				'autoload' => true,
			),
			'nbuf_logging_admin_audit_categories'    => array(
				'group'    => 'logging',
				'autoload' => true,
			),

			/* Logging settings - Security Log */
			'nbuf_security_log_enabled'              => array(
				'group'    => 'security_log',
				'autoload' => true,
			),
			'nbuf_security_log_retention'            => array(
				'group'    => 'security_log',
				'autoload' => true,
			),
			'nbuf_security_log_alerts_enabled'       => array(
				'group'    => 'security_log',
				'autoload' => true,
			),
			'nbuf_security_log_recipient_type'       => array(
				'group'    => 'security_log',
				'autoload' => true,
			),
			'nbuf_security_log_custom_email'         => array(
				'group'    => 'security_log',
				'autoload' => true,
			),

			/* Logging settings - Privacy */
			'nbuf_logging_anonymize_ip'              => array(
				'group'    => 'logging',
				'autoload' => true,
			),
			'nbuf_logging_store_user_agent'          => array(
				'group'    => 'logging',
				'autoload' => true,
			),

			/* GDPR settings */
			'nbuf_gdpr_delete_user_photos'           => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_gdpr_delete_audit_logs'            => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_gdpr_include_audit_logs'           => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_gdpr_include_2fa_data'             => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_gdpr_include_login_attempts'       => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),

			/* GDPR Policy Notice settings */
			'nbuf_policy_login_enabled'              => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_login_position'             => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_registration_enabled'       => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_registration_position'      => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_verify_enabled'             => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_verify_position'            => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_request_reset_enabled'      => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_request_reset_position'     => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_reset_enabled'              => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_reset_position'             => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_policy_account_tab_enabled'        => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),

			/* GDPR Notification settings */
			'nbuf_notify_profile_changes'            => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_notify_profile_changes_to'         => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_notify_profile_changes_fields'     => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_notify_profile_changes_digest'     => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),

			/* GDPR Version History settings */
			'nbuf_version_history_enabled'           => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_user_visible'      => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_allow_user_revert' => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_retention_days'    => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_max_versions'      => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_ip_tracking'       => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),
			'nbuf_version_history_auto_cleanup'      => array(
				'group'    => 'gdpr',
				'autoload' => true,
			),

			/* Templates (autoload=0, large, load on-demand) */
			'nbuf_email_template_html'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_email_template_text'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_welcome_email_html'                => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_welcome_email_text'                => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_expiration_warning_html'           => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_expiration_warning_text'           => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_login_form_template'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_registration_form_template'        => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_account_page_template'             => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_request_reset_form_template'       => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_reset_form_template'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_email_code_html'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_email_code_text'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_verify_template'               => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_setup_totp_template'           => array(
				'group'    => 'templates',
				'autoload' => false,
			),
			'nbuf_2fa_backup_codes_template'         => array(
				'group'    => 'templates',
				'autoload' => false,
			),

			/* CSS (autoload=0, load on-demand) */
			'nbuf_reset_page_css'                    => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_login_page_css'                    => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_registration_page_css'             => array(
				'group'    => 'css',
				'autoload' => false,
			),
			'nbuf_account_page_css'                  => array(
				'group'    => 'css',
				'autoload' => false,
			),

			/* System tokens (autoload=0, context-specific) */
			'nbuf_last_cleanup'                      => array(
				'group'    => 'system',
				'autoload' => false,
			),
			'nbuf_css_write_failed'                  => array(
				'group'    => 'system',
				'autoload' => false,
			),
			'nbuf_template_write_failed'             => array(
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
