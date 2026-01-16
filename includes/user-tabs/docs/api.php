<?php
/**
 * Docs - API Reference
 *
 * PHP API reference for developers.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'API Reference', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'PHP classes and methods available for developers to interact with user data and plugin functionality.', 'nobloat-user-foundry' ); ?>
</p>

<h2><?php esc_html_e( 'NBUF_User Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Primary class for retrieving user data with caching. Returns an object with access to all user information.', 'nobloat-user-foundry' ); ?></p>

<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Method', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Returns', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>NBUF_User::get( $user_id )</code></td>
			<td><?php esc_html_e( 'Get complete user data including profile fields, verification status, and metadata.', 'nobloat-user-foundry' ); ?></td>
			<td><code>NBUF_User|null</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User::get_many( $user_ids )</code></td>
			<td><?php esc_html_e( 'Batch load multiple users efficiently in a single query.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>$user->is_verified()</code></td>
			<td><?php esc_html_e( 'Check if user has verified their email address.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>$user->is_disabled()</code></td>
			<td><?php esc_html_e( 'Check if user account is disabled.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>$user->is_expired()</code></td>
			<td><?php esc_html_e( 'Check if user account has expired.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>$user->has_2fa()</code></td>
			<td><?php esc_html_e( 'Check if user has two-factor authentication enabled.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>$user->get_display_name()</code></td>
			<td><?php esc_html_e( 'Get user display name with fallback to username.', 'nobloat-user-foundry' ); ?></td>
			<td><code>string</code></td>
		</tr>
		<tr>
			<td><code>$user->get_full_name()</code></td>
			<td><?php esc_html_e( 'Get user full name (first + last).', 'nobloat-user-foundry' ); ?></td>
			<td><code>string</code></td>
		</tr>
		<tr>
			<td><code>$user->to_array()</code></td>
			<td><?php esc_html_e( 'Convert user object to array.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User::invalidate_cache( $user_id )</code></td>
			<td><?php esc_html_e( 'Clear cached data for a specific user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>void</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_User_Data Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages user status data stored in the nbuf_user_data table (verification, expiration, disabled status).', 'nobloat-user-foundry' ); ?></p>

<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Method', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Returns', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>NBUF_User_Data::get( $user_id )</code></td>
			<td><?php esc_html_e( 'Get user data record from custom table.', 'nobloat-user-foundry' ); ?></td>
			<td><code>object|null</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::update( $user_id, $data )</code></td>
			<td><?php esc_html_e( 'Update user data fields.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::delete( $user_id )</code></td>
			<td><?php esc_html_e( 'Delete user data record.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::is_verified( $user_id )</code></td>
			<td><?php esc_html_e( 'Check if user email is verified.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::is_disabled( $user_id )</code></td>
			<td><?php esc_html_e( 'Check if user account is disabled.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::is_expired( $user_id )</code></td>
			<td><?php esc_html_e( 'Check if user account has expired.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::set_verified( $user_id )</code></td>
			<td><?php esc_html_e( 'Mark user as email verified.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::set_disabled( $user_id, $reason )</code></td>
			<td><?php esc_html_e( 'Disable a user account.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::set_enabled( $user_id )</code></td>
			<td><?php esc_html_e( 'Enable a user account.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::set_expiration( $user_id, $expires_at )</code></td>
			<td><?php esc_html_e( 'Set expiration date for user account (null to clear).', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User_Data::get_expiration( $user_id )</code></td>
			<td><?php esc_html_e( 'Get expiration date for user account.', 'nobloat-user-foundry' ); ?></td>
			<td><code>string|null</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Profile_Data Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages extended profile fields stored in the nbuf_user_profile table.', 'nobloat-user-foundry' ); ?></p>

<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Method', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Returns', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>NBUF_Profile_Data::get( $user_id )</code></td>
			<td><?php esc_html_e( 'Get all profile fields for a user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>object|null</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_field( $user_id, $field )</code></td>
			<td><?php esc_html_e( 'Get a single profile field value.', 'nobloat-user-foundry' ); ?></td>
			<td><code>mixed</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::update( $user_id, $fields )</code></td>
			<td><?php esc_html_e( 'Update profile fields for a user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::delete( $user_id )</code></td>
			<td><?php esc_html_e( 'Delete all profile data for a user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_field_registry()</code></td>
			<td><?php esc_html_e( 'Get list of all available profile fields with metadata.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_enabled_fields()</code></td>
			<td><?php esc_html_e( 'Get list of currently enabled profile fields.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_registration_fields()</code></td>
			<td><?php esc_html_e( 'Get fields configured to show on registration.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_account_fields()</code></td>
			<td><?php esc_html_e( 'Get fields configured to show on account page.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Options Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages plugin settings stored in the custom nbuf_options table (not wp_options).', 'nobloat-user-foundry' ); ?></p>

<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Method', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Returns', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>NBUF_Options::get( $key, $default )</code></td>
			<td><?php esc_html_e( 'Get a plugin option value.', 'nobloat-user-foundry' ); ?></td>
			<td><code>mixed</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Options::update( $key, $value )</code></td>
			<td><?php esc_html_e( 'Update a plugin option value.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Options::delete( $key )</code></td>
			<td><?php esc_html_e( 'Delete a plugin option.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Options::exists( $key )</code></td>
			<td><?php esc_html_e( 'Check if an option exists.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Options::get_all()</code></td>
			<td><?php esc_html_e( 'Get all plugin options.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Options::get_multiple( $keys )</code></td>
			<td><?php esc_html_e( 'Get multiple options at once.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Audit_Log Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Log and retrieve user activity for audit purposes.', 'nobloat-user-foundry' ); ?></p>

<table class="form-table widefat striped">
	<thead>
		<tr>
			<th style="padding: 10px;"><?php esc_html_e( 'Method', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="padding: 10px;"><?php esc_html_e( 'Returns', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><code>NBUF_Audit_Log::log( $user_id, $event_type, $event_status, $message, $metadata )</code></td>
			<td><?php esc_html_e( 'Log an action to the audit log.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::get_logs( $filters, $limit, $offset )</code></td>
			<td><?php esc_html_e( 'Retrieve audit log entries with filtering.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::get_logs_count( $filters )</code></td>
			<td><?php esc_html_e( 'Get count of log entries matching filters.', 'nobloat-user-foundry' ); ?></td>
			<td><code>int</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::get_user_logs( $user_id, $limit )</code></td>
			<td><?php esc_html_e( 'Get all logs for a specific user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::delete_logs( $ids )</code></td>
			<td><?php esc_html_e( 'Delete specific log entries by ID.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::anonymize_user_logs( $user_id )</code></td>
			<td><?php esc_html_e( 'Anonymize logs for GDPR user deletion.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::export_to_csv( $filters )</code></td>
			<td><?php esc_html_e( 'Export logs to CSV format.', 'nobloat-user-foundry' ); ?></td>
			<td><code>string</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Usage Examples', 'nobloat-user-foundry' ); ?></h2>
<pre style="background: #f6f7f7; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
// Get complete user data (recommended approach)
$user = NBUF_User::get( $user_id );
if ( $user ) {
    echo 'Email: ' . $user->user_email;
    echo 'Verified: ' . ( $user->is_verified() ? 'Yes' : 'No' );
    echo 'Disabled: ' . ( $user->is_disabled() ? 'Yes' : 'No' );
    echo 'Has 2FA: ' . ( $user->has_2fa() ? 'Yes' : 'No' );
    echo 'Phone: ' . $user->phone;
}

// Batch load users (efficient for lists)
$users = NBUF_User::get_many( array( 1, 2, 3, 4, 5 ) );
foreach ( $users as $user ) {
    echo $user->get_display_name();
}

// Check verification status directly
if ( NBUF_User_Data::is_verified( $user_id ) ) {
    // User is verified
}

// Disable a user account
NBUF_User_Data::set_disabled( $user_id, 'Violation of terms' );

// Set account expiration
NBUF_User_Data::set_expiration( $user_id, '2025-12-31 23:59:59' );

// Update profile data
NBUF_Profile_Data::update( $user_id, array(
    'phone'   => '555-1234',
    'company' => 'Acme Corp',
    'city'    => 'Boston',
) );

// Get plugin option
$enable_2fa = NBUF_Options::get( 'nbuf_enable_2fa', false );

// Log custom action
NBUF_Audit_Log::log(
    $user_id,
    'custom_action',
    'success',
    'User performed custom action',
    array( 'extra_data' => 'value' )
);
</pre>
