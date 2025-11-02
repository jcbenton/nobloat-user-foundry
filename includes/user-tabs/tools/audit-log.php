<?php
/**
 * Tools > Audit Log Tab
 *
 * Audit log configuration and management settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle form submission */
if ( isset( $_POST['submit'] ) && check_admin_referer( 'nbuf_audit_log_settings' ) ) {
	/* Enable/Disable audit logging */
	$enabled = isset( $_POST['nbuf_audit_log_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_audit_log_enabled', $enabled, true, 'audit_log' );

	/* Retention period */
	$retention = isset( $_POST['nbuf_audit_log_retention'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_audit_log_retention'] ) ) : '90days';
	NBUF_Options::update( 'nbuf_audit_log_retention', $retention, true, 'audit_log' );

	/* Events to track */
	$events = array(
		'authentication' => isset( $_POST['nbuf_audit_log_events_authentication'] ) ? 1 : 0,
		'verification'   => isset( $_POST['nbuf_audit_log_events_verification'] ) ? 1 : 0,
		'passwords'      => isset( $_POST['nbuf_audit_log_events_passwords'] ) ? 1 : 0,
		'2fa'            => isset( $_POST['nbuf_audit_log_events_2fa'] ) ? 1 : 0,
		'account_status' => isset( $_POST['nbuf_audit_log_events_account_status'] ) ? 1 : 0,
		'profile'        => isset( $_POST['nbuf_audit_log_events_profile'] ) ? 1 : 0,
	);
	NBUF_Options::update( 'nbuf_audit_log_events', $events, true, 'audit_log' );

	/* Privacy & Performance */
	$store_user_agent = isset( $_POST['nbuf_audit_log_store_user_agent'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_audit_log_store_user_agent', $store_user_agent, true, 'audit_log' );

	$anonymize_ip = isset( $_POST['nbuf_audit_log_anonymize_ip'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_audit_log_anonymize_ip', $anonymize_ip, true, 'audit_log' );

	$max_message_length = isset( $_POST['nbuf_audit_log_max_message_length'] ) ? intval( $_POST['nbuf_audit_log_max_message_length'] ) : 500;
	NBUF_Options::update( 'nbuf_audit_log_max_message_length', $max_message_length, true, 'audit_log' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Audit log settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$enabled = NBUF_Options::get( 'nbuf_audit_log_enabled', true );
$retention = NBUF_Options::get( 'nbuf_audit_log_retention', '90days' );
$events = NBUF_Options::get( 'nbuf_audit_log_events', array(
	'authentication' => true,
	'verification'   => true,
	'passwords'      => true,
	'2fa'            => true,
	'account_status' => true,
	'profile'        => false,
) );
$store_user_agent = NBUF_Options::get( 'nbuf_audit_log_store_user_agent', true );
$anonymize_ip = NBUF_Options::get( 'nbuf_audit_log_anonymize_ip', false );
$max_message_length = NBUF_Options::get( 'nbuf_audit_log_max_message_length', 500 );

/* Get statistics */
$stats = NBUF_Audit_Log::get_stats();
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_audit_log_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="tools">
	<input type="hidden" name="nbuf_active_subtab" value="audit-log">

	<h2><?php esc_html_e( 'Audit Log Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure audit logging to track user activity and security events.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Enable Audit Logging -->
		<tr>
			<th scope="row">
				<label for="nbuf_audit_log_enabled">
					<?php esc_html_e( 'Enable Audit Logging', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_audit_log_enabled" id="nbuf_audit_log_enabled" value="1" <?php checked( $enabled, 1 ); ?>>
					<?php esc_html_e( 'Track user activity and security events', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, the plugin will log authentication, verification, password changes, 2FA events, and more.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Retention Period -->
		<tr>
			<th scope="row">
				<label for="nbuf_audit_log_retention">
					<?php esc_html_e( 'Log Retention Period', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<select name="nbuf_audit_log_retention" id="nbuf_audit_log_retention">
					<option value="7days" <?php selected( $retention, '7days' ); ?>><?php esc_html_e( '7 Days', 'nobloat-user-foundry' ); ?></option>
					<option value="30days" <?php selected( $retention, '30days' ); ?>><?php esc_html_e( '30 Days', 'nobloat-user-foundry' ); ?></option>
					<option value="90days" <?php selected( $retention, '90days' ); ?>><?php esc_html_e( '90 Days (Recommended)', 'nobloat-user-foundry' ); ?></option>
					<option value="180days" <?php selected( $retention, '180days' ); ?>><?php esc_html_e( '6 Months', 'nobloat-user-foundry' ); ?></option>
					<option value="1year" <?php selected( $retention, '1year' ); ?>><?php esc_html_e( '1 Year', 'nobloat-user-foundry' ); ?></option>
					<option value="2years" <?php selected( $retention, '2years' ); ?>><?php esc_html_e( '2 Years', 'nobloat-user-foundry' ); ?></option>
					<option value="forever" <?php selected( $retention, 'forever' ); ?>><?php esc_html_e( 'Forever (Not Recommended)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Logs older than this period will be automatically deleted during daily cleanup.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Events to Track -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Events to Track', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="nbuf_audit_log_events_authentication" value="1" <?php checked( ! empty( $events['authentication'] ), true ); ?>>
						<?php esc_html_e( 'Authentication (login, logout)', 'nobloat-user-foundry' ); ?>
					</label><br>

					<label>
						<input type="checkbox" name="nbuf_audit_log_events_verification" value="1" <?php checked( ! empty( $events['verification'] ), true ); ?>>
						<?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?>
					</label><br>

					<label>
						<input type="checkbox" name="nbuf_audit_log_events_passwords" value="1" <?php checked( ! empty( $events['passwords'] ), true ); ?>>
						<?php esc_html_e( 'Password Changes & Resets', 'nobloat-user-foundry' ); ?>
					</label><br>

					<label>
						<input type="checkbox" name="nbuf_audit_log_events_2fa" value="1" <?php checked( ! empty( $events['2fa'] ), true ); ?>>
						<?php esc_html_e( 'Two-Factor Authentication (2FA)', 'nobloat-user-foundry' ); ?>
					</label><br>

					<label>
						<input type="checkbox" name="nbuf_audit_log_events_account_status" value="1" <?php checked( ! empty( $events['account_status'] ), true ); ?>>
						<?php esc_html_e( 'Account Status (disable, enable, expiration)', 'nobloat-user-foundry' ); ?>
					</label><br>

					<label>
						<input type="checkbox" name="nbuf_audit_log_events_profile" value="1" <?php checked( ! empty( $events['profile'] ), true ); ?>>
						<?php esc_html_e( 'Profile Updates (can be noisy)', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which types of events should be logged.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Privacy & Performance', 'nobloat-user-foundry' ); ?></h3>

	<table class="form-table" role="presentation">
		<!-- Store User Agent -->
		<tr>
			<th scope="row">
				<label for="nbuf_audit_log_store_user_agent">
					<?php esc_html_e( 'Store User Agent', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_audit_log_store_user_agent" id="nbuf_audit_log_store_user_agent" value="1" <?php checked( $store_user_agent, 1 ); ?>>
					<?php esc_html_e( 'Store browser and device information', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Logs browser name, version, and platform. Useful for security analysis.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Anonymize IP -->
		<tr>
			<th scope="row">
				<label for="nbuf_audit_log_anonymize_ip">
					<?php esc_html_e( 'Anonymize IP Addresses', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_audit_log_anonymize_ip" id="nbuf_audit_log_anonymize_ip" value="1" <?php checked( $anonymize_ip, 1 ); ?>>
					<?php esc_html_e( 'Remove last octet of IP addresses (GDPR compliance)', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Example: 192.168.1.123 becomes 192.168.1.0. Reduces privacy concerns while maintaining some geographic information.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Max Message Length -->
		<tr>
			<th scope="row">
				<label for="nbuf_audit_log_max_message_length">
					<?php esc_html_e( 'Max Message Length', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<input type="number" name="nbuf_audit_log_max_message_length" id="nbuf_audit_log_max_message_length" value="<?php echo esc_attr( $max_message_length ); ?>" min="100" max="2000" class="small-text">
				<?php esc_html_e( 'characters', 'nobloat-user-foundry' ); ?>
				<p class="description">
					<?php esc_html_e( 'Long messages will be truncated to save database space. Recommended: 500 characters.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Database Maintenance', 'nobloat-user-foundry' ); ?></h3>

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
						/* translators: %s: Audit log page URL */
						esc_html__( 'View logs and purge options on the %s page.', 'nobloat-user-foundry' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-user-log' ) ) . '">' . esc_html__( 'User Log', 'nobloat-user-foundry' ) . '</a>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
