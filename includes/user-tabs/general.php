<?php
/**
 * General Tab
 *
 * Controls verification and reset URLs, hook behavior,
 * and uninstall cleanup options.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_settings = NBUF_Options::get( 'nbuf_settings', array() );
$nbuf_cleanup  = (array) ( $nbuf_settings['cleanup'] ?? array() );
$nbuf_hooks    = (array) ( $nbuf_settings['hooks'] ?? array() );
$nbuf_custom   = sanitize_text_field( $nbuf_settings['hooks_custom'] ?? '' );

/* Feature toggles */
$nbuf_enable_login          = NBUF_Options::get( 'nbuf_enable_login', true );
$nbuf_enable_password_reset = NBUF_Options::get( 'nbuf_enable_password_reset', true );
$nbuf_enable_custom_roles   = NBUF_Options::get( 'nbuf_enable_custom_roles', true );

/* Logout settings */
$nbuf_logout_behavior        = NBUF_Options::get( 'nbuf_logout_behavior', 'immediate' );
$nbuf_logout_redirect        = NBUF_Options::get( 'nbuf_logout_redirect', 'home' );
$nbuf_logout_redirect_custom = NBUF_Options::get( 'nbuf_logout_redirect_custom', '' );

/* Default WordPress redirect settings */
$nbuf_redirect_default_login        = NBUF_Options::get( 'nbuf_redirect_default_login', false );
$nbuf_redirect_default_register     = NBUF_Options::get( 'nbuf_redirect_default_register', false );
$nbuf_redirect_default_logout       = NBUF_Options::get( 'nbuf_redirect_default_logout', false );
$nbuf_redirect_default_lostpassword = NBUF_Options::get( 'nbuf_redirect_default_lostpassword', false );
$nbuf_redirect_default_resetpass    = NBUF_Options::get( 'nbuf_redirect_default_resetpass', false );

/* Plugin page IDs */
$nbuf_page_verification  = NBUF_Options::get( 'nbuf_page_verification', 0 );
$nbuf_page_reset         = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
$nbuf_page_request_reset = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
$nbuf_page_login         = NBUF_Options::get( 'nbuf_page_login', 0 );
$nbuf_page_registration  = NBUF_Options::get( 'nbuf_page_registration', 0 );
$nbuf_page_account       = NBUF_Options::get( 'nbuf_page_account', 0 );

