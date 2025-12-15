<?php
/**
 * Login Page Styles Tab
 *
 * CSS customization for login page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle login page styles form submission
 */
if ( isset( $_POST['nbuf_save_login_css'] ) && check_admin_referer( 'nbuf_login_css_save', 'nbuf_login_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_login_css = isset( $_POST['login_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['login_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_login_page_css', $nbuf_login_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_login_css, 'login-page', 'nbuf_css_write_failed_login' );

	/* Rebuild combined file if enabled */
	$nbuf_css_combine_files = NBUF_Options::get( 'nbuf_css_combine_files', true );
	if ( $nbuf_css_combine_files ) {
		$nbuf_reset_css        = NBUF_Options::get( 'nbuf_reset_page_css' );
		$nbuf_registration_css = NBUF_Options::get( 'nbuf_registration_page_css' );
		$nbuf_account_css      = NBUF_Options::get( 'nbuf_account_page_css' );

		if ( empty( $nbuf_reset_css ) ) {
			$nbuf_reset_css = NBUF_CSS_Manager::load_default_css( 'reset-page' );
		}
		if ( empty( $nbuf_registration_css ) ) {
			$nbuf_registration_css = NBUF_CSS_Manager::load_default_css( 'registration-page' );
		}
		if ( empty( $nbuf_account_css ) ) {
			$nbuf_account_css = NBUF_CSS_Manager::load_default_css( 'account-page' );
		}

		$nbuf_combined_css = $nbuf_reset_css . "\n\n" . $nbuf_login_css . "\n\n" . $nbuf_registration_css . "\n\n" . $nbuf_account_css;
		NBUF_CSS_Manager::save_css_to_disk( $nbuf_combined_css, 'nobloat-combined', 'nbuf_css_write_failed_combined' );
	}

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Login page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load current CSS value */
$nbuf_login_css = NBUF_Options::get( 'nbuf_login_page_css' );
if ( empty( $nbuf_login_css ) ) {
	$nbuf_login_css = NBUF_CSS_Manager::load_default_css( 'login-page' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_login' );

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
		<?php wp_nonce_field( 'nbuf_login_css_save', 'nbuf_login_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="login">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the custom login page. Changes will be minified and written to /assets/css/frontend/login-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="login_page_css"
				rows="30"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_login_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="login-page"
					data-target="login_page_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Login Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_login_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-login-wrapper</code> - Main container</li>
			<li><code>.nbuf-login-title</code> - Page title</li>
			<li><code>.nbuf-login-form</code> - Form element</li>
			<li><code>.nbuf-login-label</code> - Form labels</li>
			<li><code>.nbuf-login-input</code> - Input fields</li>
			<li><code>.nbuf-login-button</code> - Submit button</li>
			<li><code>.nbuf-login-error</code> - Error messages</li>
			<li><code>.nbuf-login-notice</code> - Success messages</li>
			<li><code>.nbuf-login-container</code> - Container when policy panel is shown</li>
			<li><code>.nbuf-policy-panel</code> - Policy panel container</li>
		</ul>
	</div>
</div>
