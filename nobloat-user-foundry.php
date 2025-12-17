<?php
/**
 * Plugin Name: NoBloat User Foundry
 * Plugin URI: https://github.com/jcbenton/nobloat-user-foundry
 * Description: Lightweight user management system for WordPress - email verification, account expiration, and user lifecycle management without the bloat.
 * Version: 1.4.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: NoBloat
 * Author URI: https://nobloat.dev
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: nobloat-user-foundry
 * Donate link: https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
$nbuf_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
define( 'NBUF_VERSION', $nbuf_plugin_data['Version'] );
define( 'NBUF_PLUGIN_FILE', __FILE__ );
define( 'NBUF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NBUF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NBUF_TEMPLATES_DIR', NBUF_PLUGIN_DIR . 'templates/' );
define( 'NBUF_INCLUDE_DIR', NBUF_PLUGIN_DIR . 'includes/' );
define( 'NBUF_DB_TABLE', 'nbuf_tokens' );
define( 'NBUF_USER_DATA_TABLE', 'nbuf_user_data' );

/**
 * Register PSR-4 autoloader
 *
 * Automatically loads classes when first used. Converts class names
 * to file names (e.g., NBUF_User_Data → class-nbuf-user-data.php).
 * Only loads classes that are actually used, reducing overhead by
 * 60-80% on most page loads.
 */
spl_autoload_register( 'nbuf_autoload' );

/**
 * PSR-4 compliant autoloader for NBUF classes
 *
 * @param string $class_name Fully qualified class name.
 */
function nbuf_autoload( $class_name ) {
	// Only autoload NBUF_ prefixed classes.
	if ( 0 !== strpos( $class_name, 'NBUF_' ) && 0 !== strpos( $class_name, 'Abstract_NBUF_' ) ) {
		return;
	}

	// Convert class name to file name.
	// NBUF_User_Data → class-nbuf-user-data.php.
	// NBUF_2FA_Login → class-nbuf-2fa-login.php.
	// NBUF_Abstract_Migration_Plugin → class-nbuf-abstract-migration-plugin.php.
	$class_lower = strtolower( $class_name );
	$class_file  = str_replace( '_', '-', $class_lower );

	// Add 'class-' prefix for all classes (WordPress coding standards).
	$file = 'class-' . $class_file . '.php';

	// Check main includes directory first.
	$path = NBUF_INCLUDE_DIR . $file;

	// If not found, check migration subdirectory.
	if ( ! file_exists( $path ) ) {
		$path = NBUF_INCLUDE_DIR . 'migration/' . $file;
	}

	// If not found, check restrictions subdirectory.
	if ( ! file_exists( $path ) ) {
		$path = NBUF_INCLUDE_DIR . 'restrictions/' . $file;
	}

	// Load if exists.
	if ( file_exists( $path ) ) {
		include_once $path;
	}
}

/**
 * Register activation and deactivation hooks
 */
register_activation_hook( __FILE__, array( 'NBUF_Activator', 'run' ) );
register_deactivation_hook(
	__FILE__,
	function () {
		NBUF_Cron::deactivate();
		NBUF_Expiration::deactivate();
	}
);

/**
 * Disable default WordPress new user notification emails
 *
 * Only disables if the NoBloat User Foundry system is enabled.
 */
add_filter(
	'wp_send_new_user_notifications',
	function ( $send ) {
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		return $system_enabled ? false : $send;
	},
	10,
	3
);

/**
 * Ensure minified live CSS files exist.
 *
 * Creates -live.min.css files from default templates if they don't exist.
 * Runs on every page load but checks are cheap (file_exists only).
 * This ensures users upgrading from older versions get minified CSS.
 */
