<?php
/**
 * Styles Tab
 *
 * CSS customization interface with live file generation
 * and token-based write failure detection.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle reset page styles form submission
 *
 * Saves CSS to database and attempts to write to disk.
 */
if ( isset( $_POST['nbuf_save_styles'] ) && check_admin_referer( 'nbuf_styles_save', 'nbuf_styles_nonce' ) ) {
	/*
	* Get and sanitize CSS inputs.
	*/
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$reset_css        = isset( $_POST['reset_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['reset_page_css'] ) ) : '';
	$login_css        = isset( $_POST['login_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['login_page_css'] ) ) : '';
	$registration_css = isset( $_POST['registration_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['registration_page_css'] ) ) : '';
	$account_css      = isset( $_POST['account_page_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['account_page_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Get and save CSS optimization options */
	$css_load_on_pages = isset( $_POST['nbuf_css_load_on_pages'] ) ? 1 : 0;
	$css_use_minified  = isset( $_POST['nbuf_css_use_minified'] ) ? 1 : 0;
	$css_combine_files = isset( $_POST['nbuf_css_combine_files'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_css_load_on_pages', $css_load_on_pages, true, 'settings' );
	NBUF_Options::update( 'nbuf_css_use_minified', $css_use_minified, true, 'settings' );
	NBUF_Options::update( 'nbuf_css_combine_files', $css_combine_files, true, 'settings' );

	/* Save to database */
	NBUF_Options::update( 'nbuf_reset_page_css', $reset_css, false, 'css' );
	NBUF_Options::update( 'nbuf_login_page_css', $login_css, false, 'css' );
	NBUF_Options::update( 'nbuf_registration_page_css', $registration_css, false, 'css' );
	NBUF_Options::update( 'nbuf_account_page_css', $account_css, false, 'css' );

	/* Try to write to disk with minification */
	$reset_success        = NBUF_CSS_Manager::save_css_to_disk( $reset_css, 'reset-page', 'nbuf_css_write_failed_reset' );
	$login_success        = NBUF_CSS_Manager::save_css_to_disk( $login_css, 'login-page', 'nbuf_css_write_failed_login' );
	$registration_success = NBUF_CSS_Manager::save_css_to_disk( $registration_css, 'registration-page', 'nbuf_css_write_failed_registration' );
	$account_success      = NBUF_CSS_Manager::save_css_to_disk( $account_css, 'account-page', 'nbuf_css_write_failed_account' );

	/* Create combined CSS file if option is enabled */
	$combined_success = true;
	if ( $css_combine_files ) {
		$combined_css     = $reset_css . "\n\n" . $login_css . "\n\n" . $registration_css . "\n\n" . $account_css;
		$combined_success = NBUF_CSS_Manager::save_css_to_disk( $combined_css, 'nobloat-combined', 'nbuf_css_write_failed_combined' );
	}

	/* Show appropriate message */
	if ( $reset_success && $login_success && $registration_success && $account_success && $combined_success ) {
		$message = __( 'Styles saved successfully and written to disk.', 'nobloat-user-foundry' );
		if ( $css_combine_files ) {
			$message .= ' ' . __( 'Combined CSS file created.', 'nobloat-user-foundry' );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	} elseif ( ! $reset_success || ! $login_success || ! $registration_success || ! $account_success || ! $combined_success ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Styles will be loaded from database (slightly slower). Check file permissions on /assets/css/frontend/ directory.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/*
==========================================================
	LOAD CURRENT CSS VALUES
	==========================================================
 */
$reset_css        = NBUF_Options::get( 'nbuf_reset_page_css' );
$login_css        = NBUF_Options::get( 'nbuf_login_page_css' );
$registration_css = NBUF_Options::get( 'nbuf_registration_page_css' );
$account_css      = NBUF_Options::get( 'nbuf_account_page_css' );

/* CSS optimization settings */
$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
$css_use_minified  = NBUF_Options::get( 'nbuf_css_use_minified', true );
$css_combine_files = NBUF_Options::get( 'nbuf_css_combine_files', true );

/* If empty, load from default templates */
if ( empty( $reset_css ) ) {
	$reset_css = NBUF_CSS_Manager::load_default_css( 'reset-page' );
}
if ( empty( $login_css ) ) {
	$login_css = NBUF_CSS_Manager::load_default_css( 'login-page' );
}
if ( empty( $registration_css ) ) {
	$registration_css = NBUF_CSS_Manager::load_default_css( 'registration-page' );
}
if ( empty( $account_css ) ) {
	$account_css = NBUF_CSS_Manager::load_default_css( 'account-page' );
}

/*
==========================================================
	CHECK FOR WRITE FAILURES
	==========================================================
 */
$has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_reset' ) ||
					NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_login' ) ||
					NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_registration' ) ||
					NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_account' ) ||
					NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_combined' );

?>

<div class="nbuf-styles-tab">

	<?php if ( $has_write_failure ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'File Write Permission Issue:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Unable to write CSS files to disk. Styles are being loaded from database (slower performance). Please check file permissions on the /assets/css/frontend/ directory.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_styles_save', 'nbuf_styles_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
	<input type="hidden" name="nbuf_active_subtab" value="reset">

		<!-- =================================================== -->
		<!-- CSS OPTIMIZATION OPTIONS -->
		<!-- =================================================== -->
		<div class="nbuf-style-section" style="background: #f9f9f9; border-left: 4px solid #0073aa;">
			<h2><?php esc_html_e( 'CSS Optimization', 'nobloat-user-foundry' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Load CSS on NoBloat Forms and Pages', 'nobloat-user-foundry' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="nbuf_css_load_on_pages" value="1" <?php checked( $css_load_on_pages, true ); ?>>
							<?php esc_html_e( 'Load CSS on plugin pages only', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, CSS files are loaded only on NoBloat-specific pages (verification, reset, login, registration, account). When disabled, CSS will not load at all. Plugin CSS never loads globally on other pages. Recommended: Enabled', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Use Minified CSS Files', 'nobloat-user-foundry' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="nbuf_css_use_minified" value="1" <?php checked( $css_use_minified, true ); ?>>
							<?php esc_html_e( 'Load minified CSS files', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, loads minified .min.css files for better performance. When disabled, loads unminified .css files (useful for debugging). Recommended: Enabled', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Combine CSS Files', 'nobloat-user-foundry' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="nbuf_css_combine_files" value="1" <?php checked( $css_combine_files, true ); ?>>
							<?php esc_html_e( 'Combine CSS into single file', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, all plugin CSS is combined into a single file (nobloat-combined.css or nobloat-combined.min.css) reducing HTTP requests. Individual files are still saved for reference. Recommended: Enabled', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- =================================================== -->
		<!-- PASSWORD RESET PAGE CSS -->
		<!-- =================================================== -->
		<div class="nbuf-style-section">
			<h2><?php esc_html_e( 'Password Reset Page CSS', 'nobloat-user-foundry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the password reset page. Changes will be minified and written to /assets/css/frontend/reset-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="reset_page_css"
				rows="20"
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

		<!-- =================================================== -->
		<!-- LOGIN PAGE CSS -->
		<!-- =================================================== -->
		<div class="nbuf-style-section">
			<h2><?php esc_html_e( 'Login Page CSS', 'nobloat-user-foundry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the custom login page. Changes will be minified and written to /assets/css/frontend/login-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="login_page_css"
				rows="20"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $login_css ); ?></textarea>

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

		<!-- =================================================== -->
		<!-- REGISTRATION PAGE CSS -->
		<!-- =================================================== -->
		<div class="nbuf-style-section">
			<h2><?php esc_html_e( 'Registration Page CSS', 'nobloat-user-foundry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the user registration page. Changes will be minified and written to /assets/css/frontend/registration-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="registration_page_css"
				rows="20"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $registration_css ); ?></textarea>

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

		<!-- =================================================== -->
		<!-- ACCOUNT PAGE CSS -->
		<!-- =================================================== -->
		<div class="nbuf-style-section">
			<h2><?php esc_html_e( 'Account Page CSS', 'nobloat-user-foundry' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Customize the styling for the user account management page. Changes will be minified and written to /assets/css/frontend/account-page-live.min.css for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="account_page_css"
				rows="20"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $account_css ); ?></textarea>

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

		<?php submit_button( __( 'Save Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_styles' ); ?>
	</form>

	<!-- =================================================== -->
	<!-- HELPER TEXT -->
	<!-- =================================================== -->
	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Password Reset Page Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-reset-wrapper</code> - Main container</li>
			<li><code>.nbuf-reset-title</code> - Page title</li>
			<li><code>.nbuf-reset-form</code> - Form element</li>
			<li><code>.nbuf-reset-label</code> - Form labels</li>
			<li><code>.nbuf-reset-input</code> - Input fields</li>
			<li><code>.nbuf-reset-button</code> - Submit button</li>
			<li><code>.nbuf-reset-error</code> - Error messages</li>
			<li><code>.nbuf-reset-notice</code> - Success messages</li>
		</ul>

		<h4><?php esc_html_e( 'Login Page Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-login-wrapper</code> - Main container</li>
			<li><code>.nbuf-login-title</code> - Page title</li>
			<li><code>.nbuf-login-form</code> - Form element</li>
			<li><code>.nbuf-login-label</code> - Form labels</li>
			<li><code>.nbuf-login-input</code> - Input fields</li>
			<li><code>.nbuf-login-button</code> - Submit button</li>
			<li><code>.nbuf-login-error</code> - Error messages</li>
			<li><code>.nbuf-login-notice</code> - Success messages</li>
		</ul>
	</div>
</div>


