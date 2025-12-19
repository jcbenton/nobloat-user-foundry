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

/* Statistics - count users with backup codes */
$users_with_backup_codes = 0;
global $wpdb;
$table_name = $wpdb->prefix . 'nbuf_user_2fa';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$users_with_backup_codes = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE backup_codes IS NOT NULL AND backup_codes != %s AND backup_codes != %s',
			$table_name,
			'',
			'[]'
		)
	);
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="backup-codes">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_2fa_backup_enabled">

	<h2><?php esc_html_e( 'Backup Codes', 'nobloat-user-foundry' ); ?></h2>

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

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<?php if ( $users_with_backup_codes > 0 ) : ?>
<h2><?php esc_html_e( 'Statistics', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php
	printf(
		/* translators: %d: number of users with backup codes */
		esc_html( _n( '%d user has backup codes generated.', '%d users have backup codes generated.', $users_with_backup_codes, 'nobloat-user-foundry' ) ),
		(int) $users_with_backup_codes
	);
	?>
</p>
<?php endif; ?>
