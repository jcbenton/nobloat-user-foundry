<?php
/**
 * Admin Notification Templates Tab
 *
 * Manage admin notification email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$admin_new_user_html = NBUF_Template_Manager::load_template( 'admin-new-user-html' );
$admin_new_user_text = NBUF_Template_Manager::load_template( 'admin-new-user-text' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the email template sent to administrators when a new user registers on your site.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="admin-notification">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_admin_new_user_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $admin_new_user_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button type="button" class="button nbuf-reset-template" data-template="admin-new-user-html">
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_admin_new_user_text"
				rows="12"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $admin_new_user_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Plain text version for email clients that don\'t support HTML. Same placeholders available.', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button type="button" class="button nbuf-reset-template" data-template="admin-new-user-text">
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Admin Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
