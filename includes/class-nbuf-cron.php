<?php
/**
 * NoBloat User Foundry - Cron Handler
 *
 * Handles scheduling and execution of maintenance tasks,
 * including cleanup of expired verification tokens.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Cron
 *
 * Manages WordPress cron jobs for maintenance tasks.
 */
class NBUF_Cron {


	/**
	 * Activate cron.
	 *
	 * Called on plugin activation. Schedules the cleanup event.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( 'nbuf_cleanup_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_cleanup_cron' );
		}
		if ( ! wp_next_scheduled( 'nbuf_audit_log_cleanup_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_audit_log_cleanup_cron' );
		}
		if ( ! wp_next_scheduled( 'nbuf_cleanup_version_history' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_cleanup_version_history' );
		}
		if ( ! wp_next_scheduled( 'nbuf_cleanup_transients' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_cleanup_transients' );
		}
		if ( ! wp_next_scheduled( 'nbuf_cleanup_unverified_accounts' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_cleanup_unverified_accounts' );
		}
		if ( ! wp_next_scheduled( 'nbuf_enterprise_logging_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_enterprise_logging_cleanup' );
		}
	}

	/**
	 * Deactivate cron.
	 *
	 * Called on plugin deactivation. Removes scheduled events.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'nbuf_cleanup_cron' );
		wp_clear_scheduled_hook( 'nbuf_audit_log_cleanup_cron' );
		wp_clear_scheduled_hook( 'nbuf_cleanup_version_history' );
		wp_clear_scheduled_hook( 'nbuf_cleanup_transients' );
		wp_clear_scheduled_hook( 'nbuf_cleanup_unverified_accounts' );
		wp_clear_scheduled_hook( 'nbuf_enterprise_logging_cleanup' );
	}

	/**
	 * Register cron hooks.
	 *
	 * Links the cron hook to the cleanup routine.
	 */
	public static function register() {
		add_action( 'nbuf_cleanup_cron', array( __CLASS__, 'run_cleanup' ) );
		add_action( 'nbuf_audit_log_cleanup_cron', array( __CLASS__, 'run_audit_log_cleanup' ) );
		add_action( 'nbuf_cleanup_version_history', array( __CLASS__, 'run_version_history_cleanup' ) );
		add_action( 'nbuf_cleanup_transients', array( __CLASS__, 'run_transient_cleanup' ) );
		add_action( 'nbuf_cleanup_unverified_accounts', array( __CLASS__, 'run_unverified_cleanup' ) );
		add_action( 'nbuf_enterprise_logging_cleanup', array( __CLASS__, 'run_enterprise_logging_cleanup' ) );
	}

	/**
	 * Run cleanup.
	 *
	 * Executes daily to remove expired and used tokens.
	 */
	public static function run_cleanup() {
		NBUF_Database::cleanup_expired();
		NBUF_Options::update( 'nbuf_last_cleanup', current_time( 'mysql' ), false, 'system' );
	}

