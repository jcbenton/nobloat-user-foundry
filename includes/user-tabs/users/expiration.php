<?php
/**
 * Expiration Tab
 *
 * Account expiration settings and warning email templates.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle user expiration form submission
 */
if ( isset( $_POST['nbuf_save_expiration'] ) && check_admin_referer( 'nbuf_expiration_save', 'nbuf_expiration_nonce' ) ) {

	/* Get and sanitize inputs */
	$enable_expiration = isset( $_POST['enable_expiration'] ) ? 1 : 0;
	$warning_days      = isset( $_POST['expiration_warning_days'] ) ? absint( wp_unslash( $_POST['expiration_warning_days'] ) ) : 7;
	$warning_html      = isset( $_POST['expiration_warning_html'] ) ? wp_kses_post( wp_unslash( $_POST['expiration_warning_html'] ) ) : '';
	$warning_text      = isset( $_POST['expiration_warning_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expiration_warning_text'] ) ) : '';

	/* Save options */
	NBUF_Options::update( 'nbuf_enable_expiration', $enable_expiration, true, 'settings' );
	NBUF_Options::update( 'nbuf_expiration_warning_days', $warning_days, true, 'settings' );
	NBUF_Options::update( 'nbuf_expiration_warning_html', $warning_html, false, 'templates' );
	NBUF_Options::update( 'nbuf_expiration_warning_text', $warning_text, false, 'templates' );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Expiration settings saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
}

/**
 * Load current user expiration values
 */
$enable_expiration = NBUF_Options::get( 'nbuf_enable_expiration', false );
$warning_days      = NBUF_Options::get( 'nbuf_expiration_warning_days', 7 );
$warning_html      = NBUF_Options::get( 'nbuf_expiration_warning_html', '' );
$warning_text      = NBUF_Options::get( 'nbuf_expiration_warning_text', '' );

/* Load from defaults if empty */
if ( empty( $warning_html ) ) {
	$default_path = NBUF_TEMPLATES_DIR . 'expiration-warning.html';
	if ( file_exists( $default_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file, not remote URL
		$warning_html = file_get_contents( $default_path );
	}
}
if ( empty( $warning_text ) ) {
	$default_path = NBUF_TEMPLATES_DIR . 'expiration-warning.txt';
	if ( file_exists( $default_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file, not remote URL
		$warning_text = file_get_contents( $default_path );
	}
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Account Expiry Settings', 'nobloat-user-foundry' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure account expiration functionality. When enabled, you can set expiration dates for user accounts. Expired accounts are automatically disabled and users cannot log in.', 'nobloat-user-foundry' ); ?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'nbuf_expiration_save', 'nbuf_expiration_nonce' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="expiration">

		<table class="form-table" role="presentation">
			<!-- Enable Expiration Feature -->
			<tr>
				<th scope="row">
					<label for="enable_expiration"><?php esc_html_e( 'Enable Account Expiration', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="enable_expiration" name="enable_expiration" value="1" <?php checked( $enable_expiration, 1 ); ?>>
						<?php esc_html_e( 'Enable expiration feature', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, you can set expiration dates on user profiles and the "Expires" column will appear in the users list.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Warning Days -->
			<tr>
				<th scope="row">
					<label for="expiration_warning_days"><?php esc_html_e( 'Warning Days', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<input type="number" id="expiration_warning_days" name="expiration_warning_days" value="<?php echo esc_attr( $warning_days ); ?>" min="1" max="90" class="small-text">
					<?php esc_html_e( 'days before expiration', 'nobloat-user-foundry' ); ?>
					<p class="description">
						<?php esc_html_e( 'Send a warning email this many days before the account expires. Default: 7 days.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Expiration Warning Email Templates', 'nobloat-user-foundry' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Customize the email sent to users before their account expires. HTML and plain text versions are both sent.', 'nobloat-user-foundry' ); ?>
		</p>

		<h3><?php esc_html_e( 'HTML Email Template', 'nobloat-user-foundry' ); ?></h3>
		<p class="description" style="margin-bottom:10px;">
			<?php
			printf(
				/* translators: %s: Available placeholders */
				esc_html__( 'Available placeholders: %s', 'nobloat-user-foundry' ),
				'<code>{site_name}</code>, <code>{display_name}</code>, <code>{username}</code>, <code>{expires_date}</code>, <code>{site_url}</code>'
			);
			?>
		</p>
		<textarea name="expiration_warning_html" rows="15" class="large-text code" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $warning_html ); ?></textarea>

		<h3 style="margin-top:30px;"><?php esc_html_e( 'Plain Text Email Template', 'nobloat-user-foundry' ); ?></h3>
		<p class="description" style="margin-bottom:10px;">
			<?php
			printf(
				/* translators: %s: Available placeholders */
				esc_html__( 'Available placeholders: %s', 'nobloat-user-foundry' ),
				'<code>{site_name}</code>, <code>{display_name}</code>, <code>{username}</code>, <code>{expires_date}</code>, <code>{site_url}</code>'
			);
			?>
		</p>
		<textarea name="expiration_warning_text" rows="15" class="large-text code" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $warning_text ); ?></textarea>

		<p class="submit">
			<button type="submit" name="nbuf_save_expiration" class="button button-primary">
				<?php esc_html_e( 'Save Expiration Settings', 'nobloat-user-foundry' ); ?>
			</button>
		</p>
	</form>
</div>


