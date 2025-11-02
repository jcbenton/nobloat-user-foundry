<?php
/**
 * Users Tab - Profile Change Notifications
 *
 * @package NoBloat_User_Foundry
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$notify_enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );
$notify_new_users = NBUF_Options::get( 'nbuf_notify_new_registrations', false );
$notify_to = NBUF_Options::get( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ) );
$notify_fields = NBUF_Options::get( 'nbuf_notify_profile_changes_fields', array( 'user_email', 'display_name' ) );
$notify_digest = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

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
	'Profile Fields' => array(
		'bio'         => 'Bio',
		'phone'       => 'Phone',
		'city'        => 'City',
		'state'       => 'State',
		'country'     => 'Country',
		'company'     => 'Company',
		'job_title'   => 'Job Title',
	),
	'Security & Privacy' => array(
		'2fa_status'      => '2FA Enabled/Disabled',
		'profile_privacy' => 'Profile Privacy Setting',
	),
);
?>

<div class="nbuf-settings-section">
	<h2>Profile Change Notifications</h2>
	<p class="description">Get notified when users make changes to their profiles.</p>

	<!-- Master Toggle -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Enable Notifications</h3>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf_notify_profile_changes">Profile Change Notifications</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
							   name="nbuf_notify_profile_changes"
							   id="nbuf_notify_profile_changes"
							   value="1"
							   <?php checked( $notify_enabled ); ?> />
						Enable profile change notifications
					</label>
					<p class="description">When enabled, admins will be notified of profile changes.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_notify_new_registrations">New User Notifications</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
							   name="nbuf_notify_new_registrations"
							   id="nbuf_notify_new_registrations"
							   value="1"
							   <?php checked( $notify_new_users ); ?> />
						Notify when new users register
					</label>
					<p class="description">Get notified immediately when a new user registers.</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Notification Recipients -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Notification Recipients</h3>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf_notify_profile_changes_to">Send Notifications To</label>
				</th>
				<td>
					<input type="text"
						   name="nbuf_notify_profile_changes_to"
						   id="nbuf_notify_profile_changes_to"
						   value="<?php echo esc_attr( $notify_to ); ?>"
						   class="regular-text" />
					<p class="description">
						Email addresses to receive notifications. Separate multiple addresses with commas.<br/>
						Default: Site admin email (<?php echo esc_html( get_option( 'admin_email' ) ); ?>)
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Monitored Fields -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Monitored Fields</h3>
		<p class="description">Select which profile fields to monitor for changes.</p>

		<table class="form-table">
			<tr>
				<th scope="row">Fields to Monitor</th>
				<td>
					<?php foreach ( $available_fields as $group_name => $fields ) : ?>
						<fieldset style="margin-bottom: 20px;">
							<legend style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html( $group_name ); ?></legend>
							<?php foreach ( $fields as $field_key => $field_label ) : ?>
								<label style="display: block; margin-bottom: 5px;">
									<input type="checkbox"
										   name="nbuf_notify_profile_changes_fields[]"
										   value="<?php echo esc_attr( $field_key ); ?>"
										   <?php checked( in_array( $field_key, $notify_fields ) ); ?> />
									<?php echo esc_html( $field_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; ?>

					<p class="description">
						<strong>Recommended minimum:</strong> Email Address and Display Name<br/>
						<strong>Security tip:</strong> Monitor 2FA and privacy settings for security awareness.
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Notification Timing -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Notification Timing</h3>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf_notify_profile_changes_digest">Notification Mode</label>
				</th>
				<td>
					<select name="nbuf_notify_profile_changes_digest" id="nbuf_notify_profile_changes_digest">
						<option value="immediate" <?php selected( $notify_digest, 'immediate' ); ?>>
							Immediate - Send email for each change
						</option>
						<option value="hourly" <?php selected( $notify_digest, 'hourly' ); ?>>
							Hourly Digest - Batch changes from past hour
						</option>
						<option value="daily" <?php selected( $notify_digest, 'daily' ); ?>>
							Daily Digest - Batch changes from past 24 hours
						</option>
					</select>
					<p class="description">
						<strong>Immediate:</strong> Get notified instantly (may receive many emails)<br/>
						<strong>Hourly:</strong> Receive one summary email per hour with all changes<br/>
						<strong>Daily:</strong> Receive one summary email per day with all changes (recommended for high-traffic sites)
					</p>
				</td>
			</tr>
		</table>

		<?php if ( $notify_digest === 'hourly' || $notify_digest === 'daily' ) : ?>
			<div style="margin-top: 15px; padding: 15px; background: #d1ecf1; border-left: 4px solid #0c5460;">
				<p style="margin: 0;">
					<strong>Note:</strong> Digest emails are sent via WordPress cron.
					<?php if ( $notify_digest === 'hourly' ) : ?>
						Hourly digests run every hour on the hour.
					<?php else : ?>
						Daily digests run once per day at midnight (server time).
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Testing & Preview -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Testing</h3>

		<p>Test your notification settings by sending a sample notification email.</p>

		<p>
			<button type="button" class="button" id="nbuf-test-notification">
				<span class="dashicons dashicons-email" style="vertical-align: middle;"></span>
				Send Test Notification
			</button>
		</p>

		<div id="nbuf-test-result" style="display: none; margin-top: 15px;"></div>
	</div>

	<!-- Email Template Preview -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Email Format</h3>

		<details>
			<summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f6f7f7; border-radius: 4px;">
				Click to preview notification email format
			</summary>

			<div style="padding: 15px; background: #f9f9f9; margin-top: 10px; border-radius: 4px;">
				<h4>Individual Change Notification (Immediate Mode)</h4>
				<pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;">Subject: Profile Changes for John Doe

Profile changes detected for user: John Doe (johndoe)

User ID: 123
Email: john@example.com
Date: 2025-11-01 14:30:00

Changes:
--------------------------------------------------

Email Address:
  Old: old-email@example.com
  New: john@example.com

Display Name:
  Old: J. Doe
  New: John Doe

City:
  Old: (empty)
  New: Boston

--------------------------------------------------

View user profile: <?php echo admin_url( 'user-edit.php?user_id=123' ); ?></pre>

				<h4 style="margin-top: 20px;">Digest Notification (Hourly/Daily Mode)</h4>
				<pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;">Subject: Profile Changes Digest (Daily)

Profile Changes Digest - Daily
Generated: 2025-11-01 23:59:00

======================================================================

User: John Doe (johndoe)
Email: john@example.com
Profile: <?php echo admin_url( 'user-edit.php?user_id=123' ); ?>

----------------------------------------------------------------------

Changed at: 2025-11-01 14:30:00
  Email Address: old-email@example.com → john@example.com
  Display Name: J. Doe → John Doe

Changed at: 2025-11-01 18:45:00
  City: (empty) → Boston


User: Jane Smith (janesmith)
Email: jane@example.com
Profile: <?php echo admin_url( 'user-edit.php?user_id=456' ); ?>

----------------------------------------------------------------------

Changed at: 2025-11-01 10:15:00
  2FA Status: disabled → enabled


======================================================================
Total changes: 4
Total users affected: 2</pre>
			</div>
		</details>
	</div>

	<!-- Use Cases -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Use Cases &amp; Best Practices</h3>

		<details>
			<summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f6f7f7; border-radius: 4px;">
				Click to view use cases and recommendations
			</summary>

			<div style="padding: 15px; background: #f9f9f9; margin-top: 10px; border-radius: 4px;">
				<h4>Common Use Cases</h4>

				<strong>1. Security Monitoring</strong>
				<ul>
					<li>Monitor email address changes (prevent account takeover)</li>
					<li>Track 2FA enabled/disabled events</li>
					<li>Watch for privacy setting changes</li>
					<li>Recommended mode: <strong>Immediate</strong></li>
				</ul>

				<strong>2. Compliance & Audit</strong>
				<ul>
					<li>Track all profile changes for audit trail</li>
					<li>Monitor sensitive data updates (tax ID, government ID)</li>
					<li>Document user-initiated changes</li>
					<li>Recommended mode: <strong>Daily Digest</strong></li>
				</ul>

				<strong>3. Data Quality</strong>
				<ul>
					<li>Monitor required field completeness</li>
					<li>Track contact information updates</li>
					<li>Verify address changes</li>
					<li>Recommended mode: <strong>Hourly or Daily Digest</strong></li>
				</ul>

				<strong>4. User Engagement</strong>
				<ul>
					<li>Track profile completion progress</li>
					<li>Monitor bio/description updates</li>
					<li>Watch for social media links added</li>
					<li>Recommended mode: <strong>Daily Digest</strong></li>
				</ul>

				<h4>Best Practices</h4>
				<ul>
					<li>✅ <strong>Start with critical fields only</strong> - Email, display name, 2FA status</li>
					<li>✅ <strong>Use digests for high-traffic sites</strong> - Avoid email overload</li>
					<li>✅ <strong>Test notifications first</strong> - Ensure emails are received</li>
					<li>✅ <strong>Monitor security-critical fields immediately</strong> - Email, password, 2FA</li>
					<li>✅ <strong>Use daily digests for non-critical fields</strong> - Bio, city, company</li>
					<li>⚠️ <strong>Don't monitor every field</strong> - Focus on what matters</li>
					<li>⚠️ <strong>Review recipients regularly</strong> - Keep list up to date</li>
				</ul>

				<h4>Performance Considerations</h4>
				<ul>
					<li><strong>Immediate mode:</strong> One email per change (low overhead, many emails)</li>
					<li><strong>Hourly digest:</strong> One email per hour (balanced approach)</li>
					<li><strong>Daily digest:</strong> One email per day (minimal overhead, best for high traffic)</li>
				</ul>
			</div>
		</details>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	/* Test notification */
	$('#nbuf-test-notification').on('click', function() {
		$(this).prop('disabled', true).text('Sending...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_test_change_notification',
				nonce: '<?php echo wp_create_nonce( 'nbuf_test_notification' ); ?>'
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
					'<div class="notice notice-error"><p>An error occurred while sending test notification.</p></div>'
				).show();
			},
			complete: function() {
				$('#nbuf-test-notification').prop('disabled', false).html(
					'<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> Send Test Notification'
				);
			}
		});
	});
});
</script>
