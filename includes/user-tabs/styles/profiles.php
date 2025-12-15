<?php
/**
 * Profile Page Styles Tab
 *
 * CSS customization for public profile pages.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle profile page styles form submission
 */
if ( isset( $_POST['nbuf_save_profile_css'] ) && check_admin_referer( 'nbuf_profile_css_save', 'nbuf_profile_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_profile_css = isset( $_POST['profile_custom_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['profile_custom_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_profile_custom_css', $nbuf_profile_css, false, 'css' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profile page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Load current CSS value */
$nbuf_profile_css = NBUF_Options::get( 'nbuf_profile_custom_css', '' );

?>

<div class="nbuf-styles-tab">

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_profile_css_save', 'nbuf_profile_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="profiles">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Add custom CSS for public profile pages. The plugin provides minimal default styles with extensive CSS classes for customization.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="profile_custom_css"
				rows="25"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_profile_css ); ?></textarea>

			<p>
				<button
					type="button"
					class="button"
					onclick="if(confirm('Clear all custom profile CSS?')) { document.querySelector('textarea[name=profile_custom_css]').value = ''; }"
				>
					<?php esc_html_e( 'Clear CSS', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Profile Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_profile_css' ); ?>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Profile Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-profile-page</code> - Main profile wrapper</li>
			<li><code>.nbuf-profile-header</code> - Header section with cover photo</li>
			<li><code>.nbuf-profile-cover</code> - Cover photo container</li>
			<li><code>.nbuf-profile-avatar-wrap</code> - Avatar wrapper</li>
			<li><code>.nbuf-profile-avatar</code> - Avatar image</li>
			<li><code>.nbuf-profile-info</code> - User info section</li>
			<li><code>.nbuf-profile-name</code> - User display name</li>
			<li><code>.nbuf-profile-username</code> - Username</li>
			<li><code>.nbuf-profile-bio</code> - Bio/description</li>
			<li><code>.nbuf-profile-meta</code> - Meta information (joined date, etc.)</li>
			<li><code>.nbuf-profile-content</code> - Main content area</li>
			<li><code>.nbuf-profile-actions</code> - Action buttons</li>
		</ul>

		<h4><?php esc_html_e( 'Avatar Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-avatar</code> - Avatar image (replaces WordPress default)</li>
			<li><code>.nbuf-svg-avatar</code> - SVG initials avatar</li>
			<li><code>.nbuf-avatar-small</code> - Small size (32px)</li>
			<li><code>.nbuf-avatar-medium</code> - Medium size (64px)</li>
			<li><code>.nbuf-avatar-large</code> - Large size (96px)</li>
			<li><code>.nbuf-avatar-xl</code> - Extra large size (150px)</li>
		</ul>

		<h4><?php esc_html_e( 'Example Custom CSS:', 'nobloat-user-foundry' ); ?></h4>
		<pre style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">/* Business-like styling */
.nbuf-profile-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.nbuf-profile-cover {
    height: 300px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.nbuf-profile-name {
    font-size: 2rem;
    font-weight: 700;
    color: #1a202c;
}

.nbuf-profile-bio {
    color: #4a5568;
    line-height: 1.6;
}</pre>
	</div>
</div>
