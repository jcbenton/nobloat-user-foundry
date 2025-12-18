<?php
/**
 * GDPR Data Export Handler
 *
 * Handles user data export functionality for GDPR compliance (Article 15 - Right of Access).
 * Supports exporting NoBloat profile data, WooCommerce orders, and EDD purchases.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Includes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GDPR Data Export Class
 *
 * @since 1.4.0
 */
class NBUF_GDPR_Export {

	/**
	 * Initialize GDPR export hooks
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		/* Register AJAX handlers */
		add_action( 'wp_ajax_nbuf_request_export', array( __CLASS__, 'ajax_request_export' ) );
		add_action( 'wp_ajax_nbuf_admin_export', array( __CLASS__, 'ajax_admin_export' ) );

		/* Register download handler */
		add_action( 'init', array( __CLASS__, 'handle_download_request' ) );

		/* Register shortcode */
		add_shortcode( 'nbuf_data_export', array( __CLASS__, 'shortcode' ) );

		/* Enqueue scripts and styles */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		/* Add admin user profile download button */
		if ( is_admin() ) {
			add_action( 'show_user_profile', array( __CLASS__, 'add_admin_export_button' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'add_admin_export_button' ) );
		}

		/* Register cleanup cron job */
		if ( ! wp_next_scheduled( 'nbuf_cleanup_exports' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_cleanup_exports' );
		}
		add_action( 'nbuf_cleanup_exports', array( __CLASS__, 'cleanup_old_exports' ) );
	}

	/**
	 * Check if GDPR export is enabled
	 *
	 * @since 1.4.0
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		return (bool) NBUF_Options::get( 'nbuf_gdpr_export_enabled', false );
	}

	/**
	 * Check if user can export data (rate limiting)
	 *
	 * @since 1.4.0
	 * @param int $user_id User ID.
	 * @return array Array with 'can_export' bool and 'wait_minutes' int.
	 */
	public static function check_rate_limit( $user_id ) {
		$last_export        = get_user_meta( $user_id, 'nbuf_last_data_export', true );
		$rate_limit_minutes = absint( NBUF_Options::get( 'nbuf_gdpr_rate_limit_minutes', 15 ) );

		if ( ! $last_export ) {
			return array(
				'can_export'   => true,
				'wait_minutes' => 0,
			);
		}

		$elapsed_seconds = time() - $last_export;
		$required_wait   = $rate_limit_minutes * 60;

		if ( $elapsed_seconds < $required_wait ) {
			$wait_minutes = ceil( ( $required_wait - $elapsed_seconds ) / 60 );
			return array(
				'can_export'   => false,
				'wait_minutes' => $wait_minutes,
			);
		}

		return array(
			'can_export'   => true,
			'wait_minutes' => 0,
		);
	}

	/**
	 * Estimate export file size
	 *
	 * @since 1.4.0
	 * @param int $user_id User ID.
	 * @return int Estimated size in bytes.
	 */
	public static function estimate_export_size( $user_id ) {
		$size = 50000; // Base size for NoBloat data + ZIP overhead (~50 KB).

		/* WooCommerce orders */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) {
			$order_count = count(
				wc_get_orders(
					array(
						'customer_id' => $user_id,
						'limit'       => -1,
						'return'      => 'ids',
					)
				)
			);
			$size       += $order_count * 5000; // ~5 KB per order
		}

