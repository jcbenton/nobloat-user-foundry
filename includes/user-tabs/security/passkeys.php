<?php
/**
 * Security > Passkeys Settings Tab
 *
 * WebAuthn passkey configuration for passwordless authentication.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Passkey options */
$nbuf_passkeys_enabled           = NBUF_Options::get( 'nbuf_passkeys_enabled', false );
$nbuf_passkey_prompt_enabled     = NBUF_Options::get( 'nbuf_passkey_prompt_enabled', true );
$nbuf_passkeys_max_per_user      = NBUF_Options::get( 'nbuf_passkeys_max_per_user', 10 );
$nbuf_passkeys_user_verification = NBUF_Options::get( 'nbuf_passkeys_user_verification', 'preferred' );
$nbuf_passkeys_attachment        = NBUF_Options::get( 'nbuf_passkeys_authenticator_attachment', 'platform' );
$nbuf_passkeys_attestation       = NBUF_Options::get( 'nbuf_passkeys_attestation', 'none' );
$nbuf_passkeys_timeout           = NBUF_Options::get( 'nbuf_passkeys_timeout', 60000 );
$nbuf_2fa_passkey_policy         = class_exists( 'NBUF_Passkeys' ) ? NBUF_Passkeys::resolve_2fa_policy() : 'always';

