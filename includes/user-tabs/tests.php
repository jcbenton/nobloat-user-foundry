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

<h2><?php esc_html_e( 'Email Verification Test', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Test your email verification system by sending a sample verification email. This allows you to verify that your email templates are working correctly and that emails are being delivered successfully.', 'nobloat-user-foundry' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'nbuf_send_test_email' ); ?>
	<input type="hidden" name="action" value="nbuf_send_test_email">

	<table class="form-table">
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
				<p class="description"><?php esc_html_e( 'The email address where the test verification email will be sent.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
	</table>

	<input type="hidden" name="nbuf_active_tab" value="tests">
	<?php submit_button( __( 'Send Test Email', 'nobloat-user-foundry' ), 'secondary' ); ?>
</form>