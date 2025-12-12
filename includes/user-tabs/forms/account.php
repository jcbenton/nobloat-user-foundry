<?php
/**
 * Account Page Template Tab
 *
 * Manage the HTML template for the account page shortcode.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current template */
$account_page = NBUF_Options::get( 'nbuf_account_page_template', '' );

/* If empty, load from default template */
if ( empty( $account_page ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'account-page.html';
	if ( file_exists( $template_path ) ) {
		$account_page = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the HTML template for the user account page displayed via the [nbuf_account_page] shortcode.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="forms">
		<input type="hidden" name="nbuf_active_subtab" value="account">

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Account Page Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_account_page_template"
				rows="40"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $account_page ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'This is a complex template with multiple tabs and sections. Available placeholders include:', 'nobloat-user-foundry' ); ?>
			</p>
			<ul class="description" style="margin-left: 20px; list-style: disc;">
				<li><?php esc_html_e( 'General: {messages}, {action_url}, {nonce_field}, {logout_url}', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'User info: {username}, {email}, {display_name}, {registered_date}, {status_badges}', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Tabs: {security_tab_button}, {policies_tab_button}, {photos_subtab_button}', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Content: {profile_photo_section}, {profile_fields}, {visibility_section}, {photos_subtab_content}', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Features: {expiration_info}, {version_history_section}, {security_tab_content}, {policies_tab_content}', 'nobloat-user-foundry' ); ?></li>
			</ul>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="account-page"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Account Page Template', 'nobloat-user-foundry' ) ); ?>
	</form>

	<div class="nbuf-template-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><code>.nbuf-account-wrapper</code> - Main wrapper</li>
			<li><code>.nbuf-account-tabs</code> - Tab navigation container</li>
			<li><code>.nbuf-tab-button</code> - Tab buttons</li>
			<li><code>.nbuf-tab-active</code> - Active tab state</li>
			<li><code>.nbuf-tab-content</code> - Tab content areas</li>
			<li><code>.nbuf-account-section</code> - Content sections</li>
			<li><code>.nbuf-account-form</code> - Form elements</li>
			<li><code>.nbuf-info-grid</code> - Account info grid layout</li>
			<li><code>.nbuf-info-label</code> - Info labels</li>
			<li><code>.nbuf-info-value</code> - Info values</li>
		</ul>

		<h3 style="margin-top: 1rem;"><?php esc_html_e( 'Tab Structure', 'nobloat-user-foundry' ); ?></h3>
		<p><?php esc_html_e( 'The account page uses nested tabs. Main tabs: Account, Profile, Password, Security (if 2FA enabled), Policies (if enabled). Profile has sub-tabs: Details, Visibility, Photos (if enabled).', 'nobloat-user-foundry' ); ?></p>
	</div>
</div>
