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
	 * @var array<int, array<string, mixed>>
	 */
	private $original_data = array();

	/**
	 * Users already processed in this request (prevents duplicate entries).
	 *
	 * @var array<int, bool>
	 */
	private $processed_users = array();

	/**
	 * Users with pending NBUF frontend updates (defer to nbuf_after_profile_update).
	 *
	 * When nbuf_before_profile_update fires, the user is added here. This tells
	 * track_profile_update to skip processing, allowing track_frontend_profile_update
	 * to capture the final state after ALL updates (including NBUF custom tables).
	 *
	 * @var array<int, bool>
	 */
	private $pending_frontend_updates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );

		if ( ! $enabled ) {
			return;
		}

		/* Hook into profile updates (WordPress core + frontend account page) */
		add_action( 'profile_update', array( $this, 'track_profile_update' ), 10, 2 );
		add_action( 'user_register', array( $this, 'track_user_registration' ), 10, 1 );
		add_action( 'nbuf_after_profile_update', array( $this, 'track_frontend_profile_update' ), 10, 1 );

		/* Store original data before updates (admin + frontend) */
		add_action( 'personal_options_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'edit_user_profile_update', array( $this, 'store_original_data' ), 1 );
		add_action( 'nbuf_before_profile_update', array( $this, 'store_original_data' ), 1 );

		/* Cleanup cron */
		add_action( 'nbuf_cleanup_version_history', array( $this, 'cleanup_old_versions' ) );

		/* AJAX handlers */
		add_action( 'wp_ajax_nbuf_get_version_timeline', array( $this, 'ajax_get_version_timeline' ) );
		add_action( 'wp_ajax_nbuf_get_version_diff', array( $this, 'ajax_get_version_diff' ) );
		add_action( 'wp_ajax_nbuf_revert_version', array( $this, 'ajax_revert_version' ) );

		/* Account page section - always register, visibility checked in shortcode */
		add_action( 'nbuf_account_version_history_section', array( $this, 'render_account_section' ) );
	}

	/**
	 * Store original user data before update
	 *
	 * Only stores data if not already captured for this user during this request.
	 * This prevents multiple hooks from overwriting the true original state with
	 * intermediate states during a single profile update operation.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function store_original_data( int $user_id ): void {
		/* Skip if already processed a version for this user in this request */
		if ( isset( $this->processed_users[ $user_id ] ) ) {
			return;
		}

		/* Only store if not already captured - prevents intermediate state overwrites */
		if ( isset( $this->original_data[ $user_id ] ) ) {
			return;
		}

		/*
		 * If called from nbuf_before_profile_update, mark this as a frontend update.
		 * This tells track_profile_update to skip and let track_frontend_profile_update
		 * capture the final state after ALL updates (including NBUF custom tables).
		 */
		if ( doing_action( 'nbuf_before_profile_update' ) ) {
			$this->pending_frontend_updates[ $user_id ] = true;
		}

		/*
		 * Use output buffering to prevent any database warnings/notices
		 * from corrupting AJAX JSON responses.
		 */
		ob_start();

		try {
			$this->original_data[ $user_id ] = $this->get_user_snapshot( $user_id );
			ob_end_clean();
		} catch ( \Throwable $e ) {
			/* Silently fail - don't break the profile update */
			$this->original_data[ $user_id ] = array();
			ob_end_clean();
		}
	}

	/**
	 * Track profile update
	 *
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Old user object (WP_User).
	 * @return void
	 */
	public function track_profile_update( int $user_id, object $old_user_data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $old_user_data required by WordPress profile_update action signature
		/* Skip if already processed in this request (prevents duplicate entries) */
		if ( isset( $this->processed_users[ $user_id ] ) ) {
			return;
		}

		/*
		 * Skip if this is a NBUF frontend update - let track_frontend_profile_update handle it.
		 * This ensures we capture the final state AFTER all updates including NBUF custom tables.
		 */
		if ( isset( $this->pending_frontend_updates[ $user_id ] ) ) {
			return;
		}

		/* Skip if no original data captured (prevents duplicate logging) */
		if ( ! isset( $this->original_data[ $user_id ] ) ) {
			return;
		}

		/* Get before/after snapshots */
		$before = $this->original_data[ $user_id ];
		$after  = $this->get_user_snapshot( $user_id );

		/* Clear original data to prevent duplicate logging from other hooks */
		unset( $this->original_data[ $user_id ] );

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

		/* Mark user as processed to prevent duplicate entries from other hooks */
		$this->processed_users[ $user_id ] = true;

		/* Cleanup old versions for this user */
		$this->enforce_max_versions( $user_id );
	}

	/**
	 * Track user registration (initial snapshot)
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function track_user_registration( int $user_id ): void {
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
	 * Track frontend profile update (from account page)
	 *
	 * This fires after nbuf_after_profile_update action, which captures
	 * changes made via update_user_meta() that don't trigger profile_update.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function track_frontend_profile_update( int $user_id ): void {
		/* Skip if already processed in this request (prevents duplicate entries) */
		if ( isset( $this->processed_users[ $user_id ] ) ) {
			return;
		}

		/*
		 * Use output buffering to prevent any database warnings/notices
		 * from corrupting AJAX JSON responses.
		 */
		ob_start();

		try {
			/* Skip if already tracked by profile_update hook (original_data cleared) */
			if ( ! isset( $this->original_data[ $user_id ] ) ) {
				ob_end_clean();
				return;
			}

			/* Get before/after snapshots */
			$before = $this->original_data[ $user_id ];
			$after  = $this->get_user_snapshot( $user_id );

			/* Clear original data to prevent duplicate logging */
			unset( $this->original_data[ $user_id ] );

			/* Detect changed fields */
			$changed_fields = $this->detect_changed_fields( $before, $after );

			/* Only save if something changed */
			if ( empty( $changed_fields ) ) {
				ob_end_clean();
				return;
			}

			/* Frontend updates are always self-updates */
			$this->save_version( $user_id, $after, $changed_fields, 'profile_update', null );

			/* Mark user as processed and clear pending flag */
			$this->processed_users[ $user_id ] = true;
			unset( $this->pending_frontend_updates[ $user_id ] );

			/* Cleanup old versions for this user */
			$this->enforce_max_versions( $user_id );

			ob_end_clean();
		} catch ( \Throwable $e ) {
			/* Silently fail - don't break the profile update */
			ob_end_clean();
		}
	}

	/**
	 * Get complete user profile snapshot
	 *
	 * @param  int $user_id User ID.
	 * @return array<string, mixed> Complete profile data
	 */
	private function get_user_snapshot( int $user_id ): array {
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
				'SELECT * FROM %i WHERE user_id = %d',
				$user_data_table,
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
				'SELECT * FROM %i WHERE user_id = %d',
				$profile_table,
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

		// Get relevant settings from custom tables.
		// 2FA status from nbuf_user_2fa table.
		$snapshot['nbuf_2fa_enabled'] = NBUF_User_2FA_Data::is_enabled( $user_id ) ? '1' : '0';

		/* Privacy settings from nbuf_user_data table */
		$user_data = NBUF_User_Data::get( $user_id );
		if ( $user_data ) {
			if ( ! empty( $user_data->profile_privacy ) ) {
				$snapshot['nbuf_profile_privacy'] = $user_data->profile_privacy;
			}
			$snapshot['nbuf_show_in_directory'] = isset( $user_data->show_in_directory ) ? (string) $user_data->show_in_directory : '0';
		}

		return $snapshot;
	}

	/**
	 * Detect which fields changed between two snapshots
	 *
	 * @param  array<string, mixed> $before Before snapshot.
	 * @param  array<string, mixed> $after  After snapshot.
	 * @return array<int, string> Changed field names
	 */
	private function detect_changed_fields( array $before, array $after ): array {
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
	 * @param  int                  $user_id        User ID.
	 * @param  array<string, mixed> $snapshot       Profile snapshot data.
	 * @param  array<int, string>   $changed_fields Changed field names.
	 * @param  string               $change_type    Type of change (registration, profile_update, admin_update, import, revert).
	 * @param  int|null             $changed_by     User ID who made the change (null = self).
	 * @return bool Success
	 */
	private function save_version( int $user_id, array $snapshot, array $changed_fields, string $change_type, ?int $changed_by = null ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_profile_versions';

		/* Check if table exists - fail gracefully if not */
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( ! $table_exists ) {
			/* Table doesn't exist - try to create it */
			if ( class_exists( 'NBUF_Database' ) && method_exists( 'NBUF_Database', 'create_profile_versions_table' ) ) {
				/* Use output buffering to prevent dbDelta() from corrupting AJAX responses */
				ob_start();
				NBUF_Database::create_profile_versions_table();
				ob_end_clean();
			} else {
				return false;
			}
		}

		/* Get IP tracking setting */
		$ip_tracking = NBUF_Options::get( 'nbuf_version_history_ip_tracking', 'anonymized' );
		$ip_address  = null;

		if ( 'off' !== $ip_tracking ) {
			$ip_address = $this->get_ip_address( 'anonymized' === $ip_tracking );
		}

		/* Get user agent */
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
		? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
		: null;

		/* Build data and format arrays (handle NULL values properly) */
		$data   = array(
			'user_id'        => $user_id,
			'changed_at'     => gmdate( 'Y-m-d H:i:s' ),
			'change_type'    => $change_type,
			'fields_changed' => wp_json_encode( $changed_fields ),
			'snapshot_data'  => wp_json_encode( $snapshot ),
		);
		$format = array( '%d', '%s', '%s', '%s', '%s' );

		/* Add changed_by only if not null (to preserve NULL in database) */
		if ( null !== $changed_by ) {
			$data['changed_by'] = $changed_by;
			$format[]           = '%d';
		}

		/* Add optional fields */
		if ( null !== $ip_address ) {
			$data['ip_address'] = $ip_address;
			$format[]           = '%s';
		}
		if ( null !== $user_agent ) {
			$data['user_agent'] = $user_agent;
			$format[]           = '%s';
		}

		/* Insert version record */
		$inserted = $wpdb->insert( $table_name, $data, $format );

		return (bool) $inserted;
	}

	/**
	 * Get IP address with optional anonymization
	 *
	 * @param  bool $anonymize Whether to anonymize IP.
	 * @return string|null IP address
	 */
	private function get_ip_address( $anonymize = false ) {
		$ip = NBUF_IP::get_client_ip( true );

		if ( empty( $ip ) ) {
			return null;
		}

		/* Anonymize if requested */
		if ( $anonymize ) {
			$ip = $this->anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Anonymize IP address for GDPR compliance
	 *
	 * Removes last two octets for IPv4 (/16 network) and last 80 bits for IPv6.
	 * This provides stronger anonymization than single-octet removal.
	 *
	 * @param  string $ip IP address.
	 * @return string Anonymized IP.
	 */
	private function anonymize_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			/* IPv4: Replace last TWO octets with 0 for stronger GDPR compliance (/16 network) */
			$parts    = explode( '.', $ip );
			$parts[2] = '0';
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
	 * @return array<int, object> Version history records.
	 */
	public function get_user_versions( int $user_id, int $limit = 50, int $offset = 0 ): array {
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
	 * @return array<string, array<string, mixed>> Diff data.
	 */
	public function compare_versions( int $version_id_1, int $version_id_2 ): array {
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
	 * @param  int  $user_id    User ID.
	 * @param  int  $version_id Version ID to revert to.
	 * @param  bool $as_admin   True when the caller has admin authority over
	 *                          this user; false for self-revert. Self-revert
	 *                          uses a strictly narrower allowlist that
	 *                          excludes role, verification state, pending
	 *                          email, and account-status flags so a user
	 *                          cannot bypass the admin-controlled state-
	 *                          change paths via "revert to an old snapshot".
	 * @return bool Success.
	 */
	public function revert_to_version( $user_id, $version_id, bool $as_admin = false ) {
		global $wpdb;

		/* Get the version */
		$version = $this->get_version_by_id( $version_id );
		if ( ! $version || $version->user_id !== $user_id ) {
			return false;
		}

		$snapshot = $version->snapshot_data;

		/* Update WordPress core user fields — sanitize all values from snapshot */
		$user_data = array( 'ID' => $user_id );

		if ( isset( $snapshot['user_email'] ) && is_email( $snapshot['user_email'] ) ) {
			/*
			 * Email change in a self-revert would bypass the email-change
			 * verification flow (a normal email change requires the user
			 * to click a confirmation link sent to the NEW address). Drop
			 * the email field entirely on self-revert.
			 */
			if ( $as_admin ) {
				$user_data['user_email'] = sanitize_email( $snapshot['user_email'] );
			}
		}
		if ( isset( $snapshot['display_name'] ) ) {
			$user_data['display_name'] = sanitize_text_field( $snapshot['display_name'] );
		}
		if ( isset( $snapshot['user_url'] ) ) {
			$user_data['user_url'] = esc_url_raw( $snapshot['user_url'] );
		}
		if ( isset( $snapshot['description'] ) ) {
			$user_data['description'] = sanitize_textarea_field( $snapshot['description'] );
		}

		/*
		 * Role revert is admin-only. A user reverting their own snapshot
		 * must not be able to restore a role they previously held — that
		 * would let any user who once had elevated privileges re-grant
		 * them via "revert profile" long after a demotion.
		 */
		if ( $as_admin && isset( $snapshot['role'] ) && ! empty( $snapshot['role'] ) && wp_roles()->is_role( $snapshot['role'] ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( sanitize_key( $snapshot['role'] ) );
		}

		$update_result = wp_update_user( $user_data );
		if ( is_wp_error( $update_result ) ) {
			return false;
		}

		/* Update user meta — sanitize to prevent stored XSS from tampered snapshots */
		if ( isset( $snapshot['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( $snapshot['first_name'] ) );
		}
		if ( isset( $snapshot['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( $snapshot['last_name'] ) );
		}

		/* Update NBUF user_data table */
		if ( isset( $snapshot['nbuf_user_data'] ) && is_array( $snapshot['nbuf_user_data'] ) ) {
			$user_data_table = $wpdb->prefix . 'nbuf_user_data';

			/* Check if row exists */
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE user_id = %d',
					$user_data_table,
					$user_id
				)
			);

			/*
			 * SECURITY: revert mass-assignment is constrained to a known-
			 * safe column set. Without this allowlist, a tampered or
			 * imported snapshot could revive privileged columns
			 * (e.g., is_disabled=0, is_verified=1, expires_at) bypassing
			 * the admin-controlled state-change paths. Drop any unknown
			 * keys before the UPDATE/INSERT.
			 *
			 * Self-revert (admin = false) uses an even narrower allowlist:
			 * verification state, pending_email, and last_login_at are
			 * admin-controlled lifecycle metadata, NOT user preferences,
			 * and a user reverting their own profile snapshot must not
			 * be able to flip them. A user reverting to a snapshot taken
			 * before an admin un-verified them, for instance, would
			 * otherwise re-verify themselves; a snapshot taken with an
			 * unsubmitted pending email would resurrect the pending change.
			 */
			$allowed_user_data_columns_admin = array(
				'is_verified',
				'verified_date',
				'verification_sent_at',
				'profile_privacy',
				'show_in_directory',
				'visible_fields',
				'use_gravatar',
				'cover_photo_url',
				'cover_photo_path',
				'profile_photo_url',
				'profile_photo_path',
				'pending_email',
				'last_login_at',
			);
			$allowed_user_data_columns_user  = array(
				'profile_privacy',
				'show_in_directory',
				'visible_fields',
				'use_gravatar',
				'cover_photo_url',
				'cover_photo_path',
				'profile_photo_url',
				'profile_photo_path',
			);
			$allowed_user_data_columns       = $as_admin ? $allowed_user_data_columns_admin : $allowed_user_data_columns_user;
			$data                            = array_intersect_key( (array) $snapshot['nbuf_user_data'], array_flip( $allowed_user_data_columns ) );
			if ( ! empty( $data ) ) {
				$data['user_id'] = $user_id;
				$format          = array_fill( 0, count( $data ), '%s' );
				if ( $exists ) {
					$wpdb->update( $user_data_table, $data, array( 'user_id' => $user_id ), $format, array( '%d' ) );
				} else {
					$wpdb->insert( $user_data_table, $data, $format );
				}
			}
		}

		/* Update NBUF profile table */
		if ( isset( $snapshot['nbuf_profile'] ) && is_array( $snapshot['nbuf_profile'] ) ) {
			$profile_table = $wpdb->prefix . 'nbuf_user_profile';

			/* Check if row exists */
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE user_id = %d',
					$profile_table,
					$user_id
				)
			);

			/*
			 * SECURITY: same allowlist treatment for nbuf_user_profile.
			 * Use NBUF_Profile_Data's known field keys when available so
			 * the revert column set stays in sync with profile-data.
			 */
			$allowed_profile_columns = class_exists( 'NBUF_Profile_Data' ) && method_exists( 'NBUF_Profile_Data', 'get_all_field_keys' )
				? NBUF_Profile_Data::get_all_field_keys()
				: array();
			$data                    = ! empty( $allowed_profile_columns )
				? array_intersect_key( (array) $snapshot['nbuf_profile'], array_flip( $allowed_profile_columns ) )
				: (array) $snapshot['nbuf_profile'];
			if ( ! empty( $data ) ) {
				$data['user_id'] = $user_id;
				$format          = array_fill( 0, count( $data ), '%s' );
				if ( $exists ) {
					$wpdb->update( $profile_table, $data, array( 'user_id' => $user_id ), $format, array( '%d' ) );
				} else {
					$wpdb->insert( $profile_table, $data, $format );
				}
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

		/*
		 * Record the revert in the admin audit log so privileged
		 * profile changes via this path leave the same forensic trail
		 * as direct admin profile edits. Previously reverts only
		 * appeared in nbuf_profile_versions and bypassed admin-audit-log.
		 */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				get_current_user_id(),
				'profile_edited_by_admin',
				'success',
				sprintf(
					/* translators: 1: target user ID, 2: version ID being reverted to */
					__( 'Reverted user %1$d to version %2$d', 'nobloat-user-foundry' ),
					$user_id,
					$version_id
				),
				$user_id,
				array(
					'version_id'      => $version_id,
					'reverted_fields' => $all_fields,
				)
			);
		}

		return true;
	}

	/**
	 * Enforce maximum versions per user
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function enforce_max_versions( int $user_id ): void {
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
	 *
	 * @return int Number of deleted records.
	 */
	public function cleanup_old_versions(): int {
		global $wpdb;

		$retention_days = (int) NBUF_Options::get( 'nbuf_version_history_retention_days', 365 );
		$table_name     = $wpdb->prefix . 'nbuf_profile_versions';

		/* Delete versions older than retention period */
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE changed_at < %s',
				$table_name,
				$cutoff_date
			)
		);

		/*
		 * Also clean up orphan records for deleted users.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphans_deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE pv FROM %i pv LEFT JOIN %i u ON pv.user_id = u.ID WHERE u.ID IS NULL',
				$table_name,
				$wpdb->users
			)
		);

		return $deleted + $orphans_deleted;
	}

	/**
	 * AJAX: Get version timeline
	 *
	 * @return void
	 */
	public function ajax_get_version_timeline(): void {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		/* Rate limiting: 30 requests per minute per user */
		$current_user_id = get_current_user_id();
		$rate_key        = 'nbuf_vh_rate_' . $current_user_id;
		$requests        = (int) get_transient( $rate_key );

		if ( $requests >= 30 ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please wait a moment.', 'nobloat-user-foundry' ) ) );
		}

		set_transient( $rate_key, $requests + 1, MINUTE_IN_SECONDS );

		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		/* Check permissions */
		if ( ! $this->can_view_version_history( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
		}

		$versions = $this->get_user_versions( $user_id, $per_page, $offset );
		$total    = $this->get_user_version_count( $user_id );

		/* Add formatted timestamps for browser display (uses WordPress date/time settings) */
		foreach ( $versions as &$version ) {
			$version->changed_at_date = NBUF_Options::format_local_time( $version->changed_at, 'date' );
			$version->changed_at_time = NBUF_Options::format_local_time( $version->changed_at, 'time' );
			$version->changed_at_full = NBUF_Options::format_local_time( $version->changed_at, 'full' );
		}

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
	 *
	 * @return void
	 */
	public function ajax_get_version_diff(): void {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		/*
		 * Rate-limit per user. Each diff decodes two JSON snapshots and
		 * compares them — for users with rich profiles the snapshots can
		 * be tens of kilobytes each, so an unbounded request rate is a
		 * cheap way to spike DB IO. Mirror the timeline endpoint's cap.
		 */
		$current_user_id = get_current_user_id();
		$rate_key        = 'nbuf_vh_diff_rate_' . $current_user_id;
		$requests        = (int) get_transient( $rate_key );
		if ( $requests >= 30 ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please wait a moment.', 'nobloat-user-foundry' ) ) );
			return;
		}
		set_transient( $rate_key, $requests + 1, MINUTE_IN_SECONDS );

		$version_id_1 = isset( $_POST['version_id_1'] ) ? absint( $_POST['version_id_1'] ) : 0;
		$version_id_2 = isset( $_POST['version_id_2'] ) ? absint( $_POST['version_id_2'] ) : 0;

		$version1 = $this->get_version_by_id( $version_id_1 );
		$version2 = $this->get_version_by_id( $version_id_2 );

		if ( ! $version1 || ! $version2 ) {
			wp_send_json_error( array( 'message' => __( 'Versions not found.', 'nobloat-user-foundry' ) ) );
			return;
		}

		/* Check permissions */
		if ( ! $this->can_view_version_history( $version1->user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
		}

		/* Both versions must belong to the same user to prevent cross-user data leak */
		if ( (int) $version1->user_id !== (int) $version2->user_id ) {
			wp_send_json_error( array( 'message' => __( 'Versions must belong to the same user.', 'nobloat-user-foundry' ) ) );
		}

		$diff = $this->compare_versions( $version_id_1, $version_id_2 );

		/* Add formatted timestamps for browser display (uses WordPress date/time settings) */
		$version1->changed_at_full = NBUF_Options::format_local_time( $version1->changed_at, 'full' );
		$version2->changed_at_full = NBUF_Options::format_local_time( $version2->changed_at, 'full' );

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
	 *
	 * @return void
	 */
	public function ajax_revert_version(): void {
		check_ajax_referer( 'nbuf_version_history', 'nonce' );

		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
		$posted_uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$version    = $this->get_version_by_id( $version_id );

		if ( ! $version ) {
			wp_send_json_error( array( 'message' => __( 'Version not found.', 'nobloat-user-foundry' ) ) );
			return;
		}

		/*
		 * SECURITY: cross-check the posted user_id against the version
		 * row's user_id. Without this, an admin viewing user A's history
		 * UI who is tricked into submitting a version_id from user B's
		 * snapshot would silently revert user B. Nonces protect against
		 * cross-site forgery; this check protects against same-site UI
		 * confusion / clickjacking.
		 */
		if ( $posted_uid && (int) $posted_uid !== (int) $version->user_id ) {
			wp_send_json_error( array( 'message' => __( 'User mismatch. Refresh and try again.', 'nobloat-user-foundry' ) ) );
			return;
		}

		/* Check permissions */
		if ( ! $this->can_revert_version( $version->user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
			return;
		}

		$as_admin = current_user_can( 'manage_options' );
		$success  = $this->revert_to_version( $version->user_id, $version_id, $as_admin );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Profile reverted successfully.', 'nobloat-user-foundry' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Revert failed.', 'nobloat-user-foundry' ) ) );
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
		$user_visible = NBUF_Options::get( 'nbuf_version_history_user_visible', false );
		if ( $user_visible && (int) $current_user_id === (int) $user_id ) {
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
		if ( $allow_user_revert && (int) $current_user_id === (int) $user_id ) {
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
	 * @return void
	 */
	public function render_account_section( int $user_id ): void {
		/* Only show for current user */
		if ( get_current_user_id() !== $user_id ) {
			return;
		}

		/* Check if user can revert */
		$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
		$can_revert        = $allow_user_revert;

		/* Enqueue version history CSS via CSS Manager */
		NBUF_CSS_Manager::enqueue_css(
			'nbuf-version-history',
			'version-history',
			'nbuf_version_history_custom_css',
			'nbuf_css_write_failed_version_history'
		);

		/* Enqueue version history JS */
		wp_enqueue_script( 'nbuf-version-history', plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js', array( 'jquery' ), '1.4.0', true );
		wp_localize_script( 'nbuf-version-history', 'NBUF_VersionHistory', self::get_script_data( $can_revert ) );

		?>
		<div class="nbuf-account-section nbuf-vh-account">

		<?php if ( ! $allow_user_revert ) : ?>
				<div class="nbuf-message nbuf-message-info nbuf-message-spaced">
			<?php esc_html_e( 'View your profile change history below. Only administrators can restore previous versions.', 'nobloat-user-foundry' ); ?>
				</div>
		<?php endif; ?>

		<?php
		/*
		 * Render the version history viewer.
		 */
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_viewer() returns escaped HTML.
		echo self::render_viewer( $user_id, 'account' );
		?>
		</div>
		<?php
	}

	/**
	 * Get script localization data for version history JS.
	 *
	 * Centralized method to avoid duplicating the localize_script data in multiple files.
	 * Includes the timeline item template with labels pre-replaced.
	 *
	 * @since 1.4.1
	 * @param bool $can_revert Whether the current user can revert versions.
	 * @return array<string, mixed> Localization data for wp_localize_script.
	 */
	public static function get_script_data( bool $can_revert = false ): array {
		return array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'nbuf_version_history' ),
			'can_revert'   => $can_revert ? true : false,
			'itemTemplate' => self::get_timeline_item_template(),
			'i18n'         => array(
				'registration'   => __( 'Registration', 'nobloat-user-foundry' ),
				'profile_update' => __( 'Profile Update', 'nobloat-user-foundry' ),
				'admin_update'   => __( 'Admin Update', 'nobloat-user-foundry' ),
				'import'         => __( 'Import', 'nobloat-user-foundry' ),
				'revert'         => __( 'Reverted', 'nobloat-user-foundry' ),
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
		);
	}

	/**
	 * Get the timeline item template HTML with labels replaced.
	 *
	 * This template is used by JavaScript to render each timeline item.
	 * Labels use {placeholder} format (PHP-replaced), while data uses
	 * {{placeholder}} format (JS-replaced at runtime).
	 *
	 * @since 1.4.1
	 * @return string Template HTML.
	 */
	private static function get_timeline_item_template() {
		$template = '
	<div class="nbuf-vh-item" data-version-id="{{version_id}}">
		<div class="nbuf-vh-item-icon">
			<span class="dashicons dashicons-{{icon}}"></span>
		</div>
		<div class="nbuf-vh-item-content">
			<div class="nbuf-vh-item-header">
				<div class="nbuf-vh-item-meta">
					<span class="nbuf-vh-item-date">{{date}}</span>
					<span class="nbuf-vh-item-time">{{time}}</span>
					<span class="nbuf-vh-item-type nbuf-vh-type-{{change_type}}">{{change_type_label}}</span>
				</div>
				<div class="nbuf-vh-item-user">
					{{changed_by}}
				</div>
			</div>
			<div class="nbuf-vh-item-details">
				<p class="nbuf-vh-item-fields">
					<strong>{fields_changed_label}</strong>
					{{fields_changed}}
				</p>
				{{#ip_address}}
				<p class="nbuf-vh-item-ip">
					<strong>{ip_address_label}</strong>
					{{ip_address}}
				</p>
				{{/ip_address}}
			</div>
			<div class="nbuf-vh-item-actions">
				<button class="nbuf-button nbuf-button-small nbuf-button-primary nbuf-vh-view-details" data-version-id="{{version_id}}">
					{view_snapshot_button}
				</button>
				{{#has_previous}}
				<button class="nbuf-button nbuf-button-small nbuf-button-primary nbuf-vh-compare" data-version-id="{{version_id}}" data-previous-id="{{previous_id}}">
					{compare_button}
				</button>
				{{/has_previous}}
				{{#can_revert}}
				<button class="nbuf-button nbuf-button-small nbuf-button-primary nbuf-vh-revert" data-version-id="{{version_id}}">
					{revert_button}
				</button>
				{{/can_revert}}
			</div>
		</div>
	</div>';

		/* Replace label placeholders with translated strings */
		$replacements = array(
			'{fields_changed_label}' => esc_html__( 'Fields changed:', 'nobloat-user-foundry' ),
			'{ip_address_label}'     => esc_html__( 'IP Address:', 'nobloat-user-foundry' ),
			'{view_snapshot_button}' => esc_html__( 'View Snapshot', 'nobloat-user-foundry' ),
			'{compare_button}'       => esc_html__( 'Compare Changes', 'nobloat-user-foundry' ),
			'{revert_button}'        => esc_html__( '⟲ Revert to This Version', 'nobloat-user-foundry' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Render version history viewer HTML
	 *
	 * Centralized method for rendering the version history viewer from HTML template.
	 * Called by admin, metabox, shortcode, and account page contexts.
	 *
	 * @since 1.4.0
	 * @param int    $user_id User ID whose history to display.
	 * @param string $context Context: 'admin', 'metabox', or 'account'.
	 * @return string HTML output.
	 */
	public static function render_viewer( $user_id, $context = 'admin' ) {
		/* Get user */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		/* Check revert permission */
		$current_user_id   = get_current_user_id();
		$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
		$can_revert        = current_user_can( 'manage_options' ) || ( $allow_user_revert && $user_id === $current_user_id );

		/* Load HTML template (checks DB first, falls back to default file) */
		$template = NBUF_Template_Manager::load_template( 'version-history-viewer-html' );

		/* Build replacements */
		$replacements = array(
			'{user_id}'              => esc_attr( $user_id ),
			'{context}'              => esc_attr( $context ),
			/* translators: %s: User display name */
			'{header_title}'         => esc_html( sprintf( __( 'Profile History: %s', 'nobloat-user-foundry' ), $user->display_name ) ),
			'{header_description}'   => esc_html__( 'Complete timeline of all profile changes. Click any version to view details or compare changes.', 'nobloat-user-foundry' ),
			'{loading_text}'         => esc_html__( 'Loading version history...', 'nobloat-user-foundry' ),
			'{prev_button}'          => esc_html__( '« Previous', 'nobloat-user-foundry' ),
			'{page_info}'            => esc_html__( 'Page 1', 'nobloat-user-foundry' ),
			'{next_button}'          => esc_html__( 'Next »', 'nobloat-user-foundry' ),
			'{empty_title}'          => esc_html__( 'No version history found.', 'nobloat-user-foundry' ),
			'{empty_description}'    => esc_html__( 'This user has no recorded profile changes yet. Changes will appear here as the user updates their profile.', 'nobloat-user-foundry' ),
			'{diff_modal_title}'     => esc_html__( 'Version Comparison', 'nobloat-user-foundry' ),
			'{close_button}'         => esc_html__( '× Close', 'nobloat-user-foundry' ),
			'{comparing_text}'       => esc_html__( 'Comparing versions...', 'nobloat-user-foundry' ),
			'{fields_changed_label}' => esc_html__( 'Fields changed:', 'nobloat-user-foundry' ),
			'{ip_address_label}'     => esc_html__( 'IP Address:', 'nobloat-user-foundry' ),
			'{view_snapshot_button}' => esc_html__( 'View Snapshot', 'nobloat-user-foundry' ),
			'{compare_button}'       => esc_html__( 'Compare Changes', 'nobloat-user-foundry' ),
			'{revert_button}'        => esc_html__( '⟲ Revert to This Version', 'nobloat-user-foundry' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
