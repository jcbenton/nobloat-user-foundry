<?php
/**
 * Reset Page Styles Tab
 *
 * CSS customization for password reset page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle reset page styles form submission
 */
if ( isset( $_POST['nbuf_save_reset_css'] ) && check_admin_referer( 'nbuf_reset_css_save', 'nbuf_reset_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_reset_css = isset( $_POST['reset_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['reset_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_reset_page_css', $nbuf_reset_css, false, 'css' );

	/* Write to disk (force=true to always regenerate on explicit save) */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_reset_css, 'reset-page', 'nbuf_css_write_failed_reset', true );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reset page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$nbuf_reset_css = NBUF_Options::get( 'nbuf_reset_page_css' );
if ( empty( $nbuf_reset_css ) ) {
	$nbuf_reset_css = NBUF_CSS_Manager::load_default_css( 'reset-page' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_reset' );

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
		<?php wp_nonce_field( 'nbuf_reset_css_save', 'nbuf_reset_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="reset">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the password reset page. Changes will be minified and written to /assets/css/frontend/reset-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="reset_page_css"
				rows="30"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_reset_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="reset-page"
					data-target="reset_page_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Reset Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_reset_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-reset-wrapper</code> - Main container (password reset form)</li>
			<li><code>.nbuf-request-reset-wrapper</code> - Main container (request reset form)</li>
			<li><code>.nbuf-reset-title</code> - Page title</li>
			<li><code>.nbuf-reset-form</code>, <code>.nbuf-request-reset-form</code> - Form elements</li>
			<li><code>.nbuf-form-label</code>, <code>.nbuf-reset-label</code> - Form labels</li>
			<li><code>.nbuf-form-input</code>, <code>.nbuf-reset-input</code> - Input fields</li>
			<li><code>.nbuf-reset-button</code>, <code>.nbuf-request-reset-button</code> - Submit buttons</li>
			<li><code>.nbuf-message-error</code>, <code>.nbuf-reset-error</code> - Error messages</li>
			<li><code>.nbuf-message-success</code>, <code>.nbuf-reset-success</code> - Success messages</li>
			<li><code>.nbuf-request-reset-description</code> - Description text</li>
			<li><code>.nbuf-request-reset-links</code> - Links below form</li>
			<li><code>.nbuf-reset-container</code> - Container when policy panel is shown</li>
			<li><code>.nbuf-policy-panel</code> - Policy panel container</li>
		</ul>
	</div>
</div>
