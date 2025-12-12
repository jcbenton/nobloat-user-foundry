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
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 13 );
		add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_purge' ) );
	}

	/**
	 * Add security log menu page
	 */
	public static function add_menu_page() {
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
	 */
	public static function render_page() {
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

		<?php if ( NBUF_Options::get( 'nbuf_security_log_enabled', true ) ) : ?>
				<a href="<?php echo esc_url( self::get_export_url() ); ?>" class="page-title-action">
			<?php esc_html_e( 'Export to CSV', 'nobloat-user-foundry' ); ?>
				</a>
				<a href="<?php echo esc_url( self::get_purge_url() ); ?>" class="page-title-action" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete ALL security logs? This action cannot be undone.', 'nobloat-user-foundry' ); ?>');">
			<?php esc_html_e( 'Purge All Logs', 'nobloat-user-foundry' ); ?>
				</a>
		<?php endif; ?>

			<hr class="wp-header-end">

			<!-- Settings Link -->
			<div class="notice notice-info" style="margin-top: 20px;">
				<p>
		<?php
		printf(
		/* translators: %s: Settings page URL */
			esc_html__( 'Configure security audit log settings in %s', 'nobloat-user-foundry' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=security-log' ) ) . '">' . esc_html__( 'Settings → Tools → Security Log', 'nobloat-user-foundry' ) . '</a>'
		);
		?>
				</p>
			</div>

		<?php if ( ! NBUF_Options::get( 'nbuf_security_log_enabled', true ) ) : ?>
				<div class="notice notice-warning">
					<p>
			<?php
			printf(
			/* translators: %s: Settings page URL */
				esc_html__( 'Security logging is currently disabled. Enable it in %s to start tracking security events.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=security-log' ) ) . '">' . esc_html__( 'Settings → Tools → Security Log', 'nobloat-user-foundry' ) . '</a>'
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
				<input type="hidden" name="page" value="nobloat-foundry-security-log">
		<?php
		$list_table->search_box( __( 'Search logs', 'nobloat-user-foundry' ), 'security-log-search' );
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
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Security logs exported successfully.', 'nobloat-user-foundry' ) . '</p></div>';
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display
		if ( ! empty( $_GET['purged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All security logs have been purged.', 'nobloat-user-foundry' ) . '</p></div>';
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
	 */
	public static function handle_purge() {
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
	 * Get export URL with nonce
	 *
	 * @return string Export URL.
	 */
	private static function get_export_url() {
		$url = admin_url( 'admin.php' );
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
