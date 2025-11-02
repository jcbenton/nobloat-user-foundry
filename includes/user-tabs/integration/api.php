<?php
/**
 * Integration > API Tab
 *
 * REST API settings (placeholder for future implementation).
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_settings_group' );
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="integration">
	<input type="hidden" name="nbuf_active_subtab" value="api">

	<h2><?php esc_html_e( 'REST API Integration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'REST API settings for external integrations. This feature is planned for a future release.', 'nobloat-user-foundry' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Coming Soon', 'nobloat-user-foundry' ); ?></strong><br>
			<?php esc_html_e( 'This section will allow you to configure REST API endpoints for user management, authentication, and profile data access.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>
</form>
