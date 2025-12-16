<?php
/**
 * Security Tab
 *
 * Controls login attempt limiting, password strength requirements,
 * and enforcement options for the NoBloat User Foundry plugin.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Login limiting settings */
$nbuf_enable_login_limiting  = NBUF_Options::get( 'nbuf_enable_login_limiting', true );
$nbuf_login_max_attempts     = NBUF_Options::get( 'nbuf_login_max_attempts', 5 );
$nbuf_login_lockout_duration = NBUF_Options::get( 'nbuf_login_lockout_duration', 10 );

/* Password strength settings */
$nbuf_password_requirements_enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', false );
$nbuf_password_min_strength         = NBUF_Options::get( 'nbuf_password_min_strength', 'medium' );
$nbuf_password_min_length           = NBUF_Options::get( 'nbuf_password_min_length', 8 );
$nbuf_password_require_uppercase    = NBUF_Options::get( 'nbuf_password_require_uppercase', false );
$nbuf_password_require_lowercase    = NBUF_Options::get( 'nbuf_password_require_lowercase', false );
$nbuf_password_require_numbers      = NBUF_Options::get( 'nbuf_password_require_numbers', false );
$nbuf_password_require_special      = NBUF_Options::get( 'nbuf_password_require_special', false );

/* Enforcement settings */
$nbuf_password_enforce_registration   = NBUF_Options::get( 'nbuf_password_enforce_registration', true );
$nbuf_password_enforce_profile_change = NBUF_Options::get( 'nbuf_password_enforce_profile_change', true );
$nbuf_password_enforce_reset          = NBUF_Options::get( 'nbuf_password_enforce_reset', true );
$nbuf_password_admin_bypass           = NBUF_Options::get( 'nbuf_password_admin_bypass', false );

