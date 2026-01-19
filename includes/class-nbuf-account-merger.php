<?php
/**
 * Account Merger
 *
 * Handles merging of multiple WordPress user accounts into a single account.
 * Consolidates emails, profile data, posts, comments, and user meta.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Account_Merger
 *
 * Handles merging of multiple user accounts into one.
 */
class NBUF_Account_Merger {


	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'wp_ajax_nbuf_load_merge_accounts', array( __CLASS__, 'ajax_load_accounts' ) );
		add_action( 'wp_ajax_nbuf_search_users', array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'wp_ajax_nbuf_get_user_details', array( __CLASS__, 'ajax_get_user_details' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_merge_submission' ) );
	}

	/**
	 * AJAX handler to search for users
	 */
	public static function ajax_search_users() {
		check_ajax_referer( 'nbuf_merge_accounts', 'nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nobloat-user-foundry' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Search term too short', 'nobloat-user-foundry' ) ) );
		}

		/* Search users by login, email, or display name */
		$users = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'number'         => 10,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
				'user_email'   => $user->user_email,
				'avatar'       => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
				'roles'        => implode( ', ', $user->roles ),
			);
		}

		wp_send_json_success( array( 'users' => $results ) );
	}

	/**
	 * AJAX handler to get full user details for merge comparison
	 */
	public static function ajax_get_user_details() {
		check_ajax_referer( 'nbuf_merge_accounts', 'nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nobloat-user-foundry' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID', 'nobloat-user-foundry' ) ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'User not found', 'nobloat-user-foundry' ) ) );
		}

		/* Get profile photo URL */
		$profile_photo_url = get_avatar_url( $user_id, array( 'size' => 64 ) );
		if ( class_exists( 'NBUF_Profile_Photos' ) ) {
			$custom_photo = NBUF_Profile_Photos::get_profile_photo( $user_id, 64 );
			if ( $custom_photo ) {
				$profile_photo_url = $custom_photo;
			}
		}

		/* WordPress core fields */
		$wp_fields = array(
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'nickname'     => $user->nickname,
			'description'  => $user->description,
			'user_url'     => $user->user_url,
		);

		/* NoBloat extended fields - use field registry for complete list */
		$extended_fields = array();
		$all_field_keys  = NBUF_Profile_Data::get_all_field_keys();

		foreach ( $all_field_keys as $field ) {
			$value                     = NBUF_Profile_Data::get_field( $user_id, $field );
			$extended_fields[ $field ] = $value ? $value : '';
		}

		/* Content counts */
		$post_count    = count_user_posts( $user_id );
		$comment_count = self::get_user_comment_count( $user_id );

		wp_send_json_success(
			array(
				'id'              => $user_id,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'display_name'    => $user->display_name,
				'avatar'          => $profile_photo_url,
				'roles'           => implode( ', ', array_map( 'ucfirst', $user->roles ) ),
				'registered'      => gmdate( 'M j, Y', strtotime( $user->user_registered ) ),
				'wp_fields'       => $wp_fields,
				'extended_fields' => $extended_fields,
				'post_count'      => $post_count,
				'comment_count'   => $comment_count,
			)
		);
	}

	/**
	 * AJAX handler to load account data for merge preview
	 */
	public static function ajax_load_accounts() {
		check_ajax_referer( 'nbuf_merge_load', 'nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'nobloat-user-foundry' ) ) );
		}

		$account_ids = isset( $_POST['accounts'] ) ? array_map( 'intval', (array) $_POST['accounts'] ) : array();

		if ( count( $account_ids ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'At least 2 accounts required', 'nobloat-user-foundry' ) ) );
		}

		$accounts  = array();
		$conflicts = array();

		/* Load account data */
		foreach ( $account_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			/* Get user photos if profile photos feature is enabled */
			$profile_photo_url = null;
			$cover_photo_url   = null;
			if ( class_exists( 'NBUF_Profile_Photos' ) ) {
				$profile_photo_url = NBUF_Profile_Photos::get_profile_photo( $user_id, 150 );
				$cover_photo_url   = NBUF_Profile_Photos::get_cover_photo( $user_id );
			}

			/* Get user data to check for custom uploaded photos */
			$user_data          = NBUF_User_Data::get( $user_id );
			$has_custom_profile = ! empty( $user_data['profile_photo_url'] );
			$has_cover          = ! empty( $user_data['cover_photo_url'] );

			$accounts[ $user_id ] = array(
				'user_login'         => $user->user_login,
				'user_email'         => $user->user_email,
				'display_name'       => $user->display_name,
				'first_name'         => $user->first_name,
				'last_name'          => $user->last_name,
				'post_count'         => count_user_posts( $user_id ),
				'comment_count'      => self::get_user_comment_count( $user_id ),
				'profile_photo_url'  => $profile_photo_url,
				'cover_photo_url'    => $cover_photo_url,
				'has_custom_profile' => $has_custom_profile,
				'has_cover'          => $has_cover,
				'roles'              => $user->roles,
			);
		}

		/* Detect profile field conflicts */
		$conflicts = self::detect_conflicts( $account_ids );

		wp_send_json_success(
			array(
				'accounts'  => $accounts,
				'conflicts' => $conflicts,
			)
		);
	}

	/**
	 * Detect conflicts in profile fields across multiple accounts
	 *
	 * @param  array $account_ids Array of user IDs.
	 * @return array Array of conflicts.
	 */
	private static function detect_conflicts( $account_ids ) {
		global $wpdb;
		$conflicts = array();

		$profile_fields = array(
			'phone'        => __( 'Phone Number', 'nobloat-user-foundry' ),
			'mobile_phone' => __( 'Mobile Phone', 'nobloat-user-foundry' ),
			'work_phone'   => __( 'Work Phone', 'nobloat-user-foundry' ),
			'address'      => __( 'Address', 'nobloat-user-foundry' ),
			'city'         => __( 'City', 'nobloat-user-foundry' ),
			'state'        => __( 'State', 'nobloat-user-foundry' ),
			'postal_code'  => __( 'Postal Code', 'nobloat-user-foundry' ),
			'country'      => __( 'Country', 'nobloat-user-foundry' ),
			'company'      => __( 'Company', 'nobloat-user-foundry' ),
			'job_title'    => __( 'Job Title', 'nobloat-user-foundry' ),
		);

		foreach ( $profile_fields as $field => $label ) {
			$values        = array();
			$unique_values = array();

			foreach ( $account_ids as $user_id ) {
				$value = NBUF_Profile_Data::get_field( $user_id, $field );
				if ( ! empty( $value ) ) {
					$values[ $user_id ] = $value;
					$unique_values[]    = $value;
				}
			}

			/* Only report as conflict if there are 2+ different non-empty values */
			$unique_values = array_unique( $unique_values );
			if ( count( $unique_values ) > 1 ) {
				$conflicts[] = array(
					'type'   => 'profile_field',
					'field'  => $field,
					'label'  => $label,
					'values' => $values,
				);
			}
		}

		/* Detect photo conflicts */
		$photo_conflicts = self::detect_photo_conflicts( $account_ids );
		if ( ! empty( $photo_conflicts ) ) {
			$conflicts = array_merge( $conflicts, $photo_conflicts );
		}

		/* Detect role conflicts */
		$role_conflicts = self::detect_role_conflicts( $account_ids );
		if ( ! empty( $role_conflicts ) ) {
			$conflicts = array_merge( $conflicts, $role_conflicts );
		}

		return $conflicts;
	}

	/**
	 * Detect photo conflicts across multiple accounts
	 *
	 * @param  array $account_ids Array of user IDs.
	 * @return array Array of photo conflicts.
	 */
	private static function detect_photo_conflicts( $account_ids ) {
		$conflicts = array();

		if ( ! class_exists( 'NBUF_Profile_Photos' ) ) {
			return $conflicts;
		}

		/* Check for profile photo conflicts */
		$profile_photos = array();
		foreach ( $account_ids as $user_id ) {
			$user_data = NBUF_User_Data::get( $user_id );
			if ( ! empty( $user_data['profile_photo_url'] ) ) {
				$profile_photos[ $user_id ] = array(
					'url'  => $user_data['profile_photo_url'],
					'path' => $user_data['profile_photo_path'],
				);
			}
		}

		if ( count( $profile_photos ) > 1 ) {
			$conflicts[] = array(
				'type'   => 'photo',
				'field'  => 'profile_photo',
				'label'  => __( 'Profile Photo', 'nobloat-user-foundry' ),
				'values' => $profile_photos,
			);
		}

		/* Check for cover photo conflicts */
		$cover_photos = array();
		foreach ( $account_ids as $user_id ) {
			$user_data = NBUF_User_Data::get( $user_id );
			if ( ! empty( $user_data['cover_photo_url'] ) ) {
				$cover_photos[ $user_id ] = array(
					'url'  => $user_data['cover_photo_url'],
					'path' => $user_data['cover_photo_path'],
				);
			}
		}

		if ( count( $cover_photos ) > 1 ) {
			$conflicts[] = array(
				'type'   => 'photo',
				'field'  => 'cover_photo',
				'label'  => __( 'Cover Photo', 'nobloat-user-foundry' ),
				'values' => $cover_photos,
			);
		}

		return $conflicts;
	}

	/**
	 * Detect role conflicts across multiple accounts
	 *
	 * @param  array $account_ids Array of user IDs.
	 * @return array Array of role conflicts.
	 */
	private static function detect_role_conflicts( $account_ids ) {
		$conflicts  = array();
		$all_roles  = array();
		$role_names = wp_roles()->get_names();

		/* Collect all roles from all accounts */
		foreach ( $account_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user && ! empty( $user->roles ) ) {
				$all_roles[ $user_id ] = $user->roles;
			}
		}

		/* Check if there are role differences */
		if ( count( $all_roles ) > 1 ) {
			$role_sets = array();
			foreach ( $all_roles as $user_id => $roles ) {
				$role_sets[] = implode( ',', $roles );
			}
			$unique_role_sets = array_unique( $role_sets );

			/* Only create conflict if roles are different */
			if ( count( $unique_role_sets ) > 1 ) {
				$conflicts[] = array(
					'type'   => 'role',
					'field'  => 'user_roles',
					'label'  => __( 'User Roles', 'nobloat-user-foundry' ),
					'values' => $all_roles,
					'names'  => $role_names,
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Get comment count for a user
	 *
	 * @param  int $user_id User ID.
	 * @return int Comment count.
	 */
	private static function get_user_comment_count( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Handle merge form submission
	 */
	public static function handle_merge_submission() {
		if ( ! isset( $_POST['nbuf_merge_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'nbuf_merge_accounts', 'nbuf_merge_nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'nobloat-user-foundry' ) );
		}

		/* Get merge parameters - support both old multi-account and new source/target formats */
		$source_id = isset( $_POST['nbuf_source_account'] ) ? intval( $_POST['nbuf_source_account'] ) : 0;
		$target_id = isset( $_POST['nbuf_target_account'] ) ? intval( $_POST['nbuf_target_account'] ) : 0;

		/* New source/target workflow */
		if ( $source_id && $target_id ) {
			$account_ids = array( $target_id, $source_id );
			$primary_id  = $target_id;
		} else {
			/* Legacy multi-account workflow */
			$account_ids = isset( $_POST['nbuf_merge_accounts'] ) ? array_map( 'intval', (array) $_POST['nbuf_merge_accounts'] ) : array();
			$primary_id  = isset( $_POST['nbuf_primary_account'] ) ? intval( $_POST['nbuf_primary_account'] ) : 0;
		}

		$merge_posts         = isset( $_POST['nbuf_merge_posts'] );
		$merge_comments      = isset( $_POST['nbuf_merge_comments'] );
		$merge_meta          = isset( $_POST['nbuf_merge_meta'] );
		$consolidate_emails  = isset( $_POST['nbuf_consolidate_emails'] );
		$notify_user         = isset( $_POST['nbuf_notify_user'] );

		/* Collect field choices - which account's values to keep */
		$field_choices = array();

		/* Collect all field choice selections from POST data */
		foreach ( $_POST as $key => $value ) {
			/* Look for field choice fields (format: nbuf_field_{field}) */
			if ( 0 === strpos( $key, 'nbuf_field_' ) ) {
				$field                 = str_replace( 'nbuf_field_', '', $key );
				$field_choices[ $field ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		/* Validate */
		if ( count( $account_ids ) < 2 || ! $primary_id || ! in_array( $primary_id, $account_ids, true ) ) {
			wp_die( esc_html__( 'Invalid merge parameters', 'nobloat-user-foundry' ) );
		}

		/* Validate source and target are different */
		if ( $source_id && $target_id && $source_id === $target_id ) {
			wp_die( esc_html__( 'Source and target accounts must be different', 'nobloat-user-foundry' ) );
		}

		/* Execute merge */
		$result = self::execute_merge(
			array(
				'primary_id'          => $primary_id,
				'source_id'           => $source_id,
				'account_ids'         => $account_ids,
				'merge_posts'         => $merge_posts,
				'merge_comments'      => $merge_comments,
				'merge_meta'          => $merge_meta,
				'consolidate_emails'  => $consolidate_emails,
				'secondary_action'    => 'delete',
				'notify_user'         => $notify_user,
				'field_choices'       => $field_choices,
			)
		);

		/* Redirect with result */
		if ( $result['success'] ) {
			$redirect = add_query_arg(
				array(
					'page'          => 'nobloat-foundry-users',
					'tab'           => 'tools',
					'subtab'        => 'merge-accounts',
					'merge_success' => 1,
					'merged_count'  => count( $account_ids ) - 1,
				),
				admin_url( 'admin.php' )
			);
		} else {
			$redirect = add_query_arg(
				array(
					'page'          => 'nobloat-foundry-users',
					'tab'           => 'tools',
					'subtab'        => 'merge-accounts',
					'merge_error'   => 1,
					'error_message' => rawurlencode( $result['message'] ),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Execute account merge
	 *
	 * @param  array $args Merge parameters.
	 * @return array Result array with success status and message.
	 * @throws Exception When merge operations fail (caught internally and returned as error).
	 */
	public static function execute_merge( $args ) {
		$defaults = array(
			'primary_id'          => 0,
			'source_id'           => 0,
			'account_ids'         => array(),
			'merge_posts'         => true,
			'merge_comments'      => true,
			'merge_meta'          => true,
			'consolidate_emails'  => true,
			'secondary_action'    => 'delete',
			'notify_user'         => false,
			'field_choices'       => array(),
			'conflict_selections' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		/* Get secondary account IDs (all except primary) */
		$secondary_ids = array_diff( $args['account_ids'], array( $args['primary_id'] ) );

		if ( empty( $secondary_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No secondary accounts to merge', 'nobloat-user-foundry' ),
			);
		}

		/* Validate that all user IDs exist */
		foreach ( array_merge( array( $args['primary_id'] ), $secondary_ids ) as $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
					/* translators: %d: User ID */
						__( 'Invalid user ID: %d. User does not exist.', 'nobloat-user-foundry' ),
						$user_id
					),
				);
			}
		}

		/* Verify transaction support */
		global $wpdb;
		$engine_check = self::verify_transaction_support();
		if ( is_wp_error( $engine_check ) ) {
			return array(
				'success' => false,
				'message' => $engine_check->get_error_message(),
			);
		}

		/*
		 * Start transaction
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction management for atomic account merge operations.
		$wpdb->query( 'START TRANSACTION' );

		/* Track copied files for cleanup on rollback */
		$copied_files = array();

		try {
			/* Apply field choices from new UI (which account's values to keep) */
			if ( ! empty( $args['field_choices'] ) && $args['source_id'] ) {
				self::apply_field_choices( $args['primary_id'], $args['source_id'], $args['field_choices'] );
			}

			/* Consolidate emails (store source email as secondary) */
			if ( $args['consolidate_emails'] ) {
				self::consolidate_emails( $args['primary_id'], $secondary_ids );
			}

			/* Merge posts */
			if ( $args['merge_posts'] ) {
				self::reassign_posts( $args['primary_id'], $secondary_ids );
			}

			/* Merge comments */
			if ( $args['merge_comments'] ) {
				self::reassign_comments( $args['primary_id'], $secondary_ids );
			}

			/* Merge user meta */
			if ( $args['merge_meta'] ) {
				self::merge_user_meta( $args['primary_id'], $secondary_ids );
			}

			/* Handle photo conflicts - track copied files (legacy workflow) */
			if ( ! empty( $args['conflict_selections'] ) ) {
				self::handle_photo_conflicts( $args['primary_id'], $secondary_ids, $args['conflict_selections'], $copied_files );
				self::handle_role_conflicts( $args['primary_id'], $args['conflict_selections'] );
			}

			/* Handle secondary accounts */
			foreach ( $secondary_ids as $secondary_id ) {
				if ( 'delete' === $args['secondary_action'] ) {
					include_once ABSPATH . 'wp-admin/includes/user.php';
					$delete_result = wp_delete_user( $secondary_id, $args['primary_id'] );

					/* wp_delete_user returns true on success, false/WP_Error on failure */
					if ( is_wp_error( $delete_result ) ) {
						throw new Exception( 'Failed to delete secondary user ' . $secondary_id . ': ' . $delete_result->get_error_message() );
					} elseif ( false === $delete_result ) {
						throw new Exception( 'Failed to delete secondary user ' . $secondary_id );
					}
				} elseif ( 'disable' === $args['secondary_action'] ) {
					$disable_result = NBUF_User_Data::disable_user( $secondary_id, 'merged' );

					/* Check if disable operation failed */
					if ( false === $disable_result ) {
						throw new Exception( 'Failed to disable secondary user ' . $secondary_id );
					}
				}
			}

			/* Log merge in audit log */
			NBUF_Audit_Log::log(
				$args['primary_id'],
				'account_merge',
				'success',
				sprintf(
				/* translators: %d: Number of accounts merged */
					__( 'Merged %d accounts into this account', 'nobloat-user-foundry' ),
					count( $secondary_ids )
				),
				array(
					'merged_ids' => $secondary_ids,
					'action'     => $args['secondary_action'],
				)
			);

			/*
			 * Commit transaction
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit transaction for atomic account merge.
			$commit_result = $wpdb->query( 'COMMIT' );

			if ( false === $commit_result ) {
				throw new Exception( 'Transaction commit failed: ' . ( $wpdb->last_error ? $wpdb->last_error : 'Unknown database error' ) );
			}

			/* Send notification */
			if ( $args['notify_user'] ) {
				self::send_merge_notification( $args['primary_id'], $secondary_ids );
			}

			return array(
				'success' => true,
				'message' => sprintf(
				/* translators: %d: Number of accounts merged */
					__( 'Successfully merged %d accounts', 'nobloat-user-foundry' ),
					count( $secondary_ids )
				),
			);

		} catch ( Exception $e ) {
			/*
			 * Rollback on error
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rollback transaction on error.
			$rollback_result = $wpdb->query( 'ROLLBACK' );

			/* Log if rollback itself failed */
			$rollback_failed = false;
			if ( false === $rollback_result ) {
				$rollback_failed = true;
				error_log( '[NoBloat User Foundry] CRITICAL: Transaction rollback failed during account merge. Database may be in inconsistent state. Error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Critical error logging for failed rollback.
			}

			/* Cleanup copied files on rollback */
			foreach ( $copied_files as $file_path ) {
				if ( file_exists( $file_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup copied files on transaction rollback.
					wp_delete_file( $file_path );
				}
			}

			/* Log rollback with file cleanup info */
			if ( class_exists( 'NBUF_Security_Log' ) ) {
				NBUF_Security_Log::log(
					'account_merge_rollback',
					$rollback_failed ? 'critical' : 'warning',
					'Account merge rolled back and cleaned up copied files',
					array(
						'primary_id'      => $args['primary_id'],
						'secondary_ids'   => $secondary_ids,
						'error'           => $e->getMessage(),
						'files_cleaned'   => count( $copied_files ),
						'cleaned_files'   => $copied_files,
						'rollback_failed' => $rollback_failed,
						'db_error'        => $rollback_failed ? $wpdb->last_error : null,
					)
				);
			}

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Consolidate email addresses from secondary accounts into primary
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param array $secondary_ids Secondary user IDs.
	 */
	private static function consolidate_emails( $primary_id, $secondary_ids ) {
		$primary_user  = get_userdata( $primary_id );
		$primary_email = $primary_user->user_email;

		$secondary_emails = array();

		/* Collect all unique emails from secondary accounts */
		foreach ( $secondary_ids as $secondary_id ) {
			$user = get_userdata( $secondary_id );
			if ( $user && $user->user_email !== $primary_email ) {
				$secondary_emails[] = $user->user_email;
			}
		}

		/* Remove duplicates */
		$secondary_emails = array_unique( $secondary_emails );

		/* Validate and store in secondary and tertiary email fields */
		if ( isset( $secondary_emails[0] ) && is_email( $secondary_emails[0] ) ) {
			NBUF_Profile_Data::update( $primary_id, array( 'secondary_email' => $secondary_emails[0] ) );
		}

		if ( isset( $secondary_emails[1] ) && is_email( $secondary_emails[1] ) ) {
			NBUF_Profile_Data::update( $primary_id, array( 'tertiary_email' => $secondary_emails[1] ) );
		}
	}

	/**
	 * Apply field choices from new merge UI
	 *
	 * Applies the selected field values to the target (primary) account based on admin's choices.
	 *
	 * @param int   $target_id     Target user ID (account being kept).
	 * @param int   $source_id     Source user ID (account being merged).
	 * @param array $field_choices Array of field => 'source' or 'target'.
	 */
	private static function apply_field_choices( $target_id, $source_id, $field_choices ) {
		$target_user = get_userdata( $target_id );
		$source_user = get_userdata( $source_id );

		if ( ! $target_user || ! $source_user ) {
			return;
		}

		/* WordPress core fields that can be updated */
		$wp_fields = array( 'display_name', 'first_name', 'last_name', 'nickname', 'description', 'user_url' );

		/* Extended NoBloat fields - use field registry for complete list */
		$extended_fields = NBUF_Profile_Data::get_all_field_keys();

		/* Process each field choice */
		foreach ( $field_choices as $field => $choice ) {
			/* Skip if choice is 'target' - no change needed */
			if ( 'target' === $choice ) {
				continue;
			}

			/* Only process if choice is 'source' */
			if ( 'source' !== $choice ) {
				continue;
			}

			/* Handle WordPress core fields */
			if ( in_array( $field, $wp_fields, true ) ) {
				$source_value = '';
				switch ( $field ) {
					case 'display_name':
						$source_value = $source_user->display_name;
						break;
					case 'first_name':
						$source_value = $source_user->first_name;
						break;
					case 'last_name':
						$source_value = $source_user->last_name;
						break;
					case 'nickname':
						$source_value = $source_user->nickname;
						break;
					case 'description':
						$source_value = $source_user->description;
						break;
					case 'user_url':
						$source_value = $source_user->user_url;
						break;
				}

				/* Update target user with source value */
				if ( '' !== $source_value || 'description' === $field || 'user_url' === $field ) {
					wp_update_user(
						array(
							'ID'   => $target_id,
							$field => $source_value,
						)
					);
				}
			}

			/* Handle extended NoBloat fields */
			if ( in_array( $field, $extended_fields, true ) ) {
				$source_value = NBUF_Profile_Data::get_field( $source_id, $field );
				if ( $source_value ) {
					NBUF_Profile_Data::update( $target_id, array( $field => $source_value ) );
				}
			}
		}

		/* Log field choices applied */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			$source_chosen = array_keys( array_filter( $field_choices, function( $v ) { return 'source' === $v; } ) );
			if ( ! empty( $source_chosen ) ) {
				NBUF_Audit_Log::log(
					$target_id,
					'account_merge_fields',
					'success',
					sprintf(
						/* translators: %s: Comma-separated list of field names */
						__( 'Applied source account values for fields: %s', 'nobloat-user-foundry' ),
						implode( ', ', $source_chosen )
					),
					array(
						'source_id'      => $source_id,
						'fields_applied' => $source_chosen,
					)
				);
			}
		}
	}

	/**
	 * Reassign all posts from secondary accounts to primary
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param array $secondary_ids Secondary user IDs.
	 */
	private static function reassign_posts( $primary_id, $secondary_ids ) {
		global $wpdb;

		foreach ( $secondary_ids as $secondary_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$wpdb->update(
				$wpdb->posts,
				array( 'post_author' => $primary_id ),
				array( 'post_author' => $secondary_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Reassign all comments from secondary accounts to primary
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param array $secondary_ids Secondary user IDs.
	 */
	private static function reassign_comments( $primary_id, $secondary_ids ) {
		global $wpdb;

		$primary_user = get_userdata( $primary_id );

		foreach ( $secondary_ids as $secondary_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$wpdb->update(
				$wpdb->comments,
				array(
					'user_id'              => $primary_id,
					'comment_author'       => $primary_user->display_name,
					'comment_author_email' => $primary_user->user_email,
				),
				array( 'user_id' => $secondary_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Merge user meta from secondary accounts to primary
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param array $secondary_ids Secondary user IDs.
	 */
	private static function merge_user_meta( $primary_id, $secondary_ids ) {
		/* Skip WordPress core and sensitive meta keys */
		$skip_meta_keys = array(
			'capabilities',
			'user_level',
			'session_tokens',
			'wp_capabilities',
			'wp_user_level',
			'dismissed_wp_pointers',
		);

		foreach ( $secondary_ids as $secondary_id ) {
			$meta_keys = get_user_meta( $secondary_id );

			foreach ( $meta_keys as $meta_key => $meta_values ) {
				/* Skip core keys and keys that already exist on primary */
				if ( in_array( $meta_key, $skip_meta_keys, true ) ) {
					continue;
				}

				if ( metadata_exists( 'user', $primary_id, $meta_key ) ) {
					continue;
				}

				/* Copy meta to primary account */
				foreach ( $meta_values as $meta_value ) {
					add_user_meta( $primary_id, $meta_key, maybe_unserialize( $meta_value ) );
				}
			}
		}
	}

	/**
	 * Send merge notification email
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param array $secondary_ids Secondary user IDs.
	 */
	private static function send_merge_notification( $primary_id, $secondary_ids ) {
		$primary_user = get_userdata( $primary_id );

		$subject = sprintf(
		/* translators: %s: Site name */
			__( '[%s] Your accounts have been merged', 'nobloat-user-foundry' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
		/* translators: 1: Display name, 2: Site name, 3: Number of merged accounts, 4: Username, 5: Email, 6: Login URL */
			__(
				'Hello %1$s,

Your accounts on %2$s have been merged.

%3$d user accounts have been combined into a single account.

Your new login credentials:
Username: %4$s
Email: %5$s

You can log in here: %6$s

If you did not request this merge or have questions, please contact the site administrator.',
				'nobloat-user-foundry'
			),
			$primary_user->display_name,
			get_bloginfo( 'name' ),
			count( $secondary_ids ) + 1,
			$primary_user->user_login,
			$primary_user->user_email,
			wp_login_url()
		);

		/* Send to all email addresses */
		$all_emails = array( $primary_user->user_email );

		foreach ( $secondary_ids as $secondary_id ) {
			$user = get_userdata( $secondary_id );
			if ( $user ) {
				$all_emails[] = $user->user_email;
			}
		}

		$all_emails = array_unique( $all_emails );

		foreach ( $all_emails as $email ) {
			NBUF_Email::send( $email, $subject, $message );
		}
	}

	/**
	 * Verify database transaction support
	 *
	 * Checks if critical WordPress tables use InnoDB engine for transaction support.
	 * MyISAM tables silently ignore transactions, which could lead to data corruption.
	 *
	 * @return true|WP_Error True if transactions are supported, WP_Error otherwise.
	 */
	private static function verify_transaction_support() {
		global $wpdb;

		/* Tables that need transaction support for account merging */
		$critical_tables = array(
			$wpdb->users,
			$wpdb->usermeta,
			$wpdb->posts,
			$wpdb->comments,
		);

		foreach ( $critical_tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
			$engine = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$table
				)
			);

			if ( ! $engine || strtolower( $engine ) !== 'innodb' ) {
				return new WP_Error(
					'transaction_not_supported',
					sprintf(
					/* translators: %s: Table name */
						__( 'Transaction support not available. Table %s does not use InnoDB storage engine. Account merging requires InnoDB for data integrity.', 'nobloat-user-foundry' ),
						$table
					)
				);
			}
		}

		return true;
	}

	/**
	 * Handle photo conflicts during merge
	 *
	 * Processes user photo selections from conflict resolution.
	 * Copies selected photos to primary account or deletes all if requested.
	 *
	 * @param int   $primary_id          Primary user ID.
	 * @param array $secondary_ids       Secondary user IDs.
	 * @param array $conflict_selections Conflict resolution selections.
	 * @param array &$copied_files       Array to track copied files for rollback cleanup (passed by reference).
	 */
	private static function handle_photo_conflicts( $primary_id, $secondary_ids, $conflict_selections, &$copied_files = array() ) {
		if ( ! class_exists( 'NBUF_Profile_Photos' ) || ! class_exists( 'NBUF_Image_Processor' ) ) {
			return;
		}

		$all_user_ids = array_merge( array( $primary_id ), $secondary_ids );

		/* Handle profile photo conflict */
		if ( isset( $conflict_selections['profile_photo'] ) ) {
			$selected_user_id = sanitize_text_field( $conflict_selections['profile_photo'] );

			/* Validate selection is one of the accounts being merged OR delete_all */
			$valid_selections = array_merge( array( 'delete_all' ), array_map( 'strval', $all_user_ids ) );
			if ( ! in_array( $selected_user_id, $valid_selections, true ) ) {
				NBUF_Security_Log::log(
					'invalid_photo_selection',
					'warning',
					'Invalid profile photo selection during account merge - skipping profile photo',
					array(
						'selected_value'   => $selected_user_id,
						'valid_selections' => $valid_selections,
						'merge_user_ids'   => $all_user_ids,
						'photo_type'       => 'profile',
						'operation'        => 'account_merge',
					)
				);
				/* Skip profile photo but continue to cover photo processing */
			} elseif ( 'delete_all' === $selected_user_id ) {
				/* Validation passed - delete all profile photos */
				foreach ( $all_user_ids as $user_id ) {
					NBUF_Image_Processor::delete_photo( $user_id, NBUF_Image_Processor::TYPE_PROFILE );
				}
				/* Clear profile photo from primary user data */
				$user_data = NBUF_User_Data::get( $primary_id );
				unset( $user_data['profile_photo_url'] );
				unset( $user_data['profile_photo_path'] );
				NBUF_User_Data::update( $primary_id, $user_data );

				/* Log deletion to audit trail */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						$primary_id,
						'photo_delete',
						'success',
						'All profile photos deleted during account merge',
						array(
							'photo_type' => 'profile',
							'delete_all' => true,
							'user_count' => count( $all_user_ids ),
						)
					);
				}
			} else {
				/* Validation passed - copy selected profile photo */
				$selected_user_id = intval( $selected_user_id );

				/* If selected photo is not from primary account, copy it */
				if ( $selected_user_id !== $primary_id && in_array( $selected_user_id, $all_user_ids, true ) ) {
					$source_user_data = NBUF_User_Data::get( $selected_user_id );
					if ( ! empty( $source_user_data['profile_photo_path'] ) ) {
						/* SECURITY: Validate source photo path */
						$source_validation          = NBUF_Profile_Photos::validate_photo_path( $source_user_data['profile_photo_path'], $selected_user_id );
						$profile_photo_copy_allowed = true;

						if ( is_wp_error( $source_validation ) ) {
							NBUF_Security_Log::log(
								'file_validation_failed',
								'warning',
								'Invalid source photo path during account merge - skipping profile photo',
								array(
									'user_id'       => $selected_user_id,
									'photo_type'    => 'profile',
									'file_path'     => $source_user_data['profile_photo_path'],
									'error_code'    => $source_validation->get_error_code(),
									'error_message' => $source_validation->get_error_message(),
									'operation'     => 'account_merge',
								)
							);
								$profile_photo_copy_allowed = false;
						}

							/* Get destination directory for primary user */
							$dest_dir = NBUF_Image_Processor::get_upload_directory( $primary_id );
						if ( $profile_photo_copy_allowed && ! is_wp_error( $dest_dir ) ) {
							/*
							 * SECURITY: Validate file immediately before copy to minimize TOCTOU window
							 * This prevents race conditions where file could be replaced between validation and use
							 */
							$source_path = realpath( $source_user_data['profile_photo_path'] );
							if ( ! $source_path || ! file_exists( $source_path ) || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
								NBUF_Security_Log::log(
									'file_not_found',
									'warning',
									'Source photo file does not exist or is not readable during account merge - skipping profile photo',
									array(
										'user_id'    => $selected_user_id,
										'photo_type' => 'profile',
										'file_path'  => $source_user_data['profile_photo_path'],
										'operation'  => 'account_merge',
									)
								);
								$profile_photo_copy_allowed = false;
							}

							/* Verify MIME type immediately before copy to prevent malicious file replacement */
							if ( $profile_photo_copy_allowed && function_exists( 'finfo_open' ) ) {
								$finfo         = finfo_open( FILEINFO_MIME_TYPE );
								$mime          = finfo_file( $finfo, $source_path );
								$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
								finfo_close( $finfo );

								if ( ! in_array( $mime, $allowed_mimes, true ) ) {
									NBUF_Security_Log::log(
										'invalid_mime_type',
										'critical',
										'File type changed or is invalid during account merge - skipping profile photo',
										array(
											'user_id'    => $selected_user_id,
											'photo_type' => 'profile',
											'detected_mime' => $mime,
											'file_path'  => $source_path,
											'operation'  => 'account_merge',
										)
									);
									$profile_photo_copy_allowed = false;
								}
							}

							/* Only proceed with copy if all validations passed */
							if ( $profile_photo_copy_allowed ) {

								/* Copy photo to primary user's directory */
								$filename  = basename( $source_path );
								$dest_path = $dest_dir['path'] . $filename;

								/*
								* Use direct copy() instead of WP_Filesystem API because:
								* 1. We need synchronous file operations within database transaction
								* 2. Paths are fully validated and within controlled upload directories
								* 3. WP_Filesystem requires additional credential handling in admin context
								*/
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct file operation required for transactional photo merging with validated paths.
								if ( copy( $source_path, $dest_path ) ) {
									/* Track copied file for rollback cleanup */
									$copied_files[] = $dest_path;

									/* Verify file integrity after copy using SHA-256 (cryptographically secure) */
									$source_hash = hash_file( 'sha256', $source_path );
									$dest_hash   = hash_file( 'sha256', $dest_path );

									if ( $source_hash === $dest_hash ) {
										/* Update primary user's profile photo */
										$user_data                       = NBUF_User_Data::get( $primary_id );
										$user_data['profile_photo_url']  = $dest_dir['url'] . $filename;
										$user_data['profile_photo_path'] = $dest_path;
										NBUF_User_Data::update( $primary_id, $user_data );

										/* Log photo copy to audit trail */
										if ( class_exists( 'NBUF_Audit_Log' ) ) {
											NBUF_Audit_Log::log(
												$primary_id,
												'photo_merge',
												'success',
												'Profile photo copied during account merge',
												array(
													'photo_type'     => 'profile',
													'source_user_id' => $selected_user_id,
												)
											);
										}
									} else {
										/* Hash mismatch - copy failed or corrupted */
										wp_delete_file( $dest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup failed copy.
										NBUF_Security_Log::log(
											'file_integrity_failed',
											'critical',
											'File copy verification failed during account merge - SHA-256 hash mismatch',
											array(
												'user_id' => $selected_user_id,
												'photo_type' => 'profile',
												'source_path' => $source_path,
												'dest_path' => $dest_path,
												'source_hash' => $source_hash,
												'dest_hash' => $dest_hash,
												'operation' => 'account_merge',
											)
										);
									}
								} else {
									NBUF_Security_Log::log(
										'file_copy_failed',
										'warning',
										'Failed to copy profile photo during account merge',
										array(
											'source_user_id' => $selected_user_id,
											'target_user_id' => $primary_id,
											'photo_type'  => 'profile',
											'source_path' => $source_path,
											'dest_path'   => $dest_path,
											'operation'   => 'account_merge',
										)
									);
								}
							}
						}
					}

					/* Delete profile photos from all non-selected accounts */
					foreach ( $all_user_ids as $user_id ) {
						if ( $user_id !== $selected_user_id ) {
							NBUF_Image_Processor::delete_photo( $user_id, NBUF_Image_Processor::TYPE_PROFILE );
						}
					}
				}
			}
		}

		/* Handle cover photo conflict */
		if ( isset( $conflict_selections['cover_photo'] ) ) {
			$selected_user_id = sanitize_text_field( $conflict_selections['cover_photo'] );

			/* Validate selection is one of the accounts being merged OR delete_all */
			$valid_selections = array_merge( array( 'delete_all' ), array_map( 'strval', $all_user_ids ) );
			if ( ! in_array( $selected_user_id, $valid_selections, true ) ) {
				NBUF_Security_Log::log(
					'invalid_photo_selection',
					'warning',
					'Invalid cover photo selection during account merge - skipping cover photo',
					array(
						'selected_value'   => $selected_user_id,
						'valid_selections' => $valid_selections,
						'merge_user_ids'   => $all_user_ids,
						'photo_type'       => 'cover',
						'operation'        => 'account_merge',
					)
				);
				/* Skip cover photo - validation failed */
			} elseif ( 'delete_all' === $selected_user_id ) {
				/* Validation passed - delete all cover photos */
				foreach ( $all_user_ids as $user_id ) {
					NBUF_Image_Processor::delete_photo( $user_id, NBUF_Image_Processor::TYPE_COVER );
				}
				/* Clear cover photo from primary user data */
				$user_data = NBUF_User_Data::get( $primary_id );
				unset( $user_data['cover_photo_url'] );
				unset( $user_data['cover_photo_path'] );
				NBUF_User_Data::update( $primary_id, $user_data );

				/* Log deletion to audit trail */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						$primary_id,
						'photo_delete',
						'success',
						'All cover photos deleted during account merge',
						array(
							'photo_type' => 'cover',
							'delete_all' => true,
							'user_count' => count( $all_user_ids ),
						)
					);
				}
			} else {
				/* Validation passed - copy selected cover photo */
				$selected_user_id = intval( $selected_user_id );

				/* If selected photo is not from primary account, copy it */
				if ( $selected_user_id !== $primary_id && in_array( $selected_user_id, $all_user_ids, true ) ) {
					$source_user_data = NBUF_User_Data::get( $selected_user_id );
					if ( ! empty( $source_user_data['cover_photo_path'] ) ) {
						/* SECURITY: Validate source photo path */
						$source_validation        = NBUF_Profile_Photos::validate_photo_path( $source_user_data['cover_photo_path'], $selected_user_id );
						$cover_photo_copy_allowed = true;

						if ( is_wp_error( $source_validation ) ) {
							NBUF_Security_Log::log(
								'file_validation_failed',
								'warning',
								'Invalid source photo path during account merge - skipping cover photo',
								array(
									'user_id'       => $selected_user_id,
									'photo_type'    => 'cover',
									'file_path'     => $source_user_data['cover_photo_path'],
									'error_code'    => $source_validation->get_error_code(),
									'error_message' => $source_validation->get_error_message(),
									'operation'     => 'account_merge',
								)
							);
							$cover_photo_copy_allowed = false;
						}

						/* Get destination directory for primary user */
						$dest_dir = NBUF_Image_Processor::get_upload_directory( $primary_id );
						if ( $cover_photo_copy_allowed && ! is_wp_error( $dest_dir ) ) {
							/*
							 * SECURITY: Validate file immediately before copy to minimize TOCTOU window
							 * This prevents race conditions where file could be replaced between validation and use
							 */
							$source_path = realpath( $source_user_data['cover_photo_path'] );
							if ( ! $source_path || ! file_exists( $source_path ) || ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
								NBUF_Security_Log::log(
									'file_not_found',
									'warning',
									'Source photo file does not exist or is not readable during account merge - skipping cover photo',
									array(
										'user_id'    => $selected_user_id,
										'photo_type' => 'cover',
										'file_path'  => $source_user_data['cover_photo_path'],
										'operation'  => 'account_merge',
									)
								);
								$cover_photo_copy_allowed = false;
							}

							/* Verify MIME type immediately before copy to prevent malicious file replacement */
							if ( $cover_photo_copy_allowed && function_exists( 'finfo_open' ) ) {
								$finfo         = finfo_open( FILEINFO_MIME_TYPE );
								$mime          = finfo_file( $finfo, $source_path );
								$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
								finfo_close( $finfo );

								if ( ! in_array( $mime, $allowed_mimes, true ) ) {
									NBUF_Security_Log::log(
										'invalid_mime_type',
										'critical',
										'File type changed or is invalid during account merge - skipping cover photo',
										array(
											'user_id'    => $selected_user_id,
											'photo_type' => 'cover',
											'detected_mime' => $mime,
											'file_path'  => $source_path,
											'operation'  => 'account_merge',
										)
									);
									$cover_photo_copy_allowed = false;
								}
							}

							/* Only proceed with copy if all validations passed */
							if ( $cover_photo_copy_allowed ) {

								/* Copy photo to primary user's directory */
								$filename  = basename( $source_path );
								$dest_path = $dest_dir['path'] . $filename;

								/*
								* Use direct copy() instead of WP_Filesystem API because:
								* 1. We need synchronous file operations within database transaction
								* 2. Paths are fully validated and within controlled upload directories
								* 3. WP_Filesystem requires additional credential handling in admin context
								*/
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Direct file operation required for transactional photo merging with validated paths.
								if ( copy( $source_path, $dest_path ) ) {
									/* Track copied file for rollback cleanup */
									$copied_files[] = $dest_path;

									/* Verify file integrity after copy using SHA-256 (cryptographically secure) */
									$source_hash = hash_file( 'sha256', $source_path );
									$dest_hash   = hash_file( 'sha256', $dest_path );

									if ( $source_hash === $dest_hash ) {
										/* Update primary user's cover photo */
										$user_data                     = NBUF_User_Data::get( $primary_id );
										$user_data['cover_photo_url']  = $dest_dir['url'] . $filename;
										$user_data['cover_photo_path'] = $dest_path;
										NBUF_User_Data::update( $primary_id, $user_data );

										/* Log photo copy to audit trail */
										if ( class_exists( 'NBUF_Audit_Log' ) ) {
											NBUF_Audit_Log::log(
												$primary_id,
												'photo_merge',
												'success',
												'Cover photo copied during account merge',
												array(
													'photo_type'     => 'cover',
													'source_user_id' => $selected_user_id,
												)
											);
										}
									} else {
										/* Hash mismatch - copy failed or corrupted */
										wp_delete_file( $dest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup failed copy.
										NBUF_Security_Log::log(
											'file_integrity_failed',
											'critical',
											'File copy verification failed during account merge - SHA-256 hash mismatch',
											array(
												'user_id' => $selected_user_id,
												'photo_type' => 'cover',
												'source_path' => $source_path,
												'dest_path' => $dest_path,
												'source_hash' => $source_hash,
												'dest_hash' => $dest_hash,
												'operation' => 'account_merge',
											)
										);
									}
								} else {
									NBUF_Security_Log::log(
										'file_copy_failed',
										'warning',
										'Failed to copy cover photo during account merge',
										array(
											'source_user_id' => $selected_user_id,
											'target_user_id' => $primary_id,
											'photo_type'  => 'cover',
											'source_path' => $source_path,
											'dest_path'   => $dest_path,
											'operation'   => 'account_merge',
										)
									);
								}
							}
						}
					}

					/* Delete cover photos from all non-selected accounts */
					foreach ( $all_user_ids as $user_id ) {
						if ( $user_id !== $selected_user_id ) {
							NBUF_Image_Processor::delete_photo( $user_id, NBUF_Image_Processor::TYPE_COVER );
						}
					}
				}
			}
		}
	}

	/**
	 * Handle role conflicts during merge
	 *
	 * Assigns selected roles to primary account based on conflict resolution.
	 * Includes security checks to prevent privilege escalation.
	 *
	 * @param int   $primary_id          Primary user ID.
	 * @param array $conflict_selections Conflict resolution selections.
	 */
	private static function handle_role_conflicts( $primary_id, $conflict_selections ) {
		if ( ! isset( $conflict_selections['user_roles'] ) ) {
			return;
		}

		$selected_user_id = intval( $conflict_selections['user_roles'] );
		if ( $selected_user_id <= 0 ) {
			return;
		}

		/* Get current user performing the merge */
		$current_user = wp_get_current_user();

		/* Get roles from selected user */
		$selected_user = get_userdata( $selected_user_id );
		if ( ! $selected_user || empty( $selected_user->roles ) ) {
			return;
		}

		/* Get primary user */
		$primary_user = get_userdata( $primary_id );
		if ( ! $primary_user ) {
			return;
		}

		/* SECURITY: UNIFIED privilege escalation check */
		$selected_highest_role = self::get_highest_role( $selected_user );
		$current_highest_role  = self::get_highest_role( $current_user );
		$role_comparison       = self::compare_role_level( $selected_highest_role, $current_highest_role );

		/*
		 * Block role assignment if trying to assign equal or higher role without proper capability.
		 * This prevents:
		 * 1. Non-admins from assigning administrator role
		 * 2. Editors from assigning editor or admin roles to themselves
		 * 3. Any privilege escalation attacks through account merging
		 */
		if ( $role_comparison >= 0 && ! current_user_can( 'promote_users' ) ) {
			NBUF_Security_Log::log(
				'privilege_escalation_blocked',
				'critical',
				'Attempted to assign equal or higher role during account merge',
				array(
					'current_user_id' => $current_user->ID,
					'current_role'    => $current_highest_role,
					'attempted_role'  => $selected_highest_role,
					'target_user_id'  => $primary_id,
					'source_user_id'  => $selected_user_id,
					'role_comparison' => $role_comparison,
					'has_promote'     => current_user_can( 'promote_users' ),
					'operation'       => 'account_merge',
				)
			);

			/* Log attempted privilege escalation in audit log */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::log(
					$current_user->ID,
					'security_violation',
					'blocked',
					'Attempted privilege escalation during account merge',
					array(
						'attempted_role' => $selected_highest_role,
						'current_role'   => $current_highest_role,
						'target_user_id' => $primary_id,
						'source_user_id' => $selected_user_id,
					)
				);
			}
			return;
		}

		/* Only update roles if they're different */
		if ( $selected_user_id !== $primary_id ) {
			/* Remove all current roles from primary user */
			foreach ( $primary_user->roles as $role ) {
				$primary_user->remove_role( $role );
			}

			/* Add selected user's roles to primary user */
			foreach ( $selected_user->roles as $role ) {
				$primary_user->add_role( $role );
			}

			/* Log role change in audit log */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				NBUF_Audit_Log::log(
					$primary_id,
					'role_change',
					'success',
					'User roles updated during account merge',
					array(
						'new_roles'      => $selected_user->roles,
						'source_user_id' => $selected_user_id,
						'performed_by'   => $current_user->ID,
						'performer_role' => $current_highest_role,
					)
				);
			}
		}
	}

	/**
	 * Get highest priority role for user
	 *
	 * @param WP_User $user User object.
	 * @return string Role slug.
	 */
	private static function get_highest_role( $user ) {
		/**
		 * Filter: Allow sites to define custom role hierarchy
		 *
		 * Enables support for custom roles (e.g., WooCommerce shop_manager, membership roles).
		 * Add custom roles with appropriate hierarchy levels.
		 *
		 * @param array $hierarchy Role hierarchy with role slug => priority level.
		 */
		$role_hierarchy = apply_filters(
			'nbuf_account_merger_role_hierarchy',
			array(
				'administrator' => 100,
				'editor'        => 80,
				'author'        => 60,
				'contributor'   => 40,
				'subscriber'    => 20,
			)
		);

		$highest_level = 0;
		$highest_role  = 'subscriber';

		foreach ( $user->roles as $role ) {
			$level = isset( $role_hierarchy[ $role ] ) ? $role_hierarchy[ $role ] : 0;
			if ( $level > $highest_level ) {
				$highest_level = $level;
				$highest_role  = $role;
			}
		}

		return $highest_role;
	}

	/**
	 * Compare role levels
	 *
	 * @param string $role1 First role slug.
	 * @param string $role2 Second role slug.
	 * @return int -1 if role1 < role2, 0 if equal, 1 if role1 > role2.
	 */
	private static function compare_role_level( $role1, $role2 ) {
		/** This filter is documented in class-nbuf-account-merger.php */
		$role_hierarchy = apply_filters(
			'nbuf_account_merger_role_hierarchy',
			array(
				'administrator' => 100,
				'editor'        => 80,
				'author'        => 60,
				'contributor'   => 40,
				'subscriber'    => 20,
			)
		);

		$level1 = isset( $role_hierarchy[ $role1 ] ) ? $role_hierarchy[ $role1 ] : 0;
		$level2 = isset( $role_hierarchy[ $role2 ] ) ? $role_hierarchy[ $role2 ] : 0;

		if ( $level1 < $level2 ) {
			return -1;
		} elseif ( $level1 > $level2 ) {
			return 1;
		}
		return 0;
	}
}
