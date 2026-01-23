<?php
/**
 * 2FA Backup Code Verify Form Template Tab
 *
 * Manage the backup code verification form template.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template using Template Manager (checks DB first, then falls back to file) */
$nbuf_backup_verify = NBUF_Template_Manager::load_template( '2fa-backup-verify' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the backup code verification form displayed when users choose to use a backup code instead of their primary 2FA method.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="backup-verify">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Backup Code Verify Form Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_backup_verify_template"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_backup_verify ); ?></textarea>
			<p class="description">
				<?php
				echo esc_html__( 'Available placeholders: ', 'nobloat-user-foundry' ) .
				esc_html( NBUF_Template_Manager::get_placeholders( '2fa-backup-verify' ) );
				?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-backup-verify"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Backup Verify Template', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
