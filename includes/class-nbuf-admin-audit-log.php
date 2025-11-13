<?php
/**
 * Admin Audit Log Handler
 *
 * Manages logging of administrator actions on users and system settings.
 * GDPR Basis: Legitimate Interest - Accountability (Article 6(1)(f))
 * Default Retention: Forever (compliance requirement)
 *
 * Purpose Limitation: This log contains ONLY admin-initiated actions such as:
 * - User deletion/creation by admin
 * - Role changes by admin
 * - Password resets by admin
 * - Bulk user operations
 * - Critical settings changes
 * - Manual verifications/modifications
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Classes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Audit Log Class
 *
 * @since 1.4.0
 */
class NBUF_Admin_Audit_Log {

	const EVENT_USER_DELETED            = 'user_deleted';
	const EVENT_USER_CREATED            = 'user_created';
	const EVENT_PASSWORD_RESET_BY_ADMIN = 'password_reset_by_admin';
	const EVENT_ROLE_CHANGED            = 'role_changed';
	const EVENT_BULK_ACTION             = 'bulk_action';
	const EVENT_SETTINGS_CHANGED        = 'settings_changed';
	const EVENT_MANUAL_VERIFY           = 'manual_verify';
	const EVENT_MANUAL_UNVERIFY         = 'manual_unverify';
	const EVENT_ACCOUNT_MERGE           = 'account_merge';
	const EVENT_PROFILE_EDITED_BY_ADMIN = 'profile_edited_by_admin';
	const EVENT_EMAIL_CHANGED_BY_ADMIN  = 'email_changed_by_admin';
	const EVENT_2FA_RESET_BY_ADMIN      = '2fa_reset_by_admin';
	const EVENT_LOGS_PURGED             = 'logs_purged';

