<?php
/**
 * System > Cleanup Tab
 *
 * Uninstall cleanup options.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = NBUF_Options::get( 'nbuf_settings', array() );
$cleanup  = (array) ( $settings['cleanup'] ?? array() );
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_settings_group' );
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="cleanup">

	<h2><?php esc_html_e( 'Uninstall Cleanup', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Choose what data to remove when the plugin is uninstalled (deleted, not just deactivated).', 'nobloat-user-foundry' ); ?>
	</p>

	<fieldset class="nbuf-cleanup">
		<?php
		$cleanup_options = array(
			'tokens'    => __( 'Delete all verification tokens.', 'nobloat-user-foundry' ),
			'settings'  => __( 'Delete plugin settings.', 'nobloat-user-foundry' ),
			'templates' => __( 'Delete templates.', 'nobloat-user-foundry' ),
			'usermeta'  => __( 'Delete user verification and disabled status meta fields.', 'nobloat-user-foundry' ),
		);
		foreach ( $cleanup_options as $key => $label ) {
			printf(
				'<label style="display:block;margin:8px 0;"><input type="checkbox" name="nbuf_settings[cleanup][]" value="%s" %s> %s</label>',
				esc_attr( $key ),
				checked( in_array( $key, $cleanup, true ), true, false ),
				esc_html( $label )
			);
		}
		?>
	</fieldset>

	<p class="description" style="margin-top: 16px;">
		<?php esc_html_e( 'Note: Uninstall cleanup only runs when you delete the plugin from the WordPress Plugins page, not when you deactivate it.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
