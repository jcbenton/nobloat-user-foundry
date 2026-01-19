<?php
/**
 * System > Pages Tab
 *
 * URL configuration for virtual pages.
 * No WordPress pages needed - the plugin intercepts URLs directly.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$nbuf_base_slug    = NBUF_Options::get( 'nbuf_universal_base_slug', 'user-foundry' );
$nbuf_default_view = NBUF_Options::get( 'nbuf_universal_default_view', 'account' );

/* Default view options */
$nbuf_default_view_options = array(
	'account'  => __( 'Account Dashboard', 'nobloat-user-foundry' ),
	'login'    => __( 'Login Form', 'nobloat-user-foundry' ),
	'register' => __( 'Registration Form', 'nobloat-user-foundry' ),
	'members'  => __( 'Member Directory', 'nobloat-user-foundry' ),
);

/* All available URLs */
$nbuf_available_urls = array(
	'login'           => __( 'Login', 'nobloat-user-foundry' ),
	'register'        => __( 'Registration', 'nobloat-user-foundry' ),
	'account'         => __( 'Account Dashboard', 'nobloat-user-foundry' ),
	'forgot-password' => __( 'Forgot Password', 'nobloat-user-foundry' ),
	'reset-password'  => __( 'Reset Password', 'nobloat-user-foundry' ),
	'verify'          => __( 'Email Verification', 'nobloat-user-foundry' ),
	'2fa'             => __( '2FA Verification', 'nobloat-user-foundry' ),
	'2fa-setup'       => __( '2FA Setup', 'nobloat-user-foundry' ),
	'logout'          => __( 'Logout', 'nobloat-user-foundry' ),
	'members'         => __( 'Member Directory', 'nobloat-user-foundry' ),
	'profile/{user}'  => __( 'Public Profile', 'nobloat-user-foundry' ),
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="pages">

	<h2><?php esc_html_e( 'URL Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'All user pages are virtual - no WordPress pages needed. Just configure the base URL and you\'re done.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Base URL Slug', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="text" name="nbuf_universal_base_slug" value="<?php echo esc_attr( $nbuf_base_slug ); ?>" class="regular-text" placeholder="user-foundry">
				<p class="description">
					<?php
					printf(
						/* translators: %s: example URL */
						esc_html__( 'All URLs will start with: %s', 'nobloat-user-foundry' ),
						'<code>' . esc_html( home_url( '/' . $nbuf_base_slug . '/' ) ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Default View', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_universal_default_view">
					<?php foreach ( $nbuf_default_view_options as $nbuf_option_value => $nbuf_option_label ) : ?>
						<option value="<?php echo esc_attr( $nbuf_option_value ); ?>" <?php selected( $nbuf_default_view, $nbuf_option_value ); ?>>
							<?php echo esc_html( $nbuf_option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php
					printf(
						/* translators: %s: example URL */
						esc_html__( 'What to show when visiting the base URL (%s).', 'nobloat-user-foundry' ),
						'<code>' . esc_html( home_url( '/' . $nbuf_base_slug . '/' ) ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<hr>

<h3><?php esc_html_e( 'Available URLs', 'nobloat-user-foundry' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'These URLs are automatically available. No setup required.', 'nobloat-user-foundry' ); ?>
</p>

<table style="max-width: 900px; margin-top: 15px; border-collapse: collapse;">
	<thead>
		<tr>
			<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Page', 'nobloat-user-foundry' ); ?></th>
			<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'URL', 'nobloat-user-foundry' ); ?></th>
			<th style="text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Actions', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $nbuf_available_urls as $nbuf_path => $nbuf_path_label ) : ?>
			<?php $nbuf_full_url = home_url( '/' . $nbuf_base_slug . '/' . $nbuf_path . '/' ); ?>
			<tr>
				<td style="padding: 8px 10px;"><strong><?php echo esc_html( $nbuf_path_label ); ?></strong></td>
				<td style="padding: 8px 10px;">
					<code style="font-size: 12px;"><?php echo esc_html( $nbuf_full_url ); ?></code>
				</td>
				<td style="padding: 8px 10px;">
					<?php if ( strpos( $nbuf_path, '{user}' ) === false ) : ?>
						<button type="button" class="button button-small nbuf-copy-url" data-url="<?php echo esc_attr( $nbuf_full_url ); ?>">
							<?php esc_html_e( 'Copy URL', 'nobloat-user-foundry' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td style="padding: 8px 10px;"><strong><?php esc_html_e( 'Account Tabs', 'nobloat-user-foundry' ); ?></strong></td>
			<td style="padding: 8px 10px;">
				<code style="font-size: 12px;"><?php echo esc_html( home_url( '/' . $nbuf_base_slug . '/account/{tab}/' ) ); ?></code>
				<br>
				<span class="description">
					<?php esc_html_e( 'e.g., /account/profile/, /account/security/', 'nobloat-user-foundry' ); ?>
				</span>
			</td>
			<td style="padding: 8px 10px;"></td>
		</tr>
	</tbody>
</table>

<script>
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.nbuf-copy-url').forEach(function(button) {
		button.addEventListener('click', function() {
			var url = this.getAttribute('data-url');
			var btn = this;
			navigator.clipboard.writeText(url).then(function() {
				var originalText = btn.textContent;
				btn.textContent = '<?php echo esc_js( __( 'Copied!', 'nobloat-user-foundry' ) ); ?>';
				setTimeout(function() {
					btn.textContent = originalText;
				}, 1500);
			});
		});
	});
});
</script>
