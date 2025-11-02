<?php
/**
 * Security > 2FA Email Tab
 *
 * Email-based two-factor authentication settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_method      = NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
$email_length      = NBUF_Options::get( 'nbuf_2fa_email_code_length', 6 );
$email_expiration  = NBUF_Options::get( 'nbuf_2fa_email_expiration', 5 );
$email_rate_limit  = NBUF_Options::get( 'nbuf_2fa_email_rate_limit', 5 );
$email_rate_window = NBUF_Options::get( 'nbuf_2fa_email_rate_window', 15 );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_security_group' );
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="2fa-email">

	<h2><?php esc_html_e( 'Email-Based Two-Factor Authentication', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Send verification codes via email after password login. Users must enter the code to complete login.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email Code Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_email_method">
					<option value="disabled" <?php selected( $email_method, 'disabled' ); ?>>
						<?php esc_html_e( 'Off', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="user_configurable" <?php selected( $email_method, 'user_configurable' ); ?><?php selected( $email_method, 'optional_all' ); ?><?php selected( $email_method, 'optional' ); ?>>
						<?php esc_html_e( 'User Configurable (users can enable in their account)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required" <?php selected( $email_method, 'required' ); ?><?php selected( $email_method, 'required_all' ); ?><?php selected( $email_method, 'required_admin' ); ?>>
						<?php esc_html_e( 'Required (all users must use email 2FA)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Control email-based 2FA availability. When set to "User Configurable", users can enable email 2FA in their account settings.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_code_length" value="<?php echo esc_attr( $email_length ); ?>" min="4" max="8" class="small-text">
				<span><?php esc_html_e( 'digits', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of digits in email verification codes. Default: 6', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Expiration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_expiration" value="<?php echo esc_attr( $email_expiration ); ?>" min="1" max="60" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long verification codes remain valid. Default: 5 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Rate Limiting', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_rate_limit" value="<?php echo esc_attr( $email_rate_limit ); ?>" min="1" max="50" class="small-text">
				<span><?php esc_html_e( 'attempts per', 'nobloat-user-foundry' ); ?></span>
				<input type="number" name="nbuf_2fa_email_rate_window" value="<?php echo esc_attr( $email_rate_window ); ?>" min="1" max="120" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Maximum failed verification attempts before lockout. Default: 5 per 15 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
