<?php
/**
 * GDPR > Privacy Tab
 *
 * GDPR compliance settings for data handling and user deletion.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/GDPR
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Security: Verify user has permission to access this page */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
}

/* Handle form submission */
if ( isset( $_POST['submit'] ) && check_admin_referer( 'nbuf_gdpr_privacy_settings' ) ) {
	/* Data Retention on User Deletion */
	$nbuf_delete_audit_logs = isset( $_POST['nbuf_gdpr_delete_audit_logs'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_gdpr_delete_audit_logs'] ) ) : 'anonymize';
	NBUF_Options::update( 'nbuf_gdpr_delete_audit_logs', $nbuf_delete_audit_logs, true, 'gdpr' );

	/* Data Export Options */
	$nbuf_include_audit_logs = isset( $_POST['nbuf_gdpr_include_audit_logs'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_audit_logs', $nbuf_include_audit_logs, true, 'gdpr' );

	$nbuf_include_2fa_data = isset( $_POST['nbuf_gdpr_include_2fa_data'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_2fa_data', $nbuf_include_2fa_data, true, 'gdpr' );

	$nbuf_include_login_attempts = isset( $_POST['nbuf_gdpr_include_login_attempts'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_include_login_attempts', $nbuf_include_login_attempts, true, 'gdpr' );

	/* User Content Deletion Options */
	$nbuf_delete_user_photos = isset( $_POST['nbuf_gdpr_delete_user_photos'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_gdpr_delete_user_photos', $nbuf_delete_user_photos, true, 'gdpr' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Privacy settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$nbuf_delete_audit_logs      = NBUF_Options::get( 'nbuf_gdpr_delete_audit_logs', 'anonymize' );
$nbuf_include_audit_logs     = NBUF_Options::get( 'nbuf_gdpr_include_audit_logs', true );
$nbuf_include_2fa_data       = NBUF_Options::get( 'nbuf_gdpr_include_2fa_data', true );
$nbuf_include_login_attempts = NBUF_Options::get( 'nbuf_gdpr_include_login_attempts', false );
$nbuf_delete_user_photos     = NBUF_Options::get( 'nbuf_gdpr_delete_user_photos', true );
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_gdpr_privacy_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="gdpr">
	<input type="hidden" name="nbuf_active_subtab" value="privacy">

	<h2><?php esc_html_e( 'Privacy Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure how User Foundry handles personal data for GDPR compliance.', 'nobloat-user-foundry' ); ?>
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
					/* translators: %s: Link to logging settings */
					esc_html__( 'IP anonymization for all logs can be configured in %s.', 'nobloat-user-foundry' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=nbuf-settings&tab=gdpr&subtab=logging' ) ) . '">' . esc_html__( 'GDPR > Logging', 'nobloat-user-foundry' ) . '</a>'
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
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="anonymize" <?php checked( $nbuf_delete_audit_logs, 'anonymize' ); ?>>
							<strong><?php esc_html_e( 'Anonymize', 'nobloat-user-foundry' ); ?></strong> &mdash; <?php esc_html_e( 'Keep audit logs but remove personal data (Recommended)', 'nobloat-user-foundry' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="delete" <?php checked( $nbuf_delete_audit_logs, 'delete' ); ?>>
							<strong><?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?></strong> &mdash; <?php esc_html_e( 'Permanently delete all audit logs for the user', 'nobloat-user-foundry' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="nbuf_gdpr_delete_audit_logs" value="keep" <?php checked( $nbuf_delete_audit_logs, 'keep' ); ?>>
							<strong><?php esc_html_e( 'Keep', 'nobloat-user-foundry' ); ?></strong> &mdash; <?php esc_html_e( 'Retain all audit logs unchanged', 'nobloat-user-foundry' ); ?>
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
					<input type="checkbox" name="nbuf_gdpr_delete_user_photos" id="nbuf_gdpr_delete_user_photos" value="1" <?php checked( $nbuf_delete_user_photos, 1 ); ?>>
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
		<?php esc_html_e( 'Configure which data is included when exporting a user\'s personal data via Tools > Export Personal Data.', 'nobloat-user-foundry' ); ?>
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
					<input type="checkbox" name="nbuf_gdpr_include_audit_logs" id="nbuf_gdpr_include_audit_logs" value="1" <?php checked( $nbuf_include_audit_logs, 1 ); ?>>
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
					<input type="checkbox" name="nbuf_gdpr_include_2fa_data" id="nbuf_gdpr_include_2fa_data" value="1" <?php checked( $nbuf_include_2fa_data, 1 ); ?>>
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
					<input type="checkbox" name="nbuf_gdpr_include_login_attempts" id="nbuf_gdpr_include_login_attempts" value="1" <?php checked( $nbuf_include_login_attempts, 1 ); ?>>
					<?php esc_html_e( 'Include failed login attempt history', 'nobloat-user-foundry' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
