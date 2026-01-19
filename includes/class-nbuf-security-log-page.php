<?php
/**
 * NoBloat User Foundry - Security Log Page
 *
 * Handles admin page registration and rendering for security log viewer.
 * Includes menu registration, page display, export, and purge functionality.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security log admin page handler
 *
 * @since 1.4.0
 */
class NBUF_Security_Log_Page {


	/**
	 * Initialize security log page
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 13 );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_purge' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_bulk_delete' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_bulk_unblock' ) );
	}

	/**
	 * Add security log menu page
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_submenu_page(
			'nobloat-foundry',
			__( 'Security Audit Log', 'nobloat-user-foundry' ),
			__( 'Security Audit Log', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-security-log',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render security log page
	 *
	 * @return void
	 */
	public static function render_page(): void {
		/* Check user capabilities */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nobloat-user-foundry' ) );
		}

		/* Handle admin notices */
		self::display_notices();

		/* Create list table instance */
		$list_table = new NBUF_Security_Log_List_Table();
		$list_table->prepare_items();

		/* Get statistics */
		$stats = NBUF_Security_Log::get_stats();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Security Audit Log', 'nobloat-user-foundry' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=gdpr&subtab=logging' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Settings', 'nobloat-user-foundry' ); ?>
			</a>
			<hr class="wp-header-end">

