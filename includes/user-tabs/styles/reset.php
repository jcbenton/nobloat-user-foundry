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
	$reset_css = isset( $_POST['reset_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['reset_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_reset_page_css', $reset_css, false, 'css' );

	/* Write to disk */
	$success = NBUF_CSS_Manager::save_css_to_disk( $reset_css, 'reset-page', 'nbuf_css_write_failed_reset' );

	/* Rebuild combined file if enabled */
	$css_combine_files = NBUF_Options::get( 'nbuf_css_combine_files', true );
	if ( $css_combine_files ) {
		$login_css        = NBUF_Options::get( 'nbuf_login_page_css' );
		$registration_css = NBUF_Options::get( 'nbuf_registration_page_css' );
		$account_css      = NBUF_Options::get( 'nbuf_account_page_css' );

		if ( empty( $login_css ) ) {
			$login_css = NBUF_CSS_Manager::load_default_css( 'login-page' );
		}
		if ( empty( $registration_css ) ) {
			$registration_css = NBUF_CSS_Manager::load_default_css( 'registration-page' );
		}
		if ( empty( $account_css ) ) {
			$account_css = NBUF_CSS_Manager::load_default_css( 'account-page' );
		}

		$combined_css = $reset_css . "\n\n" . $login_css . "\n\n" . $registration_css . "\n\n" . $account_css;
		NBUF_CSS_Manager::save_css_to_disk( $combined_css, 'nobloat-combined', 'nbuf_css_write_failed_combined' );
	}

	if ( $success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reset page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$reset_css = NBUF_Options::get( 'nbuf_reset_page_css' );
if ( empty( $reset_css ) ) {
	$reset_css = NBUF_CSS_Manager::load_default_css( 'reset-page' );
}

$has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_reset' );

?>

<div class="nbuf-styles-tab">

	<?php if ( $has_write_failure ) : ?>
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
			><?php echo esc_textarea( $reset_css ); ?></textarea>

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
			<li><code>.nbuf-reset-form</code> - Form element</li>
			<li><code>.nbuf-reset-label</code> - Form labels</li>
			<li><code>.nbuf-reset-input</code> - Input fields</li>
			<li><code>.nbuf-reset-button</code> - Submit button</li>
			<li><code>.nbuf-reset-error</code> - Error messages</li>
			<li><code>.nbuf-reset-notice</code> - Success messages</li>
			<li><code>.nbuf-reset-container</code> - Container when policy panel is shown</li>
			<li><code>.nbuf-policy-panel</code> - Policy panel container</li>
		</ul>
	</div>
</div>
