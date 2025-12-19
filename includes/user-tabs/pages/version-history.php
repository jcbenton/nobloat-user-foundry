<?php
/**
 * Version History Page Template Tab
 *
 * Manage the HTML template for the user version history viewer.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template (checks DB first, falls back to file) */
$nbuf_history_template = NBUF_Template_Manager::load_template( 'version-history-viewer-html' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the version history viewer that displays user profile change history. This template uses Mustache-style syntax for dynamic content.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="pages">
		<input type="hidden" name="nbuf_active_subtab" value="version-history">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Version History Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_version_history_viewer_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_history_template ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {header_title}, {header_description}, {context}, {user_id}, {page_info}, {prev_button}, {next_button}, {empty_title}, {empty_description}, {close_button}, {compare_button}, {revert_button}, {view_snapshot_button}, {diff_modal_title}, {comparing_text}, {loading_text}, {fields_changed_label}, {ip_address_label}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="version-history-viewer-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Version History Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-version-history</code> - Main container</li>
			<li><code>.nbuf-vh-header</code> - Header section</li>
			<li><code>.nbuf-vh-timeline</code> - Timeline container</li>
			<li><code>.nbuf-vh-entry</code> - Individual history entry</li>
			<li><code>.nbuf-vh-entry-header</code> - Entry header with date/time</li>
			<li><code>.nbuf-vh-entry-content</code> - Entry content area</li>
			<li><code>.nbuf-vh-field-change</code> - Individual field change</li>
			<li><code>.nbuf-vh-old-value</code> - Previous value display</li>
			<li><code>.nbuf-vh-new-value</code> - New value display</li>
			<li><code>.nbuf-vh-pagination</code> - Pagination controls</li>
			<li><code>.nbuf-vh-modal</code> - Diff comparison modal</li>
			<li><code>.nbuf-vh-empty</code> - Empty state message</li>
		</ul>

		<h3><?php esc_html_e( 'Mustache Syntax', 'nobloat-user-foundry' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'This template uses Mustache-style syntax for dynamic entry rendering:', 'nobloat-user-foundry' ); ?>
		</p>
		<ul>
			<li><code>{{variable}}</code> - <?php esc_html_e( 'Output variable value', 'nobloat-user-foundry' ); ?></li>
			<li><code>{{#condition}}...{{/condition}}</code> - <?php esc_html_e( 'Conditional block', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>
</div>
