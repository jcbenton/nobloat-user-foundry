<?php
/**
 * GDPR > Notices Tab
 *
 * Policy notice display settings for forms and account pages.
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
if ( isset( $_POST['submit'] ) && check_admin_referer( 'nbuf_gdpr_notices_settings' ) ) {
	/* Policy Display Settings */
	$nbuf_policy_login_enabled = isset( $_POST['nbuf_policy_login_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_login_enabled', $nbuf_policy_login_enabled, true, 'gdpr' );
	$nbuf_policy_login_position = isset( $_POST['nbuf_policy_login_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_login_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_login_position', $nbuf_policy_login_position, true, 'gdpr' );

	$nbuf_policy_registration_enabled = isset( $_POST['nbuf_policy_registration_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_registration_enabled', $nbuf_policy_registration_enabled, true, 'gdpr' );
	$nbuf_policy_registration_position = isset( $_POST['nbuf_policy_registration_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_registration_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_registration_position', $nbuf_policy_registration_position, true, 'gdpr' );

	$nbuf_policy_verify_enabled = isset( $_POST['nbuf_policy_verify_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_verify_enabled', $nbuf_policy_verify_enabled, true, 'gdpr' );
	$nbuf_policy_verify_position = isset( $_POST['nbuf_policy_verify_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_verify_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_verify_position', $nbuf_policy_verify_position, true, 'gdpr' );

	$nbuf_policy_request_reset_enabled = isset( $_POST['nbuf_policy_request_reset_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_request_reset_enabled', $nbuf_policy_request_reset_enabled, true, 'gdpr' );
	$nbuf_policy_request_reset_position = isset( $_POST['nbuf_policy_request_reset_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_request_reset_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_request_reset_position', $nbuf_policy_request_reset_position, true, 'gdpr' );

	$nbuf_policy_reset_enabled = isset( $_POST['nbuf_policy_reset_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_reset_enabled', $nbuf_policy_reset_enabled, true, 'gdpr' );
	$nbuf_policy_reset_position = isset( $_POST['nbuf_policy_reset_position'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_policy_reset_position'] ) ) : 'right';
	NBUF_Options::update( 'nbuf_policy_reset_position', $nbuf_policy_reset_position, true, 'gdpr' );

	$nbuf_policy_account_tab_enabled = isset( $_POST['nbuf_policy_account_tab_enabled'] ) ? 1 : 0;
	NBUF_Options::update( 'nbuf_policy_account_tab_enabled', $nbuf_policy_account_tab_enabled, true, 'gdpr' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Notice settings saved.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$nbuf_policy_login_enabled          = NBUF_Options::get( 'nbuf_policy_login_enabled', true );
$nbuf_policy_login_position         = NBUF_Options::get( 'nbuf_policy_login_position', 'right' );
$nbuf_policy_registration_enabled   = NBUF_Options::get( 'nbuf_policy_registration_enabled', true );
$nbuf_policy_registration_position  = NBUF_Options::get( 'nbuf_policy_registration_position', 'right' );
$nbuf_policy_verify_enabled         = NBUF_Options::get( 'nbuf_policy_verify_enabled', false );
$nbuf_policy_verify_position        = NBUF_Options::get( 'nbuf_policy_verify_position', 'right' );
$nbuf_policy_request_reset_enabled  = NBUF_Options::get( 'nbuf_policy_request_reset_enabled', false );
$nbuf_policy_request_reset_position = NBUF_Options::get( 'nbuf_policy_request_reset_position', 'right' );
$nbuf_policy_reset_enabled          = NBUF_Options::get( 'nbuf_policy_reset_enabled', false );
$nbuf_policy_reset_position         = NBUF_Options::get( 'nbuf_policy_reset_position', 'right' );
$nbuf_policy_account_tab_enabled    = NBUF_Options::get( 'nbuf_policy_account_tab_enabled', false );
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_gdpr_notices_settings' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="gdpr">
	<input type="hidden" name="nbuf_active_subtab" value="notices">

	<h2><?php esc_html_e( 'Policy Notices on Forms', 'nobloat-user-foundry' ); ?></h2>
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
					<input type="checkbox" name="nbuf_policy_login_enabled" value="1" <?php checked( $nbuf_policy_login_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_login_position" style="width: 100%;">
						<option value="right" <?php selected( $nbuf_policy_login_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $nbuf_policy_login_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Registration Form', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_registration_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_registration_enabled" value="1" <?php checked( $nbuf_policy_registration_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_registration_position" style="width: 100%;">
						<option value="right" <?php selected( $nbuf_policy_registration_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $nbuf_policy_registration_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Verification Page', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_verify_page]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_verify_enabled" value="1" <?php checked( $nbuf_policy_verify_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_verify_position" style="width: 100%;">
						<option value="right" <?php selected( $nbuf_policy_verify_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $nbuf_policy_verify_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Request Password Reset', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_request_reset_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_request_reset_enabled" value="1" <?php checked( $nbuf_policy_request_reset_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_request_reset_position" style="width: 100%;">
						<option value="right" <?php selected( $nbuf_policy_request_reset_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $nbuf_policy_request_reset_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td style="padding: 8px 10px;">
					<strong><?php esc_html_e( 'Password Reset Form', 'nobloat-user-foundry' ); ?></strong>
					<code style="font-size: 11px; margin-left: 5px;">[nbuf_reset_form]</code>
				</td>
				<td style="text-align: center; padding: 8px 10px;">
					<input type="checkbox" name="nbuf_policy_reset_enabled" value="1" <?php checked( $nbuf_policy_reset_enabled, 1 ); ?>>
				</td>
				<td style="padding: 8px 10px;">
					<select name="nbuf_policy_reset_position" style="width: 100%;">
						<option value="right" <?php selected( $nbuf_policy_reset_position, 'right' ); ?>><?php esc_html_e( 'Right', 'nobloat-user-foundry' ); ?></option>
						<option value="left" <?php selected( $nbuf_policy_reset_position, 'left' ); ?>><?php esc_html_e( 'Left', 'nobloat-user-foundry' ); ?></option>
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
					<input type="checkbox" name="nbuf_policy_account_tab_enabled" id="nbuf_policy_account_tab_enabled" value="1" <?php checked( $nbuf_policy_account_tab_enabled, 1 ); ?>>
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
