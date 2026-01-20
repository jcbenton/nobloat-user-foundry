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
 * Safely remove an empty directory with proper error handling.
 *
 * @since 1.5.0
 * @param string $dir Path to directory to remove.
 * @return bool True if removed or didn't exist, false on failure.
 */
function nbuf_safe_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return true; // Nothing to remove.
	}

	// Check if directory is empty.
	$files = scandir( $dir );
	if ( false === $files ) {
		return false;
	}

	// Filter out . and ..
	$files = array_diff( $files, array( '.', '..' ) );

	if ( ! empty( $files ) ) {
		// Directory not empty - can't remove, but this isn't an error.
		return true;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing plugin cache/upload directories during uninstall.
	$result = rmdir( $dir );

	if ( ! $result ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Uninstall error logging for troubleshooting.
		error_log( '[NoBloat User Foundry] Failed to remove directory during uninstall: ' . $dir );
	}

	return $result;
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
			'nbuf_user_passkeys',
			'nbuf_user_audit_log',
			'nbuf_admin_audit_log',
			'nbuf_user_notes',
			'nbuf_import_history',
			'nbuf_menu_restrictions',
			'nbuf_content_restrictions',
			'nbuf_user_roles',
			'nbuf_profile_versions',
			'nbuf_security_log',
			'nbuf_webhooks',
			'nbuf_webhook_log',
			'nbuf_tos_versions',
			'nbuf_tos_acceptances',
		);

		foreach ( $nbuf_tables_to_drop as $nbuf_table ) {
			$nbuf_table_name = $wpdb->prefix . $nbuf_table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $nbuf_table_name ) );
		}
	}

	/**
	 * Delete cache directory (always)
	 *
	 * Removes the /uploads/nobloat/cache/ directory which contains
	 * minified JavaScript files. Safe to delete as these are regenerated.
	 */
	$nbuf_upload_dir = wp_upload_dir();
	$nbuf_cache_dir  = trailingslashit( $nbuf_upload_dir['basedir'] ) . 'nobloat/cache/';

	if ( file_exists( $nbuf_cache_dir ) ) {
		$nbuf_cache_files = glob( $nbuf_cache_dir . '*' );

		if ( ! empty( $nbuf_cache_files ) && is_array( $nbuf_cache_files ) ) {
			foreach ( $nbuf_cache_files as $nbuf_cache_file ) {
				if ( is_file( $nbuf_cache_file ) ) {
					wp_delete_file( $nbuf_cache_file );
				}
			}
		}

		nbuf_safe_rmdir( $nbuf_cache_dir );
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

						/* Delete user directory if empty */
						nbuf_safe_rmdir( $nbuf_user_dir );
					}
				}

				/* Delete main nobloat directory if empty */
				nbuf_safe_rmdir( $nbuf_dir_real );
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
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s',
				$wpdb->options,
				'nbuf_%'
			)
		);

		/*
		 * Delete all plugin transients (rate limiting, 2FA codes, etc.)
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				'_transient_nbuf_%',
				'_transient_timeout_nbuf_%',
				'_transient_photo_upload_%',
				'_transient_timeout_photo_upload_%'
			)
		);
	}

	/**
	 * Remove scheduled cron jobs (if registered)
	 *
	 * Complete list of all plugin cron hooks - must be kept in sync
	 * with NBUF_Cron::get_cron_definitions().
	 */
	$nbuf_cron_hooks = array(
		/* Core Maintenance */
		'nbuf_cleanup_cron',
		'nbuf_cleanup_transients',
		'nbuf_cleanup_exports',
		/* User Management */
		'nbuf_check_expirations',
		'nbuf_send_expiration_warnings',
		'nbuf_cleanup_unverified_accounts',
		/* Security */
		'nbuf_cleanup_login_attempts',
		/* Logging & History */
		'nbuf_audit_log_cleanup_cron',
		'nbuf_cleanup_version_history',
		'nbuf_daily_security_log_prune',
		'nbuf_enterprise_logging_cleanup',
		/* Notifications */
		'nbuf_send_change_digest_hourly',
		'nbuf_send_change_digest_daily',
	);
	foreach ( $nbuf_cron_hooks as $nbuf_hook ) {
		wp_clear_scheduled_hook( $nbuf_hook );
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
