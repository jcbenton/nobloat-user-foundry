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

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
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
			'settings'  => __( 'Delete plugin settings.', 'nobloat-user-foundry' ),
			'templates' => __( 'Delete email and page templates.', 'nobloat-user-foundry' ),
			'tables'    => __( 'Delete all database tables (tokens, user data, profiles, audit logs, 2FA, etc.).', 'nobloat-user-foundry' ),
			'usermeta'  => __( 'Delete legacy user meta fields (from older versions).', 'nobloat-user-foundry' ),
			'pages'     => __( 'Delete pages containing NoBloat shortcodes.', 'nobloat-user-foundry' ),
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

	<p class="description" style="margin-top: 12px; color: #d63638;">
		<strong><?php esc_html_e( 'Warning:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Checking "Delete all database tables" will permanently remove all user verification data, profile fields, audit logs, and 2FA settings. This cannot be undone.', 'nobloat-user-foundry' ); ?>
	</p>

	<p class="description" style="margin-top: 16px;">
		<?php esc_html_e( 'Note: Uninstall cleanup only runs when you delete the plugin from the WordPress Plugins page, not when you deactivate it.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
