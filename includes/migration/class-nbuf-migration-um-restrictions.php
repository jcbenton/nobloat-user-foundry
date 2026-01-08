<?php
/**
 * NoBloat User Foundry - Ultimate Member Restrictions Migration
 *
 * Migrates content restrictions from Ultimate Member to NoBloat User Foundry.
 * Handles conversion of UM's access control settings to NBUF restriction format.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/migration
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Migration_UM_Restrictions
 *
 * Migrates content restrictions from Ultimate Member to NBUF.
 */
class NBUF_Migration_UM_Restrictions {


	/**
	 * Migrate Ultimate Member content restrictions to NBUF
	 *
	 * @return array Migration results with counts and errors
	 */
	public static function migrate_restrictions() {
		global $wpdb;

		$results = array(
			'total'         => 0,
			'migrated'      => 0,
			'skipped'       => 0,
			'errors'        => array(),
			'menu_migrated' => 0,
		);

		/* Check if Ultimate Member is active */
		if ( ! class_exists( 'UM' ) ) {
			$results['errors'][] = __( 'Ultimate Member plugin is not active.', 'nobloat-user-foundry' );
			return $results;
		}

		/*
		 * UM stores restrictions in a serialized array under 'um_content_restriction' meta key.
		 * Find all posts with this meta key (including nav_menu_item for menu restrictions).
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			"SELECT DISTINCT p.ID, p.post_type, p.post_title
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE pm.meta_key = 'um_content_restriction'
			AND (p.post_status = 'publish' OR p.post_type = 'nav_menu_item')"
		);

		$results['total'] = count( $posts );

		$content_table = $wpdb->prefix . 'nbuf_content_restrictions';
		$menu_table    = $wpdb->prefix . 'nbuf_menu_restrictions';

		foreach ( $posts as $post ) {
			try {
				/* Get UM restriction data (serialized array) */
				$um_restriction = get_post_meta( $post->ID, 'um_content_restriction', true );

				/* Parse the serialized data */
				if ( empty( $um_restriction ) || ! is_array( $um_restriction ) ) {
					++$results['skipped'];
					continue;
				}

				/* Get accessible value from the array */
				$um_accessible = isset( $um_restriction['_um_accessible'] ) ? $um_restriction['_um_accessible'] : 0;

				/* If no restriction (accessible = 0 or empty), skip */
				if ( empty( $um_accessible ) || 0 === $um_accessible || '0' === $um_accessible ) {
					++$results['skipped'];
					continue;
				}

				/* Map UM data to NBUF format */
				$nbuf_restriction = self::map_um_to_nbuf( $post->ID );

				/* Handle menu items separately - they go to nbuf_menu_restrictions */
				if ( 'nav_menu_item' === $post->post_type ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->replace(
						$menu_table,
						array(
							'menu_item_id' => $post->ID,
							'visibility'   => $nbuf_restriction['visibility'],
							'allowed_roles' => wp_json_encode( $nbuf_restriction['allowed_roles'] ),
							'created_at'   => current_time( 'mysql' ),
							'updated_at'   => current_time( 'mysql' ),
						),
						array( '%d', '%s', '%s', '%s', '%s' )
					);

					++$results['menu_migrated'];
					++$results['migrated'];
					continue;
				}

				/* Regular content restrictions */
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->replace(
					$content_table,
					array(
						'content_id'         => $post->ID,
						'content_type'       => $post->post_type,
						'visibility'         => $nbuf_restriction['visibility'],
						'allowed_roles'      => wp_json_encode( $nbuf_restriction['allowed_roles'] ),
						'restriction_action' => $nbuf_restriction['restriction_action'],
						'custom_message'     => $nbuf_restriction['custom_message'],
						'redirect_url'       => $nbuf_restriction['redirect_url'],
						'created_at'         => current_time( 'mysql' ),
						'updated_at'         => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				++$results['migrated'];

			} catch ( Exception $e ) {
				$results['errors'][] = sprintf(
				/* translators: 1: Post ID, 2: Error message */
					__( 'Post ID %1$d: %2$s', 'nobloat-user-foundry' ),
					$post->ID,
					$e->getMessage()
				);
			}
		}

		/* Log migration to audit */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'restrictions',
				'um_restrictions_migrated',
				sprintf(
				/* translators: 1: Number of content restrictions, 2: Number of menu restrictions */
					__( 'Migrated %1$d content restrictions and %2$d menu restrictions from Ultimate Member', 'nobloat-user-foundry' ),
					$results['migrated'] - $results['menu_migrated'],
					$results['menu_migrated']
				),
				$results
			);
		}

		return $results;
	}

	/**
	 * Map Ultimate Member restriction data to NBUF format
	 *
	 * UM stores all restriction data in a serialized array under 'um_content_restriction' meta key.
	 *
	 * @param  int $post_id Post ID.
	 * @return array NBUF restriction data.
	 */
	private static function map_um_to_nbuf( $post_id ) {
		$nbuf = array(
			'visibility'         => 'everyone',
			'allowed_roles'      => array(),
			'restriction_action' => 'message',
			'custom_message'     => '',
			'redirect_url'       => '',
		);

		/* Get UM restriction data (serialized array) */
		$um_data = get_post_meta( $post_id, 'um_content_restriction', true );

		if ( empty( $um_data ) || ! is_array( $um_data ) ) {
			return $nbuf;
		}

		/* Extract values from the serialized array */
		$accessible             = isset( $um_data['_um_accessible'] ) ? $um_data['_um_accessible'] : 0;
		$access_roles           = isset( $um_data['_um_access_roles'] ) ? $um_data['_um_access_roles'] : array();
		$custom_message_enabled = isset( $um_data['_um_restrict_by_custom_message'] ) ? $um_data['_um_restrict_by_custom_message'] : 0;
		$custom_message         = isset( $um_data['_um_restrict_custom_message'] ) ? $um_data['_um_restrict_custom_message'] : '';
		$redirect_enabled       = isset( $um_data['_um_access_redirect'] ) ? $um_data['_um_access_redirect'] : 0;
		$redirect_url           = isset( $um_data['_um_access_redirect_url'] ) ? $um_data['_um_access_redirect_url'] : '';

		/*
		 * Map accessible field
		 * UM values: 0 = everyone, 1 = logged out, 2 = logged in
		 */
		switch ( $accessible ) {
			case 1:
			case '1':
				$nbuf['visibility'] = 'logged_out';
				break;

			case 2:
			case '2':
				/* Check if role-based */
				if ( ! empty( $access_roles ) && is_array( $access_roles ) ) {
					$nbuf['visibility'] = 'role_based';
					/* UM stores roles as array keys with value '1' */
					$nbuf['allowed_roles'] = array_keys( array_filter( $access_roles ) );
				} else {
					$nbuf['visibility'] = 'logged_in';
				}
				break;

			default:
				$nbuf['visibility'] = 'everyone';
				break;
		}

		/*
		 * Map action
		 * UM values: 0 = show message, 1 = redirect
		 * SECURITY: Validate redirect URLs are internal only to prevent open redirect vulnerabilities
		 */
		if ( ( 1 === $redirect_enabled || '1' === $redirect_enabled ) && ! empty( $redirect_url ) ) {
			/* Validate redirect URL is internal only - prevents open redirect attacks */
			$validated_url = wp_validate_redirect( $redirect_url, false );

			if ( false !== $validated_url ) {
				$nbuf['restriction_action'] = 'redirect';
				$nbuf['redirect_url']       = esc_url_raw( $validated_url );
			} else {
				/* Invalid/external URL blocked - log for security audit */
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging for blocked open redirect attempt.
					sprintf(
						'[NoBloat User Foundry] SECURITY: Blocked migration of external redirect URL: %s',
						$redirect_url
					)
				);
				/* Fall back to message instead */
				$nbuf['restriction_action'] = 'message';
			}
		} elseif ( ( 1 === $custom_message_enabled || '1' === $custom_message_enabled ) && ! empty( $custom_message ) ) {
			$nbuf['restriction_action'] = 'message';
			$nbuf['custom_message']     = wp_kses_post( $custom_message );
		} else {
			/* Default to message with no custom text */
			$nbuf['restriction_action'] = 'message';
		}

		return $nbuf;
	}

	/**
	 * Get migration preview (first N items)
	 *
	 * @param  int $limit Number of items to preview.
	 * @return array Preview data.
	 */
	public static function get_migration_preview( $limit = 10 ) {
		global $wpdb;

		/*
		 * UM stores restrictions in 'um_content_restriction' meta key as serialized array.
		 * Include nav_menu_item for menu restrictions.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_type
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = 'um_content_restriction'
				AND (p.post_status = 'publish' OR p.post_type = 'nav_menu_item')
				LIMIT %d",
				$limit
			)
		);

		$preview = array();

		foreach ( $posts as $post ) {
			/* Get UM restriction data */
			$um_data = get_post_meta( $post->ID, 'um_content_restriction', true );

			if ( empty( $um_data ) || ! is_array( $um_data ) ) {
				continue;
			}

			$accessible = isset( $um_data['_um_accessible'] ) ? $um_data['_um_accessible'] : 0;

			/* Skip unrestricted */
			if ( empty( $accessible ) || 0 === $accessible || '0' === $accessible ) {
				continue;
			}

			/* Map to NBUF */
			$nbuf_data = self::map_um_to_nbuf( $post->ID );

			/* For menu items, get a better title */
			$display_title = $post->post_title;
			if ( 'nav_menu_item' === $post->post_type ) {
				$menu_item_title = get_post_meta( $post->ID, '_menu_item_title', true );
				if ( ! empty( $menu_item_title ) ) {
					$display_title = $menu_item_title;
				} elseif ( empty( $display_title ) ) {
					$display_title = __( '(Menu Item)', 'nobloat-user-foundry' );
				}
			}

			$preview[] = array(
				'id'        => $post->ID,
				'title'     => $display_title,
				'type'      => $post->post_type,
				'um_data'   => array(
					'accessible' => $accessible,
					'roles'      => isset( $um_data['_um_access_roles'] ) ? $um_data['_um_access_roles'] : array(),
				),
				'nbuf_data' => $nbuf_data,
			);
		}

		return $preview;
	}

	/**
	 * Get count of posts with UM restrictions
	 *
	 * @return int Number of restricted posts
	 */
	public static function get_restriction_count() {
		global $wpdb;

		/*
		 * Count posts with um_content_restriction meta.
		 * We can't easily filter by _um_accessible value since it's serialized,
		 * so we count all posts with this meta (slight overcount is acceptable for preview).
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id)
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'um_content_restriction'"
		);

		return absint( $count );
	}

	/**
	 * Rollback migration (delete migrated restrictions)
	 *
	 * @return array Results with count of deleted restrictions
	 */
	public static function rollback_migration() {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_content_restrictions';

		/* Get count before deletion */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		/* Delete all restrictions */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration operation.
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		/* Log rollback */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				get_current_user_id(),
				'restrictions',
				'um_restrictions_rollback',
				sprintf(
				/* translators: %d: Number of restrictions deleted */
					__( 'Rolled back migration - deleted %d content restrictions', 'nobloat-user-foundry' ),
					$count
				),
				array( 'deleted_count' => $count )
			);
		}

		return array(
			'success' => true,
			'deleted' => absint( $count ),
		);
	}
}
