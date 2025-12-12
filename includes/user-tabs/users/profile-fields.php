<?php
/**
 * Profile Fields Tab
 *
 * Configure which extended profile fields are enabled and visible
 * in registration forms and account pages, set required status,
 * and customize field labels.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle form submission */
if ( isset( $_POST['nbuf_save_profile_fields'] ) && check_admin_referer( 'nbuf_profile_fields_settings', 'nbuf_profile_fields_nonce' ) ) {
	$registration_fields = isset( $_POST['nbuf_registration_profile_fields'] ) && is_array( $_POST['nbuf_registration_profile_fields'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_registration_profile_fields'] ) )
		: array();

	$required_fields = isset( $_POST['nbuf_required_profile_fields'] ) && is_array( $_POST['nbuf_required_profile_fields'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_required_profile_fields'] ) )
		: array();

	$account_fields = isset( $_POST['nbuf_account_profile_fields'] ) && is_array( $_POST['nbuf_account_profile_fields'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_account_profile_fields'] ) )
		: array();

	$field_labels = isset( $_POST['nbuf_profile_field_labels'] ) && is_array( $_POST['nbuf_profile_field_labels'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_profile_field_labels'] ) )
		: array();

	/* Filter out empty labels */
	$field_labels = array_filter( $field_labels, function ( $label ) {
		return ! empty( trim( $label ) );
	} );

	NBUF_Options::update( 'nbuf_registration_profile_fields', $registration_fields );
	NBUF_Options::update( 'nbuf_required_profile_fields', $required_fields );
	NBUF_Options::update( 'nbuf_account_profile_fields', $account_fields );
	NBUF_Options::update( 'nbuf_profile_field_labels', $field_labels );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profile field settings saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current settings */
$registration_fields = NBUF_Options::get( 'nbuf_registration_profile_fields', array() );
$required_fields     = NBUF_Options::get( 'nbuf_required_profile_fields', array() );
$account_fields      = NBUF_Options::get( 'nbuf_account_profile_fields', array() );
$field_labels        = NBUF_Options::get( 'nbuf_profile_field_labels', array() );
$field_registry      = NBUF_Profile_Data::get_field_registry();

/* For backward compatibility - migrate from old settings if new ones are empty */
$old_enabled     = NBUF_Options::get( 'nbuf_enabled_profile_fields', array() );
$old_reg_fields  = NBUF_Options::get( 'nbuf_registration_fields', array() );

if ( empty( $registration_fields ) && empty( $account_fields ) ) {
	if ( ! empty( $old_enabled ) ) {
		$registration_fields = $old_enabled;
		$account_fields      = $old_enabled;
	}
	/* Migrate labels and required from old registration settings */
	if ( ! empty( $old_reg_fields ) ) {
		foreach ( $old_reg_fields as $key => $value ) {
			if ( strpos( $key, '_label' ) !== false && ! empty( $value ) ) {
				$field_key = str_replace( '_label', '', $key );
				$field_labels[ $field_key ] = $value;
			}
			if ( strpos( $key, '_required' ) !== false && $value ) {
				$field_key = str_replace( '_required', '', $key );
				$required_fields[] = $field_key;
			}
			if ( strpos( $key, '_enabled' ) !== false && $value ) {
				$field_key = str_replace( '_enabled', '', $key );
				if ( ! in_array( $field_key, $registration_fields, true ) ) {
					$registration_fields[] = $field_key;
				}
			}
		}
	}
}
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_profile_fields_settings', 'nbuf_profile_fields_nonce' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="profile-fields">

	<h2><?php esc_html_e( 'Profile Field Configuration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure which fields appear on registration and account pages. Fields marked "Required" must be filled during registration. Custom labels override the default field names.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php foreach ( $field_registry as $category_key => $category_data ) : ?>
		<h3><?php echo esc_html( $category_data['label'] ); ?></h3>
		<table style="margin-bottom: 30px; border-collapse: collapse; width: 100%;">
			<thead>
				<tr>
					<th style="width: 18%; text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Field', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 10%; text-align: center; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Registration', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 10%; text-align: center; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Required', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 10%; text-align: center; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Account', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 22%; text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Custom Label', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 30%; text-align: left; padding: 8px 10px; border-bottom: 1px solid #c3c4c7;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
		<?php
		foreach ( $category_data['fields'] as $field_key => $field_label ) :
			$is_registration = in_array( $field_key, $registration_fields, true );
			$is_required     = in_array( $field_key, $required_fields, true );
			$is_account      = in_array( $field_key, $account_fields, true );
			$custom_label    = $field_labels[ $field_key ] ?? '';
			$description     = nbuf_get_field_description( $field_key );
			?>
				<tr>
					<td style="padding: 8px 10px;"><strong><?php echo esc_html( $field_label ); ?></strong></td>
					<td style="text-align: center; padding: 8px 10px;">
						<input type="checkbox"
							name="nbuf_registration_profile_fields[]"
							value="<?php echo esc_attr( $field_key ); ?>"
							<?php checked( $is_registration, true ); ?>
							class="nbuf-reg-toggle"
							data-category="<?php echo esc_attr( $category_key ); ?>"
							data-field="<?php echo esc_attr( $field_key ); ?>">
					</td>
					<td style="text-align: center; padding: 8px 10px;">
						<input type="checkbox"
							name="nbuf_required_profile_fields[]"
							value="<?php echo esc_attr( $field_key ); ?>"
							<?php checked( $is_required, true ); ?>
							<?php disabled( ! $is_registration, true ); ?>
							class="nbuf-required-toggle"
							data-field="<?php echo esc_attr( $field_key ); ?>">
					</td>
					<td style="text-align: center; padding: 8px 10px;">
						<input type="checkbox"
							name="nbuf_account_profile_fields[]"
							value="<?php echo esc_attr( $field_key ); ?>"
							<?php checked( $is_account, true ); ?>
							class="nbuf-account-toggle"
							data-category="<?php echo esc_attr( $category_key ); ?>">
					</td>
					<td style="padding: 8px 10px;">
						<input type="text"
							name="nbuf_profile_field_labels[<?php echo esc_attr( $field_key ); ?>]"
							value="<?php echo esc_attr( $custom_label ); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr( $field_label ); ?>"
							style="width: 100%;">
					</td>
					<td style="padding: 8px 10px;"><span class="description"><?php echo esc_html( $description ); ?></span></td>
				</tr>
		<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<p class="description">
		<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'First Name and Last Name are always available on both forms. The "Required" column only applies to registration. Data is never deleted when fields are disabled.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Profile Fields', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_profile_fields' ); ?>
</form>

<script>
jQuery(document).ready(function($) {
	/* Enable/disable Required checkbox based on Registration checkbox */
	$('.nbuf-reg-toggle').on('change', function() {
		var field = $(this).data('field');
		var isChecked = $(this).is(':checked');
		var requiredCheckbox = $('.nbuf-required-toggle[data-field="' + field + '"]');

		if (!isChecked) {
			requiredCheckbox.prop('checked', false).prop('disabled', true);
		} else {
			requiredCheckbox.prop('disabled', false);
		}
	});

	/* Initialize Required checkbox disabled state */
	$('.nbuf-reg-toggle').each(function() {
		var field = $(this).data('field');
		var isChecked = $(this).is(':checked');
		var requiredCheckbox = $('.nbuf-required-toggle[data-field="' + field + '"]');
		if (!isChecked) {
			requiredCheckbox.prop('disabled', true);
		}
	});
});
</script>

<?php
/**
 * Get field description for UI display
 *
 * @param  string $field_key Field key.
 * @return string Field description.
 */
function nbuf_get_field_description( $field_key ) {
	$descriptions = array(
		'phone'                    => __( 'Primary phone number', 'nobloat-user-foundry' ),
		'mobile_phone'             => __( 'Mobile/cell phone number', 'nobloat-user-foundry' ),
		'work_phone'               => __( 'Work/office phone number', 'nobloat-user-foundry' ),
		'fax'                      => __( 'Fax number', 'nobloat-user-foundry' ),
		'preferred_name'           => __( 'Name the user prefers to be called', 'nobloat-user-foundry' ),
		'pronouns'                 => __( 'Preferred pronouns (e.g., he/him)', 'nobloat-user-foundry' ),
		'gender'                   => __( 'Gender', 'nobloat-user-foundry' ),
		'date_of_birth'            => __( 'Date of birth (YYYY-MM-DD)', 'nobloat-user-foundry' ),
		'timezone'                 => __( 'User timezone', 'nobloat-user-foundry' ),
		'secondary_email'          => __( 'Secondary email address', 'nobloat-user-foundry' ),
		'address'                  => __( 'Full address (single field)', 'nobloat-user-foundry' ),
		'address_line1'            => __( 'Street address', 'nobloat-user-foundry' ),
		'address_line2'            => __( 'Apt, suite, etc.', 'nobloat-user-foundry' ),
		'city'                     => __( 'City', 'nobloat-user-foundry' ),
		'state'                    => __( 'State/province/region', 'nobloat-user-foundry' ),
		'postal_code'              => __( 'ZIP or postal code', 'nobloat-user-foundry' ),
		'country'                  => __( 'Country', 'nobloat-user-foundry' ),
		'company'                  => __( 'Company or organization', 'nobloat-user-foundry' ),
		'job_title'                => __( 'Job title or position', 'nobloat-user-foundry' ),
		'department'               => __( 'Department', 'nobloat-user-foundry' ),
		'division'                 => __( 'Division or business unit', 'nobloat-user-foundry' ),
		'employee_id'              => __( 'Employee ID number', 'nobloat-user-foundry' ),
		'badge_number'             => __( 'Badge or ID card number', 'nobloat-user-foundry' ),
		'manager_name'             => __( 'Manager or supervisor name', 'nobloat-user-foundry' ),
		'supervisor_email'         => __( 'Supervisor email address', 'nobloat-user-foundry' ),
		'office_location'          => __( 'Office location or building', 'nobloat-user-foundry' ),
		'hire_date'                => __( 'Employment start date', 'nobloat-user-foundry' ),
		'termination_date'         => __( 'Employment end date', 'nobloat-user-foundry' ),
		'work_email'               => __( 'Work email (separate from login)', 'nobloat-user-foundry' ),
		'employment_type'          => __( 'Full-time, part-time, etc.', 'nobloat-user-foundry' ),
		'license_number'           => __( 'Professional license number', 'nobloat-user-foundry' ),
		'professional_memberships' => __( 'Professional associations', 'nobloat-user-foundry' ),
		'security_clearance'       => __( 'Security clearance level', 'nobloat-user-foundry' ),
		'shift'                    => __( 'Work shift (day, night, swing)', 'nobloat-user-foundry' ),
		'remote_status'            => __( 'Remote, hybrid, or on-site', 'nobloat-user-foundry' ),
		'student_id'               => __( 'Student ID number', 'nobloat-user-foundry' ),
		'school_name'              => __( 'School or university name', 'nobloat-user-foundry' ),
		'degree'                   => __( 'Degree or diploma', 'nobloat-user-foundry' ),
		'major'                    => __( 'Major or field of study', 'nobloat-user-foundry' ),
		'graduation_year'          => __( 'Year graduated', 'nobloat-user-foundry' ),
		'gpa'                      => __( 'Grade point average', 'nobloat-user-foundry' ),
		'certifications'           => __( 'Professional certifications', 'nobloat-user-foundry' ),
		'twitter'                  => __( 'Twitter/X handle or URL', 'nobloat-user-foundry' ),
		'facebook'                 => __( 'Facebook profile URL', 'nobloat-user-foundry' ),
		'linkedin'                 => __( 'LinkedIn profile URL', 'nobloat-user-foundry' ),
		'instagram'                => __( 'Instagram handle or URL', 'nobloat-user-foundry' ),
		'github'                   => __( 'GitHub username or URL', 'nobloat-user-foundry' ),
		'youtube'                  => __( 'YouTube channel URL', 'nobloat-user-foundry' ),
		'tiktok'                   => __( 'TikTok handle or URL', 'nobloat-user-foundry' ),
		'discord_username'         => __( 'Discord username', 'nobloat-user-foundry' ),
		'whatsapp'                 => __( 'WhatsApp number', 'nobloat-user-foundry' ),
		'telegram'                 => __( 'Telegram handle', 'nobloat-user-foundry' ),
		'viber'                    => __( 'Viber number', 'nobloat-user-foundry' ),
		'twitch'                   => __( 'Twitch channel', 'nobloat-user-foundry' ),
		'reddit'                   => __( 'Reddit username', 'nobloat-user-foundry' ),
		'snapchat'                 => __( 'Snapchat handle', 'nobloat-user-foundry' ),
		'soundcloud'               => __( 'SoundCloud profile', 'nobloat-user-foundry' ),
		'vimeo'                    => __( 'Vimeo channel', 'nobloat-user-foundry' ),
		'spotify'                  => __( 'Spotify profile', 'nobloat-user-foundry' ),
		'pinterest'                => __( 'Pinterest profile', 'nobloat-user-foundry' ),
		'website'                  => __( 'Personal or professional website', 'nobloat-user-foundry' ),
		'nationality'              => __( 'Nationality or citizenship', 'nobloat-user-foundry' ),
		'languages'                => __( 'Languages spoken', 'nobloat-user-foundry' ),
		'emergency_contact'        => __( 'Emergency contact info', 'nobloat-user-foundry' ),
	);

	return isset( $descriptions[ $field_key ] ) ? $descriptions[ $field_key ] : '';
}
