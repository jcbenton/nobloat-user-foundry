<?php
/**
 * Backup Codes Form Template Tab
 *
 * Manage the HTML template for the 2FA backup codes display page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template (checks DB first, falls back to file) */
$nbuf_backup_codes = NBUF_Template_Manager::load_template( '2fa-backup-codes' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the 2FA backup codes page where users can view and regenerate their backup codes.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="backup-codes">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Backup Codes Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_backup_codes_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_backup_codes ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {backup_codes}, {codes_remaining}, {action_url}, {nonce_field}, {error_message}, {success_message}, {regenerate_button}, {download_button}, {print_button}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-backup-codes"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Backup Codes Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-2fa-backup-codes</code> - Main backup codes container</li>
			<li><code>.nbuf-backup-codes-list</code> - Codes list container</li>
			<li><code>.nbuf-backup-code</code> - Individual backup code</li>
			<li><code>.nbuf-backup-code-used</code> - Used code (strikethrough)</li>
			<li><code>.nbuf-backup-actions</code> - Action buttons container</li>
			<li><code>.nbuf-button</code> - Base button style</li>
			<li><code>.nbuf-button-primary</code> - Primary button style</li>
			<li><code>.nbuf-button-secondary</code> - Secondary button style</li>
			<li><code>.nbuf-warning</code> - Warning message</li>
		</ul>
	</div>
</div>
