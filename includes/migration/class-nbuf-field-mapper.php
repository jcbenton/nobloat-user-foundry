<?php
/**
 * NoBloat User Foundry - Field Mapper Utility
 *
 * Handles field mapping logic for migrations. Provides intelligent
 * field matching, validation, and transformation capabilities.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field mapping utility class
 *
 * Manages field mappings between source plugins and NoBloat User Foundry.
 * Provides intelligent suggestions and validation.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 */
class NBUF_Field_Mapper {


	/**
	 * Field mappings (source_field => target_field)
	 *
	 * @var array
	 */
	private $mappings = array();

	/**
	 * Field transformations (source_field => callback)
	 *
	 * @var array
	 */
	private $transformations = array();

	/**
	 * Unmapped fields (fields with no mapping)
	 *
	 * @var array
	 */
	private $unmapped = array();

	/**
	 * Constructor
	 *
	 * @param array $mappings Initial field mappings.
	 */
	public function __construct( $mappings = array() ) {
		$this->set_mappings( $mappings );
	}

	/**
	 * Set field mappings
	 *
	 * @param array $mappings Field mappings array.
	 */
	public function set_mappings( $mappings ) {
		$this->mappings = array();

		foreach ( $mappings as $source_field => $mapping ) {
			if ( is_string( $mapping ) ) {
				/* Simple mapping: source => target */
				$this->mappings[ $source_field ] = array(
					'target'    => $mapping,
					'transform' => null,
				);
			} elseif ( is_array( $mapping ) ) {
				/* Complex mapping with transformation */
				$this->mappings[ $source_field ] = array(
					'target'    => isset( $mapping['target'] ) ? $mapping['target'] : null,
					'transform' => isset( $mapping['transform'] ) ? $mapping['transform'] : null,
					'priority'  => isset( $mapping['priority'] ) ? $mapping['priority'] : 1,
				);
			}
		}
	}

	/**
	 * Add single field mapping
	 *
	 * @param string $source_field Source field name.
	 * @param string $target_field Target field name.
	 * @param string $transform    Optional transformation function.
	 */
	public function add_mapping( $source_field, $target_field, $transform = null ) {
		$this->mappings[ $source_field ] = array(
			'target'    => $target_field,
			'transform' => $transform,
			'priority'  => 1,
		);
	}

	/**
	 * Get target field for source field
	 *
	 * @param  string $source_field Source field name.
	 * @return string|null Target field name or null if not mapped
	 */
	public function get_target_field( $source_field ) {
		return isset( $this->mappings[ $source_field ]['target'] ) ? $this->mappings[ $source_field ]['target'] : null;
	}

	/**
	 * Map field value from source to target
	 *
	 * Applies any transformations and returns the mapped value.
	 *
	 * @param  string $source_field Source field name.
	 * @param  mixed  $source_value Source field value.
	 * @return array Array with 'target' and 'value' keys, or null if not mapped
	 */
	public function map_field( $source_field, $source_value ) {
		if ( ! isset( $this->mappings[ $source_field ] ) ) {
			$this->unmapped[] = $source_field;
			return null;
		}

		$mapping      = $this->mappings[ $source_field ];
		$target_field = $mapping['target'];
		$value        = $source_value;

		/* Apply transformation if specified */
		if ( ! empty( $mapping['transform'] ) ) {
			$value = $this->apply_transformation( $mapping['transform'], $value );
		}

		return array(
			'target' => $target_field,
			'value'  => $value,
		);
	}

	/**
	 * Apply transformation to value
	 *
	 * @param  string $transform_name Transformation name/callback.
	 * @param  mixed  $value          Value to transform.
	 * @return mixed Transformed value
	 */
	private function apply_transformation( $transform_name, $value ) {
		switch ( $transform_name ) {
			case 'sanitize_text':
				return sanitize_text_field( $value );

			case 'sanitize_textarea':
				return sanitize_textarea_field( $value );

			case 'sanitize_email':
				return sanitize_email( $value );

			case 'sanitize_url':
				return esc_url_raw( $value );

			case 'um_account_status_to_verified':
				/* Transform UM account_status to our verification flag */
				return ( 'approved' === $value ) ? 1 : 0;

			case 'um_last_login_to_audit':
				/* Convert timestamp to datetime */
				return ! empty( $value ) ? gmdate( 'Y-m-d H:i:s', $value ) : null;

			default:
				/* Check if it's a callable function */
				if ( is_callable( $transform_name ) ) {
					return call_user_func( $transform_name, $value );
				}
				return $value;
		}
	}

	/**
	 * Get unmapped fields
	 *
	 * Returns list of source fields that don't have mappings.
	 *
	 * @param  array $source_fields All source field names.
	 * @return array Unmapped field names
	 */
	public function get_unmapped_fields( $source_fields ) {
		$unmapped = array();

		foreach ( $source_fields as $field ) {
			if ( ! isset( $this->mappings[ $field ] ) ) {
				$unmapped[] = $field;
			}
		}

		return $unmapped;
	}

