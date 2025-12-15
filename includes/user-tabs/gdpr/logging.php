<?php
/**
 * GDPR > Logging Tab
 *
 * Consolidated logging configuration for all three log tables.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/GDPR
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Security: Verify user has permission to access this page */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
}

/* Define settings fields using ACTUAL option names from the codebase */
$nbuf_settings_fields = array(

	/*
	 * ===========================================
	 * USER ACTIVITY LOG (nbuf_user_audit_log table)
	 * ===========================================
	 */
	array(
		'id'    => 'section_user_audit',
		'title' => __( 'User Activity Log', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Track user-initiated actions. Table: <code>nbuf_user_audit_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_audit_log_enabled',
		'title'    => __( 'Enable User Activity Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'audit_log',
		'desc'     => __( 'Track user logins, logouts, password changes, 2FA events, and profile updates.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_audit_log_events',
		'title'    => __( 'Events to Track', 'nobloat-user-foundry' ),
		'type'     => 'checkbox_group',
		'default'  => array(
			'authentication' => true,
			'verification'   => true,
			'passwords'      => true,
			'2fa'            => true,
			'account_status' => true,
			'profile'        => false,
		),
		'category' => 'audit_log',
		'options'  => array(
			'authentication' => __( 'Authentication (login, logout)', 'nobloat-user-foundry' ),
			'verification'   => __( 'Email Verification', 'nobloat-user-foundry' ),
			'passwords'      => __( 'Password Changes & Resets', 'nobloat-user-foundry' ),
			'2fa'            => __( 'Two-Factor Authentication (2FA)', 'nobloat-user-foundry' ),
			'account_status' => __( 'Account Status (disable, enable, expiration)', 'nobloat-user-foundry' ),
			'profile'        => __( 'Profile Updates (can be noisy)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Select which types of user events to log.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_audit_log_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => '90days',
		'category' => 'audit_log',
		'options'  => array(
			'7days'   => __( '7 Days', 'nobloat-user-foundry' ),
			'30days'  => __( '30 Days', 'nobloat-user-foundry' ),
			'90days'  => __( '90 Days (Recommended)', 'nobloat-user-foundry' ),
			'180days' => __( '6 Months', 'nobloat-user-foundry' ),
			'1year'   => __( '1 Year', 'nobloat-user-foundry' ),
			'2years'  => __( '2 Years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Logs older than this will be automatically deleted during daily cleanup.', 'nobloat-user-foundry' ),
	),

	/*
	 * ===========================================
	 * ADMIN ACTIONS LOG (nbuf_admin_audit_log table)
	 * ===========================================
	 */
	array(
		'id'    => 'section_admin_audit',
		'title' => __( 'Admin Actions Log', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Track administrator actions on users and settings. Table: <code>nbuf_admin_audit_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_enabled',
		'title'    => __( 'Enable Admin Action Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Track user deletions, role changes, manual verifications, and settings changes. <strong>Recommended for compliance.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_categories',
		'title'    => __( 'Actions to Track', 'nobloat-user-foundry' ),
		'type'     => 'checkbox_group',
		'default'  => array(
			'user_deletion'        => true,
			'role_changes'         => true,
			'settings_changes'     => true,
			'bulk_actions'         => true,
			'manual_verifications' => true,
			'password_resets'      => true,
			'profile_edits'        => true,
		),
		'category' => 'logging',
		'options'  => array(
			'user_deletion'        => __( 'User Deletion', 'nobloat-user-foundry' ),
			'role_changes'         => __( 'Role Changes', 'nobloat-user-foundry' ),
			'settings_changes'     => __( 'Settings Changes', 'nobloat-user-foundry' ),
			'bulk_actions'         => __( 'Bulk Actions', 'nobloat-user-foundry' ),
			'manual_verifications' => __( 'Manual Verifications', 'nobloat-user-foundry' ),
			'password_resets'      => __( 'Password Resets (by admin)', 'nobloat-user-foundry' ),
			'profile_edits'        => __( 'Profile Edits (by admin)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Select which admin actions to log. <strong>All recommended for compliance.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => 'forever',
		'category' => 'logging',
		'options'  => array(
			'365'     => __( '1 Year', 'nobloat-user-foundry' ),
			'730'     => __( '2 Years', 'nobloat-user-foundry' ),
			'1095'    => __( '3 Years', 'nobloat-user-foundry' ),
			'1825'    => __( '5 Years', 'nobloat-user-foundry' ),
			'2555'    => __( '7 Years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever (Recommended)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Admin logs may be required for compliance. Forever is recommended.', 'nobloat-user-foundry' ),
	),

	/*
	 * ===========================================
	 * SECURITY EVENT LOG (nbuf_security_log table)
	 * ===========================================
	 */
	array(
		'id'    => 'section_security',
		'title' => __( 'Security Event Log', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Track security events and threats. Table: <code>nbuf_security_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_security_log_enabled',
		'title'    => __( 'Enable Security Event Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Track login lockouts, file validation failures, and security threats. <strong>Recommended.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_security_log_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => '365days',
		'category' => 'security_log',
		'options'  => array(
			'7days'   => __( '7 Days', 'nobloat-user-foundry' ),
			'30days'  => __( '30 Days', 'nobloat-user-foundry' ),
			'90days'  => __( '90 Days', 'nobloat-user-foundry' ),
			'180days' => __( '6 Months', 'nobloat-user-foundry' ),
			'365days' => __( '1 Year (Recommended)', 'nobloat-user-foundry' ),
			'2years'  => __( '2 Years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever (Not Recommended)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Security logs older than this period will be automatically deleted during daily cleanup.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_security_log_alerts_enabled',
		'title'    => __( 'Enable Email Alerts', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => false,
		'category' => 'security_log',
		'desc'     => __( 'Send email notifications for critical security events such as privilege escalation attempts or security violations.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_security_log_recipient_type',
		'title'    => __( 'Alert Recipient', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => 'admin',
		'category' => 'security_log',
		'options'  => array(
			'admin'  => sprintf(
				/* translators: %s: Site admin email address */
				__( 'Site Administrator (%s)', 'nobloat-user-foundry' ),
				get_option( 'admin_email' )
			),
			'custom' => __( 'Custom Email Address', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Choose where to send critical security alerts.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_security_log_custom_email',
		'title'    => __( 'Custom Alert Email', 'nobloat-user-foundry' ),
		'type'     => 'email',
		'default'  => '',
		'category' => 'security_log',
		'desc'     => __( 'Enter custom email address for security alerts (only used when "Custom Email Address" is selected above).', 'nobloat-user-foundry' ),
	),

	/*
	 * ===========================================
	 * PRIVACY SETTINGS (applies to all logs)
	 * ===========================================
	 */
	array(
		'id'    => 'section_privacy',
		'title' => __( 'Privacy & GDPR', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure privacy options for all logging tables.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_anonymize_ip',
		'title'    => __( 'Anonymize IP Addresses', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => false,
		'category' => 'logging',
		'desc'     => __( 'Remove last octet of IP addresses (e.g., 192.168.1.123 → 192.168.1.0). Recommended for GDPR compliance.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_store_user_agent',
		'title'    => __( 'Store User Agent', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Store browser/device information. Useful for security analysis.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_user_deletion_action',
		'title'    => __( 'On User Deletion', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => 'anonymize',
		'category' => 'logging',
		'options'  => array(
			'anonymize' => __( 'Anonymize user in logs (Recommended)', 'nobloat-user-foundry' ),
			'delete'    => __( 'Delete user logs completely', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Anonymizing preserves audit trail while removing identifiable information apply to the User Activity Log only.', 'nobloat-user-foundry' ),
	),
);

/* Handle test email before rendering */
if ( isset( $_POST['send_test_email'] ) && check_admin_referer( 'nbuf_security_log_test_email' ) ) {
	if ( class_exists( 'NBUF_Security_Log' ) ) {
		$nbuf_test_result = NBUF_Security_Log::send_test_email();

		if ( is_wp_error( $nbuf_test_result ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $nbuf_test_result->get_error_message() ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent successfully! Check your inbox.', 'nobloat-user-foundry' ) . '</p></div>';
		}
	}
}

/* Render settings form */
NBUF_Settings::render_settings( $nbuf_settings_fields, __( 'Logging Settings', 'nobloat-user-foundry' ), 'gdpr', 'logging' );

/* Test Email Form */
?>
<form method="post" action="" style="margin-top: 20px;">
	<?php wp_nonce_field( 'nbuf_security_log_test_email' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="gdpr">
	<input type="hidden" name="nbuf_active_subtab" value="logging">

	<h3><?php esc_html_e( 'Test Security Alert Email', 'nobloat-user-foundry' ); ?></h3>
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

<?php
/* Security Log Database Status */
if ( class_exists( 'NBUF_Security_Log' ) ) {
	$nbuf_security_stats = NBUF_Security_Log::get_stats();
	?>
	<h3><?php esc_html_e( 'Security Log Database Status', 'nobloat-user-foundry' ); ?></h3>

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
							<td><?php echo esc_html( number_format( $nbuf_security_stats['total_entries'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Database Size:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $nbuf_security_stats['database_size'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Oldest Entry:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $nbuf_security_stats['oldest_entry'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Last Cleanup:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $nbuf_security_stats['last_cleanup'] ); ?></td>
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
	<?php
}
