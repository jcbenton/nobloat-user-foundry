<?php
/**
 * NoBloat User Foundry - Migration Orchestrator
 *
 * Orchestrates migration of user data from other WordPress user management plugins.
 * Delegates to plugin-specific mapper classes for actual migration logic.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Migration {

	/**
	 * Plugin mapper registry
	 *
	 * Maps plugin slugs to their mapper class names.
	 *
	 * @var array
	 */
	private static $plugin_mappers = array(
		'ultimate-member' => 'NBUF_Migration_Ultimate_Member',
		'buddypress'      => 'NBUF_Migration_BP_Profile',
		/* Add more plugins here as we build them:
		'profile-builder' => 'NBUF_Migration_Profile_Builder',
		*/
	);

	/**
	 * Cached mapper instances
	 *
	 * @var array
	 */
	private static $mapper_instances = array();

	/**
	 * Initialize migration hooks
	 */
	public static function init() {
		/* Register AJAX handlers - OLD wizard-based handlers */
		add_action( 'wp_ajax_nbuf_detect_plugins', array( __CLASS__, 'ajax_detect_plugins' ) );
		add_action( 'wp_ajax_nbuf_get_field_mapping', array( __CLASS__, 'ajax_get_field_mapping' ) );
		add_action( 'wp_ajax_nbuf_discover_fields', array( __CLASS__, 'ajax_discover_fields' ) );
		add_action( 'wp_ajax_nbuf_suggest_mapping', array( __CLASS__, 'ajax_suggest_mapping' ) );
		add_action( 'wp_ajax_nbuf_preview_import', array( __CLASS__, 'ajax_preview_import' ) );
		add_action( 'wp_ajax_nbuf_execute_import', array( __CLASS__, 'ajax_execute_import' ) );
		add_action( 'wp_ajax_nbuf_rollback_import', array( __CLASS__, 'ajax_rollback_import' ) );

		/* Register NEW simplified UI AJAX handlers */
		add_action( 'wp_ajax_nbuf_load_migration_plugin', array( __CLASS__, 'ajax_load_migration_plugin' ) );
		add_action( 'wp_ajax_nbuf_get_field_mappings', array( __CLASS__, 'ajax_get_field_mappings' ) );
		add_action( 'wp_ajax_nbuf_get_restrictions_preview', array( __CLASS__, 'ajax_get_restrictions_preview' ) );
		add_action( 'wp_ajax_nbuf_get_roles_preview', array( __CLASS__, 'ajax_get_roles_preview' ) );
		add_action( 'wp_ajax_nbuf_execute_migration', array( __CLASS__, 'ajax_execute_migration' ) );
	}

	/**
	 * Get mapper instance for plugin
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return Abstract_NBUF_Migration_Plugin|null Mapper instance or null if not found
	 */
	public static function get_mapper( $plugin_slug ) {
		/* Return cached instance if exists */
		if ( isset( self::$mapper_instances[ $plugin_slug ] ) ) {
			return self::$mapper_instances[ $plugin_slug ];
		}

		/* Check if mapper class exists */
		if ( ! isset( self::$plugin_mappers[ $plugin_slug ] ) ) {
			return null;
		}

		$class_name = self::$plugin_mappers[ $plugin_slug ];

		/* Create and cache instance */
		if ( class_exists( $class_name ) ) {
			self::$mapper_instances[ $plugin_slug ] = new $class_name();
			return self::$mapper_instances[ $plugin_slug ];
		}

		return null;
	}

	/**
	 * Detect installed user management plugins
	 *
	 * @return array List of detected plugins
	 */
	public static function detect_installed_plugins() {
		$detected = array();

		foreach ( self::$plugin_mappers as $plugin_slug => $class_name ) {
			$mapper = self::get_mapper( $plugin_slug );

			if ( ! $mapper ) {
				continue;
			}

			/* Check if plugin is active */
			if ( $mapper->is_plugin_active() ) {
				$detected[] = array(
					'name'       => $mapper->get_name(),
					'slug'       => $mapper->get_slug(),
					'file'       => $mapper->get_plugin_file(),
					'user_count' => $mapper->get_user_count(),
				);
			}
		}

		return $detected;
	}

	/**
	 * Get field mapping for a plugin
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return array Field mappings
	 */
	public static function get_field_mapping( $plugin_slug ) {
		$mapper = self::get_mapper( $plugin_slug );

		if ( ! $mapper ) {
			return array();
		}

		return array(
			'core_fields' => $mapper->get_default_field_mapping(),
		);
	}

	/**
	 * Discover custom fields from plugin
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return array Custom fields with sample data
	 */
	public static function discover_custom_fields( $plugin_slug ) {
		$mapper = self::get_mapper( $plugin_slug );

		if ( ! $mapper ) {
			return array();
		}

		return $mapper->discover_custom_fields();
	}

	/**
	 * Preview import data
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param int    $limit       Number of users to preview.
	 * @param array  $field_mapping Optional custom field mapping.
	 * @return array Preview data
	 */
	public static function preview_import( $plugin_slug, $limit = 10, $field_mapping = array() ) {
		$mapper = self::get_mapper( $plugin_slug );

		if ( ! $mapper ) {
			return array();
		}

		return $mapper->preview_import( $limit, $field_mapping );
	}

	/**
	 * Execute import from a plugin
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param array  $options     Import options.
	 * @param array  $field_mapping Optional custom field mapping.
	 * @return array Import results
	 */
	public static function execute_import( $plugin_slug, $options = array(), $field_mapping = array() ) {
		$mapper = self::get_mapper( $plugin_slug );

		if ( ! $mapper ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid plugin or handler not found.', 'nobloat-user-foundry' ),
			);
		}

		$defaults = array(
			'send_emails'       => false,
			'set_verified'      => true,
			'skip_existing'     => true,
			'batch_size'        => 50,
			'batch_offset'      => 0,
		);

		$options = wp_parse_args( $options, $defaults );

		/* Execute the migration through mapper */
		return $mapper->batch_import( $options, $field_mapping );
	}


	/**
	 * Log import to history
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param array  $results     Import results.
	 * @return int Import history ID
	 */
	public static function log_import_history( $plugin_slug, $results ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_import_history';

		$data = array(
			'source_plugin' => $plugin_slug,
			'imported_by'   => get_current_user_id(),
			'total_rows'    => $results['total'],
			'successful'    => $results['imported'],
			'failed'        => count( $results['errors'] ),
			'skipped'       => $results['skipped'],
			'error_log'     => wp_json_encode( $results['errors'] ),
			'imported_at'   => current_time( 'mysql' ),
		);

		$wpdb->insert( $table_name, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Get import history
	 *
	 * @param int $limit Number of records to retrieve.
	 * @return array Import history records
	 */
	public static function get_import_history( $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_import_history';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY imported_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * AJAX: Detect installed plugins
	 */
	public static function ajax_detect_plugins() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$detected = self::detect_installed_plugins();

		wp_send_json_success( array( 'plugins' => $detected ) );
	}

	/**
	 * AJAX: Get field mapping
	 */
	public static function ajax_get_field_mapping() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$mapping = self::get_field_mapping( $plugin_slug );

		wp_send_json_success( array( 'mapping' => $mapping ) );
	}

	/**
	 * AJAX: Preview import
	 */
	public static function ajax_preview_import() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$preview = self::preview_import( $plugin_slug, 10 );

		wp_send_json_success( array( 'preview' => $preview ) );
	}

	/**
	 * AJAX: Execute import
	 */
	public static function ajax_execute_import() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';
		$batch_offset = isset( $_POST['batch_offset'] ) ? absint( $_POST['batch_offset'] ) : 0;

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		/* Parse custom field mappings if provided */
		$field_mapping = array();
		if ( isset( $_POST['field_mapping'] ) && ! empty( $_POST['field_mapping'] ) ) {
			$field_mapping_json = wp_unslash( $_POST['field_mapping'] );
			$field_mapping = json_decode( $field_mapping_json, true );

			if ( ! is_array( $field_mapping ) ) {
				$field_mapping = array();
			}
		}

		$options = array(
			'send_emails'   => false, /* Don't send emails during migration */
			'set_verified'  => true,
			'skip_existing' => true,
			'batch_size'    => 50,
			'batch_offset'  => $batch_offset,
		);

		$results = self::execute_import( $plugin_slug, $options, $field_mapping );

		/* Log to history on first batch */
		if ( 0 === $batch_offset ) {
			$import_id = self::log_import_history( $plugin_slug, $results );
			$results['import_id'] = $import_id;
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Discover custom fields
	 */
	public static function ajax_discover_fields() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$custom_fields = self::discover_custom_fields( $plugin_slug );

		wp_send_json_success( array( 'custom_fields' => $custom_fields ) );
	}

	/**
	 * AJAX: Suggest field mapping
	 */
	public static function ajax_suggest_mapping() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$source_field = isset( $_POST['source_field'] ) ? sanitize_text_field( wp_unslash( $_POST['source_field'] ) ) : '';
		$sample_value = isset( $_POST['sample_value'] ) ? sanitize_text_field( wp_unslash( $_POST['sample_value'] ) ) : null;

		if ( empty( $source_field ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field name', 'nobloat-user-foundry' ) ) );
		}

		/* Create mapper and get suggestions */
		$mapper = new NBUF_Field_Mapper();
		$suggestions = $mapper->suggest_mapping( $source_field, $sample_value );

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * AJAX: Rollback import
	 */
	public static function ajax_rollback_import() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$import_id = isset( $_POST['import_id'] ) ? absint( $_POST['import_id'] ) : 0;

		if ( ! $import_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import ID', 'nobloat-user-foundry' ) ) );
		}

		/* TODO: Implement rollback logic */
		wp_send_json_error( array( 'message' => __( 'Rollback not yet implemented', 'nobloat-user-foundry' ) ) );
	}

	/* ============================================================
	   NEW SIMPLIFIED UI AJAX HANDLERS
	   ============================================================ */

	/**
	 * AJAX: Load migration plugin data
	 *
	 * Returns plugin status, user count, field count, restrictions count
	 */
	public static function ajax_load_migration_plugin() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$data = array(
			'is_active'            => false,
			'user_count'           => 0,
			'profile_fields_count' => 0,
			'restrictions_count'   => 0,
			'roles_count'          => 0,
		);

		/* For Ultimate Member */
		if ( 'ultimate-member' === $plugin_slug ) {
			$data['is_active'] = is_plugin_active( 'ultimate-member/ultimate-member.php' );

			/* Get user count with UM data */
			$mapper = self::get_mapper( $plugin_slug );
			if ( $mapper ) {
				$data['user_count'] = $mapper->get_user_count();
			}

			/* Count profile fields */
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$data['profile_fields_count'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT meta_key)
				FROM {$wpdb->usermeta}
				WHERE meta_key NOT LIKE '\_%' AND meta_key NOT IN ('nickname', 'description', 'rich_editing')"
			);

			/* Count restrictions (if restrictions migration class exists) */
			if ( class_exists( 'NBUF_Migration_UM_Restrictions' ) ) {
				$data['restrictions_count'] = NBUF_Migration_UM_Restrictions::get_restriction_count();
			}

			/* Count custom roles (if roles migration class exists) */
			if ( class_exists( 'NBUF_Migration_UM_Roles' ) ) {
				$data['roles_count'] = NBUF_Migration_UM_Roles::get_role_count();
			}
		}

		/* For BuddyPress */
		if ( 'buddypress' === $plugin_slug ) {
			$data['is_active'] = function_exists( 'buddypress' );

			/* Get user count and field count */
			if ( class_exists( 'NBUF_Migration_BP_Profile' ) ) {
				$data['user_count'] = NBUF_Migration_BP_Profile::get_user_count();

				$stats = NBUF_Migration_BP_Profile::get_field_statistics();
				if ( ! empty( $stats ) ) {
					$data['profile_fields_count'] = $stats['total_bp_fields'];
				}
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get field mappings for plugin
	 *
	 * Returns array of source fields with auto-mapped targets and sample values
	 */
	public static function ajax_get_field_mappings() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$mappings = array();

		/* For Ultimate Member */
		if ( 'ultimate-member' === $plugin_slug ) {
			$mapper = self::get_mapper( $plugin_slug );
			if ( ! $mapper ) {
				wp_send_json_error( array( 'message' => __( 'Plugin mapper not found', 'nobloat-user-foundry' ) ) );
			}

			$default_mapping = $mapper->get_default_field_mapping();

			global $wpdb;
			foreach ( $default_mapping as $source_field => $target_data ) {
				/* Get sample value */
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$sample = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->usermeta}
						WHERE meta_key = %s
						AND meta_value IS NOT NULL
						AND meta_value != ''
						LIMIT 1",
						$source_field
					)
				);

				$target_field = is_array( $target_data ) ? $target_data['target'] : $target_data;

				/* Extract just the field name if it's a path */
				if ( is_string( $target_field ) && strpos( $target_field, '.' ) !== false ) {
					$parts = explode( '.', $target_field );
					$target_field = end( $parts );
				}

				$mappings[ $source_field ] = array(
					'target'      => $target_field,
					'auto_mapped' => true,
					'sample'      => $sample ? wp_trim_words( $sample, 5 ) : '',
				);
			}
		}

		/* For BuddyPress */
		if ( 'buddypress' === $plugin_slug ) {
			if ( ! class_exists( 'NBUF_Migration_BP_Profile' ) ) {
				wp_send_json_error( array( 'message' => __( 'BuddyPress migration class not found', 'nobloat-user-foundry' ) ) );
			}

			$preview = NBUF_Migration_BP_Profile::get_migration_preview( 1 );

			if ( ! empty( $preview ) ) {
				$first_user = $preview[0];

				/* Add mapped fields */
				if ( ! empty( $first_user['mapped_fields'] ) ) {
					foreach ( $first_user['mapped_fields'] as $field ) {
						$mappings[ $field['bp_name'] ] = array(
							'target'      => $field['nbuf_field'],
							'auto_mapped' => true,
							'sample'      => wp_trim_words( $field['bp_value'], 5 ),
						);
					}
				}

				/* Add unmapped fields */
				if ( ! empty( $first_user['unmapped_fields'] ) ) {
					foreach ( $first_user['unmapped_fields'] as $field ) {
						$mappings[ $field['bp_name'] ] = array(
							'target'      => '',
							'auto_mapped' => false,
							'sample'      => wp_trim_words( $field['bp_value'], 5 ),
						);
					}
				}
			}
		}

		wp_send_json_success( array( 'mappings' => $mappings ) );
	}

	/**
	 * AJAX: Get restrictions preview
	 *
	 * Returns preview of content restrictions to be migrated
	 */
	public static function ajax_get_restrictions_preview() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$restrictions = array();

		/* For Ultimate Member */
		if ( 'ultimate-member' === $plugin_slug ) {
			if ( class_exists( 'NBUF_Migration_UM_Restrictions' ) ) {
				$preview = NBUF_Migration_UM_Restrictions::get_migration_preview( 20 );

				foreach ( $preview as $item ) {
					$summary = '';
					if ( 'logged_out' === $item['nbuf_data']['visibility'] ) {
						$summary = __( 'Logged out users only', 'nobloat-user-foundry' );
					} elseif ( 'logged_in' === $item['nbuf_data']['visibility'] ) {
						$summary = __( 'Logged in users only', 'nobloat-user-foundry' );
					} elseif ( 'role_based' === $item['nbuf_data']['visibility'] ) {
						$roles = implode( ', ', $item['nbuf_data']['allowed_roles'] );
						/* translators: %s: comma-separated list of role names */
						$summary = sprintf( __( 'Roles: %s', 'nobloat-user-foundry' ), $roles );
					}

					$restrictions[] = array(
						'title'               => $item['title'],
						'post_type'           => $item['type'],
						'restriction_summary' => $summary,
					);
				}
			}
		}

		wp_send_json_success( array( 'restrictions' => $restrictions ) );
	}

	/**
	 * AJAX: Get roles preview
	 *
	 * Returns preview of roles to be migrated
	 */
	public static function ajax_get_roles_preview() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		$roles = array();

		/* For Ultimate Member */
		if ( 'ultimate-member' === $plugin_slug ) {
			if ( class_exists( 'NBUF_Migration_UM_Roles' ) ) {
				$roles = NBUF_Migration_UM_Roles::get_migration_preview( 20 );
			}
		}

		wp_send_json_success( array( 'roles' => $roles ) );
	}

	/**
	 * AJAX: Execute migration
	 *
	 * Executes selected migration types (profile_data, restrictions, roles)
	 */
	public static function ajax_execute_migration() {
		check_ajax_referer( 'nbuf_migration_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plugin slug', 'nobloat-user-foundry' ) ) );
		}

		/* Parse migration types */
		$migration_types = array();
		if ( isset( $_POST['migration_types'] ) && ! empty( $_POST['migration_types'] ) ) {
			$migration_types_json = wp_unslash( $_POST['migration_types'] );
			$migration_types = json_decode( $migration_types_json, true );

			if ( ! is_array( $migration_types ) ) {
				$migration_types = array();
			}
		}

		/* Parse field mappings */
		$field_mappings = array();
		if ( isset( $_POST['field_mappings'] ) && ! empty( $_POST['field_mappings'] ) ) {
			$field_mappings_json = wp_unslash( $_POST['field_mappings'] );
			$field_mappings = json_decode( $field_mappings_json, true );

			if ( ! is_array( $field_mappings ) ) {
				$field_mappings = array();
			}
		}

		$results = array();

		/* Execute profile data migration */
		if ( in_array( 'profile_data', $migration_types, true ) ) {
			if ( 'ultimate-member' === $plugin_slug ) {
				$mapper = self::get_mapper( $plugin_slug );
				if ( $mapper ) {
					/* Execute import for all users */
					$options = array(
						'send_emails'   => false,
						'set_verified'  => true,
						'skip_existing' => false, /* Update existing data */
						'batch_size'    => 9999,  /* Do all at once */
						'batch_offset'  => 0,
					);

					$results['profile_data'] = $mapper->batch_import( $options, $field_mappings );
				}
			} elseif ( 'buddypress' === $plugin_slug ) {
				if ( class_exists( 'NBUF_Migration_BP_Profile' ) ) {
					$options = array(
						'field_mapping_override' => $field_mappings,
					);

					$results['profile_data'] = NBUF_Migration_BP_Profile::migrate_profile_data( $options );
				}
			}
		}

		/* Execute restrictions migration */
		if ( in_array( 'restrictions', $migration_types, true ) ) {
			if ( 'ultimate-member' === $plugin_slug && class_exists( 'NBUF_Migration_UM_Restrictions' ) ) {
				$results['restrictions'] = NBUF_Migration_UM_Restrictions::migrate_restrictions();
			}
		}

		/* Execute roles migration */
		if ( in_array( 'roles', $migration_types, true ) ) {
			if ( 'ultimate-member' === $plugin_slug && class_exists( 'NBUF_Migration_UM_Roles' ) ) {
				$results['roles'] = NBUF_Migration_UM_Roles::migrate_roles();
			}
		}

		/* Log to history */
		foreach ( $results as $type => $data ) {
			self::log_import_history( $plugin_slug . '_' . $type, $data );
		}

		wp_send_json_success( $results );
	}
}
