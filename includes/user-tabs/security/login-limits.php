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
$nbuf_enable_login_limiting         = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
$nbuf_login_max_attempts            = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );
$nbuf_login_lockout_duration        = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );
$nbuf_login_max_attempts_username   = NBUF_Options::get( 'nbuf_login_max_attempts_per_username', 10 );
$nbuf_login_username_lockout_window = NBUF_Options::get( 'nbuf_login_username_lockout_window', 60 );
$nbuf_trusted_proxies               = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );

/* IP restriction settings */
$nbuf_ip_restriction_enabled      = NBUF_Options::get( 'nbuf_ip_restriction_enabled', false );
$nbuf_ip_restriction_mode         = NBUF_Options::get( 'nbuf_ip_restriction_mode', 'whitelist' );
$nbuf_ip_restriction_list         = NBUF_Options::get( 'nbuf_ip_restriction_list', '' );
$nbuf_ip_restriction_admin_bypass = NBUF_Options::get( 'nbuf_ip_restriction_admin_bypass', true );

/* Convert array to comma-separated string for display */
$nbuf_trusted_proxies_str = is_array( $nbuf_trusted_proxies ) ? implode( ', ', $nbuf_trusted_proxies ) : '';

/* Statistics - count currently blocked IPs */
$nbuf_blocked_ips_count = 0;
global $wpdb;
$nbuf_table_name = $wpdb->prefix . 'nbuf_login_attempts';
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom login attempts table statistics.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nbuf_table_name ) ) === $nbuf_table_name ) {
	$nbuf_cutoff_time       = gmdate( 'Y-m-d H:i:s', strtotime( "-{$nbuf_login_lockout_duration} minutes" ) );
	$nbuf_blocked_ips_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM (SELECT ip_address FROM %i WHERE attempt_time > %s GROUP BY ip_address HAVING COUNT(*) >= %d) AS blocked',
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
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_ip_restriction_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_ip_restriction_admin_bypass">

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
	</table>

	<h3><?php esc_html_e( 'Distributed Attack Protection', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Protect against distributed brute force attacks where multiple IP addresses target a single username.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Max Attempts per Username', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_max_attempts_per_username" value="<?php echo esc_attr( $nbuf_login_max_attempts_username ); ?>" min="5" max="100" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Maximum failed login attempts for a single username across ALL IP addresses before blocking. Prevents distributed brute force attacks. Default: 10', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Username Lockout Window', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_username_lockout_window" value="<?php echo esc_attr( $nbuf_login_username_lockout_window ); ?>" min="5" max="1440" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Time window for counting username-based attempts. Default: 60 minutes (1 hour)', 'nobloat-user-foundry' ); ?>
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

	<h2><?php esc_html_e( 'IP Access Restrictions', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Restrict login access to specific IP addresses or ranges. Useful for limiting access to corporate networks or known locations.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'IP Restrictions', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_ip_restriction_enabled" value="1" <?php checked( $nbuf_ip_restriction_enabled, true ); ?> id="nbuf_ip_restriction_enabled">
					<?php esc_html_e( 'Enable IP-based login restrictions', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, only specified IP addresses can log in (whitelist mode) or specified IPs are blocked (blacklist mode).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_ip_mode_row">
			<th><?php esc_html_e( 'Restriction Mode', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_ip_restriction_mode">
					<option value="whitelist" <?php selected( $nbuf_ip_restriction_mode, 'whitelist' ); ?>>
						<?php esc_html_e( 'Whitelist - Only allow specified IPs', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="blacklist" <?php selected( $nbuf_ip_restriction_mode, 'blacklist' ); ?>>
						<?php esc_html_e( 'Blacklist - Block specified IPs', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Whitelist: Only listed IPs can log in. Blacklist: Listed IPs are blocked from logging in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_ip_list_row">
			<th><?php esc_html_e( 'IP Address List', 'nobloat-user-foundry' ); ?></th>
			<td>
				<textarea name="nbuf_ip_restriction_list" rows="6" class="large-text code" placeholder="192.168.1.1&#10;10.0.0.0/8&#10;172.16.*.*"><?php echo esc_textarea( $nbuf_ip_restriction_list ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'One IP per line. Supports:', 'nobloat-user-foundry' ); ?><br>
					<code>192.168.1.1</code> <?php esc_html_e( '(exact IP)', 'nobloat-user-foundry' ); ?>,
					<code>192.168.1.0/24</code> <?php esc_html_e( '(CIDR notation)', 'nobloat-user-foundry' ); ?>,
					<code>192.168.1.*</code> <?php esc_html_e( '(wildcard)', 'nobloat-user-foundry' ); ?>
				</p>
				<p class="description" style="margin-top: 8px;">
					<strong><?php esc_html_e( 'Your current IP:', 'nobloat-user-foundry' ); ?></strong>
					<code><?php echo esc_html( class_exists( 'NBUF_IP_Restrictions' ) ? NBUF_IP_Restrictions::get_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown' ) ); ?></code>
					<?php esc_html_e( '- Make sure to include this IP if using whitelist mode to avoid locking yourself out.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_ip_bypass_row">
			<th><?php esc_html_e( 'Admin Bypass', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_ip_restriction_admin_bypass" value="1" <?php checked( $nbuf_ip_restriction_admin_bypass, true ); ?>>
					<?php esc_html_e( 'Allow administrators to bypass IP restrictions', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users with administrator privileges can log in from any IP address. Recommended to prevent accidental lockouts.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<script>
	(function() {
		var enableCheckbox = document.getElementById('nbuf_ip_restriction_enabled');
		var modeRow = document.getElementById('nbuf_ip_mode_row');
		var listRow = document.getElementById('nbuf_ip_list_row');
		var bypassRow = document.getElementById('nbuf_ip_bypass_row');

		function toggleRows() {
			var show = enableCheckbox.checked;
			modeRow.style.display = show ? '' : 'none';
			listRow.style.display = show ? '' : 'none';
			bypassRow.style.display = show ? '' : 'none';
		}

		enableCheckbox.addEventListener('change', toggleRows);
		toggleRows();
	})();
	</script>

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
