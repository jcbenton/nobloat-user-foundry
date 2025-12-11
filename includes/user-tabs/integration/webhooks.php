<?php
/**
 * Integration > Webhooks Tab
 *
 * Webhook configuration (placeholder for future implementation).
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="integration">
	<input type="hidden" name="nbuf_active_subtab" value="webhooks">

	<h2><?php esc_html_e( 'Webhook Configuration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure webhooks to notify external services of user events. This feature is planned for a future release.', 'nobloat-user-foundry' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Coming Soon', 'nobloat-user-foundry' ); ?></strong><br>
			<?php esc_html_e( 'This section will allow you to set up webhooks for events like user registration, verification, login, and profile updates.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>
</form>
