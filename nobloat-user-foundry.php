<?php
/**
 * Plugin Name: NoBloat User Foundry
 * Plugin URI: https://github.com/jcbenton/nobloat-user-foundry
 * Description: Business focused user management with email verification, 2FA, passkeys, role management, GDPR, auditing, and lifecycle control.
 * Version: 1.5.5
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Jerry Benton
 * Author URI: https://www.mailborder.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: nobloat-user-foundry
 * Donate link: https://donate.stripe.com/3cIfZi81NbxX9CX4uybfO01
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
 * @return void
 */
function nbuf_autoload( string $class_name ): void {
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
		NBUF_Change_Notifications::unschedule_digests();
		NBUF_Asset_Minifier::clear_cache();
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
	1
);

/**
 * Check and run database upgrades if needed.
 *
 * Compares stored DB version with current and runs table creation
 * for any new tables added in updates. Uses dbDelta which is safe
 * to run on existing tables (only makes changes if needed).
 *
 * @return void
 */
function nbuf_maybe_upgrade_database(): void {
	$current_db_version = '1.5.2'; /* Update this when adding new tables - bumped for ToS */
	$stored_db_version  = get_option( 'nbuf_db_version', '0' );

	if ( version_compare( $stored_db_version, $current_db_version, '<' ) ) {
		if ( class_exists( 'NBUF_Database' ) ) {
			/* Run all table creation - dbDelta only modifies if needed */
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

			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::create_table();
			}

			/* Create webhooks tables */
			NBUF_Database::create_webhooks_table();
			NBUF_Database::create_webhook_log_table();

			/* Create Terms of Service tables */
			NBUF_Database::create_tos_versions_table();
			NBUF_Database::create_tos_acceptances_table();
		}

		/* Update stored version */
		update_option( 'nbuf_db_version', $current_db_version );
	}
}

/**
 * Ensure minified live CSS files exist.
 *
 * Creates -live.min.css files from default templates if they don't exist.
 * Runs on every page load but checks are cheap (file_exists only).
 * This ensures users upgrading from older versions get minified CSS.
 *
 * @return void
 */
