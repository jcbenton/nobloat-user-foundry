<?php
/**
 * Users > Verification Tab
 *
 * Email verification settings and token configuration.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_settings   = NBUF_Options::get( 'nbuf_settings', array() );
$nbuf_verify_url = $nbuf_settings['verification_page'] ?? '/verify';
$nbuf_reset_url  = $nbuf_settings['password_reset_page'] ?? '/password-reset';
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_settings_group' );
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="verification">

	<h2><?php esc_html_e( 'Verification & Password URLs', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the page slugs used for email verification and password reset functionality.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Verification URL', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="text" name="nbuf_settings[verification_page]" value="<?php echo esc_attr( $nbuf_verify_url ); ?>" class="regular-text" placeholder="/verify">
				<p class="description">
					<?php echo wp_kses_post( __( 'The slug must exist as a page. It must include the shortcode <code>[nbuf_verify_page]</code> and will use your theme.', 'nobloat-user-foundry' ) ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset URL', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="text" name="nbuf_settings[password_reset_page]" value="<?php echo esc_attr( $nbuf_reset_url ); ?>" class="regular-text" placeholder="/password-reset">
				<p class="description">
					<?php echo wp_kses_post( __( 'The slug must exist as a page. It must include the shortcode <code>[nbuf_reset_form]</code> and will use your theme.', 'nobloat-user-foundry' ) ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
