<?php
/**
 * Security > 2FA Settings Tab
 *
 * Backup codes, device trust, and general 2FA options.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Backup codes */
$backup_enabled = NBUF_Options::get( 'nbuf_2fa_backup_enabled', true );
$backup_count   = NBUF_Options::get( 'nbuf_2fa_backup_count', 10 );
$backup_length  = NBUF_Options::get( 'nbuf_2fa_backup_length', 8 );

/* General 2FA options */
$device_trust     = NBUF_Options::get( 'nbuf_2fa_device_trust', true );
$admin_bypass     = NBUF_Options::get( 'nbuf_2fa_admin_bypass', false );
$lockout_attempts = NBUF_Options::get( 'nbuf_2fa_lockout_attempts', 5 );
$grace_period     = NBUF_Options::get( 'nbuf_2fa_grace_period', 7 );

/* Admin notifications */
$notify_lockout = NBUF_Options::get( 'nbuf_2fa_notify_lockout', false );
$notify_disable = NBUF_Options::get( 'nbuf_2fa_notify_disable', false );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_security_group' );
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="2fa-settings">

	<h2><?php esc_html_e( 'Backup Codes', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'One-time use backup codes for emergency access if user loses their 2FA device.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Backup Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_backup_enabled" value="1" <?php checked( $backup_enabled, true ); ?>>
					<?php esc_html_e( 'Allow users to generate backup codes', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'One-time use codes for emergency access if user loses authenticator device.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Number of Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_count" value="<?php echo esc_attr( $backup_count ); ?>" min="5" max="20" class="small-text">
				<span><?php esc_html_e( 'codes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of backup codes to generate. Default: 10', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_length" value="<?php echo esc_attr( $backup_length ); ?>" min="6" max="12" class="small-text">
				<span><?php esc_html_e( 'characters', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Length of each backup code. Default: 8', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'General 2FA Options', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Device Trust', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_device_trust" value="1" <?php checked( $device_trust, true ); ?>>
					<?php esc_html_e( 'Allow users to trust devices for 30 days', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Trusted devices will not require 2FA for 30 days. Uses secure cookies.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Administrator Bypass', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_admin_bypass" value="1" <?php checked( $admin_bypass, true ); ?>>
					<?php esc_html_e( 'Allow administrators to bypass 2FA requirements', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users with "manage_options" capability can skip 2FA. Not recommended for security.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Failed Attempt Lockout', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_lockout_attempts" value="<?php echo esc_attr( $lockout_attempts ); ?>" min="3" max="20" class="small-text">
				<span><?php esc_html_e( 'attempts', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Lock out after this many failed 2FA attempts. Default: 5', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Setup Grace Period', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_grace_period" value="<?php echo esc_attr( $grace_period ); ?>" min="0" max="30" class="small-text">
				<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'When 2FA is made required, users have this many days to set it up. Default: 7', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Admin Notifications', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Email notifications for 2FA-related events.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Notify on Lockout', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_notify_lockout" value="1" <?php checked( $notify_lockout, true ); ?>>
					<?php esc_html_e( 'Email admins when a user is locked out from failed 2FA attempts', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Sends notification to site admin email when users exceed maximum 2FA verification attempts.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Notify on Self-Disable', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_notify_disable" value="1" <?php checked( $notify_disable, true ); ?>>
					<?php esc_html_e( 'Email admins when a user disables their own 2FA', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Sends notification to site admin email when users turn off 2FA from their account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
