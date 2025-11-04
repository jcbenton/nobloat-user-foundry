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
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'check_required_pages' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_nbuf_reset_template', array( __CLASS__, 'ajax_reset_template' ) );
		add_action( 'wp_ajax_nbuf_reset_style', array( __CLASS__, 'ajax_reset_style' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'preserve_active_tab_after_save' ), 10, 2 );

		// Hook test email handler.
		NBUF_Test::init();
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

		$settings      = NBUF_Options::get( 'nbuf_settings', array() );
		$missing_pages = array();

		/* Check verification page */
		$verify_path = isset( $settings['verification_page'] ) ? $settings['verification_page'] : '/nbuf-verify';
		$verify_slug = ltrim( $verify_path, '/' );
		$verify_page = get_page_by_path( $verify_slug );

		if ( ! $verify_page || get_post_status( $verify_page->ID ) !== 'publish' ) {
			$missing_pages[] = sprintf( 'Email Verification page at <code>%s</code>', esc_html( $verify_path ) );
		} elseif ( strpos( $verify_page->post_content, '[nbuf_verify_page]' ) === false ) {
			/* Check if page has the correct shortcode */
			$missing_pages[] = sprintf( 'Email Verification page at <code>%s</code> (missing shortcode [nbuf_verify_page])', esc_html( $verify_path ) );
		}

		/* Check password reset page */
		$reset_path = isset( $settings['password_reset_page'] ) ? $settings['password_reset_page'] : '/nbuf-reset';
		$reset_slug = ltrim( $reset_path, '/' );
		$reset_page = get_page_by_path( $reset_slug );

		if ( ! $reset_page || get_post_status( $reset_page->ID ) !== 'publish' ) {
			$missing_pages[] = sprintf( 'Password Reset page at <code>%s</code>', esc_html( $reset_path ) );
		} elseif ( strpos( $reset_page->post_content, '[nbuf_reset_form]' ) === false ) {
			/* Check if page has the correct shortcode */
			$missing_pages[] = sprintf( 'Password Reset page at <code>%s</code> (missing shortcode [nbuf_reset_form])', esc_html( $reset_path ) );
		}

		/* Display warning if any pages are missing */
		if ( ! empty( $missing_pages ) ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'NoBloat User Foundry:', 'nobloat-user-foundry' ) . '</strong> ';
			echo esc_html__( 'Required pages are missing or misconfigured:', 'nobloat-user-foundry' );
			echo '<ul style="list-style:disc;margin-left:20px;">';
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
	 * Each plugin adds its own submenu item under "NoBloat Foundry".
	 *
	 * Shared parent slug: 'nobloat-foundry'
	 * This plugin's slug: 'nobloat-foundry-users'
	 * ==========================================================
	 */
	public static function add_menu_page() {
		global $menu;

		/* Check if NoBloat Foundry top-level menu already exists */
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
				__( 'NoBloat Foundry', 'nobloat-user-foundry' ),     // Page title.
				__( 'NoBloat Foundry', 'nobloat-user-foundry' ),     // Menu title.
				'manage_options',                                      // Capability.
				'nobloat-foundry',                                     // Menu slug (SHARED across all NoBloat plugins).
				array( __CLASS__, 'render_settings_page' ),           // Callback (first plugin loaded handles this).
				'dashicons-bolt',                                      // Icon.
				30                                                     // Position (after Comments).
			);
		}

		/* Always add this plugin's submenu item */
		add_submenu_page(
			'nobloat-foundry',                                     // Parent slug (SHARED).
			__( 'Settings', 'nobloat-user-foundry' ),             // Page title.
			__( 'Settings', 'nobloat-user-foundry' ),             // Menu title.
			'manage_options',                                      // Capability.
			'nobloat-foundry-users',                               // Menu slug (UNIQUE to this plugin).
			array( __CLASS__, 'render_settings_page' )            // Callback.
		);

		/* Add Roles submenu if custom roles are enabled */
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
	 * ==========================================================
	 * REGISTER SETTINGS
	 * ----------------------------------------------------------
	 * Define registered options and sanitizers.
	 * ==========================================================
	 */
	public static function register_settings() {
		register_setting(
			'nbuf_settings_group',
			'nbuf_settings',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);

		/* Master toggle */
		register_setting(
			'nbuf_settings_group',
			'nbuf_user_manager_enabled',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Feature toggles */
		register_setting(
			'nbuf_settings_group',
			'nbuf_require_verification',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_enable_login',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_enable_registration',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_notify_admin_registration',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_enable_password_reset',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_enable_custom_roles',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Logout settings */
		register_setting(
			'nbuf_settings_group',
			'nbuf_logout_behavior',
			array(
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'immediate', 'confirm' ), true ) ? $value : 'immediate';
				},
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_logout_redirect',
			array(
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'home', 'login', 'custom' ), true ) ? $value : 'home';
				},
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_logout_redirect_custom',
			array(
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		/* Default WordPress redirect settings */
		register_setting(
			'nbuf_settings_group',
			'nbuf_redirect_default_login',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_redirect_default_register',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_redirect_default_logout',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_redirect_default_lostpassword',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_redirect_default_resetpass',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Plugin page IDs */
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_verification',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_password_reset',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_request_reset',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_login',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_registration',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_account',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);
		register_setting(
			'nbuf_settings_group',
			'nbuf_page_logout',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ),
			)
		);

		/*
		==========================================================
		TEMPLATES
		----------------------------------------------------------
		Templates are now managed by NBUF_Template_Manager and
		stored in wp_nbuf_options table (NOT wp_options).
		This prevents bloat and improves performance.
		No register_setting() needed - handled in templates.php tab.
		==========================================================
		*/

		/* CSS options - stored in DB and written to disk */
		register_setting(
			'nbuf_styles_group',
			'nbuf_reset_page_css',
			array(
				'sanitize_callback' => 'wp_strip_all_tags',
			)
		);
		register_setting(
			'nbuf_styles_group',
			'nbuf_login_page_css',
			array(
				'sanitize_callback' => 'wp_strip_all_tags',
			)
		);
		register_setting(
			'nbuf_styles_group',
			'nbuf_registration_page_css',
			array(
				'sanitize_callback' => 'wp_strip_all_tags',
			)
		);
		register_setting(
			'nbuf_styles_group',
			'nbuf_account_page_css',
			array(
				'sanitize_callback' => 'wp_strip_all_tags',
			)
		);

		/* CSS optimization options */
		register_setting(
			'nbuf_styles_group',
			'nbuf_css_load_on_pages',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_styles_group',
			'nbuf_css_use_minified',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_styles_group',
			'nbuf_css_combine_files',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Security - Login limiting settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_enable_login_limiting',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_login_max_attempts',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_login_lockout_duration',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_login_trusted_proxies',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_trusted_proxies' ),
			)
		);

		/* Security - Password strength settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_password_requirements_enabled',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_min_strength',
			array(
				'sanitize_callback' => function ( $value ) {
					$valid = array( 'none', 'weak', 'medium', 'strong', 'very-strong' );
					return in_array( $value, $valid, true ) ? $value : 'medium';
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_min_length',
			array(
				'sanitize_callback' => function ( $value ) {
					$length = absint( $value );
					return max( self::PASSWORD_MIN_LENGTH, min( self::PASSWORD_MAX_LENGTH, $length ) );
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_require_uppercase',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_require_lowercase',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_require_numbers',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_require_special',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Security - Password enforcement settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_password_enforce_registration',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_enforce_profile_change',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_enforce_reset',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_admin_bypass',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Security - Weak password migration settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_password_force_weak_change',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_check_timing',
			array(
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'once', 'every' ), true ) ? $value : 'once';
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_grace_period',
			array(
				'sanitize_callback' => function ( $value ) {
					$days = absint( $value );
					return max( 0, min( 365, $days ) ); // Between 0-365 days.
				},
			)
		);

		/* Security - Password Expiration settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_password_expiration_enabled',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_expiration_days',
			array(
				'sanitize_callback' => function ( $value ) {
					$days = absint( $value );
					return max( self::RETENTION_DAYS_MIN, min( self::RETENTION_DAYS_MAX, $days ) );
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_expiration_admin_bypass',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_password_expiration_warning_days',
			array(
				'sanitize_callback' => function ( $value ) {
					$days = absint( $value );
					return max( 0, min( 90, $days ) ); // Between 0-90 days.
				},
			)
		);

		/* Security - Two-Factor Authentication - Email settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_email_method',
			array(
				'sanitize_callback' => function ( $value ) {
					$valid = array( 'disabled', 'required_admin', 'optional_all', 'required_all' );
					return in_array( $value, $valid, true ) ? $value : 'disabled';
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_email_code_length',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 4, min( 8, absint( $value ) ) ); // Between 4-8.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_email_expiration',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 1, min( 60, absint( $value ) ) ); // Between 1-60 minutes.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_email_rate_limit',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 1, min( 50, absint( $value ) ) ); // Between 1-50 attempts.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_email_rate_window',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 1, min( 120, absint( $value ) ) ); // Between 1-120 minutes.
				},
			)
		);

		/* Security - Two-Factor Authentication - TOTP settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_totp_method',
			array(
				'sanitize_callback' => function ( $value ) {
					$valid = array( 'disabled', 'optional', 'required_admin', 'required_all' );
					return in_array( $value, $valid, true ) ? $value : 'disabled';
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_totp_code_length',
			array(
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 6, 8 ), true ) ? absint( $value ) : 6;
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_totp_time_window',
			array(
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 30, 60 ), true ) ? absint( $value ) : 30;
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_totp_tolerance',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 0, min( 2, absint( $value ) ) ); // Between 0-2.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_totp_qr_size',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 100, min( 500, absint( $value ) ) ); // Between 100-500 pixels.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_qr_method',
			array(
				'sanitize_callback' => function ( $value ) {
					$valid = array( 'external', 'svg', 'auto' );
					return in_array( $value, $valid, true ) ? $value : 'external';
				},
			)
		);

		/* Security - Two-Factor Authentication - Backup codes */
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_backup_enabled',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_backup_count',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 5, min( 20, absint( $value ) ) ); // Between 5-20.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_backup_length',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 6, min( 12, absint( $value ) ) ); // Between 6-12.
				},
			)
		);

		/* Security - Two-Factor Authentication - General settings */
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_device_trust',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_admin_bypass',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_lockout_attempts',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 3, min( 20, absint( $value ) ) ); // Between 3-20.
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_grace_period',
			array(
				'sanitize_callback' => function ( $value ) {
					return max( 0, min( 30, absint( $value ) ) ); // Between 0-30 days.
				},
			)
		);

		/* 2FA Admin Notifications */
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_notify_lockout',
			array(
				'sanitize_callback' => function ( $value ) {
					return ! empty( $value ) ? 1 : 0;
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_2fa_notify_disable',
			array(
				'sanitize_callback' => function ( $value ) {
					return ! empty( $value ) ? 1 : 0;
				},
			)
		);

		/* Security - Account Verification & Approval */
		register_setting(
			'nbuf_security_group',
			'nbuf_require_approval',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_delete_unverified_days',
			array(
				'sanitize_callback' => function ( $value ) {
					return absint( $value );
				},
			)
		);
		register_setting(
			'nbuf_security_group',
			'nbuf_new_user_default_role',
			array(
				'sanitize_callback' => function ( $value ) {
					/* Validate against WordPress roles */
					$roles = wp_roles()->get_names();
					return in_array( $value, array_keys( $roles ), true ) ? sanitize_key( $value ) : 'subscriber';
				},
			)
		);

		/* Registration field options */
		register_setting(
			'nbuf_registration_group',
			'nbuf_registration_fields',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_registration_fields' ),
			)
		);

		/* Media - Image Optimization Settings */
		register_setting(
			'nbuf_media_group',
			'nbuf_convert_images_to_webp',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'nbuf_media_group',
			'nbuf_webp_quality',
			array(
				'sanitize_callback' => function ( $value ) {
					$quality = absint( $value );
					return ( $quality >= 1 && $quality <= 100 ) ? $quality : 85;
				},
			)
		);
		register_setting(
			'nbuf_media_group',
			'nbuf_strip_exif_data',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			)
		);

		/* Media - Profile Photo Settings */
		register_setting(
			'nbuf_media_group',
			'nbuf_profile_photo_max_width',
			array(
				'sanitize_callback' => function ( $value ) {
					$width = absint( $value );
					return ( $width >= 256 && $width <= 4096 ) ? $width : 1024;
				},
			)
		);
		register_setting(
			'nbuf_media_group',
			'nbuf_profile_photo_max_size',
			array(
				'sanitize_callback' => function ( $value ) {
					$size = absint( $value );
					return ( $size >= 1 && $size <= 50 ) ? $size : 5;
				},
			)
		);

		/* Media - Cover Photo Settings */
		register_setting(
			'nbuf_media_group',
			'nbuf_cover_photo_max_width',
			array(
				'sanitize_callback' => function ( $value ) {
					$width = absint( $value );
					return ( $width >= 800 && $width <= 4096 ) ? $width : 1920;
				},
			)
		);
		register_setting(
			'nbuf_media_group',
			'nbuf_cover_photo_max_height',
			array(
				'sanitize_callback' => function ( $value ) {
					$height = absint( $value );
					return ( $height >= 200 && $height <= 2048 ) ? $height : 600;
				},
			)
		);
		register_setting(
			'nbuf_media_group',
			'nbuf_cover_photo_max_size',
			array(
				'sanitize_callback' => function ( $value ) {
					$size = absint( $value );
					return ( $size >= 1 && $size <= 50 ) ? $size : 10;
				},
			)
		);
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
		$output = array();

		$output['hooks']                    = isset( $input['hooks'] ) ? array_map( 'sanitize_text_field', (array) $input['hooks'] ) : array();
		$output['hooks_custom']             = isset( $input['hooks_custom'] ) ? sanitize_text_field( $input['hooks_custom'] ) : '';
		$output['custom_hook_enabled']      = ! empty( $input['custom_hook_enabled'] ) ? 1 : 0;
		$output['reverify_on_email_change'] = ! empty( $input['reverify_on_email_change'] ) ? 1 : 0;
		$output['auto_verify_existing']     = ! empty( $input['auto_verify_existing'] ) ? 1 : 0;
		$output['cleanup']                  = isset( $input['cleanup'] ) ? array_map( 'sanitize_text_field', (array) $input['cleanup'] ) : array();

		$output['verification_page']   = self::sanitize_path_field( $input['verification_page'] ?? '/nbuf-verify', '/nbuf-verify', 'Verification' );
		$output['password_reset_page'] = self::sanitize_path_field( $input['password_reset_page'] ?? '/nbuf-reset', '/nbuf-reset', 'Password Reset' );

		return $output;
	}

	/**
	 * Sanitize path field
	 *
	 * @param string $raw     Raw input value.
	 * @param string $default Default value.
	 * @param string $label   Field label.
	 * @return string Sanitized path.
	 */
	private static function sanitize_path_field( $raw, $default, $label ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- $default parameter name chosen for clarity in this internal method.
		$raw = trim( $raw );

		if ( preg_match( '#^https?://#i', $raw ) ) {
			$parsed = wp_parse_url( $raw );
			$raw    = isset( $parsed['path'] ) ? $parsed['path'] : $default;
		}

		$raw = '/' . ltrim( $raw, '/' );

		if ( ! preg_match( '#^/[a-z0-9\-]+$#i', $raw ) ) {
			add_settings_error(
				'nbuf_settings',
				'invalid_' . sanitize_title( $label ) . '_slug',
				/* translators: 1: Field label, 2: Default path example */
				esc_html( sprintf( __( '%1$s URL must be a simple single-level path (e.g., %2$s)', 'nobloat-user-foundry' ), $label, $default ) ),
				'error'
			);
			return $default;
		}

		$slug = ltrim( $raw, '/' );

		/*
		* Note: We don't check if slug exists here because the activator
		* creates our pages, and this would cause false errors.
		*/

		return $raw;
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
		: 'email_only';

		$output['address_mode'] = in_array( $input['address_mode'] ?? '', $allowed_address_modes, true )
		? $input['address_mode']
		: 'simplified';

		/* Sanitize field configurations */
		$fields = array(
			'first_name',
			'last_name',
			'phone',
			'company',
			'job_title',
			'address',
			'city',
			'state',
			'postal_code',
			'country',
			'bio',
			'website',
		);

		foreach ( $fields as $field ) {
			$output[ $field . '_enabled' ]  = ! empty( $input[ $field . '_enabled' ] );
			$output[ $field . '_required' ] = ! empty( $input[ $field . '_required' ] );
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
	 * Sanitizes checkbox values and saves to custom options table.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return bool Sanitized checkbox value.
	 */
	public static function sanitize_checkbox( $input ) {
		$value = ! empty( $input ) ? true : false;

		/* Determine which option this is by checking the current filter */
		$option_name = current_filter();
		$option_name = str_replace( 'sanitize_option_', '', $option_name );

		/* Save to custom options table */
		if ( strpos( $option_name, 'nbuf_' ) === 0 ) {
			NBUF_Options::update( $option_name, $value, true, 'settings' );
		}

		return $value;
	}

	/**
	 * ==========================================================
	 * SANITIZE PAGE ID
	 * ----------------------------------------------------------
	 * Sanitizes page ID values and saves to custom options table.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return int Sanitized page ID.
	 */
	public static function sanitize_page_id( $input ) {
		$value = absint( $input );

		/* Determine which option this is by checking the current filter */
		$option_name = current_filter();
		$option_name = str_replace( 'sanitize_option_', '', $option_name );

		/* Save to custom options table */
		if ( strpos( $option_name, 'nbuf_' ) === 0 ) {
			NBUF_Options::update( $option_name, $value, true, 'settings' );
		}

		return $value;
	}

	/**
	 * ==========================================================
	 * SANITIZE POSITIVE INTEGER
	 * ----------------------------------------------------------
	 * Sanitizes positive integer values and saves to custom options table.
	 * ==========================================================
	 *
	 * @param  mixed $input Raw input value.
	 * @return int Sanitized positive integer.
	 */
	public static function sanitize_positive_int( $input ) {
		$value = absint( $input );

		/* Ensure at least 1 */
		if ( $value < 1 ) {
			$value = 1;
		}

		/* Determine which option this is by checking the current filter */
		$option_name = current_filter();
		$option_name = str_replace( 'sanitize_option_', '', $option_name );

		/* Save to custom options table */
		if ( strpos( $option_name, 'nbuf_' ) === 0 ) {
			NBUF_Options::update( $option_name, $value, true, 'settings' );
		}

		return $value;
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
			$value = array();
			NBUF_Options::update( 'nbuf_login_trusted_proxies', $value, true, 'settings' );
			return $value;
		}

		/* Split by commas and newlines */
		$ips = preg_split( '/[,\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY );

		/* Validate and clean each IP */
		$valid_ips   = array();
		$invalid_ips = array();

		foreach ( $ips as $ip ) {
			$ip = trim( $ip );

			/* Skip empty entries */
			if ( empty( $ip ) ) {
				continue;
			}

			/* Validate IP address (IPv4 or IPv6) */
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$valid_ips[] = $ip;
			} else {
				$invalid_ips[] = $ip;
			}
		}

		/* Show admin notice if any IPs were invalid */
		if ( ! empty( $invalid_ips ) ) {
			add_settings_error(
				'nbuf_security',
				'invalid_trusted_proxies',
				sprintf(
				/* translators: %s: comma-separated list of invalid IPs */
					__( 'Invalid IP addresses removed: %s', 'nobloat-user-foundry' ),
					implode( ', ', $invalid_ips )
				),
				'warning'
			);
		}

		/* Remove duplicates and reindex */
		$value = array_values( array_unique( $valid_ips ) );

		/* Save to custom options table */
		NBUF_Options::update( 'nbuf_login_trusted_proxies', $value, true, 'settings' );

		return $value;
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
					'pages'     => __( 'Pages', 'nobloat-user-foundry' ),
					'hooks'     => __( 'Hooks', 'nobloat-user-foundry' ),
					'redirects' => __( 'Redirects', 'nobloat-user-foundry' ),
					'gdpr'      => __( 'GDPR', 'nobloat-user-foundry' ),
					'cleanup'   => __( 'Cleanup', 'nobloat-user-foundry' ),
				),
			),
			'security'    => array(
				'label'   => __( 'Security', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'login-limits' => __( 'Login Limits', 'nobloat-user-foundry' ),
					'passwords'    => __( 'Passwords', 'nobloat-user-foundry' ),
					'2fa-settings' => __( '2FA Config', 'nobloat-user-foundry' ),
					'2fa-email'    => __( 'Email Auth', 'nobloat-user-foundry' ),
					'2fa-totp'     => __( 'Authenticator', 'nobloat-user-foundry' ),
				),
			),
			'media'       => array(
				'label'   => __( 'Media', 'nobloat-user-foundry' ),
				'subtabs' => array(),
			),
			'users'       => array(
				'label'   => __( 'Users', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'registration'         => __( 'Registration', 'nobloat-user-foundry' ),
					'verification'         => __( 'Verification', 'nobloat-user-foundry' ),
					'expiration'           => __( 'Expiration', 'nobloat-user-foundry' ),
					'profile-fields'       => __( 'Profile Fields', 'nobloat-user-foundry' ),
					'profiles'             => __( 'Profiles & Photos', 'nobloat-user-foundry' ),
					'directory'            => __( 'Member Directory', 'nobloat-user-foundry' ),
					'version-history'      => __( 'Version History', 'nobloat-user-foundry' ),
					'change-notifications' => __( 'Change Notifications', 'nobloat-user-foundry' ),
				),
			),
			'templates'   => array(
				'label'   => __( 'Templates', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'verification'       => __( 'Verification', 'nobloat-user-foundry' ),
					'welcome'            => __( 'Welcome', 'nobloat-user-foundry' ),
					'expiration'         => __( 'Expiration', 'nobloat-user-foundry' ),
					'2fa'                => __( '2FA', 'nobloat-user-foundry' ),
					'password-reset'     => __( 'Password Reset', 'nobloat-user-foundry' ),
					'admin-notification' => __( 'Admin Notification', 'nobloat-user-foundry' ),
				),
			),
			'styles'      => array(
				'label'   => __( 'Styles', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'login'       => __( 'Login', 'nobloat-user-foundry' ),
					'register'    => __( 'Register', 'nobloat-user-foundry' ),
					'account'     => __( 'Account', 'nobloat-user-foundry' ),
					'reset'       => __( 'Reset', 'nobloat-user-foundry' ),
					'css-options' => __( 'CSS Options', 'nobloat-user-foundry' ),
				),
			),
			'integration' => array(
				'label'   => __( 'Integration', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'woocommerce'  => __( 'WooCommerce', 'nobloat-user-foundry' ),
					'restrictions' => __( 'Restrictions', 'nobloat-user-foundry' ),
					'api'          => __( 'API', 'nobloat-user-foundry' ),
					'webhooks'     => __( 'Webhooks', 'nobloat-user-foundry' ),
				),
			),
			'tools'       => array(
				'label'   => __( 'Tools', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'merge-accounts' => __( 'Merge Accounts', 'nobloat-user-foundry' ),
					'migration'      => __( 'Migration', 'nobloat-user-foundry' ),
					'audit-log'      => __( 'Audit Log', 'nobloat-user-foundry' ),
					'security-log'   => __( 'Security Log', 'nobloat-user-foundry' ),
					'diagnostics'    => __( 'Diagnostics', 'nobloat-user-foundry' ),
					'tests'          => __( 'Tests', 'nobloat-user-foundry' ),
					'shortcodes'     => __( 'Shortcodes', 'nobloat-user-foundry' ),
					'documentation'  => __( 'Documentation', 'nobloat-user-foundry' ),
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
			'html'         => 'email-verification.html',
			'text'         => 'email-verification.txt',
			'welcome-html' => 'welcome-email.html',
			'welcome-text' => 'welcome-email.txt',
			'login-form'   => 'login-form.html',
		);
		$option_map = array(
			'html'         => 'nbuf_email_template_html',
			'text'         => 'nbuf_email_template_text',
			'welcome-html' => 'nbuf_welcome_email_html',
			'welcome-text' => 'nbuf_welcome_email_text',
			'login-form'   => 'nbuf_login_form_template',
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

		/* Use Template Manager to save (stores in custom table, not wp_options) */
		NBUF_Template_Manager::save_template( $type, $content );

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
			'reset-page' => 'reset-page.css',
			'login-page' => 'login-page.css',
		);
		$option_map = array(
			'reset-page' => 'nbuf_reset_page_css',
			'login-page' => 'nbuf_login_page_css',
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

		/* Use NBUF_Options to save (stores in custom table, not wp_options) */
		NBUF_Options::update( $option_map[ $template ], $content, false, 'styles' );

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
		if ( 'nobloat-foundry_page_nobloat-foundry-users' !== $hook ) {
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
						'supports'    => array( 'profile_data', 'restrictions', 'roles' ),
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
						'nonce'        => wp_create_nonce( 'nbuf_migration' ),
						'plugins_data' => $available_plugins,
						'i18n'         => array(
							'plugin_status'         => __( 'Plugin Status', 'nobloat-user-foundry' ),
							'source_plugin_status'  => __( 'Source Plugin Status', 'nobloat-user-foundry' ),
							'error_loading_data'    => __( 'Error loading plugin data.', 'nobloat-user-foundry' ),
							'error_loading'         => __( 'Error loading plugin data. Please try again.', 'nobloat-user-foundry' ),
							'users_with_data'       => __( 'Users with Data', 'nobloat-user-foundry' ),
							'users'                 => __( 'Users', 'nobloat-user-foundry' ),
							'no_data'               => __( 'No data', 'nobloat-user-foundry' ),
							'profile_fields'        => __( 'Profile Fields', 'nobloat-user-foundry' ),
							'source_field'          => __( 'Source Field', 'nobloat-user-foundry' ),
							'type'                  => __( 'Type', 'nobloat-user-foundry' ),
							'sample_value'          => __( 'Sample Value', 'nobloat-user-foundry' ),
							'priority'              => __( 'Priority', 'nobloat-user-foundry' ),
							'map_to'                => __( 'Map To', 'nobloat-user-foundry' ),
							'skip_field'            => __( '-- Skip this field --', 'nobloat-user-foundry' ),
							'showing_first'         => __( 'Showing first 10 of', 'nobloat-user-foundry' ),
							'custom_roles'          => __( 'Custom User Roles', 'nobloat-user-foundry' ),
							'role_key'              => __( 'Role Key', 'nobloat-user-foundry' ),
							'role_name'             => __( 'Role Name', 'nobloat-user-foundry' ),
							'capabilities'          => __( 'Capabilities', 'nobloat-user-foundry' ),
							'no_custom_roles'       => __( 'No custom roles found.', 'nobloat-user-foundry' ),
							'roles_migrated'        => __( 'The following custom roles will be migrated:', 'nobloat-user-foundry' ),
							'content_restrictions'  => __( 'Content Restrictions', 'nobloat-user-foundry' ),
							'content'               => __( 'Content', 'nobloat-user-foundry' ),
							'restriction'           => __( 'Restriction', 'nobloat-user-foundry' ),
							'status'                => __( 'Status', 'nobloat-user-foundry' ),
							'no_restrictions'       => __( 'No content restrictions found.', 'nobloat-user-foundry' ),
							'restrictions_migrated' => __( 'The following content restrictions will be migrated:', 'nobloat-user-foundry' ),
							'profile_data'          => __( 'Profile Data', 'nobloat-user-foundry' ),
							'migrate_profile'       => __( 'Migrate user profile fields (phone, company, address, social media, etc.)', 'nobloat-user-foundry' ),
							'custom_user_roles'     => __( 'Custom Roles', 'nobloat-user-foundry' ),
							'migrate_roles'         => __( 'Migrate custom roles with their capabilities and settings', 'nobloat-user-foundry' ),
							'restrictions'          => __( 'restrictions', 'nobloat-user-foundry' ),
							'migrate_restrictions'  => __( 'Migrate post/page access restrictions and visibility settings', 'nobloat-user-foundry' ),
							'confirm_migration'     => __( 'Are you sure you want to start the migration? This will update data in your database.', 'nobloat-user-foundry' ),
							'total'                 => __( 'Total:', 'nobloat-user-foundry' ),
							'migrated'              => __( 'Migrated:', 'nobloat-user-foundry' ),
							'skipped'               => __( 'Skipped:', 'nobloat-user-foundry' ),
							'errors'                => __( 'errors', 'nobloat-user-foundry' ),
							'migration_complete'    => __( 'Migration Complete!', 'nobloat-user-foundry' ),
							'complete'              => __( 'Complete', 'nobloat-user-foundry' ),
							'migration_failed'      => __( 'Migration failed.', 'nobloat-user-foundry' ),
							'migration_error'       => __( 'Migration failed. Please try again.', 'nobloat-user-foundry' ),
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
}

// Initialize Settings Controller.
NBUF_Settings::init();