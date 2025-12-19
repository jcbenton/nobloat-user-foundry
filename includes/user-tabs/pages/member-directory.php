<?php
/**
 * Member Directory Page Template Tab
 *
 * Manage the HTML templates for member directory (grid and list views).
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates (checks DB first, falls back to file) */
$nbuf_directory_grid = NBUF_Template_Manager::load_template( 'member-directory-html' );
$nbuf_directory_list = NBUF_Template_Manager::load_template( 'member-directory-list-html' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML templates for the member directory. Two layouts are available: grid view (default) and list view.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="pages">
		<input type="hidden" name="nbuf_active_subtab" value="member-directory">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Grid View Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_member_directory_template"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_directory_grid ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {directory_controls}, {members_content}, {pagination}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="member-directory-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section" style="margin-top: 2rem;">
			<h3><?php esc_html_e( 'List View Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_member_directory_list_template"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_directory_list ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {directory_controls}, {members_content}, {pagination}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="member-directory-list-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Directory Templates', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-member-directory</code> - Main directory wrapper</li>
			<li><code>.nbuf-directory-controls</code> - Search and filter controls</li>
			<li><code>.nbuf-directory-stats</code> - Member count display</li>
		</ul>

		<h4><?php esc_html_e( 'Grid View Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-members-grid</code> - Grid container</li>
			<li><code>.nbuf-member-card</code> - Individual member card</li>
			<li><code>.nbuf-member-avatar</code> - Avatar container</li>
			<li><code>.nbuf-member-info</code> - Member info container</li>
			<li><code>.nbuf-member-name</code> - Member name</li>
		</ul>

		<h4><?php esc_html_e( 'List View Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-members-list</code> - List container</li>
			<li><code>.nbuf-member-item</code> - Individual member row</li>
			<li><code>.nbuf-member-details</code> - Details container</li>
		</ul>

		<h4><?php esc_html_e( 'Pagination Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-directory-pagination</code> - Pagination container</li>
			<li><code>.nbuf-page-number</code> - Page number link</li>
			<li><code>.nbuf-page-number.current</code> - Current page</li>
		</ul>
	</div>
</div>
