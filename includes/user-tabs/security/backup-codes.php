<?php
/**
 * Security > Backup Codes Tab
 *
 * One-time use backup codes for emergency 2FA access.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_backup_enabled = NBUF_Options::get( 'nbuf_2fa_backup_enabled', true );
$nbuf_backup_count   = NBUF_Options::get( 'nbuf_2fa_backup_count', 4 );
$nbuf_backup_length  = NBUF_Options::get( 'nbuf_2fa_backup_length', 32 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="backup-codes">

	<h2><?php esc_html_e( 'Backup Codes', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'One-time use backup codes provide emergency access when users cannot access their primary 2FA method (email or authenticator app).', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Backup Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_backup_enabled" value="1" <?php checked( $nbuf_backup_enabled, true ); ?>>
					<?php esc_html_e( 'Allow users to generate backup codes', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users can generate one-time use codes that bypass their primary 2FA method when their authenticator device or email is unavailable.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Number of Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_count" value="<?php echo esc_attr( $nbuf_backup_count ); ?>" min="4" max="20" class="small-text">
				<span><?php esc_html_e( 'codes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of backup codes to generate per user. Default: 4', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_length" value="<?php echo esc_attr( $nbuf_backup_length ); ?>" min="8" max="64" class="small-text">
				<span><?php esc_html_e( 'characters', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Length of each backup code. Longer codes are more secure. Default: 32', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Security Information', 'nobloat-user-foundry' ); ?></h3>
	<div class="notice notice-info inline" style="margin: 10px 0;">
		<p>
			<strong><?php esc_html_e( 'How backup codes work:', 'nobloat-user-foundry' ); ?></strong>
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><?php esc_html_e( 'Each code can only be used once', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Codes are hashed before storage (cannot be recovered if lost)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Works with both Email 2FA and Authenticator App 2FA', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Users should store codes in a secure location (password manager, safe)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Regenerating codes invalidates all previous codes', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
