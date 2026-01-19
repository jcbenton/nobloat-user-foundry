<?php
/**
 * NoBloat User Foundry - Abstract Migration Plugin
 *
 * Abstract base class for all plugin-specific migration mappers.
 * Defines the interface that all migration plugins must implement.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class for migration plugin handlers
 *
 * Each source plugin (Ultimate Member, BuddyPress, etc.) extends this class
 * and implements plugin-specific logic for field discovery and data import.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 */
abstract class NBUF_Abstract_Migration_Plugin {


	/**
	 * Get plugin display name
	 *
	 * @return string Plugin name (e.g., "Ultimate Member")
	 */
	abstract public function get_name();

	/**
	 * Get plugin slug
	 *
	 * @return string Plugin slug (e.g., "ultimate-member")
	 */
	abstract public function get_slug();

	/**
	 * Get plugin file path for activation check
	 *
	 * @return string Plugin file path relative to plugins directory
	 */
	abstract public function get_plugin_file();

	/**
	 * Check if plugin is active
	 *
	 * @return bool True if plugin is installed and active
	 */
	public function is_plugin_active() {
		return is_plugin_active( $this->get_plugin_file() );
	}

	/**
	 * Get count of users with plugin data
	 *
	 * @return int Number of users to migrate
	 */
	abstract public function get_user_count();

	/**
	 * Get default field mapping (auto-mapped fields)
	 *
	 * Returns array of source field → target field mappings.
	 * These are automatically mapped without user intervention.
	 *
	 * @return array<string, string|array{target?: string, transform?: string}> Field mappings
	 */
	abstract public function get_default_field_mapping();

	/**
	 * Discover custom fields from source plugin
	 *
	 * Scans the source plugin's data structures to find custom/unknown
	 * fields that are not in the default mapping. Returns fields with
	 * sample values for manual mapping UI.
	 *
	 * @return array<string, array<string, mixed>> Custom fields with sample data
	 */
	abstract public function discover_custom_fields();

	/**
	 * Get preview data for first N users
	 *
	 * @param  int                                                              $limit         Number of users to preview.
	 * @param  array<string, string|array{target?: string, transform?: string}> $field_mapping Optional custom field mapping.
	 * @return array<int, array<string, mixed>> Preview data
	 */
	abstract public function preview_import( $limit = 10, $field_mapping = array() );

	/**
	 * Import single user data
	 *
	 * @param  int                                                              $user_id       User ID to import.
	 * @param  array<string, mixed>                                             $options       Import options.
	 * @param  array<string, string|array{target?: string, transform?: string}> $field_mapping Optional custom field mapping.
	 * @return bool Success
	 */
	abstract public function import_user( $user_id, $options = array(), $field_mapping = array() );

	/**
	 * Batch import users
	 *
	 * @param  array<string, mixed>                                             $options       Import options.
	 * @param  array<string, string|array{target?: string, transform?: string}> $field_mapping Optional custom field mapping.
	 * @return array{success: bool, total: int, imported: int, skipped: int, errors: array<int, string>, batch_complete: bool} Import results
	 */
	public function batch_import( array $options = array(), array $field_mapping = array() ): array {
		global $wpdb;

		$defaults = array(
			'send_emails'   => false,
			'set_verified'  => true,
			'skip_existing' => true,
			'batch_size'    => 50,
			'batch_offset'  => 0,
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'success'        => true,
			'total'          => 0,
			'imported'       => 0,
			'skipped'        => 0,
			'errors'         => array(),
			'batch_complete' => false,
		);

		/* Get users to import */
		$user_ids = $this->get_user_ids_for_batch( $options['batch_size'], $options['batch_offset'] );

		/* Check if batch is complete */
		$results['batch_complete'] = count( $user_ids ) < $options['batch_size'];

		/* Import each user */
		foreach ( $user_ids as $user_id ) {
			++$results['total'];

			try {
				/* Check if already imported */
				if ( $options['skip_existing'] ) {
					$existing = NBUF_User_Data::get( $user_id );
					if ( $existing && $existing->is_verified ) {
						++$results['skipped'];
						continue;
					}
				}

				/* Import user */
				$imported = $this->import_user( $user_id, $options, $field_mapping );

				if ( $imported ) {
					++$results['imported'];
				} else {
					++$results['skipped'];
				}
			} catch ( Exception $e ) {
				$results['errors'][] = sprintf(
				/* translators: %1$d: User ID, %2$s: Error message */
					__( 'User ID %1$d: %2$s', 'nobloat-user-foundry' ),
					$user_id,
					$e->getMessage()
				);
			}
		}

		return $results;
	}

	/**
	 * Get user IDs for batch import
	 *
	 * Override this method in child classes for plugin-specific user discovery.
	 *
	 * @param  int $limit  Batch size.
	 * @param  int $offset Batch offset.
	 * @return array<int> User IDs
	 */
	abstract protected function get_user_ids_for_batch( $limit, $offset );

	/**
	 * Sanitize field value based on type
	 *
	 * Common helper method for sanitizing imported data.
	 *
	 * @param  mixed  $value Field value.
	 * @param  string $type  Field type (text, email, url, textarea, date, etc.).
	 * @return mixed Sanitized value
	 */
	protected function sanitize_field( $value, $type = 'text' ) {
		if ( empty( $value ) ) {
			return null;
		}

		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'date':
				/* Validate date format */
				$timestamp = strtotime( $value );
				return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : null;

			case 'datetime':
				$timestamp = strtotime( $value );
				return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;

			case 'int':
				return absint( $value );

			case 'float':
				return (float) $value;

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Get user metadata in batch for performance
	 *
	 * @param  array<int>    $user_ids  User IDs.
	 * @param  array<string> $meta_keys Meta keys to fetch.
	 * @return array<int, array<string, string>> User metadata keyed by user_id => meta_key => meta_value
	 */
	protected function get_user_meta_batch( array $user_ids, array $meta_keys = array() ): array {
		global $wpdb;

		if ( empty( $user_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$where_meta   = '';
		$query_params = array( $wpdb->usermeta );

		if ( ! empty( $meta_keys ) ) {
			$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$where_meta       = " AND meta_key IN ($key_placeholders)";
			$query_params     = array_merge( $query_params, $user_ids, $meta_keys );
		} else {
			$query_params = array_merge( $query_params, $user_ids );
		}

     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic placeholders for IN clause.
		$query = $wpdb->prepare(
			"SELECT user_id, meta_key, meta_value
			FROM %i
			WHERE user_id IN ($placeholders)
			{$where_meta}",
			...$query_params
		);
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above, user_ids and meta_keys are validated arrays.
		$results = $wpdb->get_results( $query );

		/* Organize by user_id => meta_key => meta_value */
		$metadata = array();
		foreach ( $results as $row ) {
			if ( ! isset( $metadata[ $row->user_id ] ) ) {
				$metadata[ $row->user_id ] = array();
			}
			$metadata[ $row->user_id ][ $row->meta_key ] = $row->meta_value;
		}

		return $metadata;
	}
}
