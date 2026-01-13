<?php
/**
 * 2FA Setup (TOTP) Form Template Tab
 *
 * Manage the HTML template for the 2FA TOTP setup page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template (checks DB first, falls back to file) */
$nbuf_twofa_setup = NBUF_Template_Manager::load_template( '2fa-setup-totp' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the 2FA TOTP authenticator setup page where users configure their authenticator app.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="2fa-setup">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( '2FA Setup Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_setup_totp_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_twofa_setup ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {qr_code}, {secret_key}, {action_url}, {cancel_url}, {nonce_field}, {error_message}, {success_message}, {app_name}, {instructions}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-setup-totp"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save 2FA Setup Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-2fa-setup-wrapper</code> - Main setup wrapper</li>
			<li><code>.nbuf-setup-grid</code> - Two-column setup layout</li>
			<li><code>.nbuf-setup-subtitle</code> - Subtitle text</li>
			<li><code>.nbuf-2fa-qr-code</code> - QR code container</li>
			<li><code>.nbuf-2fa-secret</code> - Secret key display</li>
			<li><code>.nbuf-2fa-input</code> - Verification code input</li>
			<li><code>.nbuf-2fa-button</code> - Submit button</li>
			<li><code>.nbuf-button-primary</code> - Primary button style</li>
			<li><code>.nbuf-error</code> - Error message</li>
			<li><code>.nbuf-success</code> - Success message</li>
		</ul>
	</div>
</div>