/* Master toggle */
$nbuf_user_manager_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Master Toggle -->
	<div style="background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'User Manager Status', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" style="margin-bottom: 0;">
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'System Status', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_user_manager_enabled" value="1" <?php checked( $nbuf_user_manager_enabled, true ); ?> id="nbuf_user_manager_enabled">
						<strong><?php esc_html_e( 'Enable User Management System', 'nobloat-user-foundry' ); ?></strong>
					</label>
					<?php if ( ! $nbuf_user_manager_enabled ) : ?>
						<p class="description" style="color: #d63638; font-weight: 500;">
							⚠️ <?php esc_html_e( 'User management is currently DISABLED. Configure your settings and migrate any data before enabling.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php else : ?>
						<p class="description" style="color: #00a32a; font-weight: 500;">
							✓ <?php esc_html_e( 'User management is currently ENABLED. All features are active.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'When disabled, the plugin will not modify user behavior, authentication, or the users list. Use this to configure settings and prepare for migration before activating.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<h2><?php esc_html_e( 'Feature Toggles', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Custom Login Form', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_login" value="1" <?php checked( $nbuf_enable_login, true ); ?>>
					<?php esc_html_e( 'Enable custom login form', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Use NoBloat login form via [nbuf_login_form] shortcode. When disabled, the shortcode will display a message.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_password_reset" value="1" <?php checked( $nbuf_enable_password_reset, true ); ?>>
					<?php esc_html_e( 'Enable password reset functionality', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Allow users to request password resets. When disabled, password reset forms will display a message. The "Forgot Password?" link will not appear on login forms when disabled.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Custom User Roles', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_enable_custom_roles" value="1" <?php checked( $nbuf_enable_custom_roles, true ); ?>>
					<?php esc_html_e( 'Enable custom user roles management', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Create and manage custom user roles with granular permissions. When enabled, the "Roles" menu item will appear. Custom roles automatically integrate with access restrictions and all WordPress role-based features.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'WordPress Page Redirects', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Redirect default WordPress pages to your custom NoBloat pages. These options affect all users, including administrators.', 'nobloat-user-foundry' ); ?>
	</p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Login Page', 'nobloat-user-foundry' ); ?></th>
			<td>
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
				<label>
					<input type="checkbox" name="nbuf_redirect_default_resetpass" value="1" <?php checked( $nbuf_redirect_default_resetpass, true ); ?>>
					<?php esc_html_e( 'Redirect password reset to NoBloat reset page', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, wp-login.php?action=rp will redirect to your custom password reset page.', 'nobloat-user-foundry' ); ?>
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
		$('#nbuf_logout_redirect').on('change', function() {
			if ($(this).val() === 'custom') {
				$('#nbuf_custom_url_row').show();
			} else {
				$('#nbuf_custom_url_row').hide();
			}
		});
	});
	</script>

	<h2><?php esc_html_e( 'Plugin Pages', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Verification Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_verification',
						'selected'          => absint( $nbuf_page_verification ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_verify_page] shortcode. Auto-created as "NoBloat Verification" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_password_reset',
						'selected'          => absint( $nbuf_page_reset ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_reset_form] shortcode. Auto-created as "NoBloat Password Reset" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Request Password Reset Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_request_reset',
						'selected'          => absint( $nbuf_page_request_reset ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_request_reset_form] shortcode. Auto-created as "NoBloat Request Password Reset" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Login Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_login',
						'selected'          => absint( $nbuf_page_login ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_login_form] shortcode. Auto-created as "NoBloat Login" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Registration Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_registration',
						'selected'          => absint( $nbuf_page_registration ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_registration_form] shortcode. Auto-created as "NoBloat Register" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Account Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_account',
						'selected'          => absint( $nbuf_page_account ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_account_page] shortcode. Auto-created as "NoBloat User Account" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Hooks', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Registration Hooks', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				$nbuf_available_hooks = array(
					'user_register' => __( 'user_register — default WordPress registration.', 'nobloat-user-foundry' ),
				);
				foreach ( $nbuf_available_hooks as $nbuf_hook => $nbuf_desc ) {
					printf(
						'<label style="display:block;"><input type="checkbox" name="nbuf_settings[hooks][]" value="%s" %s> %s</label>',
						esc_attr( $nbuf_hook ),
						checked( in_array( $nbuf_hook, $nbuf_hooks, true ), true, false ),
						esc_html( $nbuf_desc )
					);
				}
				?>
				<label style="display:block;margin-top:8px;">
					<input type="checkbox" name="nbuf_settings[reverify_on_email_change]" value="1" <?php checked( ! empty( $settings['reverify_on_email_change'] ), true ); ?>>
					<?php esc_html_e( 'Require re-verification if a user changes their email address.', 'nobloat-user-foundry' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Custom Hook', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_settings[custom_hook_enabled]" value="1" <?php checked( ! empty( $settings['custom_hook_enabled'] ), true ); ?>>
					<?php esc_html_e( 'Enable custom hook listener', 'nobloat-user-foundry' ); ?>
				</label>
				<div style="margin-top:6px;">
					<input type="text" name="nbuf_settings[hooks_custom]" value="<?php echo esc_attr( $custom ); ?>" placeholder="custom_user_register_hook" class="regular-text">
					<p class="description"><?php esc_html_e( 'Custom action name fired after user creation (e.g., from custom registration logic).', 'nobloat-user-foundry' ); ?></p>
				</div>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Plugin Activation', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Auto-Verify Existing Users', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_settings[auto_verify_existing]" value="1" <?php checked( ! empty( $settings['auto_verify_existing'] ), true ); ?>>
					<?php esc_html_e( 'Automatically verify all existing users when the plugin is activated.', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Disable this if you want to require verification for existing users after reactivating the plugin.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Uninstall Cleanup', 'nobloat-user-foundry' ); ?></h2>
	<fieldset class="nbuf-cleanup">
		<?php
		$nbuf_cleanup_options = array(
			'tokens'    => __( 'Delete all verification tokens.', 'nobloat-user-foundry' ),
			'settings'  => __( 'Delete plugin settings.', 'nobloat-user-foundry' ),
			'templates' => __( 'Delete templates.', 'nobloat-user-foundry' ),
			'usermeta'  => __( 'Delete user verification and disabled status meta fields.', 'nobloat-user-foundry' ),
		);
		foreach ( $nbuf_cleanup_options as $nbuf_key => $nbuf_label ) {
			printf(
				'<label style="display:block;"><input type="checkbox" name="nbuf_settings[cleanup][]" value="%s" %s> %s</label>',
				esc_attr( $nbuf_key ),
				checked( in_array( $nbuf_key, $nbuf_cleanup, true ), true, false ),
				esc_html( $nbuf_label )
			);
		}
		?>
	</fieldset>

	<input type="hidden" name="nbuf_active_tab" value="general">
	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>