/* Weak password migration settings */
$nbuf_password_force_weak_change = NBUF_Options::get( 'nbuf_password_force_weak_change', false );
$nbuf_password_check_timing      = NBUF_Options::get( 'nbuf_password_check_timing', 'once' );
$nbuf_password_grace_period      = NBUF_Options::get( 'nbuf_password_grace_period', 7 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<h2><?php esc_html_e( 'Login Protection', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Attempt Limiting', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_login_limiting" value="1" <?php checked( $nbuf_enable_login_limiting, true ); ?>>
					<?php esc_html_e( 'Enable login attempt limiting', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Protect against brute force attacks by limiting failed login attempts.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Maximum Attempts', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_max_attempts" value="<?php echo esc_attr( $nbuf_login_max_attempts ); ?>" min="1" max="100" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of failed login attempts allowed before the IP address is blocked. Default: 5', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Lockout Duration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_login_lockout_duration" value="<?php echo esc_attr( $nbuf_login_lockout_duration ); ?>" min="1" max="1440" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long to block the IP address after exceeding max attempts. Default: 10 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Password Strength Requirements', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Password Requirements', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_password_requirements_enabled" value="1" <?php checked( $nbuf_password_requirements_enabled, true ); ?> id="nbuf_password_requirements_enabled">
					<?php esc_html_e( 'Enable password strength requirements', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enforce minimum password strength and character requirements for better security.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Minimum Password Strength', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_password_min_strength">
					<option value="none" <?php selected( $nbuf_password_min_strength, 'none' ); ?>>
						<?php esc_html_e( 'None - Any strength', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="weak" <?php selected( $nbuf_password_min_strength, 'weak' ); ?>>
						<?php esc_html_e( 'Weak', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="medium" <?php selected( $nbuf_password_min_strength, 'medium' ); ?>>
						<?php esc_html_e( 'Medium', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="strong" <?php selected( $nbuf_password_min_strength, 'strong' ); ?>>
						<?php esc_html_e( 'Strong', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="very-strong" <?php selected( $nbuf_password_min_strength, 'very-strong' ); ?>>
						<?php esc_html_e( 'Very Strong', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Minimum password strength as measured by WordPress password meter. Default: Medium', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Minimum Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_password_min_length" value="<?php echo esc_attr( $nbuf_password_min_length ); ?>" min="1" max="128" class="small-text">
				<span><?php esc_html_e( 'characters', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Minimum number of characters required. Default: 8', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Required Character Types', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_require_uppercase" value="1" <?php checked( $nbuf_password_require_uppercase, true ); ?>>
						<?php esc_html_e( 'Require uppercase letters (A-Z)', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_require_lowercase" value="1" <?php checked( $nbuf_password_require_lowercase, true ); ?>>
						<?php esc_html_e( 'Require lowercase letters (a-z)', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_require_numbers" value="1" <?php checked( $nbuf_password_require_numbers, true ); ?>>
						<?php esc_html_e( 'Require numbers (0-9)', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_require_special" value="1" <?php checked( $nbuf_password_require_special, true ); ?>>
						<?php esc_html_e( 'Require special characters (!@#$%^&*)', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Select which character types must be included in passwords.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Password Enforcement', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Apply Requirements To', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_enforce_registration" value="1" <?php checked( $nbuf_password_enforce_registration, true ); ?>>
						<?php esc_html_e( 'New user registration', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_enforce_profile_change" value="1" <?php checked( $nbuf_password_enforce_profile_change, true ); ?>>
						<?php esc_html_e( 'Password changes (user profile)', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display:block;margin-bottom:6px;">
						<input type="checkbox" name="nbuf_password_enforce_reset" value="1" <?php checked( $nbuf_password_enforce_reset, true ); ?>>
						<?php esc_html_e( 'Password resets', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Choose where password strength requirements should be enforced.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Administrator Bypass', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_password_admin_bypass" value="1" <?php checked( $nbuf_password_admin_bypass, true ); ?>>
					<?php esc_html_e( 'Allow administrators to bypass password requirements', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users with "manage_options" capability can skip password strength requirements.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Weak Password Migration (Optional)', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Force Password Change', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_password_force_weak_change" value="1" <?php checked( $nbuf_password_force_weak_change, true ); ?> id="nbuf_password_force_weak_change">
					<?php esc_html_e( 'Force password change for users with weak passwords', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Existing users with weak passwords will be required to change their password on login.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Check Timing', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label style="display:block;margin-bottom:6px;">
					<input type="radio" name="nbuf_password_check_timing" value="once" <?php checked( $nbuf_password_check_timing, 'once' ); ?>>
					<?php esc_html_e( 'On next login (check once, flag account)', 'nobloat-user-foundry' ); ?>
				</label>
				<label style="display:block;margin-bottom:6px;">
					<input type="radio" name="nbuf_password_check_timing" value="every" <?php checked( $nbuf_password_check_timing, 'every' ); ?>>
					<?php esc_html_e( 'Every login (check until password changed)', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Choose when to check existing users for weak passwords.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Grace Period', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_password_grace_period" value="<?php echo esc_attr( $nbuf_password_grace_period ); ?>" min="0" max="365" class="small-text">
				<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Users see warnings during grace period, then must change password. Set to 0 for immediate enforcement. Default: 7', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- Two-Factor Authentication Settings -->
	<h2><?php esc_html_e( 'Two-Factor Authentication (2FA)', 'nobloat-user-foundry' ); ?></h2>
	<p class="description" style="margin-bottom: 20px;">
		<?php esc_html_e( 'Require users to enter a verification code after logging in with their password. Choose between email codes or authenticator apps (TOTP).', 'nobloat-user-foundry' ); ?>
	</p>

	<h3><?php esc_html_e( 'Email-Based 2FA', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table">
		<?php
		$nbuf_email_method      = NBUF_Options::get( 'nbuf_2fa_email_method', 'disabled' );
		$nbuf_email_length      = NBUF_Options::get( 'nbuf_2fa_email_code_length', 6 );
		$nbuf_email_expiration  = NBUF_Options::get( 'nbuf_2fa_email_expiration', 10 );
		$nbuf_email_rate_limit  = NBUF_Options::get( 'nbuf_2fa_email_rate_limit', 5 );
		$nbuf_email_rate_window = NBUF_Options::get( 'nbuf_2fa_email_rate_window', 15 );
		?>
		<tr>
			<th><?php esc_html_e( 'Email Code Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_email_method">
					<option value="disabled" <?php selected( $nbuf_email_method, 'disabled' ); ?>>
						<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required_admin" <?php selected( $nbuf_email_method, 'required_admin' ); ?>>
						<?php esc_html_e( 'Required for Administrators', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="optional_all" <?php selected( $nbuf_email_method, 'optional_all' ); ?>>
						<?php esc_html_e( 'Optional for All Users', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required_all" <?php selected( $nbuf_email_method, 'required_all' ); ?>>
						<?php esc_html_e( 'Required for All Users', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Control who can or must use email-based 2FA codes.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_code_length" value="<?php echo esc_attr( $nbuf_email_length ); ?>" min="4" max="8" class="small-text">
				<span><?php esc_html_e( 'digits', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of digits in email verification codes. Default: 6', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Expiration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_expiration" value="<?php echo esc_attr( $nbuf_email_expiration ); ?>" min="1" max="60" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long verification codes remain valid. Default: 10 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Rate Limiting', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_email_rate_limit" value="<?php echo esc_attr( $nbuf_email_rate_limit ); ?>" min="1" max="50" class="small-text">
				<span><?php esc_html_e( 'attempts per', 'nobloat-user-foundry' ); ?></span>
				<input type="number" name="nbuf_2fa_email_rate_window" value="<?php echo esc_attr( $nbuf_email_rate_window ); ?>" min="1" max="120" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Maximum failed verification attempts before lockout. Default: 5 per 15 minutes', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Authenticator App (TOTP)', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table">
		<?php
		$nbuf_totp_method    = NBUF_Options::get( 'nbuf_2fa_totp_method', 'disabled' );
		$nbuf_totp_length    = NBUF_Options::get( 'nbuf_2fa_totp_code_length', 6 );
		$nbuf_totp_window    = NBUF_Options::get( 'nbuf_2fa_totp_time_window', 30 );
		$nbuf_totp_tolerance = NBUF_Options::get( 'nbuf_2fa_totp_tolerance', 1 );
		$nbuf_totp_qr_size   = NBUF_Options::get( 'nbuf_2fa_totp_qr_size', 200 );
		?>
		<tr>
			<th><?php esc_html_e( 'TOTP Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_method">
					<option value="disabled" <?php selected( $nbuf_totp_method, 'disabled' ); ?>>
						<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="optional" <?php selected( $nbuf_totp_method, 'optional' ); ?>>
						<?php esc_html_e( 'Allow Users to Enable (Optional)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required_admin" <?php selected( $nbuf_totp_method, 'required_admin' ); ?>>
						<?php esc_html_e( 'Required for Administrators', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required_all" <?php selected( $nbuf_totp_method, 'required_all' ); ?>>
						<?php esc_html_e( 'Required for All Users', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Control who can use authenticator apps (Google Authenticator, Authy, etc.).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_code_length">
					<option value="6" <?php selected( $nbuf_totp_length, 6 ); ?>>6 <?php esc_html_e( 'digits (standard)', 'nobloat-user-foundry' ); ?></option>
					<option value="8" <?php selected( $nbuf_totp_length, 8 ); ?>>8 <?php esc_html_e( 'digits (extra secure)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Length of TOTP codes. Most apps use 6 digits. Default: 6', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Time Window', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_time_window">
					<option value="30" <?php selected( $nbuf_totp_window, 30 ); ?>>30 <?php esc_html_e( 'seconds (standard)', 'nobloat-user-foundry' ); ?></option>
					<option value="60" <?php selected( $nbuf_totp_window, 60 ); ?>>60 <?php esc_html_e( 'seconds', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How often codes change. Most apps use 30 seconds. Default: 30', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Clock Tolerance', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_2fa_totp_tolerance">
					<option value="0" <?php selected( $nbuf_totp_tolerance, 0 ); ?>>±0 <?php esc_html_e( 'windows (strict)', 'nobloat-user-foundry' ); ?></option>
					<option value="1" <?php selected( $nbuf_totp_tolerance, 1 ); ?>>±1 <?php esc_html_e( 'window (recommended)', 'nobloat-user-foundry' ); ?></option>
					<option value="2" <?php selected( $nbuf_totp_tolerance, 2 ); ?>>±2 <?php esc_html_e( 'windows (lenient)', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Allow codes from previous/next time windows to account for clock drift. Default: ±1', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'QR Code Size', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_totp_qr_size" value="<?php echo esc_attr( $nbuf_totp_qr_size ); ?>" min="100" max="500" step="50" class="small-text">
				<span><?php esc_html_e( 'pixels', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Size of QR codes shown during TOTP setup. Default: 200px', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'QR Code Generation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'QR codes are generated using api.qrserver.com, a free and reliable external service.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Backup Codes', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table">
		<?php
		$nbuf_backup_enabled = NBUF_Options::get( 'nbuf_2fa_backup_enabled', true );
		$nbuf_backup_count   = NBUF_Options::get( 'nbuf_2fa_backup_count', 4 );
		$nbuf_backup_length  = NBUF_Options::get( 'nbuf_2fa_backup_length', 32 );
		?>
		<tr>
			<th><?php esc_html_e( 'Enable Backup Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_backup_enabled" value="1" <?php checked( $nbuf_backup_enabled, true ); ?>>
					<?php esc_html_e( 'Allow users to generate backup codes', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'One-time use codes for emergency access if user loses authenticator device.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Number of Codes', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_count" value="<?php echo esc_attr( $nbuf_backup_count ); ?>" min="4" max="20" class="small-text">
				<span><?php esc_html_e( 'codes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of backup codes to generate. Default: 4', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Code Length', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_backup_length" value="<?php echo esc_attr( $nbuf_backup_length ); ?>" min="8" max="64" class="small-text">
				<span><?php esc_html_e( 'characters', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Length of each backup code. Longer codes are more secure. Default: 32', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'General 2FA Options', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table">
		<?php
		$nbuf_device_trust     = NBUF_Options::get( 'nbuf_2fa_device_trust', true );
		$nbuf_admin_bypass     = NBUF_Options::get( 'nbuf_2fa_admin_bypass', false );
		$nbuf_lockout_attempts = NBUF_Options::get( 'nbuf_2fa_lockout_attempts', 5 );
		$nbuf_grace_period     = NBUF_Options::get( 'nbuf_2fa_grace_period', 7 );
		?>
		<tr>
			<th><?php esc_html_e( 'Device Trust', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_device_trust" value="1" <?php checked( $nbuf_device_trust, true ); ?>>
					<?php esc_html_e( 'Allow users to trust devices for 30 days', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Trusted devices will not require 2FA for 30 days. Uses secure cookies.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Administrator Bypass', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_admin_bypass" value="1" <?php checked( $nbuf_admin_bypass, true ); ?>>
					<?php esc_html_e( 'Allow administrators to bypass 2FA requirements', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users with "manage_options" capability can skip 2FA. Not recommended for security.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Failed Attempt Lockout', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_lockout_attempts" value="<?php echo esc_attr( $nbuf_lockout_attempts ); ?>" min="3" max="20" class="small-text">
				<span><?php esc_html_e( 'attempts', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Lock out after this many failed 2FA attempts. Default: 5', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Setup Grace Period', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_2fa_grace_period" value="<?php echo esc_attr( $nbuf_grace_period ); ?>" min="0" max="30" class="small-text">
				<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'When 2FA is made required, users have this many days to set it up. Default: 7', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Admin Notifications', 'nobloat-user-foundry' ); ?></h3>
	<table class="form-table">
		<?php
		$nbuf_notify_lockout = NBUF_Options::get( 'nbuf_2fa_notify_lockout', true );
		$nbuf_notify_disable = NBUF_Options::get( 'nbuf_2fa_notify_disable', false );
		?>
		<tr>
			<th><?php esc_html_e( 'Notify on Lockout', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_notify_lockout" value="1" <?php checked( $nbuf_notify_lockout, true ); ?>>
					<?php esc_html_e( 'Email admins when a user is locked out from failed 2FA attempts', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Sends notification to site admin email when users exceed maximum 2FA verification attempts.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Notify on Self-Disable', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_2fa_notify_disable" value="1" <?php checked( $nbuf_notify_disable, true ); ?>>
					<?php esc_html_e( 'Email admins when a user disables their own 2FA', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Sends notification to site admin email when users turn off 2FA from their account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- Account Verification & Approval -->
	<h2><?php esc_html_e( 'Account Verification & Approval', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<?php
		$nbuf_require_verification   = NBUF_Options::get( 'nbuf_require_verification', false );
		$nbuf_require_approval       = NBUF_Options::get( 'nbuf_require_approval', false );
		$nbuf_delete_unverified_days = NBUF_Options::get( 'nbuf_delete_unverified_days', 5 );
		$nbuf_new_user_default_role  = NBUF_Options::get( 'nbuf_new_user_default_role', 'subscriber' );
		?>
		<tr>
			<th><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_require_verification" value="1" <?php checked( $nbuf_require_verification, true ); ?>>
					<?php esc_html_e( 'Require email verification for new accounts', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users must verify their email address before they can log in. A verification link will be emailed to them upon registration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Admin Approval', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_require_approval" value="1" <?php checked( $nbuf_require_approval, true ); ?>>
					<?php esc_html_e( 'Require administrator approval for new accounts', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'New user accounts must be manually approved by an administrator before they can log in. Users will receive an email notification once approved.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Auto-Delete Unverified Accounts', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_delete_unverified_days" value="<?php echo esc_attr( $nbuf_delete_unverified_days ); ?>" min="0" max="365" class="small-text">
				<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Automatically delete accounts that have not verified their email within this many days. Set to 0 to disable. Only applies when email verification is enabled. Default: 5 days', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'New User Default Role', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_new_user_default_role">
					<?php wp_dropdown_roles( $nbuf_new_user_default_role ); ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'The default WordPress role assigned to new user accounts upon registration. This role determines what capabilities and permissions the user will have. Default: Subscriber', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<input type="hidden" name="nbuf_active_tab" value="security">
	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
