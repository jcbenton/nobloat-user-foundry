<?php
/**
 * Shortcodes Documentation Tab
 *
 * Lists all available shortcodes with descriptions and usage examples.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Available Shortcodes', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Use these shortcodes to display various user management forms and functionality on your WordPress pages.', 'nobloat-user-foundry' ); ?>
</p>

<!-- Authentication -->
<h3 style="background: #2e7d32; color: #fff; padding: 8px 12px; margin: 30px 0 15px 0;"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></h3>

<table class="widefat striped">
	<tbody>
		<tr>
			<td style="width: 220px;"><code>[nbuf_login_form]</code></td>
			<td>
				<strong><?php esc_html_e( 'Login Form', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Displays a custom login form. Supports redirect after login and integration with 2FA if enabled.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_registration_form]</code></td>
			<td>
				<strong><?php esc_html_e( 'Registration Form', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Displays a user registration form with email verification support. Includes custom profile fields if configured.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_verify_page]</code></td>
			<td>
				<strong><?php esc_html_e( 'Email Verification Page', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Handles email verification when users click the verification link. Configure as your verification page in Settings → System → Pages.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_request_reset_form]</code></td>
			<td>
				<strong><?php esc_html_e( 'Request Password Reset', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Displays a form where users enter their email to receive a password reset link.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_reset_form]</code></td>
			<td>
				<strong><?php esc_html_e( 'Password Reset Form', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Landing page for password reset links where users enter their new password.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<!-- Account Management -->
<h3 style="background: #1565c0; color: #fff; padding: 8px 12px; margin: 30px 0 15px 0;"><?php esc_html_e( 'Account Management', 'nobloat-user-foundry' ); ?></h3>

<table class="widefat striped">
	<tbody>
		<tr>
			<td style="width: 220px;"><code>[nbuf_account_page]</code></td>
			<td>
				<strong><?php esc_html_e( 'Account Page', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Complete account management page where users can view/edit profile, change password, manage 2FA, and view account status.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<!-- Two-Factor Authentication -->
<h3 style="background: #6a1b9a; color: #fff; padding: 8px 12px; margin: 30px 0 15px 0;"><?php esc_html_e( 'Two-Factor Authentication', 'nobloat-user-foundry' ); ?></h3>

<table class="widefat striped">
	<tbody>
		<tr>
			<td style="width: 220px;"><code>[nbuf_2fa_verify]</code></td>
			<td>
				<strong><?php esc_html_e( '2FA Verification Form', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Users with 2FA enabled are redirected here after login to enter their TOTP or email code. Supports backup codes.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_totp_setup]</code></td>
			<td>
				<strong><?php esc_html_e( 'Authenticator App Setup Page', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Dedicated setup page for authenticator app (TOTP) configuration. Shows QR code for scanning with apps like Google Authenticator or Authy.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<!-- Profiles -->
<h3 style="background: #00695c; color: #fff; padding: 8px 12px; margin: 30px 0 15px 0;"><?php esc_html_e( 'Profiles', 'nobloat-user-foundry' ); ?></h3>

<table class="widefat striped">
	<tbody>
		<tr>
			<td style="width: 220px;"><code>[nbuf_profile]</code></td>
			<td>
				<strong><?php esc_html_e( 'User Profile', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Displays a public user profile. Link via ?user_id=X or ?username=X parameters. Respects privacy settings.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_members]</code></td>
			<td>
				<strong><?php esc_html_e( 'Member Directory', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Searchable directory of site members with filtering, sorting, and pagination. Only shows users with public profiles.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<!-- Utility -->
<h3 style="background: #455a64; color: #fff; padding: 8px 12px; margin: 30px 0 15px 0;"><?php esc_html_e( 'Utility', 'nobloat-user-foundry' ); ?></h3>

<table class="widefat striped">
	<tbody>
		<tr>
			<td style="width: 220px;"><code>[nbuf_logout]</code></td>
			<td>
				<strong><?php esc_html_e( 'Logout Link', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Displays a logout link for logged-in users. Hidden for guests.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_restrict]...[/nbuf_restrict]</code></td>
			<td>
				<strong><?php esc_html_e( 'Content Restriction', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Restricts content based on login status, roles, or verification. Attributes: role="subscriber,editor", logged_in="true", verified="true", message="Custom message".', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
		<tr>
			<td><code>[nbuf_data_export]</code></td>
			<td>
				<strong><?php esc_html_e( 'GDPR Data Export', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Button allowing logged-in users to download their personal data in JSON format for GDPR compliance.', 'nobloat-user-foundry' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<!-- Quick Setup Guide -->
<h3 style="margin-top: 40px;"><?php esc_html_e( 'Quick Setup Guide', 'nobloat-user-foundry' ); ?></h3>
<p class="description"><?php esc_html_e( 'Recommended page structure for a complete user management system:', 'nobloat-user-foundry' ); ?></p>

<table class="widefat striped" style="max-width: 500px;">
	<tbody>
		<tr><td><strong><?php esc_html_e( 'Login', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_login_form]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'Register', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_registration_form]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'My Account', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_account_page]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'Forgot Password', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_request_reset_form]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'Reset Password', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_reset_form]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'Verify Email', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_verify_page]</code></td></tr>
		<tr><td><strong><?php esc_html_e( '2FA Verify', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_2fa_verify]</code></td></tr>
		<tr><td><strong><?php esc_html_e( 'Authenticator Setup', 'nobloat-user-foundry' ); ?></strong></td><td><code>[nbuf_totp_setup]</code></td></tr>
	</tbody>
</table>
<p class="description" style="margin-top: 10px;">
	<?php esc_html_e( 'After creating pages, configure them in Settings → System → Pages to enable automatic redirects.', 'nobloat-user-foundry' ); ?>
</p>
