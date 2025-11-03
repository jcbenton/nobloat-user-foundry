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

<div class="nbuf-shortcode-list">

	<!-- Login Form -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category auth"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Login Form', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_login_form]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays a custom login form on any page. Users can log in with their username/email and password.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Create a new page, add this shortcode, and users can log in from the frontend. Supports redirect after login and integration with 2FA if enabled.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Registration Form -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category auth"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Registration Form', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_registration_form]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays a user registration form with email verification support. Includes custom profile fields if configured.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Add to a page to allow new users to register. Supports email verification, password strength requirements, and custom profile fields.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Verification Page -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category auth"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Email Verification Page', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_verify_page]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Handles email verification when users click the verification link in their email. This is the landing page for email verification tokens.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Create a page with this shortcode and configure it as your verification page in the plugin settings. Users will be directed here from verification emails.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Request Password Reset -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category auth"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Request Password Reset Form', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_request_reset_form]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays a form where users can request a password reset link. Users enter their email address and receive a reset link.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Add to a "Forgot Password" page. Users enter their email and receive a password reset link via email.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Password Reset Form -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category auth"><?php esc_html_e( 'Authentication', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Password Reset Form', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_reset_form]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays the password reset form where users can enter their new password. This is the landing page for password reset links.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Create a page with this shortcode and configure it as your password reset page in the plugin settings. Users are directed here from password reset emails.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Account Page -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category account"><?php esc_html_e( 'Account Management', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Account Page', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_account_page]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays a complete account management page where logged-in users can view and edit their profile, change password, manage 2FA, and view account status.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Create an "My Account" page with this shortcode. Shows user information, verification status, expiration date, profile fields, password change form, and 2FA settings.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Logout -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category utility"><?php esc_html_e( 'Utility', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( 'Logout Link', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_logout]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays a logout link for logged-in users. Non-logged-in users will see nothing.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Add to any page, menu, or widget area where you want users to be able to log out. Can be customized via CSS to match your theme.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- 2FA Verification -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category security"><?php esc_html_e( 'Two-Factor Auth', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( '2FA Verification Form', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_2fa_verify]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays the two-factor authentication verification form. Users with 2FA enabled are redirected here after successful login to enter their verification code.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Create a page with this shortcode for 2FA verification. Users enter their TOTP code from authenticator app or email code. Supports backup codes and trusted devices.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- 2FA Setup -->
	<div class="nbuf-shortcode-item">
		<span class="nbuf-shortcode-category security"><?php esc_html_e( 'Two-Factor Auth', 'nobloat-user-foundry' ); ?></span>
		<h3><?php esc_html_e( '2FA Setup Page', 'nobloat-user-foundry' ); ?></h3>
		<code class="nbuf-shortcode-code">[nbuf_2fa_setup]</code>
		<div class="nbuf-shortcode-description">
			<p><?php esc_html_e( 'Displays the 2FA setup wizard where users can enable and configure two-factor authentication. Shows QR code for TOTP setup and backup code generation.', 'nobloat-user-foundry' ); ?></p>
		</div>
		<div class="nbuf-shortcode-usage">
			<h4><?php esc_html_e( 'Usage:', 'nobloat-user-foundry' ); ?></h4>
			<p><?php esc_html_e( 'Add to a page where users can set up 2FA. Displays QR code for scanning with authenticator apps (Google Authenticator, Authy, etc.), secret key for manual entry, and backup codes for account recovery.', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

</div>

<hr style="margin: 40px 0;">

<h3><?php esc_html_e( 'Quick Setup Guide', 'nobloat-user-foundry' ); ?></h3>
<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
	<h4><?php esc_html_e( 'Recommended Page Structure:', 'nobloat-user-foundry' ); ?></h4>
	<ol>
		<li><strong><?php esc_html_e( 'Login', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_login_form]</code></li>
		<li><strong><?php esc_html_e( 'Register', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_registration_form]</code></li>
		<li><strong><?php esc_html_e( 'My Account', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_account_page]</code></li>
		<li><strong><?php esc_html_e( 'Forgot Password', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_request_reset_form]</code></li>
		<li><strong><?php esc_html_e( 'Reset Password', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_reset_form]</code></li>
		<li><strong><?php esc_html_e( 'Verify Email', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_verify_page]</code></li>
		<li><strong><?php esc_html_e( '2FA Verify', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_2fa_verify]</code></li>
		<li><strong><?php esc_html_e( '2FA Setup', 'nobloat-user-foundry' ); ?>:</strong> <code>[nbuf_2fa_setup]</code></li>
	</ol>
	<p class="description">
		<?php esc_html_e( 'After creating these pages, configure them in Settings → System → Pages to enable automatic redirects.', 'nobloat-user-foundry' ); ?>
	</p>
</div>
