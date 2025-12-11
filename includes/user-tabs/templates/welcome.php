<?php
/**
 * Welcome Email Templates Tab
 *
 * Manage welcome email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$welcome_html = NBUF_Options::get( 'nbuf_welcome_email_html', '' );
$welcome_text = NBUF_Options::get( 'nbuf_welcome_email_text', '' );

/* If empty, load from default templates */
if ( empty( $welcome_html ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'welcome-email.html';
	if ( file_exists( $template_path ) ) {
		$welcome_html = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $welcome_text ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'welcome-email.txt';
	if ( file_exists( $template_path ) ) {
		$welcome_text = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the welcome email sent to new users after registration. The password reset link will automatically be replaced with your custom password reset page URL.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="welcome">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_welcome_email_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $welcome_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="welcome-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_welcome_email_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $welcome_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {password_reset_link}, {user_email}, {username}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="welcome-text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Welcome Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
