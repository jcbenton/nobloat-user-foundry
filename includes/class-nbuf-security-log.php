<?php
/**
 * NoBloat User Foundry - Security Log
 *
 * Handles security event logging to database for compliance, auditing, and forensics.
 * Separate from user activity logs to track security-critical events like:
 * - Failed validation attempts
 * - Privilege escalation attempts
 * - Suspicious file operations
 * - Account merge security events
 * - Configuration changes
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Security_Log
 *
 * Database-backed security event logging system.
 */
class NBUF_Security_Log {

	/**
	 * Database table name (without prefix)
	 *
	 * @var string
	 */
	const TABLE_NAME = 'nbuf_security_log';

	/**
	 * Maximum number of rows to export in CSV
	 *
	 * @var int
	 */
	const MAX_EXPORT_LIMIT = 100000;

	/**
	 * Schema version for tracking migrations
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 2;

	/**
	 * Initialize security log
	 *
	 * Sets up database table and cron jobs.
	 *
	 * @return void
	 */
	public static function init(): void {
		/* Create database table on activation */
		register_activation_hook( NBUF_PLUGIN_FILE, array( __CLASS__, 'create_table' ) );

		/* Check for schema updates on admin pages */
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_schema' ) );

		/* Register daily cron for log pruning */
		add_action( 'nbuf_daily_security_log_prune', array( __CLASS__, 'prune_old_logs' ) );

		/* Schedule cron if not already scheduled */
		if ( ! wp_next_scheduled( 'nbuf_daily_security_log_prune' ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_daily_security_log_prune' );
		}
	}

	/**
	 * Check and run schema upgrades if needed
	 *
	 * @return void
	 */
	public static function maybe_upgrade_schema(): void {
		$current_version = get_option( 'nbuf_security_log_schema_version', 0 );

		if ( $current_version < self::SCHEMA_VERSION ) {
			self::migrate_existing_records();
			update_option( 'nbuf_security_log_schema_version', self::SCHEMA_VERSION );
		}
	}

	/**
	 * Create security log database table
	 *
	 * Uses InnoDB engine for data integrity and proper indexing.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			timestamp DATETIME NOT NULL,
			first_seen DATETIME DEFAULT NULL,
			occurrence_count INT UNSIGNED DEFAULT 1,
			severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
			event_type VARCHAR(50) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_agent TEXT DEFAULT NULL,
			message TEXT NOT NULL,
			context LONGTEXT DEFAULT NULL,
			INDEX idx_timestamp (timestamp),
			INDEX idx_severity (severity),
			INDEX idx_event_type (event_type),
			INDEX idx_user_id (user_id),
			INDEX idx_ip_address (ip_address),
			INDEX idx_severity_timestamp (severity, timestamp),
			INDEX idx_event_type_timestamp (event_type, timestamp),
			INDEX idx_user_id_timestamp (user_id, timestamp),
			INDEX idx_event_ip (event_type, ip_address)
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		/* Migrate existing records - set first_seen = timestamp where NULL */
		self::migrate_existing_records();
	}

