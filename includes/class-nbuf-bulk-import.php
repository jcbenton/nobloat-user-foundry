<?php
/**
 * Bulk User Import Class
 *
 * Handles CSV import of users with all profile fields
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk user import handler
 *
 * @since 1.3.0
 */
class NBUF_Bulk_Import {


	/**
	 * Import results storage
	 *
	 * @var array
	 */
	private $results = array(
		'success' => 0,
		'skipped' => 0,
		'errors'  => array(),
	);

	/**
	 * Valid profile field keys
	 *
	 * @var array
	 */
	private $valid_profile_fields = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_nbuf_upload_import_csv', array( $this, 'ajax_upload_csv' ) );
		add_action( 'wp_ajax_nbuf_import_users', array( $this, 'ajax_import_users' ) );
		add_action( 'wp_ajax_nbuf_download_error_report', array( $this, 'ajax_download_error_report' ) );

		/* Initialize valid profile fields */
		$this->init_valid_fields();
	}

	/**
	 * Initialize valid profile field keys
	 */
	private function init_valid_fields() {
		/* Core WordPress fields */
		$core_fields = array(
			'user_login',
			'user_email',
			'user_pass',
			'display_name',
			'first_name',
			'last_name',
			'user_url',
			'description',
			'role',
			'user_registered',
		);

		/* Profile fields from registration */
		$profile_fields = array(
			/* Personal Information */
			'bio',
			'date_of_birth',
			'gender',
			'pronouns',
			'nationality',

			/* Contact Information */
			'phone',
			'mobile_phone',
			'fax',
			'address_line_1',
			'address_line_2',
			'city',
			'state',
			'postal_code',
			'country',

			/* Social Media */
			'facebook',
			'twitter',
			'linkedin',
			'instagram',
			'youtube',
			'tiktok',
			'github',
			'website',

			/* Professional */
			'company',
			'job_title',
			'department',
			'employee_id',
			'education_level',
			'school',
			'degree',
			'graduation_year',

			/* Preferences */
			'language_preference',
			'timezone',
			'communication_preference',
			'newsletter_opt_in',
			'marketing_opt_in',

			/* Emergency Contact */
			'emergency_contact_name',
			'emergency_contact_phone',
			'emergency_contact_relationship',

			/* Legal */
			'tax_id',
			'government_id',

			/* Custom */
			'custom_field_1',
			'custom_field_2',
			'custom_field_3',
			'custom_field_4',
			'custom_field_5',
			'custom_field_6',
			'custom_field_7',
			'custom_field_8',
			'custom_field_9',
			'custom_field_10',
		);

		$this->valid_profile_fields = array_merge( $core_fields, $profile_fields );
	}

	/**
	 * Sanitize and validate field value for CSV injection prevention
	 *
	 * SECURITY: Prevents CSV formula injection attacks via comprehensive character blocking.
	 * Blocks: = + - @ | (formula/command injection)
	 * Blocks: Tab, CR, LF characters (control character injection)
	 * Blocks: Escaped formulas like '\t=' or "=" (quote-escaped injection)
	 *
	 * @param  string $value       Field value to sanitize.
	 * @param  string $field_name  Field name for error messages.
	 * @param  int    $line_number Line number for error messages.
	 * @return string|WP_Error Sanitized value or WP_Error on injection attempt.
	 */
	private function sanitize_csv_field( $value, $field_name, $line_number ) {
		$sanitized = sanitize_text_field( $value );

		/* Skip empty values */
		if ( empty( $sanitized ) ) {
			return $sanitized;
		}

		/*
		 * SECURITY: Block CSV formula injection characters.
		 * These characters at the start of a field can trigger formula/command execution in Excel/LibreOffice.
		 */
		$dangerous_chars = array( '=', '+', '-', '@', '|', "\t", "\r", "\n" );
		$first_char      = substr( $sanitized, 0, 1 );

		if ( in_array( $first_char, $dangerous_chars, true ) ) {
			/* SECURITY: Prefix with single quote to neutralize formula execution */
			$sanitized = "'" . $sanitized;

			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'csv_injection_prevented',
					'warning',
					'CSV field prefixed to prevent injection',
					array(
						'field'    => $field_name,
						'line'     => $line_number,
						'original' => $value,
					)
				);
			}
		}

		/*
		 * SECURITY: Block escaped formula injection.
		 * Patterns like '\t=', '"=', or "'=" can bypass simple first-character checks.
		 */
		if ( preg_match( '/^[\\\'"]*[=+\-@|]/', $sanitized ) ) {
			return new WP_Error(
				'csv_injection',
				sprintf(
					/* translators: 1: line number, 2: field name */
					__( 'Line %1$d: Field "%2$s" contains escaped formula pattern (CSV injection prevention)', 'nobloat-user-foundry' ),
					$line_number,
					$field_name
				)
			);
		}

		return $sanitized;
	}

	/**
	 * AJAX: Upload and validate CSV file
	 */
	public function ajax_upload_csv() {
		check_ajax_referer( 'nbuf_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		/* Check if file was uploaded */
		if ( ! isset( $_FILES['csv_file'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

		$file = isset( $_FILES['csv_file'] ) ? wp_unslash( $_FILES['csv_file'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload data validated below.

		/* Validate file size (10MB max) */
		if ( $file['size'] > 10485760 ) {
			wp_send_json_error( array( 'message' => 'File too large. Maximum size is 10MB.' ) );
		}

		/* Validate file type using WordPress core function to prevent spoofing */
		$filetype     = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$allowed_exts = array( 'csv' );

		if ( ! in_array( $filetype['ext'], $allowed_exts, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid file type. Only CSV files are allowed.' ) );
		}

		/* Parse CSV */
		$csv_data = $this->parse_csv( $file['tmp_name'] );

		if ( is_wp_error( $csv_data ) ) {
			wp_send_json_error( array( 'message' => $csv_data->get_error_message() ) );
		}

		/* Validate CSV structure */
		$validation = $this->validate_csv_structure( $csv_data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		/* Store CSV data in transient for processing */
		$transient_key = 'nbuf_import_' . get_current_user_id() . '_' . time();
		set_transient( $transient_key, $csv_data, HOUR_IN_SECONDS );

		/* Run dry-run validation */
		$preview = $this->dry_run_validation( $csv_data );

		wp_send_json_success(
			array(
				'transient_key' => $transient_key,
				'preview'       => $preview,
				'total_rows'    => count( $csv_data['rows'] ),
			)
		);
	}

	/**
	 * Parse CSV file
	 *
	 * @param  string $file_path Path to CSV file.
	 * @return array|WP_Error Parsed data or error.
	 */
	private function parse_csv( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_error', 'Cannot read CSV file' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV parsing requires direct file access
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'file_error', 'Cannot open CSV file' );
		}

		/* Read header row */
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV parsing requires direct file access
			fclose( $handle );
			return new WP_Error( 'parse_error', 'CSV file is empty or invalid' );
		}

		/* Clean headers */
		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'strtolower', $headers );

		/* Read data rows */
		$rows        = array();
		$line_number = 2; // Start at 2 (1 is header).

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure, Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV parsing pattern with fgetcsv().
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( count( $row ) === count( $headers ) ) {
				$rows[] = array(
					'line' => $line_number,
					'data' => array_combine( $headers, $row ),
					'raw'  => $row,
				);
			}
			++$line_number;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV parsing requires direct file access
		fclose( $handle );

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Validate CSV structure
	 *
	 * @param  array $csv_data Parsed CSV data.
	 * @return true|WP_Error True if valid, error otherwise.
	 */
	private function validate_csv_structure( $csv_data ) {
		/* Check if CSV has data */
		if ( empty( $csv_data['rows'] ) ) {
			return new WP_Error( 'empty_csv', 'CSV file contains no data rows' );
		}

		/* Check for required fields */
		$required_fields = array( 'user_email', 'user_login' );
		$headers         = $csv_data['headers'];

		foreach ( $required_fields as $required ) {
			if ( ! in_array( $required, $headers, true ) ) {
				return new WP_Error( 'missing_field', sprintf( 'Required field "%s" not found in CSV headers', $required ) );
			}
		}

		return true;
	}

	/**
	 * Dry-run validation (preview without importing)
	 *
	 * @param  array $csv_data Parsed CSV data.
	 * @return array Validation results.
	 */
	private function dry_run_validation( $csv_data ) {
		$preview = array(
			'valid'   => 0,
			'invalid' => 0,
			'errors'  => array(),
			'samples' => array(),
		);

		$max_samples  = 5;
		$sample_count = 0;

		foreach ( $csv_data['rows'] as $row ) {
			$validation = $this->validate_row( $row['data'], $row['line'] );

			if ( is_wp_error( $validation ) ) {
				++$preview['invalid'];
				$preview['errors'][] = array(
					'line'    => $row['line'],
					'message' => $validation->get_error_message(),
				);
			} else {
				++$preview['valid'];

				/* Add to samples */
				if ( $sample_count < $max_samples ) {
					$preview['samples'][] = array(
						'line' => $row['line'],
						'data' => $validation,
					);
					++$sample_count;
				}
			}
		}

		return $preview;
	}

	/**
	 * Validate single row
	 *
	 * @param  array $row         Row data.
	 * @param  int   $line_number Line number.
	 * @return array|WP_Error Validated data or error.
	 */
	private function validate_row( $row, $line_number ) {
		$validated = array();
		$errors    = array();

		/* Required: Email */
		if ( empty( $row['user_email'] ) ) {
			return new WP_Error( 'missing_email', sprintf( 'Line %d: Email is required', $line_number ) );
		}

		if ( ! is_email( $row['user_email'] ) ) {
			return new WP_Error( 'invalid_email', sprintf( 'Line %d: Invalid email format: %s', $line_number, $row['user_email'] ) );
		}

		/* Check if email already exists */
		if ( email_exists( $row['user_email'] ) ) {
			$update_existing = NBUF_Options::get( 'nbuf_import_update_existing', false );
			if ( ! $update_existing ) {
				return new WP_Error( 'duplicate_email', sprintf( 'Line %d: Email already exists: %s', $line_number, $row['user_email'] ) );
			}
		}

		$validated['user_email'] = sanitize_email( $row['user_email'] );

		/* Required: Username */
		if ( empty( $row['user_login'] ) ) {
			return new WP_Error( 'missing_username', sprintf( 'Line %d: Username is required', $line_number ) );
		}

		if ( ! validate_username( $row['user_login'] ) ) {
			return new WP_Error( 'invalid_username', sprintf( 'Line %d: Invalid username: %s', $line_number, $row['user_login'] ) );
		}

		/* Check if username already exists */
		if ( username_exists( $row['user_login'] ) ) {
			$update_existing = NBUF_Options::get( 'nbuf_import_update_existing', false );
			if ( ! $update_existing ) {
				return new WP_Error( 'duplicate_username', sprintf( 'Line %d: Username already exists: %s', $line_number, $row['user_login'] ) );
			}
		}

		$validated['user_login'] = sanitize_user( $row['user_login'] );

		/* Optional: Password (generate if not provided) */
		if ( ! empty( $row['user_pass'] ) ) {
			$validated['user_pass'] = $row['user_pass']; // Don't sanitize passwords.
		} else {
			$validated['user_pass'] = wp_generate_password( 16, true, true );
		}

		/* Optional: Display name */
		if ( ! empty( $row['display_name'] ) ) {
			$display_name = $this->sanitize_csv_field( $row['display_name'], 'display_name', $line_number );
			if ( is_wp_error( $display_name ) ) {
				return $display_name;
			}
			$validated['display_name'] = $display_name;
		}

		/* Optional: First/Last name */
		if ( ! empty( $row['first_name'] ) ) {
			$first_name = $this->sanitize_csv_field( $row['first_name'], 'first_name', $line_number );
			if ( is_wp_error( $first_name ) ) {
				return $first_name;
			}
			$validated['first_name'] = $first_name;
		}

		if ( ! empty( $row['last_name'] ) ) {
			$last_name = $this->sanitize_csv_field( $row['last_name'], 'last_name', $line_number );
			if ( is_wp_error( $last_name ) ) {
				return $last_name;
			}
			$validated['last_name'] = $last_name;
		}

		/* Optional: Role */
		if ( ! empty( $row['role'] ) ) {
			$valid_roles = array_keys( wp_roles()->roles );
			if ( ! in_array( $row['role'], $valid_roles, true ) ) {
				return new WP_Error( 'invalid_role', sprintf( 'Line %d: Invalid role: %s', $line_number, $row['role'] ) );
			}
			$validated['role'] = $row['role'];
		} else {
			$validated['role'] = NBUF_Options::get( 'nbuf_import_default_role', 'subscriber' );
		}

		/* Process all other fields with comprehensive CSV injection prevention */
		foreach ( $row as $key => $value ) {
			if ( in_array( $key, array( 'user_email', 'user_login', 'user_pass', 'display_name', 'first_name', 'last_name', 'role' ), true ) ) {
				continue; // Already processed.
			}

			if ( in_array( $key, $this->valid_profile_fields, true ) && ! empty( $value ) ) {
				/* SECURITY: Comprehensive CSV injection prevention */
				$sanitized = $this->sanitize_csv_field( $value, $key, $line_number );
				if ( is_wp_error( $sanitized ) ) {
					return $sanitized;
				}
				$validated[ $key ] = $sanitized;
			}
		}

		return $validated;
	}

	/**
	 * AJAX: Import users from CSV
	 */
	public function ajax_import_users() {
		check_ajax_referer( 'nbuf_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$transient_key = isset( $_POST['transient_key'] ) ? sanitize_text_field( wp_unslash( $_POST['transient_key'] ) ) : '';
		$batch_size    = NBUF_Options::get( 'nbuf_import_batch_size', 50 );
		$offset        = isset( $_POST['offset'] ) ? intval( wp_unslash( $_POST['offset'] ) ) : 0;

		/* Get CSV data from transient */
		$csv_data = get_transient( $transient_key );

		if ( ! $csv_data ) {
			wp_send_json_error( array( 'message' => 'Import session expired. Please upload CSV again.' ) );
		}

		/* Process batch */
		$batch_rows = array_slice( $csv_data['rows'], $offset, $batch_size );

		foreach ( $batch_rows as $row ) {
			$this->import_user( $row['data'], $row['line'] );
		}

		$total_rows = count( $csv_data['rows'] );
		$processed  = $offset + count( $batch_rows );
		$complete   = ( $processed >= $total_rows );

		/* Clean up transient if complete */
		if ( $complete ) {
			delete_transient( $transient_key );

			/* Store error report if there are errors */
			if ( ! empty( $this->results['errors'] ) ) {
				set_transient( $transient_key . '_errors', $this->results['errors'], HOUR_IN_SECONDS );
			}
		}

		wp_send_json_success(
			array(
				'complete'  => $complete,
				'processed' => $processed,
				'total'     => $total_rows,
				'success'   => $this->results['success'],
				'skipped'   => $this->results['skipped'],
				'errors'    => count( $this->results['errors'] ),
				'error_key' => $transient_key . '_errors',
			)
		);
	}

	/**
	 * Import single user
	 *
	 * @param array $data        User data.
	 * @param int   $line_number Line number.
	 */
	private function import_user( $data, $line_number ) {
		/* Validate row */
		$validated = $this->validate_row( $data, $line_number );

		if ( is_wp_error( $validated ) ) {
			++$this->results['skipped'];
			$this->results['errors'][] = array(
				'line'    => $line_number,
				'message' => $validated->get_error_message(),
			);
			return;
		}

		/* Check if user exists */
		$existing_user   = get_user_by( 'email', $validated['user_email'] );
		$update_existing = NBUF_Options::get( 'nbuf_import_update_existing', false );

		if ( $existing_user && ! $update_existing ) {
			++$this->results['skipped'];
			$this->results['errors'][] = array(
				'line'    => $line_number,
				'message' => sprintf( 'User already exists: %s', $validated['user_email'] ),
			);
			return;
		}

		/* Separate core fields from profile fields */
		$core_fields = array(
			'user_login',
			'user_email',
			'user_pass',
			'display_name',
			'first_name',
			'last_name',
			'user_url',
			'description',
			'role',
		);

		$user_data    = array();
		$profile_data = array();

		foreach ( $validated as $key => $value ) {
			if ( in_array( $key, $core_fields, true ) ) {
				$user_data[ $key ] = $value;
			} else {
				$profile_data[ $key ] = $value;
			}
		}

		/* Create or update user */
		if ( $existing_user ) {
			$user_data['ID'] = $existing_user->ID;
			$user_id         = wp_update_user( $user_data );
		} else {
			$user_id = wp_insert_user( $user_data );
		}

		if ( is_wp_error( $user_id ) ) {
			++$this->results['skipped'];
			$this->results['errors'][] = array(
				'line'    => $line_number,
				'message' => sprintf( 'Failed to create user: %s', $user_id->get_error_message() ),
			);
			return;
		}

		/* Save profile data */
		if ( ! empty( $profile_data ) ) {
			$this->save_profile_data( $user_id, $profile_data );
		}

		/* Set verification status */
		$verify_emails = NBUF_Options::get( 'nbuf_import_verify_emails', true );
		if ( $verify_emails ) {
			$this->set_user_verified( $user_id, false );
		} else {
			$this->set_user_verified( $user_id, true );
		}

		/* Send welcome email if enabled */
		$send_welcome = NBUF_Options::get( 'nbuf_import_send_welcome', false );
		if ( $send_welcome && ! $existing_user ) {
			$this->send_welcome_email( $user_id, $validated['user_pass'] );
		}

		++$this->results['success'];
	}

	/**
	 * Save profile data for user
	 *
	 * @param int   $user_id      User ID.
	 * @param array $profile_data Profile field data.
	 */
	private function save_profile_data( $user_id, $profile_data ) {
		global $wpdb;

		/* Check if profile row exists */
		$table_name = $wpdb->prefix . 'nbuf_user_profile';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		if ( $exists ) {
			/*
			 * Update existing
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations for nbuf_profile_data table.
			$wpdb->update(
				$table_name,
				$profile_data,
				array( 'user_id' => $user_id ),
				array_fill( 0, count( $profile_data ), '%s' ),
				array( '%d' )
			);
		} else {
			/* Insert new */
			$profile_data['user_id'] = $user_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$wpdb->insert(
				$table_name,
				$profile_data,
				array_merge( array( '%d' ), array_fill( 0, count( $profile_data ) - 1, '%s' ) )
			);
		}
	}

	/**
	 * Set user verification status
	 *
	 * @param int  $user_id  User ID.
	 * @param bool $verified Verified status.
	 */
	private function set_user_verified( $user_id, $verified ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_data';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		$status      = $verified ? 'verified' : 'pending';
		$verified_at = $verified ? current_time( 'mysql' ) : null;

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$wpdb->update(
				$table_name,
				array(
					'status'      => $status,
					'verified_at' => $verified_at,
				),
				array( 'user_id' => $user_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$wpdb->insert(
				$table_name,
				array(
					'user_id'     => $user_id,
					'status'      => $status,
					'verified_at' => $verified_at,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Send welcome email to new user
	 *
	 * @param int    $user_id  User ID.
	 * @param string $password Plain text password.
	 */
	private function send_welcome_email( $user_id, $password ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( 'Welcome to %s', get_bloginfo( 'name' ) );
		$message = sprintf(
			"Welcome to %s!\n\nYour account has been created:\n\nUsername: %s\nPassword: %s\n\nLogin here: %s\n",
			get_bloginfo( 'name' ),
			$user->user_login,
			$password,
			wp_login_url()
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * AJAX: Download error report
	 */
	public function ajax_download_error_report() {
		check_ajax_referer( 'nbuf_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$error_key = isset( $_GET['error_key'] ) ? sanitize_text_field( wp_unslash( $_GET['error_key'] ) ) : '';
		$errors    = get_transient( $error_key );

		if ( ! $errors ) {
			wp_die( 'Error report not found or expired' );
		}

		/* Generate CSV */
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="import-errors-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV output requires direct file access
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			NBUF_Security_Log::log(
				'csv_output_failed',
				'critical',
				'Failed to open php://output stream for CSV error report export',
				array(
					'error_key' => $error_key,
				)
			);
			wp_die( esc_html__( 'Failed to generate CSV report. Please try again or contact support.', 'nobloat-user-foundry' ) );
		}
		fputcsv( $output, array( 'Line Number', 'Error Message' ) );

		foreach ( $errors as $error ) {
			fputcsv( $output, array( $error['line'], $error['message'] ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV output requires direct file access
		fclose( $output );
		delete_transient( $error_key );
		exit;
	}
}
