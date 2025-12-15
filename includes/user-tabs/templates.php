<?php
/**
 * Templates Tab
 *
 * Manage all email and form templates with accordion organization.
 * Templates are stored in custom table (wp_nbuf_options) to prevent wp_options bloat.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle template form submission
 *
 * Saves all email and form templates to custom table.
 */
if ( isset( $_POST['nbuf_save_templates'] ) && check_admin_referer( 'nbuf_templates_save', 'nbuf_templates_nonce' ) ) {
	// Define all template fields.
	$nbuf_templates = array(
		'email-verification-html',
		'email-verification-text',
		'welcome-email-html',
		'welcome-email-text',
		'expiration-warning-html',
		'expiration-warning-text',
		'2fa-email-code-html',
		'2fa-email-code-text',
		'login-form',
		'registration-form',
		'account-page',
		'request-reset-form',
		'reset-form',
		'2fa-verify',
		'2fa-setup-totp',
		'2fa-backup-codes',
	);

	// Save all templates.
	foreach ( $nbuf_templates as $nbuf_template_name ) {
		$nbuf_post_key = str_replace( '-', '_', $nbuf_template_name );
		if ( isset( $_POST[ $nbuf_post_key ] ) ) {
         // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashed then sanitized in save_template().
			$nbuf_content = wp_unslash( $_POST[ $nbuf_post_key ] );
			NBUF_Template_Manager::save_template( $nbuf_template_name, $nbuf_content );
		}
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Templates saved successfully to custom table (zero wp_options bloat).', 'nobloat-user-foundry' ) . '</p></div>';
}

/**
 * Load current template values from custom table
 */
$nbuf_templates_data = array(
	'email_verification_html' => NBUF_Template_Manager::load_template( 'email-verification-html' ),
	'email_verification_text' => NBUF_Template_Manager::load_template( 'email-verification-text' ),
	'welcome_email_html'      => NBUF_Template_Manager::load_template( 'welcome-email-html' ),
	'welcome_email_text'      => NBUF_Template_Manager::load_template( 'welcome-email-text' ),
	'expiration_warning_html' => NBUF_Template_Manager::load_template( 'expiration-warning-html' ),
	'expiration_warning_text' => NBUF_Template_Manager::load_template( 'expiration-warning-text' ),
	'2fa_email_code_html'     => NBUF_Template_Manager::load_template( '2fa-email-code-html' ),
	'2fa_email_code_text'     => NBUF_Template_Manager::load_template( '2fa-email-code-text' ),
	'login_form'              => NBUF_Template_Manager::load_template( 'login-form' ),
	'registration_form'       => NBUF_Template_Manager::load_template( 'registration-form' ),
	'account_page'            => NBUF_Template_Manager::load_template( 'account-page' ),
	'request_reset_form'      => NBUF_Template_Manager::load_template( 'request-reset-form' ),
	'reset_form'              => NBUF_Template_Manager::load_template( 'reset-form' ),
	'2fa_verify'              => NBUF_Template_Manager::load_template( '2fa-verify' ),
	'2fa_setup_totp'          => NBUF_Template_Manager::load_template( '2fa-setup-totp' ),
	'2fa_backup_codes'        => NBUF_Template_Manager::load_template( '2fa-backup-codes' ),
);
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize email templates and form templates. Templates are stored in custom table for zero wp_options bloat. Click on each section to expand and edit.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_templates_save', 'nbuf_templates_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">

		<!-- =================================================== -->
		<!-- EMAIL TEMPLATES SECTION -->
		<!-- =================================================== -->
		<h2 style="margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #0073aa; padding-bottom: 0.5rem;">
			<?php esc_html_e( 'Email Templates', 'nobloat-user-foundry' ); ?>
		</h2>

		<!-- Email Verification -->
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
					<textarea name="email_verification_html" rows="15" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['email_verification_html'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'email-verification-html' ) );
						?>
					</p>
				</div>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="email_verification_text" rows="10" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['email_verification_text'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'email-verification-text' ) );
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- Welcome Email -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Welcome Email Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<p class="description" style="margin: 1.5rem; margin-bottom: 0;">
					<?php esc_html_e( 'Sent to new users after registration. Password reset link is automatically replaced with your custom page.', 'nobloat-user-foundry' ); ?>
				</p>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="welcome_email_html" rows="15" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['welcome_email_html'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'welcome-email-html' ) );
						?>
					</p>
				</div>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="welcome_email_text" rows="10" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['welcome_email_text'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'welcome-email-text' ) );
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- Expiration Warning -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Account Expiration Warning Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<p class="description" style="margin: 1.5rem; margin-bottom: 0;">
					<?php esc_html_e( 'Sent to users before their account expires (if expiration is enabled).', 'nobloat-user-foundry' ); ?>
				</p>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="expiration_warning_html" rows="15" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['expiration_warning_html'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'expiration-warning-html' ) );
						?>
					</p>
				</div>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="expiration_warning_text" rows="10" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['expiration_warning_text'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'expiration-warning-text' ) );
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- 2FA Email Code -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( '2FA Email Code Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<p class="description" style="margin: 1.5rem; margin-bottom: 0;">
					<?php esc_html_e( 'Sent when users choose email-based two-factor authentication.', 'nobloat-user-foundry' ); ?>
				</p>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="2fa_email_code_html" rows="15" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['2fa_email_code_html'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( '2fa-email-code-html' ) );
						?>
					</p>
				</div>
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea name="2fa_email_code_text" rows="10" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['2fa_email_code_text'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( '2fa-email-code-text' ) );
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- =================================================== -->
		<!-- FORM TEMPLATES SECTION -->
		<!-- =================================================== -->
		<h2 style="margin-top: 3rem; margin-bottom: 1rem; border-bottom: 2px solid #0073aa; padding-bottom: 0.5rem;">
			<?php esc_html_e( 'Form & Page Templates', 'nobloat-user-foundry' ); ?>
		</h2>

		<!-- Login Form -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Login Form Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="login_form" rows="20" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['login_form'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'login-form' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_login_form]', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button nbuf-reset-template" data-template="login-form">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- Registration Form -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Registration Form Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="registration_form" rows="25" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['registration_form'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'registration-form' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_registration_form]', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button nbuf-reset-template" data-template="registration-form">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- Account Page -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Account Page Template', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="account_page" rows="30" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['account_page'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'account-page' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_account_page]', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button nbuf-reset-template" data-template="account-page">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- Request Reset Form -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Request Password Reset Form', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="request_reset_form" rows="20" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['request_reset_form'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'request-reset-form' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_request_reset_form]', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button nbuf-reset-template" data-template="request-reset-form">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- Reset Form -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Password Reset Form', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="reset_form" rows="20" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['reset_form'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( 'reset-form' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_reset_form]', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button nbuf-reset-template" data-template="reset-form">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<!-- =================================================== -->
		<!-- 2FA PAGE TEMPLATES SECTION -->
		<!-- =================================================== -->
		<h2 style="margin-top: 3rem; margin-bottom: 1rem; border-bottom: 2px solid #0073aa; padding-bottom: 0.5rem;">
			<?php esc_html_e( 'Two-Factor Authentication Page Templates', 'nobloat-user-foundry' ); ?>
		</h2>

		<!-- 2FA Verify -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( '2FA Verification Page', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="2fa_verify" rows="20" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['2fa_verify'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( '2fa-verify' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_2fa_verify]', 'nobloat-user-foundry' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- 2FA Setup TOTP -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( '2FA TOTP Setup Page', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="2fa_setup_totp" rows="25" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['2fa_setup_totp'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( '2fa-setup-totp' ) );
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Shortcode: [nbuf_totp_setup]', 'nobloat-user-foundry' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- 2FA Backup Codes -->
		<div class="nbuf-accordion">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( '2FA Backup Codes Page', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<textarea name="2fa_backup_codes" rows="20" class="large-text code nbuf-template-editor"><?php echo esc_textarea( $nbuf_templates_data['2fa_backup_codes'] ); ?></textarea>
					<p class="description">
						<?php
						echo esc_html__( 'Placeholders: ', 'nobloat-user-foundry' ) .
						esc_html( NBUF_Template_Manager::get_placeholders( '2fa-backup-codes' ) );
						?>
					</p>
				</div>
			</div>
		</div>

		<?php submit_button( __( 'Save All Templates', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_templates' ); ?>
	</form>
</div>



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
