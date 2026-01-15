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

/* Admin Access */
$nbuf_restrict_admin_access = NBUF_Options::get( 'nbuf_restrict_admin_access', false );
$nbuf_admin_redirect_url    = NBUF_Options::get( 'nbuf_admin_redirect_url', '' );

/* Account Page Features */
$nbuf_session_management_enabled  = NBUF_Options::get( 'nbuf_session_management_enabled', true );
$nbuf_activity_dashboard_enabled  = NBUF_Options::get( 'nbuf_activity_dashboard_enabled', true );

/* Terms of Service */
$nbuf_tos_enabled            = NBUF_Options::get( 'nbuf_tos_enabled', false );
$nbuf_tos_require_on_login   = NBUF_Options::get( 'nbuf_tos_require_on_login', true );
$nbuf_tos_grace_period_hours = NBUF_Options::get( 'nbuf_tos_grace_period_hours', 24 );

/* User Impersonation */
$nbuf_impersonation_enabled    = NBUF_Options::get( 'nbuf_impersonation_enabled', false );
$nbuf_impersonation_capability = NBUF_Options::get( 'nbuf_impersonation_capability', 'edit_users' );
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

	<h2><?php esc_html_e( 'Admin Dashboard Access', 'nobloat-user-foundry' ); ?></h2>
	<!-- Declare checkboxes for proper unchecked handling -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_restrict_admin_access">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_session_management_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_activity_dashboard_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_tos_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_tos_require_on_login">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_impersonation_enabled">
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Restrict Admin Access', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_restrict_admin_access" value="1" <?php checked( $nbuf_restrict_admin_access, true ); ?>>
					<?php esc_html_e( 'Only allow administrators to access wp-admin', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, non-administrator users who navigate to /wp-admin will be redirected. AJAX requests are still allowed for frontend functionality.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Redirect URL', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="url" name="nbuf_admin_redirect_url" value="<?php echo esc_attr( $nbuf_admin_redirect_url ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Leave empty for Account page', 'nobloat-user-foundry' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Where to redirect non-admin users. Leave empty to redirect to the Account page (or home if not configured).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Account Page Features', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure features available to users on the frontend account page.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Session Management', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_session_management_enabled" value="1" <?php checked( $nbuf_session_management_enabled, true ); ?>>
					<?php esc_html_e( 'Enable session management for users', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can view their active login sessions and revoke sessions from other devices on their account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Activity Dashboard', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_activity_dashboard_enabled" value="1" <?php checked( $nbuf_activity_dashboard_enabled, true ); ?>>
					<?php esc_html_e( 'Enable activity dashboard for users', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can view a timeline of their account activity (logins, password changes, profile updates, etc.) on their account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Terms of Service', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Track user acceptance of Terms of Service with versioning. When a new version is published, users can be required to accept before continuing.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable ToS Tracking', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_tos_enabled" value="1" <?php checked( $nbuf_tos_enabled, true ); ?> id="nbuf_tos_enabled">
					<?php esc_html_e( 'Enable Terms of Service version tracking', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users must accept the Terms of Service. Manage ToS versions in Users > Terms of Service.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_tos_require_row">
			<th><?php esc_html_e( 'Require on Login', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_tos_require_on_login" value="1" <?php checked( $nbuf_tos_require_on_login, true ); ?>>
					<?php esc_html_e( 'Require ToS acceptance on login', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users who have not accepted the current ToS will be redirected to accept it after logging in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_tos_grace_row">
			<th><?php esc_html_e( 'Grace Period', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_tos_grace_period_hours" value="<?php echo esc_attr( $nbuf_tos_grace_period_hours ); ?>" min="0" max="720" class="small-text">
				<?php esc_html_e( 'hours', 'nobloat-user-foundry' ); ?>
				<p class="description">
					<?php esc_html_e( 'Grace period after a new ToS becomes effective before users are required to accept. Set to 0 to require immediate acceptance.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<script>
	(function() {
		var tosEnableCheckbox = document.getElementById('nbuf_tos_enabled');
		var tosRequireRow = document.getElementById('nbuf_tos_require_row');
		var tosGraceRow = document.getElementById('nbuf_tos_grace_row');

		function toggleTosRows() {
			var display = tosEnableCheckbox.checked ? '' : 'none';
			tosRequireRow.style.display = display;
			tosGraceRow.style.display = display;
		}

		tosEnableCheckbox.addEventListener('change', toggleTosRows);
		toggleTosRows();
	})();
	</script>

	<h2><?php esc_html_e( 'User Impersonation', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Allow administrators to log in as other users for support purposes. All impersonation actions are logged to the audit trail.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Impersonation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_impersonation_enabled" value="1" <?php checked( $nbuf_impersonation_enabled, true ); ?> id="nbuf_impersonation_enabled">
					<?php esc_html_e( 'Allow authorized users to impersonate other users', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, a "Login as User" link appears on the Users admin page. A prominent banner shows when impersonating.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_impersonation_capability_row">
			<th><?php esc_html_e( 'Required Capability', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_impersonation_capability">
					<option value="manage_options" <?php selected( $nbuf_impersonation_capability, 'manage_options' ); ?>>
						<?php esc_html_e( 'manage_options (Administrators only)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="edit_users" <?php selected( $nbuf_impersonation_capability, 'edit_users' ); ?>>
						<?php esc_html_e( 'edit_users (Default - Administrators and Editors with user management)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="delete_users" <?php selected( $nbuf_impersonation_capability, 'delete_users' ); ?>>
						<?php esc_html_e( 'delete_users (Users who can delete other users)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Only users with this capability can impersonate others. Users cannot impersonate anyone with higher capabilities than themselves.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<script>
	(function() {
		var enableCheckbox = document.getElementById('nbuf_impersonation_enabled');
		var capabilityRow = document.getElementById('nbuf_impersonation_capability_row');

		function toggleRow() {
			capabilityRow.style.display = enableCheckbox.checked ? '' : 'none';
		}

		enableCheckbox.addEventListener('change', toggleRow);
		toggleRow();
	})();
	</script>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
