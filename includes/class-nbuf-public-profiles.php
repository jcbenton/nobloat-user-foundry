<?php
/**
 * Public Profile Pages
 *
 * Handles public-facing user profile pages with customizable privacy settings.
 *
 * Features:
 * - Custom URL structure (e.g., site.com/profile/username)
 * - User-controlled privacy (public/members-only/private)
 * - Responsive profile template with cover photo
 * - Minimal default CSS with extensive classes for customization
 * - SEO-friendly with proper meta tags
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Public_Profiles
 *
 * Handles public-facing user profile pages with customizable privacy.
 */
class NBUF_Public_Profiles {


	/**
	 * Initialize public profiles
	 */
	public static function init() {
		// Register rewrite rules.
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );

		// Add query vars.
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );

		// Handle profile page requests.
		add_action( 'template_redirect', array( __CLASS__, 'handle_profile_request' ) );

		// Enqueue profile page CSS.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_profile_css' ) );
	}

	/**
	 * Register custom rewrite rules for profile URLs
	 */
	public static function register_rewrite_rules() {
		$slug = NBUF_Options::get( 'nbuf_profile_page_slug', 'profile' );

		add_rewrite_rule(
			'^' . $slug . '/([^/]+)/?$',
			'index.php?nbuf_profile_user=$matches[1]',
			'top'
		);
	}

	/**
	 * Add custom query vars
	 *
	 * @param  array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'nbuf_profile_user';
		return $vars;
	}

	/**
	 * Handle profile page requests
	 */
	public static function handle_profile_request() {
		$username = get_query_var( 'nbuf_profile_user' );

		if ( empty( $username ) ) {
			return;
		}

		// Get user by username.
		$user = get_user_by( 'login', $username );

		if ( ! $user ) {
			// Try by nicename (slug).
			$user = get_user_by( 'slug', $username );
		}

		if ( ! $user ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( '404' );
			exit;
		}

		// Check privacy settings.
		if ( ! self::can_view_profile( $user->ID ) ) {
			// Redirect to login or show access denied.
			$login_page_id = NBUF_Options::get( 'nbuf_page_login' );

			if ( $login_page_id ) {
				/*
				* Sanitize and validate redirect_to to prevent header injection
				*/
             // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by remove_query_arg() and rawurlencode() below.
				$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
				$redirect_to = remove_query_arg( array( '_wpnonce', 'action' ), $redirect_to );
				wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), get_permalink( $login_page_id ) ) );
				exit;
			} else {
				// Show 403 or redirect to home.
				wp_safe_redirect( home_url() );
				exit;
			}
		}

		// Render the profile page.
		self::render_profile_page( $user );
		exit;
	}

	/**
	 * Check if current user can view a profile
	 *
	 * @param  int $user_id User ID to check.
	 * @return bool True if can view, false otherwise.
	 */
	public static function can_view_profile( $user_id ) {
		$user_data = NBUF_User_Data::get( $user_id );
		$privacy   = ! empty( $user_data['profile_privacy'] ) ? $user_data['profile_privacy'] : NBUF_Options::get( 'nbuf_profile_default_privacy', 'members_only' );

		// Public profiles are visible to everyone.
		if ( 'public' === $privacy ) {
			return true;
		}

		// Private profiles are only visible to the owner.
		if ( 'private' === $privacy ) {
			return is_user_logged_in() && get_current_user_id() === $user_id;
		}

		// Members-only profiles require login.
		if ( 'members_only' === $privacy ) {
			return is_user_logged_in();
		}

		return false;
	}

	/**
	 * Render profile page
	 *
	 * @param WP_User $user User object.
	 */
	public static function render_profile_page( $user ) {
		// Get user data.
		$user_data = NBUF_User_Data::get( $user->ID );

		// Get profile photo.
		$profile_photo = NBUF_Profile_Photos::get_profile_photo( $user->ID, 150 );

		// Get cover photo.
		$cover_photo = ! empty( $user_data['cover_photo_url'] ) ? $user_data['cover_photo_url'] : '';
		$allow_cover = NBUF_Options::get( 'nbuf_profile_allow_cover_photos', true );

		// Get bio and other info.
		$bio          = get_user_meta( $user->ID, 'description', true );
		$display_name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
		$first_name   = get_user_meta( $user->ID, 'first_name', true );
		$last_name    = get_user_meta( $user->ID, 'last_name', true );

		// Get user registration date.
		$registered      = $user->user_registered;
		$registered_date = mysql2date( get_option( 'date_format' ), $registered );

		// Start output buffering.
		ob_start();

		// Output HTML head.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $display_name ); ?> - <?php bloginfo( 'name' ); ?></title>
		<?php wp_head(); ?>
		</head>
		<body <?php body_class( 'nbuf-profile-page-body' ); ?>>

		<div class="nbuf-profile-page">
			<!-- Profile Header with Cover Photo -->
			<div class="nbuf-profile-header">
		<?php if ( $allow_cover && ! empty( $cover_photo ) ) : ?>
					<div class="nbuf-profile-cover" style="background-image: url('<?php echo esc_url( $cover_photo ); ?>');">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php else : ?>
					<div class="nbuf-profile-cover nbuf-profile-cover-default">
						<div class="nbuf-profile-cover-overlay"></div>
					</div>
				<?php endif; ?>

				<div class="nbuf-profile-avatar-wrap">
					<img src="<?php echo esc_url( $profile_photo ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="nbuf-profile-avatar" width="150" height="150">
				</div>
			</div>

			<!-- Profile Info -->
			<div class="nbuf-profile-content">
				<div class="nbuf-profile-info">
					<h1 class="nbuf-profile-name"><?php echo esc_html( $display_name ); ?></h1>
					<p class="nbuf-profile-username">@<?php echo esc_html( $user->user_login ); ?></p>

		<?php if ( ! empty( $bio ) ) : ?>
						<div class="nbuf-profile-bio">
			<?php echo wpautop( wp_kses_post( $bio ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content sanitized by wp_kses_post(). ?>
						</div>
		<?php endif; ?>

					<div class="nbuf-profile-meta">
						<span class="nbuf-profile-meta-item">
							<svg class="nbuf-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M8 8a3 3 0 100-6 3 3 0 000 6zm0 1.5c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
							</svg>
		<?php
			/* translators: %s: User registration date */
			printf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( $registered_date ) );
		?>
						</span>
					</div>
				</div>

		<?php
		/**
		 * Hook for adding custom content to profile page
		 *
		 * @param WP_User $user User object.
		 * @param array   $user_data User data from custom table.
		 */
		do_action( 'nbuf_public_profile_content', $user, $user_data );
		?>

		<?php if ( is_user_logged_in() && get_current_user_id() === $user->ID ) : ?>
					<div class="nbuf-profile-actions">
			<?php
			$account_page_id = NBUF_Options::get( 'nbuf_page_account' );
			if ( $account_page_id ) :
				?>
							<a href="<?php echo esc_url( get_permalink( $account_page_id ) ); ?>" class="nbuf-button nbuf-button-primary">
				<?php esc_html_e( 'Edit Profile', 'nobloat-user-foundry' ); ?>
							</a>
			<?php endif; ?>
					</div>
		<?php endif; ?>
			</div>
		</div>

		<?php wp_footer(); ?>
		</body>
		</html>
		<?php

		// Output the buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output with escaped content.
	}

	/**
	 * Enqueue profile page CSS
	 */
	public static function enqueue_profile_css() {
		$username = get_query_var( 'nbuf_profile_user' );

		if ( empty( $username ) ) {
			return;
		}

		// Enqueue default profile CSS.
		wp_add_inline_style( 'wp-block-library', self::get_default_css() );

		// Enqueue custom CSS if set.
		$custom_css = NBUF_Options::get( 'nbuf_profile_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'wp-block-library', wp_strip_all_tags( $custom_css ) );
		}
	}

	/**
	 * Get default profile CSS
	 *
	 * @return string CSS code.
	 */
	public static function get_default_css() {
		return '
		/* NoBloat User Foundry - Profile Page CSS */

		/* Reset and Layout */
		.nbuf-profile-page {
			max-width: 1200px;
			margin: 0 auto;
			background: #fff;
			min-height: 100vh;
		}

		/* Header and Cover Photo */
		.nbuf-profile-header {
			position: relative;
			margin-bottom: 80px;
		}

		.nbuf-profile-cover {
			height: 300px;
			background-size: cover;
			background-position: center;
			background-repeat: no-repeat;
			position: relative;
		}

		.nbuf-profile-cover-default {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		}

		.nbuf-profile-cover-overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.1);
		}

		/* Avatar */
		.nbuf-profile-avatar-wrap {
			position: absolute;
			bottom: -75px;
			left: 50%;
			transform: translateX(-50%);
			width: 150px;
			height: 150px;
			border-radius: 50%;
			border: 5px solid #fff;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
			overflow: hidden;
			background: #fff;
		}

		.nbuf-profile-avatar {
			width: 100%;
			height: 100%;
			object-fit: cover;
			border-radius: 50%;
		}

		/* Content Area */
		.nbuf-profile-content {
			padding: 20px;
		}

		.nbuf-profile-info {
			text-align: center;
			margin-bottom: 30px;
		}

		.nbuf-profile-name {
			font-size: 2rem;
			font-weight: 700;
			margin: 0 0 5px 0;
			color: #1a202c;
		}

		.nbuf-profile-username {
			font-size: 1rem;
			color: #718096;
			margin: 0 0 20px 0;
		}

		.nbuf-profile-bio {
			max-width: 600px;
			margin: 20px auto;
			color: #4a5568;
			line-height: 1.6;
			font-size: 1rem;
		}

		.nbuf-profile-meta {
			display: flex;
			justify-content: center;
			gap: 20px;
			flex-wrap: wrap;
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #e2e8f0;
		}

		.nbuf-profile-meta-item {
			display: flex;
			align-items: center;
			gap: 5px;
			color: #718096;
			font-size: 0.9rem;
		}

		.nbuf-icon {
			opacity: 0.7;
		}

		/* Actions */
		.nbuf-profile-actions {
			text-align: center;
			margin-top: 30px;
		}

		.nbuf-button {
			display: inline-block;
			padding: 12px 24px;
			border-radius: 4px;
			text-decoration: none;
			font-weight: 600;
			transition: all 0.2s;
			border: none;
			cursor: pointer;
		}

		.nbuf-button-primary {
			background: #0073aa;
			color: #fff;
		}

		.nbuf-button-primary:hover {
			background: #005a87;
			color: #fff;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.nbuf-profile-cover {
				height: 200px;
			}

			.nbuf-profile-avatar-wrap {
				width: 120px;
				height: 120px;
				bottom: -60px;
			}

			.nbuf-profile-header {
				margin-bottom: 70px;
			}

			.nbuf-profile-name {
				font-size: 1.5rem;
			}

			.nbuf-profile-content {
				padding: 15px;
			}
		}

		@media (max-width: 480px) {
			.nbuf-profile-cover {
				height: 150px;
			}

			.nbuf-profile-avatar-wrap {
				width: 100px;
				height: 100px;
				bottom: -50px;
			}

			.nbuf-profile-header {
				margin-bottom: 60px;
			}

			.nbuf-profile-name {
				font-size: 1.25rem;
			}

			.nbuf-profile-meta {
				flex-direction: column;
				gap: 10px;
			}
		}
		';
	}

	/**
	 * Get profile URL for a user
	 *
	 * @param  int|string $user User ID or username.
	 * @return string Profile URL.
	 */
	public static function get_profile_url( $user ) {
		if ( is_numeric( $user ) ) {
			$user_obj = get_userdata( $user );
			if ( ! $user_obj ) {
				return '';
			}
			$username = $user_obj->user_login;
		} else {
			$username = $user;
		}

		$slug = NBUF_Options::get( 'nbuf_profile_page_slug', 'profile' );
		return home_url( '/' . $slug . '/' . $username );
	}

	/**
	 * Flush rewrite rules (call after changing slug)
	 */
	public static function flush_rewrite_rules() {
		self::register_rewrite_rules();
		flush_rewrite_rules();
	}
}
