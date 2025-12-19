<?php
/**
 * Registration Settings Subtab (Users > Registration)
 *
 * Controls registration behavior including enabling/disabling,
 * email verification, username generation, and login methods.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );

/* Default values if not set */
$nbuf_username_method = $nbuf_reg_settings['username_method'] ?? 'auto_random';

/* Feature toggles */
$nbuf_enable_registration       = NBUF_Options::get( 'nbuf_enable_registration', true );
$nbuf_require_verification      = NBUF_Options::get( 'nbuf_require_verification', true );
$nbuf_notify_admin_registration = NBUF_Options::get( 'nbuf_notify_admin_registration', false );

/* Check WordPress registration setting for mismatch warning */
$wp_users_can_register      = get_option( 'users_can_register' );
$nbuf_registration_mismatch = ( $nbuf_enable_registration && ! $wp_users_can_register ) || ( ! $nbuf_enable_registration && $wp_users_can_register );

?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nbuf-registration-form">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="registration">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_enable_registration">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_require_verification">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_notify_admin_registration">

	<h2><?php esc_html_e( 'Registration Status', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'User Registration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_registration" value="1" <?php checked( $nbuf_enable_registration, true ); ?>>
					<?php esc_html_e( 'Enable user registration', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow new users to register via [nbuf_registration_form] shortcode. When disabled, the shortcode will display a message. This setting automatically syncs with WordPress Settings → General → Membership.', 'nobloat-user-foundry' ); ?>
				</p>
				<?php if ( $nbuf_registration_mismatch ) : ?>
					<p class="description" style="color: #d63638; margin-top: 8px;">
						<strong><?php esc_html_e( 'Warning:', 'nobloat-user-foundry' ); ?></strong>
						<?php if ( $nbuf_enable_registration && ! $wp_users_can_register ) : ?>
							<?php esc_html_e( 'NoBloat registration is enabled, but WordPress "Anyone can register" is disabled. Save this page to sync the settings.', 'nobloat-user-foundry' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'WordPress "Anyone can register" is enabled, but NoBloat registration is disabled. Save this page to sync the settings.', 'nobloat-user-foundry' ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Email Verification', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_require_verification" value="1" <?php checked( $nbuf_require_verification, true ); ?>>
					<?php esc_html_e( 'Require email verification for new user registrations', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, new users must verify their email address before they can log in. When disabled, users can log in immediately after registration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Admin Notifications', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_notify_admin_registration" value="1" <?php checked( $nbuf_notify_admin_registration, true ); ?>>
					<?php esc_html_e( 'Notify administrators when new users register', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Send an email notification to the site administrator email when a new user creates an account.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Registration Behavior', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Username Generation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_registration_fields[username_method]">
					<option value="auto_random" <?php selected( $nbuf_username_method, 'auto_random' ); ?>>
						<?php esc_html_e( 'Auto Random - Generate random username (Best for privacy)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="auto_email" <?php selected( $nbuf_username_method, 'auto_email' ); ?>>
						<?php esc_html_e( 'Auto from Email - Extract from email prefix (john@example.com → john)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="user_entered" <?php selected( $nbuf_username_method, 'user_entered' ); ?>>
						<?php esc_html_e( 'User Entered - User chooses their own username', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How usernames are assigned during registration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<p class="description" style="margin-top: 20px;">
		<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Configure which fields appear on the registration form in Profile Fields.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
