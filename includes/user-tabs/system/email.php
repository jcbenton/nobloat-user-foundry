<?php
/**
 * System > Email Tab
 *
 * Email sender configuration settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current values with WordPress defaults as fallback */
$nbuf_email_sender_address = NBUF_Options::get( 'nbuf_email_sender_address', get_option( 'admin_email' ) );
$nbuf_email_sender_name    = NBUF_Options::get( 'nbuf_email_sender_name', get_bloginfo( 'name' ) );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="email">

	<h2><?php esc_html_e( 'Email Sender Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the sender address and name for all emails sent by this plugin, including verification emails, password resets, 2FA codes, and notifications.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th>
				<label for="nbuf_email_sender_address"><?php esc_html_e( 'Sender Email Address', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="email" name="nbuf_email_sender_address" id="nbuf_email_sender_address" value="<?php echo esc_attr( $nbuf_email_sender_address ); ?>" class="regular-text">
				<p class="description">
					<?php esc_html_e( 'The email address that appears in the "From" field. Defaults to your WordPress admin email.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th>
				<label for="nbuf_email_sender_name"><?php esc_html_e( 'Sender Name', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="text" name="nbuf_email_sender_name" id="nbuf_email_sender_name" value="<?php echo esc_attr( $nbuf_email_sender_name ); ?>" class="regular-text">
				<p class="description">
					<?php esc_html_e( 'The display name that appears in the "From" field. Defaults to your site name.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="nbuf-info-box" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 16px;margin:20px 0;">
		<p style="margin:0;">
			<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
			<?php esc_html_e( 'For reliable email delivery, ensure your sender address matches your domain and consider using an SMTP plugin. Some email providers may reject or spam-filter emails from mismatched domains.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<h2><?php esc_html_e( 'Test Email Delivery', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: link to email tests page */
			esc_html__( 'Use the %s to verify your email configuration is working correctly.', 'nobloat-user-foundry' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=tests' ) ) . '">' . esc_html__( 'Email Tests tool', 'nobloat-user-foundry' ) . '</a>'
		);
		?>
	</p>

	<?php submit_button( __( 'Save Email Settings', 'nobloat-user-foundry' ) ); ?>
</form>