	/**
	 * Log admin action
	 *
	 * @since 1.4.0
	 *
	 * @param int    $admin_id        Admin who performed action.
	 * @param string $action_type     Action type constant.
	 * @param string $action_status   'success' or 'failure'.
	 * @param string $action_message  Human-readable message.
	 * @param int    $target_user_id  User affected (NULL for settings).
	 * @param array  $metadata        Additional data (old_value, new_value, etc.).
	 * @return bool True on success, false on failure.
	 */
	public static function log( $admin_id, $action_type, $action_status, $action_message, $target_user_id = null, $metadata = array() ) {
		global $wpdb;

		/* Check if admin audit logging is enabled */
		if ( ! NBUF_Options::get( 'nbuf_logging_admin_audit_enabled', true ) ) {
			return false;
		}

		/* Check if this action category should be logged */
		if ( ! self::should_log_action( $action_type ) ) {
			return false;
		}

		/* Get admin user data */
		$admin_user     = get_userdata( $admin_id );
		$admin_username = $admin_user ? $admin_user->user_login : 'unknown';

		/* Get target user data if applicable */
		$target_username = null;
		if ( $target_user_id ) {
			$target_user     = get_userdata( $target_user_id );
			$target_username = $target_user ? $target_user->user_login : 'unknown';
		}

		/* Get IP address with optional anonymization */
		$ip_address = self::get_ip_address();

		/* Get user agent if enabled */
		$user_agent = NBUF_Options::get( 'nbuf_logging_store_user_agent', true )
			? ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' )
			: '';

		/* Prepare data for insertion */
		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		$data = array(
			'admin_id'        => $admin_id,
			'admin_username'  => $admin_username,
			'target_user_id'  => $target_user_id,
			'target_username' => $target_username,
			'action_type'     => $action_type,
			'action_status'   => $action_status,
			'action_message'  => $action_message,
			'metadata'        => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'ip_address'      => $ip_address,
			'user_agent'      => $user_agent,
			'created_at'      => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom audit log table requires direct database access.
		$result = $wpdb->insert( $table, $data, $format );

		return false !== $result;
	}

	/**
	 * Get logs for specific admin
	 *
	 * @since 1.4.0
	 *
	 * @param int $admin_id Admin user ID.
	 * @param int $limit    Number of logs to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_admin_actions( $admin_id, $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		$query = $wpdb->prepare(
			'SELECT * FROM %i WHERE admin_id = %d ORDER BY created_at DESC LIMIT %d',
			$table,
			$admin_id,
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, prepared query, no caching needed for audit logs.
		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get logs for specific user (what admins did to this user)
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id Target user ID.
	 * @param int $limit   Number of logs to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_user_modifications( $user_id, $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		$query = $wpdb->prepare(
			'SELECT * FROM %i WHERE target_user_id = %d ORDER BY created_at DESC LIMIT %d',
			$table,
			$user_id,
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, prepared query, no caching needed for audit logs.
		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get all logs with optional filters
	 *
	 * @since 1.4.0
	 *
	 * @param array $filters Array of filters (date_from, date_to, admin_id, target_user_id, action_type, action_status).
	 * @param int   $limit   Number of logs to retrieve.
	 * @param int   $offset  Offset for pagination.
	 * @return array Array of log entries.
	 */
	public static function get_logs( $filters = array(), $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'nbuf_admin_audit_log';
		$where  = array( '1=1' );
		$values = array();

		/* Date range filter */
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}

		/* Admin filter */
		if ( ! empty( $filters['admin_id'] ) ) {
			$where[]  = 'admin_id = %d';
			$values[] = absint( $filters['admin_id'] );
		}

		/* Target user filter */
		if ( ! empty( $filters['target_user_id'] ) ) {
			$where[]  = 'target_user_id = %d';
			$values[] = absint( $filters['target_user_id'] );
		}

		/* Action type filter */
		if ( ! empty( $filters['action_type'] ) ) {
			$where[]  = 'action_type = %s';
			$values[] = sanitize_text_field( $filters['action_type'] );
		}

		/* Action status filter */
		if ( ! empty( $filters['action_status'] ) ) {
			$where[]  = 'action_status = %s';
			$values[] = sanitize_text_field( $filters['action_status'] );
		}

		$where_clause = implode( ' AND ', $where );

		/* Build query */
		$query = "SELECT * FROM %i WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		/* Add table, limit, offset to values */
		array_unshift( $values, $table );
		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is dynamically built with proper placeholders and prepared below.
		$prepared = $wpdb->prepare( $query, $values );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, prepared query, no caching needed for audit logs.
		$results = $wpdb->get_results( $prepared, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get total count of logs (with optional filters)
	 *
	 * @since 1.4.0
	 *
	 * @param array $filters Array of filters.
	 * @return int Total count.
	 */
	public static function get_log_count( $filters = array() ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'nbuf_admin_audit_log';
		$where  = array( '1=1' );
		$values = array();

		/* Apply same filters as get_logs() */
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}
		if ( ! empty( $filters['admin_id'] ) ) {
			$where[]  = 'admin_id = %d';
			$values[] = absint( $filters['admin_id'] );
		}
		if ( ! empty( $filters['target_user_id'] ) ) {
			$where[]  = 'target_user_id = %d';
			$values[] = absint( $filters['target_user_id'] );
		}
		if ( ! empty( $filters['action_type'] ) ) {
			$where[]  = 'action_type = %s';
			$values[] = sanitize_text_field( $filters['action_type'] );
		}
		if ( ! empty( $filters['action_status'] ) ) {
			$where[]  = 'action_status = %s';
			$values[] = sanitize_text_field( $filters['action_status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause contains placeholders, prepared below.
		$query = "SELECT COUNT(*) FROM %i WHERE {$where_clause}";
		array_unshift( $values, $table );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is dynamically built with proper placeholders and prepared below.
		$prepared = $wpdb->prepare( $query, $values );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, prepared query, no caching needed for audit logs.
		$count = $wpdb->get_var( $prepared );

		return absint( $count );
	}

	/**
	 * Export admin logs to CSV
	 *
	 * @since 1.4.0
	 *
	 * @param array $filters Array of filters.
	 * @return void Outputs CSV file.
	 */
	public static function export_csv( $filters = array() ) {
		/* Capability check */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'nobloat-user-foundry' ) );
		}

		/* Set headers for CSV download */
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=admin-audit-log-' . gmdate( 'Y-m-d-His' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		/* Open output stream */
		$output = fopen( 'php://output', 'w' );

		/* Add BOM for Excel UTF-8 compatibility */
		fprintf( $output, "\xEF\xBB\xBF" );

		/* Write CSV header */
		fputcsv(
			$output,
			array(
				'ID',
				'Date/Time',
				'Admin ID',
				'Admin Username',
				'Target User ID',
				'Target Username',
				'Action Type',
				'Action Status',
				'Action Message',
				'IP Address',
				'User Agent',
				'Metadata',
			)
		);

		/* Export logs in batches to prevent memory exhaustion */
		$batch_size = 1000;
		$offset     = 0;
		$total      = 0;

		while ( true ) {
			/* Get batch of logs */
			$logs = self::get_logs( $filters, $batch_size, $offset );

			/* Break if no more logs */
			if ( empty( $logs ) ) {
				break;
			}

			/* Write data rows */
			foreach ( $logs as $log ) {
				fputcsv(
					$output,
					array(
						$log['id'],
						$log['created_at'],
						$log['admin_id'],
						$log['admin_username'],
						$log['target_user_id'],
						$log['target_username'],
						$log['action_type'],
						$log['action_status'],
						$log['action_message'],
						$log['ip_address'],
						$log['user_agent'],
						$log['metadata'],
					)
				);
				++$total;
			}

			/* Move to next batch */
			$offset += $batch_size;

			/* Safety check: prevent infinite loop */
			if ( count( $logs ) < $batch_size ) {
				break;
			}
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file operations needed for CSV export streaming.
		exit;
	}

	/**
	 * Prune old logs based on retention setting
	 *
	 * @since 1.4.0
	 *
	 * @param int $days Number of days to retain (0 = forever).
	 * @return int Number of rows deleted.
	 */
	public static function prune_logs_older_than( $days ) {
		global $wpdb;

		/* Don't prune if retention is forever */
		if ( 0 === $days || 'forever' === $days ) {
			return 0;
		}

		$table       = $wpdb->prefix . 'nbuf_admin_audit_log';
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$query = $wpdb->prepare(
			'DELETE FROM %i WHERE created_at < %s',
			$table,
			$cutoff_date
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, prepared query, no caching needed for audit logs.
		$result = $wpdb->query( $query );

		return absint( $result );
	}

	/**
	 * Purge all logs (manual purge with confirmation)
	 *
	 * SECURITY: This method should ONLY be called after proper capability check
	 * and user confirmation in the admin UI.
	 *
	 * @since 1.4.0
	 *
	 * @return array Result with count of deleted rows.
	 */
	public static function purge_all() {
		global $wpdb;

		/* Double-check capability */
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'count'   => 0,
				'message' => __( 'Unauthorized', 'nobloat-user-foundry' ),
			);
		}

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, no caching needed for audit logs.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, no caching needed for audit logs.
		$result = $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		/*
		Log the purge action (to admin audit log). Note: This creates a new entry after purging.
		 */
		self::log(
			get_current_user_id(),
			self::EVENT_LOGS_PURGED,
			'success',
			/* translators: %d is the number of log entries purged */
			sprintf( __( 'Purged %d entries from admin audit log', 'nobloat-user-foundry' ), $count ),
			null,
			array(
				'table' => 'admin_audit_log',
				'count' => $count,
			)
		);

		return array(
			'success' => false !== $result,
			'count'   => absint( $count ),
			/* translators: %d is the number of log entries purged */
			'message' => sprintf( __( 'Purged %d log entries', 'nobloat-user-foundry' ), $count ),
		);
	}

	/**
	 * Anonymize logs for a specific target user (GDPR compliance)
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id User ID to anonymize.
	 * @return int Number of rows updated.
	 */
	public static function anonymize_target_user_logs( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, no caching needed for audit logs.
		$result = $wpdb->update(
			$table,
			array(
				'target_username' => 'anonymized-user',
				'ip_address'      => '0.0.0.0',
				'metadata'        => null,
			),
			array( 'target_user_id' => $user_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return absint( $result );
	}

	/**
	 * Delete logs for a specific target user (GDPR compliance)
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id User ID to delete logs for.
	 * @return int Number of rows deleted.
	 */
	public static function delete_target_user_logs( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table, no caching needed for audit logs.
		$result = $wpdb->delete(
			$table,
			array( 'target_user_id' => $user_id ),
			array( '%d' )
		);

		return absint( $result );
	}

	/**
	 * Get log statistics
	 *
	 * @since 1.4.0
	 *
	 * @return array Statistics array.
	 */
	public static function get_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_admin_audit_log';

		$stats = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['total'] = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		/* Today's entries */
		$today_start = gmdate( 'Y-m-d 00:00:00' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['today'] = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE created_at >= %s',
				$table,
				$today_start
			)
		);

		/* This week's entries */
		$week_start = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['week'] = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE created_at >= %s',
				$table,
				$week_start
			)
		);

