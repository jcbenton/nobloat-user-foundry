<?php
/**
 * Docs - Hooks & Filters
 *
 * Reference documentation for available WordPress hooks and filters.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Hooks & Filters Reference', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Use these WordPress hooks and filters to extend or customize plugin functionality.', 'nobloat-user-foundry' ); ?>
</p>

<h2><?php esc_html_e( 'Action Hooks', 'nobloat-user-foundry' ); ?></h2>
<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Hook Name', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Parameters', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>nbuf_after_user_registration</code></td>
			<td><?php esc_html_e( 'Fires after a new user is registered via the registration form.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$user_data</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_profile_update</code></td>
			<td><?php esc_html_e( 'Fires after a user updates their profile from the account page.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$_POST</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_email_verified</code></td>
			<td><?php esc_html_e( 'Fires after a user successfully verifies their email address.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_password_reset</code></td>
			<td><?php esc_html_e( 'Fires after a user resets their password.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_2fa_enabled</code></td>
			<td><?php esc_html_e( 'Fires after a user enables two-factor authentication.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$method</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_2fa_disabled</code></td>
			<td><?php esc_html_e( 'Fires after a user disables two-factor authentication.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_login_success</code></td>
			<td><?php esc_html_e( 'Fires after a successful login via the plugin login form.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$user</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_login_failed</code></td>
			<td><?php esc_html_e( 'Fires after a failed login attempt.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$username</code>, <code>$error</code></td>
		</tr>
		<tr>
			<td><code>nbuf_account_expired</code></td>
			<td><?php esc_html_e( 'Fires when a user account expires.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_version_history_saved</code></td>
			<td><?php esc_html_e( 'Fires after a profile version is saved to history.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$version_id</code>, <code>$changes</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Filter Hooks', 'nobloat-user-foundry' ); ?></h2>
<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Filter Name', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Parameters', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>nbuf_registration_fields</code></td>
			<td><?php esc_html_e( 'Filter the fields displayed on the registration form.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_profile_fields</code></td>
			<td><?php esc_html_e( 'Filter the profile fields available for users.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_login_redirect</code></td>
			<td><?php esc_html_e( 'Filter the redirect URL after successful login.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$redirect_url</code>, <code>$user</code></td>
		</tr>
		<tr>
			<td><code>nbuf_registration_redirect</code></td>
			<td><?php esc_html_e( 'Filter the redirect URL after successful registration.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$redirect_url</code>, <code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_email_template</code></td>
			<td><?php esc_html_e( 'Filter email template content before sending.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$content</code>, <code>$template_name</code>, <code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_password_requirements</code></td>
			<td><?php esc_html_e( 'Filter password validation requirements.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$requirements</code></td>
		</tr>
		<tr>
			<td><code>nbuf_2fa_methods</code></td>
			<td><?php esc_html_e( 'Filter available 2FA methods.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$methods</code></td>
		</tr>
		<tr>
			<td><code>nbuf_account_tabs</code></td>
			<td><?php esc_html_e( 'Filter the tabs displayed on the account page.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$tabs</code>, <code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_user_can_register</code></td>
			<td><?php esc_html_e( 'Filter whether registration is allowed (return false to prevent).', 'nobloat-user-foundry' ); ?></td>
			<td><code>$can_register</code>, <code>$user_data</code></td>
		</tr>
		<tr>
			<td><code>nbuf_verification_expiry</code></td>
			<td><?php esc_html_e( 'Filter how long verification tokens remain valid (in seconds).', 'nobloat-user-foundry' ); ?></td>
			<td><code>$expiry_seconds</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Usage Example', 'nobloat-user-foundry' ); ?></h2>
<pre style="background: #f6f7f7; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
// Customize login redirect based on user role
add_filter( 'nbuf_login_redirect', function( $redirect_url, $user ) {
    if ( in_array( 'administrator', $user->roles ) ) {
        return admin_url();
    }
    return home_url( '/my-account/' );
}, 10, 2 );

// Add custom action after registration
add_action( 'nbuf_after_user_registration', function( $user_id, $user_data ) {
    // Send welcome notification to admin
    wp_mail(
        get_option( 'admin_email' ),
        'New User Registration',
        'A new user has registered: ' . $user_data['user_email']
    );
}, 10, 2 );

// Customize available profile fields
add_filter( 'nbuf_profile_fields', function( $fields ) {
    // Add a custom field
    $fields['custom_field'] = array(
        'label' => 'Custom Field',
        'type'  => 'text',
    );
    return $fields;
} );
</pre>
