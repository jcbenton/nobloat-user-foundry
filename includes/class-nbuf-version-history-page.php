<?php
/**
 * NoBloat User Foundry - Version History Admin Page
 *
 * Handles admin page registration and rendering for version history viewer.
 * Allows admins to view any user's complete profile change history.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Version_History_Page
 *
 * Admin page for viewing and managing user profile version history.
 */
class NBUF_Version_History_Page {


	/**
	 * Initialize version history page
	 */
	public static function init() {
		/* Only register if version history is enabled */
		$enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 16 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_nbuf_search_users_extended', array( __CLASS__, 'ajax_search_users' ) );
	}

	/**
	 * AJAX handler to search users by multiple fields
	 *
	 * Searches: username, email, display name, first name, last name
	 */
	public static function ajax_search_users() {
		check_ajax_referer( 'nbuf_version_history_search', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nobloat-user-foundry' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above via check_ajax_referer().
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		global $wpdb;

		/*
		 * Search users by login, email, display_name (core table)
		 * AND first_name, last_name (user meta)
		 */
		$like_search = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX user search with complex meta join.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.ID FROM {$wpdb->users} u
				LEFT JOIN {$wpdb->usermeta} um_fn ON u.ID = um_fn.user_id AND um_fn.meta_key = 'first_name'
				LEFT JOIN {$wpdb->usermeta} um_ln ON u.ID = um_ln.user_id AND um_ln.meta_key = 'last_name'
				WHERE u.user_login LIKE %s
				   OR u.user_email LIKE %s
				   OR u.display_name LIKE %s
				   OR um_fn.meta_value LIKE %s
				   OR um_ln.meta_value LIKE %s
				ORDER BY u.display_name ASC
				LIMIT 20",
				$like_search,
				$like_search,
				$like_search,
				$like_search,
				$like_search
			)
		);

		$results = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$name_parts = array();
			if ( $user->first_name ) {
				$name_parts[] = $user->first_name;
			}
			if ( $user->last_name ) {
				$name_parts[] = $user->last_name;
			}
			$full_name = implode( ' ', $name_parts );

			/* Build display text: "Display Name (username) - email" */
			$text = $user->display_name;
			if ( $full_name && $full_name !== $user->display_name ) {
				$text .= ' (' . $full_name . ')';
			}
			$text .= ' - ' . $user->user_email;

			$results[] = array(
				'id'   => $user->ID,
				'text' => $text,
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Add user profile log menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'nobloat-foundry',
			__( 'User Profile Log', 'nobloat-user-foundry' ),
			__( 'User Profile Log', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-version-history',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue page assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		$allowed_hooks = array(
			'nobloat-foundry_page_nobloat-foundry-version-history',
			'user-foundry_page_nobloat-foundry-version-history',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		$can_revert = current_user_can( 'manage_options' );

		/* Enqueue version history CSS */
		wp_enqueue_style(
			'nbuf-version-history',
			plugin_dir_url( __DIR__ ) . 'assets/css/admin/version-history.css',
			array(),
			'1.4.0'
		);

		/* Enqueue version history JS */
		wp_enqueue_script(
			'nbuf-version-history',
			plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js',
			array( 'jquery' ),
			'1.4.0',
			true
		);

		wp_localize_script( 'nbuf-version-history', 'NBUF_VersionHistory', NBUF_Version_History::get_script_data( $can_revert ) );

		/* Enqueue Select2 for searchable user dropdown */
		wp_enqueue_style( 'nbuf-select2', NBUF_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.0.13' );
		wp_enqueue_script( 'nbuf-select2', NBUF_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array( 'jquery' ), '4.0.13', true );

		/* Initialize Select2 with AJAX user search */
		wp_add_inline_script(
			'nbuf-select2',
			'jQuery(document).ready(function($) {
				$("#user_id").select2({
					placeholder: "' . esc_js( __( 'Type to search by name, email, or username...', 'nobloat-user-foundry' ) ) . '",
					allowClear: true,
					width: "350px",
					minimumInputLength: 2,
					ajax: {
						url: "' . esc_js( admin_url( 'admin-ajax.php' ) ) . '",
						dataType: "json",
						delay: 250,
						data: function(params) {
							return {
								action: "nbuf_search_users_extended",
								nonce: "' . esc_js( wp_create_nonce( 'nbuf_version_history_search' ) ) . '",
								search: params.term
							};
						},
						processResults: function(response) {
							if (response.success && response.data.results) {
								return { results: response.data.results };
							}
							return { results: [] };
						},
						cache: true
					},
					language: {
						inputTooShort: function() {
							return "' . esc_js( __( 'Type at least 2 characters to search...', 'nobloat-user-foundry' ) ) . '";
						},
						noResults: function() {
							return "' . esc_js( __( 'No users found', 'nobloat-user-foundry' ) ) . '";
						},
						searching: function() {
							return "' . esc_js( __( 'Searching...', 'nobloat-user-foundry' ) ) . '";
						}
					}
				});
			});'
		);
	}

