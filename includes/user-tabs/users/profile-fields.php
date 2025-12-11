<?php
/**
 * Profile Fields Tab
 *
 * Configure which extended profile fields are enabled and visible
 * in user profiles and admin screens.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle form submission */
if ( isset( $_POST['nbuf_save_profile_fields'] ) && check_admin_referer( 'nbuf_profile_fields_settings', 'nbuf_profile_fields_nonce' ) ) {
	$enabled_fields = isset( $_POST['nbuf_enabled_profile_fields'] ) && is_array( $_POST['nbuf_enabled_profile_fields'] )
	? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_enabled_profile_fields'] ) )
	: array();

	NBUF_Options::update( 'nbuf_enabled_profile_fields', $enabled_fields );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profile field settings saved successfully.', 'nobloat-user-foundry' ) . '</p></div>';
}

/* Get current enabled fields */
$enabled_fields = NBUF_Profile_Data::get_enabled_fields();
$field_registry = NBUF_Profile_Data::get_field_registry();
?>

<form method="post" action="">
	<?php wp_nonce_field( 'nbuf_profile_fields_settings', 'nbuf_profile_fields_nonce' ); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="profile-fields">

	<h2><?php esc_html_e( 'Profile Field Configuration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Enable or disable extended profile fields. Only enabled fields will appear in user profiles, registration forms, and admin edit screens.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php foreach ( $field_registry as $category_key => $category_data ) : ?>
		<h3><?php echo esc_html( $category_data['label'] ); ?></h3>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
			<thead>
				<tr>
					<th style="width: 50px; text-align: center;">
						<input type="checkbox" class="nbuf-select-all" data-category="<?php echo esc_attr( $category_key ); ?>">
					</th>
					<th style="width: 40%;"><?php esc_html_e( 'Field', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 60%;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
		<?php
		foreach ( $category_data['fields'] as $field_key => $field_label ) :
			$is_enabled  = in_array( $field_key, $enabled_fields, true );
			$description = get_field_description( $field_key );
			?>
					<tr>
						<td style="text-align: center;">
							<input type="checkbox"
								name="nbuf_enabled_profile_fields[]"
								value="<?php echo esc_attr( $field_key ); ?>"
			<?php checked( $is_enabled, true ); ?>
								class="nbuf-field-toggle"
								data-category="<?php echo esc_attr( $category_key ); ?>">
						</td>
						<td><strong><?php echo esc_html( $field_label ); ?></strong></td>
						<td><?php echo esc_html( $description ); ?></td>
					</tr>
		<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<p class="description">
		<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Unused fields will remain in the database but will not be displayed in forms. Data is never deleted when fields are disabled.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Profile Fields', 'nobloat-user-foundry' ), 'primary', 'nbuf_save_profile_fields' ); ?>
</form>

<script>
jQuery(document).ready(function($) {
	/* Select/deselect all checkboxes in a category */
	$('.nbuf-select-all').on('change', function() {
		var category = $(this).data('category');
		var isChecked = $(this).is(':checked');
		$('.nbuf-field-toggle[data-category="' + category + '"]').prop('checked', isChecked);
	});

	/* Update "select all" checkbox state when individual checkboxes change */
	$('.nbuf-field-toggle').on('change', function() {
		var category = $(this).data('category');
		var allCheckboxes = $('.nbuf-field-toggle[data-category="' + category + '"]');
		var checkedCount = allCheckboxes.filter(':checked').length;
		var selectAllCheckbox = $('.nbuf-select-all[data-category="' + category + '"]');

		selectAllCheckbox.prop('checked', checkedCount === allCheckboxes.length);
	});

	/* Initialize "select all" checkbox state on page load */
	$('.nbuf-select-all').each(function() {
		var category = $(this).data('category');
		var allCheckboxes = $('.nbuf-field-toggle[data-category="' + category + '"]');
		var checkedCount = allCheckboxes.filter(':checked').length;

		$(this).prop('checked', checkedCount === allCheckboxes.length);
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
function get_field_description( $field_key ) {
	$descriptions = array(
		'phone'                    => __( 'Primary phone number', 'nobloat-user-foundry' ),
		'mobile_phone'             => __( 'Mobile/cell phone number', 'nobloat-user-foundry' ),
		'work_phone'               => __( 'Work/office phone number', 'nobloat-user-foundry' ),
		'fax'                      => __( 'Fax number', 'nobloat-user-foundry' ),
		'preferred_name'           => __( 'Name the user prefers to be called', 'nobloat-user-foundry' ),
		'pronouns'                 => __( 'Preferred pronouns (e.g., he/him, she/her, they/them)', 'nobloat-user-foundry' ),
		'date_of_birth'            => __( 'Date of birth (format: YYYY-MM-DD)', 'nobloat-user-foundry' ),
		'timezone'                 => __( 'User timezone (e.g., America/New_York)', 'nobloat-user-foundry' ),
		'address'                  => __( 'Full address (single field)', 'nobloat-user-foundry' ),
		'address_line1'            => __( 'Address line 1 (street address)', 'nobloat-user-foundry' ),
		'address_line2'            => __( 'Address line 2 (apt, suite, etc.)', 'nobloat-user-foundry' ),
		'city'                     => __( 'City', 'nobloat-user-foundry' ),
		'state'                    => __( 'State, province, or region', 'nobloat-user-foundry' ),
		'postal_code'              => __( 'ZIP or postal code', 'nobloat-user-foundry' ),
		'country'                  => __( 'Country', 'nobloat-user-foundry' ),
		'company'                  => __( 'Company or organization name', 'nobloat-user-foundry' ),
		'job_title'                => __( 'Job title or position', 'nobloat-user-foundry' ),
		'department'               => __( 'Department or division', 'nobloat-user-foundry' ),
		'division'                 => __( 'Division or business unit', 'nobloat-user-foundry' ),
		'employee_id'              => __( 'Employee ID or staff number', 'nobloat-user-foundry' ),
		'badge_number'             => __( 'Badge or ID card number', 'nobloat-user-foundry' ),
		'manager_name'             => __( 'Direct manager or supervisor name', 'nobloat-user-foundry' ),
		'supervisor_email'         => __( 'Supervisor email address', 'nobloat-user-foundry' ),
		'office_location'          => __( 'Office location or building', 'nobloat-user-foundry' ),
		'hire_date'                => __( 'Employment start date', 'nobloat-user-foundry' ),
		'termination_date'         => __( 'Employment end date', 'nobloat-user-foundry' ),
		'work_email'               => __( 'Work email address (separate from login)', 'nobloat-user-foundry' ),
		'employment_type'          => __( 'Full-time, part-time, contractor, etc.', 'nobloat-user-foundry' ),
		'license_number'           => __( 'Professional license number', 'nobloat-user-foundry' ),
		'professional_memberships' => __( 'Professional associations and memberships', 'nobloat-user-foundry' ),
		'security_clearance'       => __( 'Security clearance level', 'nobloat-user-foundry' ),
		'shift'                    => __( 'Work shift (day, night, swing)', 'nobloat-user-foundry' ),
		'remote_status'            => __( 'Remote, hybrid, or on-site', 'nobloat-user-foundry' ),
		'student_id'               => __( 'Student ID number', 'nobloat-user-foundry' ),
		'school_name'              => __( 'School or university name', 'nobloat-user-foundry' ),
		'degree'                   => __( 'Degree or diploma earned', 'nobloat-user-foundry' ),
		'major'                    => __( 'Major or field of study', 'nobloat-user-foundry' ),
		'graduation_year'          => __( 'Year graduated', 'nobloat-user-foundry' ),
		'gpa'                      => __( 'Grade point average', 'nobloat-user-foundry' ),
		'certifications'           => __( 'Professional certifications', 'nobloat-user-foundry' ),
		'twitter'                  => __( 'Twitter/X handle or profile URL', 'nobloat-user-foundry' ),
		'facebook'                 => __( 'Facebook profile URL', 'nobloat-user-foundry' ),
		'linkedin'                 => __( 'LinkedIn profile URL', 'nobloat-user-foundry' ),
		'instagram'                => __( 'Instagram handle or profile URL', 'nobloat-user-foundry' ),
		'github'                   => __( 'GitHub username or profile URL', 'nobloat-user-foundry' ),
		'youtube'                  => __( 'YouTube channel URL', 'nobloat-user-foundry' ),
		'tiktok'                   => __( 'TikTok handle or profile URL', 'nobloat-user-foundry' ),
		'discord_username'         => __( 'Discord username', 'nobloat-user-foundry' ),
		'bio'                      => __( 'User biography or about section', 'nobloat-user-foundry' ),
		'website'                  => __( 'Personal or professional website URL', 'nobloat-user-foundry' ),
		'nationality'              => __( 'Nationality or citizenship', 'nobloat-user-foundry' ),
		'languages'                => __( 'Languages spoken', 'nobloat-user-foundry' ),
		'emergency_contact'        => __( 'Emergency contact information', 'nobloat-user-foundry' ),
	);

	return isset( $descriptions[ $field_key ] ) ? $descriptions[ $field_key ] : '';
}
