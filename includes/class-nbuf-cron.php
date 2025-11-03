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
}

// Initialize cleanup hook immediately.
NBUF_Cron::register();
