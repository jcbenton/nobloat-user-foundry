<?php
/**
 * Data Export Page Template Tab
 *
 * Manage the HTML template for the GDPR data export page.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template (checks DB first, falls back to file) */
$nbuf_export_template = NBUF_Template_Manager::load_template( 'account-data-export-html' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the GDPR data export page where users can download their personal data.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="pages">
		<input type="hidden" name="nbuf_active_subtab" value="data-export">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Data Export Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_account_data_export_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_export_template ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {section_title}, {gdpr_description}, {export_includes_title}, {export_includes_list}, {format_label}, {format_value}, {estimated_size_label}, {estimated_size}, {export_button}, {cancel_button}, {history_title}, {export_history}, {modal_title}, {modal_description}, {password_label}, {confirm_button}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="account-data-export-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Data Export Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-gdpr-export</code> - Main export container</li>
			<li><code>.nbuf-export-section</code> - Section wrapper</li>
			<li><code>.nbuf-export-includes</code> - Data includes list</li>
			<li><code>.nbuf-export-format</code> - Format selection</li>
			<li><code>.nbuf-export-size</code> - Size estimate display</li>
			<li><code>.nbuf-export-button</code> - Export button</li>
			<li><code>.nbuf-export-history</code> - Export history table</li>
			<li><code>.nbuf-export-modal</code> - Password confirmation modal</li>
			<li><code>.nbuf-modal-overlay</code> - Modal background overlay</li>
			<li><code>.nbuf-modal-content</code> - Modal content box</li>
		</ul>
	</div>
</div>
