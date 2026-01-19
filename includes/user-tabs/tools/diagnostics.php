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

/*
 * Database Tables - uses database introspection to discover tables.
 * Returns: expected, existing, missing, unexpected arrays.
 */
$nbuf_table_data = NBUF_Database::get_all_tables();
$nbuf_tables     = $nbuf_table_data['expected']; /* For backward compatibility with queries below */

/* Gather stats for all tables (expected + any unexpected) */
$nbuf_all_tables  = array_merge( $nbuf_table_data['expected'], $nbuf_table_data['unexpected'] );
$nbuf_table_stats = array();

foreach ( $nbuf_all_tables as $nbuf_key => $nbuf_table_name ) {
	$nbuf_exists = in_array( $nbuf_table_name, array_values( $nbuf_table_data['existing'] ), true )
				|| in_array( $nbuf_table_name, array_values( $nbuf_table_data['unexpected'] ), true );

	if ( $nbuf_exists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$nbuf_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $nbuf_table_name ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$nbuf_size                     = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND((data_length + index_length) / 1024, 2)
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$nbuf_table_name
			)
		);
		$nbuf_table_stats[ $nbuf_key ] = array(
			'name'       => $nbuf_table_name,
			'exists'     => true,
			'count'      => (int) $nbuf_count,
			'size'       => (float) $nbuf_size,
			'unexpected' => isset( $nbuf_table_data['unexpected'][ $nbuf_key ] ),
		);
	} else {
		$nbuf_table_stats[ $nbuf_key ] = array(
			'name'       => $nbuf_table_name,
			'exists'     => false,
			'count'      => 0,
			'size'       => 0,
			'unexpected' => false,
		);
	}
}

