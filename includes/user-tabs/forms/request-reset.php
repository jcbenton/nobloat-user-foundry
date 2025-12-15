<?php
/**
 * Password Reset Request Form Template Tab
 *
 * Manage the HTML template for the password reset request form shortcode.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$nbuf_request_reset_form = NBUF_Options::get( 'nbuf_request_reset_form_template', '' );

/* If empty, load from default template */
if ( empty( $nbuf_request_reset_form ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . 'request-reset-form.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_request_reset_form = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the password reset request form displayed via the [nbuf_request_reset_form] shortcode.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="request-reset">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Request Reset Form Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_request_reset_form_template"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_request_reset_form ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {error_message}, {success_message}, {action_url}, {nonce_field}, {login_url}, {register_link}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="request-reset-form"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Request Reset Form Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-request-reset-wrapper</code> - Main form wrapper</li>
			<li><code>.nbuf-request-reset-form</code> - Form element</li>
			<li><code>.nbuf-request-reset-description</code> - Description text</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-form-label</code> - Form labels</li>
			<li><code>.nbuf-form-input</code> - Input fields</li>
			<li><code>.nbuf-button-primary</code> - Submit button</li>
			<li><code>.nbuf-form-footer</code> - Footer with links</li>
		</ul>
	</div>
</div>
