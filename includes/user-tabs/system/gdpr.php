<?php
/**
 * System > GDPR Tab
 *
 * GDPR compliance settings and data privacy controls.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle form submission */
if ( isset( $_POST['submit'] ) && check_admin_referer( 'nbuf_gdpr_settings' ) ) {
	/* Data Retention on User Deletion */
	$delete_audit_logs = isset( $_POST['nbuf_gdpr_delete_audit_logs'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_gdpr_delete_audit_logs'] ) ) : 'anonymize';
	NBUF_Options::update( 'nbuf_gdpr_delete_audit_logs', $delete_audit_logs, true, 'gdpr' );

	/* Data Export Options */
	$include_audit_logs = isset( $_POST['nbuf_gdpr_include_audit_logs'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_audit_logs', $include_audit_logs, true, 'gdpr' );

	$include_2fa_data = isset( $_POST['nbuf_gdpr_include_2fa_data'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_2fa_data', $include_2fa_data, true, 'gdpr' );

	$include_login_attempts = isset( $_POST['nbuf_gdpr_include_login_attempts'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_login_attempts', $include_login_attempts, true, 'gdpr' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'GDPR settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$delete_audit_logs = NBUF_Options::get( 'nbuf_gdpr_delete_audit_logs', 'anonymize' );
$include_audit_logs = NBUF_Options::get( 'nbuf_gdpr_include_audit_logs', true );
$include_2fa_data = NBUF_Options::get( 'nbuf_gdpr_include_2fa_data', true );
$include_login_attempts = NBUF_Options::get( 'nbuf_gdpr_include_login_attempts', false );
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_gdpr_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="gdpr">

	<h2><?php esc_html_e( 'GDPR Compliance', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure data privacy and GDPR compliance settings for user data handling.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- IP Anonymization Reference -->
	<div class="notice notice-info inline" style="margin: 20px 0;">
		<p>
			<strong><?php esc_html_e( 'IP Address Anonymization', 'nobloat-user-foundry' ); ?></strong><br>
			<?php
			printf(
				/* translators: %s: Link to audit log settings */
				esc_html__( 'IP anonymization for audit logs can be configured in %s.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=audit-log' ) ) . '">' . esc_html__( 'Tools > Audit Log', 'nobloat-user-foundry' ) . '</a>'
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'When enabled, IP addresses are anonymized by zeroing the last octet (IPv4: 192.168.1.0) or last 80 bits (IPv6).', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<table class="form-table" role="presentation">
		<!-- Right to be Forgotten -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Right to be Forgotten', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php esc_html_e( 'Audit log handling on user deletion', 'nobloat-user-foundry' ); ?></span>
					</legend>
					<p>
						<label>
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="anonymize" <?php checked( $delete_audit_logs, 'anonymize' ); ?>>
							<strong><?php esc_html_e( 'Anonymize', 'nobloat-user-foundry' ); ?></strong> - <?php esc_html_e( 'Keep audit logs but remove personal data (Recommended)', 'nobloat-user-foundry' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="delete" <?php checked( $delete_audit_logs, 'delete' ); ?>>
							<strong><?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?></strong> - <?php esc_html_e( 'Permanently delete all audit logs for the user', 'nobloat-user-foundry' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="keep" <?php checked( $delete_audit_logs, 'keep' ); ?>>
							<strong><?php esc_html_e( 'Keep', 'nobloat-user-foundry' ); ?></strong> - <?php esc_html_e( 'Retain all audit logs unchanged', 'nobloat-user-foundry' ); ?>
						</label>
					</p>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Controls what happens to audit logs when a user is deleted. Anonymize keeps the logs for security purposes but replaces personal information with "deleted_user".', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Data Export', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Configure which data is included when a user requests a personal data export via WordPress Privacy Tools.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Include Audit Logs -->
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_include_audit_logs">
					<?php esc_html_e( 'Include Audit Logs', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_include_audit_logs" id="nbuf_gdpr_include_audit_logs" value="1" <?php checked( $include_audit_logs, 1 ); ?>>
					<?php esc_html_e( 'Include user activity logs in data export', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, user\'s audit log entries will be included in GDPR data export requests.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Include 2FA Data -->
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_include_2fa_data">
					<?php esc_html_e( 'Include 2FA Settings', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_include_2fa_data" id="nbuf_gdpr_include_2fa_data" value="1" <?php checked( $include_2fa_data, 1 ); ?>>
					<?php esc_html_e( 'Include two-factor authentication settings in data export', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Exports whether 2FA is enabled, method used, and setup date. Secrets and codes are NOT included for security.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Include Login Attempts -->
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_include_login_attempts">
					<?php esc_html_e( 'Include Login Attempts', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_include_login_attempts" id="nbuf_gdpr_include_login_attempts" value="1" <?php checked( $include_login_attempts, 1 ); ?>>
					<?php esc_html_e( 'Include failed login attempt history in data export', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Exports login attempt records with IP addresses and timestamps.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'WordPress Privacy Tools Integration', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'This plugin integrates with WordPress built-in privacy tools for data export and erasure requests.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="widefat" style="max-width: 800px; margin-top: 15px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Feature', 'nobloat-user-foundry' ); ?></th>
				<th><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
				<th><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Data Export', 'nobloat-user-foundry' ); ?></strong></td>
				<td><span style="color: #46b450;">●</span> <?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?></td>
				<td><?php esc_html_e( 'User data can be exported via Tools > Export Personal Data', 'nobloat-user-foundry' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Data Erasure', 'nobloat-user-foundry' ); ?></strong></td>
				<td><span style="color: #46b450;">●</span> <?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?></td>
				<td><?php esc_html_e( 'User data can be erased via Tools > Erase Personal Data', 'nobloat-user-foundry' ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Privacy Policy Guide', 'nobloat-user-foundry' ); ?></strong></td>
				<td><span style="color: #46b450;">●</span> <?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?></td>
				<td><?php esc_html_e( 'Suggested privacy policy text available in Settings > Privacy', 'nobloat-user-foundry' ); ?></td>
			</tr>
		</tbody>
	</table>

	<div class="notice notice-info inline" style="margin-top: 20px;">
		<p>
			<strong><?php esc_html_e( 'How to Use WordPress Privacy Tools:', 'nobloat-user-foundry' ); ?></strong>
		</p>
		<ul style="margin-left: 20px;">
			<li><?php esc_html_e( 'Go to Tools > Export Personal Data to export user data', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Go to Tools > Erase Personal Data to anonymize/delete user data', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Go to Settings > Privacy to view suggested privacy policy text', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>

	<?php submit_button(); ?>
</form>
