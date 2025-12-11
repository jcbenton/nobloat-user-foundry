<?php
/**
 * Registration Tab
 *
 * Controls registration form fields, username generation,
 * login methods, and address mode settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reg_settings = NBUF_Options::get( 'nbuf_registration_fields', array() );

/* Default values if not set */
$username_method = $reg_settings['username_method'] ?? 'auto_random';
$login_method    = $reg_settings['login_method'] ?? 'email_only';
$address_mode    = $reg_settings['address_mode'] ?? 'simplified';
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="nbuf-registration-form">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="registration">

	<h2><?php esc_html_e( 'Registration Behavior', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Username Generation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_registration_fields[username_method]">
					<option value="auto_random" <?php selected( $username_method, 'auto_random' ); ?>>
						<?php esc_html_e( 'Auto Random - Generate random username (Best for privacy)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="auto_email" <?php selected( $username_method, 'auto_email' ); ?>>
						<?php esc_html_e( 'Auto from Email - Extract from email prefix (john@example.com → john)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="user_entered" <?php selected( $username_method, 'user_entered' ); ?>>
						<?php esc_html_e( 'User Entered - User chooses their own username', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How usernames are assigned during registration.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Login Method', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_registration_fields[login_method]">
					<option value="email_only" <?php selected( $login_method, 'email_only' ); ?>>
						<?php esc_html_e( 'Email Only - Users login with email address', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="username_only" <?php selected( $login_method, 'username_only' ); ?>>
						<?php esc_html_e( 'Username Only - Users login with username', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="email_or_username" <?php selected( $login_method, 'email_or_username' ); ?>>
						<?php esc_html_e( 'Email or Username - Users can use either', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How users will authenticate when logging in.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Address Mode', 'nobloat-user-foundry' ); ?></th>
			<td>
				<select name="nbuf_registration_fields[address_mode]">
					<option value="simplified" <?php selected( $address_mode, 'simplified' ); ?>>
						<?php esc_html_e( 'Simplified - Single address field', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="full" <?php selected( $address_mode, 'full' ); ?>>
						<?php esc_html_e( 'Full International - Separate fields (line1, line2, city, state, zip, country)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Address field structure for registration and profiles.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Registration Form Fields', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure which fields appear on the registration form, whether they are required, and customize their labels.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php
	/* Get field registry from profile data system */
	$field_registry = NBUF_Profile_Data::get_field_registry();

	/* Add first/last name as a special category */
	$all_categories = array_merge(
		array(
			'core' => array(
				'label'  => __( 'Core Fields', 'nobloat-user-foundry' ),
				'fields' => array(
					'first_name' => __( 'First Name', 'nobloat-user-foundry' ),
					'last_name'  => __( 'Last Name', 'nobloat-user-foundry' ),
				),
			),
		),
		$field_registry
	);

	foreach ( $all_categories as $category_key => $category_data ) :
		?>
		<h3><?php echo esc_html( $category_data['label'] ); ?></h3>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
			<thead>
				<tr>
					<th style="width: 50px; text-align: center;">
						<input type="checkbox" class="nbuf-select-all-reg" data-category="<?php echo esc_attr( $category_key ); ?>">
					</th>
					<th style="width: 20%;"><?php esc_html_e( 'Field', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Required', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 65%;"><?php esc_html_e( 'Custom Label (optional)', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
		<?php
		foreach ( $category_data['fields'] as $field_key => $field_label ) :
			$enabled  = ! empty( $reg_settings[ $field_key . '_enabled' ] ) && '0' !== $reg_settings[ $field_key . '_enabled' ];
			$required = ! empty( $reg_settings[ $field_key . '_required' ] ) && '0' !== $reg_settings[ $field_key . '_required' ];
			$label    = $reg_settings[ $field_key . '_label' ] ?? '';
			?>
					<tr>
						<td style="text-align: center;">
							<input type="hidden" name="nbuf_registration_fields[<?php echo esc_attr( $field_key ); ?>_enabled]" value="0">
							<input type="checkbox"
								name="nbuf_registration_fields[<?php echo esc_attr( $field_key ); ?>_enabled]"
								value="1"
			<?php checked( $enabled, true ); ?>
								class="nbuf-field-enabled"
								data-field="<?php echo esc_attr( $field_key ); ?>"
								data-category="<?php echo esc_attr( $category_key ); ?>">
						</td>
						<td><strong><?php echo esc_html( $field_label ); ?></strong></td>
						<td>
							<input type="hidden" name="nbuf_registration_fields[<?php echo esc_attr( $field_key ); ?>_required]" value="0">
							<input type="checkbox"
								name="nbuf_registration_fields[<?php echo esc_attr( $field_key ); ?>_required]"
								value="1"
			<?php checked( $required, true ); ?>
								class="nbuf-field-required"
								data-field="<?php echo esc_attr( $field_key ); ?>">
						</td>
						<td>
							<input type="text"
								name="nbuf_registration_fields[<?php echo esc_attr( $field_key ); ?>_label]"
								value="<?php echo esc_attr( $label ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( $field_label ); ?>">
						</td>
					</tr>
		<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach; ?>

	<p class="description" style="margin-top: 10px;">
		<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Address-related fields (Address, City, State, Postal Code, Country) will use either simplified mode (single field) or full mode (separate fields) based on the "Address Mode" setting above.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php submit_button( __( 'Save Registration Settings', 'nobloat-user-foundry' ) ); ?>
</form>

<script>
jQuery(document).ready(function($) {
	/* Disable "Required" checkbox if "Enabled" is unchecked */
	$('.nbuf-field-enabled').on('change', function() {
		var field = $(this).data('field');
		var isEnabled = $(this).is(':checked');
		var requiredCheckbox = $('.nbuf-field-required[data-field="' + field + '"]');

		if (!isEnabled) {
			requiredCheckbox.prop('checked', false).prop('disabled', true);
		} else {
			requiredCheckbox.prop('disabled', false);
		}
	});

	/* Initialize state on page load */
	$('.nbuf-field-enabled').each(function() {
		var field = $(this).data('field');
		var isEnabled = $(this).is(':checked');
		var requiredCheckbox = $('.nbuf-field-required[data-field="' + field + '"]');

		if (!isEnabled) {
			requiredCheckbox.prop('disabled', true);
		}
	});

	/* Select/deselect all checkboxes in a category */
	$('.nbuf-select-all-reg').on('change', function() {
		var category = $(this).data('category');
		var isChecked = $(this).is(':checked');
		var checkboxes = $('.nbuf-field-enabled[data-category="' + category + '"]');

		checkboxes.prop('checked', isChecked);

		/* Trigger change event to update required checkboxes */
		checkboxes.each(function() {
			$(this).trigger('change');
		});
	});

	/* Update "select all" checkbox state when individual checkboxes change */
	$('.nbuf-field-enabled').on('change', function() {
		var category = $(this).data('category');
		var allCheckboxes = $('.nbuf-field-enabled[data-category="' + category + '"]');
		var checkedCount = allCheckboxes.filter(':checked').length;
		var selectAllCheckbox = $('.nbuf-select-all-reg[data-category="' + category + '"]');

		selectAllCheckbox.prop('checked', checkedCount === allCheckboxes.length);
	});

	/* Initialize "select all" checkbox state on page load */
	$('.nbuf-select-all-reg').each(function() {
		var category = $(this).data('category');
		var allCheckboxes = $('.nbuf-field-enabled[data-category="' + category + '"]');
		var checkedCount = allCheckboxes.filter(':checked').length;

		$(this).prop('checked', checkedCount === allCheckboxes.length);
	});
});
</script>
