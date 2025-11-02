<?php
/**
 * Admin Notification Templates Tab
 *
 * Manage admin notification email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current templates */
$admin_new_user_html = NBUF_Template_Manager::load_template( 'admin-new-user-html' );
$admin_new_user_text = NBUF_Template_Manager::load_template( 'admin-new-user-text' );
?>

<div class="nbuf-templates-tab">
	<p class="description">
		<?php esc_html_e( 'Customize the email template sent to administrators when a new user registers on your site.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'nbuf_templates_group' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="templates">
		<input type="hidden" name="nbuf_active_subtab" value="admin-notification">

		<!-- =================================================== -->
		<!-- ADMIN NEW USER NOTIFICATION TEMPLATES -->
		<!-- =================================================== -->
		<div class="nbuf-accordion active">
			<button type="button" class="nbuf-accordion-header">
				<span class="nbuf-accordion-title">
					<?php esc_html_e( 'Admin New User Notification Templates', 'nobloat-user-foundry' ); ?>
				</span>
				<span class="nbuf-accordion-icon">▼</span>
			</button>
			<div class="nbuf-accordion-content">
				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'HTML Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea
						name="nbuf_admin_new_user_html"
						rows="15"
						class="large-text code nbuf-template-editor"
					><?php echo esc_textarea( $admin_new_user_html ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available placeholders: {site_name}, {username}, {user_email}, {registration_date}, {user_profile_link}, {site_url}', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button button-secondary nbuf-reset-template-btn" data-template="admin-new-user" data-type="html">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>

				<div class="nbuf-template-section">
					<h3><?php esc_html_e( 'Plain Text Template', 'nobloat-user-foundry' ); ?></h3>
					<textarea
						name="nbuf_admin_new_user_text"
						rows="12"
						class="large-text code nbuf-template-editor"
					><?php echo esc_textarea( $admin_new_user_text ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Plain text version for email clients that don\'t support HTML. Same placeholders available.', 'nobloat-user-foundry' ); ?>
					</p>
					<p>
						<button type="button" class="button button-secondary nbuf-reset-template-btn" data-template="admin-new-user" data-type="text">
							<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<?php submit_button(); ?>
	</form>
</div>

<style>
.nbuf-templates-tab {
    max-width: 1200px;
}

.nbuf-accordion {
    border: 1px solid #ddd;
    margin-bottom: 1rem;
    border-radius: 4px;
    overflow: hidden;
}

.nbuf-accordion-header {
    width: 100%;
    background: #f8f8f8;
    border: none;
    padding: 1rem 1.5rem;
    text-align: left;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    font-weight: 600;
}

.nbuf-accordion-header:hover {
    background: #f0f0f0;
}

.nbuf-accordion-content {
    display: none;
    background: #fff;
}

.nbuf-accordion.active .nbuf-accordion-content {
    display: block;
}

.nbuf-accordion.active .nbuf-accordion-icon {
    transform: rotate(180deg);
}

.nbuf-accordion-icon {
    transition: transform 0.3s ease;
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
} );
</script>
