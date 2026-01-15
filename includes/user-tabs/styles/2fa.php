<?php
/**
 * 2FA Page Styles Tab
 *
 * CSS customization for 2FA verification and setup pages.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle 2FA page styles form submission
 */
if ( isset( $_POST['nbuf_save_2fa_css'] ) && check_admin_referer( 'nbuf_2fa_css_save', 'nbuf_2fa_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_twofa_css = isset( $_POST['twofa_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['twofa_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_2fa_page_css', $nbuf_twofa_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_twofa_css, '2fa-setup', 'nbuf_css_write_failed_2fa' );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '2FA page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$nbuf_twofa_css = NBUF_Options::get( 'nbuf_2fa_page_css' );
if ( empty( $nbuf_twofa_css ) ) {
	$nbuf_twofa_css = NBUF_CSS_Manager::load_default_css( '2fa-setup' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_2fa' );

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
		<?php wp_nonce_field( 'nbuf_2fa_css_save', 'nbuf_2fa_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="2fa">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the styling for 2FA verification and setup pages. Changes will be minified and written to /assets/css/frontend/2fa-setup-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="twofa_page_css"
				rows="30"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_twofa_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="2fa-setup"
					data-target="twofa_page_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save 2FA Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_2fa_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-2fa-verify-wrapper</code> - Verification form container</li>
			<li><code>.nbuf-2fa-header</code> - Header section</li>
			<li><code>.nbuf-2fa-instructions</code> - Instructions text</li>
			<li><code>.nbuf-2fa-verify-form</code> - Form element</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-2fa-input</code> - Code input field</li>
			<li><code>.nbuf-2fa-button</code> - Submit button</li>
			<li><code>.nbuf-button-primary</code> - Primary button style</li>
			<li><code>.nbuf-2fa-help</code> - Help section with links</li>
			<li><code>.nbuf-2fa-setup-page</code> - Setup page container</li>
			<li><code>.nbuf-2fa-setup-wrapper</code> - TOTP setup form wrapper</li>
			<li><code>.nbuf-setup-grid</code> - Two-column setup layout</li>
			<li><code>.nbuf-setup-subtitle</code> - Subtitle text</li>
			<li><code>.nbuf-2fa-options</code> - Options grid</li>
			<li><code>.nbuf-2fa-option-card</code> - Option card</li>
			<li><code>.nbuf-2fa-qr-code</code> - QR code section</li>
			<li><code>.nbuf-2fa-secret</code> - TOTP secret display</li>
			<li><code>.nbuf-2fa-backup-codes</code> - Backup codes section</li>
			<li><code>.nbuf-status-active</code>, <code>.nbuf-status-inactive</code> - Status badges</li>
			<li><code>.nbuf-error</code> - Error message</li>
			<li><code>.nbuf-success</code> - Success message</li>
		</ul>
	</div>
</div>
