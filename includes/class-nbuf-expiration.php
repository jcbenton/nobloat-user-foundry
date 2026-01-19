<?php
/**
 * User Expiration Management
 *
 * Handles account expiration functionality including cron jobs
 * for auto-disabling expired accounts and sending warning emails.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expiration management class.
 *
 * Manages account expiration, cron jobs, and WooCommerce integration.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */
class NBUF_Expiration {


	/**
	 * Initialize expiration functionality.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Register cron hooks.
		add_action( 'nbuf_check_expirations', array( __CLASS__, 'process_expired_users' ) );
		add_action( 'nbuf_send_expiration_warnings', array( __CLASS__, 'send_warnings' ) );
	}

	/**
	 * Activate cron jobs.
	 * Called on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		/*
		 * Optimized: Read cron array once instead of multiple wp_next_scheduled() calls.
		 * Reduces Galera sync overhead on clustered databases.
		 */
		$crons     = _get_cron_array();
		$scheduled = array();

		if ( ! empty( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron['nbuf_check_expirations'] ) ) {
					$scheduled['nbuf_check_expirations'] = true;
				}
				if ( isset( $cron['nbuf_send_expiration_warnings'] ) ) {
					$scheduled['nbuf_send_expiration_warnings'] = true;
				}
			}
		}

		/* Schedule hourly check for expired users */
		if ( empty( $scheduled['nbuf_check_expirations'] ) ) {
			wp_schedule_event( time(), 'hourly', 'nbuf_check_expirations' );
		}

		/* Schedule daily expiration warning emails */
		if ( empty( $scheduled['nbuf_send_expiration_warnings'] ) ) {
			wp_schedule_event( time(), 'daily', 'nbuf_send_expiration_warnings' );
		}
	}

	/**
	 * Deactivate cron jobs.
	 * Called on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'nbuf_check_expirations' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nbuf_check_expirations' );
		}

		$timestamp = wp_next_scheduled( 'nbuf_send_expiration_warnings' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'nbuf_send_expiration_warnings' );
		}
	}

	/**
	 * Process expired users (hourly cron job).
	 * Auto-disables users whose expiration date has passed.
	 *
	 * @since 1.0.0
	 */
	public static function process_expired_users() {
		// Check if expiration feature is enabled.
		$enabled = NBUF_Options::get( 'nbuf_enable_expiration', false );
		if ( ! $enabled ) {
			return;
		}

		$current_time  = current_time( 'mysql', true );
		$expired_users = NBUF_User_Data::get_expiring_before( $current_time );

		if ( empty( $expired_users ) ) {
			return;
		}

		foreach ( $expired_users as $user_id ) {
			// Skip if user should be protected.
			if ( self::should_protect_from_expiration( $user_id ) ) {
				continue;
			}

			// Disable user.
			NBUF_User_Data::set_disabled( $user_id, 'expired' );

			// Kill all sessions.
			$sessions = WP_Session_Tokens::get_instance( $user_id );
			$sessions->destroy_all();

			// Send expiration notice email.
			self::send_expiration_notice_email( $user_id );

			// Fire action hook for extensions.
			do_action( 'nbuf_user_expired', $user_id );

			// Log if WP_DEBUG enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[NoBloat User Foundry] User ID %d expired and disabled.', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Send expiration warning emails (daily cron job).
	 * Sends emails to users X days before expiration.
	 *
	 * @since 1.0.0
	 */
	public static function send_warnings() {
		// Check if expiration feature is enabled.
		$enabled = NBUF_Options::get( 'nbuf_enable_expiration', false );
		if ( ! $enabled ) {
			return;
		}

		// Get warning threshold (default: 7 days).
		$warning_days = NBUF_Options::get( 'nbuf_expiration_warning_days', 7 );
		$warning_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$warning_days} days" ) );

		$users_needing_warning = NBUF_User_Data::get_needing_warning( $warning_date );

		if ( empty( $users_needing_warning ) ) {
			return;
		}

		foreach ( $users_needing_warning as $user_id ) {
			// Skip if user should be protected.
			if ( self::should_protect_from_expiration( $user_id ) ) {
				continue;
			}

			// Send warning email.
			if ( self::send_expiration_warning_email( $user_id ) ) {
				// Mark warning as sent.
				NBUF_User_Data::set_expiration_warned( $user_id );

				// Log if WP_DEBUG enabled.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[NoBloat User Foundry] Expiration warning sent to user ID %d.', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}

	/**
	 * Send expiration warning email to user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True on success, false on failure.
	 */
	private static function send_expiration_warning_email( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user_data = NBUF_User_Data::get( $user_id );
		if ( ! $user_data || ! $user_data->expires_at ) {
			return false;
		}

		// Get email templates.
		$html_template = NBUF_Options::get( 'nbuf_expiration_warning_html', '' );
		$text_template = NBUF_Options::get( 'nbuf_expiration_warning_text', '' );

		if ( empty( $html_template ) && empty( $text_template ) ) {
			return false;
		}

		// Prepare placeholders.
		$expires_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user_data->expires_at ) );
		$placeholders = array(
			'{site_name}'       => get_bloginfo( 'name' ),
			'{site_url}'        => home_url(),
			'{display_name}'    => $user->display_name ? $user->display_name : $user->user_login,
			'{username}'        => $user->user_login,
			'{expires_date}'    => $expires_date,
			'{expiration_date}' => $expires_date,
			'{login_url}'       => wp_login_url(),
			'{contact_url}'     => home_url( '/contact' ),
		);

		// Use HTML template if available, otherwise text.
		$use_html = ! empty( $html_template );
		$template = $use_html ? $html_template : $text_template;

		// Replace placeholders.
		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );

		// Prepare email.
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Your account will expire soon', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		// Send email using central sender.
		return NBUF_Email::send( $user->user_email, $subject, $message, $use_html ? 'html' : 'text' );
	}

	/**
	 * Send expiration notice email to user.
	 *
	 * Sent when the account has actually expired and been disabled.
	 *
	 * @since  1.5.0
	 * @param  int $user_id User ID.
	 * @return bool         True on success, false on failure.
	 */
	private static function send_expiration_notice_email( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$user_data = NBUF_User_Data::get( $user_id );

		// Get email templates using Template Manager.
		$html_template = NBUF_Template_Manager::load_template( 'expiration-notice-html' );
		$text_template = NBUF_Template_Manager::load_template( 'expiration-notice-text' );

		// If both templates are empty/default fallback, skip sending.
		if ( empty( $html_template ) && empty( $text_template ) ) {
			return false;
		}

		// Prepare placeholders.
		$expires_date = $user_data && $user_data->expires_at
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user_data->expires_at ) )
			: date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		$placeholders = array(
			'{site_name}'       => get_bloginfo( 'name' ),
			'{site_url}'        => home_url(),
			'{display_name}'    => $user->display_name ? $user->display_name : $user->user_login,
			'{username}'        => $user->user_login,
			'{expires_date}'    => $expires_date,
			'{expiration_date}' => $expires_date,
			'{contact_url}'     => home_url( '/contact' ),
		);

		// Use HTML template if available, otherwise text.
		$use_html = ! empty( $html_template ) && strpos( $html_template, 'Template not found' ) === false;
		$template = $use_html ? $html_template : $text_template;

		// Replace placeholders.
		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );

		// Prepare email.
		/* translators: %s: Site name */
		$subject = sprintf( __( '[%s] Your account has expired', 'nobloat-user-foundry' ), get_bloginfo( 'name' ) );

		// Send email using central sender.
		$sent = NBUF_Email::send( $user->user_email, $subject, $message, $use_html ? 'html' : 'text' );

		// Log result if WP_DEBUG enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $sent ) {
				error_log( sprintf( '[NoBloat User Foundry] Expiration notice sent to user ID %d.', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			} else {
				error_log( sprintf( '[NoBloat User Foundry] Failed to send expiration notice to user ID %d.', $user_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $sent;
	}

	/**
	 * Check if user should be protected from expiration.
	 * Handles WooCommerce integration checks.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if user should be protected, false otherwise.
	 */
	private static function should_protect_from_expiration( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return true; // Protect if user doesn't exist.
		}

		// Never expire admins.
		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		// Check WooCommerce settings if WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			// Check for active subscriptions.
			$prevent_active_subs = NBUF_Options::get( 'nbuf_wc_prevent_active_subs', false );
			if ( $prevent_active_subs && self::has_active_subscription( $user_id ) ) {
				return true;
			}

			// Check for recent orders.
			$prevent_recent_orders = NBUF_Options::get( 'nbuf_wc_prevent_recent_orders', false );
			if ( $prevent_recent_orders ) {
				$days = NBUF_Options::get( 'nbuf_wc_recent_order_days', 90 );
				if ( self::has_recent_orders( $user_id, $days ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if user has active WooCommerce subscriptions.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return bool                True if user has active subscriptions, false otherwise.
	 */
	private static function has_active_subscription( $user_id ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return false;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );
		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->has_status( array( 'active', 'pending' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user has recent WooCommerce orders.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @param  int $days    Number of days to consider "recent".
	 * @return bool                True if user has recent orders, false otherwise.
	 */
	private static function has_recent_orders( $user_id, $days = 90 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		$date_threshold = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'date_after'  => $date_threshold,
				'limit'       => 1,
			)
		);

		return count( $orders ) > 0;
	}
}

// Initialize.
NBUF_Expiration::init();
