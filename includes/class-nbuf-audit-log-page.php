<?php
/**
 * NoBloat User Foundry - Audit Log Page
 *
 * Handles admin page registration and rendering for audit log viewer.
 * Includes menu registration, page display, export, and purge functionality.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Audit_Log_Page {

	/**
	 * Initialize audit log page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_purge' ) );
	}

	/**
	 * Add audit log menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'nobloat-foundry',
			__( 'User Log', 'nobloat-user-foundry' ),
			__( 'User Log', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-user-log',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render audit log page
	 */
	public static function render_page() {
		/* Check user capabilities */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nobloat-user-foundry' ) );
		}

		/* Handle admin notices */
		self::display_notices();

		/* Create list table instance */
		$list_table = new NBUF_Audit_Log_List_Table();
		$list_table->prepare_items();

		/* Get statistics */
		$stats = NBUF_Audit_Log::get_stats();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'User Audit Log', 'nobloat-user-foundry' ); ?></h1>

			<?php if ( NBUF_Options::get( 'nbuf_audit_log_enabled', true ) ) : ?>
				<a href="<?php echo esc_url( self::get_export_url() ); ?>" class="page-title-action">
					<?php esc_html_e( 'Export to CSV', 'nobloat-user-foundry' ); ?>
				</a>
				<a href="<?php echo esc_url( self::get_purge_url() ); ?>" class="page-title-action" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete ALL audit logs? This action cannot be undone.', 'nobloat-user-foundry' ); ?>');">
					<?php esc_html_e( 'Purge All Logs', 'nobloat-user-foundry' ); ?>
				</a>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php if ( ! NBUF_Options::get( 'nbuf_audit_log_enabled', true ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: Settings page URL */
							esc_html__( 'Audit logging is currently disabled. Enable it in %s to start tracking user activity.', 'nobloat-user-foundry' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=audit-log' ) ) . '">' . esc_html__( 'Settings > Tools > Audit Log', 'nobloat-user-foundry' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Statistics -->
			<div class="nbuf-stats-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0; border-radius: 4px;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Database Statistics', 'nobloat-user-foundry' ); ?></h3>
				<table class="widefat" style="max-width: 600px;">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Total Entries:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( number_format( $stats['total_entries'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Database Size:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $stats['database_size'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Oldest Entry:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $stats['oldest_entry'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Last Cleanup:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( $stats['last_cleanup'] ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Retention Period:', 'nobloat-user-foundry' ); ?></strong></td>
							<td><?php echo esc_html( self::get_retention_label() ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- List table -->
			<form method="get">
				<input type="hidden" name="page" value="nobloat-foundry-user-log">
				<?php
				$list_table->search_box( __( 'Search logs', 'nobloat-user-foundry' ), 'audit-log-search' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Display admin notices
	 */
	private static function display_notices() {
		/* Success messages */
		if ( ! empty( $_GET['deleted'] ) ) {
			$count = intval( $_GET['deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of deleted entries */
				esc_html( sprintf( _n( '%d log entry deleted.', '%d log entries deleted.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		if ( ! empty( $_GET['exported'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Audit logs exported successfully.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		if ( ! empty( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All audit logs have been purged.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		/* Error messages */
		if ( ! empty( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'An error occurred. Please try again.', 'nobloat-user-foundry' ) . '</p></div>';
		}
	}

	/**
	 * Handle CSV export
	 */
	public static function handle_export() {
		if ( ! isset( $_GET['action'] ) || 'nbuf_export_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'nbuf_export_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export logs', 'nobloat-user-foundry' ) );
		}

		/* Get current filters */
		$filters = array();
		if ( ! empty( $_GET['user_id'] ) ) {
			$filters['user_id'] = intval( $_GET['user_id'] );
		}
		if ( ! empty( $_GET['event_type'] ) ) {
			$filters['event_type'] = sanitize_text_field( wp_unslash( $_GET['event_type'] ) );
		}
		if ( ! empty( $_GET['event_status'] ) ) {
			$filters['event_status'] = sanitize_text_field( wp_unslash( $_GET['event_status'] ) );
		}

		/* Generate CSV */
		$csv = NBUF_Audit_Log::export_to_csv( $filters );

		/* Set headers for download */
		$filename = 'audit-log-' . date( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv;
		exit;
	}

	/**
	 * Handle purge all logs
	 */
	public static function handle_purge() {
		if ( ! isset( $_GET['action'] ) || 'nbuf_purge_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'nbuf_purge_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to purge logs', 'nobloat-user-foundry' ) );
		}

		/* Purge all logs */
		$success = NBUF_Audit_Log::purge_all_logs();

		/* Redirect with result */
		$redirect = remove_query_arg( array( 'action', '_wpnonce' ) );
		if ( $success ) {
			$redirect = add_query_arg( 'purged', '1', $redirect );
		} else {
			$redirect = add_query_arg( 'error', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Get export URL with nonce
	 *
	 * @return string Export URL
	 */
	private static function get_export_url() {
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( 'action', 'nbuf_export_logs', $url );

		/* Preserve current filters */
		if ( ! empty( $_GET['event_type'] ) ) {
			$url = add_query_arg( 'event_type', sanitize_text_field( wp_unslash( $_GET['event_type'] ) ), $url );
		}
		if ( ! empty( $_GET['event_status'] ) ) {
			$url = add_query_arg( 'event_status', sanitize_text_field( wp_unslash( $_GET['event_status'] ) ), $url );
		}

		return wp_nonce_url( $url, 'nbuf_export_logs' );
	}

	/**
	 * Get purge URL with nonce
	 *
	 * @return string Purge URL
	 */
	private static function get_purge_url() {
		$url = admin_url( 'admin.php?page=nobloat-foundry-user-log' );
		$url = add_query_arg( 'action', 'nbuf_purge_logs', $url );
		return wp_nonce_url( $url, 'nbuf_purge_logs' );
	}

	/**
	 * Get retention period label
	 *
	 * @return string Retention label
	 */
	private static function get_retention_label() {
		$retention = NBUF_Options::get( 'nbuf_audit_log_retention', '90days' );

		$labels = array(
			'7days'   => __( '7 Days', 'nobloat-user-foundry' ),
			'30days'  => __( '30 Days', 'nobloat-user-foundry' ),
			'90days'  => __( '90 Days', 'nobloat-user-foundry' ),
			'180days' => __( '6 Months', 'nobloat-user-foundry' ),
			'1year'   => __( '1 Year', 'nobloat-user-foundry' ),
			'2years'  => __( '2 Years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever', 'nobloat-user-foundry' ),
		);

		return isset( $labels[ $retention ] ) ? $labels[ $retention ] : $retention;
	}
}
