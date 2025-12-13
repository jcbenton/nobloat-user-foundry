<?php
/**
 * Profile & Cover Photos Settings
 *
 * Settings for profile photos, cover photos, public profiles, and Gravatar integration.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$profiles_enabled   = NBUF_Options::get( 'nbuf_enable_profiles', false );
$public_profiles    = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
$gravatar_enabled   = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
$profile_page_slug  = NBUF_Options::get( 'nbuf_profile_page_slug', 'nobloat-profile' );
$default_privacy    = NBUF_Options::get( 'nbuf_profile_default_privacy', 'private' );
$allow_cover_photos = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );
$max_photo_size     = NBUF_Options::get( 'nbuf_profile_max_photo_size', 5 );
$max_cover_size     = NBUF_Options::get( 'nbuf_profile_max_cover_size', 10 );

?>

<div class="nbuf-profiles-tab">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="users">
		<input type="hidden" name="nbuf_active_subtab" value="profiles">
		<!-- Declare checkboxes on this form for proper unchecked handling -->
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_enable_profiles">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_enable_public_profiles">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_profile_allow_cover_photos">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_profile_enable_gravatar">

		<h2><?php esc_html_e( 'Profile & Cover Photos', 'nobloat-user-foundry' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure profile photos, cover photos, and public profile pages. All features are OFF by default for privacy and performance.', 'nobloat-user-foundry' ); ?>
		</p>

		<table class="form-table">
			<!-- Master Toggle -->
			<tr>
				<th><?php esc_html_e( 'Enable Profile System', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_enable_profiles" value="0">
					<label>
						<input type="checkbox" name="nbuf_enable_profiles" value="1" <?php checked( $profiles_enabled, true ); ?>>
						<?php esc_html_e( 'Enable profile photos and profile system', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Master toggle for all profile features. When disabled, users will see default avatars only.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Public Profiles -->
			<tr>
				<th><?php esc_html_e( 'Public Profiles', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_enable_public_profiles" value="0">
					<label>
						<input type="checkbox" name="nbuf_enable_public_profiles" value="1" <?php checked( $public_profiles, true ); ?>>
						<?php esc_html_e( 'Enable public profile pages', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow users to have public profile pages (e.g., site.com/profile/username). Users can control their own privacy settings.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Profile Page Slug -->
			<tr>
				<th><?php esc_html_e( 'Profile Page Slug', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="text" name="nbuf_profile_page_slug" value="<?php echo esc_attr( $profile_page_slug ); ?>" class="regular-text">
					<p class="description">
						<?php
						printf(
						/* translators: %s: Example profile URL */
							esc_html__( 'Base URL for profile pages. Example: %s', 'nobloat-user-foundry' ),
							'<code>' . esc_html( home_url( '/' . $profile_page_slug . '/username' ) ) . '</code>'
						);
						?>
					</p>
					<p class="description" style="color: #d63638; font-weight: 500;">
						<?php esc_html_e( '⚠️ Important: This slug cannot conflict with existing page slugs. If a page with this slug already exists, the profile URL will override it. Choose a unique slug to avoid conflicts.', 'nobloat-user-foundry' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Rewrite rules are automatically flushed when you change this setting.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Privacy -->
			<tr>
				<th><?php esc_html_e( 'Default Profile Privacy', 'nobloat-user-foundry' ); ?></th>
				<td>
					<select name="nbuf_profile_default_privacy" class="regular-text">
						<option value="private" <?php selected( $default_privacy, 'private' ); ?>><?php esc_html_e( 'Private (Hidden)', 'nobloat-user-foundry' ); ?></option>
						<option value="members_only" <?php selected( $default_privacy, 'members_only' ); ?>><?php esc_html_e( 'Members Only', 'nobloat-user-foundry' ); ?></option>
						<option value="public" <?php selected( $default_privacy, 'public' ); ?>><?php esc_html_e( 'Public', 'nobloat-user-foundry' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default privacy setting for new user profiles. Users can change this in their account settings.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Cover Photos -->
			<tr>
				<th><?php esc_html_e( 'Cover Photos', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_profile_allow_cover_photos" value="0">
					<label>
						<input type="checkbox" name="nbuf_profile_allow_cover_photos" value="1" <?php checked( $allow_cover_photos, true ); ?>>
						<?php esc_html_e( 'Allow users to upload cover photos', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Cover photos appear at the top of profile pages. Disable to keep profiles minimal.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Gravatar Integration -->
			<tr>
				<th><?php esc_html_e( 'Gravatar Integration', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_profile_enable_gravatar" value="0">
					<label>
						<input type="checkbox" name="nbuf_profile_enable_gravatar" value="1" <?php checked( $gravatar_enabled, true ); ?>>
						<?php esc_html_e( 'Allow users to opt-in to Gravatar', 'nobloat-user-foundry' ); ?>
					</label>
					<div class="nbuf-gravatar-warning" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 10px;">
						<strong style="color: #856404;"><?php esc_html_e( '⚠️ Privacy Warning:', 'nobloat-user-foundry' ); ?></strong>
						<p style="margin: 5px 0 0 0; color: #856404;">
							<?php esc_html_e( 'Gravatar requires HTTP API calls to gravatar.com on every page load, which may expose user IP addresses and email hashes to third parties. This can raise GDPR compliance concerns. By default, users will see clean SVG initials avatars with no external calls.', 'nobloat-user-foundry' ); ?>
						</p>
						<ul style="margin: 10px 0 0 20px; color: #856404;">
							<li><?php esc_html_e( 'Default: SVG initials avatar (fast, private, no external calls)', 'nobloat-user-foundry' ); ?></li>
							<li><?php esc_html_e( 'Gravatar: Users can opt-in individually (requires external API call)', 'nobloat-user-foundry' ); ?></li>
							<li><?php esc_html_e( 'Custom upload: Users can upload their own photos (stored locally)', 'nobloat-user-foundry' ); ?></li>
						</ul>
					</div>
				</td>
			</tr>

			<!-- File Size Limits -->
			<tr>
				<th><?php esc_html_e( 'Profile Photo Size Limit', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="number" name="nbuf_profile_max_photo_size" value="<?php echo esc_attr( $max_photo_size ); ?>" min="1" max="50" class="small-text"> MB
					<p class="description">
						<?php esc_html_e( 'Maximum file size for profile photos (recommended: 5MB or less).', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Cover Photo Size Limit', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="number" name="nbuf_profile_max_cover_size" value="<?php echo esc_attr( $max_cover_size ); ?>" min="1" max="50" class="small-text"> MB
					<p class="description">
						<?php esc_html_e( 'Maximum file size for cover photos (recommended: 10MB or less).', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Profile Settings', 'nobloat-user-foundry' ) ); ?>
	</form>

	<!-- Helper Information -->
	<div class="nbuf-profile-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'Profile System Overview', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Privacy-First Design', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Profiles are OFF by default', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'SVG initials avatars by default (no external calls)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Gravatar is opt-in only (with privacy warning)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Users control their profile privacy (private/members-only/public)', 'nobloat-user-foundry' ); ?></li>
		</ul>

		<h4 style="margin-top: 20px;"><?php esc_html_e( 'Photo Options', 'nobloat-user-foundry' ); ?></h4>
		<ol>
			<li><strong><?php esc_html_e( 'SVG Initials (Default):', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Clean, colorful initials generated from user name. Fast, no external calls, privacy-friendly.', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Custom Upload:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Users upload their own photos. Stored locally in WordPress media library.', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Gravatar (Optional):', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Users can opt-in to Gravatar if enabled by admin. Requires external API call with privacy implications.', 'nobloat-user-foundry' ); ?></li>
		</ol>

		<h4 style="margin-top: 20px;"><?php esc_html_e( 'Design Philosophy', 'nobloat-user-foundry' ); ?></h4>
		<p><?php esc_html_e( 'The profile system uses minimal default CSS with a sleek, business-like design. Extensive CSS classes are provided for customization. Use the Custom CSS field above to style profiles to match your site design.', 'nobloat-user-foundry' ); ?></p>
	</div>
</div>
