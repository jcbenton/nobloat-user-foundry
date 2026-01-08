<?php
/**
 * NoBloat User Foundry - Member Directory
 *
 * Public-facing member directory with search, filtering, and pagination.
 * Respects user privacy settings automatically.
 *
 * Shortcode: [nbuf_members]
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Member_Directory
 *
 * Public-facing member directory with search and filtering.
 */
class NBUF_Member_Directory {


	/**
	 * Initialize directory
	 */
	public static function init() {
		/* Register shortcode */
		add_shortcode( 'nbuf_members', array( __CLASS__, 'render_directory' ) );

		/* AJAX handlers */
		add_action( 'wp_ajax_nbuf_directory_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_nbuf_directory_search', array( __CLASS__, 'ajax_search' ) );

		/* Enqueue scripts */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue directory scripts and styles
	 */
	public static function enqueue_scripts() {
		/* Check if on Universal Router members page */
		if ( class_exists( 'NBUF_Universal_Router' ) && NBUF_Universal_Router::is_universal_request() ) {
			if ( 'members' === NBUF_Universal_Router::get_current_view() ) {
				self::do_enqueue_assets();
				return;
			}
		}

		/* Check if shortcode is present in post */
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'nbuf_members' ) ) {
			self::do_enqueue_assets();
		}
	}

	/**
	 * Enqueue the directory assets (CSS and JS)
	 */
	private static function do_enqueue_assets() {
		/* Use CSS Manager for minified live CSS (same as other pages) */
		$css_load = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
		if ( $css_load && class_exists( 'NBUF_CSS_Manager' ) ) {
			NBUF_CSS_Manager::enqueue_css(
				'nbuf-member-directory',
				'member-directory',
				'nbuf_member_directory_custom_css',
				'nbuf_css_write_failed_member_directory'
			);
		}

		wp_enqueue_script(
			'nbuf-directory',
			plugin_dir_url( __DIR__ ) . 'assets/js/frontend/member-directory.js',
			array( 'jquery' ),
			NBUF_VERSION,
			true
		);

		wp_localize_script(
			'nbuf-directory',
			'nbufDirectory',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'nbuf_directory_nonce' ),
			)
		);
	}

	/**
	 * Get member avatar HTML with initials fallback.
	 *
	 * Uses NBUF_Profile_Photos if available, otherwise generates SVG initials.
	 *
	 * @param  int $user_id User ID.
	 * @param  int $size    Avatar size in pixels.
	 * @return string Avatar HTML.
	 */
	public static function get_member_avatar( $user_id, $size = 96 ) {
		$user = get_userdata( $user_id );
		$alt  = $user ? $user->display_name : '';

		/* Try to use profile photos class if available */
		if ( class_exists( 'NBUF_Profile_Photos' ) && method_exists( 'NBUF_Profile_Photos', 'get_profile_photo' ) ) {
			$photo_url = NBUF_Profile_Photos::get_profile_photo( $user_id, $size );

			/* Check if it's a data URI (SVG initials avatar) */
			if ( 0 === strpos( $photo_url, 'data:' ) ) {
				/* Data URI - use esc_attr instead of esc_url */
				return sprintf(
					'<img src="%s" alt="%s" class="avatar avatar-%d photo nbuf-avatar nbuf-svg-avatar nbuf-avatar-rounded" width="%d" height="%d" loading="lazy" />',
					esc_attr( $photo_url ),
					esc_attr( $alt ),
					$size,
					$size,
					$size
				);
			}

			/* Regular URL - use esc_url */
			return sprintf(
				'<img src="%s" alt="%s" class="avatar avatar-%d photo nbuf-avatar nbuf-avatar-rounded" width="%d" height="%d" loading="lazy" />',
				esc_url( $photo_url ),
				esc_attr( $alt ),
				$size,
				$size,
				$size
			);
		}

		/* Fallback: Use get_avatar if profile photos not available */
		return get_avatar( $user_id, $size );
	}

	/**
	 * Render member directory
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_directory( $atts ) {
		/* Check if enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_member_directory', false );
		if ( ! $enabled ) {
			return '<p class="nbuf-centered-message">' . esc_html__( 'Member directory is currently disabled.', 'nobloat-user-foundry' ) . '</p>';
		}

		/* Get defaults from admin settings */
		$default_view         = NBUF_Options::get( 'nbuf_directory_default_view', 'grid' );
		$default_per_page     = NBUF_Options::get( 'nbuf_directory_per_page', 20 );
		$default_show_search  = NBUF_Options::get( 'nbuf_directory_show_search', true ) ? 'yes' : 'no';
		$default_show_filters = NBUF_Options::get( 'nbuf_directory_show_filters', true ) ? 'yes' : 'no';

		$atts = shortcode_atts(
			array(
				'view'         => $default_view,
				'per_page'     => $default_per_page,
				'show_search'  => $default_show_search,
				'show_filters' => $default_show_filters,
				'roles'        => '',              /* Comma-separated role slugs */
				'orderby'      => 'display_name',  /* display_name, registered, last_login */
				'order'        => 'ASC',
			),
			$atts,
			'nbuf_members'
		);

		/*
		Get members
		*/
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public directory display only, no sensitive operations.
		$page        = isset( $_GET['member_page'] ) ? absint( $_GET['member_page'] ) : 1;
		$search      = isset( $_GET['member_search'] ) ? sanitize_text_field( wp_unslash( $_GET['member_search'] ) ) : '';
		$role_filter = isset( $_GET['member_role'] ) ? sanitize_text_field( wp_unslash( $_GET['member_role'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$members = self::get_members(
			array(
				'per_page' => (int) $atts['per_page'],
				'paged'    => $page,
				'search'   => $search,
				'roles'    => $role_filter ? $role_filter : $atts['roles'],
				'orderby'  => $atts['orderby'],
				'order'    => $atts['order'],
			)
		);

		$show_search  = 'yes' === $atts['show_search'];
		$show_filters = 'yes' === $atts['show_filters'];
		$total        = $members['total'];
		$total_pages  = $members['pages'];
		$is_list_view = 'list' === $atts['view'];

		/* Build directory controls HTML */
		$directory_controls = self::build_controls_html( $show_search, $show_filters, $search, $role_filter, $total );

		/* Build members content HTML */
		$members_content = self::build_members_html( $members['members'], $is_list_view, $search, $role_filter );

		/* Build pagination HTML */
		$pagination = self::build_pagination_html( $page, $total_pages );

		/* Load HTML template (checks DB first, falls back to default file) */
		$template_name = $is_list_view ? 'member-directory-list-html' : 'member-directory-html';
		$template      = NBUF_Template_Manager::load_template( $template_name );

		/* Build replacements */
		$replacements = array(
			'{directory_controls}' => $directory_controls,
			'{members_content}'    => $members_content,
			'{pagination}'         => $pagination,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Build directory controls HTML (search, filters, stats)
	 *
	 * @param  bool   $show_search  Whether to show search box.
	 * @param  bool   $show_filters Whether to show filter dropdowns.
	 * @param  string $current_search Current search value.
	 * @param  string $current_role Current role filter value.
	 * @param  int    $total Total member count.
	 * @return string HTML.
	 */
	private static function build_controls_html( $show_search, $show_filters, $current_search, $current_role, $total ) {
		if ( ! $show_search && ! $show_filters ) {
			return '';
		}

		$html = '<div class="nbuf-directory-controls">';
		$html .= '<form method="get" action="" class="nbuf-directory-form">';

		/* Preserve existing query vars using whitelist approach for security */
		$allowed_params = apply_filters( 'nbuf_directory_allowed_params', array( 'page_id', 'p', 'preview', 'preview_id', 'preview_nonce' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters preservation.
		foreach ( $allowed_params as $param ) {
			if ( isset( $_GET[ $param ] ) && ! is_array( $_GET[ $param ] ) ) {
				$html .= '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ) . '">';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/* Search */
		if ( $show_search ) {
			$html .= '<div class="nbuf-directory-search">';
			$html .= '<input type="text" name="member_search" placeholder="' . esc_attr__( 'Search members...', 'nobloat-user-foundry' ) . '" value="' . esc_attr( $current_search ) . '" class="nbuf-search-input">';
			$html .= '<button type="submit" class="nbuf-search-button">' . esc_html__( 'Search', 'nobloat-user-foundry' ) . '</button>';
			$html .= '</div>';
		}

		/* Filters */
		if ( $show_filters ) {
			$html .= '<div class="nbuf-directory-filters">';
			$html .= '<select name="member_role" class="nbuf-filter-select">';
			$html .= '<option value="">' . esc_html__( 'All Roles', 'nobloat-user-foundry' ) . '</option>';

			/* Only show roles that are allowed in directory */
			$allowed_roles = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );
			if ( ! is_array( $allowed_roles ) ) {
				$allowed_roles = array( 'author', 'contributor', 'subscriber' );
			}
			$all_roles = wp_roles()->get_names();
			foreach ( $allowed_roles as $role_slug ) {
				if ( ! isset( $all_roles[ $role_slug ] ) ) {
					continue;
				}
				$role_name = $all_roles[ $role_slug ];
				$selected  = selected( $current_role, $role_slug, false );
				$html     .= '<option value="' . esc_attr( $role_slug ) . '"' . $selected . '>' . esc_html( $role_name ) . '</option>';
			}

			$html .= '</select>';
			$html .= '<button type="submit" class="nbuf-filter-button">' . esc_html__( 'Filter', 'nobloat-user-foundry' ) . '</button>';

			if ( $current_search || $current_role ) {
				$html .= '<a href="?" class="nbuf-clear-filters">' . esc_html__( 'Clear', 'nobloat-user-foundry' ) . '</a>';
			}

			$html .= '</div>';
		}

		$html .= '</form>';

		/* Stats */
		$html .= '<div class="nbuf-directory-stats">';
		/* translators: %d: total member count */
		$html .= esc_html( sprintf( _n( '%d member found', '%d members found', $total, 'nobloat-user-foundry' ), (int) $total ) );
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build members content HTML (grid/list of member cards or empty message)
	 *
	 * @param  array  $members Array of member objects.
	 * @param  bool   $is_list_view Whether this is list view.
	 * @param  string $current_search Current search value.
	 * @param  string $current_role Current role filter value.
	 * @return string HTML.
	 */
	private static function build_members_html( $members, $is_list_view, $current_search, $current_role ) {
		if ( empty( $members ) ) {
			$html  = '<div class="nbuf-no-members">';
			$html .= '<p>' . esc_html__( 'No members found.', 'nobloat-user-foundry' ) . '</p>';
			if ( $current_search || $current_role ) {
				$html .= '<p><a href="?">' . esc_html__( 'Clear filters and show all members', 'nobloat-user-foundry' ) . '</a></p>';
			}
			$html .= '</div>';
			return $html;
		}

		$container_class = $is_list_view ? 'nbuf-members-list' : 'nbuf-members-grid';
		$html            = '<div class="' . esc_attr( $container_class ) . '">';

		foreach ( $members as $member ) {
			if ( $is_list_view ) {
				$html .= self::get_member_list_item( $member );
			} else {
				$html .= self::get_member_card( $member );
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get member list item HTML (for list view)
	 *
	 * @param  object $member Member data.
	 * @return string HTML.
	 */
	public static function get_member_list_item( $member ) {
		$viewer_id = get_current_user_id();
		$username  = isset( $member->user_login ) ? $member->user_login : get_userdata( $member->ID )->user_login;

		$html  = '<div class="nbuf-member-item" data-user-id="' . esc_attr( $member->ID ) . '">';
		$html .= '<div class="nbuf-member-avatar-small">' . self::get_member_avatar( $member->ID, 48 ) . '</div>';
		$html .= '<div class="nbuf-member-details">';
		$html .= '<h4 class="nbuf-member-name">';
		/* translators: %s: user display name */
		$html .= '<a href="' . esc_url( NBUF_URL::get_profile( $username ) ) . '" aria-label="' . esc_attr( sprintf( __( 'View %s\'s profile', 'nobloat-user-foundry' ), $member->display_name ) ) . '">';
		$html .= esc_html( $member->display_name );
		$html .= '</a></h4>';

		$html .= '<div class="nbuf-member-meta-inline">';

		/* Location */
		if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'location', $viewer_id ) ) {
			if ( ! empty( $member->city ) || ! empty( $member->country ) ) {
				$location_parts = array_filter( array( $member->city, $member->state, $member->country ) );
				$html          .= '<span class="nbuf-member-location-inline">';
				$html          .= '<span class="dashicons dashicons-location"></span>';
				$html          .= esc_html( implode( ', ', $location_parts ) );
				$html          .= '</span>';
			}
		}

		/* Joined date */
		$html .= '<span class="nbuf-member-joined-inline">';
		/* translators: %s: registration date */
		$html .= sprintf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member->user_registered ) ) ) );
		$html .= '</span>';
		$html .= '</div>';

		/* Bio */
		if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'bio', $viewer_id ) && ! empty( $member->bio ) ) {
			$html .= '<div class="nbuf-member-bio-inline">' . esc_html( wp_trim_words( $member->bio, 15 ) ) . '</div>';
		}

		$html .= '</div>';

		/* Website link */
		if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'website', $viewer_id ) && ! empty( $member->website ) ) {
			$html .= '<div class="nbuf-member-actions">';
			$html .= '<a href="' . esc_url( $member->website ) . '" target="_blank" rel="noopener noreferrer" class="nbuf-member-link">' . esc_html__( 'Website', 'nobloat-user-foundry' ) . '</a>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build pagination HTML
	 *
	 * @param  int $current_page Current page number.
	 * @param  int $total_pages Total number of pages.
	 * @return string HTML.
	 */
	private static function build_pagination_html( $current_page, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$base_url = remove_query_arg( 'member_page' );
		$html     = '<div class="nbuf-directory-pagination">';

		/* Previous link */
		if ( $current_page > 1 ) {
			$prev_url = add_query_arg( 'member_page', $current_page - 1, $base_url );
			$html    .= '<a href="' . esc_url( $prev_url ) . '" class="nbuf-page-prev">' . esc_html__( '&laquo; Previous', 'nobloat-user-foundry' ) . '</a>';
		}

		/* Page numbers */
		$html .= '<span class="nbuf-page-numbers">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			if ( $current_page === $i ) {
				$html .= '<span class="nbuf-page-number current">' . (int) $i . '</span>';
			} else {
				$html .= '<a href="' . esc_url( add_query_arg( 'member_page', $i, $base_url ) ) . '" class="nbuf-page-number">' . (int) $i . '</a>';
			}
		}
		$html .= '</span>';

		/* Next link */
		if ( $current_page < $total_pages ) {
			$next_url = add_query_arg( 'member_page', $current_page + 1, $base_url );
			$html    .= '<a href="' . esc_url( $next_url ) . '" class="nbuf-page-next">' . esc_html__( 'Next &raquo;', 'nobloat-user-foundry' ) . '</a>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get members for directory
	 *
	 * @param  array $args Query arguments.
	 * @return array Members data with pagination.
	 */
	public static function get_members( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'paged'    => 1,
			'search'   => '',
			'roles'    => '',
			'location' => '',
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_table    = $wpdb->users;
		$data_table    = NBUF_Database::get_table_name( 'user_data' );
		$profile_table = NBUF_Database::get_table_name( 'user_profile' );

		/*
		Base query
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sql = "SELECT SQL_CALC_FOUND_ROWS
		        u.ID,
		        u.user_login,
		        u.display_name,
		        u.user_email,
		        u.user_registered,
		        ud.profile_privacy,
		        ud.last_login_at,
		        up.bio,
		        up.city,
		        up.state,
		        up.country,
		        up.website
		        FROM {$user_table} u
		        LEFT JOIN {$data_table} ud ON u.ID = ud.user_id
		        LEFT JOIN {$profile_table} up ON u.ID = up.user_id
		        WHERE ud.show_in_directory = 1";

		/* Privacy filter (respect viewer status) */
		if ( is_user_logged_in() ) {
			$sql .= " AND ud.profile_privacy IN ('public', 'members_only')";
		} else {
			$sql .= " AND ud.profile_privacy = 'public'";
		}

		/* Base role restriction - only show users with allowed roles (security) */
		$allowed_roles = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array( 'author', 'contributor', 'subscriber' );
		}
		if ( ! empty( $allowed_roles ) ) {
			$base_role_parts = array();
			foreach ( $allowed_roles as $allowed_role ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$base_role_parts[] = $wpdb->prepare(
					"EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = u.ID AND um.meta_key = %s AND um.meta_value LIKE %s)",
					$wpdb->prefix . 'capabilities',
					'%"' . $wpdb->esc_like( $allowed_role ) . '"%'
				);
			}
			$sql .= ' AND (' . implode( ' OR ', $base_role_parts ) . ')';
		}

		/* Search filter */
		if ( ! empty( $args['search'] ) ) {
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql   .= $wpdb->prepare( ' AND (u.display_name LIKE %s OR up.bio LIKE %s OR up.city LIKE %s)', $search, $search, $search );
		}

		/* Location filter */
		if ( ! empty( $args['location'] ) ) {
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$location = '%' . $wpdb->esc_like( $args['location'] ) . '%';
			$sql     .= $wpdb->prepare( ' AND (up.city LIKE %s OR up.state LIKE %s OR up.country LIKE %s)', $location, $location, $location );
		}

		/* Role filter - only allow roles configured in directory settings (security) */
		if ( ! empty( $args['roles'] ) ) {
			$roles         = array_map( 'trim', explode( ',', $args['roles'] ) );
			$allowed_roles = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );
			if ( ! is_array( $allowed_roles ) ) {
				$allowed_roles = array( 'author', 'contributor', 'subscriber' );
			}

			/* Filter out any roles that aren't allowed (prevent enumeration of admin users) */
			$roles          = array_intersect( $roles, $allowed_roles );
			$role_sql_parts = array();

			foreach ( $roles as $role ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$role_sql_parts[] = $wpdb->prepare(
					"EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = u.ID AND um.meta_key = %s AND um.meta_value LIKE %s)",
					$wpdb->prefix . 'capabilities',
					'%"' . $wpdb->esc_like( $role ) . '"%'
				);
			}

			if ( ! empty( $role_sql_parts ) ) {
				$sql .= ' AND (' . implode( ' OR ', $role_sql_parts ) . ')';
			}
		}

		/* Order by */
		$allowed_orderby = array( 'display_name', 'user_registered', 'city', 'last_login' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'display_name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		if ( 'display_name' === $orderby ) {
			$sql .= " ORDER BY u.display_name {$order}";
		} elseif ( 'user_registered' === $orderby ) {
			$sql .= " ORDER BY u.user_registered {$order}";
		} elseif ( 'city' === $orderby ) {
			$sql .= " ORDER BY up.city {$order}, u.display_name ASC";
		} elseif ( 'last_login' === $orderby ) {
			$sql .= " ORDER BY ud.last_login_at {$order}";
		}

		/* Pagination */
		$offset = ( $args['paged'] - 1 ) * $args['per_page'];
		$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], $offset );

		/* Execute query */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built incrementally with prepare() for values; orderby is whitelisted.
		$members = $wpdb->get_results( $sql );

		/*
		Get total count
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Total count query, caching not needed for dynamic directory.
		$total = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		return array(
			'members' => $members,
			'total'   => (int) $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * AJAX handler for directory search
	 */
	public static function ajax_search() {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_directory_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		/* Get search parameters */
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$role     = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 20;

		/* Get members */
		$result = self::get_members(
			array(
				'search'   => $search,
				'roles'    => $role,
				'paged'    => $page,
				'per_page' => $per_page,
			)
		);

		wp_send_json_success( $result );
	}

	/**
	 * Get member card HTML
	 *
	 * @param  object $member Member data.
	 * @return string HTML.
	 */
	public static function get_member_card( $member ) {
		$viewer_id = get_current_user_id();

		ob_start();
		?>
		<div class="nbuf-member-card" data-user-id="<?php echo esc_attr( $member->ID ); ?>">
			<div class="nbuf-member-avatar">
		<?php
		echo self::get_member_avatar( $member->ID, 96 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated internally with escaped values.
		?>
			</div>

			<div class="nbuf-member-info">
				<?php
				$username = isset( $member->user_login ) ? $member->user_login : get_userdata( $member->ID )->user_login;
				?>
				<h3 class="nbuf-member-name">
					<?php /* translators: %s: user display name */ ?>
					<a href="<?php echo esc_url( NBUF_URL::get_profile( $username ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s\'s profile', 'nobloat-user-foundry' ), $member->display_name ) ); ?>">
						<?php echo esc_html( $member->display_name ); ?>
					</a>
				</h3>

		<?php if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'bio', $viewer_id ) && ! empty( $member->bio ) ) : ?>
					<div class="nbuf-member-bio">
			<?php echo esc_html( wp_trim_words( $member->bio, 20 ) ); ?>
					</div>
		<?php endif; ?>

		<?php if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'location', $viewer_id ) ) : ?>
			<?php if ( ! empty( $member->city ) || ! empty( $member->country ) ) : ?>
						<div class="nbuf-member-location">
							<span class="dashicons dashicons-location"></span>
				<?php
				$location_parts = array_filter( array( $member->city, $member->state, $member->country ) );
				echo esc_html( implode( ', ', $location_parts ) );
				?>
						</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( NBUF_Privacy_Manager::can_view_field( $member->ID, 'website', $viewer_id ) && ! empty( $member->website ) ) : ?>
					<div class="nbuf-member-website">
						<a href="<?php echo esc_url( $member->website ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Visit Website', 'nobloat-user-foundry' ); ?>
						</a>
					</div>
		<?php endif; ?>

				<div class="nbuf-member-meta">
					<span class="nbuf-member-joined">
		<?php
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			/* translators: %s: registration date */
			printf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member->user_registered ) ) ) );
		?>
					</span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

/* Initialize */
NBUF_Member_Directory::init();