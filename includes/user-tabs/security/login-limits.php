<?php
/**
 * Security > Login Limits Tab
 *
 * Login attempt limiting / brute force protection.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Login limiting settings */
$nbuf_enable_login_limiting  = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
$nbuf_login_max_attempts     = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );
$nbuf_login_lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );
$nbuf_trusted_proxies        = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );

/* Convert array to comma-separated string for display */
$nbuf_trusted_proxies_str = is_array( $nbuf_trusted_proxies ) ? implode( ', ', $nbuf_trusted_proxies ) : '';

/* Statistics - count currently blocked IPs */
$nbuf_blocked_ips_count = 0;
global $wpdb;
$nbuf_table_name = $wpdb->prefix . 'nbuf_login_attempts';
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom login attempts table statistics.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nbuf_table_name ) ) === $nbuf_table_name ) {
	$nbuf_cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$nbuf_login_lockout_duration} minutes" ) );
	$nbuf_blocked_ips_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM (SELECT ip_address FROM %i WHERE attempt_time > %s GROUP BY ip_address HAVING COUNT(*) >= %d) AS blocked",
			$nbuf_table_name,
			$nbuf_cutoff_time,
			$nbuf_login_max_attempts
		)
	);
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="login-limits">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_enable_login_limiting">

	<h2><?php esc_html_e( 'Login Protection', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Protect against brute force attacks by limiting failed login attempts.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Attempt Limiting', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_login_limiting" value="1" <?php checked( $nbuf_enable_login_limiting, true ); ?>>
					<?php esc_html_e( 'Enable login attempt limiting', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Protect against brute force attacks by limiting failed login attempts.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Maximum Attempts', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_max_attempts" value="<?php echo esc_attr( $nbuf_login_max_attempts ); ?>" min="1" max="100" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of failed login attempts allowed before the IP address is blocked. Default: 5', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Lockout Duration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_lockout_duration" value="<?php echo esc_attr( $nbuf_login_lockout_duration ); ?>" min="1" max="1440" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long to block the IP address after exceeding max attempts. Default: 10 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Trusted Proxy Servers', 'nobloat-user-foundry' ); ?></th>
			<td>
				<textarea name="nbuf_login_trusted_proxies" rows="3" class="large-text code"><?php echo esc_textarea( $nbuf_trusted_proxies_str ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'IP addresses of trusted proxy servers or load balancers (comma-separated or one per line).', 'nobloat-user-foundry' ); ?><br>
					<?php esc_html_e( 'Only requests from these IPs will have X-Forwarded-For headers trusted for rate limiting.', 'nobloat-user-foundry' ); ?><br>
					<strong><?php esc_html_e( 'Leave blank to ignore all proxy headers (most secure for direct connections).', 'nobloat-user-foundry' ); ?></strong><br>
					<?php esc_html_e( 'Example:', 'nobloat-user-foundry' ); ?> <code>127.0.0.1, 10.0.0.1</code> <?php esc_html_e( '(localhost and internal load balancer)', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<h2><?php esc_html_e( 'Statistics', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php
	if ( $nbuf_blocked_ips_count > 0 ) {
		printf(
			/* translators: %d: number of blocked IPs */
			esc_html( _n( '%d IP address is currently blocked.', '%d IP addresses are currently blocked.', $nbuf_blocked_ips_count, 'nobloat-user-foundry' ) ),
			(int) $nbuf_blocked_ips_count
		);
	} else {
		esc_html_e( 'No IP addresses are currently blocked.', 'nobloat-user-foundry' );
	}
	?>
</p>
<p class="description">
	<?php
	printf(
		/* translators: %s: link to Security Log */
		esc_html__( 'Blocked IPs can be managed from the %s.', 'nobloat-user-foundry' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-security-log' ) ) . '">' . esc_html__( 'Security Log', 'nobloat-user-foundry' ) . '</a>'
	);
	?>
</p>
