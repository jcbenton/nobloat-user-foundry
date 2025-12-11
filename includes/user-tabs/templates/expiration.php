<?php
/**
 * Account Expiration Email Templates Tab
 *
 * Manage account expiration notification email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$expiration_warning_html = NBUF_Options::get( 'nbuf_expiration_warning_email_html', '' );
$expiration_warning_text = NBUF_Options::get( 'nbuf_expiration_warning_email_text', '' );
$expiration_notice_html  = NBUF_Options::get( 'nbuf_expiration_notice_email_html', '' );
$expiration_notice_text  = NBUF_Options::get( 'nbuf_expiration_notice_email_text', '' );

/* If empty, load from default templates */
if ( empty( $expiration_warning_html ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'expiration-warning.html';
	if ( file_exists( $template_path ) ) {
		$expiration_warning_html = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $expiration_warning_text ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'expiration-warning.txt';
	if ( file_exists( $template_path ) ) {
		$expiration_warning_text = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $expiration_notice_html ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'expiration-notice.html';
	if ( file_exists( $template_path ) ) {
		$expiration_notice_html = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $expiration_notice_text ) ) {
	$template_path = NBUF_TEMPLATES_DIR . 'expiration-notice.txt';
	if ( file_exists( $template_path ) ) {
		$expiration_notice_text = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the account expiration notification emails sent to users before and after their account expires.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="expiration">

		<h2><?php esc_html_e( 'Expiration Warning Email', 'nobloat-user-foundry' ); ?></h2>
		<p class="description" style="margin-bottom: 1rem;">
			<?php esc_html_e( 'Sent to users before their account expires (based on warning days setting).', 'nobloat-user-foundry' ); ?>
		</p>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_expiration_warning_email_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $expiration_warning_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {expiration_date}, {days_remaining}, {user_email}, {username}, {site_url}, {account_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="expiration-warning-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_expiration_warning_email_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $expiration_warning_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {expiration_date}, {days_remaining}, {user_email}, {username}, {site_url}, {account_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="expiration-warning-text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<hr style="margin: 2rem 0;">

		<h2><?php esc_html_e( 'Account Expired Email', 'nobloat-user-foundry' ); ?></h2>
		<p class="description" style="margin-bottom: 1rem;">
			<?php esc_html_e( 'Sent to users when their account has expired.', 'nobloat-user-foundry' ); ?>
		</p>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_expiration_notice_email_html"
				rows="15"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $expiration_notice_html ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {expiration_date}, {user_email}, {username}, {site_url}, {contact_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="expiration-notice-html"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<div class="nbuf-template-section">
			<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
			<textarea
				name="nbuf_expiration_notice_email_text"
				rows="10"
				class="large-text code nbuf-template-editor"
			><?php echo esc_textarea( $expiration_notice_text ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Available placeholders: {site_name}, {display_name}, {expiration_date}, {user_email}, {username}, {site_url}, {contact_url}', 'nobloat-user-foundry' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button nbuf-reset-template"
					data-template="expiration-notice-text"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<?php submit_button( __( 'Save Expiration Templates', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
