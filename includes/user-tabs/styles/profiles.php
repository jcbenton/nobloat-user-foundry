<?php
/**
 * Profile Page Styles Tab
 *
 * CSS customization for public profile pages.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle reset to default request
 */
if ( isset( $_POST['nbuf_reset_profile_css'] ) && check_admin_referer( 'nbuf_profile_css_save', 'nbuf_profile_css_nonce' ) ) {
	/* Load default from template */
	$nbuf_default_css = NBUF_CSS_Manager::load_default_css( 'profile' );

	/* Save default to database */
	NBUF_Options::update( 'nbuf_profile_custom_css', $nbuf_default_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_default_css, 'profile', 'nbuf_css_write_failed_profile' );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profile styles reset to default.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Profile styles reset to default in database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/**
 * Handle profile page styles form submission
 */
if ( isset( $_POST['nbuf_save_profile_css'] ) && check_admin_referer( 'nbuf_profile_css_save', 'nbuf_profile_css_nonce' ) ) {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via NBUF_CSS_Manager::sanitize_css().
	$nbuf_profile_css = isset( $_POST['profile_custom_css'] ) ? NBUF_CSS_Manager::sanitize_css( wp_unslash( $_POST['profile_custom_css'] ) ) : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	/* Save to database */
	NBUF_Options::update( 'nbuf_profile_custom_css', $nbuf_profile_css, false, 'css' );

	/* Write to disk */
	$nbuf_success = NBUF_CSS_Manager::save_css_to_disk( $nbuf_profile_css, 'profile', 'nbuf_css_write_failed_profile' );

	if ( $nbuf_success ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profile page styles saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Styles saved to database, but could not write to disk. Check file permissions.', 'nobloat-user-foundry' ) . '</p></div>';
	}
}

/* Load CSS: check database first, fall back to disk default */
$nbuf_profile_css = NBUF_Options::get( 'nbuf_profile_custom_css', '' );
if ( empty( $nbuf_profile_css ) ) {
	$nbuf_profile_css = NBUF_CSS_Manager::load_default_css( 'profile' );
}

$nbuf_has_write_failure = NBUF_CSS_Manager::has_write_failure( 'nbuf_css_write_failed_profile' );

?>

<div class="nbuf-styles-tab">

	<?php if ( $nbuf_has_write_failure ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'File Write Permission Issue:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'Unable to write CSS file to disk. Styles are being loaded from database.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_profile_css_save', 'nbuf_profile_css_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="profiles">

		<div class="nbuf-style-section">
			<p class="description">
				<?php esc_html_e( 'Customize the CSS for public profile pages. Changes are saved to the database. Use "Reset to Default" to restore the original styling from the plugin.', 'nobloat-user-foundry' ); ?>
			</p>

			<textarea
				name="profile_custom_css"
				rows="25"
				class="large-text code nbuf-css-editor"
				spellcheck="false"
			><?php echo esc_textarea( $nbuf_profile_css ); ?></textarea>

			<p class="nbuf-button-row">
				<?php submit_button( __( 'Save Profile Styles', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_profile_css', false ); ?>

				<button
					type="submit"
					name="nbuf_reset_profile_css"
					class="button"
					onclick="return confirm('<?php echo esc_js( __( 'Reset profile CSS to the default? Your custom styles will be lost.', 'nobloat-user-foundry' ) ); ?>');"
				>
					<?php esc_html_e( 'Reset to Default', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>
	</form>

	<div class="nbuf-style-info">
		<h3><?php esc_html_e( 'CSS Class Reference', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Profile Container Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-profile-page</code> - Main profile wrapper</li>
			<li><code>.nbuf-profile-header</code> - Header section with cover photo</li>
			<li><code>.nbuf-profile-cover</code> - Cover photo container</li>
			<li><code>.nbuf-profile-avatar-wrap</code> - Avatar wrapper</li>
			<li><code>.nbuf-profile-avatar</code> - Avatar image</li>
			<li><code>.nbuf-profile-info</code> - User info section</li>
			<li><code>.nbuf-profile-name</code> - User display name</li>
			<li><code>.nbuf-profile-username</code> - Username</li>
			<li><code>.nbuf-profile-bio</code> - Bio/description</li>
			<li><code>.nbuf-profile-meta</code> - Meta information (joined date, etc.)</li>
			<li><code>.nbuf-profile-content</code> - Main content area</li>
			<li><code>.nbuf-profile-actions</code> - Action buttons</li>
		</ul>

		<h4><?php esc_html_e( 'Profile Fields Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-profile-fields</code> - Fields container</li>
			<li><code>.nbuf-profile-fields-title</code> - Fields section title</li>
			<li><code>.nbuf-profile-fields-grid</code> - Fields grid layout</li>
			<li><code>.nbuf-profile-field</code> - Individual field wrapper</li>
			<li><code>.nbuf-profile-field-label</code> - Field label</li>
			<li><code>.nbuf-profile-field-value</code> - Field value</li>
		</ul>

		<h4><?php esc_html_e( 'Avatar Classes:', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><code>.nbuf-avatar</code> - Avatar image (replaces WordPress default)</li>
			<li><code>.nbuf-svg-avatar</code> - SVG initials avatar</li>
			<li><code>.nbuf-avatar-small</code> - Small size (32px)</li>
			<li><code>.nbuf-avatar-medium</code> - Medium size (64px)</li>
			<li><code>.nbuf-avatar-large</code> - Large size (96px)</li>
			<li><code>.nbuf-avatar-xl</code> - Extra large size (150px)</li>
		</ul>
	</div>
</div>

<style>
.nbuf-button-row {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-top: 10px;
}
.nbuf-button-row .button {
	margin: 0 !important;
}
</style>
