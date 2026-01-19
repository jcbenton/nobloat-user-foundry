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
$nbuf_profiles_enabled   = NBUF_Options::get( 'nbuf_enable_profiles', false );
$nbuf_public_profiles    = NBUF_Options::get( 'nbuf_enable_public_profiles', false );
$nbuf_gravatar_enabled   = NBUF_Options::get( 'nbuf_profile_enable_gravatar', false );
$nbuf_default_privacy    = NBUF_Options::get( 'nbuf_profile_default_privacy', 'private' );
$nbuf_allow_cover_photos = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );

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
						<input type="checkbox" name="nbuf_enable_profiles" value="1" <?php checked( $nbuf_profiles_enabled, true ); ?>>
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
						<input type="checkbox" name="nbuf_enable_public_profiles" value="1" <?php checked( $nbuf_public_profiles, true ); ?>>
						<?php esc_html_e( 'Enable public profile pages', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow users to have public profile pages (e.g., site.com/profile/username). Users can control their own privacy settings.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Default Privacy -->
			<tr>
				<th><?php esc_html_e( 'Default Profile Privacy', 'nobloat-user-foundry' ); ?></th>
				<td>
					<select name="nbuf_profile_default_privacy" class="regular-text">
						<option value="private" <?php selected( $nbuf_default_privacy, 'private' ); ?>><?php esc_html_e( 'Private (Hidden)', 'nobloat-user-foundry' ); ?></option>
						<option value="members_only" <?php selected( $nbuf_default_privacy, 'members_only' ); ?>><?php esc_html_e( 'Members Only', 'nobloat-user-foundry' ); ?></option>
						<option value="public" <?php selected( $nbuf_default_privacy, 'public' ); ?>><?php esc_html_e( 'Public', 'nobloat-user-foundry' ); ?></option>
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
						<input type="checkbox" name="nbuf_profile_allow_cover_photos" value="1" <?php checked( $nbuf_allow_cover_photos, true ); ?>>
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
						<input type="checkbox" name="nbuf_profile_enable_gravatar" value="1" <?php checked( $nbuf_gravatar_enabled, true ); ?>>
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

		</table>

		<p class="description">
			<?php esc_html_e( 'Photo file size limits can be configured in the Media tab.', 'nobloat-user-foundry' ); ?>
		</p>

		<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
