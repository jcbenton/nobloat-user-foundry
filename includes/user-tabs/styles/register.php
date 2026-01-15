<?php
/**
 * Registration Page Styles Tab
 *
 * CSS customization for registration page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle registration page styles form submission
 */
if ( isset( $_POST['nbuf_save_register_css'] ) && check_admin_referer( 'nbuf_register_css_save', 'nbuf_register_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_registration_css = isset( $_POST['registration_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['registration_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_registration_page_css', $nbuf_registration_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_registration_css, 'registration-page', 'nbuf_css_write_failed_registration' );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Registration page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$nbuf_registration_css = NBUF_Options::get( 'nbuf_registration_page_css' );
if ( empty( $nbuf_registration_css ) ) {
	$nbuf_registration_css = NBUF_CSS_Manager::load_default_css( 'registration-page' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_registration' );

?>

<div class="nbuf-styles-tab">

	<?php if ( $nbuf_has_write_failure ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'File Write Permission Issue:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'Unable to write CSS file to disk. Styles are being loaded from database.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_register_css_save', 'nbuf_register_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="register">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the user registration page. Changes will be minified and written to /assets/css/frontend/registration-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="registration_page_css"
				rows="30"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_registration_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="registration-page"
					data-target="registration_page_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Registration Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_register_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-registration-wrapper</code> - Main container</li>
			<li><code>.nbuf-registration-header</code> - Header section</li>
			<li><code>.nbuf-registration-title</code> - Page title</li>
			<li><code>.nbuf-registration-form</code> - Form element</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-form-label</code>, <code>.nbuf-registration-label</code> - Form labels</li>
			<li><code>.nbuf-form-input</code>, <code>.nbuf-registration-input</code> - Input fields</li>
			<li><code>.nbuf-form-help</code> - Help text below inputs</li>
			<li><code>.nbuf-submit-button</code>, <code>.nbuf-registration-button</code> - Submit button</li>
			<li><code>.nbuf-message-error</code>, <code>.nbuf-registration-error</code> - Error messages</li>
			<li><code>.nbuf-message-success</code>, <code>.nbuf-registration-success</code> - Success messages</li>
			<li><code>.nbuf-registration-footer</code> - Footer with links</li>
			<li><code>.nbuf-registration-container</code> - Container when policy panel is shown</li>
			<li><code>.nbuf-policy-panel</code> - Policy panel container</li>
		</ul>
	</div>
</div>