	/**
	 * Suggest mapping for source field
	 *
	 * Uses fuzzy matching to suggest the best target field.
	 *
	 * @param  string $source_field Source field name.
	 * @return array Suggested mappings with confidence scores
	 */
	public function suggest_mapping( $source_field ) {
		$suggestions      = array();
		$available_fields = $this->get_available_target_fields();

		/* Normalize source field name for comparison */
		$source_normalized = $this->normalize_field_name( $source_field );

		foreach ( $available_fields as $target_field => $label ) {
			$target_normalized = $this->normalize_field_name( $target_field );

			/* Calculate similarity */
			$similarity = 0;

			/* Exact match */
			if ( $source_normalized === $target_normalized ) {
				$similarity = 100;
			} else {
				/* Partial match using levenshtein distance */
				$distance   = levenshtein( $source_normalized, $target_normalized );
				$max_len    = max( strlen( $source_normalized ), strlen( $target_normalized ) );
				$similarity = (int) ( ( 1 - ( $distance / $max_len ) ) * 100 );

				/* Boost similarity if source contains target or vice versa */
				if ( false !== strpos( $source_normalized, $target_normalized ) || false !== strpos( $target_normalized, $source_normalized ) ) {
					$similarity += 20;
				}
			}

			/* Add to suggestions if similarity is decent */
			if ( $similarity > 50 ) {
				$suggestions[] = array(
					'target'     => $target_field,
					'label'      => $label,
					'confidence' => min( 100, $similarity ),
				);
			}
		}

		/* Sort by confidence (highest first) */
		usort(
			$suggestions,
			function ( $a, $b ) {
				return $b['confidence'] - $a['confidence'];
			}
		);

		return array_slice( $suggestions, 0, 5 ); /* Top 5 suggestions */
	}

	/**
	 * Normalize field name for comparison
	 *
	 * @param  string $field_name Field name.
	 * @return string Normalized name
	 */
	private function normalize_field_name( $field_name ) {
		/* Remove common prefixes/suffixes */
		$name = preg_replace( '/^(user_|um_|_um_|custom_)/', '', $field_name );
		$name = preg_replace( '/(_field|_value|_data)$/', '', $name );

		/* Replace underscores/hyphens with spaces */
		$name = str_replace( array( '_', '-' ), ' ', $name );

		/* Remove extra spaces and convert to lowercase */
		$name = strtolower( trim( preg_replace( '/\s+/', ' ', $name ) ) );

		return $name;
	}

	/**
	 * Get available target fields
	 *
	 * Returns all NoBloat profile fields that can be mapped to.
	 *
	 * @return array Field keys => labels
	 */
	private function get_available_target_fields() {
		$registry = NBUF_Profile_Data::get_field_registry();
		$fields   = array();

		foreach ( $registry as $category ) {
			$fields = array_merge( $fields, $category['fields'] );
		}

		return $fields;
	}

	/**
	 * Save mapping preset
	 *
	 * Saves current mappings as a reusable preset.
	 *
	 * @param  string $preset_name Preset name.
	 * @param  string $plugin_slug Source plugin slug.
	 * @return bool Success
	 */
	public function save_mapping_preset( $preset_name, $plugin_slug ) {
		$presets = get_option( 'nbuf_field_mapping_presets', array() );

		$presets[ $plugin_slug ][ $preset_name ] = array(
			'name'     => $preset_name,
			'mappings' => $this->mappings,
			'created'  => current_time( 'mysql' ),
		);

		return update_option( 'nbuf_field_mapping_presets', $presets );
	}

	/**
	 * Load mapping preset
	 *
	 * @param  string $preset_name Preset name.
	 * @param  string $plugin_slug Source plugin slug.
	 * @return bool Success
	 */
	public function load_mapping_preset( $preset_name, $plugin_slug ) {
		$presets = get_option( 'nbuf_field_mapping_presets', array() );

		if ( isset( $presets[ $plugin_slug ][ $preset_name ] ) ) {
			$this->mappings = $presets[ $plugin_slug ][ $preset_name ]['mappings'];
			return true;
		}

		return false;
	}

	/**
	 * Get saved presets for plugin
	 *
	 * @param  string $plugin_slug Source plugin slug.
	 * @return array Saved presets
	 */
	public function get_saved_presets( $plugin_slug ) {
		$presets = get_option( 'nbuf_field_mapping_presets', array() );

		return isset( $presets[ $plugin_slug ] ) ? $presets[ $plugin_slug ] : array();
	}

	/**
	 * Get current mappings
	 *
	 * @return array Field mappings
	 */
	public function get_mappings() {
		return $this->mappings;
	}
}
