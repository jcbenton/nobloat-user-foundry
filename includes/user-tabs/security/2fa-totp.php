<?php
/**
 * Security > 2FA TOTP Tab
 *
 * Authenticator app (TOTP) two-factor authentication settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_totp_method    = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );
$nbuf_totp_length    = NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
$nbuf_totp_window    = NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
$nbuf_totp_tolerance = NBUF_Options::get( 'nbuf_2fa_totp_tolerance', 1 );
$nbuf_totp_qr_size   = NBUF_Options::get( 'nbuf_2fa_totp_qr_size', 200 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="2fa-totp">

	<h2><?php esc_html_e( 'Authenticator App (TOTP)', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Use time-based one-time passwords (TOTP) from authenticator apps like Google Authenticator, Authy, or Microsoft Authenticator.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'TOTP Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_method">
					<option value="disabled" <?php selected( $nbuf_totp_method, 'disabled' ); ?>>
						<?php esc_html_e( 'Off', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="user_configurable" <?php selected( $nbuf_totp_method, 'user_configurable' ); ?><?php selected( $nbuf_totp_method, 'optional' ); ?><?php selected( $nbuf_totp_method, 'optional_all' ); ?>>
						<?php esc_html_e( 'User Configurable (users can enable in their account)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required" <?php selected( $nbuf_totp_method, 'required' ); ?><?php selected( $nbuf_totp_method, 'required_all' ); ?><?php selected( $nbuf_totp_method, 'required_admin' ); ?>>
						<?php esc_html_e( 'Required (all users must use TOTP 2FA)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Control TOTP availability. When set to "User Configurable", users can enable authenticator apps (Google Authenticator, Authy, etc.) in their account settings.', 'nobloat-user-foundry' ); ?>
				</p>
				<p class="description" style="color: #d63638; font-weight: 500;">
					<strong><?php esc_html_e( 'Security Notice:', 'nobloat-user-foundry' ); ?></strong>
					<?php esc_html_e( 'HTTPS is required for authenticator-based two-factor authentication. TOTP secrets must be transmitted over a secure connection to prevent interception.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_code_length">
					<option value="6" <?php selected( $nbuf_totp_length, 6 ); ?>>6 <?php esc_html_e( 'digits (standard)', 'nobloat-user-foundry' ); ?></option>
					<option value="8" <?php selected( $nbuf_totp_length, 8 ); ?>>8 <?php esc_html_e( 'digits (extra secure)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Length of TOTP codes. Most apps use 6 digits. Default: 6', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Time Window', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_time_window">
					<option value="30" <?php selected( $nbuf_totp_window, 30 ); ?>>30 <?php esc_html_e( 'seconds (standard)', 'nobloat-user-foundry' ); ?></option>
					<option value="60" <?php selected( $nbuf_totp_window, 60 ); ?>>60 <?php esc_html_e( 'seconds', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How often codes change. Most apps use 30 seconds. Default: 30', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Clock Tolerance', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_tolerance">
					<option value="0" <?php selected( $nbuf_totp_tolerance, 0 ); ?>>±0 <?php esc_html_e( 'windows (strict)', 'nobloat-user-foundry' ); ?></option>
					<option value="1" <?php selected( $nbuf_totp_tolerance, 1 ); ?>>±1 <?php esc_html_e( 'window (recommended)', 'nobloat-user-foundry' ); ?></option>
					<option value="2" <?php selected( $nbuf_totp_tolerance, 2 ); ?>>±2 <?php esc_html_e( 'windows (lenient)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Allow codes from previous/next time windows to account for clock drift. Default: ±1', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'QR Code Size', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_totp_qr_size" value="<?php echo esc_attr( $nbuf_totp_qr_size ); ?>" min="100" max="500" step="50" class="small-text">
				<span><?php esc_html_e( 'pixels', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Size of QR codes shown during TOTP setup. Default: 200px', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'QR Code Generation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'QR codes are generated using api.qrserver.com, a free and reliable external service.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
