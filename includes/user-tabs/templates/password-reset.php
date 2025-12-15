<?php
/**
 * Password Reset Templates Tab
 *
 * Manage password reset email and form templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$nbuf_reset_email_html = NBUF_Options::get( 'nbuf_password_reset_email_html', '' );
$nbuf_reset_email_text = NBUF_Options::get( 'nbuf_password_reset_email_text', '' );

/* If empty, load from default templates */
if ( empty( $nbuf_reset_email_html ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . 'password-reset.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_reset_email_html = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $nbuf_reset_email_text ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . 'password-reset.txt';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_reset_email_text = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the password reset email sent to users when they request a password reset.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="password-reset">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_password_reset_email_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_reset_email_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {reset_link}, {user_email}, {username}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="password-reset-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_password_reset_email_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_reset_email_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {reset_link}, {user_email}, {username}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="password-reset-text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Password Reset Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
