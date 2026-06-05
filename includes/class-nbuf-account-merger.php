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
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_nbuf_search_users', array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'wp_ajax_nbuf_get_user_details', array( __CLASS__, 'ajax_get_user_details' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_merge_submission' ) );
	}

	/**
	 * AJAX handler to search for users
	 *
	 * @return void
	 */
	public static function ajax_search_users(): void {
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
	 *
	 * @return void
	 */
	public static function ajax_get_user_details(): void {
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
	 *
	 * @return void
	 */
	public static function handle_merge_submission(): void {
		if ( ! isset( $_POST['nbuf_merge_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'nbuf_merge_accounts', 'nbuf_merge_nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'nobloat-user-foundry' ) );
		}

		/*
		 * Source/target workflow only. The legacy multi-account format
		 * (nbuf_merge_accounts[] / nbuf_primary_account) was removed: no current
		 * UI renders it, and it bypassed apply_field_choices() (gated on
		 * source_id), so a legacy-format merge silently dropped the secondary
		 * accounts' custom profile-table fields. Requiring source_id + target_id
		 * guarantees the field-consolidation path always runs.
		 */
		$source_id = isset( $_POST['nbuf_source_account'] ) ? intval( $_POST['nbuf_source_account'] ) : 0;
		$target_id = isset( $_POST['nbuf_target_account'] ) ? intval( $_POST['nbuf_target_account'] ) : 0;

		if ( ! $source_id || ! $target_id ) {
			wp_die( esc_html__( 'Invalid merge parameters: a source and a target account are required.', 'nobloat-user-foundry' ) );
		}
		if ( $source_id === $target_id ) {
			wp_die( esc_html__( 'Source and target accounts must be different.', 'nobloat-user-foundry' ) );
		}

		$account_ids = array( $target_id, $source_id );
		$primary_id  = $target_id;

		$merge_posts        = isset( $_POST['nbuf_merge_posts'] );
		$merge_comments     = isset( $_POST['nbuf_merge_comments'] );
		$merge_meta         = isset( $_POST['nbuf_merge_meta'] );
		$consolidate_emails = isset( $_POST['nbuf_consolidate_emails'] );
		$notify_user        = isset( $_POST['nbuf_notify_user'] );

		/* Collect field choices - which account's values to keep */
		$field_choices = array();

		/* Collect all field choice selections from POST data */
		foreach ( $_POST as $key => $value ) {
			/* Look for field choice fields (format: nbuf_field_{field}) */
			if ( 0 === strpos( $key, 'nbuf_field_' ) ) {
				$field                   = str_replace( 'nbuf_field_', '', $key );
				$field_choices[ $field ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		/*
		 * SECURITY: site-wide `delete_users` is not sufficient authorization to
		 * absorb-and-delete an arbitrary account. Enforce the per-target meta
		 * capabilities (edit_user / delete_user, which route through
		 * map_meta_cap) for EVERY account in the merge, and protect super admins
		 * on multisite. Without this a delegated operator with `delete_users`
		 * could merge any user into another and delete the source (IDOR).
		 */
		foreach ( $account_ids as $acct_id ) {
			/* Every account in the merge is modified — require edit_user on each. */
			if ( ! current_user_can( 'edit_user', $acct_id ) ) {
				wp_die( esc_html__( 'You do not have permission to merge one or more of the selected accounts.', 'nobloat-user-foundry' ) );
			}
			/* Non-primary accounts are deleted — require delete_user on those. */
			if ( (int) $acct_id !== (int) $primary_id && ! current_user_can( 'delete_user', $acct_id ) ) {
				wp_die( esc_html__( 'You do not have permission to delete one or more of the selected accounts.', 'nobloat-user-foundry' ) );
			}
			if ( is_multisite() && is_super_admin( $acct_id ) && ! is_super_admin( get_current_user_id() ) ) {
				wp_die( esc_html__( 'You do not have permission to merge a super administrator account.', 'nobloat-user-foundry' ) );
			}
		}

		/* Execute merge */
		$result = self::execute_merge(
			array(
				'primary_id'         => $primary_id,
				'source_id'          => $source_id,
				'account_ids'        => $account_ids,
				'merge_posts'        => $merge_posts,
				'merge_comments'     => $merge_comments,
				'merge_meta'         => $merge_meta,
				'consolidate_emails' => $consolidate_emails,
				'secondary_action'   => 'delete',
				'notify_user'        => $notify_user,
				'field_choices'      => $field_choices,
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
	 * @param  array<string, mixed> $args Merge parameters.
	 * @return array{success: bool, message: string} Result array with success status and message.
	 * @throws Exception When merge operations fail (caught internally and returned as error).
	 */
	public static function execute_merge( array $args ): array {
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

		/* Prevent merging (deleting/disabling) administrator accounts as secondary */
		foreach ( $secondary_ids as $sec_id ) {
			if ( user_can( $sec_id, 'manage_options' ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %d: User ID */
						__( 'Cannot merge administrator account (ID: %d) as a secondary account. Demote the account first.', 'nobloat-user-foundry' ),
						$sec_id
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

			/* Log merge in admin audit log */
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::log(
					get_current_user_id(),
					NBUF_Admin_Audit_Log::EVENT_ACCOUNT_MERGE,
					'success',
					sprintf(
					/* translators: %d: Number of accounts merged */
						__( 'Merged %d accounts into this account', 'nobloat-user-foundry' ),
						count( $secondary_ids )
					),
					$args['primary_id'],
					array(
						'merged_ids' => $secondary_ids,
						'action'     => $args['secondary_action'],
					)
				);
			}

			/*
			 * Commit transaction
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit transaction for atomic account merge.
			$commit_result = $wpdb->query( 'COMMIT' );

			if ( false === $commit_result ) {
				throw new Exception( 'Transaction commit failed: ' . ( $wpdb->last_error ? $wpdb->last_error : 'Unknown database error' ) );
			}

			/*
			 * Delete secondary users AFTER commit so wp_delete_user's own
			 * DB writes are not inside our transaction (they commit independently
			 * and cannot be rolled back).
			 */
			if ( 'delete' === $args['secondary_action'] ) {
				include_once ABSPATH . 'wp-admin/includes/user.php';
				foreach ( $secondary_ids as $secondary_id ) {
					wp_delete_user( $secondary_id, $args['primary_id'] );
				}
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
	 * @param int[] $secondary_ids Secondary user IDs.
	 * @return void
	 */
	private static function consolidate_emails( int $primary_id, array $secondary_ids ): void {
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
	 * @param int                   $target_id     Target user ID (account being kept).
	 * @param int                   $source_id     Source user ID (account being merged).
	 * @param array<string, string> $field_choices Array of field => 'source' or 'target'.
	 * @return void
	 */
	private static function apply_field_choices( int $target_id, int $source_id, array $field_choices ): void {
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
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			$source_chosen = array_keys(
				array_filter(
					$field_choices,
					function ( $v ) {
						return 'source' === $v;
					}
				)
			);
			if ( ! empty( $source_chosen ) ) {
				NBUF_Admin_Audit_Log::log(
					get_current_user_id(),
					'account_merge_fields',
					'success',
					sprintf(
						/* translators: %s: Comma-separated list of field names */
						__( 'Applied source account values for fields: %s', 'nobloat-user-foundry' ),
						implode( ', ', $source_chosen )
					),
					$target_id,
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
	 * @param int[] $secondary_ids Secondary user IDs.
	 * @return void
	 */
	private static function reassign_posts( int $primary_id, array $secondary_ids ): void {
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
	 * @param int[] $secondary_ids Secondary user IDs.
	 * @return void
	 */
	private static function reassign_comments( int $primary_id, array $secondary_ids ): void {
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
	 * @param int[] $secondary_ids Secondary user IDs.
	 * @return void
	 */
	private static function merge_user_meta( int $primary_id, array $secondary_ids ): void {
		global $wpdb;

		/*
		 * SECURITY: allow-list, not deny-list. Only copy known, safe PROFILE
		 * usermeta from the secondary account(s). A deny-list inherently misses
		 * unknown access-granting keys (per-blog `wp_N_capabilities`, plugin
		 * entitlement/role meta, sudo grants, session tokens), any of which —
		 * if copied — silently escalates the surviving account during a routine
		 * admin merge. The allow-list covers the WordPress standard profile
		 * usermeta fields and the user's contact methods; everything else
		 * (including capabilities/user_level) is dropped.
		 *
		 * NOTE: NBUF's CUSTOM profile fields live in the {prefix}nbuf_user_profile
		 * table, NOT usermeta, so they are consolidated by apply_field_choices()
		 * — not here. (They are intentionally absent from this list.)
		 */
		$allowed_meta_keys = array( 'first_name', 'last_name', 'nickname', 'description', 'locale' );
		if ( function_exists( 'wp_get_user_contact_methods' ) ) {
			$allowed_meta_keys = array_merge( $allowed_meta_keys, array_keys( wp_get_user_contact_methods() ) );
		}
		$allowed_meta_keys = array_values( array_unique( array_filter( $allowed_meta_keys ) ) );

		foreach ( $secondary_ids as $secondary_id ) {
			$meta_keys = get_user_meta( $secondary_id );

			foreach ( $meta_keys as $meta_key => $meta_values ) {
				/* Allow-list: skip anything that is not a known profile field. */
				if ( ! in_array( $meta_key, $allowed_meta_keys, true ) ) {
					continue;
				}

				/* Don't overwrite values already present on the primary. */
				if ( metadata_exists( 'user', $primary_id, $meta_key ) ) {
					continue;
				}

				/*
				 * Copy meta to primary account.
				 *
				 * SECURITY: Do not call maybe_unserialize() on the raw value here.
				 * get_user_meta() returns serialized strings, and add_user_meta()
				 * re-serializes via maybe_serialize(). Calling maybe_unserialize()
				 * first would deserialize attacker-influenced usermeta payloads
				 * (POP-gadget surface) for no gain. If the value happens to be a
				 * serialized string, decode it with allowed_classes => false so
				 * no objects can be instantiated.
				 */
				foreach ( $meta_values as $meta_value ) {
					$value_to_copy = $meta_value;
					if ( is_string( $value_to_copy ) && is_serialized( $value_to_copy ) ) {
						$decoded = @unserialize( $value_to_copy, array( 'allowed_classes' => false ) ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Object instantiation disabled; @ suppresses E_NOTICE on malformed input.
						if ( false !== $decoded || 'b:0;' === $value_to_copy ) {
							$value_to_copy = $decoded;
						}
					}
					add_user_meta( $primary_id, $meta_key, $value_to_copy );
				}
			}
		}
	}

	/**
	 * Send merge notification email
	 *
	 * @param int   $primary_id    Primary user ID.
	 * @param int[] $secondary_ids Secondary user IDs.
	 * @return void
	 */
	private static function send_merge_notification( int $primary_id, array $secondary_ids ): void {
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
}
