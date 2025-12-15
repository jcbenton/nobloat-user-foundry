<?php
/**
 * Tests Tab
 *
 * Testing tools for email verification and other plugin features.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Email Testing Tool', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Test any email template to verify that your emails are being delivered successfully and that your templates look correct. Choose an email type from the dropdown below, specify the sender and recipient, and send a test email.', 'nobloat-user-foundry' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'nbuf_send_test_email' ); ?>
	<input type="hidden" name="action" value="nbuf_send_test_email">

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email Type', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_email_type" class="regular-text" required>
					<option value=""><?php esc_html_e( '-- Select Email Type --', 'nobloat-user-foundry' ); ?></option>
					<option value="email-verification"><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></option>
					<option value="welcome-email"><?php esc_html_e( 'Welcome Email', 'nobloat-user-foundry' ); ?></option>
					<option value="expiration-warning"><?php esc_html_e( 'Expiration Warning', 'nobloat-user-foundry' ); ?></option>
					<option value="2fa-email-code"><?php esc_html_e( '2FA Email Code', 'nobloat-user-foundry' ); ?></option>
					<option value="password-reset"><?php esc_html_e( 'Password Reset', 'nobloat-user-foundry' ); ?></option>
					<option value="admin-new-user"><?php esc_html_e( 'Admin New User Notification', 'nobloat-user-foundry' ); ?></option>
					<option value="security-alert"><?php esc_html_e( 'Security Alert', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Select which type of email you want to test.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Sender Email', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="email" name="nbuf_sender" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" required>
				<p class="description"><?php esc_html_e( 'The email address that will appear as the sender. Defaults to your WordPress admin email.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Recipient Email', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="email" name="nbuf_recipient" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" required>
				<p class="description"><?php esc_html_e( 'The email address where the test email will be sent.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
	</table>

	<input type="hidden" name="nbuf_active_tab" value="tools">
	<input type="hidden" name="nbuf_active_subtab" value="tests">
	<?php submit_button( __( 'Send Test Email', 'nobloat-user-foundry' ), 'secondary' ); ?>
</form>

<hr style="margin: 40px 0;">

<h3><?php esc_html_e( 'Email Template Information', 'nobloat-user-foundry' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'Each email type uses customizable templates from the Templates tab. Test emails will use sample data to populate placeholders.', 'nobloat-user-foundry' ); ?>
</p>

<table class="widefat striped" style="margin-top: 15px;">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Email Type', 'nobloat-user-foundry' ); ?></th>
			<th><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th><?php esc_html_e( 'Configure Template', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><strong><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent when a new user registers and needs to verify their email address.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=verification' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Welcome Email', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent to welcome new users after successful registration.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=welcome' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Expiration Warning', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent to users when their account is approaching expiration.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=expiration' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( '2FA Email Code', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent when a user needs a verification code for two-factor authentication.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=2fa' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Password Reset', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent when a user requests a password reset link.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=password-reset' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Admin New User Notification', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent to the site admin when a new user registers.', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=templates&subtab=admin-notification' ) ); ?>"><?php esc_html_e( 'Edit Template', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Security Alert', 'nobloat-user-foundry' ); ?></strong></td>
			<td><?php esc_html_e( 'Sent when critical security events occur (privilege escalation, brute force attacks, etc.).', 'nobloat-user-foundry' ); ?></td>
			<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=gdpr&subtab=logging' ) ); ?>"><?php esc_html_e( 'Configure Alerts', 'nobloat-user-foundry' ); ?></a></td>
		</tr>
	</tbody>
</table>
