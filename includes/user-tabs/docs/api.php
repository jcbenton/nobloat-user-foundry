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
<p class="description"><?php esc_html_e( 'Primary class for retrieving and managing user data.', 'nobloat-user-foundry' ); ?></p>

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
			<td><code>object|null</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User::is_verified( $user_id )</code></td>
			<td><?php esc_html_e( 'Check if a user has verified their email address.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User::is_expired( $user_id )</code></td>
			<td><?php esc_html_e( 'Check if a user account has expired.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_User::get_expiration_date( $user_id )</code></td>
			<td><?php esc_html_e( 'Get the expiration date for a user account.', 'nobloat-user-foundry' ); ?></td>
			<td><code>string|null</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_User_Data Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages user metadata stored in the custom nbuf_user_data table.', 'nobloat-user-foundry' ); ?></p>

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
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Profile_Data Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages extended profile fields stored in the custom nbuf_profile_data table.', 'nobloat-user-foundry' ); ?></p>

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
			<td><code>NBUF_Profile_Data::update( $user_id, $data )</code></td>
			<td><?php esc_html_e( 'Update profile fields for a user.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Profile_Data::get_field_registry()</code></td>
			<td><?php esc_html_e( 'Get list of all available profile fields.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Options Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Manages plugin settings stored in the custom nbuf_options table.', 'nobloat-user-foundry' ); ?></p>

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
	</tbody>
</table>

<h2><?php esc_html_e( 'NBUF_Audit_Log Class', 'nobloat-user-foundry' ); ?></h2>
<p class="description"><?php esc_html_e( 'Log user actions for audit purposes.', 'nobloat-user-foundry' ); ?></p>

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
			<td><code>NBUF_Audit_Log::log( $user_id, $action, $status, $message, $meta )</code></td>
			<td><?php esc_html_e( 'Log an action to the audit log.', 'nobloat-user-foundry' ); ?></td>
			<td><code>bool</code></td>
		</tr>
		<tr>
			<td><code>NBUF_Audit_Log::get_logs( $args )</code></td>
			<td><?php esc_html_e( 'Retrieve audit log entries with filtering.', 'nobloat-user-foundry' ); ?></td>
			<td><code>array</code></td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Usage Examples', 'nobloat-user-foundry' ); ?></h2>
<pre style="background: #f6f7f7; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
// Get complete user data
$user = NBUF_User::get( $user_id );
if ( $user ) {
    echo 'Email: ' . $user->user_email;
    echo 'Verified: ' . ( $user->is_verified ? 'Yes' : 'No' );
    echo 'Phone: ' . $user->profile->phone;
}

// Check verification status
if ( NBUF_User::is_verified( $user_id ) ) {
    // User is verified
}

// Update profile data
NBUF_Profile_Data::update( $user_id, array(
    'phone'    => '555-1234',
    'company'  => 'Acme Corp',
    'city'     => 'Boston',
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
