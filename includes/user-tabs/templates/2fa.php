<?php
/**
 * Templates Tab
 *
 * Manage email templates with accordion organization.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$verification_html  = NBUF_Options::get('nbuf_email_template_html', '' );
$verification_text  = NBUF_Options::get('nbuf_email_template_text', '' );
$welcome_html       = NBUF_Options::get('nbuf_welcome_email_html', '' );
$welcome_text       = NBUF_Options::get('nbuf_welcome_email_text', '' );
$login_form         = NBUF_Options::get('nbuf_login_form_template', '' );
$registration_form  = NBUF_Options::get('nbuf_registration_form_template', '' );
$account_page       = NBUF_Options::get('nbuf_account_page_template', '' );

/* If empty, load from default templates */
if ( empty( $verification_html ) ) {
	$path = NBUF_TEMPLATES_DIR . 'email-verification.html';
	if ( file_exists( $path ) ) {
		$verification_html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $verification_text ) ) {
	$path = NBUF_TEMPLATES_DIR . 'email-verification.txt';
	if ( file_exists( $path ) ) {
		$verification_text = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $welcome_html ) ) {
	$path = NBUF_TEMPLATES_DIR . 'welcome-email.html';
	if ( file_exists( $path ) ) {
		$welcome_html = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $welcome_text ) ) {
	$path = NBUF_TEMPLATES_DIR . 'welcome-email.txt';
	if ( file_exists( $path ) ) {
		$welcome_text = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $login_form ) ) {
	$path = NBUF_TEMPLATES_DIR . 'login-form.html';
	if ( file_exists( $path ) ) {
		$login_form = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $registration_form ) ) {
	$path = NBUF_TEMPLATES_DIR . 'registration-form.html';
	if ( file_exists( $path ) ) {
		$registration_form = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
if ( empty( $account_page ) ) {
	$path = NBUF_TEMPLATES_DIR . 'account-page.html';
	if ( file_exists( $path ) ) {
		$account_page = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize email templates and form templates. Click on each section to expand and edit.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'nbuf_templates_group' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
	<input type="hidden" name="nbuf_active_subtab" value="2fa">

		<!-- =================================================== -->
		<!-- EMAIL VERIFICATION TEMPLATES -->
		<!-- =================================================== -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Email Verification Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
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
			</div>
		</div>

		<!-- =================================================== -->
		<!-- WELCOME EMAIL TEMPLATES -->
		<!-- =================================================== -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Welcome Email Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<p class="description" style="margin-bottom: 1.5rem;">
					<?php esc_html_e( 'These templates are used for the WordPress welcome email sent to new users. The password reset link will automatically be replaced with your custom password reset page URL.', 'nobloat-user-foundry' ); ?>
				</p>

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
			</div>
		</div>

		<!-- =================================================== -->
		<!-- LOGIN FORM TEMPLATE -->
		<!-- =================================================== -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Login Form Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea
						name="nbuf_login_form_template"
						rows="20"
						class="large-text code nbuf-template-editor"
					><?php echo esc_textarea( $login_form ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available placeholders: {action_url}, {nonce_field}, {redirect_to}, {reset_url}, {register_link}, {error_message}', 'nobloat-user-foundry' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Use shortcode [nbuf_login_form] to display this form anywhere on your site.', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button
							type="button"
							class="button nbuf-reset-template"
							data-template="login-form"
						>
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- =================================================== -->
		<!-- REGISTRATION FORM TEMPLATE -->
		<!-- =================================================== -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Registration Form Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea
						name="nbuf_registration_form_template"
						rows="25"
						class="large-text code nbuf-template-editor"
					><?php echo esc_textarea( $registration_form ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available placeholders: {action_url}, {nonce_field}, {registration_fields}, {error_message}, {login_link}', 'nobloat-user-foundry' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Use shortcode [nbuf_registration_form] to display this form anywhere on your site.', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button
							type="button"
							class="button nbuf-reset-template"
							data-template="registration-form"
						>
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- =================================================== -->
		<!-- ACCOUNT PAGE TEMPLATE -->
		<!-- =================================================== -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Account Page Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea
						name="nbuf_account_page_template"
						rows="30"
						class="large-text code nbuf-template-editor"
					><?php echo esc_textarea( $account_page ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available placeholders: {messages}, {status_badges}, {username}, {email}, {display_name}, {registered_date}, {expiration_info}, {action_url}, {nonce_field}, {nonce_field_password}, {profile_fields}, {logout_url}', 'nobloat-user-foundry' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Use shortcode [nbuf_account_page] to display the account management page. Profile fields are dynamically generated based on registration settings.', 'nobloat-user-foundry' ); ?>
					</p>
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
			</div>
		</div>

		<?php submit_button( __( 'Save Template Changes', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>

<style>
/* Accordion Styles */
.nbuf-accordion {
    margin-bottom: 1.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
}

.nbuf-accordion-header {
    width: 100%;
    padding: 1rem 1.5rem;
    background: #f7f7f7;
    border: none;
    text-align: left;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 15px;
    font-weight: 600;
    transition: background 0.2s;
}

.nbuf-accordion-header:hover {
    background: #f0f0f0;
}

.nbuf-accordion-header:focus {
    outline: 2px solid #2271b1;
    outline-offset: -2px;
}

.nbuf-accordion-icon {
    transition: transform 0.3s;
    font-size: 12px;
}

.nbuf-accordion.active .nbuf-accordion-icon {
    transform: rotate(-180deg);
}

.nbuf-accordion-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    background: #fff;
}

.nbuf-accordion.active .nbuf-accordion-content {
    max-height: 5000px;
    transition: max-height 0.5s ease-in;
}

.nbuf-template-section {
    padding: 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.nbuf-template-section:last-child {
    border-bottom: none;
}

.nbuf-template-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.nbuf-template-editor {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>

<script>
document.addEventListener( 'DOMContentLoaded', function() {
	/* Accordion toggle functionality */
	const accordions = document.querySelectorAll( '.nbuf-accordion-header' );

	accordions.forEach( header => {
		header.addEventListener( 'click', function() {
			const accordion = this.closest( '.nbuf-accordion' );
			const isActive = accordion.classList.contains( 'active' );

			/* Close all accordions */
			document.querySelectorAll( '.nbuf-accordion' ).forEach( acc => {
				acc.classList.remove( 'active' );
			} );

			/* Open clicked accordion if it wasn't active */
			if ( ! isActive ) {
				accordion.classList.add( 'active' );
			}
		} );
	} );

	/* Open first accordion by default */
	const firstAccordion = document.querySelector( '.nbuf-accordion' );
	if ( firstAccordion ) {
		firstAccordion.classList.add( 'active' );
	}
} );
</script>
