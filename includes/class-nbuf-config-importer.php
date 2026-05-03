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
	 * @var array{settings_imported: int, settings_skipped: int, templates_imported: int, errors: array<int, string>}
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
	 *
	 * @return void
	 */
	public function ajax_validate_config(): void {
		check_ajax_referer( 'nbuf_config_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		/* Check if file was uploaded */
		if ( ! isset( $_FILES['config_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'nobloat-user-foundry' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File validation performed below.
		$file = $_FILES['config_file'];

		/* SECURITY: Validate file type using WordPress core function to prevent spoofing */
		$filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$allowed_exts  = array( 'json' );
		$allowed_types = array( 'application/json', 'text/plain' );

		if ( ! in_array( $filetype['ext'], $allowed_exts, true ) ||
			! in_array( $filetype['type'], $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JSON files are allowed.', 'nobloat-user-foundry' ) ) );
		}

		/* Validate file size (5MB max) */
		if ( $file['size'] > 5242880 ) {
			wp_send_json_error( array( 'message' => __( 'File too large. Maximum size is 5MB.', 'nobloat-user-foundry' ) ) );
		}

		/*
		 * Read and parse JSON with safety limits
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local uploaded file, not remote URL.
		$json_content = file_get_contents( $file['tmp_name'], false, null, 0, 5242880 ); // 5MB limit
		if ( false === $json_content ) {
			wp_send_json_error( array( 'message' => __( 'Failed to read file.', 'nobloat-user-foundry' ) ) );
		}

		/* Parse JSON with depth limit to prevent XXE-style attacks */
		$config_data = json_decode( $json_content, true, 512 );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: JSON parse error message */
						__( 'Invalid JSON: %s', 'nobloat-user-foundry' ),
						json_last_error_msg()
					),
				)
			);
		}

		/* Validate configuration structure */
		$validation = $this->validate_config_structure( $config_data );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		/*
		 * Store config data in transient with random token.
		 * SECURITY: Use cryptographically secure random token to prevent replay attacks.
		 * Timestamp-based keys are predictable and could be guessed by attackers.
		 */
		$random_token  = bin2hex( random_bytes( 16 ) ); // 32 hex characters.
		$transient_key = 'nbuf_import_config_' . get_current_user_id() . '_' . $random_token;
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
	 * @param  array<string, mixed> $config_data Configuration data.
	 * @return true|WP_Error True if valid, error otherwise.
	 */
	private function validate_config_structure( $config_data ) {
		/* Check required fields */
		if ( ! isset( $config_data['nbuf_config_version'] ) ) {
			return new WP_Error( 'invalid_config', __( 'Missing configuration version.', 'nobloat-user-foundry' ) );
		}

		if ( ! isset( $config_data['plugin_version'] ) ) {
			return new WP_Error( 'invalid_config', __( 'Missing plugin version.', 'nobloat-user-foundry' ) );
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
					/* translators: 1: current plugin version, 2: config-file plugin version */
					__( 'Version mismatch: Current plugin is v%1$s, config is from v%2$s. Major versions must match.', 'nobloat-user-foundry' ),
					$current_version,
					$import_version
				)
			);
		}

		/* Check if config has data to import */
		if ( empty( $config_data['settings'] ) && empty( $config_data['templates'] ) ) {
			return new WP_Error( 'empty_config', __( 'Configuration file contains no data to import.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Generate preview of import
	 *
	 * @param  array<string, mixed> $config_data Configuration data.
	 * @return array<string, mixed> Preview data.
	 */
	private function generate_preview( $config_data ): array {
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
	 *
	 * @return void
	 */
	public function ajax_import_config(): void {
		check_ajax_referer( 'nbuf_config_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$transient_key = isset( $_POST['transient_key'] ) ? sanitize_text_field( wp_unslash( $_POST['transient_key'] ) ) : '';
		$import_mode   = isset( $_POST['import_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['import_mode'] ) ) : 'overwrite';
		if ( ! in_array( $import_mode, array( 'overwrite', 'merge' ), true ) ) {
			$import_mode = 'overwrite';
		}

		/* Validate transient key prefix to prevent reading arbitrary transients */
		if ( ! str_starts_with( $transient_key, 'nbuf_import_config_' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import key.', 'nobloat-user-foundry' ) ) );
		}

		/* Get config data from transient */
		$config_data = get_transient( $transient_key );

		if ( ! $config_data ) {
			wp_send_json_error( array( 'message' => __( 'Import session expired. Please upload config file again.', 'nobloat-user-foundry' ) ) );
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
	 * @param array<string, array<string, mixed>> $settings Settings data.
	 * @param string                              $mode     Import mode (overwrite, merge).
	 * @return void
	 */
	private function import_settings( $settings, $mode = 'overwrite' ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_options';

		foreach ( $settings as $category => $category_settings ) {
			foreach ( $category_settings as $option_name => $option_value ) {
				/* Only allow nbuf_ prefixed option names to prevent injection of arbitrary settings */
				if ( ! str_starts_with( $option_name, 'nbuf_' ) ) {
					continue;
				}

				/*
				 * SECURITY: refuse pre-serialized payloads supplied as raw strings.
				 * NBUF_Options::get() may unserialize stored values, so accepting an
				 * attacker-supplied is_serialized() string would create an object
				 * injection sink. Legitimate complex values arrive as arrays/objects
				 * and are serialized by maybe_serialize() below.
				 */
				if ( is_string( $option_value ) && is_serialized( $option_value ) ) {
					$this->results['errors'][] = sprintf(
						/* translators: %s: setting key whose value was a raw serialized payload */
						__( 'Refused setting with serialized payload: %s', 'nobloat-user-foundry' ),
						$option_name
					);
					continue;
				}

				/* Apply settings registry sanitizer if available to prevent bypass of validation */
				if ( class_exists( 'NBUF_Settings' ) && method_exists( 'NBUF_Settings', 'get_settings_registry' ) ) {
					$registry = NBUF_Settings::get_settings_registry();
					if ( isset( $registry[ $option_name ] ) && is_callable( $registry[ $option_name ] ) ) {
						$option_value = call_user_func( $registry[ $option_name ], $option_value );
					}
				}

				/* Skip if merge mode and option already exists */
				if ( 'merge' === $mode ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom options table.
					$existing = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT option_value FROM %i WHERE option_name = %s',
							$table_name,
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
				 * Check if option exists.
				 */
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom options table.
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT option_name FROM %i WHERE option_name = %s',
						$table_name,
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
					$this->results['errors'][] = sprintf(
						/* translators: %s: setting key that failed to import */
						__( 'Failed to import setting: %s', 'nobloat-user-foundry' ),
						$option_name
					);
				}
			}
		}
	}

	/**
	 * Import templates
	 *
	 * @param array<string, array<string, mixed>> $templates Template data.
	 * @param string                              $mode      Import mode (overwrite, merge).
	 * @return void
	 */
	private function import_templates( $templates, $mode = 'overwrite' ): void {
		foreach ( $templates as $type => $type_templates ) {
			foreach ( $type_templates as $option_name => $option_value ) {
				/* Only allow nbuf_ prefixed option names */
				if ( ! str_starts_with( $option_name, 'nbuf_' ) ) {
					continue;
				}

				/*
				 * SECURITY: Refuse pre-serialized payloads here too, mirroring
				 * import_settings(). NBUF_Options reads decode stored values,
				 * so accepting an attacker-supplied is_serialized() string would
				 * be an asymmetric object-injection sink relative to the
				 * settings path even though current decoders disallow classes.
				 */
				if ( is_string( $option_value ) && is_serialized( $option_value ) ) {
					$this->results['errors'][] = sprintf(
						/* translators: %s: template key whose value was a raw serialized payload */
						__( 'Refused template with serialized payload: %s', 'nobloat-user-foundry' ),
						$option_name
					);
					continue;
				}

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
					$this->results['errors'][] = sprintf(
						/* translators: %s: template key that failed to import */
						__( 'Failed to import template: %s', 'nobloat-user-foundry' ),
						$option_name
					);
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