/* Statistics */
$nbuf_total_passkeys      = 0;
$nbuf_users_with_passkeys = 0;
if ( class_exists( 'NBUF_User_Passkeys_Data' ) ) {
	$nbuf_total_passkeys      = NBUF_User_Passkeys_Data::get_total_count();
	$nbuf_users_with_passkeys = NBUF_User_Passkeys_Data::get_users_with_passkeys_count();
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="passkeys">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_passkeys_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_passkey_prompt_enabled">

	<h2><?php esc_html_e( 'Passkey Authentication', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Passkeys enable passwordless login using biometrics (fingerprint, face recognition) or security keys. They are more secure and convenient than passwords.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php if ( ! is_ssl() ) : ?>
		<div class="notice notice-error inline" style="margin: 15px 0;">
			<p>
				<strong><?php esc_html_e( 'HTTPS Required', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Passkeys require HTTPS to function. Your site is currently not using HTTPS. Please enable SSL before using passkeys.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Passkeys', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_passkeys_enabled" value="1" <?php checked( $nbuf_passkeys_enabled, true ); ?> <?php disabled( ! is_ssl() ); ?>>
					<?php esc_html_e( 'Allow users to register and use passkeys for login', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can register passkeys in their account settings and use them to log in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Enrollment Prompt', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_passkey_prompt_enabled" value="1" <?php checked( $nbuf_passkey_prompt_enabled, true ); ?> <?php disabled( ! is_ssl() ); ?>>
					<?php esc_html_e( 'Prompt users to set up passkeys after login', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Show a one-time prompt to users who haven\'t registered a passkey yet. Users can dismiss temporarily or permanently per device.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Maximum Passkeys per User', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_passkeys_max_per_user" value="<?php echo esc_attr( $nbuf_passkeys_max_per_user ); ?>" min="1" max="20" class="small-text">
				<span><?php esc_html_e( 'passkeys', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Limit how many passkeys each user can register. Default: 10', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Advanced Options', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Advanced WebAuthn settings. Default values work for most sites.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'User Verification', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_passkeys_user_verification">
					<option value="preferred" <?php selected( $nbuf_passkeys_user_verification, 'preferred' ); ?>>
						<?php esc_html_e( 'Use biometric or PIN when the device offers it (Recommended)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="required" <?php selected( $nbuf_passkeys_user_verification, 'required' ); ?>>
						<?php esc_html_e( 'Always require biometric or PIN', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="discouraged" <?php selected( $nbuf_passkeys_user_verification, 'discouraged' ); ?>>
						<?php esc_html_e( 'Never require biometric or PIN', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Controls only what the device does at login — whether the passkey demands a fingerprint, face, or PIN. "Always require" is the strictest, but on some systems it makes the operating system prompt for your device password. This setting does NOT decide whether a passkey counts as your 2FA — that is the "2FA with Passkeys" setting below.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Passkey Device Type', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_passkeys_authenticator_attachment">
					<option value="platform" <?php selected( $nbuf_passkeys_attachment, 'platform' ); ?>>
						<?php esc_html_e( 'This device — Touch ID, Windows Hello, Android (Recommended)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="cross-platform" <?php selected( $nbuf_passkeys_attachment, 'cross-platform' ); ?>>
						<?php esc_html_e( 'Phone, tablet, or security key (cross-device QR)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="any" <?php selected( $nbuf_passkeys_attachment, 'any' ); ?>>
						<?php esc_html_e( 'Any — let the user choose', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( '"This device" registers the passkey directly with the built-in authenticator (fingerprint, face, or PIN) instead of first showing a QR code to scan with another device. Note: on a device with no built-in authenticator, choose "Any" so users can still register with a phone or security key.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Attestation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_passkeys_attestation">
					<option value="none" <?php selected( $nbuf_passkeys_attestation, 'none' ); ?>>
						<?php esc_html_e( 'None (Recommended)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="indirect" <?php selected( $nbuf_passkeys_attestation, 'indirect' ); ?>>
						<?php esc_html_e( 'Indirect', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="direct" <?php selected( $nbuf_passkeys_attestation, 'direct' ); ?>>
						<?php esc_html_e( 'Direct', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Attestation provides information about the authenticator. "None" maximizes compatibility and privacy. Only change if you need to verify authenticator types.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Timeout', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_passkeys_timeout" value="<?php echo esc_attr( $nbuf_passkeys_timeout ); ?>" min="30000" max="300000" step="1000" class="regular-text">
				<span><?php esc_html_e( 'milliseconds', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long users have to complete passkey registration or authentication. Default: 60000 (60 seconds).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( '2FA with Passkeys', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'When a user signs in with a passkey', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<label style="display: block; margin-bottom: 6px;">
						<input type="radio" name="nbuf_2fa_passkey_policy" value="always" <?php checked( $nbuf_2fa_passkey_policy, 'always' ); ?>>
						<?php esc_html_e( 'The passkey is enough — never ask for an additional 2FA code (Recommended)', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display: block; margin-bottom: 6px;">
						<input type="radio" name="nbuf_2fa_passkey_policy" value="verified" <?php checked( $nbuf_2fa_passkey_policy, 'verified' ); ?>>
						<?php esc_html_e( 'Only if the passkey verified identity (biometric / PIN) — otherwise ask for a 2FA code', 'nobloat-user-foundry' ); ?>
					</label>
					<label style="display: block;">
						<input type="radio" name="nbuf_2fa_passkey_policy" value="require" <?php checked( $nbuf_2fa_passkey_policy, 'require' ); ?>>
						<?php esc_html_e( 'Always also ask for a 2FA code after a passkey login', 'nobloat-user-foundry' ); ?>
					</label>
				</fieldset>
				<p class="description" style="margin-top: 10px;">
					<?php esc_html_e( 'A passkey already proves possession of this specific device, so "The passkey is enough" treats a passkey login as satisfying 2FA and never asks for a TOTP / email code — most users want this. The middle option additionally requires the device to have checked a fingerprint, face, or PIN; for it to work reliably, set "User Verification" above to "Always require" so that check always happens. "Always also ask" demands a separate code on top of every passkey login.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<h2><?php esc_html_e( 'Browser Support', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Passkeys are supported by all modern browsers:', 'nobloat-user-foundry' ); ?>
</p>
<ul style="list-style: disc; margin-left: 20px;">
	<li><?php esc_html_e( 'Chrome/Edge 67+ (Desktop and Mobile)', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'Safari 14+ (macOS, iOS 14.5+)', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'Firefox 60+', 'nobloat-user-foundry' ); ?></li>
</ul>
<p class="description">
	<?php esc_html_e( 'Users with unsupported browsers will not see the passkey option and can continue using passwords.', 'nobloat-user-foundry' ); ?>
</p>

<?php if ( $nbuf_total_passkeys > 0 ) : ?>
<h2><?php esc_html_e( 'Statistics', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php
	printf(
		/* translators: 1: total passkeys count, 2: users with passkeys count */
		esc_html__( '%1$d passkeys registered by %2$d users.', 'nobloat-user-foundry' ),
		(int) $nbuf_total_passkeys,
		(int) $nbuf_users_with_passkeys
	);
	?>
</p>
<?php endif; ?>