		/* This month's entries */
		$month_start = gmdate( 'Y-m-01 00:00:00' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['month'] = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE created_at >= %s',
				$table,
				$month_start
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['success'] = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE action_status = %s',
				$table,
				'success'
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['failure'] = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE action_status = %s',
				$table,
				'failure'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['top_actions'] = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT action_type, COUNT(*) as count FROM %i GROUP BY action_type ORDER BY count DESC LIMIT 5',
				$table
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit log table statistics, no caching needed.
		$stats['top_admins'] = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT admin_id, admin_username, COUNT(*) as count FROM %i GROUP BY admin_id, admin_username ORDER BY count DESC LIMIT 5',
				$table
			),
			ARRAY_A
		);

		return $stats;
	}

	/**
	 * Log bulk action
	 *
	 * @since 1.4.0
	 *
	 * @param string $action   Action name.
	 * @param array  $user_ids Affected user IDs.
	 * @param string $status   'success' or 'failure'.
	 * @param array  $results  Detailed results.
	 * @return bool True on success, false on failure.
	 */
	public static function log_bulk_action( $action, $user_ids, $status, $results = array() ) {
		return self::log(
			get_current_user_id(),
			self::EVENT_BULK_ACTION,
			$status,
			sprintf( 'Bulk %s applied to %d users', $action, count( $user_ids ) ),
			null,
			array(
				'action'     => $action,
				'user_count' => count( $user_ids ),
				'user_ids'   => $user_ids,
				'results'    => $results,
			)
		);
	}

	/**
	 * Check if action type should be logged based on settings
	 *
	 * @since 1.4.0
	 *
	 * @param string $action_type Action type constant.
	 * @return bool True if should log, false otherwise.
	 */
	private static function should_log_action( $action_type ) {
		$categories = NBUF_Options::get(
			'nbuf_logging_admin_audit_categories',
			array(
				'user_deletion'        => true,
				'role_changes'         => true,
				'settings_changes'     => true,
				'bulk_actions'         => true,
				'manual_verifications' => true,
				'password_resets'      => true,
				'profile_edits'        => true,
			)
		);

		/* Map action types to categories */
		$action_category_map = array(
			self::EVENT_USER_DELETED            => 'user_deletion',
			self::EVENT_USER_CREATED            => 'user_deletion',
			self::EVENT_ROLE_CHANGED            => 'role_changes',
			self::EVENT_SETTINGS_CHANGED        => 'settings_changes',
			self::EVENT_BULK_ACTION             => 'bulk_actions',
			self::EVENT_MANUAL_VERIFY           => 'manual_verifications',
			self::EVENT_MANUAL_UNVERIFY         => 'manual_verifications',
			self::EVENT_PASSWORD_RESET_BY_ADMIN => 'password_resets',
			self::EVENT_2FA_RESET_BY_ADMIN      => 'password_resets',
			self::EVENT_PROFILE_EDITED_BY_ADMIN => 'profile_edits',
			self::EVENT_EMAIL_CHANGED_BY_ADMIN  => 'profile_edits',
			self::EVENT_ACCOUNT_MERGE           => 'bulk_actions',
			self::EVENT_LOGS_PURGED             => 'settings_changes',
		);

		/* Get category for this action type */
		$category = isset( $action_category_map[ $action_type ] ) ? $action_category_map[ $action_type ] : null;

		/* If category not found, log by default */
		if ( null === $category ) {
			return true;
		}

		/* Check if category is enabled */
		return isset( $categories[ $category ] ) && $categories[ $category ];
	}

	/**
	 * Get IP address with optional anonymization
	 *
	 * @since 1.4.0
	 *
	 * @return string IP address (potentially anonymized).
	 */
	private static function get_ip_address() {
		$ip = '';

		/* Get IP from various server variables */
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/* Anonymize if enabled */
		if ( NBUF_Options::get( 'nbuf_logging_anonymize_ip', false ) ) {
			$ip = self::anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Anonymize IP address (GDPR compliance)
	 *
	 * @since 1.4.0
	 *
	 * @param string $ip IP address to anonymize.
	 * @return string Anonymized IP address.
	 */
	private static function anonymize_ip( $ip ) {
		/* Check if IPv6 */
		if ( false !== strpos( $ip, ':' ) ) {
			/* IPv6: Keep first 4 groups, zero out the rest */
			$parts = explode( ':', $ip );
			$parts = array_slice( $parts, 0, 4 );
			return implode( ':', $parts ) . '::';
		}

		/* IPv4: Keep first 3 octets, zero out the last */
		$parts = explode( '.', $ip );
		if ( 4 === count( $parts ) ) {
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		return $ip;
	}
}
