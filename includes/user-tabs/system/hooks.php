<?php
/**
 * System > Hooks Tab
 *
 * Verification trigger hooks configuration.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = NBUF_Options::get( 'nbuf_settings', array() );
$hooks    = (array) ( $settings['hooks'] ?? array() );
$custom   = sanitize_text_field( $settings['hooks_custom'] ?? '' );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="hooks">

	<h2><?php esc_html_e( 'Hooks', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure which WordPress actions should trigger email verification for new users.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Registration Hooks', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block;">
						<input type="checkbox" name="nbuf_settings[hooks][]" value="register_new_user" <?php checked( in_array( 'register_new_user', $hooks, true ), true ); ?>>
						<strong>register_new_user</strong>
					</label>
					<p class="description" style="margin: 4px 0 12px 24px;">
						<?php esc_html_e( 'Fires when users self-register through the standard WordPress registration form (wp-login.php?action=register). This is the recommended hook for most sites as it only targets front-end self-registration.', 'nobloat-user-foundry' ); ?>
					</p>

					<label style="display:block;">
						<input type="checkbox" name="nbuf_settings[hooks][]" value="user_register" <?php checked( in_array( 'user_register', $hooks, true ), true ); ?>>
						<strong>user_register</strong>
					</label>
					<p class="description" style="margin: 4px 0 12px 24px;">
						<?php esc_html_e( 'Fires for ALL user creation methods including: admin panel, REST API, WP-CLI, programmatic creation, and third-party plugins. Use with caution — this will trigger verification emails even when admins manually create users or when users are imported in bulk.', 'nobloat-user-foundry' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Email Change Re-verification', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_settings[reverify_on_email_change]" value="1" <?php checked( ! empty( $settings['reverify_on_email_change'] ), true ); ?>>
					<?php esc_html_e( 'Require re-verification when a user changes their email address', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users who update their email address will have their verification status reset and must verify the new address before regaining full access. This prevents users from switching to unverified email addresses.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Custom Hook', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_settings[custom_hook_enabled]" value="1" <?php checked( ! empty( $settings['custom_hook_enabled'] ), true ); ?>>
					<?php esc_html_e( 'Enable custom hook listener', 'nobloat-user-foundry' ); ?>
				</label>
				<div style="margin-top:6px;">
					<input type="text" name="nbuf_settings[hooks_custom]" value="<?php echo esc_attr( $custom ); ?>" placeholder="custom_user_register_hook" class="regular-text">
					<p class="description"><?php esc_html_e( 'Custom action name fired after user creation (e.g., from custom registration logic).', 'nobloat-user-foundry' ); ?></p>
				</div>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
