<?php
/**
 * Configuration Importer Class
 *
 * Imports plugin settings and templates from JSON
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Config_Importer
 *
 * Imports plugin configuration from JSON.
 */
class NBUF_Config_Importer {


	/**
	 * Import results
	 *
	 * @var array
	 */
	private $results = array(
		'settings_imported'  => 0,
		'settings_skipped'   => 0,
		'templates_imported' => 0,
		'errors'             => array(),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_nbuf_validate_import_config', array( $this, 'ajax_validate_config' ) );
		add_action( 'wp_ajax_nbuf_import_config', array( $this, 'ajax_import_config' ) );
	}

	/**
	 * AJAX: Validate configuration file
	 */
	public function ajax_validate_config() {
		check_ajax_referer( 'nbuf_config_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		/* Check if file was uploaded */
		if ( ! isset( $_FILES['config_file'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File validation performed below.
		$file = $_FILES['config_file'];

		/* SECURITY: Validate file type using WordPress core function to prevent spoofing */
		$filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$allowed_exts  = array( 'json' );
		$allowed_types = array( 'application/json', 'text/plain' );

		if ( ! in_array( $filetype['ext'], $allowed_exts, true ) ||
			! in_array( $filetype['type'], $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid file type. Only JSON files are allowed.' ) );
		}

		/* Validate file size (5MB max) */
		if ( $file['size'] > 5242880 ) {
			wp_send_json_error( array( 'message' => 'File too large. Maximum size is 5MB.' ) );
		}

		/*
		 * Read and parse JSON with safety limits
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local uploaded file, not remote URL.
		$json_content = file_get_contents( $file['tmp_name'], false, null, 0, 5242880 ); // 5MB limit
		if ( false === $json_content ) {
			wp_send_json_error( array( 'message' => 'Failed to read file' ) );
		}

		/* Parse JSON with depth limit to prevent XXE-style attacks */
		$config_data = json_decode( $json_content, true, 512 );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid JSON: ' . json_last_error_msg() ) );
		}

		/* Validate configuration structure */
		$validation = $this->validate_config_structure( $config_data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		/* Store config data in transient */
		$transient_key = 'nbuf_import_config_' . get_current_user_id() . '_' . time();
		set_transient( $transient_key, $config_data, HOUR_IN_SECONDS );

		/* Generate preview */
		$preview = $this->generate_preview( $config_data );

		wp_send_json_success(
			array(
				'transient_key' => $transient_key,
				'preview'       => $preview,
			)
		);
	}

	/**
	 * Validate configuration structure
	 *
	 * @param  array $config_data Configuration data.
	 * @return true|WP_Error True if valid, error otherwise.
	 */
	private function validate_config_structure( $config_data ) {
		/* Check required fields */
		if ( ! isset( $config_data['nbuf_config_version'] ) ) {
			return new WP_Error( 'invalid_config', 'Missing configuration version' );
		}

		if ( ! isset( $config_data['plugin_version'] ) ) {
			return new WP_Error( 'invalid_config', 'Missing plugin version' );
		}

		/* Check version compatibility */
		$current_version = $this->get_plugin_version();
		$import_version  = $config_data['plugin_version'];

		/* Major version must match */
		$current_major = (int) explode( '.', $current_version )[0];
		$import_major  = (int) explode( '.', $import_version )[0];

		if ( $current_major !== $import_major ) {
			return new WP_Error(
				'version_mismatch',
				sprintf(
					'Version mismatch: Current plugin is v%s, config is from v%s. Major versions must match.',
					$current_version,
					$import_version
				)
			);
		}

		/* Check if config has data to import */
		if ( empty( $config_data['settings'] ) && empty( $config_data['templates'] ) ) {
			return new WP_Error( 'empty_config', 'Configuration file contains no data to import' );
		}

		return true;
	}

	/**
	 * Generate preview of import
	 *
	 * @param  array $config_data Configuration data.
	 * @return array Preview data.
	 */
	private function generate_preview( $config_data ) {
		$preview = array(
			'plugin_version' => $config_data['plugin_version'] ?? 'Unknown',
			'exported_at'    => $config_data['exported_at'] ?? 'Unknown',
			'site_url'       => $config_data['site_url'] ?? 'Unknown',
			'settings_count' => 0,
			'template_count' => 0,
			'categories'     => array(),
		);

		/* Count settings */
		if ( isset( $config_data['settings'] ) && is_array( $config_data['settings'] ) ) {
			foreach ( $config_data['settings'] as $category => $settings ) {
				$count                              = count( $settings );
				$preview['settings_count']         += $count;
				$preview['categories'][ $category ] = $count;
			}
		}

		/* Count templates */
		if ( isset( $config_data['templates'] ) && is_array( $config_data['templates'] ) ) {
			foreach ( $config_data['templates'] as $type => $templates ) {
				$count                      = count( $templates );
				$preview['template_count'] += $count;
				$preview['categories'][ 'templates_' . $type ] = $count;
			}
		}

		return $preview;
	}

	/**
	 * AJAX: Import configuration
	 */
	public function ajax_import_config() {
		check_ajax_referer( 'nbuf_config_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$transient_key = isset( $_POST['transient_key'] ) ? sanitize_text_field( wp_unslash( $_POST['transient_key'] ) ) : '';
		$import_mode   = isset( $_POST['import_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['import_mode'] ) ) : 'overwrite';

		/* Get config data from transient */
		$config_data = get_transient( $transient_key );

		if ( ! $config_data ) {
			wp_send_json_error( array( 'message' => 'Import session expired. Please upload config file again.' ) );
		}

		/* Import settings */
		if ( isset( $config_data['settings'] ) ) {
			$this->import_settings( $config_data['settings'], $import_mode );
		}

		/* Import templates */
		if ( isset( $config_data['templates'] ) ) {
			$this->import_templates( $config_data['templates'], $import_mode );
		}

		/* Clean up transient */
		delete_transient( $transient_key );

		/* Clear options cache to ensure fresh data */
		if ( class_exists( 'NBUF_Options' ) && method_exists( 'NBUF_Options', 'clear_cache' ) ) {
			NBUF_Options::clear_cache();
		}

		wp_send_json_success(
			array(
				'settings_imported'  => $this->results['settings_imported'],
				'settings_skipped'   => $this->results['settings_skipped'],
				'templates_imported' => $this->results['templates_imported'],
				'errors'             => $this->results['errors'],
			)
		);
	}

	/**
	 * Import settings
	 *
	 * @param array  $settings Settings data.
	 * @param string $mode     Import mode (overwrite, merge).
	 */
	private function import_settings( $settings, $mode = 'overwrite' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_options';

		foreach ( $settings as $category => $category_settings ) {
			foreach ( $category_settings as $option_name => $option_value ) {
				/* Skip if merge mode and option already exists */
				if ( 'merge' === $mode ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
					$existing = $wpdb->get_var(
						$wpdb->prepare(
                   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
							"SELECT option_value FROM $table_name WHERE option_name = %s",
							$option_name
						)
					);

					if ( null !== $existing ) {
								++$this->results['settings_skipped'];
								continue;
					}
				}

				/* Serialize if needed */
				if ( is_array( $option_value ) || is_object( $option_value ) ) {
					$option_value = maybe_serialize( $option_value );
				}

				/*
				 * Check if option exists
				 *
				 * phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				 * Custom table operations.
				 */
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for nbuf_options table.
				$exists = $wpdb->get_var(
					$wpdb->prepare(
                  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
						"SELECT option_name FROM $table_name WHERE option_name = %s",
						$option_name
					)
				);

				if ( $exists ) {
						/*
						 * Update existing
						 *
						 * phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						 * Custom table operations.
						 */
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for nbuf_options table.
					$result = $wpdb->update(
						$table_name,
						array( 'option_value' => $option_value ),
						array( 'option_name' => $option_name ),
						array( '%s' ),
						array( '%s' )
					);
				} else {
					/*
					 * Insert new
					 *
					 * phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					 * Custom table operations.
					 */
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for nbuf_options table.
					$result = $wpdb->insert(
						$table_name,
						array(
							'option_name'  => $option_name,
							'option_value' => $option_value,
						),
						array( '%s', '%s' )
					);
				}

				if ( false !== $result ) {
					++$this->results['settings_imported'];
				} else {
					$this->results['errors'][] = sprintf( 'Failed to import setting: %s', $option_name );
				}
			}
		}
	}

	/**
	 * Import templates
	 *
	 * @param array  $templates Template data.
	 * @param string $mode      Import mode (overwrite, merge).
	 */
	private function import_templates( $templates, $mode = 'overwrite' ) {
		foreach ( $templates as $type => $type_templates ) {
			foreach ( $type_templates as $option_name => $option_value ) {
				/* Skip if merge mode and option already exists */
				if ( 'merge' === $mode ) {
					$existing = NBUF_Options::get( $option_name );
					if ( ! empty( $existing ) ) {
						continue;
					}
				}

				/* Update option */
				$result = NBUF_Options::update( $option_name, $option_value );

				if ( $result ) {
					++$this->results['templates_imported'];
				} else {
					$this->results['errors'][] = sprintf( 'Failed to import template: %s', $option_name );
				}
			}
		}
	}

	/**
	 * Get plugin version
	 *
	 * @return string Plugin version.
	 */
	private function get_plugin_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = dirname( __DIR__ ) . '/nobloat-user-foundry.php';
		$plugin_data = get_plugin_data( $plugin_file );

		return $plugin_data['Version'] ?? '1.3.0';
	}
}
