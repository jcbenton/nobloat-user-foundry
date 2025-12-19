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

/**
 * Audit log admin page handler
 *
 * @since 1.0.0
 */
class NBUF_Audit_Log_Page {


	/**
	 * Initialize audit log page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 15 );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_purge' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_bulk_delete' ) );
	}

	/**
	 * Add audit log menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'nobloat-foundry',
			__( 'User Audit Log', 'nobloat-user-foundry' ),
			__( 'User Audit Log', 'nobloat-user-foundry' ),
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

			<!-- Settings Link -->
			<div class="notice notice-info nbuf-notice-margin">
				<p>
		<?php
		printf(
		/* translators: %s: Settings page URL */
			esc_html__( 'Configure user audit log settings in %s', 'nobloat-user-foundry' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=audit-log' ) ) . '">' . esc_html__( 'Settings → Tools → Audit Log', 'nobloat-user-foundry' ) . '</a>'
		);
		?>
				</p>
			</div>

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
			<div class="nbuf-stats-box nbuf-admin-card">
				<h3><?php esc_html_e( 'Database Statistics', 'nobloat-user-foundry' ); ?></h3>
				<table class="widefat nbuf-stats-table">
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
		/*
		 * Success messages
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['deleted'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
			$count = intval( $_GET['deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of deleted entries */
				esc_html( sprintf( _n( '%d log entry deleted.', '%d log entries deleted.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['exported'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Audit logs exported successfully.', 'nobloat-user-foundry' ) . '</p></div>';
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All audit logs have been purged.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		/*
		 * Error messages
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error message display
		if ( ! empty( $_GET['error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'An error occurred. Please try again.', 'nobloat-user-foundry' ) . '</p></div>';
		}
	}

	/**
	 * Handle CSV export
	 */
	public static function handle_export() {
		/* Only process on our page */
		if ( ! isset( $_GET['page'] ) || 'nobloat-foundry-user-log' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'nbuf_export_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_export_logs' ) ) {
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
		$filename = 'audit-log-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV export data from NBUF_Audit_Log::export_to_csv().
		echo $csv;
		exit;
	}

	/**
	 * Handle purge all logs
	 */
	public static function handle_purge() {
		/* Only process on our page */
		if ( ! isset( $_GET['page'] ) || 'nobloat-foundry-user-log' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'nbuf_purge_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_purge_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to purge logs', 'nobloat-user-foundry' ) );
		}

		/* Buffer any stray output from database operations */
		ob_start();

		/* Purge all logs */
		$success = NBUF_Audit_Log::purge_all_logs();

		/* Discard any output */
		ob_end_clean();

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
	 * Handle bulk delete action
	 *
	 * Must run on admin_init before headers are sent.
	 */
	public static function handle_bulk_delete() {
		/* Early bail if not in admin or no page specified */
		if ( ! is_admin() || empty( $_REQUEST['page'] ) ) {
			return;
		}

		/* Check we're on the user audit log page */
		if ( 'nobloat-foundry-user-log' !== $_REQUEST['page'] ) {
			return;
		}

		/* Must have selected items - check this early */
		if ( empty( $_REQUEST['log_id'] ) || ! is_array( $_REQUEST['log_id'] ) ) {
			return;
		}

		/*
		 * Check for bulk delete action (top or bottom dropdown).
		 * WP_List_Table sends: action=delete (top) OR action2=delete (bottom).
		 * The other dropdown is typically '-1' (default "Bulk actions" option).
		 */
		$action  = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) : '';

		if ( 'delete' !== $action && 'delete' !== $action2 ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-audit_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete logs', 'nobloat-user-foundry' ) );
		}

		/* Start output buffering to catch any stray output */
		ob_start();

		/* Delete selected logs (already validated above) */
		$log_ids = array_map( 'intval', $_REQUEST['log_id'] );
		NBUF_Audit_Log::delete_logs( $log_ids );

		/* Discard any output from database operations */
		ob_end_clean();

		/* Redirect with success message */
		$redirect = admin_url( 'admin.php?page=nobloat-foundry-user-log' );
		$redirect = add_query_arg( 'deleted', count( $log_ids ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Get export URL with nonce
	 *
	 * @return string Export URL.
	 */
	private static function get_export_url() {
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( 'action', 'nbuf_export_logs', $url );

		/*
		 * Preserve current filters
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['event_type'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'event_type', sanitize_text_field( wp_unslash( $_GET['event_type'] ) ), $url );
		}
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['event_status'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'event_status', sanitize_text_field( wp_unslash( $_GET['event_status'] ) ), $url );
		}

		return wp_nonce_url( $url, 'nbuf_export_logs' );
	}

	/**
	 * Get purge URL with nonce
	 *
	 * @return string Purge URL.
	 */
	private static function get_purge_url() {
		$url = admin_url( 'admin.php?page=nobloat-foundry-user-log' );
		$url = add_query_arg( 'action', 'nbuf_purge_logs', $url );
		return wp_nonce_url( $url, 'nbuf_purge_logs' );
	}

	/**
	 * Get retention period label
	 *
	 * @return string Retention label.
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
