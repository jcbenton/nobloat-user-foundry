<?php
/**
 * NoBloat User Foundry - Security Log List Table
 *
 * Extends WP_List_Table to display security logs with filtering,
 * sorting, pagination, and bulk actions.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load WP_List_Table if not already loaded */
if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Security log list table
 *
 * @since 1.4.0
 */
class NBUF_Security_Log_List_Table extends WP_List_Table {


	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'security_log',
				'plural'   => 'security_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns
	 *
	 * @return array<string, string> Columns array
	 */
	public function get_columns(): array {
		return array(
			'cb'               => '<input type="checkbox" />',
			'timestamp'        => __( 'Last Seen', 'nobloat-user-foundry' ),
			'occurrence_count' => __( 'Count', 'nobloat-user-foundry' ),
			'severity'         => __( 'Severity', 'nobloat-user-foundry' ),
			'event_type'       => __( 'Event Type', 'nobloat-user-foundry' ),
			'ip_address'       => __( 'IP Address', 'nobloat-user-foundry' ),
			'message'          => __( 'Message', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array<string, array{0: string, 1: bool}> Sortable columns
	 */
	public function get_sortable_columns(): array {
		return array(
			'timestamp'        => array( 'timestamp', true ), // true = already sorted.
			'occurrence_count' => array( 'occurrence_count', false ),
			'severity'         => array( 'severity', false ),
			'event_type'       => array( 'event_type', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array<string, string> Bulk actions
	 */
	public function get_bulk_actions(): array {
		return array(
			'delete'     => __( 'Delete', 'nobloat-user-foundry' ),
			'unblock_ip' => __( 'Unblock IP', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Render checkbox column
	 *
	 * @param  object $item Log entry.
	 * @return string Checkbox HTML
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_id[]" value="%d" />', $item->id );
	}

	/**
	 * Render timestamp column (shows last seen with first seen on hover)
	 *
	 * @param  object $item Log entry.
	 * @return string Formatted timestamp in user's local timezone
	 */
	public function column_timestamp( $item ) {
		/* Convert UTC to user's browser timezone (from cookie) */
		$local_time = NBUF_Options::format_local_time( $item->timestamp );

		/* Build tooltip with first seen if different from last seen */
		$tooltip_parts = array();
		/* translators: %s: UTC timestamp */
		$tooltip_parts[] = sprintf( __( 'UTC: %s', 'nobloat-user-foundry' ), $item->timestamp );

		if ( ! empty( $item->first_seen ) && $item->first_seen !== $item->timestamp ) {
			$first_seen_local = NBUF_Options::format_local_time( $item->first_seen );
			/* translators: %s: first seen timestamp */
			$tooltip_parts[] = sprintf( __( 'First seen: %s', 'nobloat-user-foundry' ), $first_seen_local );
		}

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( implode( "\n", $tooltip_parts ) ),
			esc_html( $local_time )
		);
	}

	/**
	 * Render occurrence count column
	 *
	 * @param  object $item Log entry.
	 * @return string Formatted occurrence count
	 */
	public function column_occurrence_count( $item ) {
		$count = isset( $item->occurrence_count ) ? (int) $item->occurrence_count : 1;

		if ( $count > 1 ) {
			return sprintf(
				'<span class="nbuf-occurrence-badge">%s</span>',
				esc_html( number_format( $count ) )
			);
		}

		return '<span class="nbuf-muted">1</span>';
	}

	/**
	 * Render severity column
	 *
	 * @param  object $item Log entry.
	 * @return string Formatted severity label
	 */
	public function column_severity( $item ) {
		$severity_labels = array(
			'critical' => __( 'Critical', 'nobloat-user-foundry' ),
			'warning'  => __( 'Warning', 'nobloat-user-foundry' ),
			'info'     => __( 'Info', 'nobloat-user-foundry' ),
		);

		$label = isset( $severity_labels[ $item->severity ] ) ? $severity_labels[ $item->severity ] : $item->severity;

		return esc_html( $label );
	}

	/**
	 * Render event type column
	 *
	 * @param  object $item Log entry.
	 * @return string Formatted event type
	 */
	public function column_event_type( $item ) {
		$label = self::get_event_type_label( $item->event_type );
		return '<code>' . esc_html( $label ) . '</code>';
	}

	/**
	 * Get human-readable label for event type
	 *
	 * @param  string $event_type Event type slug.
	 * @return string Human-readable label.
	 */
	public static function get_event_type_label( string $event_type ): string {
		$event_labels = array(
			/* Login & Authentication */
			'login_failed'                     => __( 'Login Failed', 'nobloat-user-foundry' ),
			'login_blocked'                    => __( 'Login Blocked', 'nobloat-user-foundry' ),
			'ip_blocked'                       => __( 'IP Blocked', 'nobloat-user-foundry' ),
			'distributed_brute_force_detected' => __( 'Distributed Brute Force', 'nobloat-user-foundry' ),
			/* Registration */
			'registration_bot_blocked'         => __( 'Bot Registration Blocked', 'nobloat-user-foundry' ),
			'email_domain_blocked'             => __( 'Email Domain Blocked', 'nobloat-user-foundry' ),
			/* Magic Links */
			'magic_link_sent'                  => __( 'Magic Link Sent', 'nobloat-user-foundry' ),
			'magic_link_used'                  => __( 'Magic Link Used', 'nobloat-user-foundry' ),
			'magic_link_insert_failed'         => __( 'Magic Link Insert Failed', 'nobloat-user-foundry' ),
			/* Passkeys */
			'passkey_rate_limited'             => __( 'Passkey Rate Limited', 'nobloat-user-foundry' ),
			'passkey_clone_detected'           => __( 'Passkey Clone Detected', 'nobloat-user-foundry' ),
			/* Impersonation */
			'impersonation_start'              => __( 'Impersonation Started', 'nobloat-user-foundry' ),
			'impersonation_ip_mismatch'        => __( 'Impersonation IP Mismatch', 'nobloat-user-foundry' ),
			/* Access Control */
			'access_denied_message'            => __( 'Access Denied (Message)', 'nobloat-user-foundry' ),
			'access_denied_redirect'           => __( 'Access Denied (Redirect)', 'nobloat-user-foundry' ),
			'access_denied_404'                => __( 'Access Denied (404)', 'nobloat-user-foundry' ),
			'taxonomy_access_denied_redirect'  => __( 'Taxonomy Access Denied (Redirect)', 'nobloat-user-foundry' ),
			'taxonomy_access_denied_404'       => __( 'Taxonomy Access Denied (404)', 'nobloat-user-foundry' ),
			'privilege_escalation_blocked'     => __( 'Privilege Escalation Blocked', 'nobloat-user-foundry' ),
			/* Security */
			'open_redirect_blocked'            => __( 'Open Redirect Blocked', 'nobloat-user-foundry' ),
			'csrf_origin_mismatch'             => __( 'CSRF Origin Mismatch', 'nobloat-user-foundry' ),
			'path_traversal_attempt'           => __( 'Path Traversal Attempt', 'nobloat-user-foundry' ),
			'csv_injection_prevented'          => __( 'CSV Injection Prevented', 'nobloat-user-foundry' ),
			/* Account Merge */
			'account_merge_rollback'           => __( 'Account Merge Rollback', 'nobloat-user-foundry' ),
			'invalid_photo_selection'          => __( 'Invalid Photo Selection', 'nobloat-user-foundry' ),
			/* File Operations */
			'file_read_failed'                 => __( 'File Read Failed', 'nobloat-user-foundry' ),
			'file_validation_failed'           => __( 'File Validation Failed', 'nobloat-user-foundry' ),
			'file_not_found'                   => __( 'File Not Found', 'nobloat-user-foundry' ),
			'file_integrity_failed'            => __( 'File Integrity Failed', 'nobloat-user-foundry' ),
			'file_copy_failed'                 => __( 'File Copy Failed', 'nobloat-user-foundry' ),
			'file_deletion_failed'             => __( 'File Deletion Failed', 'nobloat-user-foundry' ),
			'invalid_mime_type'                => __( 'Invalid MIME Type', 'nobloat-user-foundry' ),
			'rmdir_failed'                     => __( 'Directory Removal Failed', 'nobloat-user-foundry' ),
			/* Image Processing */
			'image_validation_failed'          => __( 'Image Validation Failed', 'nobloat-user-foundry' ),
			'image_create_failed'              => __( 'Image Create Failed', 'nobloat-user-foundry' ),
			'image_copy_failed'                => __( 'Image Copy Failed', 'nobloat-user-foundry' ),
			'webp_conversion_failed'           => __( 'WebP Conversion Failed', 'nobloat-user-foundry' ),
			/* CSS */
			'css_read_failed'                  => __( 'CSS Read Failed', 'nobloat-user-foundry' ),
			'css_write_failed'                 => __( 'CSS Write Failed', 'nobloat-user-foundry' ),
			'css_minify_write_failed'          => __( 'CSS Minify Write Failed', 'nobloat-user-foundry' ),
			/* Export */
			'csv_output_failed'                => __( 'CSV Output Failed', 'nobloat-user-foundry' ),
			'data_export_failed'               => __( 'Data Export Failed', 'nobloat-user-foundry' ),
			/* Migration */
			'invalid_user_id_photo_migration'  => __( 'Invalid User ID (Photo Migration)', 'nobloat-user-foundry' ),
		);

		return isset( $event_labels[ $event_type ] ) ? $event_labels[ $event_type ] : $event_type;
	}

	/**
	 * Render IP address column
	 *
	 * @param  object $item Log entry.
	 * @return string IP address
	 */
	public function column_ip_address( $item ) {
		if ( empty( $item->ip_address ) ) {
			return '<span class="nbuf-muted">N/A</span>';
		}
		return '<code>' . esc_html( $item->ip_address ) . '</code>';
	}

	/**
	 * Render message column
	 *
	 * @param  object $item Log entry.
	 * @return string Security event message with optional context button
	 */
	public function column_message( $item ) {
		$output = esc_html( $item->message );

		/* Add View Details button if context available */
		if ( ! empty( $item->context ) ) {
			$context = $item->context;

			/* If context is JSON string, decode it for the data attribute */
			$decoded = json_decode( $context, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
				$decoded = array( 'raw' => $context );
			}

			$output .= sprintf(
				'<br><button type="button" class="button button-small nbuf-view-context" data-context="%s">%s</button>',
				esc_attr( wp_json_encode( $decoded ) ),
				esc_html__( 'View Details', 'nobloat-user-foundry' )
			);
		}

		return $output;
	}

	/**
	 * Prepare items for display
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		/* Set columns */
		$this->_column_headers = array(
			$this->get_columns(),
			array(), // Hidden columns.
			$this->get_sortable_columns(),
		);

		/* Handle bulk actions */
		$this->process_bulk_action();

		/* Get filters from request */
		$filters = array();

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
		if ( ! empty( $_REQUEST['severity'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
			$filters['severity'] = sanitize_text_field( wp_unslash( $_REQUEST['severity'] ) );
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
		if ( ! empty( $_REQUEST['event_type'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
			$filters['event_type'] = sanitize_text_field( wp_unslash( $_REQUEST['event_type'] ) );
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
		if ( ! empty( $_REQUEST['date_from'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
		if ( ! empty( $_REQUEST['date_to'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
		if ( ! empty( $_REQUEST['s'] ) ) {
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filtering
			$filters['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		/*
		 * Get sorting
		 */
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table sorting
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'timestamp';
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table sorting
		$order = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		/* Get pagination */
		$per_page     = $this->get_items_per_page( 'security_logs_per_page', 25 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		/* Get logs */
		$this->items = NBUF_Security_Log::get_logs(
			array_merge(
				$filters,
				array(
					'limit'   => $per_page,
					'offset'  => $offset,
					'orderby' => $orderby,
					'order'   => $order,
				)
			)
		);

		/* Get total count for pagination */
		$total_items = NBUF_Security_Log::get_log_count( $filters );

		/* Set pagination */
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
	 * Note: Bulk delete is handled by NBUF_Security_Log_Page::handle_bulk_delete()
	 * on admin_init to avoid "headers already sent" errors.
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		/* Bulk actions now handled by NBUF_Security_Log_Page on admin_init */
	}

	/**
	 * Display when no items found
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No security log entries found.', 'nobloat-user-foundry' );
	}

	/**
	 * Extra table navigation (filters)
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
		<?php $this->severity_dropdown(); ?>
		<?php $this->event_type_dropdown(); ?>
		<?php $this->date_from_field(); ?>
		<?php $this->date_to_field(); ?>
		<?php submit_button( __( 'Filter', 'nobloat-user-foundry' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render severity dropdown filter
	 *
	 * @return void
	 */
	private function severity_dropdown(): void {
		$severities = array(
			''         => __( 'All Severities', 'nobloat-user-foundry' ),
			'critical' => __( 'Critical', 'nobloat-user-foundry' ),
			'warning'  => __( 'Warning', 'nobloat-user-foundry' ),
			'info'     => __( 'Info', 'nobloat-user-foundry' ),
		);

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dropdown filter display
		$current = isset( $_REQUEST['severity'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['severity'] ) ) : '';

		echo '<select name="severity" id="severity-filter">';
		foreach ( $severities as $value => $label ) {
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
	 * Render event type dropdown filter
	 *
	 * @return void
	 */
	private function event_type_dropdown(): void {
		$event_types = array(
			''                                 => __( 'All Event Types', 'nobloat-user-foundry' ),
			/* Login & Authentication */
			'login_failed'                     => __( 'Login Failed', 'nobloat-user-foundry' ),
			'login_blocked'                    => __( 'Login Blocked', 'nobloat-user-foundry' ),
			'ip_blocked'                       => __( 'IP Blocked', 'nobloat-user-foundry' ),
			'distributed_brute_force_detected' => __( 'Distributed Brute Force', 'nobloat-user-foundry' ),
			/* Registration */
			'registration_bot_blocked'         => __( 'Bot Registration Blocked', 'nobloat-user-foundry' ),
			'email_domain_blocked'             => __( 'Email Domain Blocked', 'nobloat-user-foundry' ),
			/* Magic Links */
			'magic_link_sent'                  => __( 'Magic Link Sent', 'nobloat-user-foundry' ),
			'magic_link_used'                  => __( 'Magic Link Used', 'nobloat-user-foundry' ),
			'magic_link_insert_failed'         => __( 'Magic Link Insert Failed', 'nobloat-user-foundry' ),
			/* Passkeys */
			'passkey_rate_limited'             => __( 'Passkey Rate Limited', 'nobloat-user-foundry' ),
			'passkey_clone_detected'           => __( 'Passkey Clone Detected', 'nobloat-user-foundry' ),
			/* Impersonation */
			'impersonation_start'              => __( 'Impersonation Started', 'nobloat-user-foundry' ),
			'impersonation_ip_mismatch'        => __( 'Impersonation IP Mismatch', 'nobloat-user-foundry' ),
			/* Access Control */
			'access_denied_message'            => __( 'Access Denied (Message)', 'nobloat-user-foundry' ),
			'access_denied_redirect'           => __( 'Access Denied (Redirect)', 'nobloat-user-foundry' ),
			'access_denied_404'                => __( 'Access Denied (404)', 'nobloat-user-foundry' ),
			'taxonomy_access_denied_redirect'  => __( 'Taxonomy Access Denied (Redirect)', 'nobloat-user-foundry' ),
			'taxonomy_access_denied_404'       => __( 'Taxonomy Access Denied (404)', 'nobloat-user-foundry' ),
			'privilege_escalation_blocked'     => __( 'Privilege Escalation Blocked', 'nobloat-user-foundry' ),
			/* Security */
			'open_redirect_blocked'            => __( 'Open Redirect Blocked', 'nobloat-user-foundry' ),
			'csrf_origin_mismatch'             => __( 'CSRF Origin Mismatch', 'nobloat-user-foundry' ),
			'path_traversal_attempt'           => __( 'Path Traversal Attempt', 'nobloat-user-foundry' ),
			'csv_injection_prevented'          => __( 'CSV Injection Prevented', 'nobloat-user-foundry' ),
			/* Account Merge */
			'account_merge_rollback'           => __( 'Account Merge Rollback', 'nobloat-user-foundry' ),
			'invalid_photo_selection'          => __( 'Invalid Photo Selection', 'nobloat-user-foundry' ),
			/* File Operations */
			'file_read_failed'                 => __( 'File Read Failed', 'nobloat-user-foundry' ),
			'file_validation_failed'           => __( 'File Validation Failed', 'nobloat-user-foundry' ),
			'file_not_found'                   => __( 'File Not Found', 'nobloat-user-foundry' ),
			'file_integrity_failed'            => __( 'File Integrity Failed', 'nobloat-user-foundry' ),
			'file_copy_failed'                 => __( 'File Copy Failed', 'nobloat-user-foundry' ),
			'file_deletion_failed'             => __( 'File Deletion Failed', 'nobloat-user-foundry' ),
			'invalid_mime_type'                => __( 'Invalid MIME Type', 'nobloat-user-foundry' ),
			'rmdir_failed'                     => __( 'Directory Removal Failed', 'nobloat-user-foundry' ),
			/* Image Processing */
			'image_validation_failed'          => __( 'Image Validation Failed', 'nobloat-user-foundry' ),
			'image_create_failed'              => __( 'Image Create Failed', 'nobloat-user-foundry' ),
			'image_copy_failed'                => __( 'Image Copy Failed', 'nobloat-user-foundry' ),
			'webp_conversion_failed'           => __( 'WebP Conversion Failed', 'nobloat-user-foundry' ),
			/* CSS */
			'css_read_failed'                  => __( 'CSS Read Failed', 'nobloat-user-foundry' ),
			'css_write_failed'                 => __( 'CSS Write Failed', 'nobloat-user-foundry' ),
			'css_minify_write_failed'          => __( 'CSS Minify Write Failed', 'nobloat-user-foundry' ),
			/* Export */
			'csv_output_failed'                => __( 'CSV Output Failed', 'nobloat-user-foundry' ),
			'data_export_failed'               => __( 'Data Export Failed', 'nobloat-user-foundry' ),
			/* Migration */
			'invalid_user_id_photo_migration'  => __( 'Invalid User ID (Photo Migration)', 'nobloat-user-foundry' ),
		);

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dropdown filter display
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
	 * Render date from field
	 *
	 * @return void
	 */
	private function date_from_field(): void {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date filter display
		$value = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';

		printf(
			'<input type="date" name="date_from" id="date-from-filter" value="%s" placeholder="%s" class="nbuf-date-filter">',
			esc_attr( $value ),
			esc_attr__( 'Date From', 'nobloat-user-foundry' )
		);
	}

	/**
	 * Render date to field
	 *
	 * @return void
	 */
	private function date_to_field(): void {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date filter display
		$value = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';

		printf(
			'<input type="date" name="date_to" id="date-to-filter" value="%s" placeholder="%s" class="nbuf-date-filter">',
			esc_attr( $value ),
			esc_attr__( 'Date To', 'nobloat-user-foundry' )
		);
	}
}
