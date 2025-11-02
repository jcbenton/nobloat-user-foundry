<?php
/**
 * System > Status Tab
 *
 * Master toggle, feature toggles, and plugin activation settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = NBUF_Options::get('nbuf_settings', array() );

/* Feature toggles */
$require_verification      = NBUF_Options::get( 'nbuf_require_verification', true );
$enable_login              = NBUF_Options::get( 'nbuf_enable_login', true );
$enable_registration       = NBUF_Options::get( 'nbuf_enable_registration', true );
$notify_admin_registration = NBUF_Options::get( 'nbuf_notify_admin_registration', false );
$enable_password_reset     = NBUF_Options::get( 'nbuf_enable_password_reset', true );

/* Master toggle */
$user_manager_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_settings_group' );
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="status">

	<!-- Master Toggle -->
	<div style="background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'User Manager Status', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" style="margin-bottom: 0;">
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'System Status', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_user_manager_enabled" value="1" <?php checked( $user_manager_enabled, true ); ?> id="nbuf_user_manager_enabled">
						<strong><?php esc_html_e( 'Enable User Management System', 'nobloat-user-foundry' ); ?></strong>
					</label>
					<?php if ( ! $user_manager_enabled ) : ?>
						<p class="description" style="color: #d63638; font-weight: 500;">
							⚠️ <?php esc_html_e( 'User management is currently DISABLED. Configure your settings and migrate any data before enabling.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php else : ?>
						<p class="description" style="color: #00a32a; font-weight: 500;">
							✓ <?php esc_html_e( 'User management is currently ENABLED. All features are active.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'When disabled, the plugin will not modify user behavior, authentication, or the users list. Use this to configure settings and prepare for migration before activating.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<h2><?php esc_html_e( 'Feature Toggles', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></th>
			<td>
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

	<h2><?php esc_html_e( 'Plugin Activation', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Auto-Verify Existing Users', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_settings[auto_verify_existing]" value="1" <?php checked( ! empty( $settings['auto_verify_existing'] ), true ); ?>>
					<?php esc_html_e( 'Automatically verify all existing users when the plugin is activated.', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Disable this if you want to require verification for existing users after reactivating the plugin.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
