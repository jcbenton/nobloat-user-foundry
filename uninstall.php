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

/**
 * Execute uninstall cleanup.
 *
 * Wrapped in a function to avoid global variable naming conflicts.
 *
 * @since 1.0.0
 * @return void
 */
function nbuf_run_uninstall() {
	global $wpdb;

	/**
	 * Retrieve uninstall preferences
	 *
	 * Read the cleanup settings from custom options table.
	 */
	$nbuf_options_table = $wpdb->prefix . 'nbuf_options';

	// Check if options table exists before querying.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$nbuf_table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$nbuf_options_table
		)
	);

	$nbuf_settings = array();
	$nbuf_cleanup  = array();

	if ( $nbuf_table_exists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$nbuf_settings_value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s',
				$nbuf_options_table,
				'nbuf_settings'
			)
		);

		$nbuf_settings = $nbuf_settings_value ? maybe_unserialize( $nbuf_settings_value ) : array();
		$nbuf_cleanup  = isset( $nbuf_settings['cleanup'] ) ? (array) $nbuf_settings['cleanup'] : array();
	}

	/**
	 * Drop custom options table (if settings or templates cleanup requested)
	 *
	 * The nbuf_options table stores all plugin settings, templates,
	 * and CSS. Dropping this table removes all plugin options at once.
	 */
	if ( in_array( 'settings', $nbuf_cleanup, true ) || in_array( 'templates', $nbuf_cleanup, true ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $nbuf_options_table ) );
	}

	/**
	 * Drop all custom database tables (if requested)
	 *
	 * Supports both legacy 'tokens' key and new 'tables' key for backwards compatibility.
	 */
	if ( in_array( 'tables', $nbuf_cleanup, true ) || in_array( 'tokens', $nbuf_cleanup, true ) ) {
		/* All plugin tables to drop */
		$nbuf_tables_to_drop = array(
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

		foreach ( $nbuf_tables_to_drop as $nbuf_table ) {
			$nbuf_table_name = $wpdb->prefix . $nbuf_table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $nbuf_table_name ) );
		}
	}

	/**
	 * Delete pages containing NoBloat shortcodes (if requested)
	 *
	 * Searches for all pages that contain NoBloat shortcodes and deletes them.
	 * This includes all auto-created pages like login, registration, account, etc.
	 * Disabled by default for safety.
	 */
	if ( in_array( 'pages', $nbuf_cleanup, true ) ) {
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
			'nbuf_totp_setup',
			'nbuf_members',
			'nbuf_profile',
			'nbuf_restrict',
		);

		/* Get all published pages */
		$nbuf_page_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $nbuf_page_ids ) && is_array( $nbuf_page_ids ) ) {
			foreach ( $nbuf_page_ids as $nbuf_page_id ) {
				$nbuf_content = get_post_field( 'post_content', $nbuf_page_id );

				if ( empty( $nbuf_content ) ) {
					continue;
				}

				/* Check if page contains any NoBloat shortcode */
				$nbuf_contains_shortcode = false;
				foreach ( $nbuf_shortcodes as $nbuf_shortcode ) {
					if ( false !== strpos( $nbuf_content, '[' . $nbuf_shortcode ) ) {
						$nbuf_contains_shortcode = true;
						break;
					}
				}

				/* Delete page if it contains a NoBloat shortcode */
				if ( $nbuf_contains_shortcode ) {
					wp_delete_post( $nbuf_page_id, true ); // true = force delete (bypass trash).
				}
			}
		}
	}

	/**
	 * Delete uploads directory (if requested)
	 *
	 * Permanently deletes the entire /uploads/nobloat/ directory including
	 * all user profile photos, cover photos, and uploaded files.
	 * This action cannot be undone. Disabled by default for safety.
	 */
	if ( in_array( 'uploads', $nbuf_cleanup, true ) ) {
		$nbuf_upload_dir = wp_upload_dir();
		$nbuf_dir        = trailingslashit( $nbuf_upload_dir['basedir'] ) . 'nobloat/';

		if ( file_exists( $nbuf_dir ) ) {
			/*
			 * Recursively delete all user photo directories
			 * Security: Use realpath() to ensure we're deleting within uploads directory
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
			$nbuf_dir_real = realpath( $nbuf_dir );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath -- Required for security validation.
			$nbuf_upload_base = realpath( $nbuf_upload_dir['basedir'] );

			/* Verify nobloat directory is within uploads directory */
			if ( $nbuf_dir_real && $nbuf_upload_base && 0 === strpos( $nbuf_dir_real, $nbuf_upload_base ) ) {
				/* Get all user directories */
				$nbuf_user_dirs = glob( $nbuf_dir_real . '/*', GLOB_ONLYDIR );

				if ( ! empty( $nbuf_user_dirs ) && is_array( $nbuf_user_dirs ) ) {
					foreach ( $nbuf_user_dirs as $nbuf_user_dir ) {
						/* Delete all files in user directory */
						$nbuf_files = glob( $nbuf_user_dir . '/*' );
						if ( ! empty( $nbuf_files ) && is_array( $nbuf_files ) ) {
							foreach ( $nbuf_files as $nbuf_file ) {
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Required for file deletion validation.
								if ( is_file( $nbuf_file ) && is_writable( $nbuf_file ) ) {
									wp_delete_file( $nbuf_file );
								}
							}
						}

						/*
						 * Delete user directory (may be non-empty, @ acceptable).
						 * rmdir() only deletes empty directories.
						 */
						if ( is_dir( $nbuf_user_dir ) && is_readable( $nbuf_user_dir ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
							@rmdir( $nbuf_user_dir );
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
	if ( in_array( 'settings', $nbuf_cleanup, true ) ) {
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
	$nbuf_cron_hooks = array(
		'nbuf_cleanup_cron',
		'nbuf_audit_log_cleanup_cron',
		'nbuf_cleanup_version_history',
		'nbuf_cleanup_transients',
		'nbuf_cleanup_unverified_accounts',
		'nbuf_enterprise_logging_cleanup',
		'nbuf_check_expirations',
		'nbuf_send_expiration_warnings',
	);
	foreach ( $nbuf_cron_hooks as $nbuf_hook ) {
		if ( wp_next_scheduled( $nbuf_hook ) ) {
			wp_clear_scheduled_hook( $nbuf_hook );
		}
	}
}

// Run the uninstall function.
nbuf_run_uninstall();

/*
 * NOTE: All plugin options (settings, templates, CSS, migration flags, etc.)
 * are stored in the nbuf_options custom table and are removed when that table
 * is dropped above. Transients in wp_options are also cleaned when "settings"
 * cleanup is selected.
 */
