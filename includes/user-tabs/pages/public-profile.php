<?php
/**
 * Public Profile Page Template Tab
 *
 * Manage the HTML template for public user profile pages.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template (checks DB first, falls back to file) */
$nbuf_profile_template = NBUF_Template_Manager::load_template( 'public-profile-html' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for public user profile pages. This template renders the profile content within your theme\'s header and footer.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="pages">
		<input type="hidden" name="nbuf_active_subtab" value="public-profile">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Public Profile Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_public_profile_template"
				rows="30"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_profile_template ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {display_name}, {username}, {profile_photo}, {cover_photo_html}, {joined_text}, {profile_fields_html}, {custom_content}, {edit_profile_button}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="public-profile-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Profile Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-profile-page</code> - Main profile wrapper</li>
			<li><code>.nbuf-profile-header</code> - Header section with cover photo</li>
			<li><code>.nbuf-profile-cover</code> - Cover photo container</li>
			<li><code>.nbuf-profile-avatar-wrap</code> - Avatar wrapper</li>
			<li><code>.nbuf-profile-avatar</code> - Avatar image</li>
			<li><code>.nbuf-profile-content</code> - Main content area</li>
			<li><code>.nbuf-profile-info</code> - User info section</li>
			<li><code>.nbuf-profile-name</code> - User display name</li>
			<li><code>.nbuf-profile-username</code> - Username</li>
			<li><code>.nbuf-profile-bio</code> - Bio/description</li>
			<li><code>.nbuf-profile-meta</code> - Meta information container</li>
			<li><code>.nbuf-profile-fields</code> - Custom fields container</li>
			<li><code>.nbuf-profile-field</code> - Individual field wrapper</li>
		</ul>
	</div>
</div>
