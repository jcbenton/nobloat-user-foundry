<?php
/**
 * Verification Email Templates Tab
 *
 * Manage email verification templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$verification_html = NBUF_Options::get( 'nbuf_email_template_html', '' );
$verification_text = NBUF_Options::get( 'nbuf_email_template_text', '' );

/* If empty, load from default templates */
if ( empty( $verification_html ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'email-verification.html';
	if ( file_exists( $template_path ) ) {
		$verification_html = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $verification_text ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'email-verification.txt';
	if ( file_exists( $template_path ) ) {
		$verification_text = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the email verification template sent to users when they register.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="verification">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_email_template_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $verification_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_email_template_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $verification_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {verify_link}, {user_email}, {username}, {site_url}, {verification_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Verification Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
