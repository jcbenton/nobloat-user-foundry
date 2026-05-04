<?php
/**
 * NoBloat User Foundry - Webhooks
 *
 * Handles webhook management and delivery for user events.
 * Sends HTTP POST notifications to external services when
 * configured user events occur (registration, login, etc.).
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Class NBUF_Webhooks
 *
 * Manages webhook configuration, delivery, and logging.
 */
class NBUF_Webhooks {

	/**
	 * Available webhook events.
	 *
	 * @var array<string, string>
	 */
	private static $available_events = array(
		'user_registered'     => 'User Registered',
		'user_verified'       => 'User Verified Email',
		'user_login'          => 'User Logged In',
		'user_logout'         => 'User Logged Out',
		'user_profile_update' => 'User Profile Updated',
		'user_password_reset' => 'User Password Reset',
		'user_2fa_enabled'    => 'User Enabled 2FA',
		'user_2fa_disabled'   => 'User Disabled 2FA',
		'user_approved'       => 'User Approved',
		'user_disabled'       => 'User Disabled',
	);

	/**
	 * Get available webhook events.
	 *
	 * @return array<string, string> Event types and labels.
	 */
	public static function get_available_events(): array {
		return self::$available_events;
	}

	/**
	 * Get all webhooks.
	 *
	 * @param bool $enabled_only Only return enabled webhooks.
	 * @return array<int, object> Array of webhook objects.
	 */
	public static function get_all( bool $enabled_only = false ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhooks';

		if ( $enabled_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE enabled = 1 ORDER BY name ASC',
					$table
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY name ASC',
					$table
				)
			);
		}

		/* Decode events JSON and decrypt secrets for each webhook */
		foreach ( $results as $webhook ) {
			$decoded_events  = json_decode( $webhook->events, true );
			$webhook->events = $decoded_events ? $decoded_events : array();
			/* Decrypt the webhook secret */
			if ( ! empty( $webhook->secret ) ) {
				$decrypted       = NBUF_Encryption::decrypt( $webhook->secret );
				$webhook->secret = ( false === $decrypted ) ? '' : $decrypted;
			}
		}

		return $results;
	}

	/**
	 * Get a single webhook by ID.
	 *
	 * Decrypts the secret before returning.
	 *
	 * @param int $id Webhook ID.
	 * @return object|null Webhook object or null.
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhooks';

		$webhook = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			)
		);

		if ( $webhook ) {
			$decoded_events  = json_decode( $webhook->events, true );
			$webhook->events = $decoded_events ? $decoded_events : array();
			/* Decrypt the webhook secret */
			if ( ! empty( $webhook->secret ) ) {
				$decrypted       = NBUF_Encryption::decrypt( $webhook->secret );
				$webhook->secret = ( false === $decrypted ) ? '' : $decrypted;
			}
		}

		return $webhook;
	}

	/**
	 * Create a new webhook.
	 *
	 * Encrypts the secret before storing.
	 *
	 * @param array<string, mixed> $data Webhook data (name, url, secret, events, enabled).
	 * @return int|false Webhook ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhooks';

		/* Use wp_unslash instead of sanitize_text_field for secrets — STF strips special chars that are valid in HMAC secrets */
		$secret = isset( $data['secret'] ) ? wp_unslash( $data['secret'] ) : '';
		if ( strlen( $secret ) > 256 ) {
			return false;
		}

		/*
		 * SECURITY: enforce a minimum HMAC secret length. Previously any
		 * non-empty string was accepted, including 4-byte values that are
		 * trivially brute-forceable offline once an HMAC and matching
		 * payload are recovered from log dumps. 16 bytes is the published
		 * RFC 2104 floor and matches WordPress's own auth-key minimum.
		 */
		if ( '' !== $secret && strlen( $secret ) < 16 ) {
			return false;
		}

		$encrypted_secret = '';
		if ( ! empty( $secret ) ) {
			$encrypted_secret = NBUF_Encryption::encrypt( $secret );
			if ( false === $encrypted_secret ) {
				return false;
			}
		}

		$insert_data = array(
			'name'    => sanitize_text_field( $data['name'] ?? '' ),
			'url'     => esc_url_raw( $data['url'] ?? '' ),
			'secret'  => $encrypted_secret,
			'events'  => wp_json_encode( $data['events'] ?? array() ),
			'enabled' => ! empty( $data['enabled'] ) ? 1 : 0,
		);

		$result = $wpdb->insert( $table, $insert_data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing webhook.
	 *
	 * Encrypts the secret before storing.
	 *
	 * @param int                  $id   Webhook ID.
	 * @param array<string, mixed> $data Webhook data to update.
	 * @return bool True on success.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhooks';

		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['url'] ) ) {
			$update_data['url'] = esc_url_raw( $data['url'] );
		}
		if ( isset( $data['secret'] ) ) {
			$secret = wp_unslash( $data['secret'] );
			if ( strlen( $secret ) > 256 ) {
				return false;
			}
			if ( ! empty( $secret ) ) {
				$encrypted = NBUF_Encryption::encrypt( $secret );
				if ( false === $encrypted ) {
					return false;
				}
				$update_data['secret'] = $encrypted;
			} else {
				$update_data['secret'] = '';
			}
		}
		if ( isset( $data['events'] ) ) {
			$update_data['events'] = wp_json_encode( $data['events'] );
		}
		if ( isset( $data['enabled'] ) ) {
			$update_data['enabled'] = ! empty( $data['enabled'] ) ? 1 : 0;
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return (bool) $wpdb->update( $table, $update_data, array( 'id' => $id ) );
	}

	/**
	 * Delete a webhook.
	 *
	 * @param int $id Webhook ID.
	 * @return bool True on success.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table     = $wpdb->prefix . 'nbuf_webhooks';
		$log_table = $wpdb->prefix . 'nbuf_webhook_log';

		/* Delete associated log entries first */
		$wpdb->delete( $log_table, array( 'webhook_id' => $id ), array( '%d' ) );

		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Trigger webhooks for a specific event.
	 *
	 * @param string               $event   Event type (e.g., 'user_registered').
	 * @param array<string, mixed> $payload Data to send with the webhook.
	 * @return void
	 */
	public static function trigger( string $event, array $payload ): void {
		/* Check if webhooks are enabled globally */
		if ( ! NBUF_Options::get( 'nbuf_webhooks_enabled', false ) ) {
			return;
		}

		/* Get all enabled webhooks */
		$webhooks = self::get_all( true );

		foreach ( $webhooks as $webhook ) {
			/* Skip if this webhook doesn't listen for this event */
			if ( ! in_array( $event, $webhook->events, true ) ) {
				continue;
			}

			/*
			 * SECURITY: store the payload in a short-lived transient and
			 * pass only an opaque token to wp_schedule_single_event.
			 * The cron table is plaintext-readable in wp_options on
			 * shared hosting / backups; embedding user PII payloads
					 * inline meant any backup leak exposed event data.
			 * The transient is keyed by a 32-byte random token, expires
			 * in 1 hour (cron should fire well before then), and is
			 * deleted as soon as the cron handler reads it.
			 */
			$payload_token = bin2hex( random_bytes( 16 ) );
			set_transient(
				'nbuf_wh_payload_' . $payload_token,
				array(
					'event'   => $event,
					'payload' => $payload,
				),
				HOUR_IN_SECONDS
			);

			wp_schedule_single_event( time(), 'nbuf_deliver_webhook', array( (int) $webhook->id, $payload_token ) );
		}

		/* Spawn the cron runner so delivery happens promptly */
		spawn_cron();
	}

	/**
	 * WP cron callback for async webhook delivery.
	 *
	 * Reads the (event, payload) pair from a transient keyed by the
	 * supplied token rather than from cron args; see trigger() for the
	 * rationale. Falls back to the legacy 3-arg form for compatibility
	 * with events scheduled before the v1.6.6 upgrade.
	 *
	 * @param int          $webhook_id  Webhook ID.
	 * @param string|array $arg2        Payload token (new) OR legacy event name.
	 * @param array|null   $legacy_arg3 Legacy payload array (only present in pre-1.6.6 events).
	 * @return void
	 */
	public static function deliver_scheduled( int $webhook_id, $arg2 = '', $legacy_arg3 = null ): void {
		$webhook = self::get( $webhook_id );
		if ( ! $webhook || empty( $webhook->enabled ) ) {
			return;
		}

		if ( is_array( $legacy_arg3 ) ) {
			/* Legacy path: (id, event, payload). */
			self::deliver( $webhook, (string) $arg2, $legacy_arg3 );
			return;
		}

		$payload_token = is_string( $arg2 ) ? $arg2 : '';
		if ( '' === $payload_token ) {
			return;
		}

		$envelope = get_transient( 'nbuf_wh_payload_' . $payload_token );
		delete_transient( 'nbuf_wh_payload_' . $payload_token );

		if ( ! is_array( $envelope ) || empty( $envelope['event'] ) || ! isset( $envelope['payload'] ) ) {
			return;
		}

		self::deliver( $webhook, (string) $envelope['event'], (array) $envelope['payload'] );
	}

	/**
	 * Deliver a webhook synchronously.
	 *
	 * @param object               $webhook Webhook object.
	 * @param string               $event   Event type.
	 * @param array<string, mixed> $payload Event payload.
	 * @param bool                 $is_test True for the operator-triggered "test webhook" path; suppresses failure-count accounting so a temporarily-down test target cannot exhaust the auto-disable threshold.
	 * @return bool True on success (2xx response).
	 */
	public static function deliver( object $webhook, string $event, array $payload, bool $is_test = false ): bool {
		global $wpdb;
		$table     = $wpdb->prefix . 'nbuf_webhooks';
		$log_table = $wpdb->prefix . 'nbuf_webhook_log';

		/*
		 * Build the full payload. `delivery_id` is a unique per-attempt
		 * 16-byte token — receivers can enforce one-time delivery by
		 * recording delivery_ids they've processed, which is the standard
		 * defense against replay of a captured signed body.
		 */
		$full_payload = array(
			'event'       => $event,
			'delivery_id' => bin2hex( random_bytes( 16 ) ),
			'timestamp'   => gmdate( 'c' ),
			'webhook_id'  => $webhook->id,
			'site_url'    => home_url(),
			'data'        => $payload,
		);

		$json_payload = wp_json_encode( $full_payload );

		/* Build headers */
		$headers = array(
			'Content-Type'    => 'application/json',
			'User-Agent'      => 'NoBloat-User-Foundry-Webhook/1.0',
			'X-Webhook-Event' => $event,
		);

		/* Add HMAC signature if secret is set */
		if ( ! empty( $webhook->secret ) ) {
			$signature                      = hash_hmac( 'sha256', $json_payload, $webhook->secret );
			$headers['X-Webhook-Signature'] = 'sha256=' . $signature;
		}

		/* SSRF check: resolve host once and pin the IP for the actual request. */
		$pinned_ip = self::resolve_safe_ip( $webhook->url );
		if ( false === $pinned_ip ) {
			/* Log and abort - do not reveal internal network topology in response */
			$wpdb->insert(
				$log_table,
				array(
					'webhook_id'    => $webhook->id,
					'event_type'    => $event,
					'payload'       => $json_payload,
					'response_code' => 0,
					'response_body' => 'Blocked: URL resolves to a private/reserved IP address.',
					'duration_ms'   => 0,
				)
			);
			return false;
		}

		/*
		 * Pin the resolved IP in curl via CURLOPT_RESOLVE so the actual HTTP
		 * request does NOT re-resolve DNS at connect time. Without this,
		 * a hostile authoritative DNS serving TTL=0 AAAA can pass the
		 * resolve_safe_ip check on the first lookup, then swap to ::1 or
		 * an internal ULA on the second curl-time lookup — bypassing the
		 * SSRF gate entirely.
		 */
		$parsed_url      = wp_parse_url( $webhook->url );
		$pin_host        = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';
		$pin_port        = isset( $parsed_url['port'] ) ? (int) $parsed_url['port'] : ( ( isset( $parsed_url['scheme'] ) && 'https' === strtolower( $parsed_url['scheme'] ) ) ? 443 : 80 );
		$curl_resolve_cb = static function ( $handle ) use ( $pin_host, $pin_port, $pinned_ip ) {
			if ( $pin_host && $pinned_ip ) {
				curl_setopt( $handle, CURLOPT_RESOLVE, array( $pin_host . ':' . $pin_port . ':' . $pinned_ip ) );
			}
		};
		add_action( 'http_api_curl', $curl_resolve_cb, 10, 1 );

		/* Track timing */
		$start_time = microtime( true );

		/* Send the webhook — wp_safe_remote_post validates the URL at connect time, preventing DNS rebinding */
		$response = wp_safe_remote_post(
			$webhook->url,
			array(
				'headers'     => $headers,
				'body'        => $json_payload,
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
			)
		);

		remove_action( 'http_api_curl', $curl_resolve_cb, 10 );

		$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		/* Parse response */
		$response_code = 0;
		$response_body = '';
		$success       = false;

		if ( is_wp_error( $response ) ) {
			$response_body = $response->get_error_message();
		} else {
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$success       = $response_code >= 200 && $response_code < 300;
		}

		/* Truncate response body for storage */
		if ( strlen( $response_body ) > 1000 ) {
			$response_body = substr( $response_body, 0, 1000 ) . '... (truncated)';
		}

		/* Log the delivery attempt */
		$wpdb->insert(
			$log_table,
			array(
				'webhook_id'    => $webhook->id,
				'event_type'    => $event,
				'payload'       => $json_payload,
				'response_code' => $response_code,
				'response_body' => $response_body,
				'duration_ms'   => $duration_ms,
			)
		);

		/*
		 * Update webhook status — but skip the failure-count accounting on
		 * test deliveries. Otherwise an admin debugging a temporarily-down
		 * endpoint exhausts the 10-failure auto-disable threshold (5/min
		 * test rate-limit × 2 minutes = 10 fails) and silently takes the
		 * webhook offline. Real events resume normal accounting.
		 */
		if ( ! $is_test ) {
			$update_data = array(
				'last_triggered' => current_time( 'mysql', true ),
				'last_status'    => $response_code,
			);

			if ( $success ) {
				$update_data['failure_count'] = 0;
			} else {
				$update_data['failure_count'] = $webhook->failure_count + 1;

				/* Auto-disable after 10 consecutive failures */
				if ( $update_data['failure_count'] >= 10 ) {
					$update_data['enabled'] = 0;
				}
			}

			$wpdb->update( $table, $update_data, array( 'id' => $webhook->id ) );
		}

		return $success;
	}

	/**
	 * Get recent delivery logs for a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $limit      Number of logs to retrieve.
	 * @return array<int, object> Array of log entries.
	 */
	public static function get_logs( int $webhook_id, int $limit = 20 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhook_log';

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE webhook_id = %d ORDER BY created_at DESC LIMIT %d',
				$table,
				$webhook_id,
				$limit
			)
		);
	}

	/**
	 * Clean up old webhook logs.
	 *
	 * @param int $days Number of days to keep logs.
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_logs( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_webhook_log';

		/*
		 * Batch the DELETE so a backlog of millions of stale rows does not
		 * hold row locks on the entire log table — concurrent webhook
		 * deliveries try to INSERT during cleanup and would block on a
		 * single unbounded DELETE, dropping log entries when they timeout.
		 * Cap one cron run at 1M rows so we never burn the cron worker
		 * indefinitely on a runaway table.
		 */
		$total     = 0;
		$batch_cap = 1000;
		$run_cap   = 1000000;
		do {
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT %d',
					$table,
					$days,
					$batch_cap
				)
			);
			$total  += $deleted;
			if ( $deleted < $batch_cap ) {
				break;
			}
		} while ( $total < $run_cap );

		return $total;
	}

	/**
	 * Test a webhook by sending a test payload.
	 *
	 * @param int $id Webhook ID.
	 * @return array<string, mixed> Result with 'success', 'code', and 'message'.
	 */
	public static function test( int $id ): array {
		$webhook = self::get( $id );

		if ( ! $webhook ) {
			return array(
				'success' => false,
				'code'    => 0,
				'message' => 'Webhook not found.',
			);
		}

		$test_payload = array(
			'user_id'    => 0,
			'user_email' => 'test@example.com',
			'user_login' => 'test_user',
			'test'       => true,
		);

		/* Temporarily store original enabled status */
		$original_enabled = $webhook->enabled;
		$webhook->enabled = 1;

		$success = self::deliver( $webhook, 'test', $test_payload, true );

		/* Get the last log entry */
		$logs = self::get_logs( $id, 1 );
		$log  = ! empty( $logs ) ? $logs[0] : null;

		return array(
			'success' => $success,
			'code'    => $log ? $log->response_code : 0,
			'message' => $success ? 'Webhook delivered successfully.' : ( $log ? $log->response_body : 'Delivery failed.' ),
		);
	}

	/**
	 * Initialize webhook event hooks.
	 *
	 * Hooks into WordPress and plugin events to trigger webhooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		/* Cron callback for async webhook delivery */
		add_action( 'nbuf_deliver_webhook', array( __CLASS__, 'deliver_scheduled' ), 10, 3 );

		/* User registration */
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 100 );

		/* User verification - note: hook passes (email, user_id) */
		add_action( 'nbuf_user_verified', array( __CLASS__, 'on_user_verified' ), 10, 2 );

		/* User login/logout */
		add_action( 'wp_login', array( __CLASS__, 'on_user_login' ), 100, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'on_user_logout' ), 100, 1 );

		/* Profile updates */
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 100, 2 );

		/* Password reset */
		add_action( 'after_password_reset', array( __CLASS__, 'on_password_reset' ), 100, 2 );

		/* 2FA events */
		add_action( 'nbuf_2fa_enabled', array( __CLASS__, 'on_2fa_enabled' ), 10, 2 );
		add_action( 'nbuf_2fa_disabled', array( __CLASS__, 'on_2fa_disabled' ), 10, 1 );

		/* User approval/disable */
		add_action( 'nbuf_user_approved', array( __CLASS__, 'on_user_approved' ), 10, 1 );
		add_action( 'nbuf_user_disabled', array( __CLASS__, 'on_user_disabled' ), 10, 2 );
	}

	/**
	 * Handle user registration event.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function on_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_registered',
			array(
				'user_id'      => $user_id,
				'user_email'   => $user->user_email,
				'user_login'   => $user->user_login,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
			)
		);
	}

	/**
	 * Handle user verification event.
	 *
	 * @param string $email   User email.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	public static function on_user_verified( string $email, int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_verified',
			array(
				'user_id'    => $user_id,
				'user_email' => $email,
				'user_login' => $user->user_login,
			)
		);
	}

	/**
	 * Handle user login event.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 * @return void
	 */
	public static function on_user_login( string $user_login, WP_User $user ): void {
		self::trigger(
			'user_login',
			array(
				'user_id'    => $user->ID,
				'user_email' => $user->user_email,
				'user_login' => $user_login,
			)
		);
	}

	/**
	 * Handle user logout event.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function on_user_logout( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_logout',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
			)
		);
	}

	/**
	 * Handle profile update event.
	 *
	 * @param int     $user_id       User ID.
	 * @param WP_User $old_user_data Old user data.
	 * @return void
	 */
	public static function on_profile_update( int $user_id, WP_User $old_user_data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Detect what changed */
		$changes = array();
		if ( $user->user_email !== $old_user_data->user_email ) {
			$changes['email'] = array(
				'old' => $old_user_data->user_email,
				'new' => $user->user_email,
			);
		}
		if ( $user->display_name !== $old_user_data->display_name ) {
			$changes['display_name'] = array(
				'old' => $old_user_data->display_name,
				'new' => $user->display_name,
			);
		}

		self::trigger(
			'user_profile_update',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
				'changes'    => $changes,
			)
		);
	}

	/**
	 * Handle password reset event.
	 *
	 * @param WP_User $user     User object.
	 * @param string  $new_pass New password (not sent in webhook).
	 * @return void
	 */
	public static function on_password_reset( WP_User $user, string $new_pass ): void {
		self::trigger(
			'user_password_reset',
			array(
				'user_id'    => $user->ID,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
			)
		);
	}

	/**
	 * Handle 2FA enabled event.
	 *
	 * @param int    $user_id User ID.
	 * @param string $method  2FA method (totp, email).
	 * @return void
	 */
	public static function on_2fa_enabled( int $user_id, string $method ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_2fa_enabled',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
				'method'     => $method,
			)
		);
	}

	/**
	 * Handle 2FA disabled event.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function on_2fa_disabled( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_2fa_disabled',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
			)
		);
	}

	/**
	 * Handle user approved event.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function on_user_approved( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_approved',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
			)
		);
	}

	/**
	 * Handle user disabled event.
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  Disable reason.
	 * @return void
	 */
	public static function on_user_disabled( int $user_id, string $reason = '' ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::trigger(
			'user_disabled',
			array(
				'user_id'    => $user_id,
				'user_email' => $user->user_email,
				'user_login' => $user->user_login,
				'reason'     => $reason,
			)
		);
	}

	/**
	 * Check whether a webhook URL is safe to request (not SSRF).
	 *
	 * Resolves the hostname and rejects loopback, private, link-local,
	 * multicast, and cloud metadata IP ranges.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL is safe to request.
	 */
	public static function is_url_safe( string $url ): bool {
		return false !== self::resolve_safe_ip( $url );
	}

	/**
	 * Resolve a webhook URL to a single safe IP address, or false if unsafe.
	 *
	 * Returned IP must be pinned via CURLOPT_RESOLVE so the actual HTTP
	 * request bypasses curl's own DNS resolution and cannot be subverted
	 * by a TTL=0 AAAA-rebinding attack on the second resolution.
	 *
	 * @since 1.7.2
	 * @param string $url Webhook URL.
	 * @return string|false Resolved IP address or false on failure.
	 */
	public static function resolve_safe_ip( string $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) ) {
			return false;
		}

		/* Reject non-http(s) schemes — defense-in-depth (wp_safe_remote_post enforces this too). */
		$scheme = strtolower( $parsed['scheme'] ?? '' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = $parsed['host'];

		/*
		 * SECURITY: resolve BOTH A and AAAA records. gethostbynamel() only
		 * returns IPv4 — a dual-stack target host that has a public A but
		 * a private AAAA (or ::1, fc00::/7, fe80::/10, IPv4-mapped private
		 * range like ::ffff:10.0.0.1) would pass the IPv4 inspection while
		 * curl resolves to AAAA at request time. dns_get_record returns
		 * both families.
		 */
		$ipv4_records = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_A ) : array();
		$ipv6_records = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_AAAA ) : array();
		$ips          = array();
		if ( is_array( $ipv4_records ) ) {
			foreach ( $ipv4_records as $r ) {
				if ( isset( $r['ip'] ) ) {
					$ips[] = $r['ip'];
				}
			}
		}
		if ( is_array( $ipv6_records ) ) {
			foreach ( $ipv6_records as $r ) {
				if ( isset( $r['ipv6'] ) ) {
					$ips[] = $r['ipv6'];
				}
			}
		}

		/* Fallback for hosts without dns_get_record support. */
		if ( empty( $ips ) ) {
			$legacy = gethostbynamel( $host );
			if ( false === $legacy || empty( $legacy ) ) {
				return false;
			}
			$ips = $legacy;
		}

		$first_safe_ip = false;
		foreach ( $ips as $ip ) {
			if ( ! filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			) ) {
				return false;
			}

			/* Block AWS/GCP/Azure metadata endpoints (169.254.169.254). */
			if ( str_starts_with( $ip, '169.254.' ) ) {
				return false;
			}

			/*
			 * Block IPv6 link-local, loopback, unique-local, and
			 * IPv4-mapped private ranges in case FILTER_FLAG_NO_PRIV_RANGE
			 * misses anything (PHP filter coverage of IPv6 ranges varies).
			 */
			$ip_lc = strtolower( $ip );
			if ( '::1' === $ip_lc || str_starts_with( $ip_lc, 'fe80:' ) || str_starts_with( $ip_lc, 'fc' ) || str_starts_with( $ip_lc, 'fd' ) ) {
				return false;
			}
			if ( str_starts_with( $ip_lc, '::ffff:' ) ) {
				$mapped = substr( $ip_lc, 7 );
				if ( ! filter_var(
					$mapped,
					FILTER_VALIDATE_IP,
					FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				) ) {
					return false;
				}
				if ( str_starts_with( $mapped, '169.254.' ) ) {
					return false;
				}
			}

			if ( false === $first_safe_ip ) {
				$first_safe_ip = $ip;
			}
		}

		return $first_safe_ip;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
