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

$nbuf_settings = NBUF_Options::get( 'nbuf_settings', array() );
$nbuf_cleanup  = (array) ( $nbuf_settings['cleanup'] ?? array() );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="cleanup">
	<!-- Marker to indicate cleanup form was submitted (ensures nbuf_settings is always POSTed) -->
	<input type="hidden" name="nbuf_settings[cleanup_submitted]" value="1">

	<h2><?php esc_html_e( 'Uninstall Cleanup', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Choose what data to remove when the plugin is uninstalled (deleted, not just deactivated).', 'nobloat-user-foundry' ); ?>
	</p>

	<fieldset class="nbuf-cleanup">
		<?php
		$nbuf_cleanup_options = array(
			'settings'  => __( 'Delete plugin settings.', 'nobloat-user-foundry' ),
			'templates' => __( 'Delete email and page templates.', 'nobloat-user-foundry' ),
			'tables'    => __( 'Delete all database tables (tokens, user data, profiles, audit logs, 2FA, etc.).', 'nobloat-user-foundry' ),
			'uploads'   => __( 'Delete uploads directory (profile photos, cover photos, and all user-uploaded files).', 'nobloat-user-foundry' ),
		);
		foreach ( $nbuf_cleanup_options as $nbuf_key => $nbuf_label ) {
			printf(
				'<label style="display:block;margin:8px 0;"><input type="checkbox" name="nbuf_settings[cleanup][]" value="%s" %s> %s</label>',
				esc_attr( $nbuf_key ),
				checked( in_array( $nbuf_key, $nbuf_cleanup, true ), true, false ),
				esc_html( $nbuf_label )
			);
		}
		?>
	</fieldset>

	<p class="description" style="margin-top: 12px; color: #d63638;">
		<strong><?php esc_html_e( 'Warning:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'All cleanup actions are permanent and cannot be undone. If selected, database tables, uploaded files, and settings will be permanently deleted when the plugin is uninstalled.', 'nobloat-user-foundry' ); ?>
	</p>

	<p class="description" style="margin-top: 16px;">
		<?php esc_html_e( 'Note: Uninstall cleanup only runs when you delete the plugin from the WordPress Plugins page, not when you deactivate it.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
