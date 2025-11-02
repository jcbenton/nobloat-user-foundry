<?php
/**
 * Profile Change Notifications Class
 *
 * Monitors and notifies admins of user profile changes
 *
 * @package NoBloat_User_Foundry
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Change_Notifications {

	/**
	 * Original user data (before changes)
	 */
	private $original_data = array();

	/**
	 * Pending changes (for digest mode)
	 */
	private static $pending_changes = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$enabled = NBUF_Options::get( 'nbuf_notify_profile_changes', false );

		if ( ! $enabled ) {
			return;
		}

		/* Hook into profile updates */
		add_action( 'profile_update', array( $this, 'track_profile_update' ), 10, 2 );
		add_action( 'user_register', array( $this, 'track_user_registration' ), 10, 1 );

		/* Hook into user meta updates */
		add_action( 'update_user_meta', array( $this, 'track_meta_update' ), 10, 4 );

		/* Store original data before updates */
		add_action( 'personal_options_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'edit_user_profile_update', array( $this, 'store_original_data' ), 1 );

		/* Schedule digest emails */
		add_action( 'nbuf_send_change_digest_hourly', array( $this, 'send_hourly_digest' ) );
		add_action( 'nbuf_send_change_digest_daily', array( $this, 'send_daily_digest' ) );
	}

	/**
	 * Store original user data before update
	 *
	 * @param int $user_id User ID
	 */
	public function store_original_data( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->original_data[ $user_id ] = array(
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'description'  => $user->description,
		);

		/* Store profile data */
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';
		$profile = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d",
			$user_id
		), ARRAY_A );

		if ( $profile ) {
			unset( $profile['user_id'] );
			$this->original_data[ $user_id ]['profile'] = $profile;
		}
	}

	/**
	 * Track profile update
	 *
	 * @param int    $user_id User ID
	 * @param object $old_user_data Old user object
	 */
	public function track_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$changes = array();
		$original = isset( $this->original_data[ $user_id ] ) ? $this->original_data[ $user_id ] : array();

		/* Check monitored fields */
		$monitored_fields = $this->get_monitored_fields();

		/* Check core fields */
		if ( in_array( 'user_email', $monitored_fields ) && isset( $original['user_email'] ) ) {
			if ( $original['user_email'] !== $user->user_email ) {
				$changes['user_email'] = array(
					'old' => $original['user_email'],
					'new' => $user->user_email,
				);
			}
		}

		if ( in_array( 'display_name', $monitored_fields ) && isset( $original['display_name'] ) ) {
			if ( $original['display_name'] !== $user->display_name ) {
				$changes['display_name'] = array(
					'old' => $original['display_name'],
					'new' => $user->display_name,
				);
			}
		}

		if ( in_array( 'first_name', $monitored_fields ) && isset( $original['first_name'] ) ) {
			$new_first_name = get_user_meta( $user_id, 'first_name', true );
			if ( $original['first_name'] !== $new_first_name ) {
				$changes['first_name'] = array(
					'old' => $original['first_name'],
					'new' => $new_first_name,
				);
			}
		}

		if ( in_array( 'last_name', $monitored_fields ) && isset( $original['last_name'] ) ) {
			$new_last_name = get_user_meta( $user_id, 'last_name', true );
			if ( $original['last_name'] !== $new_last_name ) {
				$changes['last_name'] = array(
					'old' => $original['last_name'],
					'new' => $new_last_name,
				);
			}
		}

		if ( in_array( 'description', $monitored_fields ) && isset( $original['description'] ) ) {
			if ( $original['description'] !== $user->description ) {
				$changes['description'] = array(
					'old' => $original['description'],
					'new' => $user->description,
				);
			}
		}

		/* Check profile fields */
		if ( isset( $original['profile'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'nbuf_user_profile';
			$new_profile = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			), ARRAY_A );

			if ( $new_profile ) {
				unset( $new_profile['user_id'] );

				foreach ( $original['profile'] as $field => $old_value ) {
					if ( in_array( $field, $monitored_fields ) ) {
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
	 * Track user registration
	 *
	 * @param int $user_id User ID
	 */
	public function track_user_registration( $user_id ) {
		$notify_new_users = NBUF_Options::get( 'nbuf_notify_new_registrations', false );

		if ( ! $notify_new_users ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$changes = array(
			'new_user' => array(
				'old' => '',
				'new' => sprintf( 'New user registered: %s (%s)', $user->user_login, $user->user_email ),
			),
		);

		$this->queue_notification( $user_id, $changes );
	}

	/**
	 * Track user meta updates (for specific fields)
	 *
	 * @param int    $meta_id Meta ID
	 * @param int    $user_id User ID
	 * @param string $meta_key Meta key
	 * @param mixed  $meta_value Meta value
	 */
	public function track_meta_update( $meta_id, $user_id, $meta_key, $meta_value ) {
		/* Track 2FA changes */
		if ( $meta_key === 'nbuf_2fa_enabled' ) {
			$old_value = get_user_meta( $user_id, 'nbuf_2fa_enabled', true );
			$status = $meta_value ? 'enabled' : 'disabled';

			$changes = array(
				'2fa_status' => array(
					'old' => $old_value ? 'enabled' : 'disabled',
					'new' => $status,
				),
			);

			$this->queue_notification( $user_id, $changes );
		}

		/* Track privacy changes */
		if ( $meta_key === 'nbuf_profile_privacy' ) {
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
	 * @param int   $user_id User ID
	 * @param array $changes Array of changes
	 */
	private function queue_notification( $user_id, $changes ) {
		$digest_mode = NBUF_Options::get( 'nbuf_notify_profile_changes_digest', 'immediate' );

		if ( $digest_mode === 'immediate' ) {
			/* Send immediately */
			$this->send_notification( $user_id, $changes );
		} else {
			/* Queue for digest */
			if ( ! isset( self::$pending_changes[ $user_id ] ) ) {
				self::$pending_changes[ $user_id ] = array();
			}

			self::$pending_changes[ $user_id ][] = array(
				'timestamp' => current_time( 'timestamp' ),
				'changes'   => $changes,
			);

			/* Store in transient */
			$transient_key = 'nbuf_pending_changes_' . $digest_mode;
			$existing = get_transient( $transient_key );
			if ( ! $existing ) {
				$existing = array();
			}

			$existing[ $user_id ] = self::$pending_changes[ $user_id ];
			set_transient( $transient_key, $existing, DAY_IN_SECONDS );
		}
	}

	/**
	 * Send notification email
	 *
	 * @param int   $user_id User ID
	 * @param array $changes Array of changes
	 */
	private function send_notification( $user_id, $changes ) {
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
			wp_mail( $recipient, $subject, $message );
		}
	}

	/**
	 * Build notification email content
	 *
	 * @param WP_User $user User object
	 * @param array   $changes Array of changes
	 * @return string Email message
	 */
	private function build_notification_email( $user, $changes ) {
		$message = sprintf( "Profile changes detected for user: %s (%s)\n\n", $user->display_name, $user->user_login );
		$message .= sprintf( "User ID: %d\n", $user->ID );
		$message .= sprintf( "Email: %s\n", $user->user_email );
		$message .= sprintf( "Date: %s\n\n", current_time( 'mysql' ) );
		$message .= "Changes:\n";
		$message .= str_repeat( '-', 50 ) . "\n\n";

		foreach ( $changes as $field => $change ) {
			$field_label = $this->get_field_label( $field );

			if ( $field === 'new_user' ) {
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
	 * @param string $field_key Field key
	 * @return string Field label
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
	 * @param mixed $value Value to format
	 * @return string Formatted value
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
	 * @return array Field keys to monitor
	 */
	private function get_monitored_fields() {
		$default_fields = array( 'user_email', 'display_name' );
		$fields = NBUF_Options::get( 'nbuf_notify_profile_changes_fields', $default_fields );

		if ( ! is_array( $fields ) ) {
			$fields = $default_fields;
		}

		return $fields;
	}

	/**
	 * Get notification recipients
	 *
	 * @return array Email addresses
	 */
	private function get_notification_recipients() {
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
	 */
	public function send_hourly_digest() {
		$this->send_digest( 'hourly' );
	}

	/**
	 * Send daily digest
	 */
	public function send_daily_digest() {
		$this->send_digest( 'daily' );
	}

	/**
	 * Send digest email
	 *
	 * @param string $frequency Digest frequency (hourly, daily)
	 */
	private function send_digest( $frequency ) {
		$transient_key = 'nbuf_pending_changes_' . $frequency;
		$pending = get_transient( $transient_key );

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
			wp_mail( $recipient, $subject, $message );
		}

		/* Clear pending changes */
		delete_transient( $transient_key );
	}

	/**
	 * Build digest email content
	 *
	 * @param array  $pending Pending changes
	 * @param string $frequency Digest frequency
	 * @return string Email message
	 */
	private function build_digest_email( $pending, $frequency ) {
		$message = sprintf( "Profile Changes Digest - %s\n", ucfirst( $frequency ) );
		$message .= sprintf( "Generated: %s\n\n", current_time( 'mysql' ) );
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
				$timestamp = date( 'Y-m-d H:i:s', $change_event['timestamp'] );
				$message .= sprintf( "Changed at: %s\n", $timestamp );

				foreach ( $change_event['changes'] as $field => $change ) {
					$field_label = $this->get_field_label( $field );

					if ( $field === 'new_user' ) {
						$message .= $change['new'] . "\n";
					} else {
						$message .= sprintf( "  %s: %s → %s\n",
							$field_label,
							$this->format_value( $change['old'] ),
							$this->format_value( $change['new'] )
						);
					}
					$total_changes++;
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
