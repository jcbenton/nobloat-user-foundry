<?php
/**
 * NoBloat User Foundry - Admin User Enhancements
 *
 * Adds:
 * - "Enabled" column to Users list
 * - "Verified" column to Users list
 * - Bulk "Mark as Verified" action
 * - Bulk "Remove Verification" action (ignores admins)
 * - Bulk "Disable User" action (kills sessions)
 * - Bulk "Enable User" action
 * - "Resend Verification" action for unverified users
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin user enhancements class.
 *
 * Manages custom columns, bulk actions, and filters for the Users admin screen.
 */
class NBUF_Admin_Users {


	/**
	 * Initialize admin user enhancements.
	 *
	 * Registers user table columns, bulk actions, and row links.
	 * ONLY if user management system is enabled.
	 */
	public static function init() {
		/* Check if user management system is enabled */
		$system_enabled = NBUF_Options::get( 'nbuf_user_manager_enabled', false );

		/* If disabled, do NOT modify users list or add any hooks */
		if ( ! $system_enabled ) {
			return;
		}

		add_filter( 'manage_users_columns', array( __CLASS__, 'add_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );

		// Add custom filters to users list.
		add_action( 'restrict_manage_users', array( __CLASS__, 'add_user_filters' ) );
		add_filter( 'pre_get_users', array( __CLASS__, 'filter_users_by_status' ) );
		add_action( 'admin_footer', array( __CLASS__, 'add_user_count_filters' ) );

		// Add "Resend Verification" link + handler.
		add_filter( 'user_row_actions', array( __CLASS__, 'add_resend_link' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_resend_verification' ) );

		// Handle account status actions.
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_verify' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_approve_user' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_reject_user' ) );

		// Handle 2FA admin actions.
		add_action( 'admin_init', array( __CLASS__, 'handle_2fa_admin_actions' ) );

		// User profile section.
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_section' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_section' ) );

		// Enqueue scripts for user profile pages.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_user_profile_scripts' ) );

		// New user creation form.
		add_action( 'user_new_form', array( __CLASS__, 'render_new_user_field' ) );
		add_action( 'user_register', array( __CLASS__, 'handle_new_user_verification' ), 5 );

		// Version history meta box (if enabled).
		$vh_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( $vh_enabled ) {
			add_action( 'show_user_profile', array( __CLASS__, 'render_version_history_metabox' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'render_version_history_metabox' ) );
		}
	}

	/**
	 * Enqueue user profile scripts.
	 *
	 * Loads scripts for user profile edit pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_user_profile_scripts( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		/*
		* No external dependencies needed - using native HTML5 datetime-local input.
		* Inline script for basic UI interactions only.
		*/
		wp_add_inline_script(
			'jquery',
			"
		jQuery(document).ready(function($) {
			/* Handle expiration date toggle */
			$('#nbuf_never_expires').on('change', function() {
				if ($(this).is(':checked')) {
					$('#nbuf_expiration_date_wrapper').slideUp();
					$('#nbuf_expires_at_date').val('');
					$('#nbuf_expires_at_time').val('');
				} else {
					$('#nbuf_expiration_date_wrapper').slideDown();
				}
			});
		});
		"
		);

		/* Enqueue version history assets if enabled */
		$vh_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( $vh_enabled ) {
			wp_enqueue_style(
				'nbuf-version-history',
				plugin_dir_url( __DIR__ ) . 'assets/css/admin/version-history.css',
				array(),
				'1.4.0'
			);

			wp_enqueue_script(
				'nbuf-version-history',
				plugin_dir_url( __DIR__ ) . 'assets/js/admin/version-history.js',
				array( 'jquery' ),
				'1.4.0',
				true
			);

			$can_revert = current_user_can( 'manage_options' );

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
		}
	}

	/**
	 * Add custom columns to users table.
	 *
	 * @param  array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_columns( $columns ) {
		$columns['nbuf_enabled']  = __( 'Enabled', 'nobloat-user-foundry' );
		$columns['nbuf_verified'] = __( 'Verified', 'nobloat-user-foundry' );
		$columns['nbuf_expires']  = __( 'Expires', 'nobloat-user-foundry' );
		$columns['nbuf_2fa']      = __( '2FA', 'nobloat-user-foundry' );
		return $columns;
	}

	/**
	 * Render custom column content.
	 *
	 * Handles Enabled and Verified columns. Admins are immutable.
	 *
	 * @param  string $value       Current column value.
	 * @param  string $column_name Column name.
	 * @param  int    $user_id     User ID.
	 * @return string Column content.
	 */
	public static function render_column( $value, $column_name, $user_id ) {
		/* Enabled column - check/X mark */
		if ( 'nbuf_enabled' === $column_name ) {
			$disabled = NBUF_User_Data::is_disabled( $user_id );
			return $disabled ? '&#x2717;' : '&#x2713;';
		}

		/* Verified column */
		if ( 'nbuf_verified' === $column_name ) {
			$user = get_userdata( $user_id );

			/* Admins are always verified - show as Immutable */
			if ( $user && user_can( $user, 'manage_options' ) ) {
				return '<em>' . esc_html__( 'Immutable', 'nobloat-user-foundry' ) . '</em>';
			}

			$user_data = NBUF_User_Data::get( $user_id );

			if ( $user_data && $user_data->is_verified && $user_data->verified_date ) {
				$parts = explode( ' ', $user_data->verified_date );
				$d     = isset( $parts[0] ) ? esc_html( $parts[0] ) : '';
				$t     = isset( $parts[1] ) ? esc_html( $parts[1] ) : '';
				return $d . '<br><span style="color:#666;">' . $t . '</span>';
			}

			return esc_html__( 'Unverified', 'nobloat-user-foundry' );
		}

		/* Expires column */
		if ( 'nbuf_expires' === $column_name ) {
			$user_data = NBUF_User_Data::get( $user_id );
			if ( $user_data && $user_data->expires_at && '0000-00-00 00:00:00' !== $user_data->expires_at ) {
				$expires_timestamp = strtotime( $user_data->expires_at );
				$current_timestamp = time();
				$is_expired        = $expires_timestamp <= $current_timestamp;
				$parts             = explode( ' ', $user_data->expires_at );
				$d                 = isset( $parts[0] ) ? esc_html( $parts[0] ) : '';
				$t                 = isset( $parts[1] ) ? esc_html( $parts[1] ) : '';
				$color             = $is_expired ? '#c0392b' : '#333';
				$output            = '<span style="color:' . $color . ';">' . $d . '<br><span style="color:#666;">' . $t . '</span></span>';
				if ( $is_expired ) {
					$output .= '<br><span style="color:#c0392b;font-weight:bold;">' . esc_html__( 'EXPIRED', 'nobloat-user-foundry' ) . '</span>';
				}
				return $output;
			}
			return '&mdash;';
		}

		/* 2FA column */
		if ( 'nbuf_2fa' === $column_name ) {
			$method = NBUF_User_2FA_Data::get_method( $user_id );

			if ( empty( $method ) || 'disabled' === $method ) {
				return '&mdash;';
			}

			/* Determine icon and title based on method */
			if ( 'email' === $method ) {
				$icon  = 'dashicons-email-alt';
				$title = __( 'Email 2FA', 'nobloat-user-foundry' );
			} elseif ( 'totp' === $method ) {
				$icon  = 'dashicons-smartphone';
				$title = __( 'Authenticator App', 'nobloat-user-foundry' );
			} elseif ( 'both' === $method ) {
				$icon  = 'dashicons-shield';
				$title = __( 'Email + Authenticator', 'nobloat-user-foundry' );
			} else {
				return '&mdash;';
			}

			return sprintf(
				'<span class="dashicons %s" title="%s" style="font-size: 18px;"></span>',
				esc_attr( $icon ),
				esc_attr( $title )
			);
		}

		return $value;
	}

	/**
	 * Add resend verification link for unverified users.
	 *
	 * @param  array   $actions User row actions.
	 * @param  WP_User $user    User object.
	 * @return array Modified actions.
	 */
	public static function add_resend_link( $actions, $user ) {
		/* Don't show for admins */
		if ( user_can( $user, 'manage_options' ) ) {
			return $actions;
		}

		/* Resend verification link for unverified users */
		if ( ! NBUF_User_Data::is_verified( $user->ID ) ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nbuf_resend',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'nbuf_resend_' . $user->ID
			);

			$actions['nbuf_resend'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Resend Verification', 'nobloat-user-foundry' )
			);

			/* Manual verify link */
			$verify_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nbuf_manual_verify',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'nbuf_manual_verify_' . $user->ID
			);

			$actions['nbuf_manual_verify'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $verify_url ),
				esc_html__( 'Manual Verify', 'nobloat-user-foundry' )
			);
		}

		/* Approval actions for users requiring approval */
		if ( NBUF_User_Data::requires_approval( $user->ID ) && ! NBUF_User_Data::is_approved( $user->ID ) ) {
			/* Approve link */
			$approve_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nbuf_approve',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'nbuf_approve_' . $user->ID
			);

			$actions['nbuf_approve'] = sprintf(
				'<a href="%s" style="color:#0a0;">%s</a>',
				esc_url( $approve_url ),
				esc_html__( 'Approve', 'nobloat-user-foundry' )
			);

			/* Reject link */
			$reject_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nbuf_reject',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'nbuf_reject_' . $user->ID
			);

			$actions['nbuf_reject'] = sprintf(
				'<a href="%s" style="color:#c00;">%s</a>',
				esc_url( $reject_url ),
				esc_html__( 'Reject', 'nobloat-user-foundry' )
			);
		}

		/* Reset 2FA link for users with 2FA enabled */
		$twofa_method = get_user_meta( $user->ID, 'nbuf_2fa_method', true );
		if ( ! empty( $twofa_method ) && 'disabled' !== $twofa_method ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'nbuf_reset_user_2fa',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'nbuf_reset_2fa_' . $user->ID
			);

			$actions['nbuf_reset_2fa'] = sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $url ),
				esc_js( __( 'Reset this user\'s 2FA settings? They will need to set up 2FA again.', 'nobloat-user-foundry' ) ),
				esc_html__( 'Reset 2FA', 'nobloat-user-foundry' )
			);
		}

		/* Version History link (if enabled) */
		$vh_enabled = NBUF_Options::get( 'nbuf_version_history_enabled', true );
		if ( $vh_enabled ) {
			$url = add_query_arg(
				array(
					'page'    => 'nobloat-user-foundry-version-history',
					'user_id' => $user->ID,
				),
				admin_url( 'users.php' )
			);

			$actions['nbuf_history'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'History', 'nobloat-user-foundry' )
			);
		}

		return $actions;
	}

	/**
	 * Register custom bulk actions.
	 *
	 * @param  array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public static function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['nbuf_bulk_verify']                = __( 'Mark as Verified', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_unverify']              = __( 'Remove Verification', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_approve']               = __( 'Approve Users', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_reject']                = __( 'Reject Users', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_disable']               = __( 'Disable User', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_enable']                = __( 'Enable User', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_set_expiration']        = __( 'Set Expiration Date', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_remove_expiration']     = __( 'Remove Expiration', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_reset_2fa']             = __( 'Reset 2FA', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_disable_2fa']           = __( 'Disable 2FA', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_force_password_change'] = __( 'Force Password Change', 'nobloat-user-foundry' );
		$bulk_actions['nbuf_bulk_merge_accounts']        = __( 'Merge Accounts', 'nobloat-user-foundry' );
		return $bulk_actions;
	}

	/**
	 * Handle custom bulk actions.
	 *
	 * @param  string $redirect_to Redirect URL.
	 * @param  string $action      Bulk action name.
	 * @param  array  $user_ids    Array of user IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_actions( $redirect_to, $action, $user_ids ) {
		$count = 0;

		/* Mark as Verified */
		if ( 'nbuf_bulk_verify' === $action ) {
			foreach ( $user_ids as $user_id ) {
				NBUF_User_Data::set_verified( $user_id );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_verified', $count, $redirect_to );
		}

		/* Remove Verification - skip admins (immutable) */
		if ( 'nbuf_bulk_unverify' === $action ) {
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				/* Skip admins - verification is immutable */
				if ( $user && user_can( $user, 'manage_options' ) ) {
					continue;
				}
				NBUF_User_Data::set_unverified( $user_id );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_unverified', $count, $redirect_to );
		}

		/* Approve Users - skip admins */
		if ( 'nbuf_bulk_approve' === $action ) {
			$admin_id = get_current_user_id();
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				/* Skip admins */
				if ( $user && user_can( $user, 'manage_options' ) ) {
					continue;
				}
				NBUF_User_Data::approve_user( $user_id, $admin_id, 'Bulk approval by administrator' );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_approved', $count, $redirect_to );
		}

		/* Reject Users - skip admins */
		if ( 'nbuf_bulk_reject' === $action ) {
			$admin_id = get_current_user_id();
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( $user_id );
				/* Skip admins */
				if ( $user && user_can( $user, 'manage_options' ) ) {
					continue;
				}
				NBUF_User_Data::reject_user( $user_id, $admin_id, 'Bulk rejection by administrator' );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_rejected', $count, $redirect_to );
		}

		/* Disable User - set in DB + kill all sessions */
		if ( 'nbuf_bulk_disable' === $action ) {
			foreach ( $user_ids as $user_id ) {
				NBUF_User_Data::set_disabled( $user_id, 'manual' );
				/* Kill all sessions for this user */
				$sessions = WP_Session_Tokens::get_instance( $user_id );
				$sessions->destroy_all();
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_disabled', $count, $redirect_to );
		}

		/* Enable User - update DB */
		if ( 'nbuf_bulk_enable' === $action ) {
			foreach ( $user_ids as $user_id ) {
				NBUF_User_Data::set_enabled( $user_id );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_enabled', $count, $redirect_to );
		}

		/* Set Expiration Date - store user IDs and show modal */
		if ( 'nbuf_bulk_set_expiration' === $action ) {
			/* Store user IDs in transient for processing after date selection */
			set_transient( 'nbuf_bulk_expiration_users', $user_ids, 300 );
			return add_query_arg( 'nbuf_show_expiration_modal', '1', $redirect_to );
		}

		/* Remove Expiration */
		if ( 'nbuf_bulk_remove_expiration' === $action ) {
			foreach ( $user_ids as $user_id ) {
				NBUF_User_Data::set_expiration( $user_id, null );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_expiration_removed', $count, $redirect_to );
		}

		/* Reset 2FA - clear all 2FA data, user must set up again */
		if ( 'nbuf_bulk_reset_2fa' === $action ) {
			foreach ( $user_ids as $user_id ) {
				self::reset_user_2fa( $user_id );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_2fa_reset', $count, $redirect_to );
		}

		/* Disable 2FA - turn off 2FA for selected users */
		if ( 'nbuf_bulk_disable_2fa' === $action ) {
			foreach ( $user_ids as $user_id ) {
				NBUF_User_2FA_Data::disable( $user_id );
				++$count;
			}
			return add_query_arg( 'nbuf_bulk_2fa_disabled', $count, $redirect_to );
		}

		/* Force Password Change - set flag for selected users */
		if ( 'nbuf_bulk_force_password_change' === $action ) {
			if ( class_exists( 'NBUF_Password_Expiration' ) ) {
				foreach ( $user_ids as $user_id ) {
					NBUF_Password_Expiration::force_password_change( $user_id );
					++$count;
				}
				return add_query_arg( 'nbuf_bulk_password_forced', $count, $redirect_to );
			}
		}

		/* Merge Accounts - redirect to merge page with preselected users */
		if ( 'nbuf_bulk_merge_accounts' === $action ) {
			if ( count( $user_ids ) < 2 ) {
				return add_query_arg( 'nbuf_merge_error', 'minimum_users', $redirect_to );
			}

			/* Redirect to merge accounts page with user IDs */
			$merge_url = add_query_arg(
				array(
					'page'      => 'nobloat-foundry-users',
					'tab'       => 'tools',
					'subtab'    => 'merge-accounts',
					'merge_tab' => 'wordpress',
					'users'     => implode( ',', $user_ids ),
				),
				admin_url( 'admin.php' )
			);

			return $merge_url;
		}

		return $redirect_to;
	}

	/**
	 * Reset user 2FA.
	 *
	 * Clears all 2FA data for a user (complete reset).
	 *
	 * @param int $user_id User ID.
	 */
	private static function reset_user_2fa( $user_id ) {
		/* Delete all 2FA data from custom table */
		NBUF_User_2FA_Data::disable( $user_id );

		/*
		* Clear any active 2FA transients - transients expire automatically.
		* No action needed - transients will expire after 5 minutes.
		*/
	}

	/**
	 * Handle resend verification link.
	 *
	 * Resends a verification email for a specific user.
	 */
	public static function handle_resend_verification() {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action verification via capability check.
		if ( ! isset( $_GET['action'] ) || 'nbuf_resend' !== wp_unslash( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action, capability checked below.
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to resend verification emails.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_resend_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Invalid user ID.', 'nobloat-user-foundry' ) );
		}

		/* Delete any existing tokens for this user */
		global $wpdb;
		$table = $wpdb->prefix . NBUF_DB_TABLE;
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'user_id' => $user_id ) );

		/*
		 * SECURITY: Generate cryptographically secure verification token.
		 * Use random_bytes() instead of wp_generate_password() for security tokens.
		 */
		$token   = bin2hex( random_bytes( 32 ) ); /* 64 hex characters, cryptographically secure */
		$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		NBUF_Database::insert_token( $user_id, $user->user_email, $token, $expires, 0 );

		/* Send email */
		NBUF_Email::send_verification_email( $user->user_email, $token );

		/* Redirect with admin notice */
		wp_safe_redirect( add_query_arg( 'nbuf_resend', 'success', admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Handle manual verify action.
	 *
	 * Allows admin to manually verify a user without email verification.
	 */
	public static function handle_manual_verify() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action verification via capability check.
		if ( ! isset( $_GET['action'] ) || 'nbuf_manual_verify' !== wp_unslash( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action, capability checked below.
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manually verify users.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_manual_verify_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Invalid user ID.', 'nobloat-user-foundry' ) );
		}

		/* Manually verify the user */
		$admin_id = get_current_user_id();
		NBUF_User_Data::manually_verify( $user_id, $admin_id );

		/* Redirect with admin notice */
		wp_safe_redirect( add_query_arg( 'nbuf_manual_verified', 'success', admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Handle approve user action.
	 *
	 * Approves a user account that requires admin approval.
	 */
	public static function handle_approve_user() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action verification via capability check.
		if ( ! isset( $_GET['action'] ) || 'nbuf_approve' !== wp_unslash( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action, capability checked below.
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to approve users.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_approve_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Invalid user ID.', 'nobloat-user-foundry' ) );
		}

		/* Approve the user */
		$admin_id = get_current_user_id();
		NBUF_User_Data::approve_user( $user_id, $admin_id, 'Approved by administrator' );

		/* Redirect with admin notice */
		wp_safe_redirect( add_query_arg( 'nbuf_approved', 'success', admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Handle reject user action.
	 *
	 * Rejects a user account that requires admin approval.
	 */
	public static function handle_reject_user() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action verification via capability check.
		if ( ! isset( $_GET['action'] ) || 'nbuf_reject' !== wp_unslash( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action, capability checked below.
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reject users.', 'nobloat-user-foundry' ) );
		}

		check_admin_referer( 'nbuf_reject_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( esc_html__( 'Invalid user ID.', 'nobloat-user-foundry' ) );
		}

		/* Reject the user */
		$admin_id = get_current_user_id();
		NBUF_User_Data::reject_user( $user_id, $admin_id, 'Rejected by administrator' );

		/* Redirect with admin notice */
		wp_safe_redirect( add_query_arg( 'nbuf_rejected', 'success', admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Handle 2FA admin actions.
	 *
	 * Handles admin actions for managing user 2FA settings.
	 */
	public static function handle_2fa_admin_actions() {
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin 2FA action, capability checked.
		if ( ! isset( $_GET['action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Reset 2FA */
		if ( 'nbuf_reset_user_2fa' === $action ) {
			check_admin_referer( 'nbuf_reset_2fa_' . $user_id );
			self::reset_user_2fa( $user_id );

			/* Redirect back to appropriate page */
			$redirect_url = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ( isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
			if ( empty( $redirect_url ) || strpos( $redirect_url, 'users.php' ) !== false ) {
				/* From users list - go back to users list */
				wp_safe_redirect( add_query_arg( 'nbuf_2fa_reset', '1', admin_url( 'users.php' ) ) );
			} else {
				/* From user edit page - stay on user edit page */
				wp_safe_redirect( add_query_arg( 'nbuf_2fa_action', 'reset', admin_url( 'user-edit.php?user_id=' . $user_id ) ) );
			}
			exit;
		}

		/* Disable 2FA */
		if ( 'nbuf_disable_user_2fa' === $action ) {
			check_admin_referer( 'nbuf_disable_2fa_' . $user_id );
			NBUF_User_2FA_Data::disable( $user_id );
			wp_safe_redirect( add_query_arg( 'nbuf_2fa_action', 'disabled', admin_url( 'user-edit.php?user_id=' . $user_id ) ) );
			exit;
		}

		/* Regenerate Backup Codes */
		if ( 'nbuf_regenerate_backup_codes' === $action ) {
			check_admin_referer( 'nbuf_regen_codes_' . $user_id );

			/* Check if NBUF_2FA class exists */
			if ( class_exists( 'NBUF_2FA' ) ) {
				/* Generate new backup codes */
				$codes = NBUF_2FA::generate_backup_codes( $user_id );

				/* Store codes in transient for display */
				set_transient( 'nbuf_backup_codes_' . $user_id, $codes, 300 );
			}

			wp_safe_redirect( add_query_arg( 'nbuf_2fa_action', 'codes_regenerated', admin_url( 'user-edit.php?user_id=' . $user_id ) ) );
			exit;
		}

		/* Clear Trusted Devices */
		if ( 'nbuf_clear_trusted_devices' === $action ) {
			check_admin_referer( 'nbuf_clear_devices_' . $user_id );
			NBUF_User_2FA_Data::update( $user_id, array( 'trusted_devices' => wp_json_encode( array() ) ) );
			wp_safe_redirect( add_query_arg( 'nbuf_2fa_action', 'devices_cleared', admin_url( 'user-edit.php?user_id=' . $user_id ) ) );
			exit;
		}
	}

	/**
	 * Add user filters.
	 *
	 * Adds filter dropdowns to the users list page.
	 */
	public static function add_user_filters() {
		global $pagenow;
		if ( 'users.php' !== $pagenow ) {
			return;
		}

		// Verification Status Filter.
     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filter dropdowns don't require nonce
		$verification_status = isset( $_GET['nbuf_verification'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_verification'] ) ) : '';
		?>
		<select name="nbuf_verification">
			<option value=""><?php esc_html_e( 'All Verification Status', 'nobloat-user-foundry' ); ?></option>
			<option value="verified" <?php selected( $verification_status, 'verified' ); ?>><?php esc_html_e( 'Verified', 'nobloat-user-foundry' ); ?></option>
			<option value="unverified" <?php selected( $verification_status, 'unverified' ); ?>><?php esc_html_e( 'Unverified', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php

		// Account Status Filter.
		$account_status = isset( $_GET['nbuf_account_status'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_account_status'] ) ) : '';
		?>
		<select name="nbuf_account_status">
			<option value=""><?php esc_html_e( 'All Account Status', 'nobloat-user-foundry' ); ?></option>
			<option value="enabled" <?php selected( $account_status, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?></option>
			<option value="disabled" <?php selected( $account_status, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php

		// Expiration Filter.
		$expiration_status = isset( $_GET['nbuf_expiration'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_expiration'] ) ) : '';
		?>
		<select name="nbuf_expiration">
			<option value=""><?php esc_html_e( 'All Expiration Status', 'nobloat-user-foundry' ); ?></option>
			<option value="has_expiration" <?php selected( $expiration_status, 'has_expiration' ); ?>><?php esc_html_e( 'Has Expiration', 'nobloat-user-foundry' ); ?></option>
			<option value="no_expiration" <?php selected( $expiration_status, 'no_expiration' ); ?>><?php esc_html_e( 'No Expiration', 'nobloat-user-foundry' ); ?></option>
			<option value="expired" <?php selected( $expiration_status, 'expired' ); ?>><?php esc_html_e( 'Expired', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php

		// 2FA Status Filter.
		$twofa_status = isset( $_GET['nbuf_2fa_status'] ) ? sanitize_text_field( wp_unslash( $_GET['nbuf_2fa_status'] ) ) : '';
		?>
		<select name="nbuf_2fa_status">
			<option value=""><?php esc_html_e( 'All 2FA Status', 'nobloat-user-foundry' ); ?></option>
			<option value="enabled" <?php selected( $twofa_status, 'enabled' ); ?>><?php esc_html_e( '2FA Enabled', 'nobloat-user-foundry' ); ?></option>
			<option value="disabled" <?php selected( $twofa_status, 'disabled' ); ?>><?php esc_html_e( '2FA Disabled', 'nobloat-user-foundry' ); ?></option>
			<option value="email" <?php selected( $twofa_status, 'email' ); ?>><?php esc_html_e( 'Email Only', 'nobloat-user-foundry' ); ?></option>
			<option value="totp" <?php selected( $twofa_status, 'totp' ); ?>><?php esc_html_e( 'TOTP Only', 'nobloat-user-foundry' ); ?></option>
			<option value="both" <?php selected( $twofa_status, 'both' ); ?>><?php esc_html_e( 'Both Methods', 'nobloat-user-foundry' ); ?></option>
		</select>
		<?php
     // phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Filter users by status.
	 *
	 * Modifies the users query based on selected filters.
	 *
	 * @param  WP_User_Query $query User query object.
	 * @return WP_User_Query Modified query object.
	 */
	public static function filter_users_by_status( $query ) {
		global $pagenow, $wpdb;

		if ( 'users.php' !== $pagenow || ! is_admin() ) {
			return $query;
		}

		$table_name   = $wpdb->prefix . 'nbuf_user_data';
		$current_time = current_time( 'mysql' );

     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin list table filtering.

		/* Verification filter */
		if ( ! empty( $_GET['nbuf_verification'] ) ) {
			$verification = sanitize_text_field( wp_unslash( $_GET['nbuf_verification'] ) );

			if ( 'verified' === $verification ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE is_verified = %d', $wpdb->prefix . 'nbuf_user_data', 1 ) );
			} elseif ( 'unverified' === $verification ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$all_users = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i', $wpdb->users ) );
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$verified_users = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE is_verified = %d', $wpdb->prefix . 'nbuf_user_data', 1 ) );
				$user_ids       = array_diff( $all_users, $verified_users );
			}

			if ( ! empty( $user_ids ) ) {
				$query->set( 'include', $user_ids );
			} else {
				$query->set( 'include', array( 0 ) ); // No results.
			}
		}

		/* Account status filter */
		if ( ! empty( $_GET['nbuf_account_status'] ) ) {
			$account_status = sanitize_text_field( wp_unslash( $_GET['nbuf_account_status'] ) );

			if ( 'disabled' === $account_status ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE is_disabled = %d', $wpdb->prefix . 'nbuf_user_data', 1 ) );
			} elseif ( 'enabled' === $account_status ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$all_users = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i', $wpdb->users ) );
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$disabled_users = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE is_disabled = %d', $wpdb->prefix . 'nbuf_user_data', 1 ) );
				$user_ids       = array_diff( $all_users, $disabled_users );
			}

			if ( ! empty( $user_ids ) ) {
				$current_includes = $query->get( 'include' );
				if ( ! empty( $current_includes ) ) {
					$user_ids = array_intersect( $current_includes, $user_ids );
				}
				$query->set( 'include', $user_ids );
			} else {
				$query->set( 'include', array( 0 ) );
			}
		}

		/* Expiration filter */
		if ( ! empty( $_GET['nbuf_expiration'] ) ) {
			$expiration = sanitize_text_field( wp_unslash( $_GET['nbuf_expiration'] ) );

			if ( 'has_expiration' === $expiration ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE expires_at IS NOT NULL AND expires_at != %s', $wpdb->prefix . 'nbuf_user_data', '0000-00-00 00:00:00' ) );
			} elseif ( 'no_expiration' === $expiration ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$all_users = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i', $wpdb->users ) );
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$has_expiration = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE expires_at IS NOT NULL AND expires_at != %s', $wpdb->prefix . 'nbuf_user_data', '0000-00-00 00:00:00' ) );
				$user_ids       = array_diff( $all_users, $has_expiration );
			} elseif ( 'expired' === $expiration ) {
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE expires_at IS NOT NULL AND expires_at != %s AND expires_at <= %s', $wpdb->prefix . 'nbuf_user_data', '0000-00-00 00:00:00', $current_time ) );
			}

			if ( ! empty( $user_ids ) ) {
				$current_includes = $query->get( 'include' );
				if ( ! empty( $current_includes ) ) {
					$user_ids = array_intersect( $current_includes, $user_ids );
				}
				$query->set( 'include', $user_ids );
			} else {
				$query->set( 'include', array( 0 ) );
			}
		}

		/* 2FA filter */
		if ( ! empty( $_GET['nbuf_2fa_status'] ) ) {
			$twofa_status = sanitize_text_field( wp_unslash( $_GET['nbuf_2fa_status'] ) );

			if ( 'enabled' === $twofa_status ) {
				/*
				Get all users with 2FA enabled (any method except disabled)
				*/
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT user_id FROM %i
						WHERE meta_key = 'nbuf_2fa_method'
						AND meta_value IN ('email', 'totp', 'both')",
						$wpdb->usermeta
					)
				);
			} elseif ( 'disabled' === $twofa_status ) {
				/*
				Get all users without 2FA or with disabled method
				*/
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$all_users = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i', $wpdb->users ) );
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$enabled_users = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT user_id FROM %i
						WHERE meta_key = 'nbuf_2fa_method'
						AND meta_value IN ('email', 'totp', 'both')",
						$wpdb->usermeta
					)
				);
				$user_ids      = array_diff( $all_users, $enabled_users );
			} elseif ( in_array( $twofa_status, array( 'email', 'totp', 'both' ), true ) ) {
				/*
				Get users with specific method
				*/
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_ids = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT user_id FROM %i
						WHERE meta_key = %s
						AND meta_value = %s',
						$wpdb->usermeta,
						'nbuf_2fa_method',
						$twofa_status
					)
				);
			}

			if ( isset( $user_ids ) ) {
				if ( ! empty( $user_ids ) ) {
					$current_includes = $query->get( 'include' );
					if ( ! empty( $current_includes ) ) {
						$user_ids = array_intersect( $current_includes, $user_ids );
					}
					$query->set( 'include', $user_ids );
				} else {
					$query->set( 'include', array( 0 ) );
				}
			}
		}

     // phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $query;
	}

	/**
	==========================================================
	ADD USER COUNT FILTERS
	----------------------------------------------------------
	Adds count badges to the filters (e.g., "Verified (23)").
	==========================================================
	 */
	public static function add_user_count_filters() {
		global $pagenow, $wpdb;
		if ( 'users.php' !== $pagenow ) {
			return;
		}

		/* Get counts */
		$verified_count   = NBUF_User_Data::get_count( 'verified' );
		$unverified_count = NBUF_User_Data::get_count( 'unverified' );
		$disabled_count   = NBUF_User_Data::get_count( 'disabled' );
		$expired_count    = NBUF_User_Data::get_count( 'expired' );

		/* Get 2FA counts */

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$twofa_enabled_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM %i
				WHERE meta_key = 'nbuf_2fa_method'
				AND meta_value IN ('email', 'totp', 'both')",
				$wpdb->usermeta
			)
		);
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$twofa_email_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(user_id) FROM %i
				WHERE meta_key = 'nbuf_2fa_method'
				AND meta_value = 'email'",
				$wpdb->usermeta
			)
		);
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$twofa_totp_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(user_id) FROM %i
				WHERE meta_key = 'nbuf_2fa_method'
				AND meta_value = 'totp'",
				$wpdb->usermeta
			)
		);
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$twofa_both_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(user_id) FROM %i
				WHERE meta_key = 'nbuf_2fa_method'
				AND meta_value = 'both'",
				$wpdb->usermeta
			)
		);

		/* Add JavaScript to update filter labels with counts */
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			/* Update verification status filter options */
			$('select[name="nbuf_verification"] option[value="verified"]').text('<?php echo esc_js( __( 'Verified', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $verified_count ); ?>)');
			$('select[name="nbuf_verification"] option[value="unverified"]').text('<?php echo esc_js( __( 'Unverified', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $unverified_count ); ?>)');

			/* Update account status filter options */
			$('select[name="nbuf_account_status"] option[value="disabled"]').text('<?php echo esc_js( __( 'Disabled', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $disabled_count ); ?>)');

			/* Update expiration filter options */
			$('select[name="nbuf_expiration"] option[value="expired"]').text('<?php echo esc_js( __( 'Expired', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $expired_count ); ?>)');

			/* Update 2FA status filter options */
			$('select[name="nbuf_2fa_status"] option[value="enabled"]').text('<?php echo esc_js( __( '2FA Enabled', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $twofa_enabled_count ); ?>)');
			$('select[name="nbuf_2fa_status"] option[value="email"]').text('<?php echo esc_js( __( 'Email Only', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $twofa_email_count ); ?>)');
			$('select[name="nbuf_2fa_status"] option[value="totp"]').text('<?php echo esc_js( __( 'TOTP Only', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $twofa_totp_count ); ?>)');
			$('select[name="nbuf_2fa_status"] option[value="both"]').text('<?php echo esc_js( __( 'Both Methods', 'nobloat-user-foundry' ) ); ?> (<?php echo absint( $twofa_both_count ); ?>)');
		});
		</script>
		<?php
	}

	/**
	 * Render Profile Section
	 *
	 * Displays NoBloat user options in the user profile screen.
	 *
	 * @param WP_User $user The user object being edited.
	 */
	public static function render_profile_section( $user ) {
		/* Only admins can modify these settings */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id       = $user->ID;
		$user_data     = NBUF_User_Data::get( $user_id );
		$disabled      = $user_data ? $user_data->is_disabled : false;
		$verified      = $user_data ? $user_data->is_verified : false;
		$verified_date = $user_data ? $user_data->verified_date : '';
		$expires_at    = $user_data && $user_data->expires_at && '0000-00-00 00:00:00' !== $user_data->expires_at ? $user_data->expires_at : '';

		/* Check if user is admin */
		$is_admin = user_can( $user, 'manage_options' );

		/* Check if expiration feature is enabled */
		$expiration_enabled = NBUF_Options::get( 'nbuf_enable_expiration', false );
		?>
		<h2><?php esc_html_e( 'NoBloat User Options', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'User Enabled', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_user_enabled" value="1" <?php checked( ! $disabled, true ); ?>>
		<?php esc_html_e( 'User can log in', 'nobloat-user-foundry' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email Verified', 'nobloat-user-foundry' ); ?></th>
				<td>
		<?php if ( $is_admin ) : ?>
						<label>
							<input type="checkbox" checked disabled>
			<?php esc_html_e( 'Immutable (admin accounts are always verified)', 'nobloat-user-foundry' ); ?>
						</label>
					<?php else : ?>
						<label>
							<input type="checkbox" name="nbuf_user_verified" value="1" <?php checked( $verified, true ); ?>>
						<?php esc_html_e( 'Email address verified', 'nobloat-user-foundry' ); ?>
						</label>
						<?php if ( $verified && $verified_date ) : ?>
							<p class="description" style="margin-top:5px;">
							<?php
							/* translators: %s: verification date */
							echo esc_html( sprintf( __( 'Verified on: %s', 'nobloat-user-foundry' ), $verified_date ) );
							?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php if ( $expiration_enabled ) : ?>
			<tr>
				<th><?php esc_html_e( 'Account Expiration', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="nbuf_never_expires" name="nbuf_never_expires" value="1" <?php checked( empty( $expires_at ), true ); ?>>
			<?php esc_html_e( 'Never expires', 'nobloat-user-foundry' ); ?>
					</label>
					<div id="nbuf_expiration_date_wrapper" style="margin-top:10px;<?php echo empty( $expires_at ) ? 'display:none;' : ''; ?>">
						<label for="nbuf_expires_at_date"><?php esc_html_e( 'Expires on:', 'nobloat-user-foundry' ); ?></label><br>
						<input type="date" id="nbuf_expires_at_date" name="nbuf_expires_at_date" value="<?php echo esc_attr( $expires_at ? gmdate( 'Y-m-d', strtotime( $expires_at ) ) : '' ); ?>" style="width: 150px;">
						<input type="time" id="nbuf_expires_at_time" name="nbuf_expires_at_time" value="<?php echo esc_attr( $expires_at ? gmdate( 'H:i', strtotime( $expires_at ) ) : '00:00' ); ?>" style="width: 100px;">
						<p class="description"><?php esc_html_e( 'Select expiration date and time', 'nobloat-user-foundry' ); ?></p>
					</div>
			<?php if ( $expires_at && NBUF_User_Data::is_expired( $user_id ) ) : ?>
						<p class="description" style="margin-top:10px;color:#c0392b;font-weight:bold;">
				<?php esc_html_e( '⚠️ This account is currently EXPIRED.', 'nobloat-user-foundry' ); ?>
						</p>
			<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
		</table>

		<?php
		/* Profile Fields Section */
		$profile_data   = NBUF_Profile_Data::get( $user_id );
		$enabled_fields = NBUF_Profile_Data::get_enabled_fields();
		$field_registry = NBUF_Profile_Data::get_field_registry();

		if ( ! empty( $enabled_fields ) ) :
			?>
		<h2><?php esc_html_e( 'Profile Information', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<?php
			/* Build flat field labels array from registry */
			$field_labels = array();
			foreach ( $field_registry as $category_data ) {
				$field_labels = array_merge( $field_labels, $category_data['fields'] );
			}

			foreach ( $enabled_fields as $field ) {
				$label = isset( $field_labels[ $field ] ) ? $field_labels[ $field ] : ucwords( str_replace( '_', ' ', $field ) );
				$value = $profile_data ? NBUF_Profile_Data::get_field( $user_id, $field ) : '';

				echo '<tr>';
				echo '<th><label for="nbuf_profile_' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th>';
				echo '<td>';

				/* Render appropriate field type based on field characteristics */
				if ( in_array( $field, array( 'bio', 'professional_memberships', 'certifications', 'emergency_contact' ), true ) ) {
					/* Text areas */
					printf(
						'<textarea name="nbuf_profile[%s]" id="nbuf_profile_%s" rows="5" cols="50" class="large-text">%s</textarea>',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_textarea( $value )
					);
				} elseif ( in_array( $field, array( 'website', 'twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok' ), true ) ) {
					/* URLs */
					printf(
						'<input type="url" name="nbuf_profile[%s]" id="nbuf_profile_%s" value="%s" class="regular-text">',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_url( $value )
					);
				} elseif ( in_array( $field, array( 'work_email', 'supervisor_email' ), true ) ) {
					/* Emails */
					printf(
						'<input type="email" name="nbuf_profile[%s]" id="nbuf_profile_%s" value="%s" class="regular-text">',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_attr( $value )
					);
				} elseif ( in_array( $field, array( 'phone', 'mobile_phone', 'work_phone', 'fax' ), true ) ) {
					/* Phone numbers */
					printf(
						'<input type="tel" name="nbuf_profile[%s]" id="nbuf_profile_%s" value="%s" class="regular-text">',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_attr( $value )
					);
				} elseif ( in_array( $field, array( 'date_of_birth', 'hire_date', 'termination_date' ), true ) ) {
					/* Dates */
					printf(
						'<input type="date" name="nbuf_profile[%s]" id="nbuf_profile_%s" value="%s" class="regular-text">',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_attr( $value )
					);
				} else {
					/* Default text input */
					printf(
						'<input type="text" name="nbuf_profile[%s]" id="nbuf_profile_%s" value="%s" class="regular-text">',
						esc_attr( $field ),
						esc_attr( $field ),
						esc_attr( $value )
					);
				}

				echo '</td>';
				echo '</tr>';
			}
			?>
		</table>
		<?php endif; ?>

		<?php
		/* Two-Factor Authentication Section */
		$twofa_enabled         = NBUF_User_2FA_Data::is_enabled( $user_id );
		$twofa_method          = NBUF_User_2FA_Data::get_method( $user_id );
		$twofa_setup_completed = NBUF_User_2FA_Data::is_setup_completed( $user_id );
		$twofa_last_used       = NBUF_User_2FA_Data::get_last_used( $user_id );
		$twofa_forced_at       = NBUF_User_2FA_Data::get_forced_at( $user_id );
		$trusted_devices       = NBUF_User_2FA_Data::get_trusted_devices( $user_id );
		$backup_codes          = NBUF_User_2FA_Data::get_backup_codes( $user_id );
		$backup_codes_used     = NBUF_User_2FA_Data::get_backup_codes_used( $user_id );

		/* Count remaining backup codes */
		$backup_codes_remaining = 0;
		if ( is_array( $backup_codes ) && is_array( $backup_codes_used ) ) {
			$backup_codes_remaining = count( $backup_codes ) - count( $backup_codes_used );
		}

		/* Count trusted devices */
		$trusted_device_count = 0;
		if ( is_array( $trusted_devices ) ) {
			/* Count non-expired devices */
			foreach ( $trusted_devices as $token => $expires ) {
				if ( $expires > time() ) {
					++$trusted_device_count;
				}
			}
		}
		?>

		<h2><?php esc_html_e( 'Two-Factor Authentication', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( '2FA Status', 'nobloat-user-foundry' ); ?></th>
				<td>
		<?php if ( $twofa_enabled && $twofa_method && 'disabled' !== $twofa_method ) : ?>
						<p><strong style="color:#46b450;"><?php esc_html_e( '✓ Enabled', 'nobloat-user-foundry' ); ?></strong></p>
						<p class="description">
			<?php
			if ( 'email' === $twofa_method ) {
				esc_html_e( 'Method: Email 2FA', 'nobloat-user-foundry' );
			} elseif ( 'totp' === $twofa_method ) {
				esc_html_e( 'Method: Authenticator App (TOTP)', 'nobloat-user-foundry' );
			} elseif ( 'both' === $twofa_method ) {
				esc_html_e( 'Method: Email + Authenticator App', 'nobloat-user-foundry' );
			}
			?>
						</p>
			<?php if ( $twofa_setup_completed ) : ?>
							<p class="description"><?php esc_html_e( 'Setup completed', 'nobloat-user-foundry' ); ?></p>
			<?php endif; ?>
			<?php if ( $twofa_last_used ) : ?>
							<p class="description">
				<?php
				/* translators: %s: last used date and time */
				echo esc_html( sprintf( __( 'Last used: %s', 'nobloat-user-foundry' ), gmdate( 'Y-m-d H:i:s', $twofa_last_used ) ) );
				?>
							</p>
			<?php endif; ?>
					<?php else : ?>
						<p><strong style="color:#999;"><?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?></strong></p>
						<p class="description"><?php esc_html_e( 'User has not set up 2FA', 'nobloat-user-foundry' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>

		<?php if ( $twofa_enabled && $twofa_method && 'disabled' !== $twofa_method ) : ?>
			<tr>
				<th><?php esc_html_e( 'Security Details', 'nobloat-user-foundry' ); ?></th>
				<td>
					<ul style="margin:0;">
						<li><strong><?php esc_html_e( 'Trusted Devices:', 'nobloat-user-foundry' ); ?></strong> <?php echo (int) $trusted_device_count; ?></li>
						<li><strong><?php esc_html_e( 'Backup Codes Remaining:', 'nobloat-user-foundry' ); ?></strong> <?php echo (int) $backup_codes_remaining; ?> / 10</li>
			<?php if ( $twofa_forced_at ) : ?>
							<li><strong><?php esc_html_e( 'Enforcement Started:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', $twofa_forced_at ) ); ?></li>
			<?php endif; ?>
					</ul>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Admin Actions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<p style="margin-bottom:10px;">
						<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action'  => 'nbuf_reset_user_2fa',
							'user_id' => $user_id,
						),
						admin_url( 'user-edit.php' )
					),
					'nbuf_reset_2fa_' . $user_id
				)
			);
			?>
									" class="button" onclick="return confirm('<?php echo esc_js( __( 'Reset this user\'s 2FA settings? They will need to set up 2FA again.', 'nobloat-user-foundry' ) ); ?>');">
			<?php esc_html_e( 'Reset 2FA', 'nobloat-user-foundry' ); ?>
						</a>
						<span class="description"><?php esc_html_e( 'Clears all 2FA data, user must set up again', 'nobloat-user-foundry' ); ?></span>
					</p>
					<p style="margin-bottom:10px;">
						<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action'  => 'nbuf_disable_user_2fa',
							'user_id' => $user_id,
						),
						admin_url( 'user-edit.php' )
					),
					'nbuf_disable_2fa_' . $user_id
				)
			);
			?>
									" class="button" onclick="return confirm('<?php echo esc_js( __( 'Disable 2FA for this user?', 'nobloat-user-foundry' ) ); ?>');">
			<?php esc_html_e( 'Disable 2FA', 'nobloat-user-foundry' ); ?>
						</a>
						<span class="description"><?php esc_html_e( 'Turns off 2FA for this user only', 'nobloat-user-foundry' ); ?></span>
					</p>
			<?php if ( $backup_codes_remaining < 3 ) : ?>
					<p style="margin-bottom:10px;">
						<a href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action'  => 'nbuf_regenerate_backup_codes',
								'user_id' => $user_id,
							),
							admin_url( 'user-edit.php' )
						),
						'nbuf_regen_codes_' . $user_id
					)
				);
				?>
									" class="button">
				<?php esc_html_e( 'Regenerate Backup Codes', 'nobloat-user-foundry' ); ?>
						</a>
						<span class="description"><?php esc_html_e( 'Creates new backup codes (old ones will be invalid)', 'nobloat-user-foundry' ); ?></span>
					</p>
			<?php endif; ?>
			<?php if ( $trusted_device_count > 0 ) : ?>
					<p style="margin-bottom:10px;">
						<a href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action'  => 'nbuf_clear_trusted_devices',
								'user_id' => $user_id,
							),
							admin_url( 'user-edit.php' )
						),
						'nbuf_clear_devices_' . $user_id
					)
				);
				?>
									" class="button">
				<?php esc_html_e( 'Clear Trusted Devices', 'nobloat-user-foundry' ); ?>
						</a>
						<span class="description"><?php esc_html_e( 'Removes all trusted device tokens', 'nobloat-user-foundry' ); ?></span>
					</p>
			<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
		</table>

		<?php
		/* Password Expiration Section */
		$password_expiration_enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
		if ( $password_expiration_enabled && class_exists( 'NBUF_Password_Expiration' ) ) :
			$force_change          = NBUF_Password_Expiration::is_password_change_forced( $user_id );
			$is_expired            = NBUF_Password_Expiration::is_password_expired( $user_id );
			$password_age          = NBUF_Password_Expiration::get_password_age( $user_id );
			$days_until_expiration = NBUF_Password_Expiration::get_days_until_expiration( $user_id );
			?>
		<h2><?php esc_html_e( 'Password Expiration', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Password Status', 'nobloat-user-foundry' ); ?></th>
				<td>
			<?php if ( $force_change ) : ?>
						<p style="color: #d63638; font-weight: 600;">
							<span class="dashicons dashicons-warning" style="font-size: 18px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Password change forced by administrator', 'nobloat-user-foundry' ); ?>
						</p>
						<p class="description">
				<?php esc_html_e( 'User will be required to change password on next login.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php elseif ( $is_expired ) : ?>
						<p style="color: #d63638; font-weight: 600;">
							<span class="dashicons dashicons-clock" style="font-size: 18px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Password has expired', 'nobloat-user-foundry' ); ?>
						</p>
						<p class="description">
						<?php esc_html_e( 'User will be required to change password on next login.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php elseif ( null !== $password_age ) : ?>
						<p>
							<span class="dashicons dashicons-shield" style="color: #00a32a; font-size: 18px; vertical-align: middle;"></span>
						<?php
						/* translators: %d: number of days */
						echo esc_html( sprintf( _n( 'Password is %d day old', 'Password is %d days old', $password_age, 'nobloat-user-foundry' ), $password_age ) );
						?>
						</p>
						<?php if ( null !== $days_until_expiration ) : ?>
							<?php if ( $days_until_expiration > 0 ) : ?>
								<p class="description">
								<?php
								/* translators: %d: number of days */
								echo esc_html( sprintf( _n( 'Expires in %d day', 'Expires in %d days', $days_until_expiration, 'nobloat-user-foundry' ), $days_until_expiration ) );
								?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					<?php else : ?>
						<p class="description">
						<?php esc_html_e( 'No password change history available.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Force Password Change', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_force_password_change" value="1" <?php checked( $force_change, true ); ?>>
			<?php esc_html_e( 'Require password change on next login', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
			<?php esc_html_e( 'User will be forced to create a new password before they can access the site.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Session Management', 'nobloat-user-foundry' ); ?></th>
				<td>
					<button type="button" class="button" id="nbuf_force_logout_user" data-user-id="<?php echo esc_attr( $user_id ); ?>">
			<?php esc_html_e( 'Force Logout All Devices', 'nobloat-user-foundry' ); ?>
					</button>
					<p class="description">
			<?php esc_html_e( 'Immediately log this user out of all devices and sessions. They will need to log in again.', 'nobloat-user-foundry' ); ?>
					</p>
					<div id="nbuf_logout_message" style="margin-top: 10px; display: none;"></div>

					<script type="text/javascript">
					jQuery(document).ready(function($) {
						$('#nbuf_force_logout_user').on('click', function(e) {
							e.preventDefault();

							if (!confirm('<?php echo esc_js( __( 'Force logout this user from all devices?', 'nobloat-user-foundry' ) ); ?>')) {
								return;
							}

							var $button = $(this);
							var userId = $button.data('user-id');
							var $message = $('#nbuf_logout_message');

							$button.prop('disabled', true).text('<?php echo esc_js( __( 'Logging out...', 'nobloat-user-foundry' ) ); ?>');

							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'nbuf_force_logout_user',
									user_id: userId,
									nonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_force_logout' ) ); ?>'
								},
								success: function(response) {
									if (response.success) {
										$message.html('<p style="color: #00a32a;"><strong>' + response.data.message + '</strong></p>').slideDown();
									} else {
										$message.html('<p style="color: #d63638;"><strong>' + response.data.message + '</strong></p>').slideDown();
									}
									$button.prop('disabled', false).text('<?php echo esc_js( __( 'Force Logout All Devices', 'nobloat-user-foundry' ) ); ?>');
								},
								error: function() {
									$message.html('<p style="color: #d63638;"><strong><?php echo esc_js( __( 'Error: Could not logout user.', 'nobloat-user-foundry' ) ); ?></strong></p>').slideDown();
									$button.prop('disabled', false).text('<?php echo esc_js( __( 'Force Logout All Devices', 'nobloat-user-foundry' ) ); ?>');
								}
							});
						});
					});
					</script>
				</td>
			</tr>
		</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save Profile Section
	 *
	 * Saves the NoBloat user options from profile screen.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public static function save_profile_section( $user_id ) {
		/* Only admins can modify these settings */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Verify nonce for security */
		if ( ! isset( $_POST['nbuf_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_profile_nonce'] ) ), 'nbuf_update_profile_' . $user_id ) ) {
			return;
		}

		$user     = get_userdata( $user_id );
		$is_admin = $user && user_can( $user, 'manage_options' );

		/* Handle User Enabled checkbox */
		if ( isset( $_POST['nbuf_user_enabled'] ) ) {
			// Checkbox is checked - user is enabled.
			NBUF_User_Data::set_enabled( $user_id );
		} else {
			// Checkbox is unchecked - user is disabled + kill sessions.
			NBUF_User_Data::set_disabled( $user_id, 'manual' );
			$sessions = WP_Session_Tokens::get_instance( $user_id );
			$sessions->destroy_all();
		}

		/* Handle Email Verified checkbox - skip for admins (immutable) */
		if ( ! $is_admin ) {
			if ( isset( $_POST['nbuf_user_verified'] ) ) {
				// Checkbox is checked - mark as verified.
				NBUF_User_Data::set_verified( $user_id );
			} else {
				// Checkbox is unchecked - remove verification.
				NBUF_User_Data::set_unverified( $user_id );
			}
		}

		/* Handle Account Expiration */
		$expiration_enabled = NBUF_Options::get( 'nbuf_enable_expiration', false );
		if ( $expiration_enabled ) {
			if ( isset( $_POST['nbuf_never_expires'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['nbuf_never_expires'] ) ) ) {
				// Never expires - remove expiration.
				NBUF_User_Data::set_expiration( $user_id, null );
			} elseif ( isset( $_POST['nbuf_expires_at_date'] ) && ! empty( $_POST['nbuf_expires_at_date'] ) ) {
				// Parse and validate expiration date and time.
				$expires_date      = sanitize_text_field( wp_unslash( $_POST['nbuf_expires_at_date'] ) );
				$expires_time      = isset( $_POST['nbuf_expires_at_time'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_expires_at_time'] ) ) : '00:00';
				$expires_input     = $expires_date . ' ' . $expires_time;
				$expires_timestamp = strtotime( $expires_input );
				if ( false !== $expires_timestamp ) {
					// Convert to MySQL datetime format.
					$expires_at = gmdate( 'Y-m-d H:i:s', $expires_timestamp );
					NBUF_User_Data::set_expiration( $user_id, $expires_at );
				}
			}
		}

		/* Handle Profile Data */
		if ( isset( $_POST['nbuf_profile'] ) && is_array( $_POST['nbuf_profile'] ) ) {
			$profile_fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_profile'] ) );
			/* Special sanitization for specific fields */
			if ( isset( $_POST['nbuf_profile']['bio'] ) ) {
				$profile_fields['bio'] = sanitize_textarea_field( wp_unslash( $_POST['nbuf_profile']['bio'] ) );
			}
			if ( isset( $_POST['nbuf_profile']['website'] ) ) {
				$profile_fields['website'] = esc_url_raw( wp_unslash( $_POST['nbuf_profile']['website'] ) );
			}
			NBUF_Profile_Data::update( $user_id, $profile_fields );
		}

		/* Handle Force Password Change */
		$password_expiration_enabled = NBUF_Options::get( 'nbuf_password_expiration_enabled', false );
		if ( $password_expiration_enabled && class_exists( 'NBUF_Password_Expiration' ) ) {
			if ( isset( $_POST['nbuf_force_password_change'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['nbuf_force_password_change'] ) ) ) {
				// Checkbox is checked - force password change.
				NBUF_Password_Expiration::force_password_change( $user_id );
			} else {
				// Checkbox is unchecked - clear force password change flag.
				NBUF_Password_Expiration::clear_force_password_change( $user_id );
			}
		}
	}

	/**
	==========================================================
	RENDER NEW USER FIELD
	----------------------------------------------------------
	Adds verification checkbox to new user creation form.
	==========================================================
	 */
	public static function render_new_user_field() {
		/* Only show to admins */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'NoBloat Email Verification', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="nbuf_verified"><?php esc_html_e( 'NoBloat Verified', 'nobloat-user-foundry' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_verified" id="nbuf_verified" value="1" checked>
		<?php esc_html_e( 'Mark this user as verified', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
		<?php esc_html_e( 'Verification emails are not sent during administrative account creation.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Handle New User Verification
	 *
	 * Sets verification status for admin-created users.
	 * Prevents verification emails from being sent.
	 *
	 * @param int $user_id The ID of the newly created user.
	 */
	public static function handle_new_user_verification( $user_id ) {
		/* Only process if user created via admin panel */
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Verify nonce for security */
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'create-user' ) ) {
			return;
		}

		/* Check if checkbox was checked */
		if ( isset( $_POST['nbuf_verified'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['nbuf_verified'] ) ) ) {
			NBUF_User_Data::set_verified( $user_id );
		}

		/*
		Prevent verification email from being sent by removing hooks temporarily.
		*/
		/* This runs at priority 5, before the normal verification hooks at priority 10. */
	}

	/**
	 * Render Version History Metabox
	 *
	 * Displays profile version history on user edit screen.
	 * Shows recent changes, diff viewer, and revert capabilities.
	 *
	 * @param WP_User $user The user object being edited.
	 */
	public static function render_version_history_metabox( $user ) {
		/* Only admins can view version history */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id    = $user->ID;
		$can_revert = true; /* Admins can always revert */

		/* Assets are now enqueued in enqueue_user_profile_scripts() */

		?>
		<div class="nbuf-vh-metabox">
		<?php
		/* Include the version history viewer template */
		$context    = 'metabox';
		$can_revert = true; // Admins can always revert.
		include plugin_dir_path( __DIR__ ) . 'templates/version-history-viewer.php';
		?>
		</div>

		
		<?php
	}
}

// Initialize class.
NBUF_Admin_Users::init();

/**
==========================================================
	ADMIN NOTICES
	----------------------------------------------------------
	Displays notices for bulk actions and resend operations.
	==========================================================
 */
add_action(
	'admin_notices',
	function () {
     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin notice display only.

		/* Bulk verified */
		if ( isset( $_GET['nbuf_bulk_verified'] ) ) {
			$count = (int) $_GET['nbuf_bulk_verified'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user marked as verified.', '%d users marked as verified.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Bulk unverified */
		if ( isset( $_GET['nbuf_bulk_unverified'] ) ) {
			$count = (int) $_GET['nbuf_bulk_unverified'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user verification removed.', '%d user verifications removed.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Bulk disabled */
		if ( isset( $_GET['nbuf_bulk_disabled'] ) ) {
			$count = (int) $_GET['nbuf_bulk_disabled'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user disabled.', '%d users disabled.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Bulk enabled */
		if ( isset( $_GET['nbuf_bulk_enabled'] ) ) {
			$count = (int) $_GET['nbuf_bulk_enabled'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user enabled.', '%d users enabled.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Bulk expiration removed */
		if ( isset( $_GET['nbuf_bulk_expiration_removed'] ) ) {
			$count = (int) $_GET['nbuf_bulk_expiration_removed'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user expiration removed.', '%d user expirations removed.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Show expiration modal */
		if ( isset( $_GET['nbuf_show_expiration_modal'] ) && '1' === $_GET['nbuf_show_expiration_modal'] ) {
			$user_ids = get_transient( 'nbuf_bulk_expiration_users' );
			if ( $user_ids ) {
				$user_count = count( $user_ids );
				?>
			<div class="notice notice-info is-dismissible">
				<p><strong><?php esc_html_e( 'Set Expiration Date', 'nobloat-user-foundry' ); ?></strong></p>
				<p>
				<?php
				/* translators: %d: number of users */
				echo esc_html( sprintf( _n( 'Setting expiration for %d user.', 'Setting expiration for %d users.', $user_count, 'nobloat-user-foundry' ), $user_count ) );
				?>
				</p>
				<form method="post" action="" style="margin-top:10px;">
				<?php wp_nonce_field( 'nbuf_bulk_set_expiration', 'nbuf_bulk_expiration_nonce' ); ?>
					<label for="nbuf_bulk_expires_at"><strong><?php esc_html_e( 'Expiration Date & Time:', 'nobloat-user-foundry' ); ?></strong></label><br>
					<input type="text" id="nbuf_bulk_expires_at" name="nbuf_bulk_expires_at" value="" placeholder="YYYY-MM-DD HH:MM" style="width:250px;">
					<p class="description"><?php esc_html_e( 'Format: YYYY-MM-DD HH:MM (e.g., 2025-12-31 23:59)', 'nobloat-user-foundry' ); ?></p>
					<button type="submit" class="button button-primary" style="margin-top:10px;"><?php esc_html_e( 'Set Expiration', 'nobloat-user-foundry' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?></a>
				</form>
			</div>
				<?php
			}
		}

		/* Process bulk expiration setting */
		if ( isset( $_POST['nbuf_bulk_expires_at'] ) && check_admin_referer( 'nbuf_bulk_set_expiration', 'nbuf_bulk_expiration_nonce' ) ) {
			$user_ids          = get_transient( 'nbuf_bulk_expiration_users' );
			$expires_input     = sanitize_text_field( wp_unslash( $_POST['nbuf_bulk_expires_at'] ) );
			$expires_timestamp = strtotime( $expires_input );

			if ( $user_ids && false !== $expires_timestamp ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', $expires_timestamp );
				$count      = 0;

				foreach ( $user_ids as $user_id ) {
					NBUF_User_Data::set_expiration( $user_id, $expires_at );
					++$count;
				}

				delete_transient( 'nbuf_bulk_expiration_users' );

				echo '<div class="notice notice-success is-dismissible"><p>' .
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user expiration set successfully.', '%d user expirations set successfully.', $count, 'nobloat-user-foundry' ), $count ) ) .
				'</p></div>';
			}
		}

		/* Resend success */
		if ( isset( $_GET['nbuf_resend'] ) && 'success' === $_GET['nbuf_resend'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'Verification email resent successfully.', 'nobloat-user-foundry' ) .
			'</p></div>';
		}

		/* Bulk 2FA reset */
		if ( isset( $_GET['nbuf_bulk_2fa_reset'] ) ) {
			$count = (int) $_GET['nbuf_bulk_2fa_reset'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user 2FA reset.', '%d users 2FA reset.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Row action 2FA reset */
		if ( isset( $_GET['nbuf_2fa_reset'] ) && '1' === $_GET['nbuf_2fa_reset'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'User 2FA reset successfully.', 'nobloat-user-foundry' ) .
			'</p></div>';
		}

		/* Bulk 2FA disabled */
		if ( isset( $_GET['nbuf_bulk_2fa_disabled'] ) ) {
			$count = (int) $_GET['nbuf_bulk_2fa_disabled'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of users */
				esc_html( sprintf( _n( '%d user 2FA disabled.', '%d users 2FA disabled.', $count, 'nobloat-user-foundry' ), $count ) )
			);
		}

		/* Individual 2FA actions */
		if ( isset( $_GET['nbuf_2fa_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['nbuf_2fa_action'] ) );

			if ( 'reset' === $action ) {
				echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( '2FA settings reset successfully. User will need to set up 2FA again.', 'nobloat-user-foundry' ) .
				'</p></div>';
			} elseif ( 'disabled' === $action ) {
				echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( '2FA disabled for this user.', 'nobloat-user-foundry' ) .
				'</p></div>';
			} elseif ( 'codes_regenerated' === $action ) {
				$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
				$codes   = get_transient( 'nbuf_backup_codes_' . $user_id );

				if ( $codes && is_array( $codes ) ) {
					echo '<div class="notice notice-success"><p><strong>' .
					esc_html__( 'New backup codes generated! Save these codes now - they will not be shown again:', 'nobloat-user-foundry' ) .
					'</strong></p><ul style="font-family:monospace;font-size:14px;line-height:1.8;">';
					foreach ( $codes as $code ) {
						echo '<li>' . esc_html( $code ) . '</li>';
					}
					echo '</ul><p class="description">' .
					esc_html__( 'Copy these codes to a safe place. Each code can only be used once.', 'nobloat-user-foundry' ) .
					'</p></div>';

					delete_transient( 'nbuf_backup_codes_' . $user_id );
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>' .
					esc_html__( 'Backup codes regenerated successfully.', 'nobloat-user-foundry' ) .
					'</p></div>';
				}
			} elseif ( 'devices_cleared' === $action ) {
				echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'All trusted devices cleared. User will need to verify 2FA on next login.', 'nobloat-user-foundry' ) .
				'</p></div>';
			}
		}

     // phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
);
