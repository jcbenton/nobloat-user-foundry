<?php
/**
 * Security Alert Email Template Tab
 *
 * Manage security alert email template.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template using Template Manager (checks DB first, then falls back to file) */
$nbuf_security_alert_html = NBUF_Template_Manager::load_template( 'security-alert-email-html' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the security alert email sent to administrators when critical security events are detected (if security alerts are enabled in Security settings).', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="security-alert">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_security_alert_email_html"
				rows="25"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_security_alert_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {site_url}, {event_type}, {message}, {username}, {user_email}, {user_id}, {ip_address}, {timestamp}, {log_url}, {context}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="security-alert-email-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Security Alert Template', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