function nbuf_ensure_live_css_files() {
	/* CSS files to create: filename => error token */
	$css_files = array(
		'reset-page'        => 'nbuf_css_write_failed_reset',
		'login-page'        => 'nbuf_css_write_failed_login',
		'registration-page' => 'nbuf_css_write_failed_registration',
		'account-page'      => 'nbuf_css_write_failed_account',
		'2fa-setup'         => 'nbuf_css_write_failed_2fa',
		'profile'           => 'nbuf_css_write_failed_profile',
		'member-directory'  => 'nbuf_css_write_failed_member_directory',
	);

	$frontend_dir = NBUF_PLUGIN_DIR . 'assets/css/frontend/';

	/* Quick check - if all minified files exist, return early */
	$all_exist = true;
	foreach ( $css_files as $filename => $token ) {
		if ( ! file_exists( $frontend_dir . $filename . '-live.min.css' ) ) {
			$all_exist = false;
			break;
		}
	}
	if ( $all_exist ) {
		return;
	}

	/* Use CSS Manager for proper error handling and logging */
	if ( ! class_exists( 'NBUF_CSS_Manager' ) ) {
		return;
	}

	/* Create missing files using CSS Manager */
	foreach ( $css_files as $filename => $token ) {
		$min_path = $frontend_dir . $filename . '-live.min.css';

		/* Skip if already exists */
		if ( file_exists( $min_path ) ) {
			continue;
		}

		/* Load default CSS from template */
		$css = NBUF_CSS_Manager::load_default_css( $filename );
		if ( empty( $css ) ) {
			continue;
		}

		/* Save using CSS Manager (handles errors, logging, minification) */
		NBUF_CSS_Manager::save_css_to_disk( $css, $filename, $token );
	}
}

/**
 * Initialize plugin components
 *
 * Loads all necessary classes and hooks on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		// Preload all autoload settings in ONE query (massive performance boost).
		NBUF_Options::preload_autoload();

		// Ensure minified CSS files exist (for upgrades from older versions).
		nbuf_ensure_live_css_files();

		// Note: load_plugin_textdomain() is not needed for WordPress.org hosted plugins.
		// WordPress automatically loads translations since version 4.6.

		// Initialize admin settings (ALWAYS - needed to configure when disabled).
		if ( is_admin() ) {
			NBUF_Settings::init();
			NBUF_Appearance::init();
			NBUF_Roles_Page::init();
			NBUF_Multi_Role::init();
			NBUF_Username_Changer::init();
			NBUF_User_Profile_Tabs::init();
			NBUF_Admin_Profile_Fields::init();
			NBUF_Audit_Log_Page::init();
			NBUF_Security_Log_Page::init();
			NBUF_Admin_Audit_Log_Page::init();
			NBUF_Version_History_Page::init();
			NBUF_Migration::init();
			NBUF_Admin_User_Search::init();
			NBUF_Diagnostics::init();

			// User Notes - triggers autoloader which calls init_profile_link() at file end.
			class_exists( 'NBUF_User_Notes' );

			// Admin Users - triggers autoloader which calls init() at file end.
			// Adds bulk actions, columns, and profile sections to Users screen.
			class_exists( 'NBUF_Admin_Users' );
		}

		// Initialize WordPress privacy integration (ALWAYS - for GDPR compliance).
		NBUF_Privacy::init();

		// Initialize GDPR data export (ALWAYS - for GDPR compliance).
		NBUF_GDPR_Export::init();

		// Initialize security logging (ALWAYS - for audit trail and compliance).
		NBUF_Security_Log::init();

		// Initialize admin action hooks (ALWAYS - for accountability and compliance).
		NBUF_Admin_Action_Hooks::init();

		// Initialize hooks class (ALWAYS - for wp-login redirects to work even when system disabled).
		// This loads class-nbuf-hooks.php which calls NBUF_Hooks::init() at the bottom.
		class_exists( 'NBUF_Hooks' );

		// Initialize virtual page router (handles all /user-foundry/* URLs).
		// No WordPress pages needed - router intercepts URLs directly.
		NBUF_Universal_Router::init();

		// Initialize shortcodes (ALWAYS - show notice when system disabled instead of nothing).
		if ( ! is_admin() ) {
			NBUF_Shortcodes::init();
		}

		// Check if user management system is enabled.
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );

		// If system is disabled, stop here (allow settings access only).
		if ( ! $system_enabled ) {
			return;
		}

		// Initialize core components (only if system enabled).
		if ( ! is_admin() ) {
			NBUF_Verifier::init();
		}

		NBUF_Cron::register();

		// Add audit logging for general authentication events.
		add_action(
			'wp_login',
			function ( $user_login, $user ) {
				/*
				Only log if not coming from 2FA (2FA logs its own event).
				*/

				/*
				* Check if this is a 2FA login by checking for the 2FA cookie.
				*/
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cookie presence check only, not using value.
				if ( ! isset( $_COOKIE['nbuf_2fa_token'] ) ) {
					NBUF_Audit_Log::log(
						$user->ID,
						'login_success',
						'success',
						'User logged in successfully',
						array( 'username' => $user_login )
					);
				}
			},
			10,
			2
		);

		// Log logout events.
		add_action(
			'wp_logout',
			function ( $user_id ) {
				if ( $user_id ) {
					$user = get_userdata( $user_id );
					NBUF_Audit_Log::log(
						$user_id,
						'logout',
						'success',
						'User logged out',
						array( 'username' => $user ? $user->user_login : 'unknown' )
					);
				}
			}
		);

		// Log user creation by admin.
		add_action(
			'user_register',
			function ( $user_id ) {
				/* Only log if created by admin (not self-registration) */
				if ( is_admin() && current_user_can( 'create_users' ) ) {
					$user         = get_userdata( $user_id );
					$current_user = wp_get_current_user();
					NBUF_Audit_Log::log(
						$user_id,
						'user_created',
						'success',
						'User created by administrator',
						array(
							'username'   => $user->user_login,
							'created_by' => $current_user->user_login,
						)
					);
				}
			},
			100
		);

		// Log user deletion.
		add_action(
			'delete_user',
			function ( $user_id ) {
				$user         = get_userdata( $user_id );
				$current_user = wp_get_current_user();
				NBUF_Audit_Log::log(
					$user_id,
					'user_deleted',
					'success',
					'User deleted',
					array(
						'username'   => $user ? $user->user_login : 'unknown',
						'deleted_by' => $current_user ? $current_user->user_login : 'system',
					)
				);
			}
		);

		/*
		 * GDPR: Clean up user photos when account is deleted
		 * Checks nbuf_gdpr_delete_user_photos setting before deleting
		 */
		add_action( 'delete_user', array( 'NBUF_Image_Processor', 'cleanup_user_photos' ), 10, 1 );

		// Initialize 2FA only if enabled (autoloader handles class loading).
		$email_2fa_enabled = 'disabled' !== NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
		$totp_2fa_enabled  = 'disabled' !== NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );

		if ( $email_2fa_enabled || $totp_2fa_enabled ) {
			NBUF_2FA_Login::init();
		}

		// Initialize Access Restrictions (if enabled).
		// Note: This initializes even if system is disabled, but restrictions
		// themselves check the master toggle before activating.
		NBUF_Restrictions::init();

		// Initialize Privacy Manager (always - for user profile privacy controls).
		NBUF_Privacy_Manager::init();

		// Initialize Member Directory (if enabled).
		$directory_enabled = NBUF_Options::get( 'nbuf_enable_member_directory', false );
		if ( $directory_enabled ) {
			NBUF_Member_Directory::init();
		}

		// Initialize Profile Photos (handles own enable checks for profiles/gravatar).
		NBUF_Profile_Photos::init();

		// Initialize 2FA Account management (for application passwords AJAX handlers).
		// Triggers autoloader which calls init() at file end to register AJAX handlers.
		class_exists( 'NBUF_2FA_Account' );

		// Initialize Public Profiles (if enabled).
		$profiles_enabled        = NBUF_Options::get( 'nbuf_enable_profiles', false );
		$public_profiles_enabled = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
		if ( $profiles_enabled && $public_profiles_enabled ) {
			NBUF_Public_Profiles::init();
		}

		// Initialize Data Management Tools (admin only).
		if ( is_admin() ) {
			new NBUF_Bulk_Import();
			new NBUF_Config_Exporter();
			new NBUF_Config_Importer();
			NBUF_Account_Merger::init();
		}

		// Initialize Change Notifications (if enabled).
		$notify_changes_enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );
		if ( $notify_changes_enabled ) {
			new NBUF_Change_Notifications();
		}

		// Initialize Version History (if enabled).
		$version_history_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( $version_history_enabled ) {
			new NBUF_Version_History();
		}
	},
	5
);

