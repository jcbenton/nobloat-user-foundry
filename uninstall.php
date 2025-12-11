<?php
/**
 * NoBloat User Foundry - Uninstall Script
 *
 * Executed when the plugin is deleted via the WordPress admin.
 * Removes settings, templates, tokens table, user data, and
 * scheduled cron jobs.
 *
 * @package NoBloat_User_Foundry
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Prevent direct access.
}

// Load WordPress DB.
global $wpdb;

/**
 * Retrieve uninstall preferences
 *
 * Read the cleanup settings from custom options table.
 */
$options_table = $wpdb->prefix . 'nbuf_options';

// Check if options table exists before querying.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$table_exists = $wpdb->get_var(
	$wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$options_table
	)
);

$settings = array();
$cleanup  = array();

if ( $table_exists ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$settings_value = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT option_value FROM %i WHERE option_name = %s',
			$options_table,
			'nbuf_settings'
		)
	);

	$settings = $settings_value ? maybe_unserialize( $settings_value ) : array();
	$cleanup  = isset( $settings['cleanup'] ) ? (array) $settings['cleanup'] : array();
}

/**
 * Drop custom options table (if settings or templates cleanup requested)
 *
 * The nbuf_options table stores all plugin settings, templates,
 * and CSS. Dropping this table removes all plugin options at once.
 */
if ( in_array( 'settings', $cleanup, true ) || in_array( 'templates', $cleanup, true ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $options_table ) );
}

/**
 * Drop all custom database tables (if requested)
 *
 * Supports both legacy 'tokens' key and new 'tables' key for backwards compatibility.
 */
if ( in_array( 'tables', $cleanup, true ) || in_array( 'tokens', $cleanup, true ) ) {
	/* All plugin tables to drop */
	$tables_to_drop = array(
		'nbuf_tokens',
		'nbuf_user_data',
		'nbuf_user_profile',
		'nbuf_login_attempts',
		'nbuf_user_2fa',
		'nbuf_user_audit_log',
		'nbuf_admin_audit_log',
		'nbuf_user_notes',
		'nbuf_import_history',
		'nbuf_menu_restrictions',
		'nbuf_content_restrictions',
		'nbuf_user_roles',
		'nbuf_profile_versions',
		'nbuf_security_log',
	);

	foreach ( $tables_to_drop as $table ) {
		$table_name = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}
}

/**
 * Remove verification usermeta fields (if requested)
 *
 * Deletes any orphaned usermeta from older versions.
 * User data is now stored in custom table.
 */
if ( in_array( 'usermeta', $cleanup, true ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM %i
			WHERE meta_key IN ('nbuf_verified', 'nbuf_verified_date', 'nbuf_user_disabled')",
			$wpdb->usermeta
		)
	);
}

/**
 * Delete pages containing NoBloat shortcodes (if requested)
 *
 * Searches for all pages that contain NoBloat shortcodes and deletes them.
 * This includes all auto-created pages like login, registration, account, etc.
 * Disabled by default for safety.
 */
if ( in_array( 'pages', $cleanup, true ) ) {
	/* List of all NoBloat shortcodes to search for */
	$nbuf_shortcodes = array(
		'nbuf_verify_page',
		'nbuf_reset_form',
		'nbuf_request_reset_form',
		'nbuf_login_form',
		'nbuf_registration_form',
		'nbuf_account_page',
		'nbuf_logout',
		'nbuf_2fa_verify',
		'nbuf_2fa_setup',
		'nbuf_members',
		'nbuf_profile',
		'nbuf_restrict',
	);

	/* Get all published pages */
	$page_ids = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $page_ids ) && is_array( $page_ids ) ) {
		foreach ( $page_ids as $page_id ) {
			$content = get_post_field( 'post_content', $page_id );

			if ( empty( $content ) ) {
				continue;
			}

			/* Check if page contains any NoBloat shortcode */
			$contains_nbuf_shortcode = false;
			foreach ( $nbuf_shortcodes as $shortcode ) {
				if ( false !== strpos( $content, '[' . $shortcode ) ) {
					$contains_nbuf_shortcode = true;
					break;
				}
			}

			/* Delete page if it contains a NoBloat shortcode */
			if ( $contains_nbuf_shortcode ) {
				wp_delete_post( $page_id, true ); // true = force delete (bypass trash).
			}
		}
	}
}

