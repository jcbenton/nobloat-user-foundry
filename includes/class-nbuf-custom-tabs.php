<?php
/**
 * Custom Account Page Tabs
 *
 * Allows admins to add custom tabs to the frontend account page
 * with shortcode content and role-based visibility.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Custom_Tabs
 *
 * Handles CRUD operations and frontend rendering for custom account tabs.
 */
class NBUF_Custom_Tabs {

	/**
	 * Option name for storing custom tabs.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'nbuf_custom_account_tabs';

	/**
	 * Reserved slugs that cannot be used for custom tabs.
	 *
	 * @var array
	 */
	const RESERVED_SLUGS = array(
		'account',
		'main',
		'profile',
		'photos',
		'email',
		'security',
		'history',
		'policies',
		'login',
		'register',
		'verify',
		'forgot-password',
		'reset-password',
		'logout',
		'2fa',
		'2fa-setup',
		'members',
	);

	/**
	 * Get all custom tabs sorted by priority.
	 *
	 * @since  1.5.0
	 * @return array Array of custom tab configurations.
	 */
	public static function get_all(): array {
		$tabs = NBUF_Options::get( self::OPTION_NAME, array() );

		if ( ! is_array( $tabs ) ) {
			return array();
		}

		/* Sort by priority (lower = earlier) */
		usort(
			$tabs,
			function ( $a, $b ) {
				$priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : 50;
				$priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : 50;
				return $priority_a <=> $priority_b;
			}
		);

		return $tabs;
	}

	/**
	 * Get a single custom tab by ID.
	 *
	 * @since  1.5.0
	 * @param  string $id Tab ID.
	 * @return array|null Tab configuration or null if not found.
	 */
	public static function get( string $id ): ?array {
		$tabs = self::get_all();

		foreach ( $tabs as $tab ) {
			if ( isset( $tab['id'] ) && $tab['id'] === $id ) {
				return $tab;
			}
		}

		return null;
	}

	/**
	 * Get custom tabs visible to a specific user.
	 *
	 * Filters tabs based on enabled status and role restrictions.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return array Array of visible tab configurations.
	 */
	public static function get_for_user( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$all_tabs = self::get_all();
		$visible  = array();

		foreach ( $all_tabs as $tab ) {
			/* Skip disabled tabs */
			if ( empty( $tab['enabled'] ) ) {
				continue;
			}

			/* Empty roles = visible to all logged-in users */
			if ( empty( $tab['roles'] ) || ! is_array( $tab['roles'] ) ) {
				$visible[] = $tab;
				continue;
			}

			/* Check if user has any of the allowed roles */
			if ( ! empty( array_intersect( $user->roles, $tab['roles'] ) ) ) {
				$visible[] = $tab;
			}
		}

		return $visible;
	}