	/**
	 * Render version history page
	 */
	public static function render_page() {
		/* Check user capabilities */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nobloat-user-foundry' ) );
		}

		/*
		 * Get selected user from query string
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter
		$selected_user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		/* Get statistics */
		$stats = self::get_stats();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Profile Version History', 'nobloat-user-foundry' ); ?></h1>

			<hr class="wp-header-end">

			<p class="description nbuf-page-description">
		<?php esc_html_e( 'View complete profile change history for any user. Track all modifications, compare versions, and restore previous states.', 'nobloat-user-foundry' ); ?>
			</p>

			<!-- Statistics Dashboard -->
			<div class="nbuf-stats-box nbuf-admin-card">
				<h3><?php esc_html_e( 'System Statistics', 'nobloat-user-foundry' ); ?></h3>
				<div class="nbuf-stats-grid-auto">
					<div>
						<div class="nbuf-stat-value-large"><?php echo esc_html( number_format( $stats['total_versions'] ) ); ?></div>
						<div class="nbuf-stat-label-small"><?php esc_html_e( 'Total Versions', 'nobloat-user-foundry' ); ?></div>
					</div>
					<div>
						<div class="nbuf-stat-value-large"><?php echo esc_html( number_format( $stats['total_users'] ) ); ?></div>
						<div class="nbuf-stat-label-small"><?php esc_html_e( 'Users Tracked', 'nobloat-user-foundry' ); ?></div>
					</div>
					<div>
						<div class="nbuf-stat-value-large"><?php echo esc_html( $stats['database_size'] ); ?></div>
						<div class="nbuf-stat-label-small"><?php esc_html_e( 'Database Size', 'nobloat-user-foundry' ); ?></div>
					</div>
					<div>
						<div class="nbuf-stat-value-large"><?php echo esc_html( $stats['retention_days'] ); ?> <?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></div>
						<div class="nbuf-stat-label-small"><?php esc_html_e( 'Retention Period', 'nobloat-user-foundry' ); ?></div>
					</div>
				</div>

		<?php if ( ! empty( $stats['last_cleanup'] ) ) : ?>
					<p class="nbuf-stats-footer">
						<strong><?php esc_html_e( 'Last Cleanup:', 'nobloat-user-foundry' ); ?></strong>
			<?php echo esc_html( $stats['last_cleanup'] ); ?>
			<?php if ( $stats['last_cleanup_count'] > 0 ) : ?>
				<?php /* translators: %d: Number of deleted versions */ ?>
						(<?php echo esc_html( sprintf( _n( '%d version deleted', '%d versions deleted', $stats['last_cleanup_count'], 'nobloat-user-foundry' ), $stats['last_cleanup_count'] ) ); ?>)
			<?php endif; ?>
					</p>
		<?php endif; ?>
			</div>

			<!-- User Selector -->
			<div class="nbuf-user-selector nbuf-admin-card">
				<h3><?php esc_html_e( 'Select User', 'nobloat-user-foundry' ); ?></h3>
				<form method="get" action="">
					<input type="hidden" name="page" value="nobloat-foundry-version-history">