/**
 * Initialize login security features
 *
 * Must be on 'init' hook (not 'login_init') to work with both wp-login.php
 * AND custom login pages like /user-foundry/login/. The authenticate filter
 * must be registered before any wp_signon() call happens.
 *
 * Note: Login limiting works independently of the main user manager toggle
 * since security features should always be available when enabled.
 */
add_action(
	'init',
	function () {
		/* Login limiting - works independently of main system toggle for security */
		$login_limiting_enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( $login_limiting_enabled ) {
			NBUF_Login_Limiting::init();
		}

		/* Password expiration - requires main system to be enabled */
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		if ( $system_enabled ) {
			$password_expiration_enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
			if ( $password_expiration_enabled ) {
				NBUF_Password_Expiration::init();
			}
		}
	},
	5 /* Early priority to ensure hooks are registered before login processing */
);

/**
 * Enqueue front-end styles
 *
 * Loads CSS on plugin pages. CSS loading is independent of
 * system enabled status so pages display correctly during setup.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		/* Check if CSS loading is enabled */
		$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
		$css_combine_files = NBUF_Options::get( 'nbuf_css_combine_files', true );

		/* If CSS loading is disabled, don't load anything */
		if ( ! $css_load_on_pages ) {
			return;
		}

		/* Get page IDs */
		$reset_page_id   = NBUF_Options::get( 'nbuf_page_password_reset' );
		$verify_page_id  = NBUF_Options::get( 'nbuf_page_verification' );
		$login_page_id   = NBUF_Options::get( 'nbuf_page_login' );
		$reg_page_id     = NBUF_Options::get( 'nbuf_page_registration' );
		$account_page_id = NBUF_Options::get( 'nbuf_page_account' );

		/* Check if we're on a plugin page */
		$is_plugin_page = ( $reset_page_id && is_page( $reset_page_id ) ) ||
						( $verify_page_id && is_page( $verify_page_id ) ) ||
						( $login_page_id && is_page( $login_page_id ) ) ||
						( $reg_page_id && is_page( $reg_page_id ) ) ||
						( $account_page_id && is_page( $account_page_id ) );

		/* Load CSS only on plugin pages */
		if ( $is_plugin_page ) {
			if ( $css_combine_files ) {
				/* Load combined CSS file */
				NBUF_CSS_Manager::enqueue_css(
					'nbuf-combined',
					'nobloat-combined',
					'nbuf_reset_page_css', /* fallback option */
					'nbuf_css_write_failed_combined'
				);
			} else {
				/* Load individual CSS files */
				if ( ( $reset_page_id && is_page( $reset_page_id ) ) || ( $verify_page_id && is_page( $verify_page_id ) ) ) {
					NBUF_CSS_Manager::enqueue_css(
						'nbuf-reset',
						'reset-page',
						'nbuf_reset_page_css',
						'nbuf_css_write_failed_reset'
					);
				}

				if ( $login_page_id && is_page( $login_page_id ) ) {
					NBUF_CSS_Manager::enqueue_css(
						'nbuf-login',
						'login-page',
						'nbuf_login_page_css',
						'nbuf_css_write_failed_login'
					);
				}

				if ( $reg_page_id && is_page( $reg_page_id ) ) {
					NBUF_CSS_Manager::enqueue_css(
						'nbuf-registration',
						'registration-page',
						'nbuf_registration_page_css',
						'nbuf_css_write_failed_registration'
					);
				}

				if ( $account_page_id && is_page( $account_page_id ) ) {
					NBUF_CSS_Manager::enqueue_css(
						'nbuf-account',
						'account-page',
						'nbuf_account_page_css',
						'nbuf_css_write_failed_account'
					);
				}
			}
		}
	}
);

