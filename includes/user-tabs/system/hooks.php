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

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_settings_group' );
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
				<?php
				$available_hooks = array(
					'user_register' => __( 'user_register — default WordPress registration.', 'nobloat-user-foundry' ),
				);
				foreach ( $available_hooks as $hook => $desc ) {
					printf(
						'<label style="display:block;"><input type="checkbox" name="nbuf_settings[hooks][]" value="%s" %s> %s</label>',
						esc_attr( $hook ),
						checked( in_array( $hook, $hooks, true ), true, false ),
						esc_html( $desc )
					);
				}
				?>
				<label style="display:block;margin-top:8px;">
					<input type="checkbox" name="nbuf_settings[reverify_on_email_change]" value="1" <?php checked( ! empty( $settings['reverify_on_email_change'] ), true ); ?>>
					<?php esc_html_e( 'Require re-verification if a user changes their email address.', 'nobloat-user-foundry' ); ?>
				</label>
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
