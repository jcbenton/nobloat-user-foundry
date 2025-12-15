<?php
/**
 * Terms of Use Template Tab
 *
 * Manage the terms of use template displayed on forms.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$nbuf_terms_html = NBUF_Options::get( 'nbuf_policy_terms_html', '' );

/* If empty, load from default template */
if ( empty( $nbuf_terms_html ) ) {
	$nbuf_template_path = NBUF_TEMPLATES_DIR . 'policy-terms.html';
	if ( file_exists( $nbuf_template_path ) ) {
		$nbuf_terms_html = file_get_contents( $nbuf_template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the terms of use summary displayed on login and registration forms. This should be a brief overview of your terms, not the full terms of service document.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="policies">
		<input type="hidden" name="nbuf_active_subtab" value="terms">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Terms of Use Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_policy_terms_html"
				rows="20"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $nbuf_terms_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {site_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="policy-terms-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Terms of Use Template', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
