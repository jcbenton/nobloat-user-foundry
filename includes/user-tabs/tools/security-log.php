<?php
/**
 * Tools > Security Log Tab
 *
 * Security log configuration and alert settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle test email */
if ( isset( $_POST['send_test_email'] ) && check_admin_referer( 'nbuf_security_log_test_email' ) ) {
	$result = NBUF_Security_Log::send_test_email();

	if ( is_wp_error( $result ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
	} else {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent successfully! Check your inbox.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Handle form submission */
if ( isset( $_POST['submit'] ) && check_admin_referer( 'nbuf_security_log_settings' ) ) {
	/* Enable/Disable security logging */
	$enabled = isset( $_POST['nbuf_security_log_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_security_log_enabled', $enabled, true, 'security_log' );

	/* Retention period */
	$retention = isset( $_POST['nbuf_security_log_retention'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_security_log_retention'] ) ) : '365days';
	NBUF_Options::update( 'nbuf_security_log_retention', $retention, true, 'security_log' );

	/* Critical alerts enabled */
	$alerts_enabled = isset( $_POST['nbuf_security_log_alerts_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_security_log_alerts_enabled', $alerts_enabled, true, 'security_log' );

	/* Email recipient type */
	$recipient_type = isset( $_POST['nbuf_security_log_recipient_type'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_security_log_recipient_type'] ) ) : 'admin';
	NBUF_Options::update( 'nbuf_security_log_recipient_type', $recipient_type, true, 'security_log' );

	/* Custom email (validate format) */
	$custom_email = isset( $_POST['nbuf_security_log_custom_email'] ) ? sanitize_email( wp_unslash( $_POST['nbuf_security_log_custom_email'] ) ) : '';
	if ( ! empty( $custom_email ) && ! is_email( $custom_email ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid email address format.', 'nobloat-user-foundry' ) . '</p></div>';
		$custom_email = '';
	}
	NBUF_Options::update( 'nbuf_security_log_custom_email', $custom_email, true, 'security_log' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Security log settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$enabled        = NBUF_Options::get( 'nbuf_security_log_enabled', true );
$retention      = NBUF_Options::get( 'nbuf_security_log_retention', '365days' );
$alerts_enabled = NBUF_Options::get( 'nbuf_security_log_alerts_enabled', false );
$recipient_type = NBUF_Options::get( 'nbuf_security_log_recipient_type', 'admin' );
$custom_email   = NBUF_Options::get( 'nbuf_security_log_custom_email', '' );

/* Get statistics */
$stats = NBUF_Security_Log::get_stats();
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_security_log_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="tools">
	<input type="hidden" name="nbuf_active_subtab" value="security-log">

	<h2><?php esc_html_e( 'Security Log Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure security logging to track critical events, file operations, and privilege escalation attempts.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Enable Security Logging -->
		<tr>
			<th scope="row">
				<label for="nbuf_security_log_enabled">
					<?php esc_html_e( 'Enable Security Logging', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_security_log_enabled" id="nbuf_security_log_enabled" value="1" <?php checked( $enabled, 1 ); ?>>
					<?php esc_html_e( 'Track security events and file operations', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, the plugin will log privilege escalation attempts, file validation failures, and other security-related events.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Retention Period -->
		<tr>
			<th scope="row">
				<label for="nbuf_security_log_retention">
					<?php esc_html_e( 'Log Retention Period', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<select name="nbuf_security_log_retention" id="nbuf_security_log_retention">
					<option value="7days" <?php selected( $retention, '7days' ); ?>><?php esc_html_e( '7 Days', 'nobloat-user-foundry' ); ?></option>
					<option value="30days" <?php selected( $retention, '30days' ); ?>><?php esc_html_e( '30 Days', 'nobloat-user-foundry' ); ?></option>
					<option value="90days" <?php selected( $retention, '90days' ); ?>><?php esc_html_e( '90 Days', 'nobloat-user-foundry' ); ?></option>
					<option value="180days" <?php selected( $retention, '180days' ); ?>><?php esc_html_e( '6 Months', 'nobloat-user-foundry' ); ?></option>
					<option value="365days" <?php selected( $retention, '365days' ); ?>><?php esc_html_e( '1 Year (Recommended)', 'nobloat-user-foundry' ); ?></option>
					<option value="2years" <?php selected( $retention, '2years' ); ?>><?php esc_html_e( '2 Years', 'nobloat-user-foundry' ); ?></option>
					<option value="forever" <?php selected( $retention, 'forever' ); ?>><?php esc_html_e( 'Forever (Not Recommended)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Security logs older than this period will be automatically deleted during daily cleanup.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Critical Event Email Alerts', 'nobloat-user-foundry' ); ?></h3>

	<table class="form-table" role="presentation">
		<!-- Enable Critical Alerts -->
		<tr>
			<th scope="row">
				<label for="nbuf_security_log_alerts_enabled">
					<?php esc_html_e( 'Enable Email Alerts', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_security_log_alerts_enabled" id="nbuf_security_log_alerts_enabled" value="1" <?php checked( $alerts_enabled, 1 ); ?>>
					<?php esc_html_e( 'Send email notifications for critical security events', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Receive immediate email alerts when critical events occur, such as privilege escalation attempts or security violations.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Email Recipient -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Email Recipient', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="radio" name="nbuf_security_log_recipient_type" value="admin" <?php checked( $recipient_type, 'admin' ); ?>>
						<?php
						printf(
						/* translators: %s: Site admin email address */
							esc_html__( 'Site Administrator (%s)', 'nobloat-user-foundry' ),
							esc_html( get_option( 'admin_email' ) )
						);
						?>
					</label><br>

					<label>
						<input type="radio" name="nbuf_security_log_recipient_type" value="custom" <?php checked( $recipient_type, 'custom' ); ?>>
						<?php esc_html_e( 'Custom Email Address:', 'nobloat-user-foundry' ); ?>
					</label>
					<input type="email" name="nbuf_security_log_custom_email" id="nbuf_security_log_custom_email" value="<?php echo esc_attr( $custom_email ); ?>" class="regular-text" placeholder="security@example.com">
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Choose where to send critical security alerts. Custom email will be validated for correct format.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>

<!-- Test Email Form -->
<form method="post" action="" style="margin-top: 20px;">
	<?php wp_nonce_field( 'nbuf_security_log_test_email' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="tools">
	<input type="hidden" name="nbuf_active_subtab" value="security-log">

	<h3><?php esc_html_e( 'Test Email Configuration', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Send Test Email', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<button type="submit" name="send_test_email" class="button button-secondary">
					<?php esc_html_e( 'Send Test Security Alert', 'nobloat-user-foundry' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Sends a test security alert email to verify your configuration is working correctly.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>
</form>

<h3><?php esc_html_e( 'Database Status', 'nobloat-user-foundry' ); ?></h3>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row">
			<?php esc_html_e( 'Current Status', 'nobloat-user-foundry' ); ?>
		</th>
		<td>
			<table class="widefat" style="max-width: 600px;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Total Entries:', 'nobloat-user-foundry' ); ?></strong></td>
						<td><?php echo esc_html( number_format( $stats['total_entries'] ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Database Size:', 'nobloat-user-foundry' ); ?></strong></td>
						<td><?php echo esc_html( $stats['database_size'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Oldest Entry:', 'nobloat-user-foundry' ); ?></strong></td>
						<td><?php echo esc_html( $stats['oldest_entry'] ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Last Cleanup:', 'nobloat-user-foundry' ); ?></strong></td>
						<td><?php echo esc_html( $stats['last_cleanup'] ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description">
				<?php
				printf(
				/* translators: %s: Security log page URL */
					esc_html__( 'View logs and purge options on the %s page.', 'nobloat-user-foundry' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-security-log' ) ) . '">' . esc_html__( 'Security Audit Log', 'nobloat-user-foundry' ) . '</a>'
				);
				?>
			</p>
		</td>
	</tr>
</table>
