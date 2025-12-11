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

/* Feature toggles */
$require_verification      = NBUF_Options::get( 'nbuf_require_verification', true );
$enable_login              = NBUF_Options::get( 'nbuf_enable_login', true );
$enable_registration       = NBUF_Options::get( 'nbuf_enable_registration', true );
$notify_admin_registration = NBUF_Options::get( 'nbuf_notify_admin_registration', false );
$enable_password_reset     = NBUF_Options::get( 'nbuf_enable_password_reset', true );

/* Admin Users List columns */
$users_column_posts    = NBUF_Options::get( 'nbuf_users_column_posts', false );
$users_column_company  = NBUF_Options::get( 'nbuf_users_column_company', false );
$users_column_location = NBUF_Options::get( 'nbuf_users_column_location', false );

/* WordPress Toolbar */
$admin_bar_visibility = NBUF_Options::get( 'nbuf_admin_bar_visibility', 'show_admin' );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="general">

	<h2><?php esc_html_e( 'Feature Toggles', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_require_verification" value="0">
				<label>
					<input type="checkbox" name="nbuf_require_verification" value="1" <?php checked( $require_verification, true ); ?>>
					<?php esc_html_e( 'Require email verification for new user registrations', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, new users must verify their email address before they can log in. When disabled, users can log in immediately after registration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Custom Login Form', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_enable_login" value="0">
				<label>
					<input type="checkbox" name="nbuf_enable_login" value="1" <?php checked( $enable_login, true ); ?>>
					<?php esc_html_e( 'Enable custom login form', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Use NoBloat login form via [nbuf_login_form] shortcode. When disabled, the shortcode will display a message.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'User Registration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_enable_registration" value="0">
				<label>
					<input type="checkbox" name="nbuf_enable_registration" value="1" <?php checked( $enable_registration, true ); ?>>
					<?php esc_html_e( 'Enable user registration', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow new users to register via [nbuf_registration_form] shortcode. When disabled, the shortcode will display a message.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Admin Notifications', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_notify_admin_registration" value="0">
				<label>
					<input type="checkbox" name="nbuf_notify_admin_registration" value="1" <?php checked( $notify_admin_registration, true ); ?>>
					<?php esc_html_e( 'Notify administrators when new users register', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Send an email notification to the site administrator email when a new user creates an account.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_enable_password_reset" value="0">
				<label>
					<input type="checkbox" name="nbuf_enable_password_reset" value="1" <?php checked( $enable_password_reset, true ); ?>>
					<?php esc_html_e( 'Enable password reset functionality', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow users to request password resets. When disabled, password reset forms will display a message. The "Forgot Password?" link will not appear on login forms when disabled.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

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
						<input type="checkbox" name="nbuf_users_column_posts" value="1" <?php checked( $users_column_posts, true ); ?>>
						<?php esc_html_e( 'Posts', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="hidden" name="nbuf_users_column_company" value="0">
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_users_column_company" value="1" <?php checked( $users_column_company, true ); ?>>
						<?php esc_html_e( 'Company', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="hidden" name="nbuf_users_column_location" value="0">
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_users_column_location" value="1" <?php checked( $users_column_location, true ); ?>>
						<?php esc_html_e( 'Location', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which additional columns to show on the Users page in the admin dashboard.', 'nobloat-user-foundry' ); ?>
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
					<option value="show_admin" <?php selected( $admin_bar_visibility, 'show_admin' ); ?>>
						<?php esc_html_e( 'Show for administrators only', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="show_all" <?php selected( $admin_bar_visibility, 'show_all' ); ?>>
						<?php esc_html_e( 'Show for everyone', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="hide_all" <?php selected( $admin_bar_visibility, 'hide_all' ); ?>>
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
