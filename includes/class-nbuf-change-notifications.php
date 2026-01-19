<?php
/**
 * Profile Change Notifications Class
 *
 * Monitors and notifies admins of user profile changes
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change Notifications Management Class.
 *
 * Monitors and notifies admins of user profile changes.
 *
 * @since 1.3.0
 */
class NBUF_Change_Notifications {


	/**
	 * Original user data (before changes).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $original_data = array();

	/**
	 * Pending changes (for digest mode).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $pending_changes = array();

	/**
	 * Pending frontend updates (skip profile_update hook).
	 *
	 * @var array<int, bool>
	 */
	private $pending_frontend_updates = array();

	/**
	 * Register digest cron handlers (static, called unconditionally).
	 *
	 * Must be called even when notifications are disabled so that
	 * any pending digests can still be sent.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function register_cron_handlers(): void {
		add_action( 'nbuf_send_change_digest_hourly', array( __CLASS__, 'static_send_hourly_digest' ) );
		add_action( 'nbuf_send_change_digest_daily', array( __CLASS__, 'static_send_daily_digest' ) );
	}

	/**
	 * Static wrapper for hourly digest (called by cron).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function static_send_hourly_digest(): void {
		$instance = new self();
		$instance->send_hourly_digest();
	}

	/**
	 * Static wrapper for daily digest (called by cron).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function static_send_daily_digest(): void {
		$instance = new self();
		$instance->send_daily_digest();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );

		if ( ! $enabled ) {
			/* Unschedule digest events if notifications disabled */
			self::unschedule_digests();
			return;
		}

		/* Hook into profile updates */
		add_action( 'profile_update', array( $this, 'track_profile_update' ), 10, 2 );
		add_action( 'nbuf_after_profile_update', array( $this, 'track_frontend_profile_update' ), 10, 1 );

		/* Hook into user meta updates */
		add_action( 'update_user_meta', array( $this, 'track_meta_update' ), 10, 4 );

		/* Store original data before updates (admin + frontend) */
		add_action( 'personal_options_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'edit_user_profile_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'nbuf_before_profile_update', array( $this, 'store_original_data' ), 1 );

		/* Schedule digest cron events based on setting */
		$this->schedule_digest_cron();
	}

	/**
	 * Schedule digest cron events based on notification timing setting
	 *
	 * @return void
	 */
	private function schedule_digest_cron(): void {
		$digest_mode = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

		$hourly_scheduled = wp_next_scheduled( 'nbuf_send_change_digest_hourly' );
		$daily_scheduled  = wp_next_scheduled( 'nbuf_send_change_digest_daily' );

		if ( 'hourly' === $digest_mode ) {
			/* Schedule hourly, unschedule daily */
			if ( ! $hourly_scheduled ) {
				wp_schedule_event( time(), 'hourly', 'nbuf_send_change_digest_hourly' );
			}
			if ( $daily_scheduled ) {
				wp_unschedule_event( $daily_scheduled, 'nbuf_send_change_digest_daily' );
			}
		} elseif ( 'daily' === $digest_mode ) {
			/* Schedule daily, unschedule hourly */
			if ( ! $daily_scheduled ) {
				wp_schedule_event( time(), 'daily', 'nbuf_send_change_digest_daily' );
			}
			if ( $hourly_scheduled ) {
				wp_unschedule_event( $hourly_scheduled, 'nbuf_send_change_digest_hourly' );
			}
		} else {
			/* Immediate mode - unschedule both */
			self::unschedule_digests();
		}
	}

	/**
	 * Unschedule all digest cron events
	 *
	 * @return void
	 */
	public static function unschedule_digests(): void {
		$hourly_scheduled = wp_next_scheduled( 'nbuf_send_change_digest_hourly' );
		$daily_scheduled  = wp_next_scheduled( 'nbuf_send_change_digest_daily' );

		if ( $hourly_scheduled ) {
			wp_unschedule_event( $hourly_scheduled, 'nbuf_send_change_digest_hourly' );
		}
		if ( $daily_scheduled ) {
			wp_unschedule_event( $daily_scheduled, 'nbuf_send_change_digest_daily' );
		}
	}

	/**
	 * Store original user data before update
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function store_original_data( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Mark frontend updates to skip profile_update hook */
		if ( doing_action( 'nbuf_before_profile_update' ) ) {
			$this->pending_frontend_updates[ $user_id ] = true;
		}

		/* Don't overwrite if already captured */
		if ( isset( $this->original_data[ $user_id ] ) ) {
			return;
		}

		$this->original_data[ $user_id ] = array(
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'description'  => $user->description,
		);

		/* Store profile data. */
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom profile table.
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			),
			ARRAY_A
		);

		if ( $profile ) {
			unset( $profile['user_id'] );
			$this->original_data[ $user_id ]['profile'] = $profile;
		}
	}

	/**
	 * Track profile update
	 *
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Old user object.
	 * @return void
	 */
	public function track_profile_update( int $user_id, object $old_user_data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $old_user_data required by WordPress profile_update action signature
		/* Skip if this is a frontend update - nbuf_after_profile_update will handle it */
		if ( isset( $this->pending_frontend_updates[ $user_id ] ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$changes  = array();
		$original = isset( $this->original_data[ $user_id ] ) ? $this->original_data[ $user_id ] : array();

		/* Check monitored fields */
		$monitored_fields = $this->get_monitored_fields();

		/* Check core fields */
		if ( in_array( 'user_email', $monitored_fields, true ) && isset( $original['user_email'] ) ) {
			if ( $original['user_email'] !== $user->user_email ) {
				$changes['user_email'] = array(
					'old' => $original['user_email'],
					'new' => $user->user_email,
				);
			}
		}

		if ( in_array( 'display_name', $monitored_fields, true ) && isset( $original['display_name'] ) ) {
			if ( $original['display_name'] !== $user->display_name ) {
				$changes['display_name'] = array(
					'old' => $original['display_name'],
					'new' => $user->display_name,
				);
			}
		}

		if ( in_array( 'first_name', $monitored_fields, true ) && isset( $original['first_name'] ) ) {
			$new_first_name = get_user_meta( $user_id, 'first_name', true );
			if ( $original['first_name'] !== $new_first_name ) {
				$changes['first_name'] = array(
					'old' => $original['first_name'],
					'new' => $new_first_name,
				);
			}
		}

		if ( in_array( 'last_name', $monitored_fields, true ) && isset( $original['last_name'] ) ) {
			$new_last_name = get_user_meta( $user_id, 'last_name', true );
			if ( $original['last_name'] !== $new_last_name ) {
				$changes['last_name'] = array(
					'old' => $original['last_name'],
					'new' => $new_last_name,
				);
			}
		}

		if ( in_array( 'description', $monitored_fields, true ) && isset( $original['description'] ) ) {
			if ( $original['description'] !== $user->description ) {
				$changes['description'] = array(
					'old' => $original['description'],
					'new' => $user->description,
				);
			}
		}

		/* Check profile fields. */
		if ( isset( $original['profile'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'nbuf_user_profile';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom profile table.
			$new_profile = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE user_id = %d',
					$table_name,
					$user_id
				),
				ARRAY_A
			);

			if ( $new_profile ) {
				unset( $new_profile['user_id'] );

				foreach ( $original['profile'] as $field => $old_value ) {
					if ( in_array( $field, $monitored_fields, true ) ) {
						$new_value = $new_profile[ $field ] ?? '';
						if ( $old_value !== $new_value ) {
							$changes[ $field ] = array(
								'old' => $old_value,
								'new' => $new_value,
							);
						}
					}
				}
			}
		}

		/* Send notification if there are changes */
		if ( ! empty( $changes ) ) {
			$this->queue_notification( $user_id, $changes );
		}
	}

	/**
	 * Track frontend profile update
	 *
	 * Called via nbuf_after_profile_update hook after NBUF custom tables are updated.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function track_frontend_profile_update( int $user_id ): void {
		/* Clear the pending flag */
		if ( isset( $this->pending_frontend_updates[ $user_id ] ) ) {
			unset( $this->pending_frontend_updates[ $user_id ] );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$changes  = array();
		$original = isset( $this->original_data[ $user_id ] ) ? $this->original_data[ $user_id ] : array();

		/* Check monitored fields */
		$monitored_fields = $this->get_monitored_fields();

		/* Check core fields */
		if ( in_array( 'user_email', $monitored_fields, true ) && isset( $original['user_email'] ) ) {
			if ( $original['user_email'] !== $user->user_email ) {
				$changes['user_email'] = array(
					'old' => $original['user_email'],
					'new' => $user->user_email,
				);
			}
		}

		if ( in_array( 'display_name', $monitored_fields, true ) && isset( $original['display_name'] ) ) {
			if ( $original['display_name'] !== $user->display_name ) {
				$changes['display_name'] = array(
					'old' => $original['display_name'],
					'new' => $user->display_name,
				);
			}
		}

		if ( in_array( 'first_name', $monitored_fields, true ) && isset( $original['first_name'] ) ) {
			$new_first_name = get_user_meta( $user_id, 'first_name', true );
			if ( $original['first_name'] !== $new_first_name ) {
				$changes['first_name'] = array(
					'old' => $original['first_name'],
					'new' => $new_first_name,
				);
			}
		}

		if ( in_array( 'last_name', $monitored_fields, true ) && isset( $original['last_name'] ) ) {
			$new_last_name = get_user_meta( $user_id, 'last_name', true );
			if ( $original['last_name'] !== $new_last_name ) {
				$changes['last_name'] = array(
					'old' => $original['last_name'],
					'new' => $new_last_name,
				);
			}
		}

		if ( in_array( 'description', $monitored_fields, true ) && isset( $original['description'] ) ) {
			if ( $original['description'] !== $user->description ) {
				$changes['description'] = array(
					'old' => $original['description'],
					'new' => $user->description,
				);
			}
		}

		/* Check profile fields (NBUF custom tables - now updated) */
		if ( isset( $original['profile'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'nbuf_user_profile';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom profile table.
			$new_profile = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE user_id = %d',
					$table_name,
					$user_id
				),
				ARRAY_A
			);

			if ( $new_profile ) {
				unset( $new_profile['user_id'] );

				foreach ( $original['profile'] as $field => $old_value ) {
					if ( in_array( $field, $monitored_fields, true ) ) {
						$new_value = $new_profile[ $field ] ?? '';
						if ( $old_value !== $new_value ) {
							$changes[ $field ] = array(
								'old' => $old_value,
								'new' => $new_value,
							);
						}
					}
				}
			}
		}

		/* Send notification if there are changes */
		if ( ! empty( $changes ) ) {
			$this->queue_notification( $user_id, $changes );
		}

		/* Clean up original data */
		if ( isset( $this->original_data[ $user_id ] ) ) {
			unset( $this->original_data[ $user_id ] );
		}
	}

	/**
	 * Track user meta updates (for specific fields)
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function track_meta_update( int $meta_id, int $user_id, string $meta_key, $meta_value ): void {
		/* Track 2FA changes. */
		if ( 'nbuf_2fa_enabled' === $meta_key ) {
			$old_value = get_user_meta( $user_id, 'nbuf_2fa_enabled', true );
			$status    = $meta_value ? 'enabled' : 'disabled';

			$changes = array(
				'2fa_status' => array(
					'old' => $old_value ? 'enabled' : 'disabled',
					'new' => $status,
				),
			);

			$this->queue_notification( $user_id, $changes );
		}

		/* Track privacy changes. */
		if ( 'nbuf_profile_privacy' === $meta_key ) {
			$old_value = get_user_meta( $user_id, 'nbuf_profile_privacy', true );

			$changes = array(
				'profile_privacy' => array(
					'old' => $old_value,
					'new' => $meta_value,
				),
			);

			$this->queue_notification( $user_id, $changes );
		}
	}

	/**
	 * Queue notification for sending
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $changes Array of changes.
	 * @return void
	 */
	private function queue_notification( int $user_id, array $changes ): void {
		$digest_mode = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

		if ( 'immediate' === $digest_mode ) {
			/* Send immediately */
			$this->send_notification( $user_id, $changes );
		} else {
			/* Queue for digest - store directly in transient to persist across requests */
			$transient_key = 'nbuf_pending_changes_' . $digest_mode;
			$existing      = get_transient( $transient_key );

			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			/* Initialize user's change array if not exists */
			if ( ! isset( $existing[ $user_id ] ) || ! is_array( $existing[ $user_id ] ) ) {
				$existing[ $user_id ] = array();
			}

			/* Append new change (don't overwrite previous changes) */
			$existing[ $user_id ][] = array(
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Intentionally using timestamp for date comparison
				'timestamp' => current_time( 'timestamp' ),
				'changes'   => $changes,
			);

			set_transient( $transient_key, $existing, DAY_IN_SECONDS );

			/* Also update static property for current request consistency */
			self::$pending_changes = $existing;
		}
	}

	/**
	 * Send notification email
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $changes Array of changes.
	 * @return void
	 */
	private function send_notification( int $user_id, array $changes ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Get notification recipients */
		$recipients = $this->get_notification_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		/* Build email */
		$subject = sprintf( 'Profile Changes for %s', $user->display_name );
		$message = $this->build_notification_email( $user, $changes );

		/* Send email */
		foreach ( $recipients as $recipient ) {
			NBUF_Email::send( $recipient, $subject, $message );
		}
	}

	/**
	 * Build notification email content
	 *
	 * @param  WP_User              $user    User object.
	 * @param  array<string, mixed> $changes Array of changes.
	 * @return string Email message.
	 */
	private function build_notification_email( WP_User $user, array $changes ): string {
		$message  = sprintf( "Profile changes detected for user: %s (%s)\n\n", $user->display_name, $user->user_login );
		$message .= sprintf( "User ID: %d\n", $user->ID );
		$message .= sprintf( "Email: %s\n", $user->user_email );
		$message .= sprintf( "Date: %s\n\n", current_time( 'mysql', true ) );
		$message .= "Changes:\n";
		$message .= str_repeat( '-', 50 ) . "\n\n";

		foreach ( $changes as $field => $change ) {
			$field_label = $this->get_field_label( $field );

			if ( 'new_user' === $field ) {
				$message .= $change['new'] . "\n\n";
			} else {
				$message .= sprintf( "%s:\n", $field_label );
				$message .= sprintf( "  Old: %s\n", $this->format_value( $change['old'] ) );
				$message .= sprintf( "  New: %s\n\n", $this->format_value( $change['new'] ) );
			}
		}

		$message .= str_repeat( '-', 50 ) . "\n\n";
		$message .= sprintf( "View user profile: %s\n", admin_url( 'user-edit.php?user_id=' . $user->ID ) );

		return $message;
	}

	/**
	 * Get field label for display
	 *
	 * @param  string $field_key Field key.
	 * @return string Field label.
	 */
	private function get_field_label( $field_key ) {
		$labels = array(
			'user_email'      => 'Email Address',
			'display_name'    => 'Display Name',
			'first_name'      => 'First Name',
			'last_name'       => 'Last Name',
			'description'     => 'Bio',
			'2fa_status'      => '2FA Status',
			'profile_privacy' => 'Profile Privacy',
			'bio'             => 'Bio',
			'phone'           => 'Phone',
			'city'            => 'City',
			'company'         => 'Company',
		);

		return $labels[ $field_key ] ?? ucwords( str_replace( '_', ' ', $field_key ) );
	}

	/**
	 * Format value for display
	 *
	 * @param  mixed $value Value to format.
	 * @return string Formatted value.
	 */
	private function format_value( $value ) {
		if ( empty( $value ) ) {
			return '(empty)';
		}

		if ( is_array( $value ) ) {
			return implode( ', ', $value );
		}

		if ( strlen( $value ) > 100 ) {
			return substr( $value, 0, 100 ) . '...';
		}

		return $value;
	}

	/**
	 * Get monitored fields
	 *
	 * @return array<int, string> Field keys to monitor.
	 */
	private function get_monitored_fields(): array {
		$default_fields = array( 'user_email', 'display_name' );
		$fields         = NBUF_Options::get( 'nbuf_notify_profile_changes_fields', $default_fields );

		if ( ! is_array( $fields ) ) {
			$fields = $default_fields;
		}

		return $fields;
	}

	/**
	 * Get notification recipients
	 *
	 * @return array<int, string> Email addresses.
	 */
	private function get_notification_recipients(): array {
		$recipients = NBUF_Options::get( 'nbuf_notify_profile_changes_to', get_option( 'admin_email' ) );

		if ( is_string( $recipients ) ) {
			$recipients = array_map( 'trim', explode( ',', $recipients ) );
		}

		if ( ! is_array( $recipients ) ) {
			$recipients = array( get_option( 'admin_email' ) );
		}

		return array_filter( $recipients, 'is_email' );
	}

	/**
	 * Send hourly digest
	 *
	 * @return void
	 */
	public function send_hourly_digest(): void {
		$this->send_digest( 'hourly' );
	}

	/**
	 * Send daily digest
	 *
	 * @return void
	 */
	public function send_daily_digest(): void {
		$this->send_digest( 'daily' );
	}

	/**
	 * Send digest email
	 *
	 * @param string $frequency Digest frequency (hourly, daily).
	 * @return void
	 */
	private function send_digest( string $frequency ): void {
		$transient_key = 'nbuf_pending_changes_' . $frequency;
		$pending       = get_transient( $transient_key );

		if ( empty( $pending ) ) {
			return;
		}

		/* Get recipients */
		$recipients = $this->get_notification_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		/* Build digest email */
		$subject = sprintf( 'Profile Changes Digest (%s)', ucfirst( $frequency ) );
		$message = $this->build_digest_email( $pending, $frequency );

		/* Send email */
		foreach ( $recipients as $recipient ) {
			NBUF_Email::send( $recipient, $subject, $message );
		}

		/* Clear pending changes */
		delete_transient( $transient_key );
	}

	/**
	 * Build digest email content
	 *
	 * @param  array<int, array<int, array<string, mixed>>> $pending   Pending changes.
	 * @param  string                                       $frequency Digest frequency.
	 * @return string Email message.
	 */
	private function build_digest_email( array $pending, string $frequency ): string {
		$message  = sprintf( "Profile Changes Digest - %s\n", ucfirst( $frequency ) );
		$message .= sprintf( "Generated: %s\n\n", current_time( 'mysql', true ) );
		$message .= str_repeat( '=', 70 ) . "\n\n";

		$total_changes = 0;

		foreach ( $pending as $user_id => $user_changes ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$message .= sprintf( "User: %s (%s)\n", $user->display_name, $user->user_login );
			$message .= sprintf( "Email: %s\n", $user->user_email );
			$message .= sprintf( "Profile: %s\n", admin_url( 'user-edit.php?user_id=' . $user_id ) );
			$message .= str_repeat( '-', 70 ) . "\n\n";

			foreach ( $user_changes as $change_event ) {
				$timestamp = gmdate( 'Y-m-d H:i:s', $change_event['timestamp'] );
				$message  .= sprintf( "Changed at: %s\n", $timestamp );

				foreach ( $change_event['changes'] as $field => $change ) {
					$field_label = $this->get_field_label( $field );

					if ( 'new_user' === $field ) {
						$message .= $change['new'] . "\n";
					} else {
						$message .= sprintf(
							"  %s: %s → %s\n",
							$field_label,
							$this->format_value( $change['old'] ),
							$this->format_value( $change['new'] )
						);
					}
					++$total_changes;
				}

				$message .= "\n";
			}

			$message .= "\n";
		}

		$message .= str_repeat( '=', 70 ) . "\n";
		$message .= sprintf( "Total changes: %d\n", $total_changes );
		$message .= sprintf( "Total users affected: %d\n", count( $pending ) );

		return $message;
	}
}