	/**
	 * Validate a tab slug.
	 *
	 * Checks format, reserved slugs, and uniqueness.
	 *
	 * @since  1.5.0
	 * @param  string      $slug       Slug to validate.
	 * @param  string|null $exclude_id Tab ID to exclude from uniqueness check (for updates).
	 * @return array Array of error messages, empty if valid.
	 */
	public static function validate_slug( string $slug, ?string $exclude_id = null ): array {
		$errors = array();

		/* Check format: lowercase letters, numbers, and hyphens only */
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug ) ) {
			$errors[] = __( 'Slug must contain only lowercase letters, numbers, and hyphens. Cannot start or end with a hyphen.', 'nobloat-user-foundry' );
		}

		/* Check reserved slugs */
		if ( in_array( $slug, self::RESERVED_SLUGS, true ) ) {
			$errors[] = __( 'This slug is reserved and cannot be used.', 'nobloat-user-foundry' );
		}

		/* Check uniqueness among existing tabs */
		$existing = self::get_all();
		foreach ( $existing as $tab ) {
			if ( isset( $tab['slug'] ) && $tab['slug'] === $slug ) {
				if ( null === $exclude_id || $tab['id'] !== $exclude_id ) {
					$errors[] = __( 'This slug is already in use by another custom tab.', 'nobloat-user-foundry' );
					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Create a new custom tab.
	 *
	 * @since  1.5.0
	 * @param  array $data Tab data.
	 * @return array The created tab configuration.
	 */
	public static function create( array $data ): array {
		$tabs = self::get_all();

		$new_tab       = self::sanitize_tab_data( $data );
		$new_tab['id'] = 'tab_' . uniqid();

		$tabs[] = $new_tab;
		NBUF_Options::update( self::OPTION_NAME, $tabs );

		return $new_tab;
	}

	/**
	 * Update an existing custom tab.
	 *
	 * @since  1.5.0
	 * @param  string $id   Tab ID.
	 * @param  array  $data Updated tab data.
	 * @return bool True if updated, false if tab not found.
	 */
	public static function update( string $id, array $data ): bool {
		$tabs = self::get_all();

		foreach ( $tabs as $i => $tab ) {
			if ( isset( $tab['id'] ) && $tab['id'] === $id ) {
				$updated_tab       = self::sanitize_tab_data( $data );
				$updated_tab['id'] = $id; /* Preserve original ID */
				$tabs[ $i ]        = $updated_tab;

				NBUF_Options::update( self::OPTION_NAME, $tabs );
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a custom tab.
	 *
	 * @since  1.5.0
	 * @param  string $id Tab ID.
	 * @return bool True if deleted, false if tab not found.
	 */
	public static function delete( string $id ): bool {
		$tabs           = self::get_all();
		$original_count = count( $tabs );

		$tabs = array_filter(
			$tabs,
			function ( $tab ) use ( $id ) {
				return ! isset( $tab['id'] ) || $tab['id'] !== $id;
			}
		);

		if ( count( $tabs ) < $original_count ) {
			NBUF_Options::update( self::OPTION_NAME, array_values( $tabs ) );
			return true;
		}

		return false;
	}

	/**
	 * Reorder tabs based on an array of IDs.
	 *
	 * @since 1.5.0
	 * @param array $ordered_ids Array of tab IDs in desired order.
	 */
	public static function reorder( array $ordered_ids ): void {
		$tabs = self::get_all();

		/* Index tabs by ID */
		$indexed = array();
		foreach ( $tabs as $tab ) {
			if ( isset( $tab['id'] ) ) {
				$indexed[ $tab['id'] ] = $tab;
			}
		}

		/* Build reordered array with new priorities */
		$reordered = array();
		$priority  = 10;

		foreach ( $ordered_ids as $id ) {
			$id = sanitize_text_field( $id );
			if ( isset( $indexed[ $id ] ) ) {
				$indexed[ $id ]['priority'] = $priority;
				$reordered[]                = $indexed[ $id ];
				$priority                  += 10;
				unset( $indexed[ $id ] );
			}
		}

		/* Append any remaining tabs not in the order array */
		foreach ( $indexed as $tab ) {
			$tab['priority'] = $priority;
			$reordered[]     = $tab;
			$priority       += 10;
		}

		NBUF_Options::update( self::OPTION_NAME, $reordered );
	}

	/**
	 * Sanitize tab data.
	 *
	 * @since  1.5.0
	 * @param  array $data Raw tab data.
	 * @return array Sanitized tab data.
	 */
	private static function sanitize_tab_data( array $data ): array {
		/* Validate roles against actual WordPress roles */
		$valid_roles     = array_keys( wp_roles()->get_names() );
		$submitted_roles = isset( $data['roles'] ) && is_array( $data['roles'] ) ? $data['roles'] : array();
		$sanitized_roles = array_values( array_intersect( $submitted_roles, $valid_roles ) );

		return array(
			'name'     => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'slug'     => isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '',
			'content'  => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
			'roles'    => $sanitized_roles,
			'icon'     => isset( $data['icon'] ) ? sanitize_html_class( $data['icon'] ) : '',
			'priority' => isset( $data['priority'] ) ? absint( $data['priority'] ) : 50,
			'enabled'  => ! empty( $data['enabled'] ),
		);
	}

	/**
	 * Get reserved slugs.
	 *
	 * @since  1.5.0
	 * @return array Array of reserved slug strings.
	 */
	public static function get_reserved_slugs(): array {
		return self::RESERVED_SLUGS;
	}
}
