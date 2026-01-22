<?php
/**
 * Admin Audit Log Page
 *
 * Renders the admin audit log viewer page with statistics,
 * filters, export, and purge functionality.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Includes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Audit Log Page Class
 *
 * @since 1.4.0
 */
class NBUF_Admin_Audit_Log_Page {

	/**
	 * Initialize admin audit log page
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 14 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_early_actions' ) );
	}

	/**
	 * Handle export/purge/bulk actions early before any output
	 *
	 * @return void
	 */
	public static function handle_early_actions(): void {
		/*
		 * Only process on our page.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below for specific actions
		if ( ! isset( $_GET['page'] ) && ! isset( $_POST['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Nonce verified below for specific actions
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : sanitize_text_field( wp_unslash( $_POST['page'] ) );

		if ( 'nobloat-foundry-admin-audit-log' !== $page ) {
			return;
		}

		/* Security check */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Handle export action */
		if ( isset( $_GET['action'] ) && 'export' === $_GET['action'] ) {
			/* Verify nonce for export action */
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_export_admin_logs' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
			}
			self::handle_export();
		}

		/* Handle purge action */
		if ( isset( $_GET['action'] ) && 'purge' === $_GET['action'] ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nbuf_purge_admin_logs' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
			}
			self::handle_purge();
		}

		/* Handle bulk delete action */
		if ( isset( $_POST['action'] ) && 'delete' === $_POST['action'] && ! empty( $_POST['log_id'] ) ) {
			self::handle_bulk_delete();
		}
		/* Also check action2 for bottom bulk action dropdown */
		if ( isset( $_POST['action2'] ) && 'delete' === $_POST['action2'] && ! empty( $_POST['log_id'] ) ) {
			self::handle_bulk_delete();
		}
	}

	/**
	 * Handle bulk delete action
	 *
	 * @return void
	 */
	private static function handle_bulk_delete(): void {
		/* Verify nonce */
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-admin_audit_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Validate log_id exists and is array */
		if ( ! isset( $_POST['log_id'] ) || ! is_array( $_POST['log_id'] ) ) {
			return;
		}

		$log_ids = array_map( 'absint', $_POST['log_id'] );

		if ( empty( $log_ids ) ) {
			return;
		}

		/* Delete logs */
		global $wpdb;
		$table        = $wpdb->prefix . 'nbuf_admin_audit_log';
		$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic IN clause with spread operator.
		$deleted_count = $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", $table, ...$log_ids ) );

		/* Log the bulk deletion */
		if ( false !== $deleted_count && $deleted_count > 0 ) {
			NBUF_Admin_Audit_Log::log(
				get_current_user_id(),
				'logs_purged',
				'success',
				sprintf( 'Deleted %d admin audit log entries', $deleted_count ),
				null,
				array(
					'deleted_count' => $deleted_count,
					'log_ids'       => $log_ids,
				)
			);

			wp_safe_redirect( admin_url( 'admin.php?page=nobloat-foundry-admin-audit-log&deleted=' . $deleted_count ) );
			exit;
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=nobloat-foundry-admin-audit-log&delete_failed=1' ) );
			exit;
		}
	}

	/**
	 * Enqueue admin scripts and styles for this page
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_scripts( string $hook ): void {
		/* Only load on admin audit log page */
		$allowed_hooks = array(
			'nobloat-foundry_page_nobloat-foundry-admin-audit-log',
			'user-foundry_page_nobloat-foundry-admin-audit-log',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		/* Admin CSS and JS are loaded globally via NBUF_Settings::enqueue_admin_assets() */
	}

	/**
	 * Add admin audit log menu page
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_submenu_page(
			'nobloat-foundry',
			__( 'Admin Actions Log', 'nobloat-user-foundry' ),
			__( 'Admin Actions Log', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-admin-audit-log',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the admin audit log page
	 *
	 * @return void
	 */
	public static function render() {
		/* Security check */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
		}

		/* Export and purge actions are handled in handle_early_actions() via admin_init */

		/* Get statistics */
		$stats = NBUF_Admin_Audit_Log::get_stats();

		/* Load list table */
		if ( ! class_exists( 'NBUF_Admin_Audit_Log_List_Table' ) ) {
			require_once NBUF_PLUGIN_DIR . 'includes/class-nbuf-admin-audit-log-list-table.php';
		}

		$list_table = new NBUF_Admin_Audit_Log_List_Table();
		$list_table->prepare_items();

		/* Render page */
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Admin Actions Log', 'nobloat-user-foundry' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=gdpr&subtab=logging' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Settings', 'nobloat-user-foundry' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php self::render_notices(); ?>

			<!-- Statistics -->
			<div class="nbuf-log-stats">
				<div class="nbuf-log-stats-header">
					<h3><?php esc_html_e( 'Database Statistics', 'nobloat-user-foundry' ); ?></h3>
					<div class="nbuf-log-stats-actions">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=nobloat-foundry-admin-audit-log&action=export' ), 'nbuf_export_admin_logs' ) ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export', 'nobloat-user-foundry' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=nobloat-foundry-admin-audit-log&action=purge' ), 'nbuf_purge_admin_logs' ) ); ?>" class="button button-link-delete nbuf-purge-logs-btn">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Purge', 'nobloat-user-foundry' ); ?>
						</a>
					</div>
				</div>
				<div class="nbuf-log-stat-item">
					<table class="nbuf-log-stat-table">
						<tr><td><?php esc_html_e( 'Total Entries', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Today', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'This Week', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['week'] ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'This Month', 'nobloat-user-foundry' ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['month'] ) ); ?></td></tr>
					</table>
				</div>
			</div>

			<!-- Log Table -->
			<form method="post">
				<input type="hidden" name="page" value="nobloat-foundry-admin-audit-log" />
				<?php
				$list_table->search_box( __( 'Search logs', 'nobloat-user-foundry' ), 'admin-audit-log-search' );
				$list_table->display();
				?>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			/* Confirm purge action */
			$('.nbuf-purge-logs-btn').on('click', function(e) {
				if (!confirm('<?php echo esc_js( __( '⚠️ PERMANENTLY DELETE ALL ADMIN ACTION LOGS?\n\n⚠️ WARNING: Admin logs may be required for compliance.\n\nThis cannot be undone.\n\nType DELETE to confirm.', 'nobloat-user-foundry' ) ); ?>')) {
					e.preventDefault();
					return false;
				}

				var userInput = prompt('<?php echo esc_js( __( 'Type DELETE to confirm:', 'nobloat-user-foundry' ) ); ?>');
				if (userInput !== 'DELETE') {
					e.preventDefault();
					alert('<?php echo esc_js( __( 'Purge cancelled - text did not match.', 'nobloat-user-foundry' ) ); ?>');
					return false;
				}
			});

			/* View metadata modal */
			$('.nbuf-view-metadata').on('click', function(e) {
				e.preventDefault();
				var metadata = $(this).data('metadata');
				var formatted = JSON.stringify(metadata, null, 2);

				var modal = $('<div class="nbuf-metadata-modal">' +
					'<div class="nbuf-metadata-overlay"></div>' +
					'<div class="nbuf-metadata-content">' +
					'<h3><?php echo esc_js( __( 'Metadata Details', 'nobloat-user-foundry' ) ); ?></h3>' +
					'<pre>' + formatted + '</pre>' +
					'<button class="button nbuf-close-modal"><?php echo esc_js( __( 'Close', 'nobloat-user-foundry' ) ); ?></button>' +
					'</div>' +
					'</div>');

				$('body').append(modal);

				$('.nbuf-metadata-overlay, .nbuf-close-modal').on('click', function() {
					modal.remove();
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render admin notices
	 *
	 * @return void
	 */
	private static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for displaying admin notices.
		/* Deletion success notice */
		if ( isset( $_GET['deleted'] ) ) {
			$count = absint( $_GET['deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: Number of deleted log entries */
					esc_html( _n( '%d log entry deleted.', '%d log entries deleted.', $count, 'nobloat-user-foundry' ) ),
					esc_html( number_format_i18n( $count ) )
				)
			);
		}

		/* Purge success notice */
		if ( isset( $_GET['purged'] ) ) {
			$count = absint( $_GET['purged'] );
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: Number of purged log entries */
					esc_html__( 'Purged %d admin action log entries.', 'nobloat-user-foundry' ),
					esc_html( number_format_i18n( $count ) )
				)
			);
		}

		/* Export success notice */
		if ( isset( $_GET['exported'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Log exported successfully.', 'nobloat-user-foundry' )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle CSV export
	 *
	 * @return void
	 */
	private static function handle_export() {
		/* Security check */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export logs.', 'nobloat-user-foundry' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for filter values during export, admin screen with capability check.
		/* Get filters from query string */
		$filters = array();
		if ( ! empty( $_GET['action_type'] ) ) {
			$filters['action_type'] = sanitize_text_field( wp_unslash( $_GET['action_type'] ) );
		}
		if ( ! empty( $_GET['action_status'] ) ) {
			$filters['action_status'] = sanitize_text_field( wp_unslash( $_GET['action_status'] ) );
		}
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/* Export to CSV */
		NBUF_Admin_Audit_Log::export_csv( $filters );
		exit;
	}

	/**
	 * Handle log purge
	 *
	 * @return void
	 */
	private static function handle_purge() {
		/* Security check */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to purge logs.', 'nobloat-user-foundry' ) );
		}

		/* Purge logs */
		$result = NBUF_Admin_Audit_Log::purge_all();

		/* Redirect with notice */
		wp_safe_redirect(
			admin_url( 'admin.php?page=nobloat-foundry-admin-audit-log&purged=' . $result['count'] )
		);
		exit;
	}
}