/**
 * Display admin notice for missing email templates
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$html_template = NBUF_Options::get( 'nbuf_email_template_html' );
		$text_template = NBUF_Options::get( 'nbuf_email_template_text' );

		/* Warn if email templates are missing */
		if ( empty( trim( $html_template ) ) || empty( trim( $text_template ) ) ) {
			echo wp_kses_post( '<div class="notice notice-warning"><p><strong>NoBloat User Foundry:</strong> Default email templates are missing or empty. Please configure them under <em>NoBloat Foundry → User Settings</em>.</p></div>' );
		}
	}
);

/**
 * Add settings link to plugins page
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users' ) ) . '">' . __( 'Settings', 'nobloat-user-foundry' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/**
 * Hide page title on NoBloat pages
 *
 * Pages created by the plugin have _nbuf_hide_title meta set.
 * This filter returns empty string for the_title when in the loop.
 */
add_filter(
	'the_title',
	function ( $title, $post_id = 0 ) {
		// Only filter in the main loop for singular pages.
		if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}

		// Check if this page has the hide title meta.
		if ( get_post_meta( $post_id, '_nbuf_hide_title', true ) ) {
			return '';
		}

		return $title;
	},
	10,
	2
);

/**
 * Bricks theme compatibility
 *
 * Hides page titles on shortcode pages when using Bricks theme.
 */
add_filter(
	'bricks/default_page_title',
	function ( $title ) {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $title;
		}

		$content = get_post_field( 'post_content', $post_id );

		/* Hide title if page contains plugin shortcodes */
		if ( has_shortcode( $content, 'nbuf_verify_page' )
			|| has_shortcode( $content, 'nbuf_reset_form' )
		) {
			return '';
		}

		return $title;
	}
);