function nbuf_ensure_live_css_files(): void {
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

		// Check for database upgrades (creates new tables on plugin update).
		nbuf_maybe_upgrade_database();

		// Ensure minified CSS files exist (for upgrades from older versions).
		nbuf_ensure_live_css_files();

		// Note: load_plugin_textdomain() is not needed for WordPress.org hosted plugins.
		// WordPress automatically loads translations since version 4.6.

		// Initialize admin settings (ALWAYS - needed to configure when disabled).
		if ( is_admin() ) {
			NBUF_Settings::init();
			NBUF_Appearance::init();
			NBUF_Background_Activation::init();
			NBUF_Roles_Page::init();
			NBUF_Multi_Role::init();
			NBUF_Username_Changer::init();
			NBUF_User_Profile_Tabs::init();
			NBUF_Admin_Profile_Fields::init();
			NBUF_Audit_Log_Page::init();
			NBUF_Security_Log_Page::init();
			NBUF_Admin_Audit_Log_Page::init();
			NBUF_Version_History_Page::init();
			NBUF_ToS_Admin::init();
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

		// Run database migrations (checks internally if already done).
		if ( class_exists( 'NBUF_Database' ) ) {
			NBUF_Database::migrate_tokens_type_column();
		}

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

		// Initialize Magic Links (if enabled).
		$magic_links_enabled = NBUF_Options::get( 'nbuf_magic_links_enabled', false );
		if ( $magic_links_enabled ) {
			NBUF_Magic_Links::init();
		}

		// Initialize User Impersonation (if enabled).
		$impersonation_enabled = NBUF_Options::get( 'nbuf_impersonation_enabled', false );
		if ( $impersonation_enabled ) {
			NBUF_Impersonation::init();
		}

		// Initialize Activity Dashboard (if enabled).
		$activity_dashboard_enabled = NBUF_Options::get( 'nbuf_activity_dashboard_enabled', true );
		if ( $activity_dashboard_enabled ) {
			NBUF_Activity_Dashboard::init();
		}

		// Initialize Terms of Service tracking (if enabled).
		$tos_enabled = NBUF_Options::get( 'nbuf_tos_enabled', false );
		if ( $tos_enabled ) {
			NBUF_ToS::init();
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

				/* Track last login timestamp (always, regardless of 2FA) */
				NBUF_User_Data::update_last_login( $user->ID );
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
				if ( is_admin() && current_user_can( 'create_users' ) && class_exists( 'NBUF_Admin_Audit_Log' ) ) {
					$user = get_userdata( $user_id );
					NBUF_Admin_Audit_Log::log(
						get_current_user_id(),
						NBUF_Admin_Audit_Log::EVENT_USER_CREATED,
						'success',
						__( 'User created by administrator', 'nobloat-user-foundry' ),
						$user_id,
						array( 'username' => $user->user_login )
					);
				}
			},
			100
		);

		// Log user deletion.
		add_action(
			'delete_user',
			function ( $user_id ) {
				if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
					$user = get_userdata( $user_id );
					NBUF_Admin_Audit_Log::log(
						get_current_user_id(),
						NBUF_Admin_Audit_Log::EVENT_USER_DELETED,
						'success',
						__( 'User deleted', 'nobloat-user-foundry' ),
						$user_id,
						array( 'username' => $user ? $user->user_login : 'unknown' )
					);
				}
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

		// Initialize Passkeys (if enabled).
		$passkeys_enabled = NBUF_Options::get( 'nbuf_passkeys_enabled', false );
		if ( $passkeys_enabled ) {
			NBUF_Passkeys::init();
			NBUF_Passkeys_Login::init();

			// Initialize passkey enrollment prompt (if enabled).
			// Prompts users without passkeys to set one up after login.
			// Must init on all requests to catch wp_login hook; modal only renders on frontend.
			NBUF_Passkey_Prompt::init();
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

		// Initialize Sessions management (for AJAX handlers).
		// Triggers autoloader which calls init() at file end to register AJAX handlers.
		$sessions_enabled = NBUF_Options::get( 'nbuf_session_management_enabled', true );
		if ( $sessions_enabled ) {
			class_exists( 'NBUF_Sessions' );
		}

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

		// Register Change Notifications cron handlers (ALWAYS - to send pending digests).
		// Must be registered even when disabled so scheduled events can fire.
		NBUF_Change_Notifications::register_cron_handlers();

		// Initialize Change Notifications tracking (if enabled).
		$notify_changes_enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );
		if ( $notify_changes_enabled ) {
			new NBUF_Change_Notifications();
		}

		// Initialize Version History (if enabled).
		$version_history_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( $version_history_enabled ) {
			new NBUF_Version_History();
		}

		// Initialize Webhooks (if enabled).
		$webhooks_enabled = NBUF_Options::get( 'nbuf_webhooks_enabled', false );
		if ( $webhooks_enabled ) {
			NBUF_Webhooks::init();
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

		/* IP restrictions - works independently of main system toggle for security */
		$ip_restriction_enabled = NBUF_Options::get( 'nbuf_ip_restriction_enabled', false );
		if ( $ip_restriction_enabled ) {
			NBUF_IP_Restrictions::init();
		}

		/* Anti-bot registration protection - works independently of main system toggle */
		$antibot_enabled = NBUF_Options::get( 'nbuf_antibot_enabled', true );
		if ( $antibot_enabled ) {
			NBUF_Antibot::init();
		}

		/* Password expiration - requires main system to be enabled */
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
		if ( $system_enabled ) {
			$password_expiration_enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
			if ( $password_expiration_enabled ) {
				NBUF_Password_Expiration::init();
			}

			/* Weak password migration - validates passwords at login */
			NBUF_Password_Validator::init();
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

/*
 * Email template notice removed - system falls back to disk files when DB is empty.
 * No warning needed since default templates are always available in /templates/.
 */

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
