<?php
/**
 * GDPR > Notifications Tab
 *
 * Configure notifications for profile changes and new registrations.
 * Supports GDPR transparency requirements by notifying users of data changes.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/GDPR
 * @since      1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Security: Verify user has permission to access this page */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
}

/* Get current settings */
$nbuf_notify_enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );
$nbuf_notify_to      = NBUF_Options::get( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ) );
$nbuf_notify_fields    = NBUF_Options::get( 'nbuf_notify_profile_changes_fields', array( 'user_email', 'display_name' ) );
$nbuf_notify_digest    = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

/* Ensure $nbuf_notify_to is a comma-separated string */
if ( is_array( $nbuf_notify_to ) ) {
	$nbuf_notify_to = implode( ', ', $nbuf_notify_to );
} elseif ( ! is_string( $nbuf_notify_to ) ) {
	$nbuf_notify_to = get_option( 'admin_email' );
}

/* Ensure $nbuf_notify_fields is array */
if ( ! is_array( $nbuf_notify_fields ) ) {
	$nbuf_notify_fields = array( 'user_email', 'display_name' );
}

/* Available fields to monitor */
$nbuf_available_fields = array(
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
	<input type="hidden" name="nbuf_active_tab" value="gdpr">
	<input type="hidden" name="nbuf_active_subtab" value="notifications">
	<!-- Declare checkboxes on this form for proper unchecked handling -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_notify_profile_changes">

	<h2><?php esc_html_e( 'Enable Notifications', 'nobloat-user-foundry' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Get notified when users make changes to their profiles.', 'nobloat-user-foundry' ); ?></p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="nbuf_notify_profile_changes"><?php esc_html_e( 'Profile Change Notifications', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="hidden" name="nbuf_notify_profile_changes" value="0">
				<label>
					<input type="checkbox"
							name="nbuf_notify_profile_changes"
							id="nbuf_notify_profile_changes"
							value="1"
							<?php checked( $nbuf_notify_enabled ); ?> />
					<?php esc_html_e( 'Enable profile change notifications', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When enabled, admins will be notified of profile changes.', 'nobloat-user-foundry' ); ?></p>
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
						value="<?php echo esc_attr( $nbuf_notify_to ); ?>"
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
				<?php foreach ( $nbuf_available_fields as $nbuf_group_name => $nbuf_fields ) : ?>
					<fieldset style="margin-bottom: 20px;">
						<legend style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html( $nbuf_group_name ); ?></legend>
					<?php foreach ( $nbuf_fields as $nbuf_field_key => $nbuf_field_label ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox"
										name="nbuf_notify_profile_changes_fields[]"
										value="<?php echo esc_attr( $nbuf_field_key ); ?>"
						<?php checked( in_array( $nbuf_field_key, $nbuf_notify_fields, true ) ); ?> />
						<?php echo esc_html( $nbuf_field_label ); ?>
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
					<option value="immediate" <?php selected( $nbuf_notify_digest, 'immediate' ); ?>>
						<?php esc_html_e( 'Immediate - Send email for each change', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="hourly" <?php selected( $nbuf_notify_digest, 'hourly' ); ?>>
						<?php esc_html_e( 'Hourly Digest - Batch changes from past hour', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="daily" <?php selected( $nbuf_notify_digest, 'daily' ); ?>>
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

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
