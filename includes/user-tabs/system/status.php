<?php
/**
 * System > Status Tab
 *
 * Master toggle for the User Management System.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Master toggle */
$nbuf_user_manager_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="status">

	<!-- Master Toggle -->
	<div style="background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'User Manager Status', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" style="margin-bottom: 0;">
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'System Status', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_user_manager_enabled" value="0">
					<label>
						<input type="checkbox" name="nbuf_user_manager_enabled" value="1" <?php checked( $nbuf_user_manager_enabled, true ); ?> id="nbuf_user_manager_enabled">
						<strong><?php esc_html_e( 'Enable User Management System', 'nobloat-user-foundry' ); ?></strong>
					</label>
					<?php if ( ! $nbuf_user_manager_enabled ) : ?>
						<p class="description" style="color: #d63638; font-weight: 500;">
							<?php esc_html_e( 'User management is currently DISABLED. Configure your settings and migrate any data before enabling.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php else : ?>
						<p class="description" style="color: #00a32a; font-weight: 500;">
							<?php esc_html_e( 'User management is currently ENABLED. All features are active.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'When disabled, the plugin will not modify user behavior, authentication, or the users list. Use this to configure settings and prepare for migration before activating.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Virtual Page URLs -->
	<div style="border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Virtual Page URLs', 'nobloat-user-foundry' ); ?></h2>
		<p class="description" style="margin-bottom: 15px;">
			<?php esc_html_e( 'These URLs are automatically available when Universal Page Mode is enabled. The base slug can be changed in System > Pages.', 'nobloat-user-foundry' ); ?>
			<br>
			<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'These links will not work until the User Management System is enabled above.', 'nobloat-user-foundry' ); ?>
		</p>

		<?php
		$nbuf_urls = array(
			'login'           => array(
				'label' => __( 'Login', 'nobloat-user-foundry' ),
				'desc'  => __( 'User login form with optional passkey support.', 'nobloat-user-foundry' ),
			),
			'register'        => array(
				'label' => __( 'Registration', 'nobloat-user-foundry' ),
				'desc'  => __( 'New user registration form.', 'nobloat-user-foundry' ),
			),
			'account'         => array(
				'label' => __( 'Account', 'nobloat-user-foundry' ),
				'desc'  => __( 'User account dashboard with profile, security, and settings.', 'nobloat-user-foundry' ),
			),
			'forgot-password' => array(
				'label' => __( 'Forgot Password', 'nobloat-user-foundry' ),
				'desc'  => __( 'Request a password reset link via email.', 'nobloat-user-foundry' ),
			),
			'reset-password'  => array(
				'label' => __( 'Reset Password', 'nobloat-user-foundry' ),
				'desc'  => __( 'Set a new password using the reset link.', 'nobloat-user-foundry' ),
			),
			'verify'          => array(
				'label' => __( 'Email Verification', 'nobloat-user-foundry' ),
				'desc'  => __( 'Confirms user email address via verification link.', 'nobloat-user-foundry' ),
			),
			'logout'          => array(
				'label' => __( 'Logout', 'nobloat-user-foundry' ),
				'desc'  => __( 'Logs user out and redirects based on settings.', 'nobloat-user-foundry' ),
			),
			'profile'         => array(
				'label' => __( 'Public Profile', 'nobloat-user-foundry' ),
				'desc'  => __( 'View a user\'s public profile (if enabled).', 'nobloat-user-foundry' ),
			),
			'members'         => array(
				'label' => __( 'Member Directory', 'nobloat-user-foundry' ),
				'desc'  => __( 'Searchable directory of site members (if enabled).', 'nobloat-user-foundry' ),
			),
			'2fa'             => array(
				'label' => __( '2FA Verification', 'nobloat-user-foundry' ),
				'desc'  => __( 'Two-factor authentication code entry during login.', 'nobloat-user-foundry' ),
			),
			'2fa-setup'       => array(
				'label' => __( '2FA Setup', 'nobloat-user-foundry' ),
				'desc'  => __( 'Configure authenticator app for two-factor authentication.', 'nobloat-user-foundry' ),
			),
		);

		/* Check if magic links are enabled */
		$nbuf_magic_links_enabled = NBUF_Options::get( 'nbuf_magic_links_enabled', false );
		if ( $nbuf_magic_links_enabled ) {
			$nbuf_urls['magic-link'] = array(
				'label' => __( 'Magic Link', 'nobloat-user-foundry' ),
				'desc'  => __( 'Passwordless login via email link.', 'nobloat-user-foundry' ),
			);
		}
		?>

		<table class="wp-list-table widefat fixed" style="margin-top: 10px; background: transparent;">
			<thead>
				<tr>
					<th style="width: 140px;"><?php esc_html_e( 'Page', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 45%;"><?php esc_html_e( 'URL', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $nbuf_urls as $nbuf_view => $nbuf_info ) : ?>
					<?php
					$nbuf_url = '';
					if ( class_exists( 'NBUF_Universal_Router' ) ) {
						$nbuf_url = NBUF_Universal_Router::get_url( $nbuf_view );
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $nbuf_info['label'] ); ?></strong></td>
						<td>
							<?php if ( $nbuf_url ) : ?>
								<code style="font-size: 12px;"><?php echo esc_url( $nbuf_url ); ?></code>
							<?php else : ?>
								<span style="color: #d63638;"><?php esc_html_e( 'Not configured', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td><span class="description"><?php echo esc_html( $nbuf_info['desc'] ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
