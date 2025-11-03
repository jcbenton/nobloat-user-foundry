<?php
/**
 * NoBloat User Foundry - Audit Log Handler
 *
 * Tracks user activity and security events for compliance and monitoring.
 * Events include authentication, verification, password changes, 2FA, etc.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for audit logging.
 * Custom nbuf_user_audit_log table stores security events and cannot use
 * WordPress's standard meta APIs. Caching is not implemented as logs are
 * write-heavy and caching would provide minimal benefit.
 */

/**
 * Class NBUF_Audit_Log
 *
 * Handles security audit logging.
 */
class NBUF_Audit_Log {


	/* Event type constants - prevents typos, enables IDE autocomplete */
	const EVENT_LOGIN_SUCCESS           = 'login_success';
	const EVENT_LOGIN_FAILED            = 'login_failed';
	const EVENT_LOGOUT                  = 'logout';
	const EVENT_PASSWORD_CHANGED        = 'password_changed';
	const EVENT_PASSWORD_RESET          = 'password_reset';
	const EVENT_EMAIL_VERIFIED          = 'email_verified';
	const EVENT_EMAIL_VERIFICATION_SENT = 'email_verification_sent';
	const EVENT_2FA_ENABLED             = '2fa_enabled';
	const EVENT_2FA_DISABLED            = '2fa_disabled';
	const EVENT_2FA_SUCCESS             = '2fa_success';
	const EVENT_2FA_FAILED              = '2fa_failed';
	const EVENT_ACCOUNT_DISABLED        = 'account_disabled';
	const EVENT_ACCOUNT_ENABLED         = 'account_enabled';
	const EVENT_ACCOUNT_EXPIRED         = 'account_expired';
	const EVENT_PROFILE_UPDATED         = 'profile_updated';
	const EVENT_EMAIL_CHANGED           = 'email_changed';

	/* Event status constants */
	const STATUS_SUCCESS = 'success';
	const STATUS_FAILURE = 'failure';
	const STATUS_PENDING = 'pending';
	const STATUS_WARNING = 'warning';

