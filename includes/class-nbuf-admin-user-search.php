<?php
/**
 * NoBloat User Foundry - Enhanced Admin User Search
 *
 * Adds powerful search and filter capabilities to WordPress admin Users screen.
 * Search by profile fields, filter by verification/expiration status, and export to CSV.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Admin_User_Search {

	/**
	 * Initialize admin user search enhancements
	 */
	public static function init() {
		/* Add custom columns to users list */
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_custom_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_custom_column' ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( __CLASS__, 'make_columns_sortable' ) );

		/* Add filter dropdowns above users list */
		add_action( 'restrict_manage_users', array( __CLASS__, 'add_filter_dropdowns' ) );

		/* Modify user query based on filters */
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users_query' ) );

		/* Enhance search to include profile fields */
		add_filter( 'user_search_columns', array( __CLASS__, 'add_search_columns' ), 10, 3 );

		/* Add export to CSV button and handler */
		add_action( 'admin_footer-users.php', array( __CLASS__, 'add_export_button' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );

		/* Add admin styles */
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	/**
	 * Add custom columns to users list table
	 *
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	public static function add_custom_columns( $columns ) {
		/* Insert custom columns after username */
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			/* Add custom columns after username */
			if ( $key === 'username' ) {
				$new_columns['nbuf_verification'] = __( 'Verified', 'nobloat-user-foundry' );
				$new_columns['nbuf_expiration'] = __( 'Expiration', 'nobloat-user-foundry' );
				$new_columns['nbuf_company'] = __( 'Company', 'nobloat-user-foundry' );
				$new_columns['nbuf_location'] = __( 'Location', 'nobloat-user-foundry' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content
	 *
	 * @param string $output      Custom column output
	 * @param string $column_name Column name
	 * @param int    $user_id     User ID
	 * @return string Column content
	 */
	public static function render_custom_column( $output, $column_name, $user_id ) {
		switch ( $column_name ) {
			case 'nbuf_verification':
				$is_verified = NBUF_User_Data::is_verified( $user_id );
				if ( $is_verified ) {
					$output = '<span class="nbuf-status-badge nbuf-verified" title="' . esc_attr__( 'Email verified', 'nobloat-user-foundry' ) . '">' .
						'<span class="dashicons dashicons-yes-alt"></span> ' .
						esc_html__( 'Verified', 'nobloat-user-foundry' ) .
						'</span>';
				} else {
					$output = '<span class="nbuf-status-badge nbuf-unverified" title="' . esc_attr__( 'Email not verified', 'nobloat-user-foundry' ) . '">' .
						'<span class="dashicons dashicons-warning"></span> ' .
						esc_html__( 'Unverified', 'nobloat-user-foundry' ) .
						'</span>';
				}
				break;

			case 'nbuf_expiration':
				$expires_at = NBUF_User_Data::get_expires_at( $user_id );
				$is_expired = NBUF_User_Data::is_expired( $user_id );

				if ( $is_expired ) {
					$output = '<span class="nbuf-status-badge nbuf-expired" title="' . esc_attr__( 'Account expired', 'nobloat-user-foundry' ) . '">' .
						'<span class="dashicons dashicons-dismiss"></span> ' .
						esc_html__( 'Expired', 'nobloat-user-foundry' ) .
						'</span>';
				} elseif ( $expires_at ) {
					$days_until = floor( ( strtotime( $expires_at ) - time() ) / DAY_IN_SECONDS );

					if ( $days_until <= 7 ) {
						$output = '<span class="nbuf-status-badge nbuf-expiring-soon" title="' . esc_attr( sprintf( __( 'Expires in %d days', 'nobloat-user-foundry' ), $days_until ) ) . '">' .
							'<span class="dashicons dashicons-clock"></span> ' .
							esc_html( sprintf( _n( '%d day', '%d days', $days_until, 'nobloat-user-foundry' ), $days_until ) ) .
							'</span>';
					} else {
						$output = '<span class="nbuf-status-badge nbuf-active" title="' . esc_attr( sprintf( __( 'Expires: %s', 'nobloat-user-foundry' ), date_i18n( get_option( 'date_format' ), strtotime( $expires_at ) ) ) ) . '">' .
							esc_html( date_i18n( 'M j, Y', strtotime( $expires_at ) ) ) .
							'</span>';
					}
				} else {
					$output = '<span class="nbuf-status-badge nbuf-no-expiration">' . esc_html__( 'Never', 'nobloat-user-foundry' ) . '</span>';
				}
				break;

			case 'nbuf_company':
				$profile = NBUF_Profile_Data::get( $user_id );
				if ( $profile && ! empty( $profile->company ) ) {
					$output = esc_html( $profile->company );
					if ( ! empty( $profile->job_title ) ) {
						$output .= '<br><small class="nbuf-job-title">' . esc_html( $profile->job_title ) . '</small>';
					}
				} else {
					$output = '<span class="nbuf-empty-field">—</span>';
				}
				break;

			case 'nbuf_location':
				$profile = NBUF_Profile_Data::get( $user_id );
				if ( $profile ) {
					$location_parts = array_filter( array(
						$profile->city,
						$profile->state,
						$profile->country
					) );
					if ( ! empty( $location_parts ) ) {
						$output = esc_html( implode( ', ', $location_parts ) );
					} else {
						$output = '<span class="nbuf-empty-field">—</span>';
					}
				} else {
					$output = '<span class="nbuf-empty-field">—</span>';
				}
				break;
		}

		return $output;
	}

	/**
	 * Make custom columns sortable
	 *
	 * @param array $columns Sortable columns
	 * @return array Modified columns
	 */
	public static function make_columns_sortable( $columns ) {
		$columns['nbuf_verification'] = 'nbuf_verification';
		$columns['nbuf_expiration'] = 'nbuf_expiration';
		$columns['nbuf_company'] = 'nbuf_company';
		$columns['nbuf_location'] = 'nbuf_location';
		return $columns;
	}

	/**
	 * Add filter dropdowns above users list
	 */
	public static function add_filter_dropdowns() {
		global $pagenow;

		if ( 'users.php' !== $pagenow ) {
			return;
		}

		/* Verification status filter */
		$verification_filter = isset( $_GET['nbuf_verification'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_verification'] ) ) : '';
		?>
		<label for="nbuf_verification" class="screen-reader-text"><?php esc_html_e( 'Filter by verification status', 'nobloat-user-foundry' ); ?></label>
		<select name="nbuf_verification" id="nbuf_verification">
			<option value=""><?php esc_html_e( 'All Verification Status', 'nobloat-user-foundry' ); ?></option>
			<option value="verified" <?php selected( $verification_filter, 'verified' ); ?>><?php esc_html_e( 'Verified Only', 'nobloat-user-foundry' ); ?></option>
			<option value="unverified" <?php selected( $verification_filter, 'unverified' ); ?>><?php esc_html_e( 'Unverified Only', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php

		/* Expiration status filter */
		$expiration_filter = isset( $_GET['nbuf_expiration'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_expiration'] ) ) : '';
		?>
		<label for="nbuf_expiration" class="screen-reader-text"><?php esc_html_e( 'Filter by expiration status', 'nobloat-user-foundry' ); ?></label>
		<select name="nbuf_expiration" id="nbuf_expiration">
			<option value=""><?php esc_html_e( 'All Expiration Status', 'nobloat-user-foundry' ); ?></option>
			<option value="active" <?php selected( $expiration_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?></option>
			<option value="expired" <?php selected( $expiration_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'nobloat-user-foundry' ); ?></option>
			<option value="expiring_soon" <?php selected( $expiration_filter, 'expiring_soon' ); ?>><?php esc_html_e( 'Expiring Soon (7 days)', 'nobloat-user-foundry' ); ?></option>
			<option value="no_expiration" <?php selected( $expiration_filter, 'no_expiration' ); ?>><?php esc_html_e( 'No Expiration', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php

		/* Directory visibility filter */
		$directory_filter = isset( $_GET['nbuf_directory'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_directory'] ) ) : '';
		?>
		<label for="nbuf_directory" class="screen-reader-text"><?php esc_html_e( 'Filter by directory visibility', 'nobloat-user-foundry' ); ?></label>
		<select name="nbuf_directory" id="nbuf_directory">
			<option value=""><?php esc_html_e( 'All Directory Status', 'nobloat-user-foundry' ); ?></option>
			<option value="in_directory" <?php selected( $directory_filter, 'in_directory' ); ?>><?php esc_html_e( 'In Directory', 'nobloat-user-foundry' ); ?></option>
			<option value="not_in_directory" <?php selected( $directory_filter, 'not_in_directory' ); ?>><?php esc_html_e( 'Not In Directory', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter users query based on selected filters
	 *
	 * @param WP_User_Query $query User query object
	 */
	public static function filter_users_query( $query ) {
		global $pagenow, $wpdb;

		if ( 'users.php' !== $pagenow || ! is_admin() ) {
			return;
		}

		/* Get user_data and profile table names */
		$user_data_table = $wpdb->prefix . 'nbuf_user_data';
		$profile_table = $wpdb->prefix . 'nbuf_user_profile';

		/* Verification filter */
		if ( isset( $_GET['nbuf_verification'] ) && ! empty( $_GET['nbuf_verification'] ) ) {
			$verification = sanitize_text_field( wp_unslash( $_GET['nbuf_verification'] ) );

			$meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $meta_query ) ) {
				$meta_query = array();
			}

			/* Use direct SQL for better performance */
			$query->query_from .= " LEFT JOIN {$user_data_table} nbuf_ud ON {$wpdb->users}.ID = nbuf_ud.user_id ";

			if ( 'verified' === $verification ) {
				$query->query_where .= " AND nbuf_ud.is_verified = 1 ";
			} elseif ( 'unverified' === $verification ) {
				$query->query_where .= " AND (nbuf_ud.is_verified = 0 OR nbuf_ud.is_verified IS NULL) ";
			}
		}

		/* Expiration filter */
		if ( isset( $_GET['nbuf_expiration'] ) && ! empty( $_GET['nbuf_expiration'] ) ) {
			$expiration = sanitize_text_field( wp_unslash( $_GET['nbuf_expiration'] ) );

			/* Add join if not already added */
			if ( strpos( $query->query_from, 'nbuf_ud' ) === false ) {
				$query->query_from .= " LEFT JOIN {$user_data_table} nbuf_ud ON {$wpdb->users}.ID = nbuf_ud.user_id ";
			}

			$now = current_time( 'mysql' );
			$seven_days = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );

			switch ( $expiration ) {
				case 'expired':
					$query->query_where .= $wpdb->prepare( " AND nbuf_ud.expires_at IS NOT NULL AND nbuf_ud.expires_at < %s ", $now );
					break;

				case 'active':
					$query->query_where .= $wpdb->prepare( " AND (nbuf_ud.expires_at IS NULL OR nbuf_ud.expires_at >= %s) ", $now );
					break;

				case 'expiring_soon':
					$query->query_where .= $wpdb->prepare( " AND nbuf_ud.expires_at BETWEEN %s AND %s ", $now, $seven_days );
					break;

				case 'no_expiration':
					$query->query_where .= " AND (nbuf_ud.expires_at IS NULL OR nbuf_ud.expires_at = '0000-00-00 00:00:00') ";
					break;
			}
		}

		/* Directory visibility filter */
		if ( isset( $_GET['nbuf_directory'] ) && ! empty( $_GET['nbuf_directory'] ) ) {
			$directory = sanitize_text_field( wp_unslash( $_GET['nbuf_directory'] ) );

			/* Add join if not already added */
			if ( strpos( $query->query_from, 'nbuf_ud' ) === false ) {
				$query->query_from .= " LEFT JOIN {$user_data_table} nbuf_ud ON {$wpdb->users}.ID = nbuf_ud.user_id ";
			}

			if ( 'in_directory' === $directory ) {
				$query->query_where .= " AND nbuf_ud.show_in_directory = 1 ";
			} elseif ( 'not_in_directory' === $directory ) {
				$query->query_where .= " AND (nbuf_ud.show_in_directory = 0 OR nbuf_ud.show_in_directory IS NULL) ";
			}
		}

		/* Handle sorting by custom columns */
		$orderby = $query->get( 'orderby' );

		if ( 'nbuf_verification' === $orderby ) {
			if ( strpos( $query->query_from, 'nbuf_ud' ) === false ) {
				$query->query_from .= " LEFT JOIN {$user_data_table} nbuf_ud ON {$wpdb->users}.ID = nbuf_ud.user_id ";
			}
			$query->query_orderby = 'ORDER BY nbuf_ud.is_verified ' . ( 'DESC' === strtoupper( $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		} elseif ( 'nbuf_expiration' === $orderby ) {
			if ( strpos( $query->query_from, 'nbuf_ud' ) === false ) {
				$query->query_from .= " LEFT JOIN {$user_data_table} nbuf_ud ON {$wpdb->users}.ID = nbuf_ud.user_id ";
			}
			$query->query_orderby = 'ORDER BY nbuf_ud.expires_at ' . ( 'DESC' === strtoupper( $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		} elseif ( 'nbuf_company' === $orderby ) {
			$query->query_from .= " LEFT JOIN {$profile_table} nbuf_up ON {$wpdb->users}.ID = nbuf_up.user_id ";
			$query->query_orderby = 'ORDER BY nbuf_up.company ' . ( 'DESC' === strtoupper( $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		} elseif ( 'nbuf_location' === $orderby ) {
			$query->query_from .= " LEFT JOIN {$profile_table} nbuf_up ON {$wpdb->users}.ID = nbuf_up.user_id ";
			$query->query_orderby = 'ORDER BY nbuf_up.city ' . ( 'DESC' === strtoupper( $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		}
	}

	/**
	 * Add profile fields to searchable columns
	 *
	 * @param array         $search_columns Columns to search
	 * @param string        $search         Search term
	 * @param WP_User_Query $query          User query
	 * @return array Modified search columns
	 */
	public static function add_search_columns( $search_columns, $search, $query ) {
		global $wpdb;

		/* Only enhance on admin users.php page */
		if ( ! is_admin() || ! isset( $_GET['s'] ) ) {
			return $search_columns;
		}

		/* Add custom search logic */
		$profile_table = $wpdb->prefix . 'nbuf_user_profile';
		$search_term = '%' . $wpdb->esc_like( $search ) . '%';

		/* Join profile table */
		add_filter( 'users_search', function( $search_sql ) use ( $wpdb, $profile_table, $search_term ) {
			if ( empty( $search_sql ) ) {
				return $search_sql;
			}

			/* Add profile field search to existing WHERE clause */
			$profile_search = $wpdb->prepare(
				" OR nbuf_profile.company LIKE %s OR nbuf_profile.job_title LIKE %s OR nbuf_profile.city LIKE %s OR nbuf_profile.state LIKE %s OR nbuf_profile.country LIKE %s OR nbuf_profile.bio LIKE %s ",
				$search_term, $search_term, $search_term, $search_term, $search_term, $search_term
			);

			$search_sql = str_replace( 'WHERE 1=1 AND (', 'WHERE 1=1 AND (', $search_sql ) . $profile_search;

			return $search_sql;
		} );

		/* Join profile table in FROM clause */
		add_filter( 'pre_user_query', function( $query ) use ( $wpdb, $profile_table ) {
			if ( ! isset( $_GET['s'] ) ) {
				return;
			}

			if ( strpos( $query->query_from, 'nbuf_profile' ) === false ) {
				$query->query_from .= " LEFT JOIN {$profile_table} nbuf_profile ON {$wpdb->users}.ID = nbuf_profile.user_id ";
			}
		} );

		return $search_columns;
	}

	/**
	 * Add export to CSV button
	 */
	public static function add_export_button() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			/* Add export button next to Add New button */
			var exportButton = $('<a href="#" class="page-title-action nbuf-export-csv" style="margin-left: 10px;">' +
				'<?php esc_html_e( 'Export to CSV', 'nobloat-user-foundry' ); ?>' +
				'</a>');

			exportButton.on('click', function(e) {
				e.preventDefault();

				/* Build export URL with current filters */
				var url = '<?php echo esc_url( admin_url( 'users.php' ) ); ?>';
				var params = new URLSearchParams(window.location.search);
				params.set('nbuf_export_csv', '1');
				params.set('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'nbuf_export_csv' ) ); ?>');

				window.location.href = url + '?' + params.toString();
			});

			$('.wrap h1').append(exportButton);
		});
		</script>
		<?php
	}

	/**
	 * Handle CSV export
	 */
	public static function handle_csv_export() {
		if ( ! isset( $_GET['nbuf_export_csv'] ) || ! current_user_can( 'list_users' ) ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Build query args from current filters */
		$args = array(
			'number' => -1, /* Get all users */
		);

		/* Apply search */
		if ( isset( $_GET['s'] ) ) {
			$args['search'] = '*' . sanitize_text_field( wp_unslash( $_GET['s'] ) ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		/* Apply role filter */
		if ( isset( $_GET['role'] ) && ! empty( $_GET['role'] ) ) {
			$args['role'] = sanitize_text_field( wp_unslash( $_GET['role'] ) );
		}

		/* Get users */
		$user_query = new WP_User_Query( $args );
		$users = $user_query->get_results();

		/* Filter by custom filters manually (since WP_User_Query doesn't support them directly) */
		if ( isset( $_GET['nbuf_verification'] ) || isset( $_GET['nbuf_expiration'] ) || isset( $_GET['nbuf_directory'] ) ) {
			$filtered_users = array();

			foreach ( $users as $user ) {
				$include = true;

				/* Verification filter */
				if ( isset( $_GET['nbuf_verification'] ) && ! empty( $_GET['nbuf_verification'] ) ) {
					$is_verified = NBUF_User_Data::is_verified( $user->ID );
					if ( 'verified' === $_GET['nbuf_verification'] && ! $is_verified ) {
						$include = false;
					} elseif ( 'unverified' === $_GET['nbuf_verification'] && $is_verified ) {
						$include = false;
					}
				}

				/* Expiration filter */
				if ( $include && isset( $_GET['nbuf_expiration'] ) && ! empty( $_GET['nbuf_expiration'] ) ) {
					$expires_at = NBUF_User_Data::get_expires_at( $user->ID );
					$is_expired = NBUF_User_Data::is_expired( $user->ID );

					switch ( sanitize_text_field( wp_unslash( $_GET['nbuf_expiration'] ) ) ) {
						case 'expired':
							if ( ! $is_expired ) {
								$include = false;
							}
							break;
						case 'active':
							if ( $is_expired ) {
								$include = false;
							}
							break;
						case 'expiring_soon':
							if ( ! $expires_at || $is_expired ) {
								$include = false;
							} else {
								$days_until = floor( ( strtotime( $expires_at ) - time() ) / DAY_IN_SECONDS );
								if ( $days_until > 7 ) {
									$include = false;
								}
							}
							break;
						case 'no_expiration':
							if ( $expires_at ) {
								$include = false;
							}
							break;
					}
				}

				/* Directory filter */
				if ( $include && isset( $_GET['nbuf_directory'] ) && ! empty( $_GET['nbuf_directory'] ) ) {
					$directory_status = sanitize_text_field( wp_unslash( $_GET['nbuf_directory'] ) );
					$in_directory = NBUF_Privacy_Manager::show_in_directory( $user->ID, $user->ID );
					if ( 'in_directory' === $directory_status && ! $in_directory ) {
						$include = false;
					} elseif ( 'not_in_directory' === $directory_status && $in_directory ) {
						$include = false;
					}
				}

				if ( $include ) {
					$filtered_users[] = $user;
				}
			}

			$users = $filtered_users;
		}

		/* Set headers for CSV download */
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=users-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		/* Open output stream */
		$output = fopen( 'php://output', 'w' );

		/* Add UTF-8 BOM for Excel compatibility */
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		/* CSV headers */
		$headers = array(
			__( 'ID', 'nobloat-user-foundry' ),
			__( 'Username', 'nobloat-user-foundry' ),
			__( 'Email', 'nobloat-user-foundry' ),
			__( 'Display Name', 'nobloat-user-foundry' ),
			__( 'First Name', 'nobloat-user-foundry' ),
			__( 'Last Name', 'nobloat-user-foundry' ),
			__( 'Role', 'nobloat-user-foundry' ),
			__( 'Verified', 'nobloat-user-foundry' ),
			__( 'Expires At', 'nobloat-user-foundry' ),
			__( 'Company', 'nobloat-user-foundry' ),
			__( 'Job Title', 'nobloat-user-foundry' ),
			__( 'City', 'nobloat-user-foundry' ),
			__( 'State', 'nobloat-user-foundry' ),
			__( 'Country', 'nobloat-user-foundry' ),
			__( 'Phone', 'nobloat-user-foundry' ),
			__( 'Registered', 'nobloat-user-foundry' ),
		);

		fputcsv( $output, $headers );

		/* Add user data rows */
		foreach ( $users as $user ) {
			$profile = NBUF_Profile_Data::get( $user->ID );
			$is_verified = NBUF_User_Data::is_verified( $user->ID );
			$expires_at = NBUF_User_Data::get_expires_at( $user->ID );

			/* Get user role */
			$roles = $user->roles;
			$role = ! empty( $roles ) ? ucfirst( $roles[0] ) : '';

			$row = array(
				$user->ID,
				$user->user_login,
				$user->user_email,
				$user->display_name,
				get_user_meta( $user->ID, 'first_name', true ),
				get_user_meta( $user->ID, 'last_name', true ),
				$role,
				$is_verified ? __( 'Yes', 'nobloat-user-foundry' ) : __( 'No', 'nobloat-user-foundry' ),
				$expires_at ? gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) ) : '',
				$profile ? $profile->company : '',
				$profile ? $profile->job_title : '',
				$profile ? $profile->city : '',
				$profile ? $profile->state : '',
				$profile ? $profile->country : '',
				$profile ? $profile->phone : '',
				gmdate( 'Y-m-d H:i:s', strtotime( $user->user_registered ) ),
			);

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Current admin page
	 */
	public static function enqueue_admin_styles( $hook ) {
		if ( 'users.php' !== $hook ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', '
			.nbuf-status-badge {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				padding: 4px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
				white-space: nowrap;
			}
			.nbuf-status-badge .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
			}
			.nbuf-verified {
				background: #d7f8e0;
				color: #1d7a3c;
			}
			.nbuf-unverified {
				background: #ffeecc;
				color: #a66800;
			}
			.nbuf-expired {
				background: #ffe6e6;
				color: #d63638;
			}
			.nbuf-expiring-soon {
				background: #fff3cd;
				color: #856404;
			}
			.nbuf-active {
				background: #e8f5f9;
				color: #0073aa;
			}
			.nbuf-no-expiration {
				background: #f0f0f1;
				color: #646970;
			}
			.nbuf-empty-field {
				color: #c3c4c7;
			}
			.nbuf-job-title {
				color: #646970;
				font-style: italic;
			}
		' );
	}
}
