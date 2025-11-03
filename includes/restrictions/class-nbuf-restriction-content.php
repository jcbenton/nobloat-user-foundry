<?php
/**
 * NoBloat User Foundry - Content Restrictions
 *
 * Handles post/page access restrictions based on login status and user roles.
 * Filters content and handles restriction actions (message, redirect, 404).
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restriction_Content
 *
 * Handles post/page access restrictions.
 */
class NBUF_Restriction_Content extends Abstract_NBUF_Restriction {


	/**
	 * Initialize content restrictions
	 */
	public static function init() {
		/* Content filtering (high priority to run late) */
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999999 );

		/* Template redirect for redirect and 404 actions */
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );

		/* Optionally hide from queries */
		$hide_from_queries = NBUF_Options::get( 'nbuf_restrictions_hide_from_queries', false );
		if ( $hide_from_queries ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_queries' ) );
		}
	}

	/**
	 * Filter post/page content based on restrictions
	 *
	 * @param  string $content Post content.
	 * @return string Filtered content.
	 */
	public static function filter_content( $content ) {
		/* Only filter singular posts/pages */
		if ( ! is_singular() ) {
			return $content;
		}

		global $post;
		if ( ! $post ) {
			return $content;
		}

		/* Get restriction */
		$restriction = NBUF_Restrictions::get_content_restriction( $post->ID, $post->post_type );

		/* No restriction = allow access */
		if ( ! $restriction ) {
			return $content;
		}

		/* Check access */
		$has_access = self::check_access(
			$restriction['visibility'],
			$restriction['allowed_roles']
		);

		/* Access granted */
		if ( $has_access ) {
			return $content;
		}

		/* Access denied - handle based on restriction_action */
		switch ( $restriction['restriction_action'] ) {
			case 'message':
				/* Show custom message or default */
				$message = ! empty( $restriction['custom_message'] )
				? $restriction['custom_message']
				: __( 'This content is restricted. Please log in to view.', 'nobloat-user-foundry' );

				/* Log access denial */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						get_current_user_id(),
						'restrictions',
						'access_denied_message',
						sprintf(
						/* translators: %s: Post title */
							__( 'Access denied to "%s" - message shown', 'nobloat-user-foundry' ),
							$post->post_title
						),
						array(
							'content_id'   => $post->ID,
							'content_type' => $post->post_type,
							'visibility'   => $restriction['visibility'],
						)
					);
				}

				/* Return message (wpautop for formatting) */
				return '<div class="nbuf-restricted-content">' . wpautop( wp_kses_post( $message ) ) . '</div>';

			case 'redirect':
			case '404':
				/*
				* These are handled in template_redirect hook
				*/
				/* But if we get here, show a message as fallback */
				return '<div class="nbuf-restricted-content">' . wpautop( esc_html__( 'This content is restricted.', 'nobloat-user-foundry' ) ) . '</div>';

			default:
				/* Unknown action = deny access with generic message */
				return '<div class="nbuf-restricted-content">' . wpautop( esc_html__( 'This content is restricted.', 'nobloat-user-foundry' ) ) . '</div>';
		}
	}

	/**
	 * Handle redirect and 404 actions
	 */
	public static function handle_redirect() {
		/* Only on singular posts/pages */
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		/* Get restriction */
		$restriction = NBUF_Restrictions::get_content_restriction( $post->ID, $post->post_type );

		/* No restriction = allow access */
		if ( ! $restriction ) {
			return;
		}

		/* Check access */
		$has_access = self::check_access(
			$restriction['visibility'],
			$restriction['allowed_roles']
		);

		/* Access granted */
		if ( $has_access ) {
			return;
		}

		/* Handle action */
		switch ( $restriction['restriction_action'] ) {
			case 'redirect':
				/* Get redirect URL */
				$url = ! empty( $restriction['redirect_url'] )
				? esc_url( $restriction['redirect_url'] )
				: wp_login_url( get_permalink( $post->ID ) );

				/* Log access denial */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						get_current_user_id(),
						'restrictions',
						'access_denied_redirect',
						sprintf(
						/* translators: 1: Post title, 2: Redirect URL */
							__( 'Access denied to "%1$s" - redirected to %2$s', 'nobloat-user-foundry' ),
							$post->post_title,
							$url
						),
						array(
							'content_id'   => $post->ID,
							'content_type' => $post->post_type,
							'visibility'   => $restriction['visibility'],
							'redirect_url' => $url,
						)
					);
				}

				/* Redirect and exit */
				wp_safe_redirect( $url );
				exit;

			case '404':
				/* Log access denial */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						get_current_user_id(),
						'restrictions',
						'access_denied_404',
						sprintf(
						/* translators: %s: Post title */
							__( 'Access denied to "%s" - 404 shown', 'nobloat-user-foundry' ),
							$post->post_title
						),
						array(
							'content_id'   => $post->ID,
							'content_type' => $post->post_type,
							'visibility'   => $restriction['visibility'],
						)
					);
				}

				/* Set 404 */
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();

				/* Load 404 template */
				include get_query_template( '404' );
				exit;
		}
	}

	/**
	 * Exclude restricted posts from queries (optional feature)
	 *
	 * @param WP_Query $query WordPress query object.
	 */
	public static function exclude_from_queries( $query ) {
		/* Skip for admin, singular, and non-main queries */
		if ( is_admin() || $query->is_singular || ! $query->is_main_query() ) {
			return;
		}

		/* Get post type being queried */
		$post_type = $query->get( 'post_type' );
		if ( empty( $post_type ) ) {
			$post_type = 'post'; // Default.
		}

		/* Get excluded post IDs */
		$excluded_ids = self::get_excluded_post_ids( $post_type );

		if ( ! empty( $excluded_ids ) ) {
			/* Merge with existing post__not_in */
			$existing = $query->get( 'post__not_in' );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			$query->set( 'post__not_in', array_merge( $existing, $excluded_ids ) );
		}
	}

	/**
	 * Get list of post IDs to exclude from queries
	 *
	 * @param  string $post_type Post type to check.
	 * @return array Array of post IDs to exclude.
	 */
	private static function get_excluded_post_ids( $post_type ) {
		/* Try to get from cache */
		$cache_key = 'nbuf_excluded_posts_' . $post_type . '_' . ( is_user_logged_in() ? get_current_user_id() : 'guest' );
		$cached    = wp_cache_get( $cache_key, 'nbuf_restrictions' );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_content_restrictions';

		/*
		* Get all restrictions for this post type
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$restrictions = $wpdb->get_results(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT content_id, visibility, allowed_roles FROM {$table} WHERE content_type = %s",
				$post_type
			)
		);

		$excluded = array();

		foreach ( $restrictions as $restriction ) {
			/* Parse allowed_roles */
			$allowed_roles = array();
			if ( ! empty( $restriction->allowed_roles ) ) {
				$allowed_roles = json_decode( $restriction->allowed_roles, true );
				if ( ! is_array( $allowed_roles ) ) {
					$allowed_roles = array();
				}
			}

			/* Check if current user has access */
			$has_access = self::check_access( $restriction->visibility, $allowed_roles );

			/* If no access, add to excluded list */
			if ( ! $has_access ) {
				$excluded[] = (int) $restriction->content_id;
			}
		}

		/* Cache for 5 minutes */
		wp_cache_set( $cache_key, $excluded, 'nbuf_restrictions', 300 );

		return $excluded;
	}
}
