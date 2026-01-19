<?php
/**
 * NoBloat User Foundry - Admin Profile Fields
 *
 * Displays User Foundry profile fields on the WordPress user edit page.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Profile Fields Class
 *
 * Renders and saves custom profile fields on user edit pages.
 */
class NBUF_Admin_Profile_Fields {

	/**
	 * Initialize admin profile fields functionality
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		/* Add profile fields section to user profile */
		add_action( 'show_user_profile', array( __CLASS__, 'render_fields_section' ), 20 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_fields_section' ), 20 );

		/* Save profile fields on profile update */
		add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );

		/* Enqueue styles */
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets for user profile pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.nbuf-profile-fields-section .form-table th {
				width: 200px;
				padding-left: 0;
			}
			.nbuf-profile-fields-section .form-table td input[type="text"],
			.nbuf-profile-fields-section .form-table td input[type="email"],
			.nbuf-profile-fields-section .form-table td input[type="url"],
			.nbuf-profile-fields-section .form-table td input[type="date"],
			.nbuf-profile-fields-section .form-table td select,
			.nbuf-profile-fields-section .form-table td textarea {
				width: 100%;
				max-width: 400px;
			}
			.nbuf-profile-fields-section .form-table td textarea {
				min-height: 100px;
			}
			.nbuf-profile-fields-category {
				margin-top: 25px;
				padding-top: 15px;
				border-top: 1px solid #dcdcde;
			}
			.nbuf-profile-fields-category:first-child {
				margin-top: 0;
				padding-top: 0;
				border-top: none;
			}
			.nbuf-profile-fields-category h4 {
				margin: 0 0 15px 0;
				color: #1d2327;
				font-size: 14px;
			}
			.nbuf-no-fields-notice {
				padding: 15px;
				background: #f0f6fc;
				border-left: 4px solid #2271b1;
				color: #1d2327;
			}
			/* Searchable Timezone Select */
			.nbuf-admin-searchable-select {
				display: flex;
				align-items: stretch;
				max-width: 400px;
				border: 1px solid #8c8f94;
				border-radius: 4px;
				overflow: hidden;
				background: #fff;
			}
			.nbuf-admin-tz-search {
				flex: 0 0 120px;
				border: none !important;
				border-right: 1px solid #8c8f94 !important;
				border-radius: 0 !important;
				box-shadow: none !important;
				padding: 0 8px 0 28px !important;
				margin: 0 !important;
				min-height: 30px;
				font-size: 13px;
				background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'14\' height=\'14\' fill=\'%2350575e\' viewBox=\'0 0 16 16\'%3E%3Cpath d=\'M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z\'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: 8px center;
			}
			.nbuf-admin-tz-search:focus {
				outline: none;
				box-shadow: none !important;
			}
			.nbuf-admin-tz-select {
				flex: 1;
				border: none !important;
				border-radius: 0 !important;
				box-shadow: none !important;
				margin: 0 !important;
				min-width: 0;
				max-width: none !important;
				width: auto !important;
			}
			.nbuf-admin-tz-select:focus {
				outline: none;
				box-shadow: none !important;
			}
			.nbuf-admin-searchable-select:focus-within {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
			}
			'
		);

		/* Add JavaScript for timezone search filtering */
		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				$('.nbuf-admin-searchable-select').each(function() {
					var wrap = $(this);
					var search = wrap.find('.nbuf-admin-tz-search');
					var select = wrap.find('.nbuf-admin-tz-select');

					if (search.data('initialized')) return;
					search.data('initialized', true);

					/* Store original options */
					var originalData = [];
					select.find('optgroup').each(function() {
						var group = {
							label: $(this).attr('label'),
							options: []
						};
						$(this).find('option').each(function() {
							group.options.push({
								value: $(this).val(),
								text: $(this).text(),
								selected: $(this).is(':selected')
							});
						});
						originalData.push(group);
					});

					search.on('input', function() {
						var query = $(this).val().toLowerCase().trim();
						var currentValue = select.val();

						/* Remove optgroups but keep placeholder */
						select.find('optgroup').remove();

						/* Rebuild with filtered options */
						originalData.forEach(function(group) {
							var matchingOpts = group.options.filter(function(opt) {
								return opt.text.toLowerCase().indexOf(query) !== -1 ||
									   opt.value.toLowerCase().indexOf(query) !== -1 ||
									   group.label.toLowerCase().indexOf(query) !== -1;
							});

							if (matchingOpts.length > 0 || query === '') {
								var optgroup = $('<optgroup>').attr('label', group.label);
								var optsToShow = query === '' ? group.options : matchingOpts;

								optsToShow.forEach(function(opt) {
									var option = $('<option>').val(opt.value).text(opt.text);
									if (opt.value === currentValue) {
										option.prop('selected', true);
									}
									optgroup.append(option);
								});

								select.append(optgroup);
							}
						});

						/* Restore selection */
						if (currentValue) {
							select.val(currentValue);
						}
					});
				});
			});
			"
		);
	}

	/**
	 * Render the profile fields section
	 *
	 * @param WP_User $user User object being edited.
	 */
	public static function render_fields_section( $user ) {
		/* Get account fields that are enabled */
		$account_fields = NBUF_Profile_Data::get_account_fields();

		/* If no fields enabled, don't show the section */
		if ( empty( $account_fields ) ) {
			return;
		}

		/* Get field registry and labels */
		$field_registry = NBUF_Profile_Data::get_field_registry();
		$custom_labels  = NBUF_Options::get( 'nbuf_profile_field_labels', array() );

		/* Get user's current profile data */
		$profile_data = NBUF_Profile_Data::get( $user->ID );

		/* Security nonce */
		wp_nonce_field( 'nbuf_admin_profile_fields', 'nbuf_admin_profile_nonce' );

		?>
		<h2><?php esc_html_e( 'Extended Profile', 'nobloat-user-foundry' ); ?></h2>
		<div class="nbuf-profile-fields-section">
			<?php
			/* Group enabled fields by category */
			$fields_by_category = array();

			foreach ( $field_registry as $category_key => $category_data ) {
				$category_fields = array();

				foreach ( $category_data['fields'] as $field_key => $field_label ) {
					if ( in_array( $field_key, $account_fields, true ) ) {
						$category_fields[ $field_key ] = array(
							'label'         => isset( $custom_labels[ $field_key ] ) && ! empty( $custom_labels[ $field_key ] )
								? $custom_labels[ $field_key ]
								: $field_label,
							'default_label' => $field_label,
						);
					}
				}

				if ( ! empty( $category_fields ) ) {
					$fields_by_category[ $category_key ] = array(
						'label'  => $category_data['label'],
						'fields' => $category_fields,
					);
				}
			}

			/* Render fields by category */
			foreach ( $fields_by_category as $category_key => $category ) :
				?>
				<div class="nbuf-profile-fields-category">
					<h4><?php echo esc_html( $category['label'] ); ?></h4>
					<table class="form-table" role="presentation">
						<?php foreach ( $category['fields'] as $field_key => $field_info ) : ?>
							<tr>
								<th>
									<label for="nbuf_field_<?php echo esc_attr( $field_key ); ?>">
										<?php echo esc_html( $field_info['label'] ); ?>
									</label>
								</th>
								<td>
									<?php
									$value = isset( $profile_data->$field_key ) ? $profile_data->$field_key : '';
									self::render_field_input( $field_key, $value );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the appropriate input for a field
	 *
	 * @param string $field_key Field key.
	 * @param mixed  $value     Current value.
	 */
	private static function render_field_input( $field_key, $value ) {
		$field_id   = 'nbuf_field_' . $field_key;
		$field_name = 'nbuf_profile_fields[' . $field_key . ']';

		/* Determine field type based on field key */
		$textarea_fields = array( 'professional_memberships', 'certifications', 'emergency_contact', 'address' );
		$email_fields    = array( 'secondary_email', 'work_email', 'supervisor_email' );
		$url_fields      = array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok', 'soundcloud', 'vimeo', 'spotify', 'pinterest', 'twitch', 'reddit' );
		$date_fields     = array( 'date_of_birth', 'hire_date', 'termination_date' );
		$select_fields   = array(
			'gender'          => array( '', 'Male', 'Female', 'Non-binary', 'Prefer not to say', 'Other' ),
			'employment_type' => array( '', 'Full-time', 'Part-time', 'Contract', 'Temporary', 'Intern', 'Freelance' ),
			'remote_status'   => array( '', 'On-site', 'Remote', 'Hybrid' ),
			'shift'           => array( '', 'Day', 'Night', 'Swing', 'Rotating', 'Flexible' ),
		);

		if ( in_array( $field_key, $textarea_fields, true ) ) {
			?>
			<textarea
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				rows="4"
				class="regular-text"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		} elseif ( in_array( $field_key, $email_fields, true ) ) {
			?>
			<input type="email"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text">
			<?php
		} elseif ( in_array( $field_key, $url_fields, true ) ) {
			?>
			<input type="url"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
				placeholder="https://">
			<?php
		} elseif ( in_array( $field_key, $date_fields, true ) ) {
			?>
			<input type="date"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text">
			<?php
		} elseif ( isset( $select_fields[ $field_key ] ) ) {
			?>
			<select
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>">
				<?php foreach ( $select_fields[ $field_key ] as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>>
						<?php echo esc_html( $option ? $option : '— Select —' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		} elseif ( 'timezone' === $field_key ) {
			/* Special handling for searchable timezone dropdown */
			self::render_timezone_select( $field_id, $field_name, $value );
		} elseif ( 'country' === $field_key ) {
			/* Country dropdown */
			$countries = self::get_countries();
			?>
			<select
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>">
				<option value="">— Select Country —</option>
				<?php foreach ( $countries as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $value, $code ); ?>>
						<?php echo esc_html( $name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		} else {
			/* Default text input */
			?>
			<input type="text"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text">
			<?php
		}
	}

	/**
	 * Save profile fields
	 *
	 * @param int $user_id User ID being updated.
	 */
	public static function save_fields( $user_id ) {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_admin_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_admin_profile_nonce'] ) ), 'nbuf_admin_profile_fields' ) ) {
			return;
		}

		/* Check permissions */
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		/* Get submitted fields */
		$fields = isset( $_POST['nbuf_profile_fields'] ) && is_array( $_POST['nbuf_profile_fields'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_profile_fields'] ) )
			: array();

		if ( empty( $fields ) ) {
			return;
		}

		/* Update profile data - NBUF_Profile_Data::update handles sanitization */
		NBUF_Profile_Data::update( $user_id, $fields );
	}

	/**
	 * Render searchable timezone select.
	 *
	 * @param string $field_id   Field ID attribute.
	 * @param string $field_name Field name attribute.
	 * @param string $value      Current selected value.
	 */
	private static function render_timezone_select( $field_id, $field_name, $value ) {
		/* Get all timezone identifiers grouped by region */
		$timezones         = DateTimeZone::listIdentifiers();
		$grouped_timezones = array();

		foreach ( $timezones as $tz ) {
			$parts  = explode( '/', $tz, 2 );
			$region = $parts[0];
			$city   = isset( $parts[1] ) ? str_replace( '_', ' ', $parts[1] ) : $tz;

			if ( ! isset( $grouped_timezones[ $region ] ) ) {
				$grouped_timezones[ $region ] = array();
			}
			$grouped_timezones[ $region ][ $tz ] = $city;
		}
		?>
		<div class="nbuf-admin-searchable-select">
			<input type="text"
				class="nbuf-admin-tz-search"
				placeholder="<?php esc_attr_e( 'Search...', 'nobloat-user-foundry' ); ?>"
				autocomplete="off">
			<select id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				class="nbuf-admin-tz-select">
				<option value=""><?php esc_html_e( '— Select Timezone —', 'nobloat-user-foundry' ); ?></option>
				<?php foreach ( $grouped_timezones as $region => $cities ) : ?>
					<optgroup label="<?php echo esc_attr( $region ); ?>">
						<?php foreach ( $cities as $tz_value => $tz_label ) : ?>
							<option value="<?php echo esc_attr( $tz_value ); ?>" <?php selected( $value, $tz_value ); ?>>
								<?php echo esc_html( $tz_label ); ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Get list of countries
	 *
	 * @return array Country code => name pairs.
	 */
	private static function get_countries() {
		return array(
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'AU' => 'Australia',
			'DE' => 'Germany',
			'FR' => 'France',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'AT' => 'Austria',
			'CH' => 'Switzerland',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'DK' => 'Denmark',
			'FI' => 'Finland',
			'IE' => 'Ireland',
			'PT' => 'Portugal',
			'PL' => 'Poland',
			'CZ' => 'Czech Republic',
			'GR' => 'Greece',
			'HU' => 'Hungary',
			'RO' => 'Romania',
			'BG' => 'Bulgaria',
			'HR' => 'Croatia',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'LT' => 'Lithuania',
			'LV' => 'Latvia',
			'EE' => 'Estonia',
			'CY' => 'Cyprus',
			'MT' => 'Malta',
			'LU' => 'Luxembourg',
			'JP' => 'Japan',
			'CN' => 'China',
			'KR' => 'South Korea',
			'IN' => 'India',
			'BR' => 'Brazil',
			'MX' => 'Mexico',
			'AR' => 'Argentina',
			'CL' => 'Chile',
			'CO' => 'Colombia',
			'PE' => 'Peru',
			'VE' => 'Venezuela',
			'NZ' => 'New Zealand',
			'SG' => 'Singapore',
			'HK' => 'Hong Kong',
			'TW' => 'Taiwan',
			'TH' => 'Thailand',
			'MY' => 'Malaysia',
			'PH' => 'Philippines',
			'ID' => 'Indonesia',
			'VN' => 'Vietnam',
			'ZA' => 'South Africa',
			'EG' => 'Egypt',
			'NG' => 'Nigeria',
			'KE' => 'Kenya',
			'MA' => 'Morocco',
			'AE' => 'United Arab Emirates',
			'SA' => 'Saudi Arabia',
			'IL' => 'Israel',
			'TR' => 'Turkey',
			'RU' => 'Russia',
			'UA' => 'Ukraine',
		);
	}
}
