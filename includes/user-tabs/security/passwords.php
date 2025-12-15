<?php
/**
 * Security > Passwords Tab
 *
 * Password strength requirements, enforcement, and weak password migration.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Password strength settings */
$nbuf_password_requirements_enabled = NBUF_Options::get( 'nbuf_password_requirements_enabled', true );
$nbuf_password_min_strength         = NBUF_Options::get( 'nbuf_password_min_strength', 'medium' );
$nbuf_password_min_length           = NBUF_Options::get( 'nbuf_password_min_length', 12 );
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

/* Password expiration settings */
$nbuf_password_expiration_enabled      = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
$nbuf_password_expiration_days         = NBUF_Options::get( 'nbuf_password_expiration_days', 365 );
$nbuf_password_expiration_admin_bypass = NBUF_Options::get( 'nbuf_password_expiration_admin_bypass', true );
$nbuf_password_expiration_warning_days = NBUF_Options::get( 'nbuf_password_expiration_warning_days', 7 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="passwords">

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
					<?php esc_html_e( 'Minimum number of characters required. Default: 12', 'nobloat-user-foundry' ); ?>
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
	<p class="description">
		<?php esc_html_e( 'Force existing users with weak passwords to update them.', 'nobloat-user-foundry' ); ?>
	</p>
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

	<h2><?php esc_html_e( 'Password Expiration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Automatically expire passwords after a specified number of days, forcing users to create new passwords periodically.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Password Expiration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_password_expiration_enabled" value="1" <?php checked( $nbuf_password_expiration_enabled, true ); ?> id="nbuf_password_expiration_enabled">
					<?php esc_html_e( 'Enable automatic password expiration', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, user passwords will automatically expire after the specified number of days. Users will be forced to change their password on their next login after expiration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Expires After', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_password_expiration_days" value="<?php echo esc_attr( $nbuf_password_expiration_days ); ?>" min="1" max="3650" class="small-text">
				<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Number of days after which a password expires. Default: 365 days (1 year). Industry standard is 90-365 days.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Warning Period', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_password_expiration_warning_days" value="<?php echo esc_attr( $nbuf_password_expiration_warning_days ); ?>" min="0" max="90" class="small-text">
				<span><?php esc_html_e( 'days before expiration', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Show a warning message to users this many days before their password expires. Set to 0 to disable warnings. Default: 7 days.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Administrator Bypass', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_password_expiration_admin_bypass" value="1" <?php checked( $nbuf_password_expiration_admin_bypass, true ); ?>>
					<?php esc_html_e( 'Exempt administrators from password expiration', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Users with "manage_options" capability will not be subject to password expiration. Recommended for emergency access. Default: ON', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div style="background: #f0f6fc; border: 1px solid #c3e6ff; border-radius: 4px; padding: 15px; margin: 20px 0;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Manual Password Change Controls', 'nobloat-user-foundry' ); ?></h3>
		<p>
			<?php esc_html_e( 'In addition to automatic expiration, you can manually force password changes for specific users:', 'nobloat-user-foundry' ); ?>
		</p>
		<ul style="list-style: disc; margin-left: 25px;">
			<li><strong><?php esc_html_e( 'Bulk Action:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Select multiple users on the Users page and choose "Force Password Change" from the bulk actions menu.', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'User Edit Screen:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'When editing a user, use the "Force Password Change" checkbox and "Force Logout All Devices" button in the Password Expiration section.', 'nobloat-user-foundry' ); ?></li>
		</ul>
		<p class="description">
			<?php esc_html_e( 'When a user is forced to change their password, they will be required to create a new password on their next login before they can access the site.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
