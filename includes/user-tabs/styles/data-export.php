<?php
/**
 * Data Export (GDPR) Styles Tab
 *
 * CSS customization for GDPR data export page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle reset to default request
 */
if ( isset( $_POST['nbuf_reset_data_export_css'] ) && check_admin_referer( 'nbuf_data_export_css_save', 'nbuf_data_export_css_nonce' ) ) {
	/* Load default from template */
	$nbuf_default_css = NBUF_CSS_Manager::load_default_css( 'data-export' );

	/* Save default to database */
	NBUF_Options::update( 'nbuf_data_export_custom_css', $nbuf_default_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_default_css, 'data-export', 'nbuf_css_write_failed_data_export' );


	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Data Export styles reset to default.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Data Export styles reset to default in database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/**
 * Handle data export styles form submission
 */
if ( isset( $_POST['nbuf_save_data_export_css'] ) && check_admin_referer( 'nbuf_data_export_css_save', 'nbuf_data_export_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_data_export_css = isset( $_POST['data_export_custom_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['data_export_custom_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_data_export_custom_css', $nbuf_data_export_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_data_export_css, 'data-export', 'nbuf_css_write_failed_data_export' );


	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Data Export styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load CSS: check database first, fall back to disk default */
$nbuf_data_export_css = NBUF_Options::get( 'nbuf_data_export_custom_css', '' );
if ( empty( $nbuf_data_export_css ) ) {
	$nbuf_data_export_css = NBUF_CSS_Manager::load_default_css( 'data-export' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_data_export' );

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
		<?php wp_nonce_field( 'nbuf_data_export_css_save', 'nbuf_data_export_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="data-export">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the CSS for the GDPR data export page. Changes are saved to the database. Use "Reset to Default" to restore the original styling from the plugin.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="data_export_custom_css"
				rows="25"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_data_export_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="data-export"
					data-target="data_export_custom_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Data Export Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_data_export_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-gdpr-export-container</code> - Main container</li>
			<li><code>.nbuf-export-content</code> - Content section</li>
			<li><code>.nbuf-export-includes</code> - Includes list</li>
			<li><code>.nbuf-export-info</code> - Info box</li>
			<li><code>.nbuf-export-actions</code> - Actions area</li>
		</ul>

		<h4><?php esc_html_e( 'Button Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-export-button</code> - Export button</li>
		</ul>

		<h4><?php esc_html_e( 'Notice Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-export-notice</code> - Base notice</li>
			<li><code>.nbuf-export-notice.success</code> - Success message</li>
			<li><code>.nbuf-export-notice.error</code> - Error message</li>
			<li><code>.nbuf-export-notice.info</code> - Info message</li>
			<li><code>.nbuf-export-notice.warning</code> - Warning message</li>
		</ul>

		<h4><?php esc_html_e( 'History Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-export-history</code> - History section</li>
			<li><code>.nbuf-export-history-table</code> - History table</li>
			<li><code>.nbuf-export-history-empty</code> - Empty state</li>
		</ul>

		<h4><?php esc_html_e( 'Modal Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-export-modal-overlay</code> - Modal overlay</li>
			<li><code>.nbuf-export-modal</code> - Modal container</li>
			<li><code>.nbuf-export-modal-actions</code> - Modal buttons</li>
		</ul>
	</div>
</div>