/*
 * Bloat Check
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_wp_options_bloat = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s', $wpdb->options, 'nbuf_%' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_wp_usermeta_bloat = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, 'nbuf_%' ) );

/*
 * User Statistics
 * All queries join with wp_users to exclude orphan records from deleted users.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_total_users = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->users ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_verified_users = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.is_verified = 1',
		$nbuf_tables['user_data'],
		$wpdb->users
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_unverified_users = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.is_verified = 0',
		$nbuf_tables['user_data'],
		$wpdb->users
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_users_with_expiration = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.expires_at IS NOT NULL',
		$nbuf_tables['user_data'],
		$wpdb->users
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_expired_users = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM %i ud INNER JOIN %i u ON ud.user_id = u.ID WHERE ud.expires_at IS NOT NULL AND ud.expires_at < NOW() AND ud.is_disabled = 0',
		$nbuf_tables['user_data'],
		$wpdb->users
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_users_with_2fa = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM %i tfa INNER JOIN %i u ON tfa.user_id = u.ID WHERE tfa.enabled = 1',
		$nbuf_tables['user_2fa'],
		$wpdb->users
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_total_notes = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $nbuf_tables['user_notes'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_total_audit_logs = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $nbuf_tables['user_audit_log'] ) );

/*
 * Custom Options Count
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_custom_options_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $nbuf_tables['options'] ) );

/* System Information */
$nbuf_wordpress_version = get_bloginfo( 'version' );
$nbuf_php_version       = PHP_VERSION;
$nbuf_php_memory_limit  = ini_get( 'memory_limit' );
$nbuf_php_max_execution = ini_get( 'max_execution_time' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nbuf_db_version      = $wpdb->get_var( 'SELECT VERSION()' );
$nbuf_server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
$nbuf_plugin_version  = defined( 'NBUF_VERSION' ) ? NBUF_VERSION : 'Unknown';

/* Total Custom Table Size */
$nbuf_total_table_size = array_sum( array_column( $nbuf_table_stats, 'size' ) );

/* WordPress Memory Usage */
$nbuf_wp_memory_limit     = WP_MEMORY_LIMIT;
$nbuf_wp_max_memory_limit = WP_MAX_MEMORY_LIMIT;

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

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action.
		if ( isset( $_GET['tables_repaired'] ) && '1' === $_GET['tables_repaired'] ) :
			?>
			<div class="notice notice-success inline" style="margin: 0 0 15px 0;">
				<p><?php esc_html_e( 'Database tables have been repaired successfully.', 'nobloat-user-foundry' ); ?></p>
			</div>
		<?php endif; ?>

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
				<?php foreach ( $nbuf_table_stats as $nbuf_key => $nbuf_stats ) : ?>
					<tr>
						<td><code><?php echo esc_html( $nbuf_stats['name'] ); ?></code></td>
						<td>
							<?php if ( $nbuf_stats['unexpected'] ) : ?>
								<span class="nbuf-status-badge warning">⚠ <?php esc_html_e( 'Unexpected', 'nobloat-user-foundry' ); ?></span>
							<?php elseif ( $nbuf_stats['exists'] ) : ?>
								<span class="nbuf-status-badge success">✓ <?php esc_html_e( 'Exists', 'nobloat-user-foundry' ); ?></span>
							<?php else : ?>
								<span class="nbuf-status-badge error">✗ <?php esc_html_e( 'Missing', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $nbuf_stats['count'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $nbuf_stats['size'], 2 ) ); ?> KB</td>
					</tr>
				<?php endforeach; ?>
				<tr style="font-weight: 600;">
					<td colspan="3"><?php esc_html_e( 'Total Custom Tables Size:', 'nobloat-user-foundry' ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $nbuf_total_table_size, 2 ) ); ?> KB</td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $nbuf_table_data['unexpected'] ) ) : ?>
		<div class="notice notice-warning inline" style="margin: 15px 0;">
			<p>
				<strong><?php esc_html_e( 'Unexpected tables detected:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'These tables exist in the database but are not part of the current plugin version. They may be from an older version or a different plugin.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<div style="margin-top: 15px;">
			<p class="description" style="margin-bottom: 10px;">
				<?php esc_html_e( 'If any tables show as "Missing", use the repair button to create them.', 'nobloat-user-foundry' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
				<input type="hidden" name="action" value="nbuf_repair_tables">
				<input type="hidden" name="redirect_to" value="diagnostics">
				<?php wp_nonce_field( 'nbuf_repair_tables' ); ?>
				<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'This will create any missing database tables. Existing data will not be affected. Continue?', 'nobloat-user-foundry' ) ); ?>');">
					<?php esc_html_e( 'Repair Database Tables', 'nobloat-user-foundry' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Zero Bloat Verification -->
	<div class="nbuf-diag-section">
		<h3><?php esc_html_e( 'Zero Bloat Verification', 'nobloat-user-foundry' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Verifies that minimal plugin data is stored in WordPress default tables (wp_options, wp_usermeta). Most data should be in custom tables.', 'nobloat-user-foundry' ); ?>
		</p>
		<table class="nbuf-diag-table">
			<tbody>
				<tr>
					<td><strong><code><?php echo esc_html( $wpdb->options ); ?></code></strong> <span class="description">(<?php esc_html_e( 'nbuf_ entries', 'nobloat-user-foundry' ); ?>)</span></td>
					<td>
						<?php if ( (int) $nbuf_wp_options_bloat < 10 ) : ?>
							<span class="nbuf-status-badge success">✓ <?php echo esc_html( (int) $nbuf_wp_options_bloat ); ?> <?php esc_html_e( 'entries (minimal)', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span class="nbuf-status-badge warning"><?php echo esc_html( (int) $nbuf_wp_options_bloat ); ?> <?php esc_html_e( 'entries found', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><code><?php echo esc_html( $wpdb->usermeta ); ?></code></strong> <span class="description">(<?php esc_html_e( 'nbuf_ entries', 'nobloat-user-foundry' ); ?>)</span></td>
					<td>
						<?php if ( 0 === (int) $nbuf_wp_usermeta_bloat ) : ?>
							<span class="nbuf-status-badge success">✓ <?php esc_html_e( 'Zero entries', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span class="nbuf-status-badge warning"><?php echo esc_html( (int) $nbuf_wp_usermeta_bloat ); ?> <?php esc_html_e( 'entries found', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><code><?php echo esc_html( $nbuf_tables['options'] ); ?></code></strong></td>
					<td>
						<span class="nbuf-status-badge success"><?php echo esc_html( number_format_i18n( $nbuf_custom_options_count ) ); ?> <?php esc_html_e( 'settings stored', 'nobloat-user-foundry' ); ?></span>
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
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_total_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Verified Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_verified_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Unverified Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_unverified_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'With Expiration', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_users_with_expiration ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Expired Users', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_expired_users ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( '2FA Enabled', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_users_with_2fa ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'User Notes', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_total_notes ) ); ?></div>
			</div>
			<div class="nbuf-stat-box">
				<div class="label"><?php esc_html_e( 'Audit Log Entries', 'nobloat-user-foundry' ); ?></div>
				<div class="number"><?php echo esc_html( number_format_i18n( $nbuf_total_audit_logs ) ); ?></div>
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
					<td><?php echo esc_html( $nbuf_plugin_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WordPress Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_wordpress_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_php_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database Version', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_db_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Server Software', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_server_software ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_php_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WP Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_wp_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WP Max Memory Limit', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_wp_max_memory_limit ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Max Execution Time', 'nobloat-user-foundry' ); ?></strong></td>
					<td><?php echo esc_html( $nbuf_php_max_execution ); ?> <?php esc_html_e( 'seconds', 'nobloat-user-foundry' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>