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
	 * @return array<string, string> Columns array.
	 */
	public function get_columns(): array {
		return array(
			'cb'              => '<input type="checkbox" />',
			'created_at'      => __( 'Date/Time', 'nobloat-user-foundry' ),
			'admin_username'  => __( 'Admin', 'nobloat-user-foundry' ),
			'action_type'     => __( 'Action', 'nobloat-user-foundry' ),
			'target_username' => __( 'Target User', 'nobloat-user-foundry' ),
			'action_status'   => __( 'Status', 'nobloat-user-foundry' ),
			'ip_address'      => __( 'IP Address', 'nobloat-user-foundry' ),
			'action_message'  => __( 'Details', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array<string, array{0: string, 1: bool}> Sortable columns.
	 */
	public function get_sortable_columns(): array {
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
	 * @return array<string, string> Bulk actions.
	 */
	public function get_bulk_actions(): array {
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
	 * @return string Formatted date/time in user's local timezone.
	 */
	public function column_created_at( $item ) {
		/* Convert UTC to user's browser timezone (from cookie) */
		$local_time = NBUF_Options::format_local_time( $item['created_at'] );
		return sprintf(
			'<span title="%s">%s</span>',
			/* translators: %s: UTC timestamp */
			esc_attr( sprintf( __( 'UTC: %s', 'nobloat-user-foundry' ), $item['created_at'] ) ),
			esc_html( $local_time )
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
			/* User Management */
			'user_created'             => __( 'User Created', 'nobloat-user-foundry' ),
			'user_deleted'             => __( 'User Deleted', 'nobloat-user-foundry' ),
			'role_changed'             => __( 'Role Changed', 'nobloat-user-foundry' ),
			'profile_edited_by_admin'  => __( 'Profile Edited', 'nobloat-user-foundry' ),
			'email_changed_by_admin'   => __( 'Email Changed', 'nobloat-user-foundry' ),
			'password_reset_by_admin'  => __( 'Password Reset', 'nobloat-user-foundry' ),
			'manual_verify'            => __( 'Manual Verify', 'nobloat-user-foundry' ),
			'manual_unverify'          => __( 'Manual Unverify', 'nobloat-user-foundry' ),
			'2fa_reset_by_admin'       => __( '2FA Reset', 'nobloat-user-foundry' ),
			/* Impersonation */
			'impersonation'            => __( 'Impersonation', 'nobloat-user-foundry' ),
			/* Account Merge */
			'account_merge'            => __( 'Account Merge', 'nobloat-user-foundry' ),
			'account_merge_fields'     => __( 'Account Merge Fields', 'nobloat-user-foundry' ),
			'photo_merge'              => __( 'Photo Merge', 'nobloat-user-foundry' ),
			'photo_delete'             => __( 'Photo Delete', 'nobloat-user-foundry' ),
			/* User Notes */
			'user_note_added'          => __( 'User Note Added', 'nobloat-user-foundry' ),
			'user_note_updated'        => __( 'User Note Updated', 'nobloat-user-foundry' ),
			'user_note_deleted'        => __( 'User Note Deleted', 'nobloat-user-foundry' ),
			/* Restrictions */
			'restriction_added'        => __( 'Restriction Added', 'nobloat-user-foundry' ),
			'restriction_removed'      => __( 'Restriction Removed', 'nobloat-user-foundry' ),
			/* Roles */
			'role_created'             => __( 'Role Created', 'nobloat-user-foundry' ),
			'role_updated'             => __( 'Role Updated', 'nobloat-user-foundry' ),
			'role_deleted'             => __( 'Role Deleted', 'nobloat-user-foundry' ),
			'roles_adopted'            => __( 'Roles Adopted', 'nobloat-user-foundry' ),
			/* Settings & System */
			'settings_changed'         => __( 'Settings Changed', 'nobloat-user-foundry' ),
			'logs_purged'              => __( 'Logs Purged', 'nobloat-user-foundry' ),
			/* Data Export */
			'user_data_exported'       => __( 'User Data Exported', 'nobloat-user-foundry' ),
			'admin_exported_user_data' => __( 'Admin Exported User Data', 'nobloat-user-foundry' ),
			/* Migration */
			'migration_imported'       => __( 'Migration Imported', 'nobloat-user-foundry' ),
			'migration_profiles'       => __( 'Migration Profiles', 'nobloat-user-foundry' ),
			'migration_restrictions'   => __( 'Migration Restrictions', 'nobloat-user-foundry' ),
			'migration_rollback'       => __( 'Migration Rollback', 'nobloat-user-foundry' ),
		);

		$label = isset( $labels[ $action_type ] ) ? $labels[ $action_type ] : esc_html( $action_type );

		return '<code>' . esc_html( $label ) . '</code>';
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
		$labels = array(
			'success' => __( 'Success', 'nobloat-user-foundry' ),
			'failure' => __( 'Failure', 'nobloat-user-foundry' ),
		);

		return isset( $labels[ $status ] ) ? esc_html( $labels[ $status ] ) : esc_html( $status );
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
					'<br><button type="button" class="button button-small nbuf-view-metadata" data-metadata="%s">%s</button>',
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
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		/* Set columns */
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/* Process bulk actions */
		$this->process_bulk_action();

		/* Get filters */
		$filters = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only parameters for filtering, admin screen with capability check.
		if ( ! empty( $_REQUEST['admin_id'] ) ) {
			$filters['admin_id'] = absint( $_REQUEST['admin_id'] );
		}

		if ( ! empty( $_REQUEST['target_user_id'] ) ) {
			$filters['target_user_id'] = absint( $_REQUEST['target_user_id'] );
		}

		if ( ! empty( $_REQUEST['action_type'] ) ) {
			$filters['action_type'] = sanitize_text_field( wp_unslash( $_REQUEST['action_type'] ) );
		}

		if ( ! empty( $_REQUEST['action_status'] ) ) {
			$filters['action_status'] = sanitize_text_field( wp_unslash( $_REQUEST['action_status'] ) );
		}

		if ( ! empty( $_REQUEST['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
		}

		if ( ! empty( $_REQUEST['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$filters['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

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
	 *
	 * Note: Bulk delete is handled early in NBUF_Admin_Audit_Log_Page::handle_early_actions()
	 * to avoid "headers already sent" errors. This method is kept for WP_List_Table compatibility.
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		/* Bulk actions are handled in admin_init by NBUF_Admin_Audit_Log_Page::handle_early_actions() */
	}

	/**
	 * Display filter controls
	 *
	 * @param string $which Top or bottom table navigation.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<select name="action_type">
				<option value=""><?php esc_html_e( 'All Action Types', 'nobloat-user-foundry' ); ?></option>
				<?php
				$action_types = array(
					/* User Management */
					'user_created'             => __( 'User Created', 'nobloat-user-foundry' ),
					'user_deleted'             => __( 'User Deleted', 'nobloat-user-foundry' ),
					'role_changed'             => __( 'Role Changed', 'nobloat-user-foundry' ),
					'profile_edited_by_admin'  => __( 'Profile Edited', 'nobloat-user-foundry' ),
					'email_changed_by_admin'   => __( 'Email Changed', 'nobloat-user-foundry' ),
					'password_reset_by_admin'  => __( 'Password Reset', 'nobloat-user-foundry' ),
					'manual_verify'            => __( 'Manual Verify', 'nobloat-user-foundry' ),
					'manual_unverify'          => __( 'Manual Unverify', 'nobloat-user-foundry' ),
					'2fa_reset_by_admin'       => __( '2FA Reset', 'nobloat-user-foundry' ),
					/* Impersonation */
					'impersonation'            => __( 'Impersonation', 'nobloat-user-foundry' ),
					/* Account Merge */
					'account_merge'            => __( 'Account Merge', 'nobloat-user-foundry' ),
					'account_merge_fields'     => __( 'Account Merge Fields', 'nobloat-user-foundry' ),
					'photo_merge'              => __( 'Photo Merge', 'nobloat-user-foundry' ),
					'photo_delete'             => __( 'Photo Delete', 'nobloat-user-foundry' ),
					/* User Notes */
					'user_note_added'          => __( 'User Note Added', 'nobloat-user-foundry' ),
					'user_note_updated'        => __( 'User Note Updated', 'nobloat-user-foundry' ),
					'user_note_deleted'        => __( 'User Note Deleted', 'nobloat-user-foundry' ),
					/* Restrictions */
					'restriction_added'        => __( 'Restriction Added', 'nobloat-user-foundry' ),
					'restriction_removed'      => __( 'Restriction Removed', 'nobloat-user-foundry' ),
					/* Roles */
					'role_created'             => __( 'Role Created', 'nobloat-user-foundry' ),
					'role_updated'             => __( 'Role Updated', 'nobloat-user-foundry' ),
					'role_deleted'             => __( 'Role Deleted', 'nobloat-user-foundry' ),
					'roles_adopted'            => __( 'Roles Adopted', 'nobloat-user-foundry' ),
					/* Settings & System */
					'settings_changed'         => __( 'Settings Changed', 'nobloat-user-foundry' ),
					'logs_purged'              => __( 'Logs Purged', 'nobloat-user-foundry' ),
					/* Data Export */
					'user_data_exported'       => __( 'User Data Exported', 'nobloat-user-foundry' ),
					'admin_exported_user_data' => __( 'Admin Exported User Data', 'nobloat-user-foundry' ),
					/* Migration */
					'migration_imported'       => __( 'Migration Imported', 'nobloat-user-foundry' ),
					'migration_profiles'       => __( 'Migration Profiles', 'nobloat-user-foundry' ),
					'migration_restrictions'   => __( 'Migration Restrictions', 'nobloat-user-foundry' ),
					'migration_rollback'       => __( 'Migration Rollback', 'nobloat-user-foundry' ),
				);
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only parameter for displaying current filter value.
				$selected = isset( $_REQUEST['action_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_type'] ) ) : '';
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
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only parameter for displaying current filter value. ?>
				<option value="success" <?php selected( isset( $_REQUEST['action_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_status'] ) ) : '', 'success' ); ?>><?php esc_html_e( 'Success', 'nobloat-user-foundry' ); ?></option>
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only parameter for displaying current filter value. ?>
				<option value="failure" <?php selected( isset( $_REQUEST['action_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_status'] ) ) : '', 'failure' ); ?>><?php esc_html_e( 'Failure', 'nobloat-user-foundry' ); ?></option>
			</select>

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for displaying current filter values. ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only parameter for displaying current filter value. ?>
			<label><?php esc_html_e( 'From:', 'nobloat-user-foundry' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '' ); ?>" /></label>
			<label><?php esc_html_e( 'To:', 'nobloat-user-foundry' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '' ); ?>" /></label>
			<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

			<?php submit_button( __( 'Filter', 'nobloat-user-foundry' ), 'secondary', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
