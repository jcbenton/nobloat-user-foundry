<?php
/**
 * Account Page Styles Tab
 *
 * CSS customization for account page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle account page styles form submission
 */
if ( isset( $_POST['nbuf_save_account_css'] ) && check_admin_referer( 'nbuf_account_css_save', 'nbuf_account_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_account_css = isset( $_POST['account_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['account_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_account_page_css', $nbuf_account_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_account_css, 'account-page', 'nbuf_css_write_failed_account' );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Account page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$nbuf_account_css = NBUF_Options::get( 'nbuf_account_page_css' );
if ( empty( $nbuf_account_css ) ) {
	$nbuf_account_css = NBUF_CSS_Manager::load_default_css( 'account-page' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_account' );

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
		<?php wp_nonce_field( 'nbuf_account_css_save', 'nbuf_account_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="account">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the user account management page. Changes will be minified and written to /assets/css/frontend/account-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="account_page_css"
				rows="30"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_account_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="account-page"
					data-target="account_page_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Account Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_account_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-account-wrapper</code> - Main container</li>
			<li><code>.nbuf-account-tabs</code> - Tab navigation</li>
			<li><code>.nbuf-tab-button</code> - Tab buttons</li>
			<li><code>.nbuf-tab-content</code> - Tab content areas</li>
			<li><code>.nbuf-subtabs</code> - Sub-tab navigation</li>
			<li><code>.nbuf-subtab-button</code> - Sub-tab buttons</li>
			<li><code>.nbuf-account-section</code> - Content sections</li>
			<li><code>.nbuf-account-form</code> - Form element</li>
			<li><code>.nbuf-form-group</code> - Input group wrapper</li>
			<li><code>.nbuf-form-label</code>, <code>.nbuf-account-label</code> - Form labels</li>
			<li><code>.nbuf-form-input</code>, <code>.nbuf-account-input</code> - Input fields</li>
			<li><code>.nbuf-button</code>, <code>.nbuf-account-button</code> - Submit button</li>
			<li><code>.nbuf-message-error</code>, <code>.nbuf-account-error</code> - Error messages</li>
			<li><code>.nbuf-message-success</code>, <code>.nbuf-account-success</code> - Success messages</li>
			<li><code>.nbuf-info-grid</code> - Info display grid</li>
			<li><code>.nbuf-info-label</code>, <code>.nbuf-info-value</code> - Info items</li>
		</ul>
	</div>
</div>
