<?php
/**
 * Login Form Template Tab
 *
 * Manage the HTML template for the login form shortcode.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$nbuf_login_form = NBUF_Options::get( 'nbuf_login_form_template', '' );

/* If empty, load from default template */
if ( empty( $nbuf_login_form ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . 'login-form.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_login_form = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the login form displayed via the [nbuf_login_form] shortcode.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="login">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Login Form Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_login_form_template"
				rows="25"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_login_form ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {error_message}, {action_url}, {nonce_field}, {redirect_to}, {reset_link}, {register_link}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="login-form"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Login Form Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-login-wrapper</code> - Main form wrapper</li>
			<li><code>.nbuf-login-form</code> - Form element</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-form-label</code> - Form labels</li>
			<li><code>.nbuf-form-input</code> - Input fields</li>
			<li><code>.nbuf-button-primary</code> - Submit button</li>
			<li><code>.nbuf-form-footer</code> - Footer with links</li>
			<li><code>.nbuf-remember-group</code> - Remember me checkbox group</li>
		</ul>
	</div>
</div>
