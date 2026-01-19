<?php
/**
 * Activity Dashboard
 *
 * Provides users with a timeline view of their account activity.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_Activity_Dashboard class.
 *
 * Displays user activity timeline on the account page.
 *
 * @since 1.5.2
 */
class NBUF_Activity_Dashboard {

	/**
	 * Default items per page.
	 */
	const ITEMS_PER_PAGE = 20;

	/**
	 * Initialize activity dashboard hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		/* Register AJAX handler for loading more activity */
		add_action( 'wp_ajax_nbuf_load_activity', array( __CLASS__, 'ajax_load_activity' ) );
	}

	/**
	 * Check if activity dashboard is enabled.
	 *
	 * @since  1.5.2
	 * @return bool True if enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) NBUF_Options::get( 'nbuf_activity_dashboard_enabled', true );
	}

	/**
	 * Get user activity from audit log.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @param  int $page    Page number (1-indexed).
	 * @param  int $limit   Items per page.
	 * @return array<int, array<string, mixed>> Array of activity items.
	 */
	public static function get_user_activity( int $user_id, int $page = 1, int $limit = self::ITEMS_PER_PAGE ): array {
		global $wpdb;

		$offset     = ( $page - 1 ) * $limit;
		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance-critical timeline query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_type, event_status, event_message, ip_address, created_at, metadata
				FROM %i
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d',
				$table_name,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get total activity count for user.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return int Total count.
	 */
	public static function get_user_activity_count( int $user_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_user_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count query.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
				$user_id
			)
		);

		return (int) $count;
	}

	/**
	 * Get icon for event type.
	 *
	 * @since  1.5.2
	 * @param  string $event_type Event type.
	 * @return string SVG icon HTML.
	 */
	public static function get_event_icon( string $event_type ): string {
		$icons = array(
			/* Authentication */
			'login_success'        => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>',
			'login_failed'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
			'logout'               => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',

			/* Password */
			'password_change'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
			'password_reset'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>',

			/* Profile */
			'profile_update'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
			'email_change'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',

			/* Verification */
			'verification_sent'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>',
			'verification_success' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',

			/* 2FA */
			'2fa_enabled'          => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
			'2fa_disabled'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="4" y1="4" x2="20" y2="20"></line></svg>',
			'2fa_verified'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',

			/* Passkeys */
			'passkey_registered'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>',
			'passkey_deleted'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path><line x1="4" y1="4" x2="20" y2="20"></line></svg>',

			/* Sessions */
			'session_revoked'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line><line x1="2" y1="3" x2="22" y2="17"></line></svg>',

			/* Registration */
			'registration'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>',
			'user_created'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>',
		);

		/* Default icon for unknown events */
		$default_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';

		return isset( $icons[ $event_type ] ) ? $icons[ $event_type ] : $default_icon;
	}

	/**
	 * Get CSS class for event status.
	 *
	 * @since  1.5.2
	 * @param  string $event_status Event status.
	 * @return string CSS class.
	 */
	public static function get_status_class( string $event_status ): string {
		$classes = array(
			'success' => 'nbuf-activity-success',
			'failed'  => 'nbuf-activity-failed',
			'warning' => 'nbuf-activity-warning',
			'info'    => 'nbuf-activity-info',
		);

		return isset( $classes[ $event_status ] ) ? $classes[ $event_status ] : 'nbuf-activity-info';
	}

	/**
	 * Format event message for display.
	 *
	 * @since  1.5.2
	 * @param  string $event_type    Event type.
	 * @param  string $event_message Raw event message.
	 * @return string Formatted message.
	 */
	public static function format_event_message( string $event_type, string $event_message ): string {
		/* Use the message directly if provided */
		if ( ! empty( $event_message ) ) {
			return esc_html( $event_message );
		}

		/* Fallback to generic messages */
		$messages = array(
			'login_success'        => __( 'Logged in successfully', 'nobloat-user-foundry' ),
			'login_failed'         => __( 'Failed login attempt', 'nobloat-user-foundry' ),
			'logout'               => __( 'Logged out', 'nobloat-user-foundry' ),
			'password_change'      => __( 'Password changed', 'nobloat-user-foundry' ),
			'password_reset'       => __( 'Password reset', 'nobloat-user-foundry' ),
			'profile_update'       => __( 'Profile updated', 'nobloat-user-foundry' ),
			'email_change'         => __( 'Email address changed', 'nobloat-user-foundry' ),
			'verification_sent'    => __( 'Verification email sent', 'nobloat-user-foundry' ),
			'verification_success' => __( 'Email verified', 'nobloat-user-foundry' ),
			'2fa_enabled'          => __( 'Two-factor authentication enabled', 'nobloat-user-foundry' ),
			'2fa_disabled'         => __( 'Two-factor authentication disabled', 'nobloat-user-foundry' ),
			'2fa_verified'         => __( '2FA verification successful', 'nobloat-user-foundry' ),
			'passkey_registered'   => __( 'Passkey registered', 'nobloat-user-foundry' ),
			'passkey_deleted'      => __( 'Passkey deleted', 'nobloat-user-foundry' ),
			'session_revoked'      => __( 'Session revoked', 'nobloat-user-foundry' ),
			'registration'         => __( 'Account registered', 'nobloat-user-foundry' ),
			'user_created'         => __( 'Account created', 'nobloat-user-foundry' ),
		);

		return isset( $messages[ $event_type ] ) ? $messages[ $event_type ] : esc_html( $event_type );
	}

	/**
	 * Format relative time.
	 *
	 * @since  1.5.2
	 * @param  string $datetime MySQL datetime string.
	 * @return string Relative time string.
	 */
	public static function format_relative_time( string $datetime ): string {
		$timestamp = strtotime( $datetime );
		$diff      = time() - $timestamp;

		if ( $diff < 60 ) {
			return __( 'Just now', 'nobloat-user-foundry' );
		} elseif ( $diff < 3600 ) {
			$minutes = floor( $diff / 60 );
			/* translators: %d: number of minutes ago */
			return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'nobloat-user-foundry' ), $minutes );
		} elseif ( $diff < 86400 ) {
			$hours = floor( $diff / 3600 );
			/* translators: %d: number of hours ago */
			return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'nobloat-user-foundry' ), $hours );
		} elseif ( $diff < 604800 ) {
			$days = floor( $diff / 86400 );
			/* translators: %d: number of days ago */
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'nobloat-user-foundry' ), $days );
		} else {
			return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		}
	}

	/**
	 * Build activity tab HTML for account page.
	 *
	 * @since  1.5.2
	 * @param  int $user_id User ID.
	 * @return string HTML content.
	 */
	public static function build_activity_tab_html( int $user_id ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$activities  = self::get_user_activity( $user_id, 1, self::ITEMS_PER_PAGE );
		$total_count = self::get_user_activity_count( $user_id );
		$has_more    = $total_count > self::ITEMS_PER_PAGE;

		ob_start();
		?>
		<div class="nbuf-activity-dashboard">
			<h3><?php esc_html_e( 'Recent Activity', 'nobloat-user-foundry' ); ?></h3>

			<?php if ( empty( $activities ) ) : ?>
				<p class="nbuf-activity-empty">
					<?php esc_html_e( 'No activity recorded yet.', 'nobloat-user-foundry' ); ?>
				</p>
			<?php else : ?>
				<div class="nbuf-activity-timeline" id="nbuf-activity-timeline">
					<?php foreach ( $activities as $activity ) : ?>
						<?php echo self::render_activity_item( $activity ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method handles escaping. ?>
					<?php endforeach; ?>
				</div>

				<?php if ( $has_more ) : ?>
					<div class="nbuf-activity-load-more">
						<button type="button" class="nbuf-button nbuf-button-secondary" id="nbuf-load-more-activity"
							data-page="2"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'nbuf_load_activity' ) ); ?>">
							<?php esc_html_e( 'Load More', 'nobloat-user-foundry' ); ?>
						</button>
						<span class="nbuf-activity-count">
							<?php
							printf(
								/* translators: 1: items shown, 2: total items */
								esc_html__( 'Showing %1$d of %2$d activities', 'nobloat-user-foundry' ),
								(int) count( $activities ),
								(int) $total_count
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single activity item.
	 *
	 * @since  1.5.2
	 * @param  array<string, mixed> $activity Activity data.
	 * @return string HTML for activity item.
	 */
	public static function render_activity_item( array $activity ): string {
		$event_type   = $activity['event_type'] ?? '';
		$event_status = $activity['event_status'] ?? 'info';
		$message      = $activity['event_message'] ?? '';
		$created_at   = $activity['created_at'] ?? '';
		$ip_address   = $activity['ip_address'] ?? '';

		$icon          = self::get_event_icon( $event_type );
		$status_class  = self::get_status_class( $event_status );
		$display_msg   = self::format_event_message( $event_type, $message );
		$relative_time = self::format_relative_time( $created_at );

		ob_start();
		?>
		<div class="nbuf-activity-item <?php echo esc_attr( $status_class ); ?>">
			<div class="nbuf-activity-icon">
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG icons are safe. ?>
			</div>
			<div class="nbuf-activity-content">
				<div class="nbuf-activity-message"><?php echo $display_msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method handles escaping. ?></div>
				<div class="nbuf-activity-meta">
					<span class="nbuf-activity-time" title="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $created_at ) ) ); ?>">
						<?php echo esc_html( $relative_time ); ?>
					</span>
					<?php if ( ! empty( $ip_address ) ) : ?>
						<span class="nbuf-activity-ip"><?php echo esc_html( $ip_address ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler for loading more activity.
	 *
	 * @since 1.5.2
	 */
	public static function ajax_load_activity(): void {
		/* Verify nonce */
		if ( ! check_ajax_referer( 'nbuf_load_activity', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'nobloat-user-foundry' ) ) );
		}

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		$activities  = self::get_user_activity( $user_id, $page, self::ITEMS_PER_PAGE );
		$total_count = self::get_user_activity_count( $user_id );
		$shown_count = ( $page - 1 ) * self::ITEMS_PER_PAGE + count( $activities );
		$has_more    = $shown_count < $total_count;

		$html = '';
		foreach ( $activities as $activity ) {
			$html .= self::render_activity_item( $activity );
		}

		wp_send_json_success(
			array(
				'html'        => $html,
				'has_more'    => $has_more,
				'shown_count' => $shown_count,
				'total_count' => $total_count,
			)
		);
	}

	/**
	 * Get CSS for activity dashboard.
	 *
	 * @since  1.5.2
	 * @return string CSS styles.
	 */
	public static function get_css(): string {
		return '
.nbuf-activity-dashboard {
	margin-top: 20px;
}

.nbuf-activity-dashboard h3 {
	font-size: 16px;
	font-weight: 600;
	margin: 0 0 16px 0;
	color: #1d2327;
}

.nbuf-activity-empty {
	color: #646970;
	font-style: italic;
}

.nbuf-activity-timeline {
	display: flex;
	flex-direction: column;
	gap: 0;
}

.nbuf-activity-item {
	display: flex;
	gap: 12px;
	padding: 12px 0;
	border-bottom: 1px solid #e0e0e0;
}

.nbuf-activity-item:last-child {
	border-bottom: none;
}

.nbuf-activity-icon {
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: #f0f0f1;
	color: #50575e;
}

.nbuf-activity-success .nbuf-activity-icon {
	background: #e7f7e7;
	color: #00a32a;
}

.nbuf-activity-failed .nbuf-activity-icon {
	background: #fce9e9;
	color: #d63638;
}

.nbuf-activity-warning .nbuf-activity-icon {
	background: #fcf3e7;
	color: #dba617;
}

.nbuf-activity-content {
	flex: 1;
	min-width: 0;
}

.nbuf-activity-message {
	font-size: 14px;
	color: #1d2327;
	margin-bottom: 4px;
}

.nbuf-activity-meta {
	display: flex;
	gap: 12px;
	font-size: 12px;
	color: #646970;
}

.nbuf-activity-ip {
	font-family: monospace;
}

.nbuf-activity-load-more {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-top: 16px;
	padding-top: 16px;
	border-top: 1px solid #e0e0e0;
}

.nbuf-activity-count {
	font-size: 12px;
	color: #646970;
}

#nbuf-load-more-activity:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}
';
	}
}
