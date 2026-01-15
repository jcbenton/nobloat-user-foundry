<?php
/**
 * Security > Magic Links Settings Tab
 *
 * Passwordless login via email magic links.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Magic links options */
$nbuf_magic_links_enabled    = NBUF_Options::get( 'nbuf_magic_links_enabled', false );
$nbuf_magic_links_expiration = NBUF_Options::get( 'nbuf_magic_links_expiration', 15 );
$nbuf_magic_links_rate_limit = NBUF_Options::get( 'nbuf_magic_links_rate_limit', 3 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="magic-links">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_magic_links_enabled">

	<h2><?php esc_html_e( 'Magic Link Login', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Magic links allow users to log in without entering a password. They receive a secure, one-time use link via email that logs them in automatically.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Magic Links', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_magic_links_enabled" value="1" <?php checked( $nbuf_magic_links_enabled, true ); ?>>
					<?php esc_html_e( 'Allow users to log in via email magic links', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, users can request a magic link from the login page. The shortcode [nbuf_magic_link_form] displays the request form.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Link Expiration', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_magic_links_expiration" value="<?php echo esc_attr( $nbuf_magic_links_expiration ); ?>" min="5" max="60" class="small-text">
				<span><?php esc_html_e( 'minutes', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'How long magic links remain valid. Shorter times are more secure. Default: 15 minutes.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Rate Limit', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_magic_links_rate_limit" value="<?php echo esc_attr( $nbuf_magic_links_rate_limit ); ?>" min="1" max="10" class="small-text">
				<span><?php esc_html_e( 'requests per hour per email', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Limit how many magic links can be requested for the same email address per hour. Prevents abuse. Default: 3.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<h2><?php esc_html_e( 'How It Works', 'nobloat-user-foundry' ); ?></h2>
<ol style="margin-left: 20px;">
	<li><?php esc_html_e( 'User enters their email address on the magic link form', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'If the email exists, a secure one-time link is sent', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'User clicks the link in their email', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'User is automatically logged in', 'nobloat-user-foundry' ); ?></li>
</ol>

<h2><?php esc_html_e( 'Security Features', 'nobloat-user-foundry' ); ?></h2>
<ul style="list-style: disc; margin-left: 20px;">
	<li><?php esc_html_e( 'One-time use: Each link can only be used once', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'Time-limited: Links expire after the configured time', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'Rate-limited: Prevents brute force attacks on email addresses', 'nobloat-user-foundry' ); ?></li>
	<li><?php esc_html_e( 'No enumeration: Same response whether email exists or not', 'nobloat-user-foundry' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Using Magic Links', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'You can add the magic link form using:', 'nobloat-user-foundry' ); ?>
</p>
<ul style="list-style: disc; margin-left: 20px;">
	<li>
		<strong><?php esc_html_e( 'Shortcode:', 'nobloat-user-foundry' ); ?></strong>
		<code>[nbuf_magic_link_form]</code>
	</li>
	<li>
		<strong><?php esc_html_e( 'URL:', 'nobloat-user-foundry' ); ?></strong>
		<code>/user-foundry/magic-link/</code>
	</li>
</ul>
