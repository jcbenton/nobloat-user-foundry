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
		/* Only enqueue if shortcode is present */
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'nbuf_members' ) ) {
			return;
		}

		wp_enqueue_style(
			'nbuf-directory',
			plugin_dir_url( __DIR__ ) . 'assets/css/frontend/member-directory.css',
			array(),
			NBUF_VERSION
		);

		wp_enqueue_script(
			'nbuf-directory',
			plugin_dir_url( __DIR__ ) . 'assets/js/frontend/member-directory.js',
			array( 'jquery' ),
			NBUF_VERSION,
			true
		);

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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
	 * Render member directory
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_directory( $atts ) {
		/* Check if enabled */
		$enabled = NBUF_Options::get( 'nbuf_enable_member_directory', false );
		if ( ! $enabled ) {
			return '<p>' . esc_html__( 'Member directory is currently disabled.', 'nobloat-user-foundry' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'view'         => 'grid',          /* grid or list */
				'per_page'     => 20,
				'show_search'  => 'yes',
				'show_filters' => 'yes',
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

		/* Load template */
		ob_start();

		$template_file = 'list' === $atts['view']
		? 'member-directory-list.php'
		: 'member-directory.php';

		$template_path = plugin_dir_path( __DIR__ ) . 'templates/' . $template_file;

		/* Allow theme override */
		$theme_template = locate_template(
			array(
				'nbuf-templates/' . $template_file,
				'nobloat-user-foundry/' . $template_file,
			)
		);

		if ( $theme_template ) {
			$template_path = $theme_template;
		}

		if ( file_exists( $template_path ) ) {
			/*
			* Extract variables for template
			*/
         // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled variables for template.
			extract(
				array(
					'members'      => $members['members'],
					'total'        => $members['total'],
					'per_page'     => $atts['per_page'],
					'current_page' => $page,
					'total_pages'  => $members['pages'],
					'show_search'  => 'yes' === $atts['show_search'],
					'show_filters' => 'yes' === $atts['show_filters'],
				)
			);

			include $template_path;
		}

		return ob_get_clean();
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
		        u.display_name,
		        u.user_email,
		        u.user_registered,
		        ud.profile_privacy,
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

		/* Role filter */
		if ( ! empty( $args['roles'] ) ) {
			$roles          = array_map( 'trim', explode( ',', $args['roles'] ) );
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
		$allowed_orderby = array( 'display_name', 'user_registered', 'city' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'display_name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		if ( 'display_name' === $orderby ) {
			$sql .= " ORDER BY u.display_name {$order}";
		} elseif ( 'user_registered' === $orderby ) {
			$sql .= " ORDER BY u.user_registered {$order}";
		} elseif ( 'city' === $orderby ) {
			$sql .= " ORDER BY up.city {$order}, u.display_name ASC";
		}

		/* Pagination */
		$offset = ( $args['paged'] - 1 ) * $args['per_page'];
		$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], $offset );

		/*
		Execute query
		*/
     // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query built with $wpdb->prepare() above, caching not needed for dynamic directory.
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
       // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		echo get_avatar( $member->ID, 96 );
		?>
			</div>

			<div class="nbuf-member-info">
				<h3 class="nbuf-member-name">
		<?php echo esc_html( $member->display_name ); ?>
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