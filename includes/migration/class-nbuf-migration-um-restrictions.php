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
			'total'    => 0,
			'migrated' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		/* Check if Ultimate Member is active */
		if ( ! class_exists( 'UM' ) ) {
			$results['errors'][] = __( 'Ultimate Member plugin is not active.', 'nobloat-user-foundry' );
			return $results;
		}

		/* Get all posts with UM restrictions */
		$um_meta_keys = array(
			'_um_accessible',           // Main restriction setting.
			'_um_access_roles',         // Role-based access.
			'_um_noaccess_action',      // What happens when access denied.
			'_um_restrict_by_custom_message', // Custom message option.
			'_um_restrict_custom_message',    // Custom message content.
			'_um_access_redirect',      // Redirect option.
			'_um_access_redirect_url',  // Redirect URL.
		);

		/* Find all posts with UM restriction meta */
		$placeholders = implode( ',', array_fill( 0, count( $um_meta_keys ), '%s' ) );

     // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
     // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN clause with variable placeholders
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_type, p.post_title
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key IN ($placeholders)
				AND p.post_status = 'publish'",
				...$um_meta_keys
			)
		);
     // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$results['total'] = count( $posts );

		foreach ( $posts as $post ) {
			try {
				/* Get all UM meta for this post */
				$um_accessible = get_post_meta( $post->ID, '_um_accessible', true );

				/* If no restriction (accessible = 0 or empty), skip */
				if ( empty( $um_accessible ) || '0' === $um_accessible ) {
					++$results['skipped'];
					continue;
				}

				/* Map UM data to NBUF format */
				$nbuf_restriction = self::map_um_to_nbuf( $post->ID );

				/* Insert into NBUF table */
				$table = $wpdb->prefix . 'nbuf_content_restrictions';

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->replace(
					$table,
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
				/* translators: %d: Number of restrictions migrated */
					__( 'Migrated %d content restrictions from Ultimate Member', 'nobloat-user-foundry' ),
					$results['migrated']
				),
				$results
			);
		}

		return $results;
	}

	/**
	 * Map Ultimate Member restriction data to NBUF format
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

		/* Get UM meta */
		$accessible             = get_post_meta( $post_id, '_um_accessible', true );
		$access_roles           = get_post_meta( $post_id, '_um_access_roles', true );
		$noaccess_action        = get_post_meta( $post_id, '_um_noaccess_action', true );
		$custom_message_enabled = get_post_meta( $post_id, '_um_restrict_by_custom_message', true );
		$custom_message         = get_post_meta( $post_id, '_um_restrict_custom_message', true );
		$redirect_enabled       = get_post_meta( $post_id, '_um_access_redirect', true );
		$redirect_url           = get_post_meta( $post_id, '_um_access_redirect_url', true );

		/*
		Map accessible field
		* UM values: 0 = everyone, 1 = logged out, 2 = logged in
		*/
		switch ( $accessible ) {
			case '1':
				$nbuf['visibility'] = 'logged_out';
				break;

			case '2':
				/* Check if role-based */
				if ( ! empty( $access_roles ) && is_array( $access_roles ) ) {
					$nbuf['visibility'] = 'role_based';
					/* UM stores roles as array keys with value 1 */
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
		if ( '1' === $redirect_enabled && ! empty( $redirect_url ) ) {
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
		} elseif ( '1' === $custom_message_enabled && ! empty( $custom_message ) ) {
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

		$um_meta_keys = array(
			'_um_accessible',
			'_um_access_roles',
			'_um_noaccess_action',
		);

		$placeholders = implode( ',', array_fill( 0, count( $um_meta_keys ), '%s' ) );

     // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_type
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key IN ($placeholders)
				AND p.post_status = 'publish'
				LIMIT %d",
				array_merge( $um_meta_keys, array( $limit ) )
			)
		);

		$preview = array();

		foreach ( $posts as $post ) {
			/* Get UM data */
			$accessible = get_post_meta( $post->ID, '_um_accessible', true );

			/* Skip unrestricted */
			if ( empty( $accessible ) || '0' === $accessible ) {
				continue;
			}

			/* Map to NBUF */
			$nbuf_data = self::map_um_to_nbuf( $post->ID );

			$preview[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'type'      => $post->post_type,
				'um_data'   => array(
					'accessible' => $accessible,
					'roles'      => get_post_meta( $post->ID, '_um_access_roles', true ),
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

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id)
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_um_accessible'
			AND meta_value IN ('1', '2')"
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

		/*
		* Get count before deletion
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		/*
		* Delete all restrictions
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

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
