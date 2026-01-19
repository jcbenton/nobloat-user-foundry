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
				$webhook->secret = NBUF_Encryption::decrypt( $webhook->secret );
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
				$webhook->secret = NBUF_Encryption::decrypt( $webhook->secret );
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

		$secret = sanitize_text_field( $data['secret'] ?? '' );

		$insert_data = array(
			'name'    => sanitize_text_field( $data['name'] ?? '' ),
			'url'     => esc_url_raw( $data['url'] ?? '' ),
			'secret'  => ! empty( $secret ) ? NBUF_Encryption::encrypt( $secret ) : '',
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
			$secret                = sanitize_text_field( $data['secret'] );
			$update_data['secret'] = ! empty( $secret ) ? NBUF_Encryption::encrypt( $secret ) : '';
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

			/* Queue webhook delivery (async to not block user requests) */
			self::deliver( $webhook, $event, $payload );
		}
	}

	/**
	 * Deliver a webhook.
	 *
	 * @param object               $webhook Webhook object.
	 * @param string               $event   Event type.
	 * @param array<string, mixed> $payload Event payload.
	 * @return bool True on success (2xx response).
	 */
	public static function deliver( object $webhook, string $event, array $payload ): bool {
		global $wpdb;
		$table     = $wpdb->prefix . 'nbuf_webhooks';
		$log_table = $wpdb->prefix . 'nbuf_webhook_log';

		/* Build the full payload */
		$full_payload = array(
			'event'      => $event,
			'timestamp'  => gmdate( 'c' ),
			'webhook_id' => $webhook->id,
			'site_url'   => home_url(),
			'data'       => $payload,
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

		/* Track timing */
		$start_time = microtime( true );

		/* Send the webhook */
		$response = wp_remote_post(
			$webhook->url,
			array(
				'headers'     => $headers,
				'body'        => $json_payload,
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
			)
		);

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

		/* Update webhook status */
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

		return $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$table,
				$days
			)
		);
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

		$success = self::deliver( $webhook, 'test', $test_payload );

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
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