		/* Easy Digital Downloads */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_count_purchases_of_customer' ) ) {
			$purchase_count = edd_count_purchases_of_customer( $user_id );
			$size          += $purchase_count * 3000; // ~3 KB per purchase
		}

		return $size;
	}

	/**
	 * Get counts of data sources for display
	 *
	 * @since 1.4.0
	 * @param int $user_id User ID.
	 * @return array Array with counts.
	 */
	public static function get_data_counts( $user_id ) {
		$counts = array(
			'nbuf_fields'    => 0,
			'woo_orders'     => 0,
			'edd_purchases'  => 0,
			'estimated_size' => 0,
		);

		/* Count NoBloat profile fields */
		$user_data = NBUF_User_Data::get( $user_id );
		if ( $user_data ) {
			$profile_fields = self::get_profile_field_names();
			foreach ( $profile_fields as $field ) {
				if ( ! empty( $user_data->$field ) ) {
					++$counts['nbuf_fields'];
				}
			}
		}

		/* Count WooCommerce orders */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) {
			$counts['woo_orders'] = count(
				wc_get_orders(
					array(
						'customer_id' => $user_id,
						'limit'       => -1,
						'return'      => 'ids',
					)
				)
			);
		}

		/* Count EDD purchases */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_count_purchases_of_customer' ) ) {
			$counts['edd_purchases'] = edd_count_purchases_of_customer( $user_id );
		}

		/* Estimate size */
		$counts['estimated_size'] = self::estimate_export_size( $user_id );

		return $counts;
	}

	/**
	 * Generate user data export
	 *
	 * @since 1.4.0
	 * @param int $user_id User ID.
	 * @return string|false Path to ZIP file or false on failure.
	 */
	public static function generate_export( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		/* Create temporary directory for export files */
		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'nbuf-exports';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
			/* Create .htaccess to protect directory */
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary file in protected directory.
				$temp_dir . '/.htaccess',
				"Deny from all\n"
			);
		}

		$timestamp     = gmdate( 'Y-m-d-His' );
		$export_subdir = $temp_dir . '/' . $user->user_login . '-' . $timestamp;
		wp_mkdir_p( $export_subdir );

		/* Generate README.html */
		self::create_readme_file( $export_subdir, $user_id );

		/* Generate NoBloat profile data */
		self::create_nbuf_export( $export_subdir, $user_id );

		/* Generate WooCommerce data if enabled */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) {
			self::create_woocommerce_export( $export_subdir, $user_id );
		}

		/* Generate EDD data if enabled */
		if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_get_users_purchases' ) ) {
			self::create_edd_export( $export_subdir, $user_id );
		}

		/* Create ZIP archive */
		$zip_filename = 'nobloat-user-data-' . $user->user_login . '-' . $timestamp . '.zip';
		$zip_path     = $temp_dir . '/' . $zip_filename;

		if ( ! self::create_zip( $export_subdir, $zip_path ) ) {
			return false;
		}

		/* Clean up temporary directory */
		self::delete_directory( $export_subdir );

		return $zip_path;
	}

	/**
	 * Create README.html file
	 *
	 * @since 1.4.0
	 * @param string $dir    Directory path.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	private static function create_readme_file( $dir, $user_id ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$user      = get_userdata( $user_id );

		$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html__( 'Your Personal Data Export', 'nobloat-user-foundry' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .info-box { background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0; }
        ul { margin-left: 20px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <h1>' . esc_html__( 'Your Personal Data Export', 'nobloat-user-foundry' ) . '</h1>

    <div class="info-box">
        <strong>' . esc_html__( 'Export Date:', 'nobloat-user-foundry' ) . '</strong> ' . gmdate( 'F j, Y g:i a' ) . ' UTC<br>
        <strong>' . esc_html__( 'Username:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( $user->user_login ) . '<br>
        <strong>' . esc_html__( 'Email:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( $user->user_email ) . '
    </div>

    <h2>' . esc_html__( 'About This Export', 'nobloat-user-foundry' ) . '</h2>
    <p>' . esc_html__( 'This archive contains your personal data stored on', 'nobloat-user-foundry' ) . ' <strong>' . esc_html( $site_name ) . '</strong>. ' . esc_html__( 'This export was generated in compliance with GDPR Article 15 (Right of Access).', 'nobloat-user-foundry' ) . '</p>

    <h2>' . esc_html__( 'What\'s Included', 'nobloat-user-foundry' ) . '</h2>
    <ul>
        <li><strong>profile.json</strong> - ' . esc_html__( 'Your profile data in machine-readable format', 'nobloat-user-foundry' ) . '</li>
        <li><strong>profile.html</strong> - ' . esc_html__( 'Your profile data in human-readable format', 'nobloat-user-foundry' ) . '</li>';

		if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) {
			$html .= '
        <li><strong>woocommerce.json</strong> - ' . esc_html__( 'Your order history (machine-readable)', 'nobloat-user-foundry' ) . '</li>
        <li><strong>woocommerce.html</strong> - ' . esc_html__( 'Your order history (human-readable)', 'nobloat-user-foundry' ) . '</li>';
		}

		if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_get_users_purchases' ) ) {
			$html .= '
        <li><strong>edd.json</strong> - ' . esc_html__( 'Your purchase history (machine-readable)', 'nobloat-user-foundry' ) . '</li>
        <li><strong>edd.html</strong> - ' . esc_html__( 'Your purchase history (human-readable)', 'nobloat-user-foundry' ) . '</li>';
		}

		$html .= '
    </ul>

    <h2>' . esc_html__( 'How to Read the Files', 'nobloat-user-foundry' ) . '</h2>
    <ul>
        <li><strong>.html files</strong> - ' . esc_html__( 'Open with any web browser', 'nobloat-user-foundry' ) . '</li>
        <li><strong>.json files</strong> - ' . esc_html__( 'Machine-readable format, can be imported into other systems. Open with text editor or JSON viewer.', 'nobloat-user-foundry' ) . '</li>
    </ul>

    <h2>' . esc_html__( 'Your Data Rights (GDPR)', 'nobloat-user-foundry' ) . '</h2>
    <p>' . esc_html__( 'Under GDPR, you have the following rights:', 'nobloat-user-foundry' ) . '</p>
    <ul>
        <li><strong>' . esc_html__( 'Right of Access', 'nobloat-user-foundry' ) . '</strong> - ' . esc_html__( 'You can request copies of your personal data (this export).', 'nobloat-user-foundry' ) . '</li>
        <li><strong>' . esc_html__( 'Right to Rectification', 'nobloat-user-foundry' ) . '</strong> - ' . esc_html__( 'You can request we correct inaccurate data.', 'nobloat-user-foundry' ) . '</li>
        <li><strong>' . esc_html__( 'Right to Erasure', 'nobloat-user-foundry' ) . '</strong> - ' . esc_html__( 'You can request we delete your data.', 'nobloat-user-foundry' ) . '</li>
        <li><strong>' . esc_html__( 'Right to Data Portability', 'nobloat-user-foundry' ) . '</strong> - ' . esc_html__( 'You can transfer your data to another service.', 'nobloat-user-foundry' ) . '</li>
    </ul>

    <h2>' . esc_html__( 'Request Account Deletion', 'nobloat-user-foundry' ) . '</h2>
    <p>' . esc_html__( 'If you wish to delete your account and all associated data, you can:', 'nobloat-user-foundry' ) . '</p>
    <ul>
        <li>' . sprintf(
		/* translators: %s: site URL */
		esc_html__( 'Use the account deletion feature on %s (if available)', 'nobloat-user-foundry' ), // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent -- Complex sprintf formatting.
		'<a href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a>'
		) . '</li> <?php // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent ?>
        <li>' . sprintf(
		/* translators: %s: admin email */
		esc_html__( 'Contact us at %s to request deletion', 'nobloat-user-foundry' ), // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent -- Complex sprintf formatting.
		'<a href="mailto:' . esc_attr( get_option( 'admin_email' ) ) . '">' . esc_html( get_option( 'admin_email' ) ) . '</a>'
		) . '</li> <?php // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent ?>
    </ul>

    <h2>' . esc_html__( 'Questions?', 'nobloat-user-foundry' ) . '</h2>
    <p>' . sprintf(
		/* translators: %s: admin email */
		esc_html__( 'If you have questions about this export or your data rights, please contact us at %s', 'nobloat-user-foundry' ), // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent -- Complex sprintf formatting.
		'<a href="' . esc_attr( get_option( 'admin_email' ) ) . '">' . esc_html( get_option( 'admin_email' ) ) . '</a>'
		) . '</p> <?php // phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent ?>

    <div class="footer">
        <p>' . esc_html__( 'This export was generated by NoBloat User Foundry in compliance with GDPR.', 'nobloat-user-foundry' ) . '</p>
        <p><a href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a></p>
    </div>
