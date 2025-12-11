<?php
/**
 * NoBloat User Foundry - Diagnostics Export
 *
 * Handles diagnostic report export functionality.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/*
 * Direct database access is architectural for diagnostics reporting.
 * Diagnostics need to query custom tables and system information directly
 * for accurate reporting. Caching is not applicable for diagnostic data.
 */

/**
 * Diagnostics report generator
 *
 * @since 1.0.0
 */
class NBUF_Diagnostics {


	/**
	 * Initialize diagnostics export.
	 */
	public static function init() {
		add_action( 'admin_post_nbuf_export_diagnostics', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Handle diagnostic report export.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_export_diagnostics' );

		global $wpdb;

		/* Gather all diagnostic data */
		$report   = array();
		$report[] = '===========================================';
		$report[] = 'NOBLOAT USER FOUNDRY - DIAGNOSTIC REPORT';
		$report[] = '===========================================';
		$report[] = 'Generated: ' . current_time( 'mysql' );
		$report[] = 'Site: ' . get_bloginfo( 'name' ) . ' (' . home_url() . ')';
		$report[] = '';

		/* Plugin Information */
		$report[] = '-------------------------------------------';
		$report[] = 'PLUGIN INFORMATION';
		$report[] = '-------------------------------------------';
		$report[] = 'Plugin Version: ' . ( defined( 'NBUF_VERSION' ) ? NBUF_VERSION : 'Unknown' );
		$report[] = '';

		/* System Information */
		$report[] = '-------------------------------------------';
		$report[] = 'SYSTEM INFORMATION';
		$report[] = '-------------------------------------------';
		$report[] = 'WordPress Version: ' . get_bloginfo( 'version' );
		$report[] = 'PHP Version: ' . PHP_VERSION;
		$report[] = 'Database Version: ' . $wpdb->get_var( 'SELECT VERSION()' );
		$report[] = 'Server Software: ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' );
		$report[] = 'PHP Memory Limit: ' . ini_get( 'memory_limit' );
		$report[] = 'WP Memory Limit: ' . WP_MEMORY_LIMIT;
		$report[] = 'WP Max Memory Limit: ' . WP_MAX_MEMORY_LIMIT;
		$report[] = 'PHP Max Execution Time: ' . ini_get( 'max_execution_time' ) . ' seconds';
		$report[] = '';

		/* Database Tables */
		$report[] = '-------------------------------------------';
		$report[] = 'DATABASE HEALTH';
		$report[] = '-------------------------------------------';

		$tables = array(
			'tokens'         => $wpdb->prefix . 'nbuf_tokens',
			'user_data'      => $wpdb->prefix . 'nbuf_user_data',
			'user_2fa'       => $wpdb->prefix . 'nbuf_user_2fa',
			'user_profile'   => $wpdb->prefix . 'nbuf_user_profile',
			'login_attempts' => $wpdb->prefix . 'nbuf_login_attempts',
			'options'        => $wpdb->prefix . 'nbuf_options',
			'audit_log'      => $wpdb->prefix . 'nbuf_user_audit_log',
			'user_notes'     => $wpdb->prefix . 'nbuf_user_notes',
		);

		$total_size = 0;
		foreach ( $tables as $key => $table_name ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			if ( $exists ) {
				$count       = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
				$size        = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT ROUND((data_length + index_length) / 1024, 2)
					FROM information_schema.TABLES
					WHERE table_schema = %s AND table_name = %s',
						DB_NAME,
						$table_name
					)
				);
				$total_size += (float) $size;
				$report[]    = sprintf( '%-30s | Status: EXISTS | Rows: %s | Size: %s KB', $table_name, number_format( $count ), number_format( $size, 2 ) );
			} else {
				$report[] = sprintf( '%-30s | Status: MISSING', $table_name );
			}
		}
		$report[] = sprintf( 'Total Custom Tables Size: %s KB', number_format( $total_size, 2 ) );
		$report[] = '';

		/* Zero Bloat Verification */
		$report[]          = '-------------------------------------------';
		$report[]          = 'ZERO BLOAT VERIFICATION';
		$report[]          = '-------------------------------------------';
		$wp_options_bloat  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s', $wpdb->options, 'nbuf_%' ) );
		$wp_usermeta_bloat = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'nbuf_%' ) );
		$custom_options    = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['options'] ) );

		$report[] = 'wp_options bloat: ' . ( 0 === $wp_options_bloat ? '✓ ZERO entries (GOOD)' : '✗ ' . $wp_options_bloat . ' entries found (WARNING)' );
		$report[] = 'wp_usermeta bloat: ' . ( 0 === $wp_usermeta_bloat ? '✓ ZERO entries (GOOD)' : '✗ ' . $wp_usermeta_bloat . ' entries found (WARNING)' );
		$report[] = 'Custom options table: ' . number_format( $custom_options ) . ' settings stored';
		$report[] = '';

		/* User Statistics */
		$report[]              = '-------------------------------------------';
		$report[]              = 'USER STATISTICS';
		$report[]              = '-------------------------------------------';
		$total_users           = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->users ) );
		$verified_users        = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE is_verified = 1', $tables['user_data'] ) );
		$unverified_users      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE is_verified = 0', $tables['user_data'] ) );
		$users_with_expiration = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE expires_at IS NOT NULL', $tables['user_data'] ) );
		$expired_users         = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_disabled = 0', $tables['user_data'] ) );
		$users_with_2fa        = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE enabled = 1', $tables['user_2fa'] ) );
		$total_notes           = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['user_notes'] ) );
		$total_audit_logs      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['audit_log'] ) );

		$report[] = 'Total Users: ' . number_format( $total_users );
		$report[] = 'Verified Users: ' . number_format( $verified_users );
		$report[] = 'Unverified Users: ' . number_format( $unverified_users );
		$report[] = 'Users with Expiration: ' . number_format( $users_with_expiration );
		$report[] = 'Expired Users: ' . number_format( $expired_users );
		$report[] = '2FA Enabled: ' . number_format( $users_with_2fa );
		$report[] = 'User Notes: ' . number_format( $total_notes );
		$report[] = 'Audit Log Entries: ' . number_format( $total_audit_logs );
		$report[] = '';

		/* Active Plugins */
		$report[]       = '-------------------------------------------';
		$report[]       = 'ACTIVE PLUGINS';
		$report[]       = '-------------------------------------------';
		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$report[]    = $plugin_data['Name'] . ' (v' . $plugin_data['Version'] . ')';
		}
		$report[] = '';

		$report[] = '===========================================';
		$report[] = 'END OF DIAGNOSTIC REPORT';
		$report[] = '===========================================';

		/* Generate filename */
		$filename = 'nobloat-diagnostics-' . gmdate( 'Y-m-d-His' ) . '.txt';

		/* Send headers */
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		/* Output report */
		echo implode( "\n", $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
