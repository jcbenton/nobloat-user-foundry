<?php
/**
 * NoBloat User Foundry - Audit Log List Table
 *
 * Extends WP_List_Table to display audit logs with filtering,
 * sorting, pagination, and bulk actions.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load WP_List_Table if not already loaded */
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NBUF_Audit_Log_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'audit_log',
			'plural'   => 'audit_logs',
			'ajax'     => false,
		) );
	}

	/**
	 * Get columns
	 *
	 * @return array Columns array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'created_at'    => __( 'Date/Time', 'nobloat-user-foundry' ),
			'username'      => __( 'User', 'nobloat-user-foundry' ),
			'event_type'    => __( 'Event', 'nobloat-user-foundry' ),
			'event_status'  => __( 'Status', 'nobloat-user-foundry' ),
			'event_message' => __( 'Details', 'nobloat-user-foundry' ),
			'ip_address'    => __( 'IP Address', 'nobloat-user-foundry' ),
			'user_agent'    => __( 'User Agent', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array Sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'created_at'   => array( 'created_at', true ), // true = already sorted
			'username'     => array( 'username', false ),
			'event_type'   => array( 'event_type', false ),
			'event_status' => array( 'event_status', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array Bulk actions
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @param object $item Log entry
	 * @return string Checkbox HTML
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_id[]" value="%d" />', $item->id );
	}

	/**
	 * Render date/time column
	 *
	 * @param object $item Log entry
	 * @return string Formatted date/time
	 */
	public function column_created_at( $item ) {
		$date = mysql2date( 'Y/m/d g:i:s A', $item->created_at );
		return esc_html( $date );
	}

	/**
	 * Render username column
	 *
	 * @param object $item Log entry
	 * @return string Username with link
	 */
	public function column_username( $item ) {
		$user = get_user_by( 'id', $item->user_id );

		if ( $user ) {
			$edit_link = get_edit_user_link( $item->user_id );
			return sprintf(
				'<a href="%s">%s</a><br><small>ID: %d</small>',
				esc_url( $edit_link ),
				esc_html( $item->username ),
				$item->user_id
			);
		}

		return sprintf(
			'%s<br><small>ID: %d (deleted)</small>',
			esc_html( $item->username ),
			$item->user_id
		);
	}

	/**
	 * Render event type column
	 *
	 * @param object $item Log entry
	 * @return string Formatted event type
	 */
	public function column_event_type( $item ) {
		$event_labels = array(
			'login_success'              => __( 'Login Success', 'nobloat-user-foundry' ),
			'login_failed'               => __( 'Login Failed', 'nobloat-user-foundry' ),
			'logout'                     => __( 'Logout', 'nobloat-user-foundry' ),
			'session_expired'            => __( 'Session Expired', 'nobloat-user-foundry' ),
			'email_verification_sent'    => __( 'Verification Sent', 'nobloat-user-foundry' ),
			'email_verified'             => __( 'Email Verified', 'nobloat-user-foundry' ),
			'email_verification_failed'  => __( 'Verification Failed', 'nobloat-user-foundry' ),
			'password_changed'           => __( 'Password Changed', 'nobloat-user-foundry' ),
			'password_reset_requested'   => __( 'Password Reset Requested', 'nobloat-user-foundry' ),
			'password_reset_completed'   => __( 'Password Reset Completed', 'nobloat-user-foundry' ),
			'weak_password_flagged'      => __( 'Weak Password Flagged', 'nobloat-user-foundry' ),
			'2fa_enabled'                => __( '2FA Enabled', 'nobloat-user-foundry' ),
			'2fa_disabled'               => __( '2FA Disabled', 'nobloat-user-foundry' ),
			'2fa_verified'               => __( '2FA Verified', 'nobloat-user-foundry' ),
			'2fa_failed'                 => __( '2FA Failed', 'nobloat-user-foundry' ),
			'2fa_backup_codes_generated' => __( '2FA Backup Codes Generated', 'nobloat-user-foundry' ),
			'2fa_device_trusted'         => __( '2FA Device Trusted', 'nobloat-user-foundry' ),
			'2fa_reset_by_admin'         => __( '2FA Reset by Admin', 'nobloat-user-foundry' ),
			'account_disabled'           => __( 'Account Disabled', 'nobloat-user-foundry' ),
			'account_enabled'            => __( 'Account Enabled', 'nobloat-user-foundry' ),
			'account_expired'            => __( 'Account Expired', 'nobloat-user-foundry' ),
			'expiration_warning_sent'    => __( 'Expiration Warning Sent', 'nobloat-user-foundry' ),
			'user_registered'            => __( 'User Registered', 'nobloat-user-foundry' ),
			'user_created'               => __( 'User Created', 'nobloat-user-foundry' ),
			'user_deleted'               => __( 'User Deleted', 'nobloat-user-foundry' ),
			'profile_updated'            => __( 'Profile Updated', 'nobloat-user-foundry' ),
		);

		$label = isset( $event_labels[ $item->event_type ] ) ? $event_labels[ $item->event_type ] : $item->event_type;
		return '<code>' . esc_html( $label ) . '</code>';
	}

	/**
	 * Render event status column
	 *
	 * @param object $item Log entry
	 * @return string Formatted status with color coding
	 */
	public function column_event_status( $item ) {
		$status_labels = array(
			'success' => __( 'Success', 'nobloat-user-foundry' ),
			'failure' => __( 'Failed', 'nobloat-user-foundry' ),
			'pending' => __( 'Pending', 'nobloat-user-foundry' ),
			'warning' => __( 'Warning', 'nobloat-user-foundry' ),
		);

		$label = isset( $status_labels[ $item->event_status ] ) ? $status_labels[ $item->event_status ] : $item->event_status;

		/* Only use red color for failures */
		$style = '';
		if ( $item->event_status === 'failure' ) {
			$style = 'color: #dc3232; font-weight: bold;';
		}

		return sprintf( '<span style="%s">%s</span>', $style, esc_html( $label ) );
	}

	/**
	 * Render event message column
	 *
	 * @param object $item Log entry
	 * @return string Event message
	 */
	public function column_event_message( $item ) {
		return esc_html( $item->event_message );
	}

	/**
	 * Render IP address column
	 *
	 * @param object $item Log entry
	 * @return string IP address
	 */
	public function column_ip_address( $item ) {
		if ( empty( $item->ip_address ) ) {
			return '<span style="color: #999;">N/A</span>';
		}
		return '<code>' . esc_html( $item->ip_address ) . '</code>';
	}

	/**
	 * Render user agent column
	 *
	 * @param object $item Log entry
	 * @return string User agent (truncated)
	 */
	public function column_user_agent( $item ) {
		if ( empty( $item->user_agent ) ) {
			return '<span style="color: #999;">N/A</span>';
		}

		$user_agent = $item->user_agent;

		/* Parse user agent for better display */
		$browser = self::parse_user_agent( $user_agent );

		if ( $browser ) {
			return esc_html( $browser );
		}

		/* Truncate long user agents */
		if ( strlen( $user_agent ) > 50 ) {
			$user_agent = substr( $user_agent, 0, 47 ) . '...';
		}

		return '<small>' . esc_html( $user_agent ) . '</small>';
	}

	/**
	 * Parse user agent string to get browser/device info
	 *
	 * @param string $user_agent User agent string
	 * @return string Parsed browser/device info
	 */
	private static function parse_user_agent( $user_agent ) {
		$browser = '';
		$platform = '';

		/* Detect browser */
		if ( strpos( $user_agent, 'Chrome' ) !== false ) {
			$browser = 'Chrome';
		} elseif ( strpos( $user_agent, 'Safari' ) !== false ) {
			$browser = 'Safari';
		} elseif ( strpos( $user_agent, 'Firefox' ) !== false ) {
			$browser = 'Firefox';
		} elseif ( strpos( $user_agent, 'Edge' ) !== false ) {
			$browser = 'Edge';
		} elseif ( strpos( $user_agent, 'MSIE' ) !== false || strpos( $user_agent, 'Trident' ) !== false ) {
			$browser = 'IE';
		}

		/* Detect platform */
		if ( strpos( $user_agent, 'Windows' ) !== false ) {
			$platform = 'Windows';
		} elseif ( strpos( $user_agent, 'Mac' ) !== false ) {
			$platform = 'Mac';
		} elseif ( strpos( $user_agent, 'Linux' ) !== false ) {
			$platform = 'Linux';
		} elseif ( strpos( $user_agent, 'iPhone' ) !== false || strpos( $user_agent, 'iPad' ) !== false ) {
			$platform = 'iOS';
		} elseif ( strpos( $user_agent, 'Android' ) !== false ) {
			$platform = 'Android';
		}

		if ( $browser && $platform ) {
			return $browser . ' / ' . $platform;
		} elseif ( $browser ) {
			return $browser;
		}

		return '';
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		/* Set columns */
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns
			$this->get_sortable_columns(),
		);

		/* Handle bulk actions */
		$this->process_bulk_action();

		/* Get filters from request */
		$filters = array();

		if ( ! empty( $_REQUEST['user_id'] ) ) {
			$filters['user_id'] = intval( $_REQUEST['user_id'] );
		}

		if ( ! empty( $_REQUEST['event_type'] ) ) {
			$filters['event_type'] = sanitize_text_field( wp_unslash( $_REQUEST['event_type'] ) );
		}

		if ( ! empty( $_REQUEST['event_status'] ) ) {
			$filters['event_status'] = sanitize_text_field( wp_unslash( $_REQUEST['event_status'] ) );
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$filters['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		/* Get sorting */
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		/* Get pagination */
		$per_page = $this->get_items_per_page( 'audit_logs_per_page', 25 );
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		/* Get logs */
		$this->items = NBUF_Audit_Log::get_logs( $filters, $per_page, $offset, $orderby, $order );

		/* Get total count for pagination */
		$total_items = NBUF_Audit_Log::get_logs_count( $filters );

		/* Set pagination */
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		/* Check for bulk delete action */
		if ( 'delete' === $this->current_action() ) {
			/* Verify nonce */
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'bulk-audit_logs' ) ) {
				wp_die( esc_html__( 'Security check failed', 'nobloat-user-foundry' ) );
			}

			/* Check capability */
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to delete logs', 'nobloat-user-foundry' ) );
			}

			/* Get selected log IDs */
			if ( ! empty( $_REQUEST['log_id'] ) && is_array( $_REQUEST['log_id'] ) ) {
				$log_ids = array_map( 'intval', $_REQUEST['log_id'] );
				NBUF_Audit_Log::delete_logs( $log_ids );

				/* Redirect with success message */
				$redirect = remove_query_arg( array( 'action', 'action2', 'log_id', '_wpnonce', '_wp_http_referer' ) );
				$redirect = add_query_arg( 'deleted', count( $log_ids ), $redirect );
				wp_safe_redirect( $redirect );
				exit;
			}
		}
	}

	/**
	 * Display when no items found
	 */
	public function no_items() {
		esc_html_e( 'No audit log entries found.', 'nobloat-user-foundry' );
	}

	/**
	 * Extra table navigation (filters)
	 *
	 * @param string $which Top or bottom
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php $this->event_type_dropdown(); ?>
			<?php $this->event_status_dropdown(); ?>
			<?php submit_button( __( 'Filter', 'nobloat-user-foundry' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render event type dropdown filter
	 */
	private function event_type_dropdown() {
		$event_types = array(
			''                           => __( 'All Event Types', 'nobloat-user-foundry' ),
			'login_success'              => __( 'Login Success', 'nobloat-user-foundry' ),
			'login_failed'               => __( 'Login Failed', 'nobloat-user-foundry' ),
			'logout'                     => __( 'Logout', 'nobloat-user-foundry' ),
			'email_verified'             => __( 'Email Verified', 'nobloat-user-foundry' ),
			'password_changed'           => __( 'Password Changed', 'nobloat-user-foundry' ),
			'password_reset_requested'   => __( 'Password Reset Requested', 'nobloat-user-foundry' ),
			'2fa_enabled'                => __( '2FA Enabled', 'nobloat-user-foundry' ),
			'2fa_disabled'               => __( '2FA Disabled', 'nobloat-user-foundry' ),
			'2fa_verified'               => __( '2FA Verified', 'nobloat-user-foundry' ),
			'2fa_failed'                 => __( '2FA Failed', 'nobloat-user-foundry' ),
			'user_registered'            => __( 'User Registered', 'nobloat-user-foundry' ),
			'account_disabled'           => __( 'Account Disabled', 'nobloat-user-foundry' ),
			'account_expired'            => __( 'Account Expired', 'nobloat-user-foundry' ),
		);

		$current = isset( $_REQUEST['event_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['event_type'] ) ) : '';

		echo '<select name="event_type" id="event-type-filter">';
		foreach ( $event_types as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render event status dropdown filter
	 */
	private function event_status_dropdown() {
		$statuses = array(
			''        => __( 'All Statuses', 'nobloat-user-foundry' ),
			'success' => __( 'Success', 'nobloat-user-foundry' ),
			'failure' => __( 'Failed', 'nobloat-user-foundry' ),
			'pending' => __( 'Pending', 'nobloat-user-foundry' ),
			'warning' => __( 'Warning', 'nobloat-user-foundry' ),
		);

		$current = isset( $_REQUEST['event_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['event_status'] ) ) : '';

		echo '<select name="event_status" id="event-status-filter">';
		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}
}
