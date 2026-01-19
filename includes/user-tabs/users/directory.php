<?php
/**
 * Settings Tab: Users > Member Directory
 *
 * Member directory settings including visibility, search, and display options.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/user-tabs/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$nbuf_directory_enabled        = NBUF_Options::get( 'nbuf_enable_member_directory', false );
$nbuf_directory_view           = NBUF_Options::get( 'nbuf_directory_default_view', 'grid' );
$nbuf_directory_per_page       = NBUF_Options::get( 'nbuf_directory_per_page', 20 );
$nbuf_directory_show_search    = NBUF_Options::get( 'nbuf_directory_show_search', true );
$nbuf_directory_show_filters   = NBUF_Options::get( 'nbuf_directory_show_filters', true );
$nbuf_directory_privacy        = NBUF_Options::get( 'nbuf_directory_default_privacy', 'private' );
$nbuf_allow_privacy_control    = NBUF_Options::get( 'nbuf_allow_user_privacy_control', false );
$nbuf_display_privacy_disabled = NBUF_Options::get( 'nbuf_display_privacy_when_disabled', false );
$nbuf_directory_roles          = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );

/* Ensure arrays */
if ( ! is_array( $nbuf_directory_roles ) ) {
	$nbuf_directory_roles = array( 'author', 'contributor', 'subscriber' );
}

/* Get all roles */
$nbuf_wp_roles = wp_roles()->get_names();
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="directory">
	<!-- Declare checkboxes on this form for proper unchecked handling -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_enable_member_directory">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_directory_show_search">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_directory_show_filters">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_allow_user_privacy_control">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_display_privacy_when_disabled">
	<!-- Declare arrays on this form for proper empty array handling -->
	<input type="hidden" name="nbuf_form_arrays[]" value="nbuf_directory_roles">

	<h2><?php esc_html_e( 'Member Directory', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the public member directory. Member directories allow users to find and connect with other members on your site.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<!-- Enable Member Directory -->
		<tr>
			<th><?php esc_html_e( 'Enable Member Directory', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_enable_member_directory" value="0">
				<label>
					<input type="checkbox" name="nbuf_enable_member_directory" value="1" <?php checked( $nbuf_directory_enabled, true ); ?>>
					<?php esc_html_e( 'Enable the member directory feature', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Master toggle for the member directory. When disabled, the directory page will show a "disabled" message.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Display Settings', 'nobloat-user-foundry' ); ?></h2>

	<table class="form-table">
		<!-- Default View -->
		<tr>
			<th><?php esc_html_e( 'Default View', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_directory_default_view">
					<option value="grid" <?php selected( $nbuf_directory_view, 'grid' ); ?>><?php esc_html_e( 'Grid View', 'nobloat-user-foundry' ); ?></option>
					<option value="list" <?php selected( $nbuf_directory_view, 'list' ); ?>><?php esc_html_e( 'List View', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Choose how members are displayed in the directory by default.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Members Per Page -->
		<tr>
			<th><?php esc_html_e( 'Members Per Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_directory_per_page" value="<?php echo esc_attr( $nbuf_directory_per_page ); ?>" min="5" max="100" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of members to display per page in the directory (5-100).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Show Search Box -->
		<tr>
			<th><?php esc_html_e( 'Show Search Box', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_directory_show_search" value="0">
				<label>
					<input type="checkbox" name="nbuf_directory_show_search" value="1" <?php checked( $nbuf_directory_show_search, true ); ?>>
					<?php esc_html_e( 'Display a search box in the member directory', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow visitors to search the member directory by name, location, or bio.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Show Filter Dropdowns -->
		<tr>
			<th><?php esc_html_e( 'Show Filters', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_directory_show_filters" value="0">
				<label>
					<input type="checkbox" name="nbuf_directory_show_filters" value="1" <?php checked( $nbuf_directory_show_filters, true ); ?>>
					<?php esc_html_e( 'Display filter dropdowns in the directory', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Show role and other filter options to help users find specific members.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Privacy Settings', 'nobloat-user-foundry' ); ?></h2>

	<table class="form-table">
		<!-- Default Privacy Level -->
		<tr>
			<th><?php esc_html_e( 'Default Privacy Level', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_directory_default_privacy">
					<option value="private" <?php selected( $nbuf_directory_privacy, 'private' ); ?>><?php esc_html_e( 'Private - Hidden from directories', 'nobloat-user-foundry' ); ?></option>
					<option value="members_only" <?php selected( $nbuf_directory_privacy, 'members_only' ); ?>><?php esc_html_e( 'Members Only - Logged in users can view', 'nobloat-user-foundry' ); ?></option>
					<option value="public" <?php selected( $nbuf_directory_privacy, 'public' ); ?>><?php esc_html_e( 'Public - Anyone can view', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'The default privacy setting for new user registrations.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Allow Users to Adjust Privacy -->
		<tr>
			<th><?php esc_html_e( 'User Privacy Control', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_allow_user_privacy_control" value="0">
				<label>
					<input type="checkbox" name="nbuf_allow_user_privacy_control" value="1" <?php checked( $nbuf_allow_privacy_control, true ); ?>>
					<?php esc_html_e( 'Allow users to adjust their own privacy settings', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can change their profile privacy and directory visibility from their account page. When disabled, privacy settings are admin-controlled only.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Display Privacy When Disabled -->
		<tr>
			<th><?php esc_html_e( 'Show Read-Only Privacy', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_display_privacy_when_disabled" value="0">
				<label>
					<input type="checkbox" name="nbuf_display_privacy_when_disabled" value="1" <?php checked( $nbuf_display_privacy_disabled, true ); ?>>
					<?php esc_html_e( 'Display privacy settings (read-only) even when user editing is disabled', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users will see their current privacy settings even when they cannot edit them. When disabled, the privacy section is hidden entirely.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Role Restrictions', 'nobloat-user-foundry' ); ?></h2>

	<table class="form-table">
		<!-- Allowed Roles -->
		<tr>
			<th><?php esc_html_e( 'Include Roles', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<?php foreach ( $nbuf_wp_roles as $nbuf_role_slug => $nbuf_role_name ) : ?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="nbuf_directory_roles[]" value="<?php echo esc_attr( $nbuf_role_slug ); ?>" <?php checked( in_array( $nbuf_role_slug, $nbuf_directory_roles, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $nbuf_role_name ) ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which user roles can appear in the member directory.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