					<div class="nbuf-selector-row">
						<label for="user_id" class="nbuf-label-bold">
		<?php esc_html_e( 'User:', 'nobloat-user-foundry' ); ?>
						</label>

						<select name="user_id" id="user_id" class="regular-text">
		<?php if ( $selected_user_id > 0 ) : ?>
			<?php
			$selected_user = get_userdata( $selected_user_id );
			if ( $selected_user ) :
				$name_parts = array();
				if ( $selected_user->first_name ) {
					$name_parts[] = $selected_user->first_name;
				}
				if ( $selected_user->last_name ) {
					$name_parts[] = $selected_user->last_name;
				}
				$full_name    = implode( ' ', $name_parts );
				$display_text = $selected_user->display_name;
				if ( $full_name && $full_name !== $selected_user->display_name ) {
					$display_text .= ' (' . $full_name . ')';
				}
				$display_text .= ' - ' . $selected_user->user_email;
				?>
								<option value="<?php echo esc_attr( $selected_user_id ); ?>" selected>
				<?php echo esc_html( $display_text ); ?>
								</option>
			<?php endif; ?>
		<?php endif; ?>
						</select>

		<?php submit_button( __( 'View History', 'nobloat-user-foundry' ), 'primary', 'submit', false ); ?>
					</div>

		<?php if ( $selected_user_id > 0 ) : ?>
						<p class="nbuf-clear-selection">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-version-history' ) ); ?>" class="button">
			<?php esc_html_e( 'Clear Selection', 'nobloat-user-foundry' ); ?>
							</a>
						</p>
		<?php endif; ?>
				</form>
			</div>

			<!-- Version History Display -->
		<?php if ( $selected_user_id > 0 ) : ?>
			<?php
			$user = get_userdata( $selected_user_id );
			if ( $user ) :
				?>
					<div class="nbuf-vh-admin-page nbuf-admin-card">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NBUF_Version_History::render_viewer() returns escaped HTML.
				echo NBUF_Version_History::render_viewer( $selected_user_id, 'admin', true );
				?>
					</div>
				<?php else : ?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'User not found.', 'nobloat-user-foundry' ); ?></p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Select a user above to view their profile version history.', 'nobloat-user-foundry' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		
		<?php
	}

	/**
	 * Get version history statistics
	 *
	 * @return array Statistics data.
	 */
	private static function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		/*
		 * Total versions (only for existing users).
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
		$total_versions = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i pv INNER JOIN %i u ON pv.user_id = u.ID',
				$table_name,
				$wpdb->users
			)
		);

		/*
		 * Total users with versions (only existing users).
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
		$total_users = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT pv.user_id) FROM %i pv INNER JOIN %i u ON pv.user_id = u.ID',
				$table_name,
				$wpdb->users
			)
		);

		/*
		* Database size.
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
		$size_result = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table_name ) );
     // phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$size_bytes = isset( $size_result->Data_length ) ? (int) $size_result->Data_length : 0;
     // phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$database_size = size_format( $size_bytes, 2 );

		/* Retention period */
		$retention_days = (int) NBUF_Options::get( 'nbuf_version_history_retention_days', 365 );

		/* Last cleanup */
		$last_cleanup       = NBUF_Options::get( 'nbuf_last_vh_cleanup', '' );
		$last_cleanup_count = (int) NBUF_Options::get( 'nbuf_last_vh_cleanup_count', 0 );

		if ( $last_cleanup ) {
			$last_cleanup = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_cleanup ) );
		} else {
			$last_cleanup = __( 'Never', 'nobloat-user-foundry' );
		}

		return array(
			'total_versions'     => $total_versions,
			'total_users'        => $total_users,
			'database_size'      => $database_size,
			'retention_days'     => $retention_days,
			'last_cleanup'       => $last_cleanup,
			'last_cleanup_count' => $last_cleanup_count,
		);
	}
}
