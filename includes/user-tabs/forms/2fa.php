<?php
/**
 * 2FA Verification Form Template Tab
 *
 * Manage the HTML template for the 2FA verification form shortcode.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$nbuf_twofa_form = NBUF_Options::get( 'nbuf_2fa_verify_template', '' );

/* If empty, load from default template */
if ( empty( $nbuf_twofa_form ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . '2fa-verify.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_twofa_form = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the 2FA verification form displayed via the [nbuf_2fa_verify] shortcode.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="2fa">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( '2FA Verification Form Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_verify_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_twofa_form ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {nonce_field}, {error_message}, {success_message}, {instructions_text}, {code_length}, {device_trust_checkbox}, {resend_email_link}, {backup_code_link}, {help_text}, {grace_period_notice}, {locked_out_notice}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-verify"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save 2FA Form Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-2fa-verify-wrapper</code> - Main form wrapper</li>
			<li><code>.nbuf-2fa-header</code> - Header section</li>
			<li><code>.nbuf-2fa-subtitle</code> - Subtitle text</li>
			<li><code>.nbuf-2fa-instructions</code> - Instructions text</li>
			<li><code>.nbuf-2fa-verify-form</code> - Form element</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-2fa-input</code> - Code input field</li>
			<li><code>.nbuf-2fa-button</code> - Submit button</li>
			<li><code>.nbuf-button-primary</code> - Primary button style</li>
			<li><code>.nbuf-2fa-help</code> - Help section with links</li>
			<li><code>.nbuf-error</code> - Error message</li>
			<li><code>.nbuf-success</code> - Success message</li>
			<li><code>.nbuf-warning</code> - Warning message</li>
		</ul>
	</div>
</div>
