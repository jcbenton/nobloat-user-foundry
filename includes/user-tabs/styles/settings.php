<?php
/**
 * Styles Settings Tab
 *
 * CSS optimization settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle CSS settings form submission
 */
if ( isset( $_POST['nbuf_save_css_settings'] ) && check_admin_referer( 'nbuf_css_settings_save', 'nbuf_css_settings_nonce' ) ) {
	/* Get and save CSS optimization options */
	$css_load_on_pages = isset( $_POST['nbuf_css_load_on_pages'] ) ? 1 : 0;
	$css_use_minified  = isset( $_POST['nbuf_css_use_minified'] ) ? 1 : 0;
	$css_combine_files = isset( $_POST['nbuf_css_combine_files'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_css_load_on_pages', $css_load_on_pages, true, 'settings' );
	NBUF_Options::update( 'nbuf_css_use_minified', $css_use_minified, true, 'settings' );
	NBUF_Options::update( 'nbuf_css_combine_files', $css_combine_files, true, 'settings' );

	/* If combine files is enabled, regenerate the combined file */
	if ( $css_combine_files ) {
		$reset_css        = NBUF_Options::get( 'nbuf_reset_page_css' );
		$login_css        = NBUF_Options::get( 'nbuf_login_page_css' );
		$registration_css = NBUF_Options::get( 'nbuf_registration_page_css' );
		$account_css      = NBUF_Options::get( 'nbuf_account_page_css' );

		/* Load defaults if empty */
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

		$combined_css = $reset_css . "\n\n" . $login_css . "\n\n" . $registration_css . "\n\n" . $account_css;
		NBUF_CSS_Manager::save_css_to_disk( $combined_css, 'nobloat-combined', 'nbuf_css_write_failed_combined' );
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'CSS settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* CSS optimization settings */
$css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
$css_use_minified  = NBUF_Options::get( 'nbuf_css_use_minified', true );
$css_combine_files = NBUF_Options::get( 'nbuf_css_combine_files', true );

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
		<?php wp_nonce_field( 'nbuf_css_settings_save', 'nbuf_css_settings_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="settings">

		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Load CSS on NoBloat Pages', 'nobloat-user-foundry' ); ?>
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

		<?php submit_button( __( 'Save Settings', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_css_settings' ); ?>
	</form>
</div>
