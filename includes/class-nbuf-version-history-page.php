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

		wp_localize_script(
			'nbuf-version-history',
			'NBUF_VersionHistory',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'nbuf_version_history' ),
				'can_revert' => $can_revert ? true : false,
				'i18n'       => array(
					'registration'   => __( 'Registration', 'nobloat-user-foundry' ),
					'profile_update' => __( 'Profile Update', 'nobloat-user-foundry' ),
					'admin_update'   => __( 'Admin Update', 'nobloat-user-foundry' ),
					'import'         => __( 'Import', 'nobloat-user-foundry' ),
					'revert'         => __( 'Reverted', 'nobloat-user-foundry' ),
					'self'           => __( 'Self', 'nobloat-user-foundry' ),
					'admin'          => __( 'Admin', 'nobloat-user-foundry' ),
					'confirm_revert' => __( 'Are you sure you want to revert to this version? This will create a new version entry.', 'nobloat-user-foundry' ),
					'revert_success' => __( 'Profile reverted successfully.', 'nobloat-user-foundry' ),
					'revert_failed'  => __( 'Revert failed.', 'nobloat-user-foundry' ),
					'error'          => __( 'An error occurred.', 'nobloat-user-foundry' ),
					'before'         => __( 'Before:', 'nobloat-user-foundry' ),
					'after'          => __( 'After:', 'nobloat-user-foundry' ),
					'field'          => __( 'Field', 'nobloat-user-foundry' ),
					'before_value'   => __( 'Before', 'nobloat-user-foundry' ),
					'after_value'    => __( 'After', 'nobloat-user-foundry' ),
				),
			)
		);

		/* Enqueue Select2 for searchable user dropdown */
		wp_enqueue_style( 'nbuf-select2', NBUF_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.0.13' );
		wp_enqueue_script( 'nbuf-select2', NBUF_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array( 'jquery' ), '4.0.13', true );

		/* Initialize Select2 on user dropdown */
		wp_add_inline_script(
			'nbuf-select2',
			'jQuery(document).ready(function($) {
				$("#user_id").select2({
					placeholder: "' . esc_js( __( 'Search for a user...', 'nobloat-user-foundry' ) ) . '",
					allowClear: true,
					width: "300px"
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

		<?php
		wp_dropdown_users(
			array(
				'name'             => 'user_id',
				'id'               => 'user_id',
				'selected'         => $selected_user_id,
				'show_option_none' => __( '— Select User —', 'nobloat-user-foundry' ),
				'class'            => 'regular-text',
			)
		);
		?>

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
