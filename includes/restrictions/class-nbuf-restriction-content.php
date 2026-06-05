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
class NBUF_Restriction_Content extends NBUF_Abstract_Restriction {


	/**
	 * Initialize content restrictions
	 *
	 * Registers hooks for content filtering, redirect handling, and optional
	 * query filtering to hide restricted content from listings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		/* Content filtering (high priority to run late) */
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999999 );
		add_filter( 'the_excerpt', array( __CLASS__, 'filter_content' ), 999999 );

		/* Template redirect for redirect and 404 actions */
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );

		/* Enforce restrictions on REST API responses for all configured post types */
		foreach ( self::get_restricted_post_types() as $post_type ) {
			add_filter( 'rest_prepare_' . $post_type, array( __CLASS__, 'filter_rest_content' ), 10, 3 );
		}

		/*
		 * Always exclude no-access restricted posts from FEEDS and SEARCH so
		 * their titles/URLs/existence are not leaked there — bringing those
		 * surfaces in line with the already fully-closed REST path. The
		 * nbuf_restrictions_hide_from_queries toggle additionally removes them
		 * from normal archive/listing queries. The callback applies the right
		 * scope per request.
		 */
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_queries' ) );

		/*
		 * Close the remaining sibling surfaces that render a restricted post's
		 * TITLE/URL without ever passing through the_content / the main query /
		 * rest_prepare_{type}. Each only ever excludes posts the CURRENT user
		 * cannot access, so authorized users are unaffected.
		 */
		/* XML sitemap (wp-sitemap.xml) runs its own per-type secondary query. */
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'filter_sitemap_query_args' ), 10, 2 );
		/* oEmbed endpoint assembles {title,author,thumbnail} via a separate path. */
		add_filter( 'oembed_response_data', array( __CLASS__, 'filter_oembed_response' ), 10, 2 );
		/* Adjacent-post links (previous/next_post_link) use a direct neighbor SQL. */
		add_filter( 'get_previous_post_where', array( __CLASS__, 'filter_adjacent_post_where' ) );
		add_filter( 'get_next_post_where', array( __CLASS__, 'filter_adjacent_post_where' ) );
		/* REST search controller emits id/title/url via rest_prepare_search_result. */
		add_filter( 'rest_post_search_query', array( __CLASS__, 'filter_rest_search_query' ), 10, 2 );
		/* Comment feeds render parent-post titles via a separate comments query. */
		add_filter( 'comment_feed_where', array( __CLASS__, 'filter_comment_feed_where' ) );
	}

	/**
	 * Filter REST API responses to enforce content restrictions.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_Post          $post     Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response Filtered response.
	 */
	public static function filter_rest_content( $response, $post, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $request kept for hook signature.
		unset( $request );

		$restriction = NBUF_Restrictions::get_content_restriction( $post->ID, $post->post_type );
		if ( empty( $restriction ) ) {
			return $response;
		}

		if ( self::check_access( $restriction['visibility'], $restriction['allowed_roles'] ) ) {
			return $response;
		}

		/*
		 * SECURITY: previously this only blanked content/excerpt, leaving
		 * title, slug, status, meta, ACF/custom REST fields, taxonomies,
		 * featured media, and the raw content (under context=edit) exposed
		 * to any user who could query the REST endpoint. Refuse the
		 * response entirely — a user who lacks access to a post should not
		 * learn the post's title or any of its metadata via REST.
		 */
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to view this content.', 'nobloat-user-foundry' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Filter post/page content based on restrictions
	 *
	 * Checks if current user has access to view the content. If access is denied,
	 * replaces content with a restriction message based on the configured action.
	 * Only filters content on singular post/page views.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $content Post content.
	 * @return string Filtered content or restriction message.
	 */
	public static function filter_content( $content ) {
		/* Filter on singular views, feeds, and excerpts — skip only admin */
		if ( is_admin() ) {
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

				/*
				 * Log access denial to security log — but ONLY for the singular
				 * page render. the_content / the_excerpt fires for archive
				 * loops, search results, related-post widgets, RSS items,
				 * sidebar excerpts, and Gutenberg recursive renders. Logging
				 * every render floods the security log on busy sites with a
				 * popular restricted post; the singular gate matches the
				 * handle_redirect path.
				 */
				if ( is_singular() && class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log_or_update(
						'access_denied_message',
						'info',
						sprintf(
						/* translators: %s: Post title */
							__( 'Access denied to "%s" - message shown', 'nobloat-user-foundry' ),
							$post->post_title
						),
						array(
							'content_id'   => $post->ID,
							'content_type' => $post->post_type,
							'visibility'   => $restriction['visibility'],
						),
						get_current_user_id()
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
	 *
	 * Intercepts template loading for restricted content when the restriction
	 * action is set to 'redirect' or '404'. Logs access denial and performs
	 * the appropriate action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_redirect(): void {
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
				? esc_url_raw( $restriction['redirect_url'] )
				: ( class_exists( 'NBUF_Shortcodes' ) && method_exists( 'NBUF_Shortcodes', 'get_login_url' )
					? NBUF_Shortcodes::get_login_url( get_permalink( $post->ID ) )
					: wp_login_url( get_permalink( $post->ID ) ) );

				/* Log access denial to security log */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'access_denied_redirect',
						'info',
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
						),
						get_current_user_id()
					);
				}

				/* Redirect and exit */
				wp_safe_redirect( $url );
				exit;

			case '404':
				/* Log access denial to security log */
				if ( class_exists( 'NBUF_Security_Log' ) ) {
					NBUF_Security_Log::log(
						'access_denied_404',
						'info',
						sprintf(
						/* translators: %s: Post title */
							__( 'Access denied to "%s" - 404 shown', 'nobloat-user-foundry' ),
							$post->post_title
						),
						array(
							'content_id'   => $post->ID,
							'content_type' => $post->post_type,
							'visibility'   => $restriction['visibility'],
						),
						get_current_user_id()
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
	 * When enabled, prevents restricted content from appearing in archives,
	 * search results, and other query listings. Only affects main queries
	 * on the frontend.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query WordPress query object.
	 * @return void
	 */
	public static function exclude_from_queries( $query ): void {
		/* Skip for admin, singular, and non-main queries */
		if ( is_admin() || $query->is_singular || ! $query->is_main_query() ) {
			return;
		}

		/*
		 * Feeds and search always get the exclusion (parity with REST, which is
		 * fully closed). Normal archive/listing queries are excluded only when
		 * the operator opts in via nbuf_restrictions_hide_from_queries, to avoid
		 * silently changing which posts appear in ordinary archives.
		 */
		$hide_all = (bool) NBUF_Options::get( 'nbuf_restrictions_hide_from_queries', false );
		if ( ! $hide_all && ! ( $query->is_feed() || $query->is_search() ) ) {
			return;
		}

		/*
		 * Resolve which post type(s) to compute exclusions for:
		 *  - explicit ARRAY (multi-type feed / multi-type search): use each. A bare
		 *    array passed to get_excluded_post_ids would bind as the literal string
		 *    'Array' and match zero rows -> silent no-op leak, so iterate.
		 *  - explicit single type: use it.
		 *  - empty / 'any' (a default front-end search spans ALL searchable types):
		 *    union EVERY configured restricted type, not just 'post', so a restricted
		 *    page/CPT is not left in the result set with its title/URL exposed.
		 */
		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			$types = $post_type;
		} elseif ( ! empty( $post_type ) && 'any' !== $post_type ) {
			$types = array( $post_type );
		} else {
			$types = self::get_restricted_post_types();
		}

		$excluded_ids = array();
		foreach ( $types as $pt ) {
			$excluded_ids = array_merge( $excluded_ids, self::get_excluded_post_ids( (string) $pt ) );
		}

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
	 * Queries the restrictions table to find posts the current user cannot access.
	 * Results are cached for 5 minutes to improve performance.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $post_type Post type to check.
	 * @return array<int, int> Array of post IDs to exclude from queries.
	 */
	private static function get_excluded_post_ids( $post_type ): array {
		/*
		 * Try to get from cache. The key embeds the group's "last_changed"
		 * version so any restriction edit/delete or user role change — which
		 * bump it via NBUF_Restrictions::flush_excluded_cache() — instantly
		 * invalidates every cached exclusion list. Without it, a newly
		 * restricted post stayed visible (or a downgraded user kept access) for
		 * up to the 5-minute TTL.
		 */
		$last_changed = wp_cache_get_last_changed( 'nbuf_restrictions' );
		$cache_key    = 'nbuf_excluded_posts_' . $post_type . '_' . ( is_user_logged_in() ? get_current_user_id() : 'guest' ) . '_' . $last_changed;
		$cached       = wp_cache_get( $cache_key, 'nbuf_restrictions' );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_content_restrictions';

		/*
		 * Get all restrictions for this post type.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom restrictions table.
		$restrictions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT content_id, visibility, allowed_roles FROM %i WHERE content_type = %s',
				$table,
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

	/**
	 * Configured restricted post types (with a safe default).
	 *
	 * @return array<int, string>
	 */
	private static function get_restricted_post_types(): array {
		$types = NBUF_Options::get( 'nbuf_restrictions_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $types ) || empty( $types ) ) {
			$types = array( 'post', 'page' );
		}
		return $types;
	}

	/**
	 * Union of excluded post IDs across EVERY configured restricted post type.
	 *
	 * For surfaces that are not scoped to a single post type (oEmbed, adjacent
	 * links, REST search, comment feeds). Each per-type lookup is cached.
	 *
	 * @return array<int, int>
	 */
	private static function get_all_excluded_post_ids(): array {
		$all = array();
		foreach ( self::get_restricted_post_types() as $pt ) {
			$all = array_merge( $all, self::get_excluded_post_ids( (string) $pt ) );
		}
		return array_values( array_unique( $all ) );
	}

	/**
	 * Exclude no-access restricted posts from the core XML sitemap.
	 *
	 * WP_Sitemaps_Posts runs its own per-post-type secondary WP_Query that never
	 * hits the main-query pre_get_posts gate, so restricted permalinks would be
	 * published to anonymous users without this.
	 *
	 * @param  array<string, mixed> $args      Query args for the sitemap provider.
	 * @param  string               $post_type Post type being listed.
	 * @return array<string, mixed> Filtered args.
	 */
	public static function filter_sitemap_query_args( $args, $post_type ) {
		$excluded = self::get_excluded_post_ids( (string) $post_type );
		if ( ! empty( $excluded ) ) {
			$existing = ( isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ) ? $args['post__not_in'] : array();
			$args['post__not_in'] = array_merge( $existing, $excluded );
		}
		return $args;
	}

	/**
	 * Strip title/author/thumbnail from the oEmbed response for a no-access post.
	 *
	 * The oembed/1.0/embed route assembles its payload outside rest_prepare_{type},
	 * so it would otherwise leak a restricted post's title + author to anyone.
	 *
	 * @param  array<string, mixed> $data oEmbed response data.
	 * @param  WP_Post              $post Post object.
	 * @return array<string, mixed> Filtered data.
	 */
	public static function filter_oembed_response( $data, $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return $data;
		}
		$restriction = NBUF_Restrictions::get_content_restriction( $post->ID, $post->post_type );
		if ( empty( $restriction ) ) {
			return $data;
		}
		if ( self::check_access( $restriction['visibility'], $restriction['allowed_roles'] ) ) {
			return $data;
		}
		/* No access: keep a valid shape but remove the leaked fields. */
		if ( is_array( $data ) ) {
			$data['title'] = __( 'Restricted content', 'nobloat-user-foundry' );
			unset(
				$data['author_name'],
				$data['author_url'],
				$data['thumbnail_url'],
				$data['thumbnail_width'],
				$data['thumbnail_height'],
				$data['html']
			);
		}
		return $data;
	}

	/**
	 * Exclude no-access restricted neighbors from adjacent-post link queries.
	 *
	 * get_adjacent_post() runs a direct SQL neighbor lookup (alias `p`) that does
	 * not fire pre_get_posts, so previous/next_post_link() would render a
	 * restricted neighbor's title + URL.
	 *
	 * @param  string $where Adjacent-post WHERE clause.
	 * @return string Filtered WHERE.
	 */
	public static function filter_adjacent_post_where( $where ) {
		$excluded = self::get_all_excluded_post_ids();
		if ( ! empty( $excluded ) ) {
			$ids    = implode( ',', array_map( 'absint', $excluded ) );
			$where .= ' AND p.ID NOT IN (' . $ids . ')';
		}
		return $where;
	}

	/**
	 * Exclude no-access restricted posts from the REST search controller.
	 *
	 * /wp/v2/search emits id/title/url via rest_prepare_search_result, not
	 * rest_prepare_{type}, so the round-1 REST 403 does not cover it.
	 *
	 * @param  array<string, mixed> $query_args Search WP_Query args.
	 * @param  mixed                $request    REST request (unused).
	 * @return array<string, mixed> Filtered args.
	 */
	public static function filter_rest_search_query( $query_args, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $request kept for hook signature.
		unset( $request );
		$excluded = self::get_all_excluded_post_ids();
		if ( ! empty( $excluded ) ) {
			$existing = ( isset( $query_args['post__not_in'] ) && is_array( $query_args['post__not_in'] ) ) ? $query_args['post__not_in'] : array();
			$query_args['post__not_in'] = array_merge( $existing, $excluded );
		}
		return $query_args;
	}

	/**
	 * Drop comments on no-access restricted posts from comment feeds.
	 *
	 * Comment feeds iterate a SEPARATE comments query (comment_feed_where) and
	 * render each comment's parent-post title + permalink, which post__not_in on
	 * the posts query does not constrain.
	 *
	 * @param  string $cwhere Comment-feed WHERE clause.
	 * @return string Filtered WHERE.
	 */
	public static function filter_comment_feed_where( $cwhere ) {
		$excluded = self::get_all_excluded_post_ids();
		if ( ! empty( $excluded ) ) {
			$ids     = implode( ',', array_map( 'absint', $excluded ) );
			$cwhere .= ' AND comment_post_ID NOT IN (' . $ids . ')';
		}
		return $cwhere;
	}
}
