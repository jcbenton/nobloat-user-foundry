<?php
/**
 * 2FA Email Templates Tab
 *
 * Manage two-factor authentication email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$nbuf_twofa_email_html = NBUF_Options::get( 'nbuf_2fa_email_html', '' );
$nbuf_twofa_email_text = NBUF_Options::get( 'nbuf_2fa_email_text', '' );

/* If empty, load from default templates */
if ( empty( $nbuf_twofa_email_html ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . '2fa-email-code.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_twofa_email_html = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $nbuf_twofa_email_text ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . '2fa-email-code.txt';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_twofa_email_text = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the two-factor authentication code email sent to users during login.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="2fa">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_email_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_twofa_email_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {verification_code}, {user_email}, {username}, {site_url}, {expiry_minutes}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_2fa_email_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_twofa_email_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {verification_code}, {user_email}, {username}, {site_url}, {expiry_minutes}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="2fa-text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save 2FA Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
