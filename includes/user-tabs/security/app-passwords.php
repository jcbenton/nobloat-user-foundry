<?php
/**
 * Security > Application Passwords Tab
 *
 * Settings for user-manageable application passwords on frontend.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Application passwords settings */
$nbuf_app_passwords_enabled = NBUF_Options::get( 'nbuf_app_passwords_enabled', false );

/* Statistics - count users with application passwords */
$users_with_app_passwords = 0;
$total_app_passwords      = 0;
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$users_with_app_passwords = (int) $wpdb->get_var(
	"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_application_passwords' AND meta_value != 'a:0:{}' AND meta_value != ''"
);
if ( $users_with_app_passwords > 0 ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$all_passwords = $wpdb->get_col(
		"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_application_passwords' AND meta_value != 'a:0:{}' AND meta_value != ''"
	);
	foreach ( $all_passwords as $serialized ) {
		$passwords = maybe_unserialize( $serialized );
		if ( is_array( $passwords ) ) {
			$total_app_passwords += count( $passwords );
		}
	}
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="app-passwords">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_app_passwords_enabled">

	<h2><?php esc_html_e( 'Application Passwords', 'nobloat-user-foundry' ); ?></h2>

	<p class="description" style="margin-bottom: 20px;">
		<?php esc_html_e( 'Application passwords allow users to authenticate with the WordPress REST API without using their main password. This is useful for mobile apps, external services, or other integrations.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable for Users', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_app_passwords_enabled" value="1" <?php checked( $nbuf_app_passwords_enabled, true ); ?>>
					<?php esc_html_e( 'Allow users to manage application passwords from their account page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can create and revoke application passwords from the frontend account page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="nbuf-info-box" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; margin: 20px 0;">
		<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'About Application Passwords', 'nobloat-user-foundry' ); ?></h4>
		<ul style="margin: 0; padding-left: 20px;">
			<li><?php esc_html_e( 'Application passwords are stored securely (hashed) in the database', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'They only work for REST API and XML-RPC requests, not the login page', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Each password can be individually revoked without affecting others', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Users can see when each password was last used', 'nobloat-user-foundry' ); ?></li>
			<li style="color: #996800;"><strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Application passwords bypass two-factor authentication (by design for programmatic access)', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>

	<div class="nbuf-info-box" style="background: #fcf9e8; border-left: 4px solid #dba617; padding: 12px 15px; margin: 20px 0;">
		<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Security Considerations', 'nobloat-user-foundry' ); ?></h4>
		<ul style="margin: 0; padding-left: 20px;">
			<li><?php esc_html_e( 'Application passwords have the same permissions as the user who created them', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'If compromised, an attacker has full API access as that user', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Users should only create passwords for apps they trust', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Encourage users to revoke unused passwords regularly', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<?php if ( $users_with_app_passwords > 0 ) : ?>
<h2><?php esc_html_e( 'Statistics', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php
	printf(
		/* translators: 1: total app passwords count, 2: users with app passwords count */
		esc_html__( '%1$d application passwords created by %2$d users.', 'nobloat-user-foundry' ),
		(int) $total_app_passwords,
		(int) $users_with_app_passwords
	);
	?>
</p>
<?php endif; ?>
