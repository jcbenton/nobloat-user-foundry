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
			<td><code>nbuf_user_verified</code></td>
			<td><?php esc_html_e( 'Fires after a user successfully verifies their email address.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_email</code>, <code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_before_profile_update</code></td>
			<td><?php esc_html_e( 'Fires before a user profile is updated.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_after_profile_update</code></td>
			<td><?php esc_html_e( 'Fires after a user updates their profile from the account page.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$fields</code>, <code>$clean_data</code></td>
		</tr>
		<tr>
			<td><code>nbuf_user_expired</code></td>
			<td><?php esc_html_e( 'Fires when a user account expires.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_user_disabled</code></td>
			<td><?php esc_html_e( 'Fires when a user account is disabled.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$reason</code></td>
		</tr>
		<tr>
			<td><code>nbuf_user_approved</code></td>
			<td><?php esc_html_e( 'Fires when a user account is approved by an admin.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_2fa_enabled</code></td>
			<td><?php esc_html_e( 'Fires after a user enables two-factor authentication.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code>, <code>$method</code></td>
		</tr>
		<tr>
			<td><code>nbuf_2fa_disabled</code></td>
			<td><?php esc_html_e( 'Fires after a user disables two-factor authentication.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_public_profile_content</code></td>
			<td><?php esc_html_e( 'Fires within the public profile template to add custom content.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user</code>, <code>$user_data</code></td>
		</tr>
		<tr>
			<td><code>nbuf_account_profile_settings_subtab</code></td>
			<td><?php esc_html_e( 'Fires within the account profile settings subtab.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_account_profile_photo_subtab</code></td>
			<td><?php esc_html_e( 'Fires within the account profile photo subtab.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_account_cover_photo_subtab</code></td>
			<td><?php esc_html_e( 'Fires within the account cover photo subtab.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$user_id</code></td>
		</tr>
		<tr>
			<td><code>nbuf_update_option_{$key}</code></td>
			<td><?php esc_html_e( 'Fires after a specific plugin option is updated. Replace {$key} with the option name.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$old_value</code>, <code>$value</code>, <code>$key</code></td>
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
			<td><code>nbuf_login_redirect</code></td>
			<td><?php esc_html_e( 'Filter the redirect URL after successful login.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$redirect_url</code>, <code>$user</code></td>
		</tr>
		<tr>
			<td><code>nbuf_profile_enabled_fields</code></td>
			<td><?php esc_html_e( 'Filter which profile fields are enabled.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$enabled_fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_profile_registration_fields</code></td>
			<td><?php esc_html_e( 'Filter the profile fields displayed on the registration form.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_profile_account_fields</code></td>
			<td><?php esc_html_e( 'Filter the profile fields displayed on the account page.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$fields</code></td>
		</tr>
		<tr>
			<td><code>nbuf_sanitize_profile_field_{$key}</code></td>
			<td><?php esc_html_e( 'Filter sanitization for a specific profile field. Replace {$key} with field name.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$value</code>, <code>$key</code></td>
		</tr>
		<tr>
			<td><code>nbuf_directory_allowed_params</code></td>
			<td><?php esc_html_e( 'Filter the allowed URL parameters for the member directory.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$allowed_params</code></td>
		</tr>
		<tr>
			<td><code>nbuf_bp_profile_field_mapping</code></td>
			<td><?php esc_html_e( 'Filter BuddyPress profile field mapping during migration.', 'nobloat-user-foundry' ); ?></td>
			<td><code>$mapping</code>, <code>$bp_fields</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Usage Examples', 'nobloat-user-foundry' ); ?></h2>
<pre style="background: #f6f7f7; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
// Customize login redirect based on user role
add_filter( 'nbuf_login_redirect', function( $redirect_url, $user ) {
    if ( in_array( 'administrator', $user->roles ) ) {
        return admin_url();
    }
    return home_url( '/my-account/' );
}, 10, 2 );

// Send notification when user is verified
add_action( 'nbuf_user_verified', function( $user_email, $user_id ) {
    wp_mail(
        get_option( 'admin_email' ),
        'User Verified',
        'User ' . $user_email . ' has verified their email.'
    );
}, 10, 2 );

// Add custom action when account expires
add_action( 'nbuf_user_expired', function( $user_id ) {
    // Log expiration or notify user
    error_log( 'User ' . $user_id . ' account has expired.' );
} );

// Customize profile fields on registration
add_filter( 'nbuf_profile_registration_fields', function( $fields ) {
    // Remove a field from registration
    unset( $fields['company'] );
    return $fields;
} );
</pre>
