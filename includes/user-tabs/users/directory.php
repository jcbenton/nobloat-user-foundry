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
$nbuf_directory_enabled       = NBUF_Options::get( 'nbuf_enable_member_directory', false );
$nbuf_directory_view          = NBUF_Options::get( 'nbuf_directory_default_view', 'grid' );
$nbuf_directory_per_page      = NBUF_Options::get( 'nbuf_directory_per_page', 20 );
$nbuf_directory_show_search   = NBUF_Options::get( 'nbuf_directory_show_search', true );
$nbuf_directory_show_filters  = NBUF_Options::get( 'nbuf_directory_show_filters', true );
$nbuf_directory_privacy       = NBUF_Options::get( 'nbuf_directory_default_privacy', 'private' );
$nbuf_directory_auto_include  = NBUF_Options::get( 'nbuf_directory_auto_include', false );
$nbuf_allow_privacy_control   = NBUF_Options::get( 'nbuf_allow_user_privacy_control', false );
$nbuf_display_privacy_disabled = NBUF_Options::get( 'nbuf_display_privacy_when_disabled', false );
$nbuf_searchable_fields       = NBUF_Options::get( 'nbuf_directory_searchable_fields', array( 'display_name', 'bio', 'city' ) );
$nbuf_visible_fields          = NBUF_Options::get( 'nbuf_directory_visible_fields', array( 'location', 'website', 'company', 'job_title', 'joined' ) );
$nbuf_directory_roles         = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );

/* Ensure arrays */
if ( ! is_array( $nbuf_searchable_fields ) ) {
	$nbuf_searchable_fields = array( 'display_name', 'bio', 'city' );
}
if ( ! is_array( $nbuf_visible_fields ) ) {
	$nbuf_visible_fields = array( 'location', 'website', 'company', 'job_title', 'joined' );
}
if ( ! is_array( $nbuf_directory_roles ) ) {
	$nbuf_directory_roles = array( 'author', 'contributor', 'subscriber' );
}

/* Available searchable fields */
$nbuf_available_search_fields = array(
	'display_name' => __( 'Display Name', 'nobloat-user-foundry' ),
	'bio'          => __( 'Biography', 'nobloat-user-foundry' ),
	'city'         => __( 'City', 'nobloat-user-foundry' ),
	'state'        => __( 'State/Province', 'nobloat-user-foundry' ),
	'country'      => __( 'Country', 'nobloat-user-foundry' ),
	'company'      => __( 'Company', 'nobloat-user-foundry' ),
	'job_title'    => __( 'Job Title', 'nobloat-user-foundry' ),
);

/* Available visible fields for member cards */
$nbuf_available_visible_fields = array(
	'bio'       => __( 'Biography', 'nobloat-user-foundry' ),
	'location'  => __( 'Location (City/State/Country)', 'nobloat-user-foundry' ),
	'website'   => __( 'Website', 'nobloat-user-foundry' ),
	'company'   => __( 'Company', 'nobloat-user-foundry' ),
	'job_title' => __( 'Job Title', 'nobloat-user-foundry' ),
	'joined'    => __( 'Join Date', 'nobloat-user-foundry' ),
);

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
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_directory_auto_include">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_allow_user_privacy_control">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_display_privacy_when_disabled">

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

		<!-- Visible Fields in Member Cards -->
		<tr>
			<th><?php esc_html_e( 'Visible in Member Cards', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<?php foreach ( $nbuf_available_visible_fields as $field_key => $field_label ) : ?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="nbuf_directory_visible_fields[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $nbuf_visible_fields, true ) ); ?>>
							<?php echo esc_html( $field_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which profile fields are displayed in member cards (subject to user privacy settings).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Searchable Fields -->
		<tr>
			<th><?php esc_html_e( 'Searchable Fields', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<?php foreach ( $nbuf_available_search_fields as $field_key => $field_label ) : ?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="nbuf_directory_searchable_fields[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $nbuf_searchable_fields, true ) ); ?>>
							<?php echo esc_html( $field_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which profile fields can be searched in the member directory.', 'nobloat-user-foundry' ); ?>
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

		<!-- Auto-Include New Users -->
		<tr>
			<th><?php esc_html_e( 'Auto-Include New Users', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_directory_auto_include" value="0">
				<label>
					<input type="checkbox" name="nbuf_directory_auto_include" value="1" <?php checked( $nbuf_directory_auto_include, true ); ?>>
					<?php esc_html_e( 'Automatically include new users in the member directory', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Automatically opt-in new users to appear in member directories (respecting their privacy level). If disabled, users must manually opt-in from their account settings.', 'nobloat-user-foundry' ); ?>
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
					<?php foreach ( $nbuf_wp_roles as $role_slug => $role_name ) : ?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="nbuf_directory_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $nbuf_directory_roles, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $role_name ) ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which user roles can appear in the member directory.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Directory Settings', 'nobloat-user-foundry' ) ); ?>
</form>

<hr style="margin: 40px 0;">

<h2><?php esc_html_e( 'Shortcode Reference', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Use the following shortcode to display the member directory on any page:', 'nobloat-user-foundry' ); ?>
</p>

<table style="margin-top: 15px; border-collapse: collapse; max-width: 800px;">
	<thead>
		<tr>
			<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Shortcode', 'nobloat-user-foundry' ); ?></th>
			<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Basic member directory with default settings', 'nobloat-user-foundry' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members view="list"]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Display in list view instead of grid', 'nobloat-user-foundry' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members per_page="30"]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Show 30 members per page', 'nobloat-user-foundry' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members roles="subscriber,contributor"]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Limit to specific roles', 'nobloat-user-foundry' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members show_search="no"]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Hide the search box', 'nobloat-user-foundry' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 8px 10px;"><code>[nbuf_members orderby="user_registered" order="DESC"]</code></td>
			<td style="padding: 8px 10px;"><?php esc_html_e( 'Sort by registration date, newest first', 'nobloat-user-foundry' ); ?></td>
		</tr>
	</tbody>
</table>

<p class="description" style="margin-top: 15px;">
	<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
	<?php esc_html_e( 'The member directory is also available at the /members/ URL when using the Universal Page system.', 'nobloat-user-foundry' ); ?>
</p>