		<?php if ( ! NBUF_Options::get( 'nbuf_security_log_enabled', true ) ) : ?>
				<div class="notice notice-warning">
					<p>
			<?php
			printf(
			/* translators: %s: Settings page URL */
				esc_html__( 'Security logging is currently disabled. Enable it in %s to start tracking security events.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=gdpr&subtab=logging' ) ) . '">' . esc_html__( 'Settings → GDPR → Logging', 'nobloat-user-foundry' ) . '</a>'
			);
			?>
					</p>
				</div>
		<?php endif; ?>

			<!-- Statistics -->
			<div class="nbuf-log-stats">
				<div class="nbuf-log-stats-header">
					<h3><?php esc_html_e( 'Database Statistics', 'nobloat-user-foundry' ); ?></h3>
					<?php if ( NBUF_Options::get( 'nbuf_security_log_enabled', true ) ) : ?>
					<div class="nbuf-log-stats-actions">
						<a href="<?php echo esc_url( self::get_export_url() ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export', 'nobloat-user-foundry' ); ?>
						</a>
						<a href="<?php echo esc_url( self::get_purge_url() ); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete ALL security logs? This action cannot be undone.', 'nobloat-user-foundry' ); ?>');">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Purge', 'nobloat-user-foundry' ); ?>
						</a>
					</div>
					<?php endif; ?>
				</div>
				<div class="nbuf-log-stat-item">
					<table class="nbuf-log-stat-table">
						<tr><td><?php esc_html_e( 'Total Entries', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( number_format( $stats['total_entries'] ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Database Size', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( $stats['database_size'] ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Oldest Entry', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( $stats['oldest_entry'] ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Retention', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( self::get_retention_label() ); ?></td></tr>
					</table>
				</div>
			</div>

			<!-- List table -->
			<form method="get">
				<input type="hidden" name="page" value="nobloat-foundry-security-log">
		<?php
		$list_table->search_box( __( 'Search logs', 'nobloat-user-foundry' ), 'security-log-search' );
		$list_table->display();
		?>
			</form>
		</div>

		<!-- Modal JavaScript -->
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			/* View context modal */
			$('.nbuf-view-context').on('click', function(e) {
				e.preventDefault();
				var context = $(this).data('context');
				var formatted = JSON.stringify(context, null, 2);

				var modal = $('<div class="nbuf-metadata-modal">' +
					'<div class="nbuf-metadata-overlay"></div>' +
					'<div class="nbuf-metadata-content">' +
					'<h3><?php echo esc_js( __( 'Event Details', 'nobloat-user-foundry' ) ); ?></h3>' +
					'<pre>' + $('<div>').text(formatted).html() + '</pre>' +
					'<button class="button nbuf-close-modal"><?php echo esc_js( __( 'Close', 'nobloat-user-foundry' ) ); ?></button>' +
					'</div>' +
					'</div>');

				$('body').append(modal);

				/* Close on overlay click, button click, or Escape key */
				$('.nbuf-metadata-overlay, .nbuf-close-modal').on('click', function() {
					modal.remove();
				});
				$(document).on('keydown.nbufModal', function(e) {
					if (e.key === 'Escape') {
						modal.remove();
						$(document).off('keydown.nbufModal');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	private static function display_notices(): void {
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
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Security logs exported successfully.', 'nobloat-user-foundry' ) . '</p></div>';
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All security logs have been purged.', 'nobloat-user-foundry' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['unblocked'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
			$count = intval( $_GET['unblocked'] );
			if ( $count > 0 ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					/* translators: %d: number of unblocked IPs */
					esc_html( sprintf( _n( '%d IP address unblocked.', '%d IP addresses unblocked.', $count, 'nobloat-user-foundry' ), $count ) )
				);
			} else {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No IPs were unblocked. The selected entries may not have had any blocked login attempts.', 'nobloat-user-foundry' ) . '</p></div>';
			}
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
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		if ( ! isset( $_GET['action'] ) || 'nbuf_export_security_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce and capability FIRST before any processing */
		check_admin_referer( 'nbuf_export_security_logs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export logs', 'nobloat-user-foundry' ) );
		}

		/* Get current filters */
		$filters = array();
		if ( ! empty( $_GET['severity'] ) ) {
			$filters['severity'] = sanitize_text_field( wp_unslash( $_GET['severity'] ) );
		}
		if ( ! empty( $_GET['event_type'] ) ) {
			$filters['event_type'] = sanitize_text_field( wp_unslash( $_GET['event_type'] ) );
		}
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}

		/* Generate CSV */
		$csv = NBUF_Security_Log::export_to_csv( $filters );

		/* Set headers for download */
		$filename = 'security-log-' . gmdate( 'Y-m-d-His' ) . '.csv';
		/* Remove any potential CRLF or special characters for security */
		$filename = preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV export data from NBUF_Security_Log::export_to_csv().
		echo $csv;
		exit;
	}

	/**
	 * Handle purge all logs
	 *
	 * @return void
	 */
	public static function handle_purge(): void {
		if ( ! isset( $_GET['action'] ) || 'nbuf_purge_security_logs' !== $_GET['action'] ) {
			return;
		}

		/* Check nonce */
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_purge_security_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to purge logs', 'nobloat-user-foundry' ) );
		}

		/* Purge all logs */
		$success = NBUF_Security_Log::purge_all_logs();

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
	 *
	 * @return void
	 */
	public static function handle_bulk_delete(): void {
		/* Early bail if not in admin or no page specified */
		if ( ! is_admin() || empty( $_REQUEST['page'] ) ) {
			return;
		}

		/* Check we're on the security log page */
		if ( 'nobloat-foundry-security-log' !== $_REQUEST['page'] ) {
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
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-security_logs' ) ) {
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
		NBUF_Security_Log::delete_logs( $log_ids );

		/* Discard any output from database operations */
		ob_end_clean();

		/* Redirect with success message */
		$redirect = admin_url( 'admin.php?page=nobloat-foundry-security-log' );
		$redirect = add_query_arg( 'deleted', count( $log_ids ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle bulk unblock IP action
	 *
	 * Clears login attempts for selected IPs to unblock them.
	 * Must run on admin_init before headers are sent.
	 *
	 * @return void
	 */
	public static function handle_bulk_unblock(): void {
		/* Early bail if not in admin or no page specified */
		if ( ! is_admin() || empty( $_REQUEST['page'] ) ) {
			return;
		}

		/* Check we're on the security log page */
		if ( 'nobloat-foundry-security-log' !== $_REQUEST['page'] ) {
			return;
		}

		/* Must have selected items - check this early */
		if ( empty( $_REQUEST['log_id'] ) || ! is_array( $_REQUEST['log_id'] ) ) {
			return;
		}

		/* Check for unblock_ip action (top or bottom dropdown) */
		$action  = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) : '';

		if ( 'unblock_ip' !== $action && 'unblock_ip' !== $action2 ) {
			return;
		}

		/* Verify nonce */
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-security_logs' ) ) {
			wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
		}

		/* Check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to unblock IPs', 'nobloat-user-foundry' ) );
		}

		/* Start output buffering to catch any stray output */
		ob_start();

		/* Get IP addresses from selected log entries */
		$log_ids = array_map( 'intval', $_REQUEST['log_id'] );
		$ips     = NBUF_Security_Log::get_ips_from_log_ids( $log_ids );

		/* Clear login attempts for these IPs */
		$unblocked = 0;
		if ( ! empty( $ips ) ) {
			$unblocked = self::clear_login_attempts_for_ips( $ips );
		}

		/* Discard any output from database operations */
		ob_end_clean();

		/* Redirect with success message */
		$redirect = admin_url( 'admin.php?page=nobloat-foundry-security-log' );
		$redirect = add_query_arg( 'unblocked', $unblocked, $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Clear login attempts for specified IP addresses
	 *
	 * @param  array<int, string> $ips Array of IP addresses to unblock.
	 * @return int Number of IPs unblocked.
	 */
	private static function clear_login_attempts_for_ips( array $ips ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_login_attempts';
		$count      = 0;

		foreach ( $ips as $ip ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security unblock operation.
			$deleted = $wpdb->delete(
				$table_name,
				array( 'ip_address' => $ip ),
				array( '%s' )
			);

			if ( false !== $deleted && $deleted > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get export URL with nonce
	 *
	 * @return string Export URL.
	 */
	private static function get_export_url() {
		$url = admin_url( 'admin.php?page=nobloat-foundry-security-log' );
		$url = add_query_arg( 'action', 'nbuf_export_security_logs', $url );

		/*
		 * Preserve current filters
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['severity'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'severity', sanitize_text_field( wp_unslash( $_GET['severity'] ) ), $url );
		}
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['event_type'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'event_type', sanitize_text_field( wp_unslash( $_GET['event_type'] ) ), $url );
		}
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['date_from'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'date_from', sanitize_text_field( wp_unslash( $_GET['date_from'] ) ), $url );
		}
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
		if ( ! empty( $_GET['date_to'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter preservation
			$url = add_query_arg( 'date_to', sanitize_text_field( wp_unslash( $_GET['date_to'] ) ), $url );
		}

		return wp_nonce_url( $url, 'nbuf_export_security_logs' );
	}

	/**
	 * Get purge URL with nonce
	 *
	 * @return string Purge URL.
	 */
	private static function get_purge_url() {
		$url = admin_url( 'admin.php?page=nobloat-foundry-security-log' );
		$url = add_query_arg( 'action', 'nbuf_purge_security_logs', $url );
		return wp_nonce_url( $url, 'nbuf_purge_security_logs' );
	}

	/**
	 * Get retention period label
	 *
	 * @return string Retention label.
	 */
	private static function get_retention_label() {
		$retention = NBUF_Options::get( 'nbuf_security_log_retention', '365days' );

		$labels = array(
			'7days'   => __( '7 Days', 'nobloat-user-foundry' ),
			'30days'  => __( '30 Days', 'nobloat-user-foundry' ),
			'90days'  => __( '90 Days', 'nobloat-user-foundry' ),
			'180days' => __( '6 Months', 'nobloat-user-foundry' ),
			'365days' => __( '1 Year', 'nobloat-user-foundry' ),
			'2years'  => __( '2 Years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever', 'nobloat-user-foundry' ),
		);

		return isset( $labels[ $retention ] ) ? $labels[ $retention ] : $retention;
	}
}
