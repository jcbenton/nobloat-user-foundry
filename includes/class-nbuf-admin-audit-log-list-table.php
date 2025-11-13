<?php
/**
 * Admin Audit Log List Table
 *
 * Displays admin actions on users and system settings with filtering,
 * sorting, pagination, and export capabilities.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Includes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load WP_List_Table if not already loaded */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin Audit Log List Table Class
 *
 * @since 1.4.0
 */
class NBUF_Admin_Audit_Log_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'admin_audit_log',
				'plural'   => 'admin_audit_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns
	 *
	 * @return array Columns array.
	 */
	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox" />',
			'created_at'      => __( 'Date/Time', 'nobloat-user-foundry' ),
			'admin_username'  => __( 'Admin', 'nobloat-user-foundry' ),
			'action_type'     => __( 'Action', 'nobloat-user-foundry' ),
			'target_username' => __( 'Target User', 'nobloat-user-foundry' ),
			'action_status'   => __( 'Status', 'nobloat-user-foundry' ),
			'action_message'  => __( 'Details', 'nobloat-user-foundry' ),
			'ip_address'      => __( 'IP Address', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'created_at'      => array( 'created_at', true ),
			'admin_username'  => array( 'admin_username', false ),
			'action_type'     => array( 'action_type', false ),
			'target_username' => array( 'target_username', false ),
			'action_status'   => array( 'action_status', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array Bulk actions.
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @param object $item Log entry.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_id[]" value="%d" />', $item['id'] );
	}

	/**
	 * Render date/time column
	 *
	 * @param object $item Log entry.
	 * @return string Formatted date/time.
	 */
	public function column_created_at( $item ) {
		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( $item['created_at'] ),
			esc_html( mysql2date( 'Y-m-d H:i:s', $item['created_at'] ) )
		);
	}

	/**
	 * Render admin username column
	 *
	 * @param object $item Log entry.
	 * @return string Admin username with link.
	 */
	public function column_admin_username( $item ) {
		$admin_id = absint( $item['admin_id'] );
		$username = esc_html( $item['admin_username'] );

		if ( $admin_id && get_userdata( $admin_id ) ) {
			return sprintf(
				'<a href="%s">%s</a> <span class="description">(ID: %d)</span>',
				esc_url( get_edit_user_link( $admin_id ) ),
				$username,
				$admin_id
			);
		}

		return $username . ' <span class="description">(deleted)</span>';
	}

	/**
	 * Render action type column
	 *
	 * @param object $item Log entry.
	 * @return string Action type badge.
	 */
	public function column_action_type( $item ) {
		$action_type = $item['action_type'];
		$labels      = array(
			'user_deleted'            => __( 'User Deleted', 'nobloat-user-foundry' ),
			'user_created'            => __( 'User Created', 'nobloat-user-foundry' ),
			'password_reset_by_admin' => __( 'Password Reset', 'nobloat-user-foundry' ),
			'role_changed'            => __( 'Role Changed', 'nobloat-user-foundry' ),
			'bulk_action'             => __( 'Bulk Action', 'nobloat-user-foundry' ),
			'settings_changed'        => __( 'Settings Changed', 'nobloat-user-foundry' ),
			'manual_verify'           => __( 'Manual Verify', 'nobloat-user-foundry' ),
			'manual_unverify'         => __( 'Manual Unverify', 'nobloat-user-foundry' ),
			'account_merge'           => __( 'Account Merge', 'nobloat-user-foundry' ),
			'profile_edited_by_admin' => __( 'Profile Edited', 'nobloat-user-foundry' ),
			'email_changed_by_admin'  => __( 'Email Changed', 'nobloat-user-foundry' ),
			'2fa_reset_by_admin'      => __( '2FA Reset', 'nobloat-user-foundry' ),
			'logs_purged'             => __( 'Logs Purged', 'nobloat-user-foundry' ),
		);

		$label = isset( $labels[ $action_type ] ) ? $labels[ $action_type ] : esc_html( $action_type );

		return sprintf(
			'<span class="nbuf-action-badge nbuf-action-%s">%s</span>',
			esc_attr( $action_type ),
			esc_html( $label )
		);
	}

	/**
	 * Render target username column
	 *
	 * @param object $item Log entry.
	 * @return string Target username with link or N/A.
	 */
	public function column_target_username( $item ) {
		if ( empty( $item['target_user_id'] ) ) {
			return '<span class="description">' . esc_html__( 'N/A (settings)', 'nobloat-user-foundry' ) . '</span>';
		}

		$user_id  = absint( $item['target_user_id'] );
		$username = esc_html( $item['target_username'] );

		if ( $user_id && get_userdata( $user_id ) ) {
			return sprintf(
				'<a href="%s">%s</a> <span class="description">(ID: %d)</span>',
				esc_url( get_edit_user_link( $user_id ) ),
				$username,
				$user_id
			);
		}

		return $username . ' <span class="description">(deleted)</span>';
	}

	/**
	 * Render action status column
	 *
	 * @param object $item Log entry.
	 * @return string Status badge.
	 */
	public function column_action_status( $item ) {
		$status = $item['action_status'];
		$badges = array(
			'success' => '<span class="nbuf-status-badge nbuf-status-success">✓ ' . __( 'Success', 'nobloat-user-foundry' ) . '</span>',
			'failure' => '<span class="nbuf-status-badge nbuf-status-failure">✗ ' . __( 'Failure', 'nobloat-user-foundry' ) . '</span>',
		);

		return isset( $badges[ $status ] ) ? $badges[ $status ] : esc_html( $status );
	}

	/**
	 * Render action message column
	 *
	 * @param object $item Log entry.
	 * @return string Action message with metadata.
	 */
	public function column_action_message( $item ) {
		$message = esc_html( $item['action_message'] );

		/* Show metadata if available */
		if ( ! empty( $item['metadata'] ) ) {
			$metadata = json_decode( $item['metadata'], true );
			if ( is_array( $metadata ) && ! empty( $metadata ) ) {
				$message .= sprintf(
					' <button type="button" class="button button-small nbuf-view-metadata" data-metadata="%s">%s</button>',
					esc_attr( wp_json_encode( $metadata ) ),
					esc_html__( 'View Details', 'nobloat-user-foundry' )
				);
			}
		}

		return $message;
	}

	/**
	 * Render IP address column
	 *
	 * @param object $item Log entry.
	 * @return string IP address.
	 */
	public function column_ip_address( $item ) {
		return esc_html( $item['ip_address'] );
	}

	/**
	 * Default column renderer
	 *
	 * @param object $item        Log entry.
	 * @param string $column_name Column name.
	 * @return string Column value.
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		/* Set columns */
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/* Process bulk actions */
		$this->process_bulk_action();

		/* Get filters */
		$filters = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for filtering, admin screen with capability check.
		if ( ! empty( $_GET['admin_id'] ) ) {
			$filters['admin_id'] = absint( $_GET['admin_id'] );
		}

		if ( ! empty( $_GET['target_user_id'] ) ) {
			$filters['target_user_id'] = absint( $_GET['target_user_id'] );
		}

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

		/* Pagination */
		$per_page     = 25;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		/* Get logs */
		$logs        = NBUF_Admin_Audit_Log::get_logs( $filters, $per_page, $offset );
		$total_items = NBUF_Admin_Audit_Log::get_log_count( $filters );

		/* Set items and pagination */
		$this->items = $logs;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		/* Check nonce */
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-admin_audit_logs' ) ) {
			return;
		}

		/* Delete action */
		if ( 'delete' === $this->current_action() && ! empty( $_POST['log_id'] ) ) {
			$log_ids = array_map( 'absint', $_POST['log_id'] );

			/* Delete logs */
			global $wpdb;
			$table        = $wpdb->prefix . 'nbuf_admin_audit_log';
			$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			$query        = "DELETE FROM {$table} WHERE id IN ({$placeholders})";
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic IN clause with array of IDs.
			$deleted_count = $wpdb->query( $wpdb->prepare( $query, $log_ids ) );

			/* Log the bulk deletion to admin audit log only if deletion succeeded */
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

				wp_safe_redirect( admin_url( 'admin.php?page=nbuf-admin-audit-log&deleted=' . $deleted_count ) );
			} else {
				/* Log failure */
				NBUF_Admin_Audit_Log::log(
					get_current_user_id(),
					'logs_purged',
					'failure',
					'Failed to delete admin audit log entries',
					null,
					array(
						'attempted_count' => count( $log_ids ),
						'log_ids'         => $log_ids,
					)
				);

				wp_safe_redirect( admin_url( 'admin.php?page=nbuf-admin-audit-log&delete_failed=1' ) );
			}
			exit;
		}
	}

	/**
	 * Display filter controls
	 *
	 * @param string $which Top or bottom table navigation.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<select name="action_type">
				<option value=""><?php esc_html_e( 'All Action Types', 'nobloat-user-foundry' ); ?></option>
				<?php
				$action_types = array(
					'user_deleted'            => __( 'User Deleted', 'nobloat-user-foundry' ),
					'user_created'            => __( 'User Created', 'nobloat-user-foundry' ),
					'password_reset_by_admin' => __( 'Password Reset', 'nobloat-user-foundry' ),
					'role_changed'            => __( 'Role Changed', 'nobloat-user-foundry' ),
					'bulk_action'             => __( 'Bulk Action', 'nobloat-user-foundry' ),
					'settings_changed'        => __( 'Settings Changed', 'nobloat-user-foundry' ),
					'manual_verify'           => __( 'Manual Verify', 'nobloat-user-foundry' ),
					'manual_unverify'         => __( 'Manual Unverify', 'nobloat-user-foundry' ),
					'profile_edited_by_admin' => __( 'Profile Edited', 'nobloat-user-foundry' ),
				);
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for displaying current filter value.
				$selected = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
				foreach ( $action_types as $value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $value ),
						selected( $selected, $value, false ),
						esc_html( $label )
					);
				}
				?>
			</select>

			<select name="action_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'nobloat-user-foundry' ); ?></option>
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for displaying current filter value. ?>
				<option value="success" <?php selected( isset( $_GET['action_status'] ) ? sanitize_text_field( wp_unslash( $_GET['action_status'] ) ) : '', 'success' ); ?>><?php esc_html_e( 'Success', 'nobloat-user-foundry' ); ?></option>
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for displaying current filter value. ?>
				<option value="failure" <?php selected( isset( $_GET['action_status'] ) ? sanitize_text_field( wp_unslash( $_GET['action_status'] ) ) : '', 'failure' ); ?>><?php esc_html_e( 'Failure', 'nobloat-user-foundry' ); ?></option>
			</select>

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for displaying current filter values. ?>
			<label><?php esc_html_e( 'From:', 'nobloat-user-foundry' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '' ); ?>" /></label>
			<label><?php esc_html_e( 'To:', 'nobloat-user-foundry' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '' ); ?>" /></label>
			<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

			<?php submit_button( __( 'Filter', 'nobloat-user-foundry' ), 'secondary', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
