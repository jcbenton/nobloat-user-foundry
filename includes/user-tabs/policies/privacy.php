<?php
/**
 * Privacy Policy Template Tab
 *
 * Manage the privacy policy template displayed on forms.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$privacy_html = NBUF_Options::get( 'nbuf_policy_privacy_html', '' );

/* If empty, load from default template */
if ( empty( $privacy_html ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'policy-privacy.html';
	if ( file_exists( $template_path ) ) {
		$privacy_html = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the privacy policy summary displayed on login and registration forms. This should be a brief overview of your privacy practices, not the full privacy policy.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="policies">
		<input type="hidden" name="nbuf_active_subtab" value="privacy">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Privacy Policy Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_policy_privacy_html"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $privacy_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="policy-privacy-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Privacy Policy Template', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