	/**
	 * Migrate existing table schema and records
	 *
	 * Adds missing columns and migrates data for upgrades.
	 *
	 * @return void
	 */
	private static function migrate_existing_records(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		/*
		 * Check if table exists.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return;
		}

		/*
		 * Check and add first_seen column if missing.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema migration check.
		$first_seen_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'first_seen'",
				DB_NAME,
				$table_name
			)
		);

		if ( ! $first_seen_exists ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN first_seen DATETIME DEFAULT NULL AFTER timestamp',
					$table_name
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		/*
		 * Check and add occurrence_count column if missing.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema migration check.
		$occurrence_count_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'occurrence_count'",
				DB_NAME,
				$table_name
			)
		);

		if ( ! $occurrence_count_exists ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN occurrence_count INT UNSIGNED DEFAULT 1 AFTER first_seen',
					$table_name
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		/*
		 * Add index for event_type + ip_address lookups if missing.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$index_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_event_ip'",
				DB_NAME,
				$table_name
			)
		);

		if ( ! $index_exists ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD INDEX idx_event_ip (event_type, ip_address)',
					$table_name
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		/*
		 * Set first_seen = timestamp for existing records where first_seen is NULL.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET first_seen = timestamp WHERE first_seen IS NULL',
				$table_name
			)
		);

		/*
		 * Set occurrence_count = 1 for existing records where it's NULL.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET occurrence_count = 1 WHERE occurrence_count IS NULL',
				$table_name
			)
		);
	}

	/**
	 * Log security event to database
	 *
	 * @param string               $event_type Event type slug (e.g., 'file_validation_failed').
	 * @param string               $severity   Severity level: 'info', 'warning', or 'critical'.
	 * @param string               $message    Human-readable message.
	 * @param array<string, mixed> $context    Additional context data (stored as JSON).
	 * @param int|null             $user_id    User ID (optional, defaults to current user).
	 * @return bool True on success, false on failure.
	 */
	public static function log( string $event_type, string $severity, string $message, array $context = array(), ?int $user_id = null ): bool {
		global $wpdb;

		/* Check if logging is enabled */
		if ( ! self::is_enabled() ) {
			return false;
		}

		/* Validate severity enum */
		$valid_severities = array( 'info', 'warning', 'critical' );
		if ( ! in_array( $severity, $valid_severities, true ) ) {
			$severity = 'info'; /* Default to info for invalid values */
		}

		/* Default to current user if not specified */
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		/* Sanitize and limit context data to prevent DoS */
		$sanitized_context = self::sanitize_context( $context );

		$now = gmdate( 'Y-m-d H:i:s' );

		// Insert log entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security logging requires direct database call to prevent infinite recursion.
		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'timestamp'        => $now,
				'first_seen'       => $now,
				'occurrence_count' => 1,
				'severity'         => $severity,
				'event_type'       => $event_type,
				'user_id'          => $user_id,
				'ip_address'       => self::get_client_ip(),
				'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '',
				'message'          => $message,
				'context'          => wp_json_encode( $sanitized_context ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		/* Send email alert for critical events with rate limiting */
		if ( 'critical' === $severity ) {
			$rate_limit_key = 'nbuf_security_alert_last_sent_' . md5( $event_type );
			$last_sent      = get_transient( $rate_limit_key );

			/* Only send if no alert was sent in last 5 minutes for this event type */
			if ( false === $last_sent ) {
				self::send_critical_alert( $event_type, $message, $context, $user_id );
				set_transient( $rate_limit_key, time(), 5 * MINUTE_IN_SECONDS );
			}
		}

		return false !== $result;
	}

	/**
	 * Log or update security event (upsert for aggregation)
	 *
	 * For repeated events from the same IP (like failed logins), this method
	 * updates an existing record instead of creating duplicates. This reduces
	 * log pollution while maintaining accurate occurrence counts.
	 *
	 * @param string               $event_type Event type slug (e.g., 'login_failed').
	 * @param string               $severity   Severity level: 'info', 'warning', or 'critical'.
	 * @param string               $message    Human-readable message.
	 * @param array<string, mixed> $context    Additional context data (stored as JSON).
	 * @param int|null             $user_id    User ID (optional, defaults to current user).
	 * @return bool True on success, false on failure.
	 */
	public static function log_or_update( string $event_type, string $severity, string $message, array $context = array(), ?int $user_id = null ): bool {
		global $wpdb;

		/* Check if logging is enabled */
		if ( ! self::is_enabled() ) {
			return false;
		}

		/* Validate severity enum */
		$valid_severities = array( 'info', 'warning', 'critical' );
		if ( ! in_array( $severity, $valid_severities, true ) ) {
			$severity = 'info';
		}

		/* Default to current user if not specified */
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$ip_address = self::get_client_ip();
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$now        = gmdate( 'Y-m-d H:i:s' );

		/*
		 * Look for existing record with same event_type and ip_address.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security logging upsert check.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, occurrence_count, severity FROM %i WHERE event_type = %s AND ip_address = %s ORDER BY timestamp DESC LIMIT 1',
				$table_name,
				$event_type,
				$ip_address
			)
		);

		if ( $existing ) {
			/* Update existing record: increment count, update timestamp and context */
			$new_count = (int) $existing->occurrence_count + 1;

			/* Escalate severity if needed (warning -> critical) */
			$new_severity = $severity;
			if ( 'critical' === $severity || 'critical' === $existing->severity ) {
				$new_severity = 'critical';
			} elseif ( 'warning' === $severity || 'warning' === $existing->severity ) {
				$new_severity = 'warning';
			}

			/* Sanitize and update context */
			$sanitized_context              = self::sanitize_context( $context );
			$sanitized_context['last_seen'] = $now;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security logging upsert update.
			$result = $wpdb->update(
				$table_name,
				array(
					'timestamp'        => $now,
					'occurrence_count' => $new_count,
					'severity'         => $new_severity,
					'message'          => $message,
					'context'          => wp_json_encode( $sanitized_context ),
					'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '',
				),
				array( 'id' => $existing->id ),
				array( '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			/* Send email alert for critical events (rate limited) */
			if ( 'critical' === $new_severity ) {
				$rate_limit_key = 'nbuf_security_alert_last_sent_' . md5( $event_type . '_' . $ip_address );
				$last_sent      = get_transient( $rate_limit_key );

				if ( false === $last_sent ) {
					self::send_critical_alert( $event_type, $message . ' (Occurrence #' . $new_count . ')', $context, $user_id );
					set_transient( $rate_limit_key, time(), 5 * MINUTE_IN_SECONDS );
				}
			}

			return false !== $result;
		}

		/* No existing record - insert new one */
		$sanitized_context = self::sanitize_context( $context );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Security logging insert.
		$result = $wpdb->insert(
			$table_name,
			array(
				'timestamp'        => $now,
				'first_seen'       => $now,
				'occurrence_count' => 1,
				'severity'         => $severity,
				'event_type'       => $event_type,
				'user_id'          => $user_id,
				'ip_address'       => $ip_address,
				'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '',
				'message'          => $message,
				'context'          => wp_json_encode( $sanitized_context ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		/* Send email alert for critical events */
		if ( 'critical' === $severity ) {
			$rate_limit_key = 'nbuf_security_alert_last_sent_' . md5( $event_type . '_' . $ip_address );
			$last_sent      = get_transient( $rate_limit_key );

			if ( false === $last_sent ) {
				self::send_critical_alert( $event_type, $message, $context, $user_id );
				set_transient( $rate_limit_key, time(), 5 * MINUTE_IN_SECONDS );
			}
		}

		return false !== $result;
	}

	/**
	 * Sanitize and limit context data to prevent DoS attacks
	 *
	 * Limits the size and depth of context arrays to prevent memory exhaustion
	 * and database bloat from maliciously large context data.
	 *
	 * @param array<string, mixed> $context Raw context data.
	 * @return array<string, mixed> Sanitized context data.
	 */
	private static function sanitize_context( array $context ): array {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $context as $key => $value ) {
			/* Sanitize array keys */
			$safe_key = sanitize_key( $key );

			if ( is_string( $value ) ) {
				/* Limit string length to 10,000 characters to prevent bloat (multibyte-safe) */
				$sanitized_value = sanitize_text_field( $value );
				if ( mb_strlen( $sanitized_value, 'UTF-8' ) > 10000 ) {
					$sanitized[ $safe_key ] = mb_substr( $sanitized_value, 0, 10000, 'UTF-8' );
				} else {
					$sanitized[ $safe_key ] = $sanitized_value;
				}
			} elseif ( is_numeric( $value ) ) {
				/* Store numeric values as-is */
				$sanitized[ $safe_key ] = $value;
			} elseif ( is_bool( $value ) ) {
				/* Store boolean values as-is */
				$sanitized[ $safe_key ] = $value;
			} elseif ( is_array( $value ) ) {
				/* Limit array depth - convert nested arrays to JSON string */
				$json_value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE );
				/* Limit JSON string to 10,000 characters */
				$sanitized[ $safe_key ] = substr( $json_value, 0, 10000 );
			}

			/* Limit total context array size to 50 items to prevent memory issues */
			if ( count( $sanitized ) >= 50 ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Check if security logging is enabled
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	private static function is_enabled() {
		return (bool) NBUF_Options::get( 'nbuf_security_log_enabled', true );
	}

	/**
	 * Get client IP address
	 *
	 * Handles proxy headers and returns sanitized IP.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		$ip = '';

		/*
		 * SECURITY: Prevent IP spoofing via X-Forwarded-For header
		 *
		 * Only trust proxy headers if request originates from a trusted proxy.
		 * This prevents attackers from bypassing security logging by sending fake
		 * X-Forwarded-For headers.
		 */
		$trusted_proxies = NBUF_Options::get( 'nbuf_login_trusted_proxies', array() );
		$remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/* Only trust X-Forwarded-For/Client-IP if request comes from trusted proxy */
		if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
			if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			}
		}

		/* Fallback to REMOTE_ADDR (cannot be spoofed) */
		if ( empty( $ip ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/* Handle multiple IPs from proxy (take first one) */
		if ( strpos( $ip, ',' ) !== false ) {
			$ip_array = explode( ',', $ip );
			$ip       = trim( $ip_array[0] );
		}

		/* Validate IP address format */
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Filter sensitive data from context for email alerts
	 *
	 * Removes or redacts sensitive information like file paths, hashes,
	 * and other data that should not be transmitted via email.
	 *
	 * @param array<string, mixed> $context   Raw context data.
	 * @param int                  $depth     Current recursion depth (for protection against stack overflow).
	 * @param int                  $max_depth Maximum nesting depth allowed.
	 * @return array<string, mixed> Filtered context safe for email transmission.
	 */
	private static function filter_sensitive_context( array $context, int $depth = 0, int $max_depth = 10 ): array {
		if ( ! is_array( $context ) ) {
			return array();
		}

		/* Prevent infinite recursion / stack overflow */
		if ( $depth >= $max_depth ) {
			return array( '__truncated__' => 'Maximum nesting depth exceeded' );
		}

		$filtered = array();

		/* Sensitive keys to redact */
		$redact_keys = array(
			'source_path',
			'dest_path',
			'file_path',
			'path',
			'source_hash',
			'dest_hash',
			'hash',
			'password',
			'pass',
			'secret',
			'token',
			'key',
		);

		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( $key );

			/* Check if key should be redacted */
			$should_redact = false;
			foreach ( $redact_keys as $redact_key ) {
				if ( false !== strpos( $lower_key, $redact_key ) ) {
					$should_redact = true;
					break;
				}
			}

			if ( $should_redact ) {
				/* Redact sensitive values */
				if ( is_string( $value ) && ! empty( $value ) ) {
					$filtered[ $key ] = '[REDACTED]';
				}
			} elseif ( is_scalar( $value ) ) {
				/* Keep non-sensitive scalar values */
				$filtered[ $key ] = $value;
			} elseif ( is_array( $value ) ) {
				/* Recursively filter nested arrays with depth tracking */
				$filtered[ $key ] = self::filter_sensitive_context( $value, $depth + 1, $max_depth );
			}
		}

		return $filtered;
	}

	/**
	 * Send critical security alert email
	 *
	 * @param string               $event_type Event type.
	 * @param string               $message    Alert message.
	 * @param array<string, mixed> $context    Event context.
	 * @param int|null             $user_id    User ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function send_critical_alert( string $event_type, string $message, array $context, ?int $user_id ) {
		/* Check if critical alerts are enabled */
		if ( ! NBUF_Options::get( 'nbuf_security_log_alerts_enabled', false ) ) {
			return new WP_Error( 'alerts_disabled', __( 'Security alerts are currently disabled.', 'nobloat-user-foundry' ) );
		}

		/* Get recipient email */
		$recipient_type = NBUF_Options::get( 'nbuf_security_log_recipient_type', 'admin' );

		if ( 'admin' === $recipient_type ) {
			$recipient = get_option( 'admin_email' );
		} else {
			$recipient = NBUF_Options::get( 'nbuf_security_log_custom_email', '' );
			/* Validate custom email */
			if ( ! is_email( $recipient ) ) {
				$recipient = get_option( 'admin_email' ); /* Fallback to admin */
			}
		}

		/* Get user info */
		$user       = get_userdata( $user_id );
		$username   = $user ? $user->user_login : __( 'Unknown', 'nobloat-user-foundry' );
		$user_email = $user ? $user->user_email : __( 'Unknown', 'nobloat-user-foundry' );

		/* Get template */
		$template = self::get_email_template();

		/* Filter sensitive data from context before email transmission */
		$filtered_context = self::filter_sensitive_context( $context );

		/* Prepare replacements */
		$replacements = array(
			'{site_name}'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'   => home_url(),
			'{event_type}' => esc_html( $event_type ),
			'{message}'    => esc_html( $message ),
			'{username}'   => esc_html( $username ),
			'{user_email}' => esc_html( $user_email ),
			'{user_id}'    => absint( $user_id ),
			'{ip_address}' => esc_html( self::get_client_ip() ),
			'{timestamp}'  => esc_html( wp_date( 'Y-m-d H:i:s T' ) ),
			'{log_url}'    => admin_url( 'admin.php?page=nobloat-foundry-security-log' ),
			'{context}'    => esc_html( wp_json_encode( $filtered_context, JSON_PRETTY_PRINT ) ),
		);

		/* Replace placeholders */
		$email_body = strtr( $template, $replacements );

		/* Subject - sanitize event_type to prevent email header injection */
		$safe_event_type = str_replace( array( "\r", "\n", "\t" ), ' ', $event_type );
		$safe_event_type = sanitize_text_field( $safe_event_type );

		$subject = sprintf(
			/* translators: 1: Site name, 2: Event type */
			__( '[Security Alert] %1$s - %2$s', 'nobloat-user-foundry' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$safe_event_type
		);

		/* Send email using central sender */
		$result = NBUF_Email::send( $recipient, $subject, $email_body, 'html' );

		if ( ! $result ) {
			return new WP_Error( 'email_failed', __( 'Failed to send security alert email.', 'nobloat-user-foundry' ) );
		}

		return true;
	}

	/**
	 * Get security alert email template
	 *
	 * Loads from template manager or returns default.
	 *
	 * @return string Email template HTML.
	 */
	private static function get_email_template() {
		if ( class_exists( 'NBUF_Template_Manager' ) ) {
			return NBUF_Template_Manager::load_template( 'security-alert-email-html' );
		}

		/* Fallback default template */
		return self::get_default_email_template();
	}

	/**
	 * Get default email template
	 *
	 * @return string Default HTML template.
	 */
	private static function get_default_email_template() {
		return '<html>
<body style="font-family: Arial, sans-serif; background: #f8f8f8; padding: 20px;">
  <table width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; box-shadow: 0 0 8px rgba(0,0,0,0.05);">
    <tr>
      <td style="padding: 30px;">
        <h2 style="color: #d63638; margin-top: 0;">🛡️ Security Alert</h2>
        <p>A critical security event was detected on <strong>{site_name}</strong>.</p>

        <div style="background: #fff3cd; border-left: 4px solid #d63638; padding: 15px; margin: 20px 0;">
          <strong style="color: #d63638;">Event Type:</strong> {event_type}<br>
          <strong style="color: #d63638;">Message:</strong> {message}
        </div>

        <h3 style="color: #333333;">Event Details</h3>
        <table style="width: 100%; border-collapse: collapse;">
          <tr style="border-bottom: 1px solid #e5e5e5;">
            <td style="padding: 8px 0; font-weight: 600;">User:</td>
            <td style="padding: 8px 0;">{username} (ID: {user_id})</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e5e5;">
            <td style="padding: 8px 0; font-weight: 600;">Email:</td>
            <td style="padding: 8px 0;">{user_email}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e5e5;">
            <td style="padding: 8px 0; font-weight: 600;">IP Address:</td>
            <td style="padding: 8px 0;">{ip_address}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e5e5;">
            <td style="padding: 8px 0; font-weight: 600;">Timestamp:</td>
            <td style="padding: 8px 0;">{timestamp}</td>
          </tr>
        </table>

        <div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px;">
          <strong>Additional Context:</strong><br>
          <pre style="margin: 10px 0; font-size: 12px; overflow-x: auto;">{context}</pre>
        </div>

        <p style="text-align:center; margin: 30px 0;">
          <a href="{log_url}" style="background-color:#d63638; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:4px; display:inline-block;">View Security Log</a>
        </p>

        <hr style="margin:30px 0; border:none; border-top:1px solid #e5e5e5;">
        <p style="font-size:12px; color:#777;">
          This is an automated security alert from {site_name}.<br>
          {site_url}
        </p>
      </td>
    </tr>
  </table>
</body>
</html>';
	}

	/**
	 * Send test security alert email
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_test_email() {
		return self::send_critical_alert(
			'test_email',
			__( 'This is a test security alert email to verify your configuration.', 'nobloat-user-foundry' ),
			array(
				'test'    => true,
				'sent_by' => get_current_user_id(),
				'note'    => 'If you received this email, your security alert configuration is working correctly.',
			),
			get_current_user_id()
		);
	}

	/**
	 * Prune old log entries based on retention period
	 *
	 * Runs daily via cron.
	 *
	 * @return int Number of deleted entries.
	 */
	public static function prune_old_logs() {
		global $wpdb;

		$retention_period = NBUF_Options::get( 'nbuf_security_log_retention', '90days' );
		$table_name       = $wpdb->prefix . self::TABLE_NAME;

		/* Convert retention period to days */
		$retention_map = array(
			'7days'   => 7,
			'30days'  => 30,
			'90days'  => 90,
			'180days' => 180,
			'365days' => 365,
			'2years'  => 730,
			'forever' => 0,
		);

		$retention_days = isset( $retention_map[ $retention_period ] ) ? $retention_map[ $retention_period ] : 90;

		/* Don't prune if set to forever */
		if ( 0 === $retention_days ) {
			return 0;
		}

		// Delete old logs.
		$table_name = $wpdb->prefix . 'nbuf_security_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cron job deletes old logs.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$table_name,
				$retention_days
			)
		);

		/* Log the pruning action */
		if ( $deleted > 0 ) {
			self::log(
				'log_pruned',
				'info',
				sprintf(
					/* translators: 1: Number of entries deleted, 2: Retention days */
					__( 'Pruned %1$d security log entries older than %2$d days', 'nobloat-user-foundry' ),
					$deleted,
					$retention_days
				),
				array(
					'rows_deleted'   => $deleted,
					'retention_days' => $retention_days,
				),
				0 /* System action */
			);
		}

		return $deleted ? $deleted : 0;
	}

	/**
	 * Get log entries with filtering and pagination
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, object> Array of log entries.
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'severity'   => '',
			'event_type' => '',
			'user_id'    => '',
			'date_from'  => '',
			'date_to'    => '',
			'search'     => '',
			'orderby'    => 'timestamp',
			'order'      => 'DESC',
			'limit'      => 25,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where      = array( '1=1' );
		$where_args = array();

		/* Filter by severity */
		if ( ! empty( $args['severity'] ) && in_array( $args['severity'], array( 'info', 'warning', 'critical' ), true ) ) {
			$where[]      = 'severity = %s';
			$where_args[] = $args['severity'];
		}

		/* Filter by event type */
		if ( ! empty( $args['event_type'] ) ) {
			$where[]      = 'event_type = %s';
			$where_args[] = $args['event_type'];
		}

		/* Filter by user */
		if ( ! empty( $args['user_id'] ) ) {
			$where[]      = 'user_id = %d';
			$where_args[] = intval( $args['user_id'] );
		}

		/* Filter by date range with strict DateTime validation */
		if ( ! empty( $args['date_from'] ) ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			/* Strict validation using DateTime to prevent SQL injection */
			$date_obj = DateTime::createFromFormat( 'Y-m-d', $date_from );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $date_from ) {
				$where[]      = 'timestamp >= %s';
				$where_args[] = $date_from . ' 00:00:00';
			} else {
				/* Log invalid date format (potential attack attempt) */
				self::log(
					'invalid_date_filter',
					'warning',
					'Invalid date_from format in security log filter',
					array(
						'provided_value'  => $date_from,
						'expected_format' => 'YYYY-MM-DD',
					)
				);
			}
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date_to = sanitize_text_field( $args['date_to'] );
			/* Strict validation using DateTime to prevent SQL injection */
			$date_obj = DateTime::createFromFormat( 'Y-m-d', $date_to );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $date_to ) {
				$where[]      = 'timestamp <= %s';
				$where_args[] = $date_to . ' 23:59:59';
			} else {
				/* Log invalid date format (potential attack attempt) */
				self::log(
					'invalid_date_filter',
					'warning',
					'Invalid date_to format in security log filter',
					array(
						'provided_value'  => $date_to,
						'expected_format' => 'YYYY-MM-DD',
					)
				);
			}
		}

		/* Search in message */
		if ( ! empty( $args['search'] ) ) {
			$where[]      = 'message LIKE %s';
			$where_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		/* Whitelist allowed orderby columns to prevent SQL injection */
		$allowed_orderby = array( 'timestamp', 'severity', 'event_type', 'user_id', 'id', 'occurrence_count', 'first_seen' );
		$orderby_column  = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'timestamp';

		/* Whitelist allowed order direction */
		$order_direction = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		/* Build WHERE clause */
		$where_clause = implode( ' AND ', $where );
		$table_name   = $wpdb->prefix . 'nbuf_security_log';

		/*
		 * Build base query with whitelisted orderby and order (safe, from whitelist).
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby_column and $order_direction are whitelisted values.
		$base_query = 'SELECT * FROM %i WHERE ' . $where_clause . ' ORDER BY ' . $orderby_column . ' ' . $order_direction . ' LIMIT %d OFFSET %d';

		/* Build complete query with all parameters */
		if ( ! empty( $where_args ) ) {
			$where_args[] = $args['limit'];
			$where_args[] = $args['offset'];
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with whitelisted values and placeholders for user input.
			$query = $wpdb->prepare( $base_query, array_merge( array( $table_name ), $where_args ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with whitelisted values, only LIMIT/OFFSET need preparation.
			$query = $wpdb->prepare( $base_query, $table_name, $args['limit'], $args['offset'] );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query fully prepared above, orderby/order are whitelisted.
		return $wpdb->get_results( $query );
	}

	/**
	 * Get total log count with filtering
	 *
	 * @param array<string, mixed> $args Query arguments (same as get_logs).
	 * @return int Total count.
	 */
	public static function get_log_count( array $args = array() ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where      = array( '1=1' );
		$where_args = array();

		/* Apply same filters as get_logs */
		if ( ! empty( $args['severity'] ) && in_array( $args['severity'], array( 'info', 'warning', 'critical' ), true ) ) {
			$where[]      = 'severity = %s';
			$where_args[] = $args['severity'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[]      = 'event_type = %s';
			$where_args[] = $args['event_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]      = 'user_id = %d';
			$where_args[] = intval( $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$date_from = sanitize_text_field( $args['date_from'] );
			/* Validate date format YYYY-MM-DD */
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$where[]      = 'timestamp >= %s';
				$where_args[] = $date_from . ' 00:00:00';
			}
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date_to = sanitize_text_field( $args['date_to'] );
			/* Validate date format YYYY-MM-DD */
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$where[]      = 'timestamp <= %s';
				$where_args[] = $date_to . ' 23:59:59';
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]      = 'message LIKE %s';
			$where_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		/* Build WHERE clause */
		$where_clause = implode( ' AND ', $where );
		$table_name   = $wpdb->prefix . 'nbuf_security_log';

		/* Build base query with WHERE clause */
		$base_query = 'SELECT COUNT(*) FROM %i WHERE ' . $where_clause;

		/* Build complete query with all parameters */
		if ( ! empty( $where_args ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom security log table, query built with placeholders, search is escaped with esc_like.
			return (int) $wpdb->get_var( $wpdb->prepare( $base_query, array_merge( array( $table_name ), $where_args ) ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom security log table, query built with placeholders.
			return (int) $wpdb->get_var( $wpdb->prepare( $base_query, $table_name ) );
		}
	}

	/**
	 * Get log statistics
	 *
	 * @return array<string, int> Statistics array.
	 */
	public static function get_statistics(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_security_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query for dashboard.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
					SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
					SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) as info,
					SUM(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_7_days,
					SUM(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days
				FROM %i",
				$table_name
			),
			ARRAY_A
		);

		return $stats ? $stats : array(
			'total'        => 0,
			'critical'     => 0,
			'warning'      => 0,
			'info'         => 0,
			'last_7_days'  => 0,
			'last_30_days' => 0,
		);
	}

	/**
	 * Get log statistics for admin page
	 *
	 * @return array<string, mixed> Statistics array with additional database info.
	 */
	public static function get_stats(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Get basic statistics.
		$basic_stats = self::get_statistics();

		// Get database size.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query for admin.
		$size_query = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
				FROM information_schema.TABLES
				WHERE table_schema = %s
				AND table_name = %s',
				DB_NAME,
				$table_name
			)
		);

		$database_size = $size_query ? $size_query . ' MB' : '0 MB';

		// Get oldest entry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query for admin.
		$oldest       = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(timestamp) FROM %i', $table_name ) );
		$oldest_entry = $oldest ? mysql2date( 'F j, Y g:i A', $oldest ) : __( 'No entries', 'nobloat-user-foundry' );

		// Get last cleanup time.
		$last_cleanup = NBUF_Options::get( 'nbuf_security_log_last_cleanup', '' );
		$last_cleanup = $last_cleanup ? mysql2date( 'F j, Y g:i A', $last_cleanup ) : __( 'Never', 'nobloat-user-foundry' );

		return array(
			'total_entries' => isset( $basic_stats['total'] ) ? $basic_stats['total'] : 0,
			'database_size' => $database_size,
			'oldest_entry'  => $oldest_entry,
			'last_cleanup'  => $last_cleanup,
		);
	}

	/**
	 * Escape CSV field to prevent formula injection
	 *
	 * Prevents CSV injection by prefixing dangerous characters with a single quote.
	 * Dangerous characters: = + - @ \t \r (can trigger formula execution in Excel/LibreOffice)
	 *
	 * @param string $value Field value to escape.
	 * @return string Escaped value.
	 */
	private static function csv_escape( $value ) {
		/* Convert to string */
		$value = (string) $value;

		/* Check if value starts with dangerous characters */
		if ( ! empty( $value ) && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			/* Prefix with single quote to neutralize formula execution */
			$value = "'" . $value;
		}

		/* Standard CSV double-quote escaping */
		return str_replace( '"', '""', $value );
	}

	/**
	 * Export security logs to CSV
	 *
	 * @param array<string, mixed> $filters Optional filters to apply.
	 * @return string CSV content.
	 */
	public static function export_to_csv( array $filters = array() ): string {
		// Get logs with filters (up to MAX_EXPORT_LIMIT).
		$filters['limit']  = self::MAX_EXPORT_LIMIT;
		$filters['offset'] = 0;
		$logs              = self::get_logs( $filters );

		// Build CSV content.
		$csv = '';

		// Headers.
		$csv .= '"Last Seen","First Seen","Count","Severity","Event Type","User ID","Username","IP Address","Message","Context"' . "\n";

		// Data rows.
		foreach ( $logs as $log ) {
			$user     = get_userdata( $log->user_id );
			$username = $user ? $user->user_login : 'N/A';

			$csv .= sprintf(
				'"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
				self::csv_escape( $log->timestamp ),
				self::csv_escape( isset( $log->first_seen ) ? $log->first_seen : $log->timestamp ),
				self::csv_escape( isset( $log->occurrence_count ) ? $log->occurrence_count : 1 ),
				self::csv_escape( $log->severity ),
				self::csv_escape( $log->event_type ),
				self::csv_escape( $log->user_id ? $log->user_id : 'N/A' ),
				self::csv_escape( $username ),
				self::csv_escape( $log->ip_address ),
				self::csv_escape( $log->message ),
				self::csv_escape( $log->context ? $log->context : '' )
			);
		}

		return $csv;
	}

	/**
	 * Purge all security logs
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function purge_all_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_security_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin action to delete all logs.
		$result = $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );

		if ( false !== $result ) {
			// Update last cleanup time (stored in UTC).
			NBUF_Options::update( 'nbuf_security_log_last_cleanup', gmdate( 'Y-m-d H:i:s' ), false, 'system' );
			return true;
		}

		return false;
	}

	/**
	 * Delete specific security logs by IDs
	 *
	 * @param array<int> $log_ids Array of log IDs to delete.
	 * @return int|false Number of deleted logs, or false on failure.
	 */
	public static function delete_logs( array $log_ids ) {
		if ( empty( $log_ids ) || ! is_array( $log_ids ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_security_log';

		// Sanitize IDs.
		$log_ids = array_map( 'intval', $log_ids );

		// Build placeholders.
		$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

		// Build query with placeholders.
		$query = "DELETE FROM %i WHERE id IN ($placeholders)";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin bulk delete action with dynamic placeholders for sanitized IDs.
		$deleted = $wpdb->query( $wpdb->prepare( $query, array_merge( array( $table_name ), $log_ids ) ) );

		return $deleted;
	}

	/**
	 * Get IP addresses from log IDs
	 *
	 * @param  array<int> $log_ids Array of log entry IDs.
	 * @return array<int, string> Array of unique IP addresses.
	 */
	public static function get_ips_from_log_ids( array $log_ids ): array {
		if ( empty( $log_ids ) || ! is_array( $log_ids ) ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_security_log';

		// Sanitize IDs.
		$log_ids = array_map( 'intval', $log_ids );

		// Build placeholders.
		$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

		// Build query with placeholders.
		$query = "SELECT DISTINCT ip_address FROM %i WHERE id IN ($placeholders) AND ip_address IS NOT NULL AND ip_address != ''";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Admin action with dynamic placeholders for sanitized IDs.
		$ips = $wpdb->get_col( $wpdb->prepare( $query, array_merge( array( $table_name ), $log_ids ) ) );

		return $ips ? $ips : array();
	}

	/**
	 * Prune logs older than specified days
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 */
	public static function prune_logs_older_than( $days ) {
		global $wpdb;

		/* Don't prune if retention is forever */
		if ( 0 === $days || 'forever' === $days ) {
			return 0;
		}

		$table_name  = $wpdb->prefix . self::TABLE_NAME;
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cron job cleanup.
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE timestamp < %s',
				$table_name,
				$cutoff_date
			)
		);

		return absint( $result );
	}

	/**
	 * Anonymize logs for a specific user (GDPR compliance)
	 *
	 * @param int $user_id User ID to anonymize.
	 * @return int Number of rows updated.
	 */
	public static function anonymize_user_logs( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- GDPR data erasure.
		$result = $wpdb->update(
			$table,
			array(
				'ip_address' => '0.0.0.0',
				'user_agent' => null,
				'message'    => 'User data anonymized',
				'context'    => null,
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return absint( $result );
	}

	/**
	 * Delete logs for a specific user (GDPR compliance)
	 *
	 * @param int $user_id User ID to delete logs for.
	 * @return int Number of rows deleted.
	 */
	public static function delete_user_logs( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- GDPR data erasure.
		$result = $wpdb->delete(
			$table,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return absint( $result );
	}
}