	/**
	 * Run audit log cleanup.
	 *
	 * Executes daily to remove old audit log entries based on retention settings.
	 */
	public static function run_audit_log_cleanup() {
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::cleanup_old_logs();
		}
	}

	/**
	 * Run version history cleanup.
	 *
	 * Executes daily to remove old version history entries based on retention settings.
	 * Only runs if auto-cleanup is enabled in settings.
	 */
	public static function run_version_history_cleanup() {
		/* Check if auto-cleanup is enabled */
		$auto_cleanup = NBUF_Options::get( 'nbuf_version_history_auto_cleanup', true );

		if ( ! $auto_cleanup ) {
			return;
		}

		/* Check if version history system is enabled */
		$vh_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );

		if ( ! $vh_enabled ) {
			return;
		}

		/* Run cleanup if class exists */
		if ( class_exists( 'NBUF_Version_History' ) ) {
			$vh      = new NBUF_Version_History();
			$deleted = $vh->cleanup_old_versions();

			/* Log cleanup result */
			if ( $deleted > 0 ) {
				NBUF_Options::update( 'nbuf_last_vh_cleanup', current_time( 'mysql' ), false, 'system' );
				NBUF_Options::update( 'nbuf_last_vh_cleanup_count', $deleted, false, 'system' );
			}
		}
	}

	/**
	 * Run transient cleanup.
	 *
	 * Removes expired transients from the database.
	 * WordPress versions < 6.1 don't automatically clean up expired transients,
	 * which can cause database bloat over time. This ensures our plugin's
	 * transients are properly cleaned up.
	 *
	 * Only cleans transients prefixed with 'nbuf_' to avoid interfering
	 * with other plugins or WordPress core.
	 */
	public static function run_transient_cleanup() {
		global $wpdb;

		/*
		 * Delete expired transient timeouts
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup requires direct database query for performance.
		$deleted_timeouts = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_timeout_nbuf_%'
			 AND option_value < UNIX_TIMESTAMP()"
		);

		/*
		 * Delete corresponding transient values (orphaned after timeout deletion)
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Orphaned transient cleanup requires direct database query for efficiency.
		$deleted_values = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_nbuf_%'
			 AND option_name NOT IN (
				 SELECT CONCAT('_transient_', SUBSTRING(option_name, 20))
				 FROM {$wpdb->options}
				 WHERE option_name LIKE '_transient_timeout_nbuf_%'
			 )"
		);

		/* Log cleanup statistics */
		if ( $deleted_timeouts > 0 || $deleted_values > 0 ) {
			NBUF_Options::update( 'nbuf_last_transient_cleanup', current_time( 'mysql' ), false, 'system' );
			NBUF_Options::update( 'nbuf_last_transient_cleanup_count', ( $deleted_timeouts + $deleted_values ), false, 'system' );
		}

		return ( $deleted_timeouts + $deleted_values );
	}

	/**
	 * Run unverified accounts cleanup.
	 *
	 * Executes daily to remove accounts that have not verified their email
	 * within the configured time period. Only runs if email verification
	 * is enabled and auto-delete is configured.
	 *
	 * @return int Number of accounts deleted.
	 */
	public static function run_unverified_cleanup(): int {
		/* Check if email verification is required */
		$require_verification = NBUF_Options::get( 'nbuf_require_verification', false );

		if ( ! $require_verification ) {
			return 0;
		}

		/* Get cleanup days setting (0 = disabled) */
		$cleanup_days = (int) NBUF_Options::get( 'nbuf_delete_unverified_days', 5 );

		if ( $cleanup_days <= 0 ) {
			return 0;
		}

		global $wpdb;

		/* Calculate cutoff date */
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$cleanup_days} days" ) );

		/*
		 * Query unverified users older than cutoff date
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table join required for unverified user cleanup.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT u.ID
				 FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->prefix}nbuf_user_data ud ON u.ID = ud.user_id
				 WHERE ud.is_verified = 0
				 AND ud.is_disabled = 0
				 AND u.user_registered < %s
				 LIMIT 100",
				$cutoff_date
			)
		);

		if ( empty( $user_ids ) ) {
			return 0;
		}

		$deleted_count = 0;

		foreach ( $user_ids as $user_id ) {
			/* Safety check: never delete admins */
			$user = get_userdata( $user_id );
			if ( ! $user || user_can( $user_id, 'manage_options' ) ) {
				continue;
			}

			/* Log before deletion */
			NBUF_Audit_Log::log(
				$user_id,
				'users',
				'unverified_account_deleted',
				array(
					'reason'     => 'auto_cleanup',
					'days'       => $cleanup_days,
					'username'   => $user->user_login,
					'email'      => $user->user_email,
					'registered' => $user->user_registered,
				)
			);

			/* Delete user (reassign posts to admin if needed) */
			if ( wp_delete_user( $user_id ) ) {
				++$deleted_count;
			}
		}

		/* Log cleanup statistics */
		if ( $deleted_count > 0 ) {
			NBUF_Options::update( 'nbuf_last_unverified_cleanup', current_time( 'mysql' ), false, 'system' );
			NBUF_Options::update( 'nbuf_last_unverified_cleanup_count', $deleted_count, false, 'system' );
		}

		return $deleted_count;
	}

	/**
	 * Run enterprise logging cleanup
	 *
	 * Executes daily to remove old log entries from all 3 enterprise logging tables
	 * based on their individual retention settings. Supports GDPR-compliant data
	 * minimization by automatically pruning logs according to configured periods.
	 *
	 * Tables pruned:
	 * - User Activity Log (user-initiated actions)
	 * - Admin Actions Log (admin actions on users/settings)
	 * - Security Events Log (system security events)
	 *
	 * @since 1.4.0
	 * @return array Array with counts of deleted entries per table.
	 */
	public static function run_enterprise_logging_cleanup() {
		$results = array(
			'user_audit'   => 0,
			'admin_audit'  => 0,
			'security_log' => 0,
		);

		/* Prune user audit log */
		if ( class_exists( 'NBUF_Audit_Log' ) && NBUF_Options::get( 'nbuf_logging_user_audit_enabled', true ) ) {
			$retention = NBUF_Options::get( 'nbuf_logging_user_audit_retention', '365' );
			if ( 'forever' !== $retention ) {
				$results['user_audit'] = NBUF_Audit_Log::prune_logs_older_than( absint( $retention ) );
			}
		}

		/* Prune admin audit log */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) && NBUF_Options::get( 'nbuf_logging_admin_audit_enabled', true ) ) {
			$retention = NBUF_Options::get( 'nbuf_logging_admin_audit_retention', 'forever' );
			if ( 'forever' !== $retention ) {
				$results['admin_audit'] = NBUF_Admin_Audit_Log::prune_logs_older_than( absint( $retention ) );
			}
		}

		/* Prune security log - uses NBUF_Security_Log's built-in cleanup which reads nbuf_security_log_retention */
		if ( class_exists( 'NBUF_Security_Log' ) && NBUF_Options::get( 'nbuf_security_log_enabled', true ) ) {
			$results['security_log'] = NBUF_Security_Log::prune_old_logs();
		}

		/* Log cleanup statistics */
		$total_deleted = array_sum( $results );
		if ( $total_deleted > 0 ) {
			NBUF_Options::update( 'nbuf_last_enterprise_logging_cleanup', current_time( 'mysql' ), false, 'system' );
			NBUF_Options::update( 'nbuf_last_enterprise_logging_cleanup_count', $total_deleted, false, 'system' );
			NBUF_Options::update( 'nbuf_last_enterprise_logging_cleanup_details', $results, false, 'system' );
		}

		return $results;
	}
}

// Initialize cleanup hook immediately.
NBUF_Cron::register();
