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

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$settings_value = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT option_value FROM %i WHERE option_name = %s',
		$options_table,
		'nbuf_settings'
	)
);

$settings = $settings_value ? maybe_unserialize( $settings_value ) : array();
$cleanup  = isset( $settings['cleanup'] ) ? (array) $settings['cleanup'] : array( 'settings', 'templates', 'tokens' );

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
 * Drop custom database tables (if requested)
 */
if ( in_array( 'tokens', $cleanup, true ) ) {
	$tokens_table = $wpdb->prefix . 'nbuf_tokens';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $tokens_table ) );

	$user_data_table = $wpdb->prefix . 'nbuf_user_data';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $user_data_table ) );

	$user_profile_table = $wpdb->prefix . 'nbuf_user_profile';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $user_profile_table ) );

	$login_attempts_table = $wpdb->prefix . 'nbuf_login_attempts';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $login_attempts_table ) );

	$menu_restrictions_table = $wpdb->prefix . 'nbuf_menu_restrictions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $menu_restrictions_table ) );

	$content_restrictions_table = $wpdb->prefix . 'nbuf_content_restrictions';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $content_restrictions_table ) );

	$security_log_table = $wpdb->prefix . 'nbuf_security_log';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $security_log_table ) );
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
 * GDPR: Delete all user photos (if requested)
 *
 * Checks nbuf_gdpr_delete_on_uninstall setting. If enabled, permanently
 * deletes the entire /uploads/nobloat/ directory and all user photos.
 * This action cannot be undone. Disabled by default for safety.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$delete_photos_setting = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT option_value FROM %i WHERE option_name = %s',
		$options_table,
		'nbuf_gdpr_delete_on_uninstall'
	)
);

$delete_photos = $delete_photos_setting ? (bool) maybe_unserialize( $delete_photos_setting ) : false;

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
 * Remove scheduled cron jobs (if registered)
 */
$cron_hooks = array(
	'nbuf_cleanup_cron',
	'nbuf_check_expirations',
	'nbuf_send_expiration_warnings',
);
foreach ( $cron_hooks as $hook ) {
	if ( wp_next_scheduled( $hook ) ) {
		wp_clear_scheduled_hook( $hook );
	}
}

/**
 * Remove migration flag from wp_options
 *
 * The nbuf_options_migrated flag is kept in wp_options as
 * a marker. Remove it during uninstall.
 */
delete_option( 'nbuf_options_migrated' );

/*
 * NOTE: All other plugin options (settings, templates, CSS, etc.)
 * are stored in the nbuf_options custom table and are removed
 * when that table is dropped above.
 */
