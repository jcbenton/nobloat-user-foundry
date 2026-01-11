<?php
/**
 * NoBloat User Foundry - Settings Controller
 *
 * Handles the admin settings interface, sanitization, and
 * integration with tab renderers located in /includes/user-tabs/.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load supporting classes.
require_once NBUF_INCLUDE_DIR . 'class-nbuf-test.php';

/**
 * Class NBUF_Settings
 *
 * Handles admin settings interface and sanitization.
 */
class NBUF_Settings {


	/* Validation limits constants */
	const PASSWORD_MIN_LENGTH      = 1;
	const PASSWORD_MAX_LENGTH      = 128;
	const RETENTION_DAYS_MIN       = 1;
	const RETENTION_DAYS_MAX       = 3650;
	const PASSWORD_EXPIRY_DAYS_MIN = 1;
	const PASSWORD_EXPIRY_DAYS_MAX = 3650;

	/**
	 * Initialize settings controller.
	 *
	 * Registers admin menus, hooks, AJAX handlers, and scripts.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_roles_submenu' ), 12 );
		add_action( 'admin_menu', array( __CLASS__, 'add_users_submenu' ), 13 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_duplicate_submenu' ), 999 );
		add_action( 'admin_head', array( __CLASS__, 'highlight_users_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'auto_detect_pages' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_wp_options' ) );
		add_action( 'admin_notices', array( __CLASS__, 'check_required_pages' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_settings_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_nbuf_reset_template', array( __CLASS__, 'ajax_reset_template' ) );
		add_action( 'wp_ajax_nbuf_reset_style', array( __CLASS__, 'ajax_reset_style' ) );
		add_action( 'admin_post_nbuf_save_settings', array( __CLASS__, 'handle_settings_save' ) );

		/* Admin footer text - plugin name and version */
		add_filter( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ) );
		add_filter( 'update_footer', array( __CLASS__, 'admin_footer_version' ), 11 );

		// Hook test email handler.
		NBUF_Test::init();
	}

	/**
	 * Remove the auto-generated duplicate submenu item.
	 *
	 * WordPress automatically creates a submenu item with the same slug as the parent.
	 * This removes that duplicate since we have our own "Settings" submenu.
	 */
	public static function remove_duplicate_submenu() {
		remove_submenu_page( 'nobloat-foundry', 'nobloat-foundry' );
	}

	/**
	 * ==========================================================
	 * SETTINGS REGISTRY
	 * ----------------------------------------------------------
	 * Defines all plugin settings with their sanitize callbacks.
	 * This replaces WordPress register_setting() to avoid wp_options bloat.
	 * ==========================================================
	 *
	 * @return array Settings registry with sanitize callbacks.
	 */
	public static function get_settings_registry() {
		return array(
			/* General settings array */
			'nbuf_settings'                       => array( __CLASS__, 'sanitize_settings' ),

			/* Master toggle */
			'nbuf_user_manager_enabled'           => array( __CLASS__, 'sanitize_checkbox' ),

			/* Feature toggles */
			'nbuf_require_verification'           => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_enable_registration'            => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_notify_admin_registration'      => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_enable_password_reset'          => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_enable_custom_roles'            => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_allow_email_change'             => function ( $value ) {
				return in_array( $value, array( 'disabled', 'enabled' ), true ) ? $value : 'disabled';
			},
			'nbuf_verify_email_change'            => array( __CLASS__, 'sanitize_checkbox' ),

			/* Admin Users List columns */
			'nbuf_users_column_posts'             => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_users_column_company'           => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_users_column_location'          => array( __CLASS__, 'sanitize_checkbox' ),

			/* WordPress Toolbar */
			'nbuf_admin_bar_visibility'           => function ( $value ) {
				$valid = array( 'show_all', 'show_admin', 'hide_all' );
				return in_array( $value, $valid, true ) ? $value : 'show_admin';
			},

			/* Admin Dashboard Access */
			'nbuf_restrict_admin_access'          => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_admin_redirect_url'             => 'esc_url_raw',

			/* Email Sender Settings */
			'nbuf_email_sender_address'           => 'sanitize_email',
			'nbuf_email_sender_name'              => 'sanitize_text_field',

			/* Logout settings */
			'nbuf_logout_behavior'                => function ( $value ) {
				return in_array( $value, array( 'immediate', 'confirm' ), true ) ? $value : 'immediate';
			},
			'nbuf_logout_redirect'                => function ( $value ) {
				return in_array( $value, array( 'home', 'login', 'custom' ), true ) ? $value : 'home';
			},
			'nbuf_logout_redirect_custom'         => 'esc_url_raw',

			/* Login redirect settings */
			'nbuf_login_redirect'                 => function ( $value ) {
				return in_array( $value, array( 'account', 'admin', 'home', 'custom' ), true ) ? $value : 'account';
			},
			'nbuf_login_redirect_custom'          => 'esc_url_raw',

			/* Default WordPress redirect settings */
			'nbuf_redirect_default_login'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_redirect_default_register'      => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_redirect_default_logout'        => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_redirect_default_lostpassword'  => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_redirect_default_resetpass'     => array( __CLASS__, 'sanitize_checkbox' ),

			/* Plugin page IDs */
			'nbuf_page_verification'              => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_password_reset'            => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_request_reset'             => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_login'                     => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_registration'              => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_account'                   => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_profile'                   => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_logout'                    => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_2fa_verify'                => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_totp_setup'                => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_page_member_directory'          => array( __CLASS__, 'sanitize_page_id' ),

			/* Universal Page Mode */
			'nbuf_universal_mode_enabled'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_universal_page_id'              => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_universal_base_slug'            => 'sanitize_title',
			'nbuf_universal_default_view'         => function ( $value ) {
				$valid = array( 'account', 'login', 'register', 'members' );
				return in_array( $value, $valid, true ) ? $value : 'account';
			},
			'nbuf_legacy_redirects_enabled'       => array( __CLASS__, 'sanitize_checkbox' ),

			/* Universal Page View Overrides */
			'nbuf_view_override_login'            => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_register'         => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_account'          => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_profile'          => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_verify'           => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_forgot-password'  => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_reset-password'   => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_2fa'              => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_2fa-setup'        => array( __CLASS__, 'sanitize_page_id' ),
			'nbuf_view_override_members'          => array( __CLASS__, 'sanitize_page_id' ),

			/* CSS options */
			'nbuf_reset_page_css'                 => 'wp_strip_all_tags',
			'nbuf_login_page_css'                 => 'wp_strip_all_tags',
			'nbuf_registration_page_css'          => 'wp_strip_all_tags',
			'nbuf_account_page_css'               => 'wp_strip_all_tags',
			'nbuf_css_load_on_pages'              => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_css_use_minified'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_css_combine_files'              => array( __CLASS__, 'sanitize_checkbox' ),

			/* Security - Login limiting */
			'nbuf_enable_login_limiting'          => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_login_max_attempts'             => array( __CLASS__, 'sanitize_positive_int' ),
			'nbuf_login_lockout_duration'         => array( __CLASS__, 'sanitize_positive_int' ),
			'nbuf_login_trusted_proxies'          => array( __CLASS__, 'sanitize_trusted_proxies' ),

			/* Security - Anti-Bot Registration Protection */
			'nbuf_antibot_enabled'                => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_honeypot'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_time_check'             => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_min_time'               => function ( $value ) {
				return max( 1, min( 30, absint( $value ) ) );
			},
			'nbuf_antibot_js_token'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_interaction'            => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_min_interactions'       => function ( $value ) {
				return max( 1, min( 50, absint( $value ) ) );
			},
			'nbuf_antibot_pow'                    => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_antibot_pow_difficulty'         => function ( $value ) {
				return in_array( $value, array( 'low', 'medium', 'high' ), true ) ? $value : 'medium';
			},

			/* Security - Password strength */
			'nbuf_password_requirements_enabled'  => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_min_strength'          => function ( $value ) {
				$valid = array( 'none', 'weak', 'medium', 'strong', 'very-strong' );
				return in_array( $value, $valid, true ) ? $value : 'medium';
			},
			'nbuf_password_min_length'            => function ( $value ) {
				$length = absint( $value );
				return max( self::PASSWORD_MIN_LENGTH, min( self::PASSWORD_MAX_LENGTH, $length ) );
			},
			'nbuf_password_require_uppercase'     => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_require_lowercase'     => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_require_numbers'       => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_require_special'       => array( __CLASS__, 'sanitize_checkbox' ),

			/* Security - Password enforcement */
			'nbuf_password_enforce_registration'  => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_enforce_profile_change' => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_enforce_reset'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_admin_bypass'          => array( __CLASS__, 'sanitize_checkbox' ),

			/* Security - Weak password migration */
			'nbuf_password_force_weak_change'     => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_check_timing'          => function ( $value ) {
				return in_array( $value, array( 'once', 'every' ), true ) ? $value : 'once';
			},
			'nbuf_password_grace_period'          => function ( $value ) {
				return max( 0, min( 365, absint( $value ) ) );
			},

			/* Security - Password Expiration */
			'nbuf_password_expiration_enabled'    => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_expiration_days'       => function ( $value ) {
				return max( self::RETENTION_DAYS_MIN, min( self::RETENTION_DAYS_MAX, absint( $value ) ) );
			},
			'nbuf_password_expiration_admin_bypass' => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_password_expiration_warning_days' => function ( $value ) {
				return max( 0, min( 90, absint( $value ) ) );
			},

			/* Security - Application Passwords */
			'nbuf_app_passwords_enabled'          => array( __CLASS__, 'sanitize_checkbox' ),

			/* Security - 2FA Email */
			'nbuf_2fa_email_method'               => function ( $value ) {
				$valid = array( 'disabled', 'required_admin', 'optional_all', 'required_all', 'user_configurable', 'required' );
				return in_array( $value, $valid, true ) ? $value : 'disabled';
			},
			'nbuf_2fa_email_code_length'          => function ( $value ) {
				return max( 4, min( 8, absint( $value ) ) );
			},
			'nbuf_2fa_email_expiration'           => function ( $value ) {
				return max( 1, min( 60, absint( $value ) ) );
			},
			'nbuf_2fa_email_rate_limit'           => function ( $value ) {
				return max( 1, min( 50, absint( $value ) ) );
			},
			'nbuf_2fa_email_rate_window'          => function ( $value ) {
				return max( 1, min( 120, absint( $value ) ) );
			},

			/* Security - 2FA TOTP */
			'nbuf_2fa_totp_method'                => function ( $value ) {
				$valid = array( 'disabled', 'optional', 'required_admin', 'required_all', 'user_configurable', 'required' );
				return in_array( $value, $valid, true ) ? $value : 'disabled';
			},
			'nbuf_2fa_totp_code_length'           => function ( $value ) {
				return in_array( (int) $value, array( 6, 8 ), true ) ? absint( $value ) : 6;
			},
			'nbuf_2fa_totp_time_window'           => function ( $value ) {
				return in_array( (int) $value, array( 30, 60 ), true ) ? absint( $value ) : 30;
			},
			'nbuf_2fa_totp_tolerance'             => function ( $value ) {
				return max( 0, min( 2, absint( $value ) ) );
			},
			'nbuf_2fa_totp_qr_size'               => function ( $value ) {
				return max( 100, min( 500, absint( $value ) ) );
			},

			/* Security - 2FA Backup codes */
			'nbuf_2fa_backup_enabled'             => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_2fa_backup_count'               => function ( $value ) {
				return max( 4, min( 20, absint( $value ) ) );
			},
			'nbuf_2fa_backup_length'              => function ( $value ) {
				return max( 8, min( 64, absint( $value ) ) );
			},

			/* Security - 2FA General */
			'nbuf_2fa_device_trust'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_2fa_admin_bypass'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_2fa_lockout_attempts'           => function ( $value ) {
				return max( 3, min( 20, absint( $value ) ) );
			},
			'nbuf_2fa_grace_period'               => function ( $value ) {
				return max( 0, min( 30, absint( $value ) ) );
			},
			'nbuf_2fa_notify_lockout'             => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_2fa_notify_disable'             => array( __CLASS__, 'sanitize_checkbox' ),

			/* Security - Passkeys */
			'nbuf_passkeys_enabled'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_passkeys_max_per_user'          => function ( $value ) {
				return max( 1, min( 20, absint( $value ) ) );
			},
			'nbuf_passkeys_user_verification'     => function ( $value ) {
				return in_array( $value, array( 'preferred', 'required', 'discouraged' ), true ) ? $value : 'preferred';
			},
			'nbuf_passkeys_attestation'           => function ( $value ) {
				return in_array( $value, array( 'none', 'indirect', 'direct' ), true ) ? $value : 'none';
			},
			'nbuf_passkeys_timeout'               => function ( $value ) {
				return max( 30000, min( 300000, absint( $value ) ) );
			},

			/* Security - Account Verification & Approval */
			'nbuf_require_approval'               => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_delete_unverified_days'         => 'absint',
			'nbuf_new_user_default_role'          => function ( $value ) {
				$roles = wp_roles()->get_names();
				return in_array( $value, array_keys( $roles ), true ) ? sanitize_key( $value ) : 'subscriber';
			},

			/* Registration fields */
			'nbuf_registration_fields'            => array( __CLASS__, 'sanitize_registration_fields' ),

			/* Media - Image Optimization */
			'nbuf_convert_images_to_webp'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_webp_quality'                   => function ( $value ) {
				$quality = absint( $value );
				return ( $quality >= 1 && $quality <= 100 ) ? $quality : 85;
			},
			'nbuf_strip_exif_data'                => array( __CLASS__, 'sanitize_checkbox' ),

			/* Media - Profile Photo */
			'nbuf_profile_photo_max_width'        => function ( $value ) {
				$width = absint( $value );
				return ( $width >= 256 && $width <= 4096 ) ? $width : 1024;
			},
			'nbuf_profile_photo_max_size'         => function ( $value ) {
				$size = absint( $value );
				return ( $size >= 1 && $size <= 50 ) ? $size : 5;
			},

			/* Media - Cover Photo */
			'nbuf_cover_photo_max_width'          => function ( $value ) {
				$width = absint( $value );
				return ( $width >= 800 && $width <= 4096 ) ? $width : 1920;
			},
			'nbuf_cover_photo_max_height'         => function ( $value ) {
				$height = absint( $value );
				return ( $height >= 200 && $height <= 2048 ) ? $height : 600;
			},
			'nbuf_cover_photo_max_size'           => function ( $value ) {
				$size = absint( $value );
				return ( $size >= 1 && $size <= 50 ) ? $size : 10;
			},

			/* User Expiration */
			'nbuf_enable_expiration'              => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_expiration_warning_days'        => function ( $value ) {
				return max( 1, min( 90, absint( $value ) ) );
			},

			/* Version History */
			'nbuf_version_history_enabled'           => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_version_history_user_visible'      => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_version_history_allow_user_revert' => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_version_history_retention_days'    => function ( $value ) {
				return max( 1, min( 3650, absint( $value ) ) );
			},
			'nbuf_version_history_max_versions'      => function ( $value ) {
				return max( 10, min( 500, absint( $value ) ) );
			},
			'nbuf_version_history_ip_tracking'       => function ( $value ) {
				return in_array( $value, array( 'off', 'anonymized', 'on' ), true ) ? $value : 'anonymized';
			},
			'nbuf_version_history_auto_cleanup'      => array( __CLASS__, 'sanitize_checkbox' ),

			/* Logging */
			'nbuf_logging_user_audit_enabled'      => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_logging_user_audit_retention'    => 'sanitize_text_field',
			'nbuf_logging_user_audit_categories'   => array( __CLASS__, 'sanitize_checkbox_group' ),
			'nbuf_logging_admin_audit_enabled'     => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_logging_admin_audit_retention'   => 'sanitize_text_field',
			'nbuf_logging_admin_audit_categories'  => array( __CLASS__, 'sanitize_checkbox_group' ),
			'nbuf_logging_security_enabled'        => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_logging_security_retention'      => 'sanitize_text_field',
			'nbuf_logging_security_categories'     => array( __CLASS__, 'sanitize_checkbox_group' ),

			/* Profile settings */
			'nbuf_enable_profiles'                => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_enable_public_profiles'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_profile_enable_gravatar'        => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_profile_default_privacy'        => function ( $value ) {
				$valid = array( 'public', 'members_only', 'private' );
				return in_array( $value, $valid, true ) ? $value : 'private';
			},
			'nbuf_profile_allow_cover_photos'     => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_profile_max_photo_size'         => 'absint',
			'nbuf_profile_max_cover_size'         => 'absint',
			'nbuf_profile_custom_css'             => 'wp_strip_all_tags',

			/* GDPR settings */
			'nbuf_gdpr_delete_user_photos'        => array( __CLASS__, 'sanitize_checkbox' ),

			/* Policy display settings */
			'nbuf_policy_login_enabled'           => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_policy_login_position'          => function ( $value ) {
				return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'right';
			},
			'nbuf_policy_registration_enabled'    => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_policy_registration_position'   => function ( $value ) {
				return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'right';
			},
			'nbuf_policy_verify_enabled'          => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_policy_verify_position'         => function ( $value ) {
				return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'right';
			},
			'nbuf_policy_request_reset_enabled'   => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_policy_request_reset_position'  => function ( $value ) {
				return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'right';
			},
			'nbuf_policy_reset_enabled'           => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_policy_reset_position'          => function ( $value ) {
				return in_array( $value, array( 'left', 'right' ), true ) ? $value : 'right';
			},
			'nbuf_policy_account_tab_enabled'     => array( __CLASS__, 'sanitize_checkbox' ),

			/* Form templates - HTML sanitization via Template Manager */
			'nbuf_login_form_template'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'login-form' );
			},
			'nbuf_registration_form_template'     => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'registration-form' );
			},
			'nbuf_request_reset_form_template'    => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'request-reset-form' );
			},
			'nbuf_reset_form_template'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'reset-form' );
			},
			'nbuf_account_page_template'          => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'account-page' );
			},
			'nbuf_2fa_verify_template'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, '2fa-verify' );
			},

			/* Email templates - HTML sanitization via Template Manager */
			'nbuf_email_template_html'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'email-verification-html' );
			},
			'nbuf_email_template_text'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'email-verification-text' );
			},
			'nbuf_welcome_email_html'             => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'welcome-email-html' );
			},
			'nbuf_welcome_email_text'             => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'welcome-email-text' );
			},
			'nbuf_2fa_email_html'                 => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, '2fa-html' );
			},
			'nbuf_2fa_email_text'                 => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, '2fa-text' );
			},
			'nbuf_expiration_warning_email_html'  => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'expiration-warning-html' );
			},
			'nbuf_expiration_warning_email_text'  => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'expiration-warning-text' );
			},
			'nbuf_expiration_notice_email_html'   => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'expiration-notice-html' );
			},
			'nbuf_expiration_notice_email_text'   => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'expiration-notice-text' );
			},
			'nbuf_password_reset_email_html'      => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'password-reset-html' );
			},
			'nbuf_password_reset_email_text'      => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'password-reset-text' );
			},
			'nbuf_admin_new_user_html'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'admin-new-user-html' );
			},
			'nbuf_admin_new_user_text'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'admin-new-user-text' );
			},
			'nbuf_policy_privacy_html'            => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'policy-privacy-html' );
			},
			'nbuf_policy_terms_html'              => function ( $value ) {
				return NBUF_Template_Manager::sanitize_template( $value, 'policy-terms-html' );
			},

			/* WooCommerce integration */
			'nbuf_wc_require_verification'        => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_wc_prevent_active_subs'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_wc_prevent_recent_orders'       => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_wc_recent_order_days'           => function ( $value ) {
				return max( 1, min( 365, absint( $value ) ) );
			},

			/* Access Restrictions */
			'nbuf_restrictions_enabled'              => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrictions_menu_enabled'         => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrictions_content_enabled'      => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrict_content_shortcode_enabled' => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrict_widgets_enabled'          => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrict_taxonomies_enabled'       => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrictions_hide_from_queries'    => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrict_taxonomies_filter_queries' => array( __CLASS__, 'sanitize_checkbox' ),
			'nbuf_restrictions_post_types'           => array( __CLASS__, 'sanitize_post_type_array' ),
			'nbuf_restrict_taxonomies_list'          => array( __CLASS__, 'sanitize_taxonomy_array' ),

			/* Webhooks */
			'nbuf_webhooks_enabled'                  => array( __CLASS__, 'sanitize_checkbox' ),
		);
	}

	/**
	 * ==========================================================
	 * HANDLE SETTINGS SAVE
	 * ----------------------------------------------------------
	 * Custom handler for saving settings to custom options table.
	 * Replaces WordPress Settings API to avoid wp_options bloat.
	 * ==========================================================
	 */
	public static function handle_settings_save() {
		/* Verify user capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'nobloat-user-foundry' ) );
		}

		/* Verify nonce - check both POST and GET for webhook actions */
		$nonce = '';
		if ( isset( $_POST['nbuf_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nbuf_settings_nonce'] ) );
		} elseif ( isset( $_GET['nbuf_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['nbuf_settings_nonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'nbuf_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Handle webhook actions */
		$webhook_action = '';
		$webhook_id     = 0;

		if ( isset( $_POST['nbuf_webhook_action'] ) ) {
			$webhook_action = sanitize_text_field( wp_unslash( $_POST['nbuf_webhook_action'] ) );
			$webhook_id     = isset( $_POST['nbuf_webhook_id'] ) ? absint( $_POST['nbuf_webhook_id'] ) : 0;
		} elseif ( isset( $_GET['nbuf_webhook_action'] ) ) {
			$webhook_action = sanitize_text_field( wp_unslash( $_GET['nbuf_webhook_action'] ) );
			$webhook_id     = isset( $_GET['nbuf_webhook_id'] ) ? absint( $_GET['nbuf_webhook_id'] ) : 0;
		}

		if ( $webhook_action ) {
			self::handle_webhook_action( $webhook_action, $webhook_id );
			return;
		}

		$registry      = self::get_settings_registry();
		$saved_count   = 0;
		$errors        = array();

		/* Process each submitted field */
		foreach ( $_POST as $key => $value ) {
			/* Skip non-nbuf fields and meta fields */
			if ( strpos( $key, 'nbuf_' ) !== 0 || in_array( $key, array( 'nbuf_settings_nonce', 'nbuf_active_tab', 'nbuf_active_subtab' ), true ) ) {
				continue;
			}

			/* Get sanitize callback */
			$sanitize_callback = isset( $registry[ $key ] ) ? $registry[ $key ] : 'sanitize_text_field';

			/* Sanitize the value */
			if ( is_callable( $sanitize_callback ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by callback.
				$sanitized_value = call_user_func( $sanitize_callback, wp_unslash( $value ) );
			} else {
				$sanitized_value = sanitize_text_field( wp_unslash( $value ) );
			}

			/* Save to custom options table */
			$result = NBUF_Options::update( $key, $sanitized_value, true, 'settings' );
			if ( $result ) {
				++$saved_count;
			}
		}

		/* Handle unchecked checkboxes - only for checkboxes declared on the current form */
		if ( isset( $_POST['nbuf_form_checkboxes'] ) && is_array( $_POST['nbuf_form_checkboxes'] ) ) {
			$form_checkboxes = array_map( 'sanitize_key', wp_unslash( $_POST['nbuf_form_checkboxes'] ) );
			foreach ( $form_checkboxes as $checkbox_key ) {
				/* If checkbox was declared but not submitted, it was unchecked */
				if ( ! isset( $_POST[ $checkbox_key ] ) && isset( $registry[ $checkbox_key ] ) ) {
					$result = NBUF_Options::update( $checkbox_key, false, true, 'settings' );
					if ( $result ) {
						++$saved_count;
					}
				}
			}
		}

		/* Handle empty arrays - only for array fields declared on the current form */
		if ( isset( $_POST['nbuf_form_arrays'] ) && is_array( $_POST['nbuf_form_arrays'] ) ) {
			$form_arrays = array_map( 'sanitize_key', wp_unslash( $_POST['nbuf_form_arrays'] ) );
			foreach ( $form_arrays as $array_key ) {
				/* If array was declared but not submitted, save empty array */
				if ( ! isset( $_POST[ $array_key ] ) && isset( $registry[ $array_key ] ) ) {
					$sanitize_callback = $registry[ $array_key ];
					/* Call sanitizer with empty array to get proper default */
					$sanitized_value = is_callable( $sanitize_callback ) ? call_user_func( $sanitize_callback, array() ) : array();
					$result          = NBUF_Options::update( $array_key, $sanitized_value, true, 'settings' );
					if ( $result ) {
						++$saved_count;
					}
				}
			}
		}

		/* Sync NBUF registration setting with WordPress users_can_register */
		if ( isset( $_POST['nbuf_form_checkboxes'] ) && is_array( $_POST['nbuf_form_checkboxes'] ) ) {
			$form_checkboxes = array_map( 'sanitize_key', wp_unslash( $_POST['nbuf_form_checkboxes'] ) );
			if ( in_array( 'nbuf_enable_registration', $form_checkboxes, true ) ) {
				$nbuf_registration_enabled = ! empty( $_POST['nbuf_enable_registration'] );
				$wp_users_can_register     = (bool) get_option( 'users_can_register' );

				if ( $nbuf_registration_enabled !== $wp_users_can_register ) {
					update_option( 'users_can_register', $nbuf_registration_enabled ? 1 : 0 );
				}
			}
		}

		/* Store success message in transient for display after redirect */
		if ( $saved_count > 0 ) {
			set_transient( 'nbuf_settings_saved', true, 30 );
		}

		/* Build redirect URL with tab/subtab preserved */
		$active_tab = isset( $_POST['nbuf_active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_active_tab'] ) ) : '';
		$active_subtab = isset( $_POST['nbuf_active_subtab'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_active_subtab'] ) ) : '';

		/* Determine which page to redirect to based on tab */
		$appearance_tabs = array( 'emails', 'policies', 'forms', 'styles', 'templates' );
		if ( in_array( $active_tab, $appearance_tabs, true ) ) {
			$redirect_url = admin_url( 'admin.php?page=nobloat-foundry-appearance' );
		} else {
			$redirect_url = admin_url( 'admin.php?page=nobloat-foundry-users' );
		}

		if ( $active_tab ) {
			$redirect_url = add_query_arg( 'tab', $active_tab, $redirect_url );
		}
		if ( $active_subtab ) {
			$redirect_url = add_query_arg( 'subtab', $active_subtab, $redirect_url );
		}

		$redirect_url = add_query_arg( 'settings-updated', 'true', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle webhook CRUD actions.
	 *
	 * @param string $action     The webhook action (create, update, delete, test).
	 * @param int    $webhook_id The webhook ID (for update/delete/test).
	 */
	private static function handle_webhook_action( $action, $webhook_id ) {
		$redirect_url = admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks' );

		switch ( $action ) {
			case 'create':
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_settings_save.
				$data = array(
					'name'    => isset( $_POST['webhook_name'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_name'] ) ) : '',
					'url'     => isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '',
					'secret'  => isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '',
					'events'  => isset( $_POST['webhook_events'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['webhook_events'] ) ) : array(),
					'enabled' => ! empty( $_POST['webhook_enabled'] ),
				);
				// phpcs:enable WordPress.Security.NonceVerification.Missing

				if ( empty( $data['name'] ) || empty( $data['url'] ) ) {
					set_transient( 'nbuf_webhook_error', __( 'Webhook name and URL are required.', 'nobloat-user-foundry' ), 30 );
				} else {
					$result = NBUF_Webhooks::create( $data );
					if ( $result ) {
						set_transient( 'nbuf_settings_saved', true, 30 );
					} else {
						set_transient( 'nbuf_webhook_error', __( 'Failed to create webhook.', 'nobloat-user-foundry' ), 30 );
					}
				}
				break;

			case 'update':
				if ( ! $webhook_id ) {
					break;
				}

				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_settings_save.
				$data = array(
					'name'    => isset( $_POST['webhook_name'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_name'] ) ) : '',
					'url'     => isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '',
					'secret'  => isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '',
					'events'  => isset( $_POST['webhook_events'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['webhook_events'] ) ) : array(),
					'enabled' => ! empty( $_POST['webhook_enabled'] ),
				);
				// phpcs:enable WordPress.Security.NonceVerification.Missing

				$result = NBUF_Webhooks::update( $webhook_id, $data );
				if ( $result ) {
					set_transient( 'nbuf_settings_saved', true, 30 );
				} else {
					set_transient( 'nbuf_webhook_error', __( 'Failed to update webhook.', 'nobloat-user-foundry' ), 30 );
				}
				break;

			case 'delete':
				if ( ! $webhook_id ) {
					break;
				}

				$result = NBUF_Webhooks::delete( $webhook_id );
				if ( $result ) {
					set_transient( 'nbuf_webhook_deleted', true, 30 );
				} else {
					set_transient( 'nbuf_webhook_error', __( 'Failed to delete webhook.', 'nobloat-user-foundry' ), 30 );
				}
				break;

			case 'test':
				if ( ! $webhook_id ) {
					break;
				}

				$result = NBUF_Webhooks::test( $webhook_id );
				if ( $result['success'] ) {
					set_transient( 'nbuf_webhook_test_success', $result['message'], 30 );
				} else {
					set_transient( 'nbuf_webhook_error', $result['message'], 30 );
				}
				break;
		}

		$redirect_url = add_query_arg( 'settings-updated', 'true', $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Display settings saved notice.
	 */
	public static function display_settings_notices() {
		/* Check if we're on a plugin settings page */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display check.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$valid_pages = array( 'nobloat-foundry-users', 'nobloat-foundry-appearance' );
		if ( ! in_array( $page, $valid_pages, true ) ) {
			return;
		}

		/* Check for settings-updated parameter or transient */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display check.
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			delete_transient( 'nbuf_settings_saved' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Settings saved.', 'nobloat-user-foundry' ) . '</strong></p></div>';
		}

		/* Webhook-specific notices */
		$webhook_error = get_transient( 'nbuf_webhook_error' );
		if ( $webhook_error ) {
			delete_transient( 'nbuf_webhook_error' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $webhook_error ) . '</p></div>';
		}

		$webhook_deleted = get_transient( 'nbuf_webhook_deleted' );
		if ( $webhook_deleted ) {
			delete_transient( 'nbuf_webhook_deleted' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Webhook deleted.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		$webhook_test = get_transient( 'nbuf_webhook_test_success' );
		if ( $webhook_test ) {
			delete_transient( 'nbuf_webhook_test_success' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $webhook_test ) . '</p></div>';
		}
	}

	/**
	 * ==========================================================
	 * MIGRATE WP_OPTIONS TO CUSTOM TABLE
	 * ----------------------------------------------------------
	 * One-time migration of existing wp_options entries to
	 * the custom nbuf_options table.
	 * ==========================================================
	 */
	public static function maybe_migrate_wp_options() {
		/* Check if migration already done */
		if ( NBUF_Options::get( 'nbuf_wp_options_migrated', false ) ) {
			return;
		}

		global $wpdb;

		/* Get all nbuf_ options from wp_options */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wp_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'nbuf_%'
			)
		);

		if ( empty( $wp_options ) ) {
			/* No options to migrate, mark as done */
			NBUF_Options::update( 'nbuf_wp_options_migrated', true, false, 'system' );
			return;
		}

		/* Migrate each option */
		foreach ( $wp_options as $option ) {
			/* Skip if already exists in custom table */
			if ( NBUF_Options::exists( $option->option_name ) ) {
				continue;
			}

			/* Migrate to custom table */
			$value = maybe_unserialize( $option->option_value );
			NBUF_Options::update( $option->option_name, $value, true, 'settings' );
		}

		/* Delete from wp_options */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'nbuf_%'
			)
		);

		/* Mark migration as complete */
		NBUF_Options::update( 'nbuf_wp_options_migrated', true, false, 'system' );
	}

	/**
	 * Output nonce field and action for settings forms.
	 * Replaces settings_fields() for custom form handling.
	 */
	public static function settings_nonce_field() {
		wp_nonce_field( 'nbuf_save_settings', 'nbuf_settings_nonce' );
		echo '<input type="hidden" name="action" value="nbuf_save_settings">';
	}

	/**
	 * Auto-detect existing NoBloat pages.
	 *
	 * Runs on admin_init to find existing pages by slug and save their IDs.
	 * Does NOT create pages - only detects user-created pages with legacy shortcodes.
	 */
	public static function auto_detect_pages() {
		/* Only run page detection once per day to avoid overhead */
		$last_check = NBUF_Options::get( 'nbuf_pages_auto_detected', 0 );
		if ( $last_check && ( time() - $last_check ) < DAY_IN_SECONDS ) {
			return;
		}

		/* Page mappings: option_key => slug */
		$page_mappings = array(
			'nbuf_page_verification'     => 'nobloat-verify',
			'nbuf_page_password_reset'   => 'nobloat-reset',
			'nbuf_page_request_reset'    => 'nobloat-forgot-password',
			'nbuf_page_login'            => 'nobloat-login',
			'nbuf_page_registration'     => 'nobloat-register',
			'nbuf_page_account'          => 'nobloat-account',
			'nbuf_page_profile'          => 'nobloat-profile',
			'nbuf_page_logout'           => 'nobloat-logout',
			'nbuf_page_2fa_verify'       => 'nobloat-2fa-verify',
			'nbuf_page_totp_setup'       => 'nobloat-totp-setup',
			'nbuf_page_member_directory' => 'nobloat-members',
			'nbuf_universal_page_id'     => 'user-foundry',
		);

		foreach ( $page_mappings as $option_key => $slug ) {
			/* Skip if already set and page exists */
			$current_id = NBUF_Options::get( $option_key, 0 );
			if ( $current_id && get_post( $current_id ) ) {
				continue;
			}

			/* Try to find existing page by slug - do NOT create if missing */
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				NBUF_Options::update( $option_key, $page->ID, true, 'settings' );
			}
		}

		/* Mark as checked */
		NBUF_Options::update( 'nbuf_pages_auto_detected', time(), false, 'system' );
	}

	/**
	 * ==========================================================
	 * CHECK REQUIRED PAGES
	 * ----------------------------------------------------------
	 * Displays admin notice if required pages are missing.
	 * Checks if pages exist at the configured slug paths.
	 * Only runs on our settings page.
	 * ==========================================================
	 */
	public static function check_required_pages() {
		/* Only run on our settings page */
		$screen = get_current_screen();
		if ( ! $screen || 'nobloat-foundry_page_nobloat-foundry-users' !== $screen->id ) {
			return;
		}

		$missing_pages = array();

		/* Check verification page */
		$verify_page_id = NBUF_Options::get( 'nbuf_page_verification', 0 );
		$verify_page    = $verify_page_id ? get_post( $verify_page_id ) : null;

		if ( ! $verify_page || get_post_status( $verify_page->ID ) !== 'publish' ) {
			$missing_pages[] = __( 'Email Verification page not configured in System → Pages', 'nobloat-user-foundry' );
		} elseif ( strpos( $verify_page->post_content, '[nbuf_verify_page]' ) === false ) {
			/* Check if page has the correct shortcode */
			$missing_pages[] = sprintf( 'Email Verification page "%s" (missing shortcode [nbuf_verify_page])', esc_html( $verify_page->post_title ) );
		}

		/* Check password reset page */
		$reset_page_id = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
		$reset_page    = $reset_page_id ? get_post( $reset_page_id ) : null;

		if ( ! $reset_page || get_post_status( $reset_page->ID ) !== 'publish' ) {
			$missing_pages[] = __( 'Password Reset page not configured in System → Pages', 'nobloat-user-foundry' );
		} elseif ( strpos( $reset_page->post_content, '[nbuf_reset_form]' ) === false ) {
			/* Check if page has the correct shortcode */
			$missing_pages[] = sprintf( 'Password Reset page "%s" (missing shortcode [nbuf_reset_form])', esc_html( $reset_page->post_title ) );
		}

		/* Display warning if any pages are missing */
		if ( ! empty( $missing_pages ) ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'NoBloat User Foundry:', 'nobloat-user-foundry' ) . '</strong> ';
			echo esc_html__( 'Required pages are missing or misconfigured:', 'nobloat-user-foundry' );
			echo '<ul class="nbuf-notice-list">';
			foreach ( $missing_pages as $page_desc ) {
				/* SECURITY: Allow <code> tags for page path display */
				echo '<li>' . wp_kses_post( $page_desc ) . '</li>';
			}
			echo '</ul>';
			echo '<p>' . esc_html__( 'Please create the pages with the correct slugs and shortcodes, or deactivate and reactivate the plugin.', 'nobloat-user-foundry' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * ==========================================================
	 * ADMIN MENU - MULTI-PLUGIN ARCHITECTURE
	 * ----------------------------------------------------------
	 * Supports multiple NoBloat plugins sharing the same top-level menu.
	 * Each plugin adds its own submenu item under "User Foundry".
	 *
	 * Shared parent slug: 'nobloat-foundry'
	 * This plugin's slug: 'nobloat-foundry-users'
	 * ==========================================================
	 */
	public static function add_menu_page() {
		global $menu;

		/* Check if User Foundry top-level menu already exists */
		$menu_exists = false;
		if ( ! empty( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'nobloat-foundry' === $item[2] ) {
					$menu_exists = true;
					break;
				}
			}
		}

		/* Only create top-level menu if it doesn't exist yet */
		if ( ! $menu_exists ) {
			add_menu_page(
				__( 'User Foundry', 'nobloat-user-foundry' ),          // Page title.
				__( 'User Foundry', 'nobloat-user-foundry' ),          // Menu title.
				'list_users',                                          // Capability (same as Users menu).
				'nobloat-foundry',                                     // Menu slug.
				array( __CLASS__, 'render_settings_page' ),           // Callback.
				'dashicons-superhero',                                 // Icon.
				30                                                     // Position (after Comments).
			);
		}

		/* Add Settings submenu (first item - clicking "User Foundry" goes here) */
		add_submenu_page(
			'nobloat-foundry',                                     // Parent slug.
			__( 'Settings', 'nobloat-user-foundry' ),             // Page title.
			__( 'Settings', 'nobloat-user-foundry' ),             // Menu title.
			'manage_options',                                      // Capability.
			'nobloat-foundry-users',                               // Menu slug.
			array( __CLASS__, 'render_settings_page' )            // Callback.
		);
	}

	/**
	 * Redirect top-level menu click to WordPress Users page
	 */
	public static function redirect_to_users() {
		wp_safe_redirect( admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Add Roles submenu page.
	 *
	 * Added separately at priority 12 to ensure it appears after Appearance (priority 11).
	 */
	public static function add_roles_submenu() {
		$enable_custom_roles = NBUF_Options::get( 'nbuf_enable_custom_roles', true );
		if ( $enable_custom_roles ) {
			add_submenu_page(
				'nobloat-foundry',                                 // Parent slug (SHARED).
				__( 'Roles', 'nobloat-user-foundry' ),            // Page title.
				__( 'Roles', 'nobloat-user-foundry' ),            // Menu title.
				'manage_options',                                  // Capability.
				'nobloat-foundry-roles',                          // Menu slug (UNIQUE).
				array( 'NBUF_Roles_Page', 'render_page' )        // Callback.
			);
		}
	}

	/**
	 * Add All Users submenu and custom Users top-level menu.
	 *
	 * - Adds "All Users" submenu under User Foundry
	 * - Creates a "Users" top-level menu at position 31 (no submenus)
	 * - Hides the native WordPress Users menu
	 */
	public static function add_users_submenu() {
		/* Only modify menus if User Management System is enabled */
		if ( ! NBUF_Options::get( 'nbuf_user_manager_enabled', false ) ) {
			return;
		}

		global $menu, $submenu;

		/* Add "All Users" submenu under User Foundry */
		add_submenu_page(
			'nobloat-foundry',
			__( 'All Users', 'nobloat-user-foundry' ),
			__( 'All Users', 'nobloat-user-foundry' ),
			'list_users',
			'users.php'
		);

		/* Hide the native WordPress Users menu */
		remove_menu_page( 'users.php' );

		/* Add custom Users top-level menu at position 31 (right after User Foundry at 30) */
		/* Use a custom slug to avoid WordPress attaching submenus */
		$menu['31'] = array(
			__( 'Users', 'nobloat-user-foundry' ),
			'list_users',
			'nbuf-users-redirect',
			'',
			'menu-top menu-icon-users',
			'menu-users-nbuf',
			'dashicons-admin-users',
		);

		/* Remove any submenus that might attach to our custom menu */
		unset( $submenu['nbuf-users-redirect'] );
	}

	/**
	 * Fix custom Users menu link and handle highlighting.
	 *
	 * - Changes custom Users menu href to users.php (avoids 404)
	 * - User Foundry menu expands on users.php (shows "All Users" submenu as current)
	 * - Custom Users top-level menu highlights on all user pages
	 */
	public static function highlight_users_menu() {
		/* Only output menu JS if User Management System is enabled */
		if ( ! NBUF_Options::get( 'nbuf_user_manager_enabled', false ) ) {
			return;
		}

		global $pagenow;

		/* Always output script to fix the menu link href */
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			/* Fix custom Users menu link to point directly to users.php */
			var usersMenu = document.getElementById('menu-users-nbuf');
			if (usersMenu) {
				var menuLink = usersMenu.querySelector('a.menu-top');
				if (menuLink) {
					menuLink.href = '<?php echo esc_url( admin_url( 'users.php' ) ); ?>';
				}
			}
			<?php if ( in_array( $pagenow, array( 'users.php', 'user-edit.php', 'user-new.php', 'profile.php' ), true ) ) : ?>
			/* Highlight User Foundry menu and "All Users" submenu */
			var foundryMenu = document.getElementById('toplevel_page_nobloat-foundry');
			if (foundryMenu) {
				foundryMenu.classList.remove('wp-not-current-submenu');
				foundryMenu.classList.add('wp-has-current-submenu', 'wp-menu-open');
				var submenuLink = foundryMenu.querySelector('a[href="users.php"]');
				if (submenuLink) {
					submenuLink.classList.add('current');
					submenuLink.closest('li').classList.add('current');
				}
			}

			/* Highlight custom Users top-level menu */
			if (usersMenu) {
				usersMenu.classList.remove('wp-not-current-submenu');
				usersMenu.classList.add('wp-has-current-submenu', 'wp-menu-open', 'current');
				var menuLink = usersMenu.querySelector('a.menu-top');
				if (menuLink) {
					menuLink.classList.add('wp-has-current-submenu', 'wp-menu-open');
				}
			}
			<?php endif; ?>
		});
		</script>
		<?php
	}


	/**
	 * ==========================================================
	 * SANITIZE SETTINGS
	 * ----------------------------------------------------------
	 * Cleans all fields in the settings array.
	 * ==========================================================
	 *
	 * @param  array $input Raw input values.
	 * @return array Sanitized output.
	 */
	public static function sanitize_settings( $input ) {
		/*
		 * Only save if this setting was actually in the submitted form.
		 * This prevents other tabs' settings from being overwritten.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Settings API before sanitize callback.
		if ( ! isset( $_POST['nbuf_settings'] ) ) {
			/* Return existing value to prevent overwrite */
			return NBUF_Options::get( 'nbuf_settings', array() );
		}

		/* Start with existing settings to preserve unsubmitted fields */
		$existing = NBUF_Options::get( 'nbuf_settings', array() );
		$output   = array();

		/* Only update fields that were actually submitted */
		$output['hooks'] = isset( $input['hooks'] )
			? array_map( 'sanitize_text_field', (array) $input['hooks'] )
			: ( $existing['hooks'] ?? array() );

		$output['hooks_custom'] = isset( $input['hooks_custom'] )
			? sanitize_text_field( $input['hooks_custom'] )
			: ( $existing['hooks_custom'] ?? '' );

		$output['custom_hook_enabled'] = isset( $input['custom_hook_enabled'] )
			? ( ! empty( $input['custom_hook_enabled'] ) ? 1 : 0 )
			: ( $existing['custom_hook_enabled'] ?? 0 );

		$output['reverify_on_email_change'] = isset( $input['reverify_on_email_change'] )
			? ( ! empty( $input['reverify_on_email_change'] ) ? 1 : 0 )
			: ( $existing['reverify_on_email_change'] ?? 0 );

		$output['auto_verify_existing'] = isset( $input['auto_verify_existing'] )
			? ( ! empty( $input['auto_verify_existing'] ) ? 1 : 0 )
			: ( $existing['auto_verify_existing'] ?? 0 );

		$output['cleanup'] = isset( $input['cleanup'] )
			? array_map( 'sanitize_text_field', (array) $input['cleanup'] )
			: ( $existing['cleanup'] ?? array() );

		/* Save to custom options table */
		NBUF_Options::update( 'nbuf_settings', $output, true, 'settings' );

		return $output;
	}

	/**
	 * ==========================================================
	 * SANITIZE REGISTRATION FIELDS
	 * ----------------------------------------------------------
	 * Cleans all registration field settings.
	 * ==========================================================
	 *
	 * @param  array $input Raw input values.
	 * @return array Sanitized output.
	 */
	public static function sanitize_registration_fields( $input ) {
		/*
		 * Only save if this setting was actually in the submitted form.
		 * This prevents other tabs' settings from being overwritten.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by Settings API before sanitize callback.
		if ( ! isset( $_POST['nbuf_registration_fields'] ) ) {
			/* Return existing value to prevent overwrite */
			return NBUF_Options::get( 'nbuf_registration_fields', array() );
		}

		$output = array();

		/* Sanitize username and login methods */
		$allowed_username_methods = array( 'auto_random', 'auto_email', 'user_entered' );
		$allowed_login_methods    = array( 'email_only', 'username_only', 'email_or_username' );
		$allowed_address_modes    = array( 'simplified', 'full' );

		$output['username_method'] = in_array( $input['username_method'] ?? '', $allowed_username_methods, true )
		? $input['username_method']
		: 'auto_random';

		$output['login_method'] = in_array( $input['login_method'] ?? '', $allowed_login_methods, true )
		? $input['login_method']
		: 'email_or_username';

		$output['address_mode'] = in_array( $input['address_mode'] ?? '', $allowed_address_modes, true )
		? $input['address_mode']
		: 'simplified';

		/* Sanitize field configurations - get all fields dynamically from registry */
		$fields = array( 'first_name', 'last_name' ); // Core fields always included.

		/* Add all fields from the profile data registry */
		$field_registry = NBUF_Profile_Data::get_field_registry();
		foreach ( $field_registry as $category_data ) {
			$fields = array_merge( $fields, array_keys( $category_data['fields'] ) );
		}

		foreach ( $fields as $field ) {
			$output[ $field . '_enabled' ]  = ! empty( $input[ $field . '_enabled' ] ) && '0' !== ( $input[ $field . '_enabled' ] ?? '' );
			$output[ $field . '_required' ] = ! empty( $input[ $field . '_required' ] ) && '0' !== ( $input[ $field . '_required' ] ?? '' );
			$output[ $field . '_label' ]    = sanitize_text_field( $input[ $field . '_label' ] ?? '' );
		}

		/* Save to custom options table */
		NBUF_Options::update( 'nbuf_registration_fields', $output, true, 'settings' );

		return $output;
	}

	/**
	 * ==========================================================
	 * SANITIZE CHECKBOX
	 * ----------------------------------------------------------
	 * Sanitizes checkbox values to boolean.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return bool Sanitized checkbox value.
	 */
	public static function sanitize_checkbox( $input ) {
		return ! empty( $input ) && '0' !== $input;
	}

	/**
	 * ==========================================================
	 * SANITIZE CHECKBOX GROUP
	 * ----------------------------------------------------------
	 * Sanitizes checkbox group values to array of booleans.
	 * Used for multi-checkbox settings like logging categories.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value (array or other).
	 * @return array Sanitized array with boolean values.
	 */
	public static function sanitize_checkbox_group( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = ! empty( $value ) && '0' !== $value;
		}

		return $sanitized;
	}

	/**
	 * ==========================================================
	 * SANITIZE POST TYPE ARRAY
	 * ----------------------------------------------------------
	 * Sanitizes array of post type names.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return array Sanitized array of post type slugs.
	 */
	public static function sanitize_post_type_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'post', 'page' );
		}

		$valid_post_types = get_post_types( array( 'public' => true ) );
		$sanitized        = array();

		foreach ( $input as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $valid_post_types, true ) ) {
				$sanitized[] = $post_type;
			}
		}

		return ! empty( $sanitized ) ? $sanitized : array( 'post', 'page' );
	}

	/**
	 * ==========================================================
	 * SANITIZE TAXONOMY ARRAY
	 * ----------------------------------------------------------
	 * Sanitizes array of taxonomy names.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return array Sanitized array of taxonomy slugs.
	 */
	public static function sanitize_taxonomy_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'category', 'post_tag' );
		}

		$valid_taxonomies = get_taxonomies( array( 'public' => true ) );
		$sanitized        = array();

		foreach ( $input as $taxonomy ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( in_array( $taxonomy, $valid_taxonomies, true ) ) {
				$sanitized[] = $taxonomy;
			}
		}

		return ! empty( $sanitized ) ? $sanitized : array( 'category', 'post_tag' );
	}

	/**
	 * ==========================================================
	 * SANITIZE PAGE ID
	 * ----------------------------------------------------------
	 * Sanitizes page ID values to positive integer.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return int Sanitized page ID.
	 */
	public static function sanitize_page_id( $input ) {
		return absint( $input );
	}

	/**
	 * ==========================================================
	 * SANITIZE POSITIVE INTEGER
	 * ----------------------------------------------------------
	 * Sanitizes positive integer values (minimum 1).
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return int Sanitized positive integer.
	 */
	public static function sanitize_positive_int( $input ) {
		$value = absint( $input );
		return max( 1, $value );
	}

	/**
	 * Sanitize trusted proxy IP addresses
	 *
	 * Takes comma or newline-separated list of IP addresses and validates each one.
	 * Returns array of valid IPv4/IPv6 addresses only.
	 *
	 * @param  string $input Raw textarea input from admin form.
	 * @return array Array of valid IP addresses.
	 */
	public static function sanitize_trusted_proxies( $input ) {
		/* Handle empty input */
		if ( empty( $input ) ) {
			return array();
		}

		/* Split by commas and newlines */
		$ips = preg_split( '/[,\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY );

		/* Validate and clean each IP */
		$valid_ips = array();

		foreach ( $ips as $ip ) {
			$ip = trim( $ip );

			/* Skip empty entries */
			if ( empty( $ip ) ) {
				continue;
			}

			/* Validate IP address (IPv4 or IPv6) */
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$valid_ips[] = $ip;
			}
		}

		/* Remove duplicates and reindex */
		return array_values( array_unique( $valid_ips ) );
	}

	/**
	 * ==========================================================
	 * RENDER SETTINGS
	 * ----------------------------------------------------------
	 * Renders a settings form from an array of field definitions.
	 * Used by settings tabs that define fields programmatically.
	 * ==========================================================
	 *
	 * @param array  $fields  Array of field definitions.
	 * @param string $title   Form title.
	 * @param string $tab     Active tab for form redirect.
	 * @param string $subtab  Active subtab for form redirect.
	 */
	public static function render_settings( array $fields, string $title = '', string $tab = 'system', string $subtab = '' ) {
		/* Collect checkbox field IDs for unchecked state handling */
		$checkbox_ids = array();
		foreach ( $fields as $field ) {
			$type = $field['type'] ?? 'text';
			if ( in_array( $type, array( 'checkbox', 'checkbox_group' ), true ) && ! empty( $field['id'] ) ) {
				$checkbox_ids[] = $field['id'];
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			self::settings_nonce_field();
			settings_errors( 'nbuf_settings' );
			?>
			<input type="hidden" name="nbuf_active_tab" value="<?php echo esc_attr( $tab ); ?>">
			<input type="hidden" name="nbuf_active_subtab" value="<?php echo esc_attr( $subtab ); ?>">
			<?php foreach ( $checkbox_ids as $checkbox_id ) : ?>
			<input type="hidden" name="nbuf_form_checkboxes[]" value="<?php echo esc_attr( $checkbox_id ); ?>">
			<?php endforeach; ?>

			<?php
			$in_table = false;

			foreach ( $fields as $field ) {
				$type = $field['type'] ?? 'text';

				/* Handle section headers */
				if ( 'section' === $type ) {
					if ( $in_table ) {
						echo '</table>';
						$in_table = false;
					}
					echo '<h2>' . esc_html( $field['title'] ) . '</h2>';
					if ( ! empty( $field['desc'] ) ) {
						echo '<p class="description">' . wp_kses_post( $field['desc'] ) . '</p>';
					}
					continue;
				}

				/* Start table if not in one */
				if ( ! $in_table ) {
					echo '<table class="form-table">';
					$in_table = true;
				}

				$id      = $field['id'] ?? '';
				$label   = $field['title'] ?? '';
				$desc    = $field['desc'] ?? '';
				$default = $field['default'] ?? '';
				$value   = NBUF_Options::get( $id, $default );

				echo '<tr>';
				echo '<th scope="row">' . esc_html( $label ) . '</th>';
				echo '<td>';

				switch ( $type ) {
					case 'checkbox':
						echo '<input type="hidden" name="' . esc_attr( $id ) . '" value="0">';
						echo '<label>';
						echo '<input type="checkbox" name="' . esc_attr( $id ) . '" value="1" ' . checked( $value, true, false ) . '>';
						if ( $desc ) {
							echo ' ' . wp_kses_post( $desc );
						}
						echo '</label>';
						break;

					case 'checkbox_group':
						$options = $field['options'] ?? array();
						if ( ! is_array( $value ) ) {
							$value = $default;
						}
						echo '<fieldset>';
						foreach ( $options as $opt_key => $opt_label ) {
							$checked = isset( $value[ $opt_key ] ) && $value[ $opt_key ];
							echo '<label class="nbuf-checkbox-label">';
							echo '<input type="checkbox" name="' . esc_attr( $id ) . '[' . esc_attr( $opt_key ) . ']" value="1" ' . checked( $checked, true, false ) . '>';
							echo ' ' . esc_html( $opt_label );
							echo '</label><br>';
						}
						echo '</fieldset>';
						if ( $desc ) {
							echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
						}
						break;

					case 'select':
						$options = $field['options'] ?? array();
						echo '<select name="' . esc_attr( $id ) . '">';
						foreach ( $options as $opt_key => $opt_label ) {
							echo '<option value="' . esc_attr( $opt_key ) . '" ' . selected( $value, $opt_key, false ) . '>' . esc_html( $opt_label ) . '</option>';
						}
						echo '</select>';
						if ( $desc ) {
							echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
						}
						break;

					case 'text':
					default:
						echo '<input type="text" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
						if ( $desc ) {
							echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
						}
						break;
				}

				echo '</td>';
				echo '</tr>';
			}

			if ( $in_table ) {
				echo '</table>';
			}

			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * ==========================================================
	 * GET TAB STRUCTURE
	 * ----------------------------------------------------------
	 * Returns the two-level tab structure definition.
	 * ==========================================================
	 *
	 * @return array Tab structure definition.
	 */
	public static function get_tab_structure() {
		return array(
			'system'      => array(
				'label'   => __( 'System', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'status'    => __( 'Status', 'nobloat-user-foundry' ),
					'general'   => __( 'General', 'nobloat-user-foundry' ),
					'email'     => __( 'Email', 'nobloat-user-foundry' ),
					'pages'     => __( 'Pages', 'nobloat-user-foundry' ),
					'hooks'     => __( 'Hooks', 'nobloat-user-foundry' ),
					'redirects' => __( 'Redirects', 'nobloat-user-foundry' ),
					'cleanup'   => __( 'Cleanup', 'nobloat-user-foundry' ),
				),
			),
			'security'    => array(
				'label'   => __( 'Security', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'login-limits'            => __( 'Login Limits', 'nobloat-user-foundry' ),
					'registration-protection' => __( 'Registration', 'nobloat-user-foundry' ),
					'passwords'               => __( 'Passwords', 'nobloat-user-foundry' ),
					'app-passwords' => __( 'App Passwords', 'nobloat-user-foundry' ),
					'passkeys'      => __( 'Passkeys', 'nobloat-user-foundry' ),
					'2fa-settings'  => __( '2FA Config', 'nobloat-user-foundry' ),
					'2fa-email'     => __( 'Email Auth', 'nobloat-user-foundry' ),
					'2fa-totp'      => __( 'Authenticator', 'nobloat-user-foundry' ),
					'backup-codes'  => __( 'Backup Codes', 'nobloat-user-foundry' ),
				),
			),
			'gdpr'        => array(
				'label'   => __( 'GDPR', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'privacy'       => __( 'Privacy', 'nobloat-user-foundry' ),
					'logging'       => __( 'Logging', 'nobloat-user-foundry' ),
					'notices'       => __( 'Notices', 'nobloat-user-foundry' ),
					'history'       => __( 'History', 'nobloat-user-foundry' ),
					'notifications' => __( 'Notifications', 'nobloat-user-foundry' ),
					'tools'         => __( 'Tools', 'nobloat-user-foundry' ),
				),
			),
			'users'       => array(
				'label'   => __( 'Users', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'registration'   => __( 'Registration', 'nobloat-user-foundry' ),
					'account'        => __( 'Account', 'nobloat-user-foundry' ),
					'profile-fields' => __( 'Profile Fields', 'nobloat-user-foundry' ),
					'profiles'       => __( 'Profiles & Photos', 'nobloat-user-foundry' ),
					'directory'      => __( 'Member Directory', 'nobloat-user-foundry' ),
				),
			),
			'integration' => array(
				'label'   => __( 'Integration', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'woocommerce'  => __( 'WooCommerce', 'nobloat-user-foundry' ),
					'restrictions' => __( 'Restrictions', 'nobloat-user-foundry' ),
					'webhooks'     => __( 'Webhooks', 'nobloat-user-foundry' ),
				),
			),
			'tools'       => array(
				'label'   => __( 'Tools', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'merge-accounts' => __( 'Merge Accounts', 'nobloat-user-foundry' ),
					'migration'      => __( 'Migration', 'nobloat-user-foundry' ),
					'diagnostics'    => __( 'Diagnostics', 'nobloat-user-foundry' ),
					'tests'          => __( 'Tests', 'nobloat-user-foundry' ),
				),
			),
			'docs'        => array(
				'label'   => __( 'Docs', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'overview'   => __( 'Overview', 'nobloat-user-foundry' ),
					'shortcodes' => __( 'Shortcodes', 'nobloat-user-foundry' ),
					'hooks'      => __( 'Hooks & Filters', 'nobloat-user-foundry' ),
					'api'        => __( 'API Reference', 'nobloat-user-foundry' ),
				),
			),
		);
	}

	/**
	 * ==========================================================
	 * GET ACTIVE TAB
	 * ----------------------------------------------------------
	 * Returns the currently active outer tab from URL parameter.
	 * Defaults to 'system' if not specified or invalid.
	 * ==========================================================
	 *
	 * @return string Active tab slug.
	 */
	public static function get_active_tab() {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		$tab       = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'system';
		$structure = self::get_tab_structure();

		/* Validate tab exists */
		if ( ! isset( $structure[ $tab ] ) ) {
			$tab = 'system';
		}

		return $tab;
	}

	/**
	 * ==========================================================
	 * GET ACTIVE SUBTAB
	 * ----------------------------------------------------------
	 * Returns the currently active inner subtab from URL parameter.
	 * Defaults to first subtab in the active outer tab.
	 * ==========================================================
	 *
	 * @return string Active subtab slug.
	 */
	public static function get_active_subtab() {
		$active_tab = self::get_active_tab();
		$structure  = self::get_tab_structure();
		$subtabs    = array_keys( $structure[ $active_tab ]['subtabs'] );

		/* Handle tabs with no subtabs */
		if ( empty( $subtabs ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only subtab selection.
		$subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : $subtabs[0];

		/* Validate subtab exists in current tab */
		if ( ! in_array( $subtab, $subtabs, true ) ) {
			$subtab = $subtabs[0];
		}

		return $subtab;
	}

	/**
	 * ==========================================================
	 * RENDER SETTINGS PAGE
	 * ----------------------------------------------------------
	 * Outputs two-level tabbed layout with outer and inner tabs.
	 * ==========================================================
	 */
	public static function render_settings_page() {
		$structure     = self::get_tab_structure();
		$active_tab    = self::get_active_tab();
		$active_subtab = self::get_active_subtab();

		?>
		<div class="wrap" id="nbuf-settings">
			<h1><?php esc_html_e( 'Settings', 'nobloat-user-foundry' ); ?></h1>

			<!-- Outer tabs (main navigation) -->
			<div class="nbuf-outer-tabs">
		<?php foreach ( $structure as $tab_key => $tab_data ) : ?>
					<a href="?page=nobloat-foundry-users&tab=<?php echo esc_attr( $tab_key ); ?>"
						class="nbuf-outer-tab-link<?php echo $active_tab === $tab_key ? ' active' : ''; ?>"
						data-tab="<?php echo esc_attr( $tab_key ); ?>">
			<?php echo esc_html( $tab_data['label'] ); ?>
					</a>
		<?php endforeach; ?>
			</div>

			<!-- Tab content areas -->
		<?php foreach ( $structure as $tab_key => $tab_data ) : ?>
				<div id="nbuf-tab-<?php echo esc_attr( $tab_key ); ?>"
					class="nbuf-outer-tab-content<?php echo $active_tab === $tab_key ? ' active' : ''; ?>">

					<!-- Inner tabs (sub-navigation) -->
					<div class="nbuf-inner-tabs">
			<?php
			$subtab_count  = 0;
			$total_subtabs = count( $tab_data['subtabs'] );
			foreach ( $tab_data['subtabs'] as $subtab_key => $subtab_label ) :
				++$subtab_count;
				$is_active = ( $active_tab === $tab_key && $active_subtab === $subtab_key );
				?>
							<a href="?page=nobloat-foundry-users&tab=<?php echo esc_attr( $tab_key ); ?>&subtab=<?php echo esc_attr( $subtab_key ); ?>"
								class="nbuf-inner-tab-link<?php echo $is_active ? ' active' : ''; ?>"
								data-subtab="<?php echo esc_attr( $subtab_key ); ?>">
				<?php echo esc_html( $subtab_label ); ?>
							</a>
				<?php
				if ( $subtab_count < $total_subtabs ) :
					?>
								|
								<?php
				endif;
				?>
			<?php endforeach; ?>
					</div>

					<!-- Subtab content areas -->
			<?php
			foreach ( $tab_data['subtabs'] as $subtab_key => $subtab_label ) :
				$is_active = ( $active_tab === $tab_key && $active_subtab === $subtab_key );
				$file_path = NBUF_INCLUDE_DIR . 'user-tabs/' . $tab_key . '/' . $subtab_key . '.php';
				?>
						<div id="nbuf-subtab-<?php echo esc_attr( $subtab_key ); ?>"
							class="nbuf-inner-tab-content<?php echo $is_active ? ' active' : ''; ?>">
				<?php
				if ( file_exists( $file_path ) ) {
					include $file_path;
				} else {
					echo '<div class="notice notice-warning"><p>';
					echo esc_html(
						sprintf(
						/* translators: %s: File path */
							__( 'Content file not yet created: %s', 'nobloat-user-foundry' ),
							'user-tabs/' . $tab_key . '/' . $subtab_key . '.php'
						)
					);
					echo '</p></div>';
				}
				?>
						</div>
			<?php endforeach; ?>

				</div>
		<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * AJAX template reset handler.
	 *
	 * Restores templates to defaults on request.
	 */
	public static function ajax_reset_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'nobloat-user-foundry' ) );
		}

		check_ajax_referer( 'nbuf_ajax', 'nonce' );

		$type       = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$file_map   = array(
			/* Email verification */
			'html'                    => 'email-verification.html',
			'text'                    => 'email-verification.txt',
			/* Welcome email */
			'welcome-html'            => 'welcome-email.html',
			'welcome-text'            => 'welcome-email.txt',
			/* Admin notification */
			'admin-new-user-html'     => 'admin-new-user.html',
			'admin-new-user-text'     => 'admin-new-user.txt',
			/* 2FA email code */
			'2fa-html'                => '2fa-email-code.html',
			'2fa-text'                => '2fa-email-code.txt',
			/* Expiration emails */
			'expiration-warning-html' => 'expiration-warning.html',
			'expiration-warning-text' => 'expiration-warning.txt',
			'expiration-notice-html'  => 'expiration-notice.html',
			'expiration-notice-text'  => 'expiration-notice.txt',
			/* Password reset email */
			'password-reset-html'     => 'password-reset.html',
			'password-reset-text'     => 'password-reset.txt',
			/* Form templates */
			'login-form'              => 'login-form.html',
			'registration-form'       => 'registration-form.html',
			'account-page'            => 'account-page.html',
			'request-reset-form'      => 'request-reset-form.html',
			'reset-form'              => 'reset-form.html',
			'2fa-verify'              => '2fa-verify.html',
			'2fa-setup-totp'          => '2fa-setup-totp.html',
			'2fa-backup-codes'        => '2fa-backup-codes.html',
			/* Policy templates */
			'policy-privacy-html'     => 'policy-privacy.html',
			'policy-terms-html'       => 'policy-terms.html',
			/* Page templates */
			'public-profile-html'         => 'public-profile.html',
			'member-directory-html'       => 'member-directory.html',
			'member-directory-list-html'  => 'member-directory-list.html',
			'account-data-export-html'    => 'account-data-export.html',
			'version-history-viewer-html' => 'version-history-viewer.html',
		);
		$option_map = array(
			/* Email verification */
			'html'                    => 'nbuf_email_template_html',
			'text'                    => 'nbuf_email_template_text',
			/* Welcome email */
			'welcome-html'            => 'nbuf_welcome_email_html',
			'welcome-text'            => 'nbuf_welcome_email_text',
			/* Admin notification */
			'admin-new-user-html'     => 'nbuf_admin_new_user_html',
			'admin-new-user-text'     => 'nbuf_admin_new_user_text',
			/* 2FA email code */
			'2fa-html'                => 'nbuf_2fa_email_html',
			'2fa-text'                => 'nbuf_2fa_email_text',
			/* Expiration emails */
			'expiration-warning-html' => 'nbuf_expiration_warning_email_html',
			'expiration-warning-text' => 'nbuf_expiration_warning_email_text',
			'expiration-notice-html'  => 'nbuf_expiration_notice_email_html',
			'expiration-notice-text'  => 'nbuf_expiration_notice_email_text',
			/* Password reset email */
			'password-reset-html'     => 'nbuf_password_reset_email_html',
			'password-reset-text'     => 'nbuf_password_reset_email_text',
			/* Form templates */
			'login-form'              => 'nbuf_login_form_template',
			'registration-form'       => 'nbuf_registration_form_template',
			'account-page'            => 'nbuf_account_page_template',
			'request-reset-form'      => 'nbuf_request_reset_form_template',
			'reset-form'              => 'nbuf_reset_form_template',
			'2fa-verify'              => 'nbuf_2fa_verify_template',
			'2fa-setup-totp'          => 'nbuf_2fa_setup_totp_template',
			'2fa-backup-codes'        => 'nbuf_2fa_backup_codes_template',
			/* Policy templates */
			'policy-privacy-html'     => 'nbuf_policy_privacy_html',
			'policy-terms-html'       => 'nbuf_policy_terms_html',
			/* Page templates */
			'public-profile-html'         => 'nbuf_public_profile_template',
			'member-directory-html'       => 'nbuf_member_directory_template',
			'member-directory-list-html'  => 'nbuf_member_directory_list_template',
			'account-data-export-html'    => 'nbuf_account_data_export_template',
			'version-history-viewer-html' => 'nbuf_version_history_viewer_template',
		);

		if ( empty( $file_map[ $type ] ) ) {
			wp_send_json_error( __( 'Invalid template type.', 'nobloat-user-foundry' ) );
		}

		$path = NBUF_TEMPLATES_DIR . $file_map[ $type ];
		if ( ! file_exists( $path ) ) {
			wp_send_json_error( __( 'Default template file not found.', 'nobloat-user-foundry' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file, not remote URL
		$content = file_get_contents( $path );

		if ( false === $content ) {
			NBUF_Security_Log::log(
				'file_read_failed',
				'critical',
				'Failed to read template file during AJAX reset',
				array(
					'file_path'     => $path,
					'template_type' => $type,
					'operation'     => 'ajax_reset_template',
					'user_id'       => get_current_user_id(),
				)
			);
			wp_send_json_error( __( 'Failed to read template file.', 'nobloat-user-foundry' ) );
		}

		/* Delete the custom template from database so it falls back to file */
		if ( isset( $option_map[ $type ] ) ) {
			NBUF_Options::delete( $option_map[ $type ] );
		}

		/* Clear Template Manager cache for this template */
		NBUF_Template_Manager::clear_cache( $type );

		wp_send_json_success(
			array(
				'message' => __( 'Template restored to default successfully.', 'nobloat-user-foundry' ),
				'content' => $content,
			)
		);
	}

	/**
	 * AJAX style reset handler.
	 *
	 * Restores CSS styles to defaults on request.
	 */
	public static function ajax_reset_style() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'nobloat-user-foundry' ) );
		}

		check_ajax_referer( 'nbuf_ajax', 'nonce' );

		$template   = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$file_map   = array(
			'reset-page'        => 'reset-page.css',
			'login-page'        => 'login-page.css',
			'registration-page' => 'registration-page.css',
			'account-page'      => 'account-page.css',
			'2fa-setup'         => '2fa-setup.css',
			'profile'           => 'profile.css',
			'member-directory'  => 'member-directory.css',
			'version-history'   => 'version-history.css',
			'data-export'       => 'data-export.css',
		);
		$option_map = array(
			'reset-page'        => 'nbuf_reset_page_css',
			'login-page'        => 'nbuf_login_page_css',
			'registration-page' => 'nbuf_registration_page_css',
			'account-page'      => 'nbuf_account_page_css',
			'2fa-setup'         => 'nbuf_2fa_page_css',
			'profile'           => 'nbuf_profile_custom_css',
			'member-directory'  => 'nbuf_member_directory_custom_css',
			'version-history'   => 'nbuf_version_history_custom_css',
			'data-export'       => 'nbuf_data_export_custom_css',
		);

		if ( empty( $file_map[ $template ] ) ) {
			wp_send_json_error( __( 'Invalid CSS template type.', 'nobloat-user-foundry' ) );
		}

		$path = NBUF_TEMPLATES_DIR . $file_map[ $template ];
		if ( ! file_exists( $path ) ) {
			wp_send_json_error( __( 'Default CSS file not found.', 'nobloat-user-foundry' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file, not remote URL
		$content = file_get_contents( $path );

		if ( false === $content ) {
			NBUF_Security_Log::log(
				'file_read_failed',
				'critical',
				'Failed to read CSS file during AJAX reset',
				array(
					'file_path'     => $path,
					'template_type' => $template,
					'operation'     => 'ajax_reset_style',
					'user_id'       => get_current_user_id(),
				)
			);
			wp_send_json_error( __( 'Failed to read CSS file.', 'nobloat-user-foundry' ) );
		}

		/* Delete the custom CSS from database so it falls back to file */
		NBUF_Options::delete( $option_map[ $template ] );

		wp_send_json_success(
			array(
				'message' => __( 'CSS restored to default successfully.', 'nobloat-user-foundry' ),
				'content' => $content,
			)
		);
	}

	/**
	 * ==========================================================
	 * ENQUEUE ADMIN ASSETS
	 * ==========================================================
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		/* Load on all NoBloat User Foundry admin pages */
		if ( strpos( $hook, 'nobloat-foundry' ) === false && strpos( $hook, 'user-foundry' ) === false ) {
			return;
		}

		/* No external dependencies - using native HTML5 datetime inputs */
		wp_enqueue_style( 'nbuf-admin-css', NBUF_PLUGIN_URL . 'assets/css/admin/admin.css', array(), NBUF_VERSION );

		/* Enqueue consolidated admin UI styles (extracted from inline styles) */
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );
		$ui_css_file  = $use_minified ? 'admin-ui.min.css' : 'admin-ui.css';
		wp_enqueue_style( 'nbuf-admin-ui', NBUF_PLUGIN_URL . 'assets/css/admin/' . $ui_css_file, array(), NBUF_VERSION );

		wp_enqueue_script( 'nbuf-admin-js', NBUF_PLUGIN_URL . 'assets/js/admin/admin.js', array( 'jquery' ), NBUF_VERSION, true );

		wp_localize_script(
			'nbuf-admin-js',
			'nobloatEV',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nbuf_ajax' ),
			)
		);

		/*
		* Conditional script loading based on active tab/subtab.
		*/
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection for script loading.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only subtab selection for script loading.
		$current_subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : '';

		/* Tools tab specific scripts */
		if ( 'tools' === $current_tab ) {
			if ( 'config-portability' === $current_subtab ) {
				wp_enqueue_script( 'nbuf-config-portability', NBUF_PLUGIN_URL . 'assets/js/admin/config-portability.js', array( 'jquery' ), NBUF_VERSION, true );
			} elseif ( 'import' === $current_subtab ) {
				wp_enqueue_script( 'nbuf-bulk-import', NBUF_PLUGIN_URL . 'assets/js/admin/bulk-import.js', array( 'jquery' ), NBUF_VERSION, true );
			} elseif ( 'migration' === $current_subtab ) {
				/* Available migration plugins */
				$available_plugins = array(
					'ultimate-member' => array(
						'name'        => 'Ultimate Member',
						'plugin_file' => 'ultimate-member/ultimate-member.php',
						'class'       => 'NBUF_Migration_Ultimate_Member',
						'supports'    => array( 'profile_data', 'restrictions' ),
					),
					'buddypress'      => array(
						'name'        => 'BuddyPress',
						'plugin_file' => 'buddypress/bp-loader.php',
						'class'       => 'NBUF_Migration_BP_Profile',
						'supports'    => array( 'profile_data' ),
					),
				);

				wp_enqueue_script( 'nbuf-migration', NBUF_PLUGIN_URL . 'assets/js/admin/migration.js', array( 'jquery' ), NBUF_VERSION, true );
				wp_localize_script(
					'nbuf-migration',
					'NBUF_Migration',
					array(
						'ajax_url'     => admin_url( 'admin-ajax.php' ),
						'nonce'        => wp_create_nonce( 'nbuf_migration_nonce' ),
						'plugins_data' => $available_plugins,
						'i18n'         => array(
							/* Status labels */
							'plugin_status'           => __( 'Plugin Status', 'nobloat-user-foundry' ),
							'source_plugin_status'    => __( 'Source Plugin Status', 'nobloat-user-foundry' ),
							'active'                  => __( 'Active', 'nobloat-user-foundry' ),
							'inactive'                => __( 'Inactive', 'nobloat-user-foundry' ),
							'status'                  => __( 'Status', 'nobloat-user-foundry' ),
							'auto'                    => __( 'Auto', 'nobloat-user-foundry' ),
							'manual'                  => __( 'Manual', 'nobloat-user-foundry' ),
							'skip'                    => __( 'Skip', 'nobloat-user-foundry' ),

							/* Error messages */
							'error_loading_data'      => __( 'Error loading plugin data.', 'nobloat-user-foundry' ),
							'error_loading'           => __( 'Error loading plugin data. Please try again.', 'nobloat-user-foundry' ),
							'migration_failed'        => __( 'Migration failed.', 'nobloat-user-foundry' ),
							'migration_error'         => __( 'Migration failed. Please try again.', 'nobloat-user-foundry' ),

							/* Plugin data labels */
							'users_with_data'         => __( 'Users with Data', 'nobloat-user-foundry' ),
							'users'                   => __( 'Users', 'nobloat-user-foundry' ),
							'no_data'                 => __( 'No data', 'nobloat-user-foundry' ),
							'profile_fields'          => __( 'Profile Fields', 'nobloat-user-foundry' ),

							/* Field mapping labels */
							'source_field'            => __( 'Source Field', 'nobloat-user-foundry' ),
							'type'                    => __( 'Type', 'nobloat-user-foundry' ),
							'sample_value'            => __( 'Sample Value', 'nobloat-user-foundry' ),
							'priority'                => __( 'Priority', 'nobloat-user-foundry' ),
							'map_to'                  => __( 'Map To', 'nobloat-user-foundry' ),
							'skip_field'              => __( '-- Skip this field --', 'nobloat-user-foundry' ),

							/* Migration type labels */
							'profile_data'            => __( 'Profile Data', 'nobloat-user-foundry' ),
							'profile_data_desc'       => __( 'Migrate user profile fields (phone, company, address, social media, etc.)', 'nobloat-user-foundry' ),
							'copy_photos'             => __( 'Copy Profile & Cover Photos', 'nobloat-user-foundry' ),
							'copy_photos_desc'        => __( 'Copy user photos to NoBloat directory with WebP conversion (if enabled in Media settings)', 'nobloat-user-foundry' ),
							'content_restrictions'    => __( 'Content Restrictions', 'nobloat-user-foundry' ),
							'restrictions_desc'       => __( 'Migrate post/page access restrictions and visibility settings', 'nobloat-user-foundry' ),
							'adopt_roles'             => __( 'Adopt Custom Roles', 'nobloat-user-foundry' ),
							/* translators: %d: number of orphaned roles */
							'adopt_roles_desc'        => __( 'Import %d custom role(s) into NoBloat for management (user assignments are preserved)', 'nobloat-user-foundry' ),
							'user_roles'              => __( 'User Roles', 'nobloat-user-foundry' ),
							'roles_retained'          => __( 'All user roles and assignments are automatically retained. No custom roles to adopt.', 'nobloat-user-foundry' ),

							/* Restrictions preview */
							'content'                 => __( 'Content', 'nobloat-user-foundry' ),
							'restriction'             => __( 'Restriction', 'nobloat-user-foundry' ),
							'no_restrictions'         => __( 'No content restrictions found.', 'nobloat-user-foundry' ),
							'restrictions_will_migrate' => __( 'The following content restrictions will be migrated:', 'nobloat-user-foundry' ),
							/* translators: %d: total number of restrictions */
							'showing_first_10'        => __( 'Showing first 10 of %d restrictions', 'nobloat-user-foundry' ),

							/* Migration execution */
							'confirm_migration'       => __( 'Are you sure you want to start the migration? This will update data in your database.', 'nobloat-user-foundry' ),
							'migration_complete'      => __( 'Migration Complete!', 'nobloat-user-foundry' ),
							'complete'                => __( 'Complete', 'nobloat-user-foundry' ),
							'total'                   => __( 'Total', 'nobloat-user-foundry' ),
							'migrated'                => __( 'Migrated', 'nobloat-user-foundry' ),
							'skipped'                 => __( 'Skipped', 'nobloat-user-foundry' ),
							'errors'                  => __( 'Errors', 'nobloat-user-foundry' ),
						),
					)
				);
			}
		}
	}

	/**
	 * Preserve active tab and subtab after form save
	 *
	 * @param  string $location Redirect URL.
	 * @param  int    $status   HTTP status code.
	 * @return string Modified redirect URL.
	 */
	public static function preserve_active_tab_after_save( $location, $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $status required by WordPress wp_redirect filter signature

		/*
		* Preserve outer tab.
		*/

     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserving tab state on redirect after settings save.
		if ( ! empty( $_POST['nbuf_active_tab'] ) ) {
         // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			$tab      = sanitize_text_field( wp_unslash( $_POST['nbuf_active_tab'] ) );
			$location = add_query_arg( 'tab', $tab, $location );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserving tab state on redirect.
		} elseif ( ! empty( $_GET['tab'] ) ) {
			/*
			* Fallback to GET parameter if POST not set.
			*/
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab      = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			$location = add_query_arg( 'tab', $tab, $location );
		}

		/*
		* Preserve inner subtab.
		*/
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserving subtab state on redirect after settings save.
		if ( ! empty( $_POST['nbuf_active_subtab'] ) ) {
         // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			$subtab   = sanitize_text_field( wp_unslash( $_POST['nbuf_active_subtab'] ) );
			$location = add_query_arg( 'subtab', $subtab, $location );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserving subtab state on redirect.
		} elseif ( ! empty( $_GET['subtab'] ) ) {
			/*
			* Fallback to GET parameter if POST not set.
			*/
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$subtab   = sanitize_text_field( wp_unslash( $_GET['subtab'] ) );
			$location = add_query_arg( 'subtab', $subtab, $location );
		}

		return $location;
	}

	/**
	 * Check if current screen is a plugin page.
	 *
	 * @return bool True if on a plugin page.
	 */
	private static function is_plugin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		/* Check for User Foundry pages */
		if ( strpos( $screen->id, 'nobloat-foundry' ) !== false ) {
			return true;
		}
		if ( strpos( $screen->id, 'user-foundry' ) !== false ) {
			return true;
		}

		/* Check for Logs pages */
		if ( strpos( $screen->id, 'nbuf-' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Custom admin footer text for plugin pages.
	 *
	 * @param string $text Default footer text.
	 * @return string Modified footer text.
	 */
	public static function admin_footer_text( $text ) {
		if ( ! self::is_plugin_page() ) {
			return $text;
		}

		return sprintf(
			/* translators: %s: Mailborder link */
			esc_html__( 'Nobloat User Foundry by %s', 'nobloat-user-foundry' ),
			'<a href="https://www.mailborder.com" target="_blank" rel="noopener noreferrer">Mailborder</a>'
		);
	}

	/**
	 * Custom admin footer version text for plugin pages.
	 *
	 * @param string $text Default version text.
	 * @return string Modified version text.
	 */
	public static function admin_footer_version( $text ) {
		if ( ! self::is_plugin_page() ) {
			return $text;
		}

		return sprintf(
			/* translators: %s: plugin version number */
			esc_html__( 'Version %s', 'nobloat-user-foundry' ),
			NBUF_VERSION
		);
	}
}

// Initialize Settings Controller.
NBUF_Settings::init();