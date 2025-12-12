<?php
/**
 * Account Settings Subtab (Users > Account)
 *
 * Controls account-related settings such as login method, password resets,
 * email changes, and account expiration.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Login settings */
$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );
$login_method = $reg_settings['login_method'] ?? 'email_only';

/* Password reset */
$enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );

/* Email settings */
$allow_email_change = NBUF_Options::get( 'nbuf_allow_email_change', 'disabled' );

/* Expiration settings */
$enable_expiration = NBUF_Options::get( 'nbuf_enable_expiration', false );
$warning_days      = NBUF_Options::get( 'nbuf_expiration_warning_days', 7 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nbuf-account-settings-form">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="account">

	<h2><?php esc_html_e( 'Account Settings', 'nobloat-user-foundry' ); ?></h2>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_registration_fields[login_method]">
					<option value="email_only" <?php selected( $login_method, 'email_only' ); ?>>
						<?php esc_html_e( 'Email Only - Users login with email address', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="username_only" <?php selected( $login_method, 'username_only' ); ?>>
						<?php esc_html_e( 'Username Only - Users login with username', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="email_or_username" <?php selected( $login_method, 'email_or_username' ); ?>>
						<?php esc_html_e( 'Email or Username - Users can use either', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How users will authenticate when logging in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Resets', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_enable_password_reset" value="0">
				<label>
					<input type="checkbox" name="nbuf_enable_password_reset" value="1" <?php checked( $enable_password_reset, true ); ?>>
					<?php esc_html_e( 'Enable password reset functionality', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow users to request password resets. When disabled, the "Forgot Password?" link will not appear on login forms.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Email Address Changes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_allow_email_change">
					<option value="disabled" <?php selected( $allow_email_change, 'disabled' ); ?>>
						<?php esc_html_e( 'Disabled - Users cannot change their email', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="enabled" <?php selected( $allow_email_change, 'enabled' ); ?>>
						<?php esc_html_e( 'Enabled - Users can change email (password required)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Allow users to change their email address from the frontend account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Enable Account Expiration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_expiration" value="1" <?php checked( $enable_expiration, 1 ); ?>>
					<?php esc_html_e( 'Enable expiration feature', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, you can set expiration dates on user profiles. Expired accounts cannot log in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Warning Days', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_expiration_warning_days" value="<?php echo esc_attr( $warning_days ); ?>" min="1" max="90" class="small-text">
				<?php esc_html_e( 'days before expiration', 'nobloat-user-foundry' ); ?>
				<p class="description">
					<?php esc_html_e( 'Send a warning email this many days before the account expires.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Account Settings', 'nobloat-user-foundry' ) ); ?>
</form>
