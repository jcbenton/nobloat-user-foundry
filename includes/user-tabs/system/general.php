<?php
/**
 * System > General Tab
 *
 * Feature toggles, admin UI settings, and WordPress toolbar visibility.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Admin Users List columns */
$nbuf_users_column_posts    = NBUF_Options::get( 'nbuf_users_column_posts', false );
$nbuf_users_column_company  = NBUF_Options::get( 'nbuf_users_column_company', false );
$nbuf_users_column_location = NBUF_Options::get( 'nbuf_users_column_location', false );

/* WordPress Toolbar */
$nbuf_admin_bar_visibility = NBUF_Options::get( 'nbuf_admin_bar_visibility', 'show_admin' );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="general">

	<h2><?php esc_html_e( 'Admin Users List Columns', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure which optional columns to display on the WordPress Users admin page. The default columns (Username, Name, Email, Verified, Expiration, Role) are always shown.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Optional Columns', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<input type="hidden" name="nbuf_users_column_posts" value="0">
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_users_column_posts" value="1" <?php checked( $nbuf_users_column_posts, true ); ?>>
						<?php esc_html_e( 'Posts', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="hidden" name="nbuf_users_column_company" value="0">
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_users_column_company" value="1" <?php checked( $nbuf_users_column_company, true ); ?>>
						<?php esc_html_e( 'Company', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="hidden" name="nbuf_users_column_location" value="0">
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_users_column_location" value="1" <?php checked( $nbuf_users_column_location, true ); ?>>
						<?php esc_html_e( 'Location', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which additional columns to show on the Users page in the admin dashboard. Company displays company and job title from the Professional profile fields. Location displays city, state, and country from the Address profile fields. Enable these fields in Users > Profile Fields for users to populate them.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'WordPress Toolbar', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Toolbar Visibility', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_admin_bar_visibility" id="nbuf_admin_bar_visibility">
					<option value="show_admin" <?php selected( $nbuf_admin_bar_visibility, 'show_admin' ); ?>>
						<?php esc_html_e( 'Show for administrators only', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="show_all" <?php selected( $nbuf_admin_bar_visibility, 'show_all' ); ?>>
						<?php esc_html_e( 'Show for everyone', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="hide_all" <?php selected( $nbuf_admin_bar_visibility, 'hide_all' ); ?>>
						<?php esc_html_e( 'Hide for all users', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Control the visibility of the WordPress toolbar when users are logged in and viewing the frontend of the site.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
