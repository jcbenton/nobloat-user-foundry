<?php
/**
 * Profile Version History Class
 *
 * Tracks all user profile changes with complete snapshots for timeline viewing,
 * diff comparison, and revert capability.
 *
 * @package NoBloat_User_Foundry
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for version history tracking.
 * Custom nbuf_profile_versions table stores profile snapshots and cannot use
 * WordPress's standard meta APIs. Caching is not implemented as version data
 * is accessed infrequently and caching would provide minimal benefit.
 */

/**
 * Class NBUF_Version_History
 *
 * Tracks and manages user profile version history.
 */
class NBUF_Version_History {


	/**
	 * Original user data (before changes).
	 *
	 * @var array
	 */
	private $original_data = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );

		if ( ! $enabled ) {
			return;
		}

		/* Hook into profile updates */
		add_action( 'profile_update', array( $this, 'track_profile_update' ), 10, 2 );
		add_action( 'user_register', array( $this, 'track_user_registration' ), 10, 1 );

		/* Store original data before updates */
		add_action( 'personal_options_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'edit_user_profile_update', array( $this, 'store_original_data' ), 1 );

		/* Cleanup cron */
		add_action( 'nbuf_cleanup_version_history', array( $this, 'cleanup_old_versions' ) );

		/* AJAX handlers */
		add_action( 'wp_ajax_nbuf_get_version_timeline', array( $this, 'ajax_get_version_timeline' ) );
		add_action( 'wp_ajax_nbuf_get_version_diff', array( $this, 'ajax_get_version_diff' ) );
		add_action( 'wp_ajax_nbuf_revert_version', array( $this, 'ajax_revert_version' ) );

		/* Account page section (if user visibility enabled) */
		$user_visible = NBUF_Options::get( 'nbuf_version_history_user_visible', false );
		if ( $user_visible ) {
			add_action( 'nbuf_account_version_history_section', array( $this, 'render_account_section' ) );
		}
	}

	/**
	 * Store original user data before update
	 *
	 * @param int $user_id User ID.
	 */
	public function store_original_data( $user_id ) {
		$this->original_data[ $user_id ] = $this->get_user_snapshot( $user_id );
	}

	/**
	 * Track profile update
	 *
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Old user object (WP_User).
	 */
	public function track_profile_update( $user_id, $old_user_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $old_user_data required by WordPress profile_update action signature
		/* Get before/after snapshots */
		$before = isset( $this->original_data[ $user_id ] )
		? $this->original_data[ $user_id ]
		: $this->get_user_snapshot( $user_id );

		$after = $this->get_user_snapshot( $user_id );

		/* Detect changed fields */
		$changed_fields = $this->detect_changed_fields( $before, $after );

		/* Only save if something changed */
		if ( empty( $changed_fields ) ) {
			return;
		}

		/* Determine who made the change */
		$current_user_id = get_current_user_id();
		$changed_by      = ( $current_user_id === $user_id ) ? null : $current_user_id;

		/* Determine change type */
		$change_type = ( null === $changed_by ) ? 'profile_update' : 'admin_update';

		/* Save version */
		$this->save_version( $user_id, $after, $changed_fields, $change_type, $changed_by );

		/* Cleanup old versions for this user */
		$this->enforce_max_versions( $user_id );
	}

	/**
	 * Track user registration (initial snapshot)
	 *
	 * @param int $user_id User ID.
	 */
	public function track_user_registration( $user_id ) {
		$snapshot = $this->get_user_snapshot( $user_id );

		/* Get all field names as "changed" for registration */
		$all_fields = array_keys( $snapshot );

		$this->save_version(
			$user_id,
			$snapshot,
			$all_fields,
			'registration',
			null
		);
	}

	/**
	 * Get complete user profile snapshot
	 *
	 * @param  int $user_id User ID.
	 * @return array Complete profile data
	 */
	private function get_user_snapshot( $user_id ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$snapshot = array(
			/* WordPress core fields */
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'description'  => $user->description,
			'user_url'     => $user->user_url,
			'role'         => ! empty( $user->roles ) ? $user->roles[0] : '',
		);

		/* Get NBUF user_data */
		$user_data_table = $wpdb->prefix . 'nbuf_user_data';
		$user_data       = $wpdb->get_row(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM $user_data_table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( $user_data ) {
			unset( $user_data['user_id'] );
			$snapshot['nbuf_user_data'] = $user_data;
		}

		/* Get NBUF profile data (53 fields) */
		$profile_table = $wpdb->prefix . 'nbuf_user_profile';
		$profile       = $wpdb->get_row(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM $profile_table WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( $profile ) {
			unset( $profile['user_id'] );
			unset( $profile['created_at'] );
			unset( $profile['updated_at'] );
			$snapshot['nbuf_profile'] = $profile;
		}

		/* Get relevant user meta (2FA, privacy settings, etc.) */
		$meta_keys = array(
			'nbuf_2fa_enabled',
			'nbuf_profile_privacy',
			'nbuf_show_in_directory',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_user_meta( $user_id, $key, true );
			if ( '' !== $value ) {
				$snapshot[ $key ] = $value;
			}
		}

		return $snapshot;
	}

	/**
	 * Detect which fields changed between two snapshots
	 *
	 * @param  array $before Before snapshot.
	 * @param  array $after  After snapshot.
	 * @return array Changed field names
	 */
	private function detect_changed_fields( $before, $after ) {
		$changed = array();

		/* Compare all keys from both snapshots */
		$all_keys = array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) );

		foreach ( $all_keys as $key ) {
			$before_value = isset( $before[ $key ] ) ? $before[ $key ] : null;
			$after_value  = isset( $after[ $key ] ) ? $after[ $key ] : null;

			/* Deep comparison for arrays */
			if ( is_array( $before_value ) || is_array( $after_value ) ) {
				if ( wp_json_encode( $before_value ) !== wp_json_encode( $after_value ) ) {
					$changed[] = $key;
				}
			} elseif ( $before_value !== $after_value ) {
				$changed[] = $key;
			}
		}

		return $changed;
	}

	/**
	 * Save a version snapshot to the database
	 *
	 * @param  int    $user_id        User ID.
	 * @param  array  $snapshot       Profile snapshot data.
	 * @param  array  $changed_fields Changed field names.
	 * @param  string $change_type    Type of change (registration, profile_update, admin_update, import, revert).
	 * @param  int    $changed_by     User ID who made the change (null = self).
	 * @return bool Success
	 */
	private function save_version( $user_id, $snapshot, $changed_fields, $change_type, $changed_by = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		/* Get IP tracking setting */
		$ip_tracking = NBUF_Options::get( 'nbuf_version_history_ip_tracking', 'off' );
		$ip_address  = null;

		if ( 'off' !== $ip_tracking ) {
			$ip_address = $this->get_ip_address( 'anonymized' === $ip_tracking );
		}

		/* Get user agent */
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
		? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
		: null;

		/* Insert version record */
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'        => $user_id,
				'changed_at'     => current_time( 'mysql', true ),
				'changed_by'     => $changed_by,
				'change_type'    => $change_type,
				'fields_changed' => wp_json_encode( $changed_fields ),
				'snapshot_data'  => wp_json_encode( $snapshot ),
				'ip_address'     => $ip_address,
				'user_agent'     => $user_agent,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (bool) $inserted;
	}

	/**
	 * Get IP address with optional anonymization
	 *
	 * @param  bool $anonymize Whether to anonymize IP.
	 * @return string|null IP address
	 */
	private function get_ip_address( $anonymize = false ) {
		$ip = '';

		/* Check various headers for IP */
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/* Validate IP */
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );
		if ( ! $ip ) {
			return null;
		}

		/* Anonymize if requested */
		if ( $anonymize ) {
			$ip = $this->anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Anonymize IP address (remove last octet for IPv4, last 80 bits for IPv6)
	 *
	 * @param  string $ip IP address.
	 * @return string Anonymized IP.
	 */
	private function anonymize_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			/* IPv4: Replace last octet with 0 */
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			/* IPv6: Replace last 80 bits with zeros */
			$parts = explode( ':', $ip );
			$parts = array_slice( $parts, 0, 3 );
			return implode( ':', $parts ) . '::';
		}

		return $ip;
	}

	/**
	 * Get version history for a user (timeline)
	 *
	 * @param  int $user_id User ID.
	 * @param  int $limit   Number of versions to retrieve (default: 50).
	 * @param  int $offset  Offset for pagination.
	 * @return array Version history records.
	 */
	public function get_user_versions( $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		$versions = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i
			WHERE user_id = %d
			ORDER BY changed_at DESC
			LIMIT %d OFFSET %d',
				$table_name,
				$user_id,
				$limit,
				$offset
			)
		);

		/* Decode JSON fields */
		foreach ( $versions as &$version ) {
			$version->fields_changed = json_decode( $version->fields_changed, true );
			$version->snapshot_data  = json_decode( $version->snapshot_data, true );
		}

		return $versions;
	}

	/**
	 * Get total version count for a user
	 *
	 * @param  int $user_id User ID.
	 * @return int Total versions.
	 */
	public function get_user_version_count( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);
	}

	/**
	 * Get a specific version by ID
	 *
	 * @param  int $version_id Version ID.
	 * @return object|null Version record.
	 */
	public function get_version_by_id( $version_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		$version = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$version_id
			)
		);

		if ( $version ) {
			$version->fields_changed = json_decode( $version->fields_changed, true );
			$version->snapshot_data  = json_decode( $version->snapshot_data, true );
		}

		return $version;
	}

	/**
	 * Compare two versions (for diff view)
	 *
	 * @param  int $version_id_1 First version ID (older).
	 * @param  int $version_id_2 Second version ID (newer).
	 * @return array Diff data.
	 */
	public function compare_versions( $version_id_1, $version_id_2 ) {
		$version1 = $this->get_version_by_id( $version_id_1 );
		$version2 = $this->get_version_by_id( $version_id_2 );

		if ( ! $version1 || ! $version2 ) {
			return array();
		}

		$snapshot1 = $version1->snapshot_data;
		$snapshot2 = $version2->snapshot_data;

		/* Get all unique keys */
		$all_keys = array_unique( array_merge( array_keys( $snapshot1 ), array_keys( $snapshot2 ) ) );

		$diff = array();

		foreach ( $all_keys as $key ) {
			$value1 = isset( $snapshot1[ $key ] ) ? $snapshot1[ $key ] : null;
			$value2 = isset( $snapshot2[ $key ] ) ? $snapshot2[ $key ] : null;

			/* Convert arrays to JSON for display */
			if ( is_array( $value1 ) ) {
				$value1 = wp_json_encode( $value1, JSON_PRETTY_PRINT );
			}
			if ( is_array( $value2 ) ) {
				$value2 = wp_json_encode( $value2, JSON_PRETTY_PRINT );
			}

			/* Determine change status */
			$status = 'unchanged';
			if ( $value1 !== $value2 ) {
				if ( null === $value1 ) {
					$status = 'added';
				} elseif ( null === $value2 ) {
					$status = 'removed';
				} else {
					$status = 'changed';
				}
			}

			$diff[ $key ] = array(
				'field'  => $key,
				'before' => $value1,
				'after'  => $value2,
				'status' => $status,
			);
		}

		return $diff;
	}

	/**
	 * Revert user profile to a specific version
	 *
	 * @param  int $user_id    User ID.
	 * @param  int $version_id Version ID to revert to.
	 * @return bool Success.
	 */
	public function revert_to_version( $user_id, $version_id ) {
		global $wpdb;

		/* Get the version */
		$version = $this->get_version_by_id( $version_id );
		if ( ! $version || $version->user_id !== $user_id ) {
			return false;
		}

		$snapshot = $version->snapshot_data;

		/* Update WordPress core user fields */
		$user_data = array( 'ID' => $user_id );

		if ( isset( $snapshot['user_email'] ) ) {
			$user_data['user_email'] = $snapshot['user_email'];
		}
		if ( isset( $snapshot['display_name'] ) ) {
			$user_data['display_name'] = $snapshot['display_name'];
		}
		if ( isset( $snapshot['user_url'] ) ) {
			$user_data['user_url'] = $snapshot['user_url'];
		}
		if ( isset( $snapshot['description'] ) ) {
			$user_data['description'] = $snapshot['description'];
		}
		if ( isset( $snapshot['role'] ) && ! empty( $snapshot['role'] ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( $snapshot['role'] );
		}

		wp_update_user( $user_data );

		/* Update user meta */
		if ( isset( $snapshot['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', $snapshot['first_name'] );
		}
		if ( isset( $snapshot['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', $snapshot['last_name'] );
		}

		/* Update NBUF user_data table */
		if ( isset( $snapshot['nbuf_user_data'] ) && is_array( $snapshot['nbuf_user_data'] ) ) {
			$user_data_table = $wpdb->prefix . 'nbuf_user_data';

			/* Check if row exists */
			$exists = $wpdb->get_var(
				$wpdb->prepare(
           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					"SELECT COUNT(*) FROM $user_data_table WHERE user_id = %d",
					$user_id
				)
			);

			$data            = $snapshot['nbuf_user_data'];
			$data['user_id'] = $user_id;

			if ( $exists ) {
				$wpdb->update( $user_data_table, $data, array( 'user_id' => $user_id ) );
			} else {
				$wpdb->insert( $user_data_table, $data );
			}
		}

		/* Update NBUF profile table */
		if ( isset( $snapshot['nbuf_profile'] ) && is_array( $snapshot['nbuf_profile'] ) ) {
			$profile_table = $wpdb->prefix . 'nbuf_user_profile';

			/* Check if row exists */
			$exists = $wpdb->get_var(
				$wpdb->prepare(
           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					"SELECT COUNT(*) FROM $profile_table WHERE user_id = %d",
					$user_id
				)
			);

			$data            = $snapshot['nbuf_profile'];
			$data['user_id'] = $user_id;

			if ( $exists ) {
				$wpdb->update( $profile_table, $data, array( 'user_id' => $user_id ) );
			} else {
				$wpdb->insert( $profile_table, $data );
			}
		}

		/* Create a new version entry for the revert */
		$current_snapshot = $this->get_user_snapshot( $user_id );
		$all_fields       = array_keys( $snapshot );

		$this->save_version(
			$user_id,
			$current_snapshot,
			$all_fields,
			'revert',
			get_current_user_id()
		);

		return true;
	}

	/**
	 * Enforce maximum versions per user
	 *
	 * @param int $user_id User ID.
	 */
	private function enforce_max_versions( $user_id ) {
		global $wpdb;

		$max_versions = (int) NBUF_Options::get( 'nbuf_version_history_max_versions', 50 );
		$table_name   = $wpdb->prefix . 'nbuf_profile_versions';

		/* Get current count */
		$count = $this->get_user_version_count( $user_id );

		if ( $count > $max_versions ) {
			/* Delete oldest versions */
			$delete_count = $count - $max_versions;

			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i
				WHERE user_id = %d
				ORDER BY changed_at ASC
				LIMIT %d',
					$table_name,
					$user_id,
					$delete_count
				)
			);
		}
	}

	/**
	 * Cleanup old versions based on retention period
	 */
	public function cleanup_old_versions() {
		global $wpdb;

		$retention_days = (int) NBUF_Options::get( 'nbuf_version_history_retention_days', 365 );
		$table_name     = $wpdb->prefix . 'nbuf_profile_versions';

		/* Delete versions older than retention period */
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE changed_at < %s',
				$table_name,
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * AJAX: Get version timeline
	 */
	public function ajax_get_version_timeline() {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		/* Check permissions */
		if ( ! $this->can_view_version_history( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$versions = $this->get_user_versions( $user_id, $per_page, $offset );
		$total    = $this->get_user_version_count( $user_id );

		wp_send_json_success(
			array(
				'versions' => $versions,
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * AJAX: Get version diff
	 */
	public function ajax_get_version_diff() {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		$version_id_1 = isset( $_POST['version_id_1'] ) ? absint( $_POST['version_id_1'] ) : 0;
		$version_id_2 = isset( $_POST['version_id_2'] ) ? absint( $_POST['version_id_2'] ) : 0;

		$version1 = $this->get_version_by_id( $version_id_1 );
		$version2 = $this->get_version_by_id( $version_id_2 );

		if ( ! $version1 || ! $version2 ) {
			wp_send_json_error( array( 'message' => 'Versions not found.' ) );
		}

		/* Check permissions */
		if ( ! $this->can_view_version_history( $version1->user_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$diff = $this->compare_versions( $version_id_1, $version_id_2 );

		wp_send_json_success(
			array(
				'diff'     => $diff,
				'version1' => $version1,
				'version2' => $version2,
			)
		);
	}

	/**
	 * AJAX: Revert to version
	 */
	public function ajax_revert_version() {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
		$version    = $this->get_version_by_id( $version_id );

		if ( ! $version ) {
			wp_send_json_error( array( 'message' => 'Version not found.' ) );
		}

		/* Check permissions */
		if ( ! $this->can_revert_version( $version->user_id ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$success = $this->revert_to_version( $version->user_id, $version_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => 'Profile reverted successfully.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Revert failed.' ) );
		}
	}

	/**
	 * Check if current user can view version history
	 *
	 * @param  int $user_id User ID whose history to view.
	 * @return bool Can view.
	 */
	private function can_view_version_history( $user_id ) {
		$current_user_id = get_current_user_id();

		/* Admins can always view */
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		/* Users can view their own if enabled */
		$user_visible = NBUF_Options::get( 'nbuf_version_history_user_visible', true );
		if ( $user_visible && $current_user_id === $user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current user can revert a version
	 *
	 * @param  int $user_id User ID to revert.
	 * @return bool Can revert.
	 */
	private function can_revert_version( $user_id ) {
		$current_user_id = get_current_user_id();

		/* Admins can always revert */
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		/* Users can revert their own if allowed */
		$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
		if ( $allow_user_revert && $current_user_id === $user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete all versions for a user (for GDPR compliance)
	 *
	 * @param  int $user_id User ID.
	 * @return bool Success.
	 */
	public function delete_user_versions( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		return (bool) $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Render version history section on account page
	 *
	 * @param int $user_id User ID.
	 */
	public function render_account_section( $user_id ) {
		/* Only show for current user */
		if ( get_current_user_id() !== $user_id ) {
			return;
		}

		/* Check if user can revert */
		$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
		$can_revert        = $allow_user_revert;

		/* Enqueue version history CSS */
		wp_enqueue_style(
			'nbuf-version-history',
			plugin_dir_url( __DIR__ ) . 'assets/css/admin/version-history.css',
			array(),
			'1.4.0'
		);

		/* Enqueue version history JS */
		wp_enqueue_script( 'nbuf-version-history', plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js', array( 'jquery' ), '1.4.0', true );
		wp_localize_script(
			'nbuf-version-history',
			'NBUF_VersionHistory',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'nbuf_version_history' ),
				'can_revert' => $can_revert ? true : false,
				'i18n'       => array(
					'registration'   => __( 'Registration', 'nobloat-user-foundry' ),
					'profile_update' => __( 'Profile Update', 'nobloat-user-foundry' ),
					'admin_update'   => __( 'Admin Update', 'nobloat-user-foundry' ),
					'import'         => __( 'Import', 'nobloat-user-foundry' ),
					'reverted'       => __( 'Reverted', 'nobloat-user-foundry' ),
					'self'           => __( 'Self', 'nobloat-user-foundry' ),
					'admin'          => __( 'Admin', 'nobloat-user-foundry' ),
					'confirm_revert' => __( 'Are you sure you want to revert to this version? This will create a new version entry.', 'nobloat-user-foundry' ),
					'revert_success' => __( 'Profile reverted successfully.', 'nobloat-user-foundry' ),
					'revert_failed'  => __( 'Revert failed.', 'nobloat-user-foundry' ),
					'error'          => __( 'An error occurred.', 'nobloat-user-foundry' ),
					'before'         => __( 'Before:', 'nobloat-user-foundry' ),
					'after'          => __( 'After:', 'nobloat-user-foundry' ),
					'field'          => __( 'Field', 'nobloat-user-foundry' ),
					'before_value'   => __( 'Before', 'nobloat-user-foundry' ),
					'after_value'    => __( 'After', 'nobloat-user-foundry' ),
				),
			)
		);

		?>
		<div class="nbuf-account-section nbuf-vh-account">
			<h2 class="nbuf-section-title"><?php esc_html_e( 'Profile History', 'nobloat-user-foundry' ); ?></h2>

		<?php if ( ! $allow_user_revert ) : ?>
				<div class="nbuf-message nbuf-message-info" style="margin-bottom: 20px;">
			<?php esc_html_e( 'You can view your profile change history below. Only administrators can restore previous versions.', 'nobloat-user-foundry' ); ?>
				</div>
		<?php endif; ?>

		<?php
		/* Include the version history viewer template */
		$context = 'account';
		include plugin_dir_path( __DIR__ ) . 'templates/version-history-viewer.php';
		?>
		</div>
		<?php
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
