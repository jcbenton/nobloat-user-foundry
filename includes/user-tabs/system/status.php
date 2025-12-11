<?php
/**
 * System > Status Tab
 *
 * Master toggle for the User Management System.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Master toggle */
$user_manager_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="status">

	<!-- Master Toggle -->
	<div style="background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'User Manager Status', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" style="margin-bottom: 0;">
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'System Status', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_user_manager_enabled" value="0">
					<label>
						<input type="checkbox" name="nbuf_user_manager_enabled" value="1" <?php checked( $user_manager_enabled, true ); ?> id="nbuf_user_manager_enabled">
						<strong><?php esc_html_e( 'Enable User Management System', 'nobloat-user-foundry' ); ?></strong>
					</label>
					<?php if ( ! $user_manager_enabled ) : ?>
						<p class="description" style="color: #d63638; font-weight: 500;">
							<?php esc_html_e( 'User management is currently DISABLED. Configure your settings and migrate any data before enabling.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php else : ?>
						<p class="description" style="color: #00a32a; font-weight: 500;">
							<?php esc_html_e( 'User management is currently ENABLED. All features are active.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'When disabled, the plugin will not modify user behavior, authentication, or the users list. Use this to configure settings and prepare for migration before activating.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
