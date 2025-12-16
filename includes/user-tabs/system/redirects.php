<?php
/**
 * System > Redirects Tab
 *
 * Default WordPress form redirect options and logout settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Default WordPress redirect settings */
$nbuf_redirect_default_login        = NBUF_Options::get( 'nbuf_redirect_default_login', true );
$nbuf_redirect_default_register     = NBUF_Options::get( 'nbuf_redirect_default_register', true );
$nbuf_redirect_default_logout       = NBUF_Options::get( 'nbuf_redirect_default_logout', true );
$nbuf_redirect_default_lostpassword = NBUF_Options::get( 'nbuf_redirect_default_lostpassword', true );
$nbuf_redirect_default_resetpass    = NBUF_Options::get( 'nbuf_redirect_default_resetpass', true );

/* Login redirect settings */
$nbuf_login_redirect        = NBUF_Options::get( 'nbuf_login_redirect', 'account' );
$nbuf_login_redirect_custom = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );

/* Get base slug for dynamic URL display */
$nbuf_base_slug = NBUF_Options::get( 'nbuf_universal_base_slug', 'user-foundry' );

/* Logout settings */
$nbuf_logout_behavior        = NBUF_Options::get( 'nbuf_logout_behavior', 'immediate' );
$nbuf_logout_redirect        = NBUF_Options::get( 'nbuf_logout_redirect', 'home' );
$nbuf_logout_redirect_custom = NBUF_Options::get( 'nbuf_logout_redirect_custom', '' );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="redirects">

	<h2><?php esc_html_e( 'WordPress Page Redirects', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Redirect default WordPress pages to your custom NoBloat pages. These options affect all users, including administrators.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_redirect_default_login" value="0">
				<label>
					<input type="checkbox" name="nbuf_redirect_default_login" value="1" <?php checked( $nbuf_redirect_default_login, true ); ?>>
					<?php esc_html_e( 'Redirect default login page to NoBloat login page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php will redirect to your custom login page. Affects all users, including administrators.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Registration Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_redirect_default_register" value="0">
				<label>
					<input type="checkbox" name="nbuf_redirect_default_register" value="1" <?php checked( $nbuf_redirect_default_register, true ); ?>>
					<?php esc_html_e( 'Redirect default registration page to NoBloat registration page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php?action=register will redirect to your custom registration page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Logout Redirect', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_redirect_default_logout" value="0">
				<label>
					<input type="checkbox" name="nbuf_redirect_default_logout" value="1" <?php checked( $nbuf_redirect_default_logout, true ); ?>>
					<?php esc_html_e( 'Redirect default logout to NoBloat login page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php?action=logout will redirect to your custom login page after logout instead of the default WordPress message page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Forgot Password Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_redirect_default_lostpassword" value="0">
				<label>
					<input type="checkbox" name="nbuf_redirect_default_lostpassword" value="1" <?php checked( $nbuf_redirect_default_lostpassword, true ); ?>>
					<?php esc_html_e( 'Redirect forgot password page to NoBloat request reset page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php?action=lostpassword will redirect to your custom forgot password page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="hidden" name="nbuf_redirect_default_resetpass" value="0">
				<label>
					<input type="checkbox" name="nbuf_redirect_default_resetpass" value="1" <?php checked( $nbuf_redirect_default_resetpass, true ); ?>>
					<?php esc_html_e( 'Redirect password reset to NoBloat reset page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php?action=rp will redirect to your custom password reset page.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'After Login Redirect', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_login_redirect" id="nbuf_login_redirect">
					<option value="account" <?php selected( $nbuf_login_redirect, 'account' ); ?>>
						<?php
						/* translators: %s: URL path like /user-foundry/account/ */
						printf( esc_html__( 'NoBloat Account (/%s/account/)', 'nobloat-user-foundry' ), esc_html( $nbuf_base_slug ) );
						?>
					</option>
					<option value="admin" <?php selected( $nbuf_login_redirect, 'admin' ); ?>>
						<?php esc_html_e( 'Admin Dashboard', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="home" <?php selected( $nbuf_login_redirect, 'home' ); ?>>
						<?php esc_html_e( 'Home Page', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="custom" <?php selected( $nbuf_login_redirect, 'custom' ); ?>>
						<?php esc_html_e( 'Custom URL', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Where to redirect users after successful login. Default: NoBloat Account', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_login_custom_url_row" style="display: <?php echo ( 'custom' === $nbuf_login_redirect ) ? 'table-row' : 'none'; ?>;">
			<th><?php esc_html_e( 'Custom Redirect URL', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="text" name="nbuf_login_redirect_custom" value="<?php echo esc_attr( $nbuf_login_redirect_custom ); ?>" class="regular-text">
				<p class="description">
					<?php esc_html_e( 'Enter a full URL (e.g., https://example.com/page) or relative path (e.g., /my-account).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Logout Settings', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Logout Behavior', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_logout_behavior">
					<option value="immediate" <?php selected( $nbuf_logout_behavior, 'immediate' ); ?>>
						<?php esc_html_e( 'Immediate Logout', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="confirm" <?php selected( $nbuf_logout_behavior, 'confirm' ); ?>>
						<?php esc_html_e( 'Ask for Confirmation', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Choose whether to log users out immediately or show a confirmation screen. Default: Immediate', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Logout Redirect', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_logout_redirect" id="nbuf_logout_redirect">
					<option value="home" <?php selected( $nbuf_logout_redirect, 'home' ); ?>>
						<?php esc_html_e( 'Home Page', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="login" <?php selected( $nbuf_logout_redirect, 'login' ); ?>>
						<?php esc_html_e( 'Login Page', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="custom" <?php selected( $nbuf_logout_redirect, 'custom' ); ?>>
						<?php esc_html_e( 'Custom URL', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Where to redirect users after logout. Default: Home Page', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr id="nbuf_custom_url_row" style="display: <?php echo ( 'custom' === $nbuf_logout_redirect ) ? 'table-row' : 'none'; ?>;">
			<th><?php esc_html_e( 'Custom Redirect URL', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="text" name="nbuf_logout_redirect_custom" value="<?php echo esc_attr( $nbuf_logout_redirect_custom ); ?>" class="regular-text">
				<p class="description">
					<?php esc_html_e( 'Enter a full URL (e.g., https://example.com/page) or relative path (e.g., /my-page). Default: /', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<script>
	jQuery(document).ready(function($) {
		$('#nbuf_login_redirect').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#nbuf_login_custom_url_row').show();
			} else {
				$('#nbuf_login_custom_url_row').hide();
			}
		});

		$('#nbuf_logout_redirect').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#nbuf_custom_url_row').show();
			} else {
				$('#nbuf_custom_url_row').hide();
			}
		});
	});
	</script>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
