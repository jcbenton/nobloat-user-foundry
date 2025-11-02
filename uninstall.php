<?php
/**
 * NoBloat User Foundry - Uninstall Script
 *
 * Executed when the plugin is deleted via the WordPress admin.
 * Removes settings, templates, tokens table, user data, and
 * scheduled cron jobs.
 *
 * @package    NoBloat_User_Foundry
 * @since      1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Prevent direct access.
}

// Load WordPress DB.
global $wpdb;

/* ==========================================================
   1) Retrieve uninstall preferences
   ----------------------------------------------------------
   Read the cleanup settings from custom options table.
   ========================================================== */
$options_table = $wpdb->prefix . 'nbuf_options';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$settings_value = $wpdb->get_var( $wpdb->prepare(
	"SELECT option_value FROM {$options_table} WHERE option_name = %s",
	'nbuf_settings'
) );

$settings = $settings_value ? maybe_unserialize( $settings_value ) : array();
$cleanup  = isset( $settings['cleanup'] ) ? (array) $settings['cleanup'] : array( 'settings', 'templates', 'tokens' );

/* ==========================================================
   2) Drop custom options table (if settings or templates cleanup requested)
   ----------------------------------------------------------
   The nbuf_options table stores all plugin settings, templates,
   and CSS. Dropping this table removes all plugin options at once.
   ========================================================== */
if ( in_array( 'settings', $cleanup, true ) || in_array( 'templates', $cleanup, true ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $options_table ) );
}

/* ==========================================================
   4) Drop custom database tables (if requested)
   ========================================================== */
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
}

/* ==========================================================
   5) Remove verification usermeta fields (if requested)
   ----------------------------------------------------------
   Deletes any orphaned usermeta from older versions.
   User data is now stored in custom table.
   ========================================================== */
if ( in_array( 'usermeta', $cleanup, true ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->usermeta}
		WHERE meta_key IN ('nbuf_verified', 'nbuf_verified_date', 'nbuf_user_disabled')"
	);
}

/* ==========================================================
   6) Remove scheduled cron jobs (if registered)
   ========================================================== */
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

/* ==========================================================
   7) Remove migration flag from wp_options
   ----------------------------------------------------------
   The nbuf_options_migrated flag is kept in wp_options as
   a marker. Remove it during uninstall.
   ========================================================== */
delete_option( 'nbuf_options_migrated' );

/* NOTE: All other plugin options (settings, templates, CSS, etc.)
   are stored in the nbuf_options custom table and are removed
   when that table is dropped above. */

/* ==========================================================
   10) Optional debug log
   ========================================================== */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '[NoBloat User Foundry] Uninstalled. Cleanup flags: ' . implode( ', ', $cleanup ) );
}