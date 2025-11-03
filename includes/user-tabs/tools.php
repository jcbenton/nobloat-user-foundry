<?php
/**
 * Tools Tab
 *
 * Migration tools and utilities for importing user data
 * from other user management plugins.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h2><?php esc_html_e( 'Migration & Tools', 'nobloat-user-foundry' ); ?></h2>

	<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 20px; margin-top: 20px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Data Migration Tools', 'nobloat-user-foundry' ); ?></h3>
		<p>
			<?php esc_html_e( 'Migration tools will be available here to help you import user data from other user management plugins.', 'nobloat-user-foundry' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Coming soon: Import verification status, expiration dates, and user profile data from popular plugins.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Diagnostic Tools', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'System Status', 'nobloat-user-foundry' ); ?></h4>
		<table class="widefat" style="max-width: 600px;">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'User Manager Status:', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php
						$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
						if ( $system_enabled ) {
							echo '<span style="color: #00a32a; font-weight: 500;">✓ ' . esc_html__( 'ENABLED', 'nobloat-user-foundry' ) . '</span>';
						} else {
							echo '<span style="color: #d63638; font-weight: 500;">⚠️ ' . esc_html__( 'DISABLED', 'nobloat-user-foundry' ) . '</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database Tables:', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php
						global $wpdb;
						$tables       = array(
							$wpdb->prefix . 'nbuf_tokens',
							$wpdb->prefix . 'nbuf_user_data',
							$wpdb->prefix . 'nbuf_options',
							$wpdb->prefix . 'nbuf_user_profile',
							$wpdb->prefix . 'nbuf_login_attempts',
						);
						$tables_exist = 0;
						foreach ( $tables as $table ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
							if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
								++$tables_exist;
							}
						}
						echo esc_html( $tables_exist . ' / 5 tables exist' );
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Total Options:', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
						$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nbuf_options" );
						echo esc_html( $count . ' options in custom table' );
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Verified Users:', 'nobloat-user-foundry' ); ?></strong></td>
					<td>
						<?php
						if ( class_exists( 'NBUF_User_Data' ) ) {
							echo esc_html( NBUF_User_Data::get_count( 'verified' ) );
						} else {
							echo '—';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-user-foundry&tab=general' ) ); ?>" class="button">
				<?php esc_html_e( 'Go to Settings', 'nobloat-user-foundry' ); ?>
			</a>
		</p>
	</div>
</div>
