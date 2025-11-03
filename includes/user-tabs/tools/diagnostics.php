<?php
/**
 * Tools > Diagnostics Tab
 *
 * System diagnostics and debugging information.
 * Displays database health, user statistics, system info, and performance metrics.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

/*
 * ==========================================================
 * GATHER DIAGNOSTIC DATA
 * ==========================================================
 */

/* Database Tables */
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

$table_stats = array();
foreach ( $tables as $key => $table_name ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	if ( $exists ) {
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$size                = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND((data_length + index_length) / 1024, 2)
			FROM information_schema.TABLES
			WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name
			)
		);
		$table_stats[ $key ] = array(
			'exists' => true,
			'count'  => (int) $count,
			'size'   => (float) $size,
		);
	} else {
		$table_stats[ $key ] = array(
			'exists' => false,
			'count'  => 0,
			'size'   => 0,
		);
	}
}

/*
 * Bloat Check
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wp_options_bloat = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s', $wpdb->options, 'nbuf_%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wp_usermeta_bloat = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'nbuf_%' ) );

/*
 * User Statistics
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$total_users = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->users ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$verified_users = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE is_verified = 1', $tables['user_data'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$unverified_users = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE is_verified = 0', $tables['user_data'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$users_with_expiration = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE expires_at IS NOT NULL', $tables['user_data'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$expired_users = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_disabled = 0', $tables['user_data'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$users_with_2fa = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE enabled = 1', $tables['user_2fa'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$total_notes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['user_notes'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$total_audit_logs = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['audit_log'] ) );

/*
 * Custom Options Count
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$custom_options_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['options'] ) );

/* System Information */
$wordpress_version = get_bloginfo( 'version' );
$php_version       = PHP_VERSION;
$php_memory_limit  = ini_get( 'memory_limit' );
$php_max_execution = ini_get( 'max_execution_time' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$db_version      = $wpdb->get_var( 'SELECT VERSION()' );
$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
$plugin_version  = defined( 'NBUF_VERSION' ) ? NBUF_VERSION : 'Unknown';

/* Total Custom Table Size */
$total_table_size = array_sum( array_column( $table_stats, 'size' ) );

/* WordPress Memory Usage */
$wp_memory_limit     = WP_MEMORY_LIMIT;
$wp_max_memory_limit = WP_MAX_MEMORY_LIMIT;

?>



<div class="nbuf-diagnostics">
	<h2><?php esc_html_e( 'System Diagnostics', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'System status, database information, user statistics, and performance metrics.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- Export Button -->
	<div class="nbuf-export-section">
		<h3><?php esc_html_e( 'Export Diagnostic Report', 'nobloat-user-foundry' ); ?></h3>
		<p><?php esc_html_e( 'Download a complete diagnostic report for troubleshooting or support purposes.', 'nobloat-user-foundry' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'nbuf_export_diagnostics' ); ?>
			<input type="hidden" name="action" value="nbuf_export_diagnostics">
			<button type="submit" class="button button-primary button-hero">
				<span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
				<?php esc_html_e( 'Download Diagnostic Report', 'nobloat-user-foundry' ); ?>
			</button>
		</form>
	</div>

	<!-- Database Health -->
	<div class="nbuf-diag-section">
		<h3><?php esc_html_e( 'Database Health', 'nobloat-user-foundry' ); ?></h3>
		<table class="nbuf-diag-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Table', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Rows', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Size (KB)', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $table_stats as $key => $stats ) : ?>
					<tr>
						<td><code><?php echo esc_html( $tables[ $key ] ); ?></code></td>
						<td>
					<?php if ( $stats['exists'] ) : ?>
								<span class="nbuf-status-badge success">✓ <?php esc_html_e( 'Exists', 'nobloat-user-foundry' ); ?></span>
							<?php else : ?>
								<span class="nbuf-status-badge error">✗ <?php esc_html_e( 'Missing', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $stats['size'], 2 ) ); ?> KB</td>
					</tr>
				<?php endforeach; ?>
				<tr style="font-weight: 600; background: #f6f7f7;">
					<td colspan="3"><?php esc_html_e( 'Total Custom Tables Size:', 'nobloat-user-foundry' ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $total_table_size, 2 ) ); ?> KB</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Zero Bloat Verification -->
	<div class="nbuf-diag-section">
		<h3><?php esc_html_e( 'Zero Bloat Verification', 'nobloat-user-foundry' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Verifies that no plugin data is stored in WordPress default tables (wp_options, wp_usermeta). All data should be in custom tables.', 'nobloat-user-foundry' ); ?>
		</p>
		<table class="nbuf-diag-table">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'wp_options bloat', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php if ( 0 === (int) $wp_options_bloat ) : ?>
							<span class="nbuf-status-badge success">✓ <?php esc_html_e( 'Zero entries', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span class="nbuf-status-badge warning"><?php echo esc_html( (int) $wp_options_bloat ); ?> <?php esc_html_e( 'entries found', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'wp_usermeta bloat', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php if ( 0 === (int) $wp_usermeta_bloat ) : ?>
							<span class="nbuf-status-badge success">✓ <?php esc_html_e( 'Zero entries', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span class="nbuf-status-badge warning"><?php echo esc_html( (int) $wp_usermeta_bloat ); ?> <?php esc_html_e( 'entries found', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Custom options table', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<span class="nbuf-status-badge success"><?php echo esc_html( number_format_i18n( $custom_options_count ) ); ?> <?php esc_html_e( 'settings stored', 'nobloat-user-foundry' ); ?></span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- User Statistics -->
	<div class="nbuf-diag-section">
		<h3><?php esc_html_e( 'User Statistics', 'nobloat-user-foundry' ); ?></h3>
		<div class="nbuf-stat-grid">
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Total Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Verified Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $verified_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Unverified Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $unverified_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'With Expiration', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $users_with_expiration ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Expired Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $expired_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( '2FA Enabled', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $users_with_2fa ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'User Notes', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $total_notes ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Audit Log Entries', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $total_audit_logs ) ); ?></div>
			</div>
		</div>
	</div>

	<!-- System Information -->
	<div class="nbuf-diag-section">
		<h3><?php esc_html_e( 'System Information', 'nobloat-user-foundry' ); ?></h3>
		<table class="nbuf-diag-table">
			<tbody>
				<tr>
					<td style="width: 40%;"><strong><?php esc_html_e( 'Plugin Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $plugin_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WordPress Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $wordpress_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $php_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $db_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Server Software', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $server_software ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $php_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WP Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $wp_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WP Max Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $wp_max_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Max Execution Time', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $php_max_execution ); ?> <?php esc_html_e( 'seconds', 'nobloat-user-foundry' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>