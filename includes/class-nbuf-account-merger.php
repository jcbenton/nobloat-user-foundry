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
		add_action( 'admin_init', array( __CLASS__, 'handle_merge_submission' ) );
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

			$accounts[ $user_id ] = array(
				'user_login'    => $user->user_login,
				'user_email'    => $user->user_email,
				'display_name'  => $user->display_name,
				'first_name'    => $user->first_name,
				'last_name'     => $user->last_name,
				'post_count'    => count_user_posts( $user_id ),
				'comment_count' => self::get_user_comment_count( $user_id ),
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
				$value = NBUF_User_Data::get_profile_field( $user_id, $field );
				if ( ! empty( $value ) ) {
					$values[ $user_id ] = $value;
					$unique_values[]    = $value;
				}
			}

			/* Only report as conflict if there are 2+ different non-empty values */
			$unique_values = array_unique( $unique_values );
			if ( count( $unique_values ) > 1 ) {
				$conflicts[] = array(
					'field'  => $field,
					'label'  => $label,
					'values' => $values,
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

		/* Get merge parameters */
		$account_ids      = isset( $_POST['nbuf_merge_accounts'] ) ? array_map( 'intval', (array) $_POST['nbuf_merge_accounts'] ) : array();
		$primary_id       = isset( $_POST['nbuf_primary_account'] ) ? intval( $_POST['nbuf_primary_account'] ) : 0;
		$merge_posts      = isset( $_POST['nbuf_merge_posts'] );
		$merge_comments   = isset( $_POST['nbuf_merge_comments'] );
		$merge_meta       = isset( $_POST['nbuf_merge_meta'] );
		$secondary_action = isset( $_POST['nbuf_secondary_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_secondary_action'] ) ) : 'delete';
		$notify_user      = isset( $_POST['nbuf_notify_user'] );

		/* Validate */
		if ( count( $account_ids ) < 2 || ! $primary_id || ! in_array( $primary_id, $account_ids, true ) ) {
			wp_die( esc_html__( 'Invalid merge parameters', 'nobloat-user-foundry' ) );
		}

		/* Execute merge */
		$result = self::execute_merge(
			array(
				'primary_id'       => $primary_id,
				'account_ids'      => $account_ids,
				'merge_posts'      => $merge_posts,
				'merge_comments'   => $merge_comments,
				'merge_meta'       => $merge_meta,
				'secondary_action' => $secondary_action,
				'notify_user'      => $notify_user,
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
	 */
	public static function execute_merge( $args ) {
		$defaults = array(
			'primary_id'       => 0,
			'account_ids'      => array(),
			'merge_posts'      => true,
			'merge_comments'   => true,
			'merge_meta'       => true,
			'secondary_action' => 'delete',
			'notify_user'      => false,
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

		try {
			/* Consolidate emails */
			self::consolidate_emails( $args['primary_id'], $secondary_ids );

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

			/* Handle secondary accounts */
			foreach ( $secondary_ids as $secondary_id ) {
				if ( 'delete' === $args['secondary_action'] ) {
					include_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $secondary_id, $args['primary_id'] );
				} elseif ( 'disable' === $args['secondary_action'] ) {
					NBUF_User_Data::disable_user( $secondary_id, 'merged' );
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
			$wpdb->query( 'COMMIT' );

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
			$wpdb->query( 'ROLLBACK' );

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

		/* Store in secondary and tertiary email fields */
		if ( isset( $secondary_emails[0] ) ) {
			NBUF_User_Data::update_profile_field( $primary_id, 'secondary_email', $secondary_emails[0] );
		}

		if ( isset( $secondary_emails[1] ) ) {
			NBUF_User_Data::update_profile_field( $primary_id, 'tertiary_email', $secondary_emails[1] );
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
			wp_mail( $email, $subject, $message );
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
