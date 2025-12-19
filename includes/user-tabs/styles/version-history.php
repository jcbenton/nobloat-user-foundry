<?php
/**
 * Version History Styles Tab
 *
 * CSS customization for version history viewer.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle reset to default request
 */
if ( isset( $_POST['nbuf_reset_version_history_css'] ) && check_admin_referer( 'nbuf_version_history_css_save', 'nbuf_version_history_css_nonce' ) ) {
	/* Load default from template */
	$nbuf_default_css = NBUF_CSS_Manager::load_default_css( 'version-history' );

	/* Save default to database */
	NBUF_Options::update( 'nbuf_version_history_custom_css', $nbuf_default_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_default_css, 'version-history', 'nbuf_css_write_failed_version_history' );

	/* Rebuild combined file if enabled */
	NBUF_CSS_Manager::rebuild_combined_css();

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Version History styles reset to default.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Version History styles reset to default in database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/**
 * Handle version history styles form submission
 */
if ( isset( $_POST['nbuf_save_version_history_css'] ) && check_admin_referer( 'nbuf_version_history_css_save', 'nbuf_version_history_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_version_history_css = isset( $_POST['version_history_custom_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['version_history_custom_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_version_history_custom_css', $nbuf_version_history_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_version_history_css, 'version-history', 'nbuf_css_write_failed_version_history' );

	/* Rebuild combined file if enabled */
	NBUF_CSS_Manager::rebuild_combined_css();

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Version History styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load CSS: check database first, fall back to disk default */
$nbuf_version_history_css = NBUF_Options::get( 'nbuf_version_history_custom_css', '' );
if ( empty( $nbuf_version_history_css ) ) {
	$nbuf_version_history_css = NBUF_CSS_Manager::load_default_css( 'version-history' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_version_history' );

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
		<?php wp_nonce_field( 'nbuf_version_history_css_save', 'nbuf_version_history_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="version-history">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the CSS for the version history viewer. Changes are saved to the database. Use "Reset to Default" to restore the original styling from the plugin.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="version_history_custom_css"
				rows="25"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_version_history_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="version-history"
					data-target="version_history_custom_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Version History Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_version_history_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-version-history-viewer</code> - Main container</li>
			<li><code>.nbuf-vh-header</code> - Header section</li>
			<li><code>.nbuf-vh-loading</code> - Loading state</li>
			<li><code>.nbuf-vh-timeline</code> - Timeline container</li>
			<li><code>.nbuf-vh-empty</code> - Empty state</li>
			<li><code>.nbuf-vh-pagination</code> - Pagination controls</li>
		</ul>

		<h4><?php esc_html_e( 'Timeline Item Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-vh-item</code> - Timeline item</li>
			<li><code>.nbuf-vh-item-icon</code> - Item icon</li>
			<li><code>.nbuf-vh-item-content</code> - Item content area</li>
			<li><code>.nbuf-vh-item-header</code> - Item header</li>
			<li><code>.nbuf-vh-item-meta</code> - Meta information</li>
			<li><code>.nbuf-vh-item-type</code> - Change type badge</li>
			<li><code>.nbuf-vh-item-details</code> - Details section</li>
			<li><code>.nbuf-vh-item-actions</code> - Action buttons</li>
		</ul>

		<h4><?php esc_html_e( 'Modal Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-vh-diff-modal</code> - Modal container</li>
			<li><code>.nbuf-vh-diff-overlay</code> - Modal overlay</li>
			<li><code>.nbuf-vh-diff-content</code> - Modal content</li>
			<li><code>.nbuf-vh-diff-table</code> - Diff comparison table</li>
		</ul>

		<h4><?php esc_html_e( 'Change Type Modifiers:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-vh-type-create</code> - Created record</li>
			<li><code>.nbuf-vh-type-update</code> - Updated record</li>
			<li><code>.nbuf-vh-type-delete</code> - Deleted record</li>
			<li><code>.nbuf-vh-type-revert</code> - Reverted to previous</li>
		</ul>
	</div>
</div>