</body>
</html>';

		file_put_contents( $dir . '/README.html', $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
	}

	/**
	 * Create NoBloat profile export files
	 *
	 * @since 1.4.0
	 * @param string $dir    Directory path.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	private static function create_nbuf_export( $dir, $user_id ) {
		$user      = get_userdata( $user_id );
		$user_data = NBUF_User_Data::get( $user_id );

		$export = array(
			'user_id'          => $user_id,
			'username'         => $user->user_login,
			'email'            => $user->user_email,
			'first_name'       => $user->first_name,
			'last_name'        => $user->last_name,
			'display_name'     => $user->display_name,
			'registered_date'  => $user->user_registered,
			'verification'     => array(
				'is_verified'       => NBUF_User_Data::is_verified( $user_id ),
				'verified_date'     => $user_data ? $user_data->verified_date : null,
				'verification_code' => $user_data ? $user_data->verification_code : null,
			),
			'account_settings' => array(
				'expiration_enabled' => $user_data ? (bool) $user_data->expiration_enabled : false,
				'expiration_date'    => $user_data ? $user_data->expiration_date : null,
			),
			'profile_fields'   => array(),
		);

		/* Get all profile fields */
		if ( $user_data ) {
			$profile_fields = self::get_profile_field_names();
			foreach ( $profile_fields as $field ) {
				if ( property_exists( $user_data, $field ) && ! empty( $user_data->$field ) ) {
					$export['profile_fields'][ $field ] = $user_data->$field;
				}
			}
		}

		/* Create JSON file */
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
			$dir . '/profile.json',
			wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		/* Create HTML file */
		self::create_profile_html( $dir, $export );
	}

	/**
	 * Create profile HTML file
	 *
	 * @since 1.4.0
	 * @param string $dir    Directory path.
	 * @param array  $data   Profile data.
	 * @return void
	 */
	private static function create_profile_html( $dir, $data ) {
		$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html__( 'Profile Data', 'nobloat-user-foundry' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #0073aa; color: white; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .verified { color: green; font-weight: bold; }
        .not-verified { color: #d63301; font-weight: bold; }
    </style>
</head>
<body>
    <h1>' . esc_html__( 'Your Profile Data', 'nobloat-user-foundry' ) . '</h1>

    <h2>' . esc_html__( 'Account Information', 'nobloat-user-foundry' ) . '</h2>
    <table>
        <tr>
            <th>' . esc_html__( 'Field', 'nobloat-user-foundry' ) . '</th>
            <th>' . esc_html__( 'Value', 'nobloat-user-foundry' ) . '</th>
        </tr>
        <tr>
            <td>' . esc_html__( 'User ID', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['user_id'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Username', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['username'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Email', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['email'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'First Name', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['first_name'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Last Name', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['last_name'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Display Name', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['display_name'] ) . '</td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Registration Date', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['registered_date'] ) . '</td>
        </tr>
    </table>

    <h2>' . esc_html__( 'Verification Status', 'nobloat-user-foundry' ) . '</h2>
    <table>
        <tr>
            <th>' . esc_html__( 'Field', 'nobloat-user-foundry' ) . '</th>
            <th>' . esc_html__( 'Value', 'nobloat-user-foundry' ) . '</th>
        </tr>
        <tr>
            <td>' . esc_html__( 'Verified', 'nobloat-user-foundry' ) . '</td>
            <td class="' . ( $data['verification']['is_verified'] ? 'verified' : 'not-verified' ) . '">
                ' . ( $data['verification']['is_verified'] ? esc_html__( 'Yes', 'nobloat-user-foundry' ) : esc_html__( 'No', 'nobloat-user-foundry' ) ) . '
            </td>
        </tr>
        <tr>
            <td>' . esc_html__( 'Verified Date', 'nobloat-user-foundry' ) . '</td>
            <td>' . esc_html( $data['verification']['verified_date'] ? $data['verification']['verified_date'] : __( 'N/A', 'nobloat-user-foundry' ) ) . '</td>
        </tr>
    </table>';

		if ( ! empty( $data['profile_fields'] ) ) {
			$html .= '
    <h2>' . esc_html__( 'Custom Profile Fields', 'nobloat-user-foundry' ) . '</h2>
    <table>
        <tr>
            <th>' . esc_html__( 'Field', 'nobloat-user-foundry' ) . '</th>
            <th>' . esc_html__( 'Value', 'nobloat-user-foundry' ) . '</th>
        </tr>';

			foreach ( $data['profile_fields'] as $field => $value ) {
				$field_label = str_replace( '_', ' ', $field );
				$field_label = ucwords( $field_label );
				$html       .= '
        <tr>
            <td>' . esc_html( $field_label ) . '</td>
            <td>' . esc_html( $value ) . '</td>
        </tr>';
			}

			$html .= '
    </table>';
		}

		$html .= '
</body>
</html>';

		file_put_contents( $dir . '/profile.html', $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
	}

	/**
	 * Get profile field names
	 *
	 * @since 1.4.0
	 * @return array Array of field names.
	 */
	private static function get_profile_field_names() {
		return array(
			'profile_field_1',
			'profile_field_2',
			'profile_field_3',
			'profile_field_4',
			'profile_field_5',
			'profile_field_6',
			'profile_field_7',
			'profile_field_8',
			'profile_field_9',
			'profile_field_10',
			'profile_field_11',
			'profile_field_12',
			'profile_field_13',
			'profile_field_14',
			'profile_field_15',
			'profile_field_16',
			'profile_field_17',
			'profile_field_18',
			'profile_field_19',
			'profile_field_20',
			'profile_field_21',
			'profile_field_22',
			'profile_field_23',
			'profile_field_24',
			'profile_field_25',
			'profile_field_26',
			'profile_field_27',
			'profile_field_28',
			'profile_field_29',
			'profile_field_30',
			'profile_field_31',
			'profile_field_32',
			'profile_field_33',
			'profile_field_34',
			'profile_field_35',
			'profile_field_36',
			'profile_field_37',
			'profile_field_38',
			'profile_field_39',
			'profile_field_40',
			'profile_field_41',
			'profile_field_42',
			'profile_field_43',
			'profile_field_44',
			'profile_field_45',
			'profile_field_46',
			'profile_field_47',
			'profile_field_48',
			'profile_field_49',
			'profile_field_50',
			'profile_field_51',
			'profile_field_52',
			'profile_field_53',
		);
	}

	/**
	 * Create WooCommerce export files
	 *
	 * @since 1.4.0
	 * @param string $dir    Directory path.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	private static function create_woocommerce_export( $dir, $user_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
			)
		);

		$export = array(
			'customer_id'    => $user_id,
			'total_orders'   => count( $orders ),
			'orders'         => array(),
		);

		foreach ( $orders as $order ) {
			$order_data = array(
				'order_id'         => $order->get_id(),
				'order_number'     => $order->get_order_number(),
				'date_created'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
				'status'           => $order->get_status(),
				'currency'         => $order->get_currency(),
				'total'            => $order->get_total(),
				'payment_method'   => $order->get_payment_method_title(),
				'billing_address'  => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
				'billing_city'     => $order->get_billing_city(),
				'billing_state'    => $order->get_billing_state(),
				'billing_postcode' => $order->get_billing_postcode(),
				'billing_country'  => $order->get_billing_country(),
				'billing_email'    => $order->get_billing_email(),
				'billing_phone'    => $order->get_billing_phone(),
				'shipping_address' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
				'shipping_city'    => $order->get_shipping_city(),
				'shipping_state'   => $order->get_shipping_state(),
				'shipping_postcode' => $order->get_shipping_postcode(),
				'shipping_country' => $order->get_shipping_country(),
				'items'            => array(),
			);

			/* Get order items */
			foreach ( $order->get_items() as $item ) {
				$order_data['items'][] = array(
					'product_name' => $item->get_name(),
					'quantity'     => $item->get_quantity(),
					'subtotal'     => $item->get_subtotal(),
					'total'        => $item->get_total(),
				);
			}

			$export['orders'][] = $order_data;
		}

		/* Create JSON file */
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
			$dir . '/woocommerce.json',
			wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		/* Create HTML file */
		self::create_woocommerce_html( $dir, $export );
	}

	/**
	 * Create WooCommerce HTML file
	 *
	 * @since 1.4.0
	 * @param string $dir  Directory path.
	 * @param array  $data WooCommerce data.
	 * @return void
	 */
	private static function create_woocommerce_html( $dir, $data ) {
		$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html__( 'WooCommerce Order History', 'nobloat-user-foundry' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; border-bottom: 2px solid #96588a; padding-bottom: 10px; }
        h2 { color: #96588a; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9em; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #96588a; color: white; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .order-section { margin: 40px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .summary { background: #f0f0f0; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>' . esc_html__( 'Your WooCommerce Order History', 'nobloat-user-foundry' ) . '</h1>

    <div class="summary">
        <strong>' . esc_html__( 'Total Orders:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( $data['total_orders'] ) . '
    </div>';

		if ( ! empty( $data['orders'] ) ) {
			foreach ( $data['orders'] as $order ) {
				$html .= '
    <div class="order-section">
        <h2>' . esc_html__( 'Order', 'nobloat-user-foundry' ) . ' #' . esc_html( $order['order_number'] ) . '</h2>

        <table>
            <tr>
                <th>' . esc_html__( 'Order Date', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $order['date_created'] ) . '</td>
                <th>' . esc_html__( 'Status', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $order['status'] ) . '</td>
            </tr>
            <tr>
                <th>' . esc_html__( 'Total', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $order['currency'] ) . ' ' . esc_html( $order['total'] ) . '</td>
                <th>' . esc_html__( 'Payment Method', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $order['payment_method'] ) . '</td>
            </tr>
        </table>

        <h3>' . esc_html__( 'Items Ordered', 'nobloat-user-foundry' ) . '</h3>
        <table>
            <tr>
                <th>' . esc_html__( 'Product', 'nobloat-user-foundry' ) . '</th>
                <th>' . esc_html__( 'Quantity', 'nobloat-user-foundry' ) . '</th>
                <th>' . esc_html__( 'Subtotal', 'nobloat-user-foundry' ) . '</th>
                <th>' . esc_html__( 'Total', 'nobloat-user-foundry' ) . '</th>
            </tr>';

				foreach ( $order['items'] as $item ) {
					$html .= '
            <tr>
                <td>' . esc_html( $item['product_name'] ) . '</td>
                <td>' . esc_html( $item['quantity'] ) . '</td>
                <td>' . esc_html( $item['subtotal'] ) . '</td>
                <td>' . esc_html( $item['total'] ) . '</td>
            </tr>';
				}

				$html .= '
        </table>

        <h3>' . esc_html__( 'Billing Address', 'nobloat-user-foundry' ) . '</h3>
        <p>
            ' . esc_html( $order['billing_address'] ) . '<br>
            ' . esc_html( $order['billing_city'] ) . ', ' . esc_html( $order['billing_state'] ) . ' ' . esc_html( $order['billing_postcode'] ) . '<br>
            ' . esc_html( $order['billing_country'] ) . '<br>
            ' . esc_html__( 'Email:', 'nobloat-user-foundry' ) . ' ' . esc_html( $order['billing_email'] ) . '<br>
            ' . esc_html__( 'Phone:', 'nobloat-user-foundry' ) . ' ' . esc_html( $order['billing_phone'] ) . '
        </p>

        <h3>' . esc_html__( 'Shipping Address', 'nobloat-user-foundry' ) . '</h3>
        <p>
            ' . esc_html( $order['shipping_address'] ) . '<br>
            ' . esc_html( $order['shipping_city'] ) . ', ' . esc_html( $order['shipping_state'] ) . ' ' . esc_html( $order['shipping_postcode'] ) . '<br>
            ' . esc_html( $order['shipping_country'] ) . '
        </p>
    </div>';
			}
		} else {
			$html .= '<p>' . esc_html__( 'No orders found.', 'nobloat-user-foundry' ) . '</p>';
		}

		$html .= '
</body>
</html>';

		file_put_contents( $dir . '/woocommerce.html', $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
	}

	/**
	 * Create EDD export files
	 *
	 * @since 1.4.0
	 * @param string $dir    Directory path.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	private static function create_edd_export( $dir, $user_id ) {
		$purchases = edd_get_users_purchases( $user_id, -1 );
		$customer  = new EDD_Customer( $user_id, true );

		$export = array(
			'customer_id'     => $customer->id,
			'user_id'         => $user_id,
			'email'           => $customer->email,
			'name'            => $customer->name,
			'purchase_count'  => $customer->purchase_count,
			'purchase_value'  => $customer->purchase_value,
			'date_created'    => $customer->date_created,
			'purchases'       => array(),
		);

		if ( $purchases ) {
			foreach ( $purchases as $payment ) {
				$payment_meta = edd_get_payment_meta( $payment->ID );
				$downloads    = edd_get_payment_meta_downloads( $payment->ID );

				$purchase_data = array(
					'payment_id'     => $payment->ID,
					'date'           => $payment->post_date,
					'amount'         => edd_get_payment_amount( $payment->ID ),
					'status'         => $payment->post_status,
					'payment_method' => edd_get_payment_gateway( $payment->ID ),
					'downloads'      => array(),
				);

				if ( $downloads ) {
					foreach ( $downloads as $download ) {
						$purchase_data['downloads'][] = array(
							'download_id'   => $download['id'],
							'download_name' => get_the_title( $download['id'] ),
							'price'         => isset( $download['price'] ) ? $download['price'] : 0,
						);
					}
				}

				$export['purchases'][] = $purchase_data;
			}
		}

		/* Create JSON file */
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
			$dir . '/edd.json',
			wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		/* Create HTML file */
		self::create_edd_html( $dir, $export );
	}

	/**
	 * Create EDD HTML file
	 *
	 * @since 1.4.0
	 * @param string $dir  Directory path.
	 * @param array  $data EDD data.
	 * @return void
	 */
	private static function create_edd_html( $dir, $data ) {
		$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html__( 'Easy Digital Downloads Purchase History', 'nobloat-user-foundry' ) . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; border-bottom: 2px solid #2794da; padding-bottom: 10px; }
        h2 { color: #2794da; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #2794da; color: white; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .purchase-section { margin: 40px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .summary { background: #f0f0f0; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>' . esc_html__( 'Your Easy Digital Downloads Purchase History', 'nobloat-user-foundry' ) . '</h1>

    <div class="summary">
        <strong>' . esc_html__( 'Total Purchases:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( $data['purchase_count'] ) . '<br>
        <strong>' . esc_html__( 'Total Value:', 'nobloat-user-foundry' ) . '</strong> ' . esc_html( edd_currency_filter( edd_format_amount( $data['purchase_value'] ) ) ) . '
    </div>';

		if ( ! empty( $data['purchases'] ) ) {
			foreach ( $data['purchases'] as $purchase ) {
				$html .= '
    <div class="purchase-section">
        <h2>' . esc_html__( 'Payment', 'nobloat-user-foundry' ) . ' #' . esc_html( $purchase['payment_id'] ) . '</h2>

        <table>
            <tr>
                <th>' . esc_html__( 'Purchase Date', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $purchase['date'] ) . '</td>
                <th>' . esc_html__( 'Status', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $purchase['status'] ) . '</td>
            </tr>
            <tr>
                <th>' . esc_html__( 'Amount', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( edd_currency_filter( edd_format_amount( $purchase['amount'] ) ) ) . '</td>
                <th>' . esc_html__( 'Payment Method', 'nobloat-user-foundry' ) . '</th>
                <td>' . esc_html( $purchase['payment_method'] ) . '</td>
            </tr>
        </table>

        <h3>' . esc_html__( 'Downloads Purchased', 'nobloat-user-foundry' ) . '</h3>
        <table>
            <tr>
                <th>' . esc_html__( 'Download', 'nobloat-user-foundry' ) . '</th>
                <th>' . esc_html__( 'Price', 'nobloat-user-foundry' ) . '</th>
            </tr>';

				if ( ! empty( $purchase['downloads'] ) ) {
					foreach ( $purchase['downloads'] as $download ) {
						$html .= '
            <tr>
                <td>' . esc_html( $download['download_name'] ) . '</td>
                <td>' . esc_html( edd_currency_filter( edd_format_amount( $download['price'] ) ) ) . '</td>
            </tr>';
					}
				}

				$html .= '
        </table>
    </div>';
			}
		} else {
			$html .= '<p>' . esc_html__( 'No purchases found.', 'nobloat-user-foundry' ) . '</p>';
		}

		$html .= '
</body>
</html>';

		file_put_contents( $dir . '/edd.html', $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary export file.
	}

	/**
	 * Create ZIP archive from directory
	 *
	 * @since 1.4.0
	 * @param string $source_dir Source directory path.
	 * @param string $zip_file   ZIP file path.
	 * @return bool True on success, false on failure.
	 */
	private static function create_zip( $source_dir, $zip_file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_dir ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = $file->getRealPath();
				$relative_path = substr( $file_path, strlen( $source_dir ) + 1 );
				$zip->addFile( $file_path, $relative_path );
			}
		}

		$zip->close();
		return file_exists( $zip_file );
	}

	/**
	 * Delete directory recursively
	 *
	 * @since 1.4.0
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		foreach ( $files as $fileinfo ) {
			if ( $fileinfo->isDir() ) {
				$wp_filesystem->rmdir( $fileinfo->getRealPath() );
			} else {
				wp_delete_file( $fileinfo->getRealPath() );
			}
		}

		$wp_filesystem->rmdir( $dir );
	}

	/**
	 * Clean up old export files (older than 48 hours)
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function cleanup_old_exports() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'nbuf-exports';

		if ( ! file_exists( $temp_dir ) ) {
			return;
		}

		$files = glob( $temp_dir . '/*.zip' );
		if ( ! $files ) {
			return;
		}

		$max_age = 48 * HOUR_IN_SECONDS;

		foreach ( $files as $file ) {
			if ( is_file( $file ) && ( time() - filemtime( $file ) ) > $max_age ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * AJAX handler: Request data export (with password verification)
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function ajax_request_export() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_export_data', 'nonce' );

		/* Check if logged in */
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in to export data.', 'nobloat-user-foundry' ) );
		}

		$user_id = get_current_user_id();

		/* Check if feature is enabled */
		if ( ! self::is_enabled() ) {
			wp_send_json_error( __( 'Data export is currently disabled.', 'nobloat-user-foundry' ) );
		}

		/* Verify password if required */
		if ( NBUF_Options::get( 'nbuf_gdpr_require_password', true ) ) {
			if ( empty( $_POST['password'] ) ) {
				wp_send_json_error( __( 'Password is required.', 'nobloat-user-foundry' ) );
			}

			$password = sanitize_text_field( wp_unslash( $_POST['password'] ) );
			$user     = wp_get_current_user();

			if ( ! wp_check_password( $password, $user->user_pass ) ) {
				/* Log failed attempt to security log */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log_event(
						'data_export_failed',
						$user_id,
						'incorrect_password'
					);
				}
				wp_send_json_error( __( 'Incorrect password.', 'nobloat-user-foundry' ) );
			}
		}

		/* Check rate limit */
		$rate_check = self::check_rate_limit( $user_id );
		if ( ! $rate_check['can_export'] ) {
			wp_send_json_error(
				sprintf(
					/* translators: %d: minutes to wait */
					__( 'Please wait %d minutes before exporting again.', 'nobloat-user-foundry' ),
					$rate_check['wait_minutes']
				)
			);
		}

		/* Generate export */
		$export_file = self::generate_export( $user_id );

		if ( ! $export_file ) {
			wp_send_json_error( __( 'Failed to generate export. Please try again.', 'nobloat-user-foundry' ) );
		}

		/* Update rate limit timestamp */
		update_user_meta( $user_id, 'nbuf_last_data_export', time() );

		/* Log to admin audit log */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				$user_id,
				'user_data_exported',
				'success',
				sprintf( 'User exported personal data (file size: %s)', size_format( filesize( $export_file ) ) ),
				null,
				array(
					'method'    => NBUF_Options::get( 'nbuf_gdpr_download_method', 'direct' ),
					'file_size' => filesize( $export_file ),
					'includes'  => array(
						'nbuf'        => true,
						'woocommerce' => NBUF_Options::get( 'nbuf_gdpr_include_woo', false ),
						'edd'         => NBUF_Options::get( 'nbuf_gdpr_include_edd', false ),
					),
				)
			);
		}

		/* Check download method */
		$download_method = NBUF_Options::get( 'nbuf_gdpr_download_method', 'direct' );

		if ( 'email' === $download_method ) {
			/* Send email with download link */
			$email_sent = self::send_export_email( $user_id, $export_file );

			if ( $email_sent ) {
				wp_send_json_success(
					array(
						'method'  => 'email',
						'message' => __( 'A download link has been sent to your email address.', 'nobloat-user-foundry' ),
					)
				);
			} else {
				wp_send_json_error( __( 'Failed to send email. Please try again.', 'nobloat-user-foundry' ) );
			}
		} else {
			/* Generate secure download token */
			$token = wp_generate_password( 32, false );
			set_transient(
				'nbuf_export_token_' . $user_id,
				array(
					'token' => $token,
					'file'  => $export_file,
				),
				15 * MINUTE_IN_SECONDS
			);

			/* Return download URL */
			$download_url = add_query_arg(
				array(
					'nbuf_download' => 'export',
					'user_id'       => $user_id,
					'token'         => $token,
				),
				home_url()
			);

			wp_send_json_success(
				array(
					'method'       => 'download',
					'download_url' => $download_url,
					'message'      => __( 'Your data export is ready.', 'nobloat-user-foundry' ),
				)
			);
		}
	}

	/**
	 * Handle export download request
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function handle_download_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Security is handled via one-time use token stored in transient, which provides equivalent protection to nonces while supporting email-based download links.
		if ( ! isset( $_GET['nbuf_download'] ) || 'export' !== $_GET['nbuf_download'] ) {
			return;
		}

		/* Check if logged in */
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to download exports.', 'nobloat-user-foundry' ) );
		}

		$current_user_id = get_current_user_id();
		$request_user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$token           = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/* Verify user ID matches current user (unless admin) */
		if ( $request_user_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'nobloat-user-foundry' ) );
		}

		/* Get token data */
		$token_data = get_transient( 'nbuf_export_token_' . $request_user_id );

		if ( ! $token_data || ! isset( $token_data['token'] ) || ! isset( $token_data['file'] ) ) {
			wp_die( esc_html__( 'Download link expired or invalid.', 'nobloat-user-foundry' ) );
		}

		/* Verify token */
		if ( $token !== $token_data['token'] ) {
			wp_die( esc_html__( 'Invalid download token.', 'nobloat-user-foundry' ) );
		}

		$file_path = $token_data['file'];

		/* Verify file exists */
		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Export file not found.', 'nobloat-user-foundry' ) );
		}

		/* Delete transient (one-time use) */
		delete_transient( 'nbuf_export_token_' . $request_user_id );

		/* Send file */
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming export file download.

		/* Delete file after download */
		wp_delete_file( $file_path );

		exit;
	}

	/**
	 * Send export email with download link
	 *
	 * @since 1.4.0
	 * @param int    $user_id User ID.
	 * @param string $file_path File path.
	 * @return bool True on success, false on failure.
	 */
	public static function send_export_email( $user_id, $file_path ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		/* Generate secure download token valid for 48 hours */
		$token = wp_generate_password( 32, false );
		set_transient(
			'nbuf_export_token_' . $user_id,
			array(
				'token' => $token,
				'file'  => $file_path,
			),
			48 * HOUR_IN_SECONDS
		);

		/* Generate download URL */
		$download_url = add_query_arg(
			array(
				'nbuf_download' => 'export',
				'user_id'       => $user_id,
				'token'         => $token,
			),
			home_url()
		);

		/* Get email template */
		$template = NBUF_Options::get( 'nbuf_gdpr_export_email_template', self::get_default_email_template() );

		/* Replace placeholders */
		$message = str_replace(
			array(
				'{username}',
				'{email}',
				'{site_name}',
				'{download_url}',
				'{expiry_hours}',
			),
			array(
				$user->user_login,
				$user->user_email,
				get_bloginfo( 'name' ),
				$download_url,
				'48',
			),
			$template
		);

		/* Send email */
		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Personal Data Export from %s', 'nobloat-user-foundry' ),
			get_bloginfo( 'name' )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	/**
	 * Get default email template
	 *
	 * @since 1.4.0
	 * @return string Email template HTML.
	 */
	public static function get_default_email_template() {
		return '<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Your Personal Data Export</h2>

        <p>Hello {username},</p>

        <p>Your personal data export from <strong>{site_name}</strong> is ready for download.</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{download_url}" style="background-color: #0073aa; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Download My Data</a>
        </p>

        <p><strong>Important:</strong></p>
        <ul>
            <li>This link will expire in <strong>{expiry_hours} hours</strong></li>
            <li>You must be logged in to download the file</li>
            <li>The link can only be used once</li>
        </ul>

        <p>If you did not request this export, please ignore this email or contact us immediately.</p>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

        <p style="font-size: 0.9em; color: #666;">
            This email was sent in compliance with GDPR Article 15 (Right of Access).<br>
            <strong>{site_name}</strong>
        </p>
    </div>
</body>
</html>';
	}

	/**
	 * AJAX handler: Generate admin export (for admin backend)
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function ajax_admin_export() {
		/* Verify nonce */
		check_ajax_referer( 'nbuf_admin_export_data', 'nonce' );

		/* Check admin capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'nobloat-user-foundry' ) );
		}

		/* Get user ID */
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( __( 'Invalid user ID.', 'nobloat-user-foundry' ) );
		}

		/* Generate export */
		$export_file = self::generate_export( $user_id );

		if ( ! $export_file ) {
			wp_send_json_error( __( 'Failed to generate export.', 'nobloat-user-foundry' ) );
		}

		/* Log admin action */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				get_current_user_id(),
				'admin_exported_user_data',
				'success',
				sprintf( 'Admin exported data for user ID %d', $user_id ),
				$user_id,
				array(
					'file_size' => filesize( $export_file ),
					'includes'  => array(
						'nbuf'        => true,
						'woocommerce' => NBUF_Options::get( 'nbuf_gdpr_include_woo', false ),
						'edd'         => NBUF_Options::get( 'nbuf_gdpr_include_edd', false ),
					),
				)
			);
		}

		/* Generate admin download token (15 minute expiry) */
		$token = wp_generate_password( 32, false );
		set_transient(
			'nbuf_export_token_' . $user_id,
			array(
				'token' => $token,
				'file'  => $export_file,
			),
			15 * MINUTE_IN_SECONDS
		);

		/* Generate download URL */
		$download_url = add_query_arg(
			array(
				'nbuf_download' => 'export',
				'user_id'       => $user_id,
				'token'         => $token,
			),
			home_url()
		);

		wp_send_json_success(
			array(
				'download_url' => $download_url,
				'message'      => __( 'Export generated successfully.', 'nobloat-user-foundry' ),
			)
		);
	}

	/**
	 * Shortcode for data export UI
	 *
	 * Usage: [nbuf_data_export]
	 *
	 * @since 1.4.0
	 * @return string Shortcode output.
	 */
	public static function shortcode() {
		if ( ! self::is_enabled() || ! is_user_logged_in() ) {
			return '';
		}

		$user_id    = get_current_user_id();
		$rate_check = self::check_rate_limit( $user_id );
		$counts     = self::get_data_counts( $user_id );

		$last_export = get_user_meta( $user_id, 'nbuf_last_data_export', true );

		/* Build export includes list */
		$includes_list  = '<li>' . esc_html__( 'Profile information (name, email, custom fields)', 'nobloat-user-foundry' ) . '</li>';
		$includes_list .= '<li>' . esc_html__( 'Account verification status and history', 'nobloat-user-foundry' ) . '</li>';

		if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) {
			/* translators: %d: number of orders */
			$includes_list .= '<li>' . esc_html( sprintf( __( 'WooCommerce orders and addresses (%d orders)', 'nobloat-user-foundry' ), $counts['woo_orders'] ) ) . '</li>';
		}

		if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_get_users_purchases' ) ) {
			/* translators: %d: number of purchases */
			$includes_list .= '<li>' . esc_html( sprintf( __( 'Easy Digital Downloads purchases (%d purchases)', 'nobloat-user-foundry' ), $counts['edd_purchases'] ) ) . '</li>';
		}

		/* Build export button */
		if ( $rate_check['can_export'] ) {
			$export_button = '<button type="button" id="nbuf-request-export" class="button button-primary button-large">' . esc_html__( 'Download My Data', 'nobloat-user-foundry' ) . '</button>';
		} else {
			/* translators: %d: minutes to wait */
			$export_button = '<button type="button" class="button button-large" disabled>' . esc_html( sprintf( __( 'Available in %d minutes', 'nobloat-user-foundry' ), $rate_check['wait_minutes'] ) ) . '</button>';
		}

		/* Build export history */
		if ( $last_export ) {
			$export_history  = '<p>';
			$export_history .= '<strong>' . esc_html__( 'Last exported:', 'nobloat-user-foundry' ) . '</strong> ';
			$export_history .= esc_html( human_time_diff( $last_export, time() ) . ' ' . __( 'ago', 'nobloat-user-foundry' ) );
			$export_history .= '<br>';
			$export_history .= '<strong>' . esc_html__( 'Next available:', 'nobloat-user-foundry' ) . '</strong> ';
			if ( ! $rate_check['can_export'] ) {
				/* translators: %d: minutes to wait */
				$export_history .= esc_html( sprintf( __( 'in %d minutes', 'nobloat-user-foundry' ), $rate_check['wait_minutes'] ) );
			} else {
				$export_history .= esc_html__( 'Now', 'nobloat-user-foundry' );
			}
			$export_history .= '</p>';
		} else {
			$export_history = '<p>' . esc_html__( 'You have not exported your data yet.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Load HTML template */
		$template = NBUF_Template_Manager::load_default_file( 'account-data-export-html' );

		/* Build replacements */
		$replacements = array(
			'{section_title}'        => esc_html_x( 'Download Your Personal Data', 'GDPR export section title', 'nobloat-user-foundry' ),
			'{gdpr_description}'     => esc_html__( 'Under GDPR Article 15 (Right of Access), you have the right to receive a copy of your personal data we store.', 'nobloat-user-foundry' ),
			'{export_includes_title}' => esc_html__( 'This export includes:', 'nobloat-user-foundry' ),
			'{export_includes_list}' => $includes_list,
			'{estimated_size_label}' => esc_html__( 'Estimated file size:', 'nobloat-user-foundry' ),
			'{estimated_size}'       => esc_html( size_format( $counts['estimated_size'] ) ),
			'{format_label}'         => esc_html__( 'Format:', 'nobloat-user-foundry' ),
			'{format_value}'         => esc_html__( 'ZIP archive (JSON + HTML)', 'nobloat-user-foundry' ),
			'{export_button}'        => $export_button,
			'{history_title}'        => esc_html__( 'Export History:', 'nobloat-user-foundry' ),
			'{export_history}'       => $export_history,
			'{modal_title}'          => esc_html_x( 'Confirm Your Password', 'Password modal title', 'nobloat-user-foundry' ),
			'{modal_description}'    => esc_html__( 'For security, please confirm your password to download your personal data.', 'nobloat-user-foundry' ),
			'{password_label}'       => esc_html__( 'Password:', 'nobloat-user-foundry' ),
			'{cancel_button}'        => esc_html__( 'Cancel', 'nobloat-user-foundry' ),
			'{confirm_button}'       => esc_html__( 'Confirm & Download', 'nobloat-user-foundry' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function enqueue_scripts() {
		if ( ! self::is_enabled() || ! is_user_logged_in() ) {
			return;
		}

		/* Check if we're on account page or page with shortcode */
		$account_page_id = NBUF_Options::get( 'nbuf_page_account' );
		$is_account_page = $account_page_id && is_page( $account_page_id );

		global $post;
		$has_shortcode = $post && has_shortcode( $post->post_content, 'nbuf_data_export' );

		if ( ! $is_account_page && ! $has_shortcode ) {
			return;
		}

		/* Enqueue JavaScript */
		wp_enqueue_script(
			'nbuf-gdpr-export',
			NBUF_PLUGIN_URL . 'assets/js/gdpr-export.js',
			array( 'jquery' ),
			NBUF_VERSION,
			true
		);

		/* Localize script with vars */
		wp_localize_script(
			'nbuf-gdpr-export',
			'nbuf_gdpr_vars',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'nbuf_export_data' ),
				'require_password' => (bool) NBUF_Options::get( 'nbuf_gdpr_require_password', true ),
				'i18n'             => array(
					'password_required'  => __( 'Please enter your password.', 'nobloat-user-foundry' ),
					'processing'         => __( 'Processing...', 'nobloat-user-foundry' ),
					'confirm_download'   => __( 'Confirm & Download', 'nobloat-user-foundry' ),
					'generating_export'  => __( 'Generating your data export...', 'nobloat-user-foundry' ),
					'ajax_error'         => __( 'An error occurred. Please try again.', 'nobloat-user-foundry' ),
					'unknown_error'      => __( 'Unknown error occurred.', 'nobloat-user-foundry' ),
					'email_sent'         => __( 'Email Sent', 'nobloat-user-foundry' ),
				),
			)
		);
	}

	/**
	 * Add admin export button to user profile page
	 *
	 * @since 1.4.0
	 * @param WP_User $user User object.
	 * @return void
	 */
	public static function add_admin_export_button( $user ) {
		if ( ! self::is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Enqueue external script with localized data */
		wp_enqueue_script(
			'nbuf-gdpr-admin-export',
			NBUF_PLUGIN_URL . 'assets/js/admin/gdpr-admin-export.js',
			array( 'jquery' ),
			NBUF_VERSION,
			true
		);

		wp_localize_script(
			'nbuf-gdpr-admin-export',
			'nbufAdminExport',
			array(
				'nonce' => wp_create_nonce( 'nbuf_admin_export_data' ),
				'i18n'  => array(
					'generating'        => __( 'Generating...', 'nobloat-user-foundry' ),
					'generating_export' => __( 'Generating export...', 'nobloat-user-foundry' ),
					'download_button'   => __( 'Download User Data (ZIP)', 'nobloat-user-foundry' ),
					'error'             => __( 'Error occurred. Please try again.', 'nobloat-user-foundry' ),
				),
			)
		);

		?>
		<h2><?php esc_html_e( 'GDPR Data Export', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Download User Data', 'nobloat-user-foundry' ); ?></th>
				<td>
					<button type="button" id="nbuf-admin-export-btn" class="button" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
						<?php esc_html_e( 'Download User Data (ZIP)', 'nobloat-user-foundry' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Download all personal data for this user in ZIP format (includes NoBloat profile data', 'nobloat-user-foundry' ); ?>
						<?php if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) ) : ?>
							<?php esc_html_e( ', WooCommerce orders', 'nobloat-user-foundry' ); ?>
						<?php endif; ?>
						<?php if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) ) : ?>
							<?php esc_html_e( ', EDD purchases', 'nobloat-user-foundry' ); ?>
						<?php endif; ?>
						<?php esc_html_e( ').', 'nobloat-user-foundry' ); ?>
					</p>
					<div id="nbuf-admin-export-status" style="margin-top: 10px;"></div>
				</td>
			</tr>
		</table>
		<?php
	}
}
