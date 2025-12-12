<?php
/**
 * Users Tab - Profile Change Notifications
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$notify_enabled   = NBUF_Options::get( 'nbuf_notify_profile_changes', false );
$notify_new_users = NBUF_Options::get( 'nbuf_notify_new_registrations', false );
$notify_to        = NBUF_Options::get( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ) );
$notify_fields    = NBUF_Options::get( 'nbuf_notify_profile_changes_fields', array( 'user_email', 'display_name' ) );
$notify_digest    = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

/* Convert to array if string */
if ( is_string( $notify_to ) ) {
	$notify_to = $notify_to;
} elseif ( is_array( $notify_to ) ) {
	$notify_to = implode( ', ', $notify_to );
}

/* Ensure $notify_fields is array */
if ( ! is_array( $notify_fields ) ) {
	$notify_fields = array( 'user_email', 'display_name' );
}

/* Available fields to monitor */
$available_fields = array(
	'Core WordPress Fields' => array(
		'user_email'   => 'Email Address',
		'display_name' => 'Display Name',
		'first_name'   => 'First Name',
		'last_name'    => 'Last Name',
		'description'  => 'Bio / Description',
	),
	'Profile Fields'        => array(
		'bio'       => 'Bio',
		'phone'     => 'Phone',
		'city'      => 'City',
		'state'     => 'State',
		'country'   => 'Country',
		'company'   => 'Company',
		'job_title' => 'Job Title',
	),
	'Security & Privacy'    => array(
		'2fa_status'      => '2FA Enabled/Disabled',
		'profile_privacy' => 'Profile Privacy Setting',
	),
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="change-notifications">

	<h2><?php esc_html_e( 'Enable Notifications', 'nobloat-user-foundry' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Get notified when users make changes to their profiles.', 'nobloat-user-foundry' ); ?></p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="nbuf_notify_profile_changes"><?php esc_html_e( 'Profile Change Notifications', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox"
							name="nbuf_notify_profile_changes"
							id="nbuf_notify_profile_changes"
							value="1"
							<?php checked( $notify_enabled ); ?> />
					<?php esc_html_e( 'Enable profile change notifications', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When enabled, admins will be notified of profile changes.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="nbuf_notify_new_registrations"><?php esc_html_e( 'New User Notifications', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox"
							name="nbuf_notify_new_registrations"
							id="nbuf_notify_new_registrations"
							value="1"
							<?php checked( $notify_new_users ); ?> />
					<?php esc_html_e( 'Notify when new users register', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Get notified immediately when a new user registers.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Notification Recipients', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="nbuf_notify_profile_changes_to"><?php esc_html_e( 'Send Notifications To', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="text"
						name="nbuf_notify_profile_changes_to"
						id="nbuf_notify_profile_changes_to"
						value="<?php echo esc_attr( $notify_to ); ?>"
						class="regular-text" />
				<p class="description">
					<?php esc_html_e( 'Email addresses to receive notifications. Separate multiple addresses with commas.', 'nobloat-user-foundry' ); ?><br/>
					<?php
					printf(
						/* translators: %s: admin email address */
						esc_html__( 'Default: Site admin email (%s)', 'nobloat-user-foundry' ),
						esc_html( get_option( 'admin_email' ) )
					);
					?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Monitored Fields', 'nobloat-user-foundry' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Select which profile fields to monitor for changes.', 'nobloat-user-foundry' ); ?></p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Fields to Monitor', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php foreach ( $available_fields as $group_name => $fields ) : ?>
					<fieldset style="margin-bottom: 20px;">
						<legend style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html( $group_name ); ?></legend>
					<?php foreach ( $fields as $field_key => $field_label ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox"
										name="nbuf_notify_profile_changes_fields[]"
										value="<?php echo esc_attr( $field_key ); ?>"
						<?php checked( in_array( $field_key, $notify_fields, true ) ); ?> />
						<?php echo esc_html( $field_label ); ?>
							</label>
					<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>

				<p class="description">
					<strong><?php esc_html_e( 'Recommended minimum:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Email Address and Display Name', 'nobloat-user-foundry' ); ?><br/>
					<strong><?php esc_html_e( 'Security tip:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Monitor 2FA and privacy settings for security awareness.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Notification Timing', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="nbuf_notify_profile_changes_digest"><?php esc_html_e( 'Notification Mode', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<select name="nbuf_notify_profile_changes_digest" id="nbuf_notify_profile_changes_digest">
					<option value="immediate" <?php selected( $notify_digest, 'immediate' ); ?>>
						<?php esc_html_e( 'Immediate - Send email for each change', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="hourly" <?php selected( $notify_digest, 'hourly' ); ?>>
						<?php esc_html_e( 'Hourly Digest - Batch changes from past hour', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="daily" <?php selected( $notify_digest, 'daily' ); ?>>
						<?php esc_html_e( 'Daily Digest - Batch changes from past 24 hours', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<strong><?php esc_html_e( 'Immediate:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Get notified instantly (may receive many emails)', 'nobloat-user-foundry' ); ?><br/>
					<strong><?php esc_html_e( 'Hourly:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Receive one summary email per hour with all changes', 'nobloat-user-foundry' ); ?><br/>
					<strong><?php esc_html_e( 'Daily:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Receive one summary email per day with all changes (recommended for high-traffic sites)', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Testing', 'nobloat-user-foundry' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Test your notification settings by sending a sample notification email.', 'nobloat-user-foundry' ); ?></p>
	<p>
		<button type="button" class="button" id="nbuf-test-notification">
			<span class="dashicons dashicons-email" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Send Test Notification', 'nobloat-user-foundry' ); ?>
		</button>
	</p>
	<div id="nbuf-test-result" style="display: none; margin-top: 15px;"></div>

	<?php submit_button( __( 'Save Notification Settings', 'nobloat-user-foundry' ) ); ?>
</form>

<script>
jQuery(document).ready(function($) {
	/* Test notification */
	$('#nbuf-test-notification').on('click', function() {
		$(this).prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'nobloat-user-foundry' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_test_change_notification',
				nonce: '<?php echo esc_attr( wp_create_nonce( 'nbuf_test_notification' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$('#nbuf-test-result').html(
						'<div class="notice notice-success"><p>' + response.data.message + '</p></div>'
					).show();
				} else {
					$('#nbuf-test-result').html(
						'<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
					).show();
				}
			},
			error: function() {
				$('#nbuf-test-result').html(
					'<div class="notice notice-error"><p><?php echo esc_js( __( 'An error occurred while sending test notification.', 'nobloat-user-foundry' ) ); ?></p></div>'
				).show();
			},
			complete: function() {
				$('#nbuf-test-notification').prop('disabled', false).html(
					'<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> <?php echo esc_js( __( 'Send Test Notification', 'nobloat-user-foundry' ) ); ?>'
				);
			}
		});
	});
});
</script>
