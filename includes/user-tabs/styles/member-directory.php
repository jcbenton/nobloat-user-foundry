<?php
/**
 * Member Directory Styles Tab
 *
 * CSS customization for member directory pages (grid and list views).
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle member directory styles form submission
 */
if ( isset( $_POST['nbuf_save_member_directory_css'] ) && check_admin_referer( 'nbuf_member_directory_css_save', 'nbuf_member_directory_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_member_directory_css = isset( $_POST['member_directory_custom_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['member_directory_custom_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_member_directory_custom_css', $nbuf_member_directory_css, false, 'css' );

	/* Write to disk (force=true to always regenerate on explicit save) */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_member_directory_css, 'member-directory', 'nbuf_css_write_failed_member_directory', true );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Member directory styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load CSS: check database first, fall back to disk default */
$nbuf_member_directory_css = NBUF_Options::get( 'nbuf_member_directory_custom_css', '' );
if ( empty( $nbuf_member_directory_css ) ) {
	$nbuf_member_directory_css = NBUF_CSS_Manager::load_default_css( 'member-directory' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_member_directory' );

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
		<?php wp_nonce_field( 'nbuf_member_directory_css_save', 'nbuf_member_directory_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="member-directory">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the CSS for member directory pages. The directory uses CSS custom properties (variables) for easy customization. Changes are saved to the database and written to disk for optimal performance.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="member_directory_custom_css"
				rows="25"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_member_directory_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button nbuf-reset-style-btn"
					data-template="member-directory"
					data-target="member_directory_custom_css"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Member Directory Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_member_directory_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Custom Properties (Variables)', 'nobloat-user-foundry' ); ?></h3>
		<p><?php esc_html_e( 'The member directory uses CSS custom properties for easy customization. Override these in the :root selector:', 'nobloat-user-foundry' ); ?></p>

		<h4><?php esc_html_e( 'Color Variables:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>--nbuf-dir-card-bg</code> - Card/item background color (default: #ffffff)</li>
			<li><code>--nbuf-dir-card-border</code> - Card/item border color (default: #e0e0e0)</li>
			<li><code>--nbuf-dir-card-hover-shadow</code> - Hover shadow color (default: rgba(0, 0, 0, 0.1))</li>
			<li><code>--nbuf-dir-primary-color</code> - Primary button/link color (default: #0073aa)</li>
			<li><code>--nbuf-dir-primary-hover</code> - Primary button/link hover color (default: #005a87)</li>
			<li><code>--nbuf-dir-name-color</code> - Member name color (default: #333333)</li>
			<li><code>--nbuf-dir-text-color</code> - General text color (default: #666666)</li>
			<li><code>--nbuf-dir-meta-color</code> - Meta text color (default: #777777)</li>
			<li><code>--nbuf-dir-control-bg</code> - Search/filter background (default: #f5f5f5)</li>
			<li><code>--nbuf-dir-input-border</code> - Input border color (default: #dddddd)</li>
			<li><code>--nbuf-dir-input-focus</code> - Input focus color (default: #0073aa)</li>
		</ul>

		<h4><?php esc_html_e( 'Size Variables:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>--nbuf-dir-avatar-size</code> - Avatar size in grid view (default: 96px)</li>
			<li><code>--nbuf-dir-avatar-size-small</code> - Avatar size in list view (default: 48px)</li>
			<li><code>--nbuf-dir-name-size</code> - Member name font size (default: 18px)</li>
			<li><code>--nbuf-dir-name-weight</code> - Member name font weight (default: 600)</li>
			<li><code>--nbuf-dir-meta-size</code> - Meta text font size (default: 13px)</li>
			<li><code>--nbuf-dir-card-padding</code> - Card padding (default: 20px)</li>
			<li><code>--nbuf-dir-card-gap</code> - Gap between cards (default: 25px)</li>
			<li><code>--nbuf-dir-card-radius</code> - Border radius for cards (default: 8px)</li>
			<li><code>--nbuf-dir-button-radius</code> - Border radius for buttons (default: 4px)</li>
			<li><code>--nbuf-dir-input-padding</code> - Input padding (default: 10px 15px)</li>
		</ul>

		<h4><?php esc_html_e( 'Example Customization:', 'nobloat-user-foundry' ); ?></h4>
		<pre class="code">:root {
	/* Larger avatars in grid view */
	--nbuf-dir-avatar-size: 120px;

	/* Darker colors */
	--nbuf-dir-primary-color: #2271b1;
	--nbuf-dir-primary-hover: #135e96;

	/* More spacing */
	--nbuf-dir-card-padding: 30px;
	--nbuf-dir-card-gap: 30px;

	/* Rounder corners */
	--nbuf-dir-card-radius: 12px;
}</pre>

		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-member-directory</code> - Main directory wrapper</li>
			<li><code>.nbuf-directory-controls</code> - Search and filter controls container</li>
			<li><code>.nbuf-directory-form</code> - Search/filter form</li>
			<li><code>.nbuf-directory-stats</code> - Member count display</li>
		</ul>

		<h4><?php esc_html_e( 'Search & Filter Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-directory-search</code> - Search box container</li>
			<li><code>.nbuf-search-input</code> - Search input field</li>
			<li><code>.nbuf-search-button</code> - Search submit button</li>
			<li><code>.nbuf-directory-filters</code> - Filter controls container</li>
			<li><code>.nbuf-filter-select</code> - Filter dropdown</li>
			<li><code>.nbuf-filter-button</code> - Filter submit button</li>
			<li><code>.nbuf-clear-filters</code> - Clear filters link</li>
		</ul>

		<h4><?php esc_html_e( 'Grid View Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-members-grid</code> - Grid container</li>
			<li><code>.nbuf-member-card</code> - Individual member card</li>
			<li><code>.nbuf-member-avatar</code> - Avatar container (grid)</li>
			<li><code>.nbuf-member-info</code> - Member information container</li>
			<li><code>.nbuf-member-name</code> - Member display name</li>
			<li><code>.nbuf-member-bio</code> - Member biography</li>
			<li><code>.nbuf-member-location</code> - Location display</li>
			<li><code>.nbuf-member-website</code> - Website link</li>
			<li><code>.nbuf-member-meta</code> - Meta information (join date, etc.)</li>
		</ul>

		<h4><?php esc_html_e( 'List View Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-members-list</code> - List container</li>
			<li><code>.nbuf-member-item</code> - Individual member list item</li>
			<li><code>.nbuf-member-avatar-small</code> - Avatar container (list)</li>
			<li><code>.nbuf-member-details</code> - Member details container</li>
			<li><code>.nbuf-member-meta-inline</code> - Inline meta information</li>
			<li><code>.nbuf-member-location-inline</code> - Location (list view)</li>
			<li><code>.nbuf-member-joined-inline</code> - Join date (list view)</li>
			<li><code>.nbuf-member-bio-inline</code> - Biography (list view)</li>
			<li><code>.nbuf-member-actions</code> - Action buttons container</li>
			<li><code>.nbuf-member-link</code> - Action link/button</li>
		</ul>

		<h4><?php esc_html_e( 'Pagination Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-directory-pagination</code> - Pagination container</li>
			<li><code>.nbuf-page-prev</code> - Previous page link</li>
			<li><code>.nbuf-page-next</code> - Next page link</li>
			<li><code>.nbuf-page-numbers</code> - Page numbers container</li>
			<li><code>.nbuf-page-number</code> - Individual page number</li>
			<li><code>.nbuf-page-number.current</code> - Current page (active state)</li>
		</ul>

		<h4><?php esc_html_e( 'Empty State Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-no-members</code> - Empty state container (no results)</li>
		</ul>

		<h4><?php esc_html_e( 'Data Attributes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>[data-view="grid"]</code> - Applied to .nbuf-member-directory for grid view</li>
			<li><code>[data-view="list"]</code> - Applied to .nbuf-member-directory for list view</li>
			<li><code>[data-user-id]</code> - Applied to cards/items with user ID</li>
		</ul>
	</div>
</div>

<style>
.nbuf-style-info pre.code {
	background: #f5f5f5;
	padding: 15px;
	border-left: 4px solid #0073aa;
	overflow-x: auto;
	font-size: 13px;
	line-height: 1.6;
}
</style>
