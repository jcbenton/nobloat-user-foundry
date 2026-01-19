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
		add_action( 'admin_post_nbuf_repair_tables', array( __CLASS__, 'handle_repair_tables' ) );
	}

	/**
	 * Handle database table repair.
	 *
	 * Creates any missing database tables.
	 */
	public static function handle_repair_tables() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_repair_tables' );

		/* Run all table creation and update functions via centralized method */
		NBUF_Database::repair_all_tables();

		/* Redirect back to the originating page with success message */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above.
		$redirect_to = isset( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : 'status';

		if ( 'diagnostics' === $redirect_to ) {
			wp_safe_redirect( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=diagnostics&tables_repaired=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=nobloat-foundry-users&tab=system&subtab=status&tables_repaired=1' ) );
		}
		exit;
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

		/* Database Tables - use single source of truth from NBUF_Database */
		$report[] = '-------------------------------------------';
		$report[] = 'DATABASE HEALTH';
		$report[] = '-------------------------------------------';

		$table_data = NBUF_Database::get_all_tables();
		$tables     = $table_data['expected']; /* For backward compatibility with table key references */

		$total_size = 0;

		/* Report expected tables */
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
				$report[]    = sprintf( '%-40s | Status: EXISTS | Rows: %s | Size: %s KB', $table_name, number_format( $count ), number_format( $size, 2 ) );
			} else {
				$report[] = sprintf( '%-40s | Status: MISSING', $table_name );
			}
		}

		/* Report unexpected tables (exist in DB but not in expected list) */
		if ( ! empty( $table_data['unexpected'] ) ) {
			$report[] = '';
			$report[] = 'UNEXPECTED TABLES (not in expected list):';
			foreach ( $table_data['unexpected'] as $table_name ) {
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
				$report[]    = sprintf( '%-40s | Status: UNEXPECTED | Rows: %s | Size: %s KB', $table_name, number_format( $count ), number_format( $size, 2 ) );
			}
		}

		$report[] = sprintf( 'Total Custom Tables Size: %s KB', number_format( $total_size, 2 ) );
		$report[] = '';

		/* Zero Bloat Verification */
		$report[]          = '-------------------------------------------';
		$report[]          = 'ZERO BLOAT VERIFICATION';
		$report[]          = '-------------------------------------------';
		$wp_options_bloat  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s', $wpdb->options, 'nbuf_%' ) );
		$wp_usermeta_bloat = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'nbuf_%' ) );
		$custom_options    = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['options'] ) );

		$report[] = $wpdb->options . ' (nbuf_ entries): ' . ( $wp_options_bloat < 10 ? '✓ ' . $wp_options_bloat . ' entries (minimal - OK)' : '⚠ ' . $wp_options_bloat . ' entries found (WARNING)' );
		$report[] = $wpdb->usermeta . ' (nbuf_ entries): ' . ( 0 === $wp_usermeta_bloat ? '✓ ZERO entries (GOOD)' : '⚠ ' . $wp_usermeta_bloat . ' entries found (WARNING)' );
		$report[] = $tables['options'] . ': ' . number_format( $custom_options ) . ' settings stored';
		$report[] = '';

		/* User Statistics - join with wp_users to exclude orphan records */
		$report[]              = '-------------------------------------------';
		$report[]              = 'USER STATISTICS';
		$report[]              = '-------------------------------------------';
		$total_users           = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->users ) );
		$verified_users        = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.is_verified = 1',
				$tables['user_data'],
				$wpdb->users
			)
		);
		$unverified_users      = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.is_verified = 0',
				$tables['user_data'],
				$wpdb->users
			)
		);
		$users_with_expiration = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.expires_at IS NOT NULL',
				$tables['user_data'],
				$wpdb->users
			)
		);
		$expired_users         = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.expires_at IS NOT NULL AND ud.expires_at < NOW() AND ud.is_disabled = 0',
				$tables['user_data'],
				$wpdb->users
			)
		);
		$users_with_2fa        = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i tfa INNER JOIN %i u ON tfa.user_id = u.ID WHERE tfa.enabled = 1',
				$tables['user_2fa'],
				$wpdb->users
			)
		);
		$total_notes           = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['user_notes'] ) );
		$total_audit_logs      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['user_audit_log'] ) );

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
