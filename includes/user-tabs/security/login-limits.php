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
$enable_login_limiting  = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
$login_max_attempts     = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );
$login_lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );
$trusted_proxies        = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );

/* Convert array to comma-separated string for display */
$trusted_proxies_str = is_array( $trusted_proxies ) ? implode( ', ', $trusted_proxies ) : '';
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="login-limits">

	<h2><?php esc_html_e( 'Login Protection', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Protect against brute force attacks by limiting failed login attempts.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Attempt Limiting', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_login_limiting" value="1" <?php checked( $enable_login_limiting, true ); ?>>
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
				<input type="number" name="nbuf_login_max_attempts" value="<?php echo esc_attr( $login_max_attempts ); ?>" min="1" max="100" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of failed login attempts allowed before the IP address is blocked. Default: 5', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Lockout Duration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_lockout_duration" value="<?php echo esc_attr( $login_lockout_duration ); ?>" min="1" max="1440" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long to block the IP address after exceeding max attempts. Default: 10 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Trusted Proxy Servers', 'nobloat-user-foundry' ); ?></th>
			<td>
				<textarea name="nbuf_login_trusted_proxies" rows="3" class="large-text code"><?php echo esc_textarea( $trusted_proxies_str ); ?></textarea>
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
