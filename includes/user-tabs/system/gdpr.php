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

	/* User Content Deletion Options */
	$delete_user_photos = isset( $_POST['nbuf_gdpr_delete_user_photos'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_delete_user_photos', $delete_user_photos, true, 'gdpr' );

	/* Policy Display Settings */
	$policy_login_enabled = isset( $_POST['nbuf_policy_login_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_login_enabled', $policy_login_enabled, true, 'gdpr' );
	$policy_login_position = isset( $_POST['nbuf_policy_login_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_login_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_login_position', $policy_login_position, true, 'gdpr' );

	$policy_registration_enabled = isset( $_POST['nbuf_policy_registration_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_registration_enabled', $policy_registration_enabled, true, 'gdpr' );
	$policy_registration_position = isset( $_POST['nbuf_policy_registration_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_registration_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_registration_position', $policy_registration_position, true, 'gdpr' );

	$policy_verify_enabled = isset( $_POST['nbuf_policy_verify_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_verify_enabled', $policy_verify_enabled, true, 'gdpr' );
	$policy_verify_position = isset( $_POST['nbuf_policy_verify_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_verify_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_verify_position', $policy_verify_position, true, 'gdpr' );

	$policy_request_reset_enabled = isset( $_POST['nbuf_policy_request_reset_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_request_reset_enabled', $policy_request_reset_enabled, true, 'gdpr' );
	$policy_request_reset_position = isset( $_POST['nbuf_policy_request_reset_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_request_reset_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_request_reset_position', $policy_request_reset_position, true, 'gdpr' );

	$policy_reset_enabled = isset( $_POST['nbuf_policy_reset_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_reset_enabled', $policy_reset_enabled, true, 'gdpr' );
	$policy_reset_position = isset( $_POST['nbuf_policy_reset_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_reset_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_reset_position', $policy_reset_position, true, 'gdpr' );

	$policy_account_tab_enabled = isset( $_POST['nbuf_policy_account_tab_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_account_tab_enabled', $policy_account_tab_enabled, true, 'gdpr' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'GDPR settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$delete_audit_logs      = NBUF_Options::get( 'nbuf_gdpr_delete_audit_logs', 'anonymize' );
$include_audit_logs     = NBUF_Options::get( 'nbuf_gdpr_include_audit_logs', true );
$include_2fa_data       = NBUF_Options::get( 'nbuf_gdpr_include_2fa_data', true );
$include_login_attempts = NBUF_Options::get( 'nbuf_gdpr_include_login_attempts', false );
$delete_user_photos = NBUF_Options::get( 'nbuf_gdpr_delete_user_photos', true );

/* Policy display settings */
$policy_login_enabled          = NBUF_Options::get( 'nbuf_policy_login_enabled', true );
$policy_login_position         = NBUF_Options::get( 'nbuf_policy_login_position', 'right' );
$policy_registration_enabled   = NBUF_Options::get( 'nbuf_policy_registration_enabled', true );
$policy_registration_position  = NBUF_Options::get( 'nbuf_policy_registration_position', 'right' );
$policy_verify_enabled         = NBUF_Options::get( 'nbuf_policy_verify_enabled', false );
$policy_verify_position        = NBUF_Options::get( 'nbuf_policy_verify_position', 'right' );
$policy_request_reset_enabled  = NBUF_Options::get( 'nbuf_policy_request_reset_enabled', false );
$policy_request_reset_position = NBUF_Options::get( 'nbuf_policy_request_reset_position', 'right' );
$policy_reset_enabled          = NBUF_Options::get( 'nbuf_policy_reset_enabled', false );
$policy_reset_position         = NBUF_Options::get( 'nbuf_policy_reset_position', 'right' );
$policy_account_tab_enabled    = NBUF_Options::get( 'nbuf_policy_account_tab_enabled', false );
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_gdpr_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="gdpr">

	<h2><?php esc_html_e( 'GDPR Compliance', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure data privacy and GDPR compliance settings for user data handling.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- IP Anonymization Reference -->
		<tr>
			<th scope="row">
				<?php esc_html_e( 'IP Address Anonymization', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<?php
				printf(
					/* translators: %s: Link to audit log settings */
					esc_html__( 'IP anonymization for audit logs can be configured in %s.', 'nobloat-user-foundry' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=audit-log' ) ) . '">' . esc_html__( 'Tools → Audit Log', 'nobloat-user-foundry' ) . '</a>'
				);
				?>
			</td>
		</tr>
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

		<!-- Delete User Photos on Account Deletion -->
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_delete_user_photos">
					<?php esc_html_e( 'Delete User Photos', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_delete_user_photos" id="nbuf_gdpr_delete_user_photos" value="1" <?php checked( $delete_user_photos, 1 ); ?>>
					<?php esc_html_e( 'Delete user photos when user account is deleted', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, all photos in /uploads/nobloat/users/{user_id}/ will be permanently deleted when the user account is removed. Recommended for GDPR compliance.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<hr style="margin: 30px 0;">

	<h3><?php esc_html_e( 'Data Export Settings', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Configure which data is included when exporting a user\'s personal data.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
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
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_include_2fa_data">
					<?php esc_html_e( 'Include 2FA Settings', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_include_2fa_data" id="nbuf_gdpr_include_2fa_data" value="1" <?php checked( $include_2fa_data, 1 ); ?>>
					<?php esc_html_e( 'Include two-factor authentication settings (secrets excluded)', 'nobloat-user-foundry' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="nbuf_gdpr_include_login_attempts">
					<?php esc_html_e( 'Include Login Attempts', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_gdpr_include_login_attempts" id="nbuf_gdpr_include_login_attempts" value="1" <?php checked( $include_login_attempts, 1 ); ?>>
					<?php esc_html_e( 'Include failed login attempt history', 'nobloat-user-foundry' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<hr style="margin: 30px 0;">

	<h3><?php esc_html_e( 'Privacy Tools', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Export or erase personal data for a specific user. These tools integrate with WordPress core privacy features.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Export Personal Data', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>" class="button">
					<?php esc_html_e( 'Export Personal Data', 'nobloat-user-foundry' ); ?>
				</a>
				<p class="description">
					<?php esc_html_e( 'Generate a downloadable file containing all personal data for a user.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Erase Personal Data', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>" class="button">
					<?php esc_html_e( 'Erase Personal Data', 'nobloat-user-foundry' ); ?>
				</a>
				<p class="description">
					<?php esc_html_e( 'Anonymize or delete all personal data for a user.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<hr style="margin: 30px 0;">

	<h3><?php esc_html_e( 'Policy Notices on Forms', 'nobloat-user-foundry' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Display Privacy Policy and Terms of Use in a tabbed panel alongside your forms. Templates can be customized in Policy Templates tab.', 'nobloat-user-foundry' ); ?>
	</p>

	<table style="max-width: 700px; margin-top: 15px; border-collapse: collapse;">
		<thead>
			<tr>
				<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Form', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 80px; text-align: center; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Enable', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 120px; text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Position', 'nobloat-user-foundry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Login Form', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_login_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_login_enabled" value="1" <?php checked( $policy_login_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_login_position" style="width: 100%;">
						<option value="right" <?php selected( $policy_login_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $policy_login_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Registration Form', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_registration_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_registration_enabled" value="1" <?php checked( $policy_registration_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_registration_position" style="width: 100%;">
						<option value="right" <?php selected( $policy_registration_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $policy_registration_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Verification Page', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_verify_page]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_verify_enabled" value="1" <?php checked( $policy_verify_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_verify_position" style="width: 100%;">
						<option value="right" <?php selected( $policy_verify_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $policy_verify_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Request Password Reset', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_request_reset_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_request_reset_enabled" value="1" <?php checked( $policy_request_reset_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_request_reset_position" style="width: 100%;">
						<option value="right" <?php selected( $policy_request_reset_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $policy_request_reset_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Password Reset Form', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_reset_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_reset_enabled" value="1" <?php checked( $policy_reset_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_reset_position" style="width: 100%;">
						<option value="right" <?php selected( $policy_reset_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $policy_reset_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
		</tbody>
	</table>

	<hr style="margin: 30px 0;">

	<h3><?php esc_html_e( 'Account Page', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="nbuf_policy_account_tab_enabled">
					<?php esc_html_e( 'Policies Tab', 'nobloat-user-foundry' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_policy_account_tab_enabled" id="nbuf_policy_account_tab_enabled" value="1" <?php checked( $policy_account_tab_enabled, 1 ); ?>>
					<?php esc_html_e( 'Add Policies tab to account page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Displays Privacy Policy and Terms of Use side-by-side in a "Policies" tab on the user account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