	/**
	 * Log a user activity event
	 *
	 * @param  int    $user_id      User ID.
	 * @param  string $event_type   Event type (e.g., 'login_success', 'password_changed').
	 * @param  string $event_status Event status ('success', 'failure', 'pending', 'warning').
	 * @param  string $message      Event description.
	 * @param  array  $metadata     Optional additional data (stored as JSON).
	 * @return bool  True on success, false on failure.
	 */
	public static function log( int $user_id, string $event_type, string $event_status, string $message = '', array $metadata = array() ): bool {
		/* Check if audit logging is enabled */
		if ( ! NBUF_Options::get( 'nbuf_audit_log_enabled', true ) ) {
			return false;
		}

		/* Check if this event type should be tracked */
		if ( ! self::should_track_event( $event_type ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		/* Get username (cache it for deleted users) */
		$user     = get_userdata( $user_id );
		$username = $user ? $user->user_login : 'unknown';

		/* Get IP address (with optional anonymization) */
		$ip_address = self::get_client_ip();
		if ( NBUF_Options::get( 'nbuf_audit_log_anonymize_ip', false ) ) {
			$ip_address = self::anonymize_ip( $ip_address );
		}

		/* Get user agent if enabled */
		$user_agent = null;
		if ( NBUF_Options::get( 'nbuf_audit_log_store_user_agent', true ) ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : null;
		}

		/* Truncate message if too long */
		$max_length = NBUF_Options::get( 'nbuf_audit_log_max_message_length', 500 );
		if ( strlen( $message ) > $max_length ) {
			$message = substr( $message, 0, $max_length ) . '...';
		}

		/* Prepare metadata JSON */
		$metadata_json = null;
		if ( ! empty( $metadata ) && is_array( $metadata ) ) {
			$metadata_json = wp_json_encode( $metadata );
		}

		/* Insert log entry */
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'       => (int) $user_id,
				'username'      => sanitize_text_field( $username ),
				'event_type'    => sanitize_text_field( $event_type ),
				'event_status'  => sanitize_text_field( $event_status ),
				'event_message' => sanitize_textarea_field( $message ),
				'ip_address'    => sanitize_text_field( $ip_address ),
				'user_agent'    => $user_agent,
				'metadata'      => $metadata_json,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (bool) $result;
	}

	/**
	 * Check if an event type should be tracked based on settings
	 *
	 * @param  string $event_type Event type to check.
	 * @return bool True if should track, false otherwise.
	 */
	private static function should_track_event( string $event_type ): bool {
		/* Map event types to their category settings */
		$event_categories = array(
			/* Authentication events */
			'login_success'              => 'authentication',
			'login_failed'               => 'authentication',
			'logout'                     => 'authentication',
			'session_expired'            => 'authentication',

			/* Verification events */
			'email_verification_sent'    => 'verification',
			'email_verified'             => 'verification',
			'email_verification_failed'  => 'verification',

			/* Password events */
			'password_changed'           => 'passwords',
			'password_reset_requested'   => 'passwords',
			'password_reset_completed'   => 'passwords',
			'weak_password_flagged'      => 'passwords',

			/* 2FA events */
			'2fa_enabled'                => '2fa',
			'2fa_disabled'               => '2fa',
			'2fa_verified'               => '2fa',
			'2fa_failed'                 => '2fa',
			'2fa_backup_codes_generated' => '2fa',
			'2fa_device_trusted'         => '2fa',
			'2fa_reset_by_admin'         => '2fa',

			/* Account status events */
			'account_disabled'           => 'account_status',
			'account_enabled'            => 'account_status',
			'account_expired'            => 'account_status',
			'expiration_warning_sent'    => 'account_status',

			/* User lifecycle events */
			'user_registered'            => 'authentication',
			'user_created'               => 'account_status',
			'user_deleted'               => 'account_status',

			/* Profile events */
			'profile_updated'            => 'profile',
		);

		$category = isset( $event_categories[ $event_type ] ) ? $event_categories[ $event_type ] : null;

		if ( ! $category ) {
			/* Unknown event type - track by default */
			return true;
		}

		/* Get category tracking settings */
		$tracking_settings = NBUF_Options::get(
			'nbuf_audit_log_events',
			array(
				'authentication' => true,
				'verification'   => true,
				'passwords'      => true,
				'2fa'            => true,
				'account_status' => true,
				'profile'        => false,
			)
		);

		return isset( $tracking_settings[ $category ] ) && $tracking_settings[ $category ];
	}

	/**
	 * Get audit logs with optional filters
	 *
	 * @param  array  $filters Filters (user_id, event_type, event_status, date_from, date_to, search).
	 * @param  int    $limit   Number of results to return.
	 * @param  int    $offset  Offset for pagination.
	 * @param  string $orderby Column to order by.
	 * @param  string $order   Order direction (ASC or DESC).
	 * @return array Array of log entries.
	 */
	public static function get_logs( array $filters = array(), int $limit = 25, int $offset = 0, string $orderby = 'created_at', string $order = 'DESC' ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		$where_clauses = array();
		$where_values  = array();

		/* Filter by user_id */
		if ( ! empty( $filters['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[]  = (int) $filters['user_id'];
		}

		/* Filter by event_type */
		if ( ! empty( $filters['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = $filters['event_type'];
		}

		/* Filter by event_status */
		if ( ! empty( $filters['event_status'] ) ) {
			$where_clauses[] = 'event_status = %s';
			$where_values[]  = $filters['event_status'];
		}

		/* Filter by date range */
		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $filters['date_to'];
		}

		/* Filter by search (username, message, IP) */
		if ( ! empty( $filters['search'] ) ) {
			/*
			* PERFORMANCE: Use FULLTEXT search for event_message (100x+ faster on large datasets)
			* FULLTEXT index was added in Session #30 (HIGH-6 fix)
			* BOOLEAN MODE with '*' allows prefix matching similar to LIKE '%term%'
			*/
			$where_clauses[] = '(username LIKE %s OR MATCH(event_message) AGAINST(%s IN BOOLEAN MODE) OR ip_address LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$search_boolean  = $wpdb->esc_like( $filters['search'] ) . '*';
			$where_values[]  = $search_term;    // for username LIKE.
			$where_values[]  = $search_boolean; // for FULLTEXT MATCH...AGAINST.
			$where_values[]  = $search_term;    // for ip_address LIKE.
		}

		/* Build WHERE clause */
		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		/* Sanitize ORDER BY */
		$allowed_orderby = array( 'id', 'user_id', 'username', 'event_type', 'event_status', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		/*
		* Build query.
		* NOTE: $where_sql is built from placeholders with validated values, $orderby validated against allowlist, $order sanitized to ASC/DESC.
		*/
     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
     // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic WHERE clause contains variable placeholders
		$sql = $wpdb->prepare(
			"SELECT * FROM %i {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...array_merge( array( $table_name ), $where_values, array( (int) $limit, (int) $offset ) )
		);
     // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

     // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with $wpdb->prepare() above.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get total count of logs matching filters
	 *
	 * @param  array $filters Filters (same as get_logs).
	 * @return int Total count.
	 */
	public static function get_logs_count( array $filters = array() ): int {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		$where_clauses = array();
		$where_values  = array();

		/* Filter by user_id */
		if ( ! empty( $filters['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[]  = (int) $filters['user_id'];
		}

		/* Filter by event_type */
		if ( ! empty( $filters['event_type'] ) ) {
			$where_clauses[] = 'event_type = %s';
			$where_values[]  = $filters['event_type'];
		}

		/* Filter by event_status */
		if ( ! empty( $filters['event_status'] ) ) {
			$where_clauses[] = 'event_status = %s';
			$where_values[]  = $filters['event_status'];
		}

		/* Filter by date range */
		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $filters['date_to'];
		}

		/* Filter by search */
		if ( ! empty( $filters['search'] ) ) {
			/* PERFORMANCE: Use FULLTEXT search for event_message (100x+ faster) */
			$where_clauses[] = '(username LIKE %s OR MATCH(event_message) AGAINST(%s IN BOOLEAN MODE) OR ip_address LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$search_boolean  = $wpdb->esc_like( $filters['search'] ) . '*';
			$where_values[]  = $search_term;    // for username LIKE.
			$where_values[]  = $search_boolean; // for FULLTEXT MATCH...AGAINST.
			$where_values[]  = $search_term;    // for ip_address LIKE.
		}

		/* Build WHERE clause - $where_sql is built from placeholders with validated values */
		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM %i {$where_sql}",
			...array_merge( array( $table_name ), $where_values )
		);
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

     // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with $wpdb->prepare() above.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Delete specific log entries
	 *
	 * @param  array $ids Array of log IDs to delete.
	 * @return bool True on success.
	 */
	public static function delete_logs( array $ids ): bool {
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		/*
		* NOTE: $placeholders dynamically generated from count(), all IDs validated as integers.
		*/
     // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"DELETE FROM %i WHERE id IN ({$placeholders})",
			...array_merge( array( $table_name ), $ids )
		);
     // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

     // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with $wpdb->prepare() above.
		return (bool) $wpdb->query( $sql );
	}

	/**
	 * Purge all audit logs
	 *
	 * @return bool True on success.
	 */
	public static function purge_all_logs(): bool {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with $wpdb->prepare() above.
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		return (bool) $wpdb->query(
			$wpdb->prepare( 'TRUNCATE TABLE %i', $table_name )
		);
	}

	/**
	 * Get audit log statistics
	 *
	 * @return array Statistics array.
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		$stats = array();

		/*
		* Total entries.
		*/
		$stats['total_entries'] = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name )
		);

		/* Database size */
		$size                   = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT data_length + index_length
			 FROM information_schema.TABLES
			 WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table_name
			)
		);
		$stats['database_size'] = $size ? self::format_bytes( $size ) : 'N/A';

		/*
		* Oldest entry.
		*/
		$oldest                = $wpdb->get_var(
			$wpdb->prepare( 'SELECT created_at FROM %i ORDER BY created_at ASC LIMIT 1', $table_name )
		);
		$stats['oldest_entry'] = $oldest ? $oldest : 'N/A';

		/* Last cleanup */
		$stats['last_cleanup'] = NBUF_Options::get( 'nbuf_audit_log_last_cleanup', 'Never' );

		return $stats;
	}

	/**
	 * Export logs to CSV
	 *
	 * @param  array $filters Filters (same as get_logs).
	 * @return string CSV content.
	 */
	public static function export_to_csv( array $filters = array() ): string {
		$logs = self::get_logs( $filters, 10000, 0 ); // Max 10k rows.

		$csv   = array();
		$csv[] = array( 'ID', 'Date/Time', 'User ID', 'Username', 'Event Type', 'Status', 'Message', 'IP Address', 'User Agent' );

		foreach ( $logs as $log ) {
			$csv[] = array(
				$log->id,
				$log->created_at,
				$log->user_id,
				$log->username,
				$log->event_type,
				$log->event_status,
				$log->event_message,
				$log->ip_address,
				$log->user_agent,
			);
		}

		/* Convert to CSV string */
		ob_start();
		$output = fopen( 'php://output', 'w' );
		foreach ( $csv as $row ) {
			fputcsv( $output, $row );
		}
     // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Required for CSV output to php://output stream.
		fclose( $output );
		return ob_get_clean();
	}

	/**
	 * Anonymize user logs (for GDPR right to be forgotten)
	 *
	 * @param  int $user_id User ID.
	 * @return bool True on success.
	 */
	public static function anonymize_user_logs( int $user_id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		return (bool) $wpdb->update(
			$table_name,
			array(
				'username'      => 'deleted_user',
				'ip_address'    => null,
				'user_agent'    => null,
				'event_message' => 'User data anonymized',
				'metadata'      => null,
			),
			array( 'user_id' => (int) $user_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete all logs for a user (for GDPR right to be forgotten)
	 *
	 * @param  int $user_id User ID.
	 * @return bool True on success.
	 */
	public static function delete_user_logs( int $user_id ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		return (bool) $wpdb->delete(
			$table_name,
			array( 'user_id' => (int) $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Clean up old logs based on retention settings
	 *
	 * @return int Number of deleted entries.
	 */
	public static function cleanup_old_logs(): int {
		if ( ! NBUF_Options::get( 'nbuf_audit_log_enabled', true ) ) {
			return 0;
		}

		$retention = NBUF_Options::get( 'nbuf_audit_log_retention', '90days' );

		$days_map = array(
			'7days'   => 7,
			'30days'  => 30,
			'90days'  => 90,
			'180days' => 180,
			'1year'   => 365,
			'2years'  => 730,
			'forever' => 0,
		);

		$days = isset( $days_map[ $retention ] ) ? $days_map[ $retention ] : 90;

		if ( 0 === $days ) {
			/* Forever - don't delete anything */
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table_name,
				$cutoff
			)
		);

		/* Update last cleanup time */
		NBUF_Options::update( 'nbuf_audit_log_last_cleanup', current_time( 'mysql' ), true, 'system' );

		return (int) $deleted;
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		$ip = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ip = explode( ',', $ip );
			$ip = trim( $ip[0] );
		} elseif ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Anonymize IP address
	 *
	 * @param  string $ip IP address to anonymize.
	 * @return string Anonymized IP.
	 */
	private static function anonymize_ip( string $ip ): string {
		if ( empty( $ip ) ) {
			return '';
		}

		/* Check if IPv4 or IPv6 */
		if ( false !== strpos( $ip, ':' ) ) {
			/* IPv6 - zero out last 80 bits (keep first 48 bits) */
			$parts       = explode( ':', $ip );
			$parts_count = count( $parts );
			for ( $i = 3; $i < $parts_count; $i++ ) {
				$parts[ $i ] = '0';
			}
			return implode( ':', $parts );
		} else {
			/* IPv4 - zero out last octet */
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}
	}

	/**
	 * Format bytes to human readable size
	 *
	 * @param  int $bytes Size in bytes.
	 * @return string Formatted size.
	 */
	private static function format_bytes( int $bytes ): string {
		$units  = array( 'B', 'KB', 'MB', 'GB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