/**
 * GDPR: Delete all user photos (if requested)
 *
 * Checks nbuf_gdpr_delete_on_uninstall setting. If enabled, permanently
 * deletes the entire /uploads/nobloat/ directory (including /nobloat/users/) and all user photos.
 * This action cannot be undone. Disabled by default for safety.
 */
$delete_photos = false;

if ( $table_exists ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$delete_photos_setting = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT option_value FROM %i WHERE option_name = %s',
			$options_table,
			'nbuf_gdpr_delete_on_uninstall'
		)
	);

	$delete_photos = $delete_photos_setting ? (bool) maybe_unserialize( $delete_photos_setting ) : false;
}

if ( $delete_photos ) {
	$upload_dir = wp_upload_dir();
	$nbuf_dir   = trailingslashit( $upload_dir['basedir'] ) . 'nobloat/';

	if ( file_exists( $nbuf_dir ) ) {
		/*
		 * Recursively delete all user photo directories
		 * Security: Use realpath() to ensure we're deleting within uploads directory
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$nbuf_dir_real = realpath( $nbuf_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
		$upload_base = realpath( $upload_dir['basedir'] );

		/* Verify nobloat directory is within uploads directory */
		if ( $nbuf_dir_real && $upload_base && 0 === strpos( $nbuf_dir_real, $upload_base ) ) {
			/* Get all user directories */
			$user_dirs = glob( $nbuf_dir_real . '/*', GLOB_ONLYDIR );

			if ( ! empty( $user_dirs ) && is_array( $user_dirs ) ) {
				foreach ( $user_dirs as $user_dir ) {
					/*
					 * SECURITY: Delete files with proper error handling (no @ suppression).
					 * GDPR compliance: Delete all user photo data.
					 */
					$files = glob( $user_dir . '/*' );
					if ( ! empty( $files ) && is_array( $files ) ) {
						foreach ( $files as $file ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Required for GDPR file deletion validation.
							if ( is_file( $file ) && is_writable( $file ) ) {
								// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- GDPR data deletion.
								unlink( $file );
							}
						}
					}

					/*
					 * Delete user directory (may be non-empty, @ acceptable).
					 * rmdir() only deletes empty directories.
					 */
					if ( is_dir( $user_dir ) && is_readable( $user_dir ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
						@rmdir( $user_dir );
					}
				}
			}

			/*
			 * Delete main nobloat directory (may be non-empty, @ acceptable).
			 */
			if ( is_dir( $nbuf_dir_real ) && is_readable( $nbuf_dir_real ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
				@rmdir( $nbuf_dir_real );
			}
		}
	}
}

/**
 * Delete plugin data from wp_options (if settings cleanup requested)
 *
 * WordPress Settings API stores registered settings in wp_options.
 * Also cleans up transients used for rate limiting, 2FA codes, etc.
 */
if ( in_array( 'settings', $cleanup, true ) ) {
	/*
	 * Delete all nbuf_* options stored by WordPress Settings API
	 * These are created when register_setting() options are saved via options.php
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE 'nbuf_%'"
	);

	/*
	 * Delete all plugin transients (rate limiting, 2FA codes, etc.)
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_nbuf_%'
		 OR option_name LIKE '_transient_timeout_nbuf_%'
		 OR option_name LIKE '_transient_photo_upload_%'
		 OR option_name LIKE '_transient_timeout_photo_upload_%'"
	);
}

/**
 * Remove scheduled cron jobs (if registered)
 */
$cron_hooks = array(
	'nbuf_cleanup_cron',
	'nbuf_audit_log_cleanup_cron',
	'nbuf_cleanup_version_history',
	'nbuf_cleanup_transients',
	'nbuf_cleanup_unverified_accounts',
	'nbuf_enterprise_logging_cleanup',
	'nbuf_check_expirations',
	'nbuf_send_expiration_warnings',
);
foreach ( $cron_hooks as $hook ) {
	if ( wp_next_scheduled( $hook ) ) {
		wp_clear_scheduled_hook( $hook );
	}
}

/*
 * NOTE: All plugin options (settings, templates, CSS, migration flags, etc.)
 * are stored in the nbuf_options custom table and are removed when that table
 * is dropped above. Transients in wp_options are also cleaned when "settings"
 * cleanup is selected.
 */
