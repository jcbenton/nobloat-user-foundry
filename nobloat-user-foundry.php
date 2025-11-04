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
 * Domain Path: /languages
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
$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ), false );
define( 'NBUF_VERSION', $plugin_data['Version'] );
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
	// Abstract_NBUF_Migration_Plugin → class-abstract-nbuf-migration-plugin.php.
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
 * Initialize plugin components
 *
 * Loads all necessary classes and hooks on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		// Preload all autoload settings in ONE query (massive performance boost).
		NBUF_Options::preload_autoload();

		// Load text domain for translations.
		load_plugin_textdomain( 'nobloat-user-foundry', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Initialize admin settings (ALWAYS - needed to configure when disabled).
		if ( is_admin() ) {
			NBUF_Settings::init();
			NBUF_Audit_Log_Page::init();
			NBUF_Security_Log_Page::init();
			NBUF_Version_History_Page::init();
			NBUF_Migration::init();
			NBUF_Admin_User_Search::init();
		}

		// Initialize WordPress privacy integration (ALWAYS - for GDPR compliance).
		NBUF_Privacy::init();

		// Initialize security logging (ALWAYS - for audit trail and compliance).
		NBUF_Security_Log::init();

		// Check if user management system is enabled.
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );

		// If system is disabled, stop here (allow settings access only).
		if ( ! $system_enabled ) {
			return;
		}

		// Initialize core components (only if system enabled).
		if ( ! is_admin() ) {
			NBUF_Verifier::init();
			NBUF_Shortcodes::init();
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

		// Initialize Profile Photos (if enabled).
		$profiles_enabled = NBUF_Options::get( 'nbuf_enable_profiles', false );
		if ( $profiles_enabled ) {
			NBUF_Profile_Photos::init();

			// Initialize Public Profiles (if enabled).
			$public_profiles_enabled = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
			if ( $public_profiles_enabled ) {
				NBUF_Public_Profiles::init();
			}
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
 * Initialize login limiting and password expiration
 *
 * Lazy initialization - only loads on login pages to avoid
 * unnecessary hooks. Autoloader loads classes on-demand.
 */
add_action(
	'login_init',
	function () {
		// Check if user management system is enabled.
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		if ( ! $system_enabled ) {
			return;
		}

		$enabled = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
		if ( $enabled ) {
			NBUF_Login_Limiting::init();
		}

		/* Initialize password expiration system (if enabled) */
		$password_expiration_enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
		if ( $password_expiration_enabled ) {
			NBUF_Password_Expiration::init();
		}
	}
);

/**
 * Enqueue front-end styles
 *
 * Loads CSS only on specific plugin pages when enabled.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		/* Check if user management system is enabled */
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		if ( ! $system_enabled ) {
			return;
		}

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
