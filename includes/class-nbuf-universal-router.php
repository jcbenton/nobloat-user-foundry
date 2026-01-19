<?php
/**
 * NoBloat User Foundry - Universal Router
 *
 * Virtual page routing - no WordPress page required.
 * Intercepts URLs and renders content directly.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Universal_Router
 *
 * Handles all user-facing pages via virtual page routing.
 */
class NBUF_Universal_Router {

	/**
	 * View configuration: view key => [method, title].
	 *
	 * @var array<string, array{method: string, title: string}>
	 */
	private static $views = array(
		'login'           => array(
			'method' => 'render_login',
			'title'  => 'Login',
		),
		'register'        => array(
			'method' => 'render_register',
			'title'  => 'Register',
		),
		'account'         => array(
			'method' => 'render_account',
			'title'  => 'Account',
		),
		'verify'          => array(
			'method' => 'render_verify',
			'title'  => 'Verify Email',
		),
		'forgot-password' => array(
			'method' => 'render_forgot_password',
			'title'  => 'Forgot Password',
		),
		'reset-password'  => array(
			'method' => 'render_reset_password',
			'title'  => 'Reset Password',
		),
		'logout'          => array(
			'method' => 'render_logout',
			'title'  => 'Logout',
		),
		'2fa'             => array(
			'method' => 'render_2fa',
			'title'  => 'Two-Factor Authentication',
		),
		'2fa-setup'       => array(
			'method' => 'render_2fa_setup',
			'title'  => '2FA Setup',
		),
		'members'         => array(
			'method' => 'render_members',
			'title'  => 'Members',
		),
		'profile'         => array(
			'method' => 'render_profile',
			'title'  => 'Profile',
		),
		'magic-link'      => array(
			'method' => 'render_magic_link',
			'title'  => 'Magic Link Login',
		),
		'accept-tos'      => array(
			'method' => 'render_accept_tos',
			'title'  => 'Terms of Service',
		),
	);

	/**
	 * Current view being rendered.
	 *
	 * @var string
	 */
	private static $current_view = null;

	/**
	 * Current subview being rendered.
	 *
	 * @var string
	 */
	private static $current_subview = null;

	/**
	 * Initialize the router.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( is_admin() ) {
			return;
		}

		/* Intercept requests at template_redirect - cleanest approach */
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_request' ), 0 );
	}

	/**
	 * Check if this is our request and handle it.
	 *
	 * @return void
	 */
	public static function maybe_handle_request(): void {
		/* Parse URL to see if it's ours */
		$parsed = self::parse_url();

		if ( ! $parsed ) {
			return;
		}

		self::$current_view    = $parsed['view'];
		self::$current_subview = $parsed['subview'];

		/* Override any 404 status WordPress may have set for virtual URL */
		status_header( 200 );

		/*
		 * Process form submissions BEFORE rendering.
		 * Form handlers may redirect, so they must run first.
		 * Since we intercept at priority 0 and exit, normal template_redirect
		 * handlers won't run - we need to call them explicitly.
		 */
		if ( class_exists( 'NBUF_Shortcodes' ) ) {
			/* Login form handler */
			if ( method_exists( 'NBUF_Shortcodes', 'maybe_handle_login' ) ) {
				NBUF_Shortcodes::maybe_handle_login();
			}
			/* Registration form handler */
			if ( method_exists( 'NBUF_Shortcodes', 'maybe_handle_registration' ) ) {
				NBUF_Shortcodes::maybe_handle_registration();
			}
			/* Password reset request handler */
			if ( method_exists( 'NBUF_Shortcodes', 'maybe_handle_request_reset' ) ) {
				NBUF_Shortcodes::maybe_handle_request_reset();
			}
			/* Password reset form handler */
			if ( method_exists( 'NBUF_Shortcodes', 'maybe_handle_password_reset' ) ) {
				NBUF_Shortcodes::maybe_handle_password_reset();
			}
			/* Account page form actions */
			if ( method_exists( 'NBUF_Shortcodes', 'maybe_handle_account_actions' ) ) {
				NBUF_Shortcodes::maybe_handle_account_actions();
			}
		}

		/* 2FA verification form handler */
		if ( class_exists( 'NBUF_2FA_Login' ) && method_exists( 'NBUF_2FA_Login', 'maybe_handle_2fa_verification' ) ) {
			NBUF_2FA_Login::maybe_handle_2fa_verification();
		}

		/* Magic link token handler - must run before rendering */
		if ( class_exists( 'NBUF_Magic_Links' ) && method_exists( 'NBUF_Magic_Links', 'maybe_handle_magic_link' ) ) {
			NBUF_Magic_Links::maybe_handle_magic_link();
		}

		/* Render the page and exit */
		self::render_page();
		exit;
	}

	/**
	 * Render the full page.
	 *
	 * @return void
	 */
	private static function render_page(): void {
		$view = self::$current_view;

		if ( ! $view || ! isset( self::$views[ $view ] ) ) {
			status_header( 404 );
			return;
		}

		/* Set 200 OK status */
		status_header( 200 );

		/* Get page title */
		$page_title = self::$views[ $view ]['title'];

		/* Set document title via WordPress filters */
		add_filter(
			'pre_get_document_title',
			function () use ( $page_title ) {
				return $page_title . ' - ' . get_bloginfo( 'name' );
			}
		);

		add_filter(
			'document_title_parts',
			function ( $title_parts ) use ( $page_title ) {
				$title_parts['title'] = $page_title;
				return $title_parts;
			}
		);

		add_filter(
			'wp_title',
			function () use ( $page_title ) {
				return $page_title . ' - ' . get_bloginfo( 'name' );
			}
		);

		/* Get the content */
		$content = self::render_view( $view );

		/* Enqueue our assets */
		self::enqueue_assets();

		/* Add body class for CSS specificity (beats page builder styles) */
		add_filter(
			'body_class',
			function ( $classes ) use ( $view ) {
				$classes[] = 'nbuf-page';
				$classes[] = 'nbuf-page-' . $view;
				return $classes;
			}
		);

		/* Output the page using theme's header/footer */
		get_header();
		?>
		<div class="nbuf-virtual-page-wrapper">
			<div id="primary" class="content-area">
				<main id="main" class="site-main">
					<article class="page type-page status-publish hentry nbuf-virtual-page">
						<div class="entry-content">
							<?php
							/*
							 * SECURITY: Output escaping is handled by render methods in NBUF_Shortcodes.
							 *
							 * WHY THIS IS SAFE:
							 * - $content comes exclusively from internal render methods (render_login, render_account, etc.)
							 * - All render methods escape output using esc_html(), esc_attr(), wp_kses_post()
							 * - No user input reaches this point without prior sanitization
							 * - Content is generated server-side from trusted templates
							 *
							 * WHEN THIS WOULD NOT BE SAFE:
							 * - If $content accepted raw user input without sanitization
							 * - If a render method failed to escape dynamic values
							 * - If external/untrusted content was passed to the router
							 *
							 * MAINTAINER NOTE: Any new render method MUST escape all dynamic output.
							 * Review render methods when modifying: NBUF_Shortcodes::render_*()
							 */
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $content;
							?>
						</div>
					</article>
				</main>
			</div>
		</div>
		<?php
		get_footer();
	}

	/**
	 * Enqueue CSS/JS for current view.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$view = self::$current_view;

		/* Map views to CSS page types */
		$css_map = array(
			'login'           => 'login',
			'register'        => 'registration',
			'account'         => 'account',
			'forgot-password' => 'reset',
			'reset-password'  => 'reset',
			'2fa'             => '2fa',
			'2fa-setup'       => '2fa',
			'profile'         => 'profile',
			'members'         => 'member-directory',
			'accept-tos'      => 'tos',
			'magic-link'      => 'login',
		);

		if ( isset( $css_map[ $view ] ) && class_exists( 'NBUF_CSS_Manager' ) ) {
			$page_type = $css_map[ $view ];

			$css_files = array(
				'login'            => array( 'nbuf-login', 'login-page', 'nbuf_login_page_css', 'nbuf_css_write_failed_login' ),
				'registration'     => array( 'nbuf-registration', 'registration-page', 'nbuf_registration_page_css', 'nbuf_css_write_failed_registration' ),
				'reset'            => array( 'nbuf-reset', 'reset-page', 'nbuf_reset_page_css', 'nbuf_css_write_failed_reset' ),
				'account'          => array( 'nbuf-account', 'account-page', 'nbuf_account_page_css', 'nbuf_css_write_failed_account' ),
				'2fa'              => array( 'nbuf-2fa', '2fa-setup', 'nbuf_2fa_page_css', 'nbuf_css_write_failed_2fa' ),
				'profile'          => array( 'nbuf-profile', 'profile', 'nbuf_profile_custom_css', 'nbuf_css_write_failed_profile' ),
				'member-directory' => array( 'nbuf-member-directory', 'member-directory', 'nbuf_member_directory_custom_css', 'nbuf_css_write_failed_member_directory' ),
				'tos'              => array( 'nbuf-tos', 'tos-acceptance', 'nbuf_tos_page_css', 'nbuf_css_write_failed_tos' ),
			);

			if ( isset( $css_files[ $page_type ] ) ) {
				list( $handle, $filename, $db_option, $token_key ) = $css_files[ $page_type ];

				$css_load = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
				if ( $css_load ) {
					NBUF_CSS_Manager::enqueue_css( $handle, $filename, $db_option, $token_key );
				}
			}
		}

		/* Account page JS */
		if ( 'account' === $view ) {
			NBUF_Asset_Minifier::enqueue_script( 'nbuf-account-page', 'assets/js/frontend/account-page.js', array() );

			/*
			 * Get subtab from query param.
			 */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading subtab for display purposes.
			$active_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : '';

			wp_localize_script(
				'nbuf-account-page',
				'nbufAccountData',
				array(
					'activeTab'    => self::$current_subview ? self::$current_subview : '',
					'activeSubtab' => $active_subtab,
					'baseSlug'     => self::get_base_slug(),
				)
			);
		}
	}

	/**
	 * Parse current URL to extract view and subview.
	 *
	 * @return array{view: string, subview: string}|false Array with view/subview or false if not our URL.
	 */
	public static function parse_url() {
		$base_slug = self::get_base_slug();

		if ( empty( $base_slug ) ) {
			return false;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

		/* Check if path starts with base slug */
		if ( strpos( $path, $base_slug ) !== 0 ) {
			return false;
		}

		/* Extract remaining path */
		$remaining = trim( substr( $path, strlen( $base_slug ) ), '/' );
		$segments  = $remaining ? explode( '/', $remaining ) : array();

		$view    = isset( $segments[0] ) && '' !== $segments[0] ? $segments[0] : self::get_default_view();
		$subview = isset( $segments[1] ) ? $segments[1] : '';

		/* Validate view */
		if ( ! isset( self::$views[ $view ] ) ) {
			$view = self::get_default_view();
		}

		return array(
			'view'    => $view,
			'subview' => $subview,
		);
	}

	/**
	 * Get base slug from settings.
	 *
	 * @return string Base slug.
	 */
	public static function get_base_slug() {
		return NBUF_Options::get( 'nbuf_universal_base_slug', 'user-foundry' );
	}

	/**
	 * Get default view.
	 *
	 * @return string Default view key.
	 */
	public static function get_default_view() {
		return NBUF_Options::get( 'nbuf_universal_default_view', 'account' );
	}

	/**
	 * Get current view.
	 *
	 * @return string View key.
	 */
	public static function get_current_view() {
		return self::$current_view ? self::$current_view : '';
	}

	/**
	 * Get current subview.
	 *
	 * @return string Subview key.
	 */
	public static function get_current_subview() {
		return self::$current_subview ? self::$current_subview : '';
	}

	/**
	 * Render view content.
	 *
	 * @param  string $view View key.
	 * @return string HTML content.
	 */
	public static function render_view( $view ) {
		if ( ! isset( self::$views[ $view ] ) ) {
			return '<p>' . esc_html__( 'Page not found.', 'nobloat-user-foundry' ) . '</p>';
		}

		$method = self::$views[ $view ]['method'];

		if ( ! method_exists( __CLASS__, $method ) ) {
			return '<p>' . esc_html__( 'View not available.', 'nobloat-user-foundry' ) . '</p>';
		}

		return self::$method();
	}

	/**
	 * Get URL for a view.
	 *
	 * @param  string               $view    View key.
	 * @param  string               $subview Optional subview.
	 * @param  array<string, mixed> $args    Optional query args.
	 * @return string URL.
	 */
	public static function get_url( string $view = '', string $subview = '', array $args = array() ): string {
		$base_slug = self::get_base_slug();
		$path      = $base_slug;

		if ( $view ) {
			$path .= '/' . $view;
		}

		if ( $subview ) {
			$path .= '/' . $subview;
		}

		$url = home_url( '/' . $path . '/' );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	/**
	 * Check if on a universal page.
	 *
	 * @return bool True if on universal page.
	 */
	public static function is_universal_request() {
		return null !== self::$current_view;
	}

	/**
	 * Get title for a view.
	 *
	 * @param  string $view View key.
	 * @return string Title or empty string.
	 */
	public static function get_view_title( $view ) {
		if ( isset( self::$views[ $view ] ) ) {
			return self::$views[ $view ]['title'];
		}
		return '';
	}

	/**
	 * Flush rewrite rules (no-op for virtual pages).
	 *
	 * Kept for backward compatibility with activator.
	 *
	 * @return void
	 */
	public static function flush_rules(): void {
		/* Virtual pages don't use rewrite rules - nothing to flush */
	}

	/*
	 * =========================================================
	 * VIEW RENDERERS
	 * =========================================================
	 */

	/**
	 * Render login form.
	 *
	 * @return string HTML.
	 */
	private static function render_login() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_login_redirect_url() );
			exit;
		}
		return NBUF_Shortcodes::sc_login_form( array() );
	}

	/**
	 * Get the redirect URL for logged-in users based on settings.
	 *
	 * @since  1.5.0
	 * @return string Redirect URL.
	 */
	private static function get_login_redirect_url() {
		$login_redirect_setting = NBUF_Options::get( 'nbuf_login_redirect', 'account' );

		switch ( $login_redirect_setting ) {
			case 'account':
				return self::get_url( 'account' );

			case 'admin':
				return admin_url();

			case 'home':
				return home_url( '/' );

			case 'custom':
				$custom_url = NBUF_Options::get( 'nbuf_login_redirect_custom', '' );
				if ( $custom_url ) {
					/* Handle both absolute URLs and relative paths */
					if ( strpos( $custom_url, 'http' ) === 0 ) {
						return $custom_url;
					}
					return home_url( $custom_url );
				}
				return self::get_url( 'account' );

			default:
				return home_url( '/' );
		}
	}

	/**
	 * Render registration form.
	 *
	 * @return string HTML.
	 */
	private static function render_register() {
		if ( is_user_logged_in() ) {
			return self::render_already_logged_in();
		}
		return NBUF_Shortcodes::sc_registration_form( array() );
	}

	/**
	 * Render account page.
	 *
	 * @return string HTML.
	 */
	private static function render_account() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_url( 'login', '', array( 'redirect_to' => self::get_url( 'account' ) ) ) );
			exit;
		}

		/* Set tab from subview */
		if ( self::$current_subview ) {
			$_GET['tab'] = self::$current_subview;
		}

		return NBUF_Shortcodes::sc_account_page( array() );
	}

	/**
	 * Render email verification page.
	 *
	 * @return string HTML.
	 */
	private static function render_verify() {
		return NBUF_Shortcodes::sc_verify_page( array() );
	}

	/**
	 * Render forgot password form.
	 *
	 * @return string HTML.
	 */
	private static function render_forgot_password() {
		if ( is_user_logged_in() ) {
			return self::render_already_logged_in();
		}
		return NBUF_Shortcodes::sc_request_reset_form( array() );
	}

	/**
	 * Render reset password form.
	 *
	 * @return string HTML.
	 */
	private static function render_reset_password() {
		return NBUF_Shortcodes::sc_reset_form( array() );
	}

	/**
	 * Render logout.
	 *
	 * @return string HTML.
	 */
	private static function render_logout() {
		return NBUF_Shortcodes::sc_logout( array() );
	}

	/**
	 * Render 2FA verification.
	 *
	 * @return string HTML.
	 */
	private static function render_2fa() {
		return NBUF_Shortcodes::sc_2fa_verify( array() );
	}

	/**
	 * Render 2FA setup.
	 *
	 * @return string HTML.
	 */
	private static function render_2fa_setup() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( self::get_url( 'login' ) );
			exit;
		}
		return NBUF_Shortcodes::sc_totp_setup( array() );
	}

	/**
	 * Render members directory.
	 *
	 * @return string HTML.
	 */
	private static function render_members() {
		return NBUF_Shortcodes::sc_members( array() );
	}

	/**
	 * Render public profile.
	 *
	 * @return string HTML.
	 */
	private static function render_profile() {
		$username = self::$current_subview;

		if ( empty( $username ) ) {
			/* No username - show current user's profile or redirect */
			if ( is_user_logged_in() ) {
				$user     = wp_get_current_user();
				$username = $user->user_login;
			} else {
				return '<p>' . esc_html__( 'No profile specified.', 'nobloat-user-foundry' ) . '</p>';
			}
		}

		return NBUF_Shortcodes::sc_profile( array( 'user' => $username ) );
	}

	/**
	 * Render magic link form/handler.
	 *
	 * @return string HTML.
	 */
	private static function render_magic_link() {
		/* Check if magic links are enabled */
		if ( ! class_exists( 'NBUF_Magic_Links' ) || ! NBUF_Magic_Links::is_enabled() ) {
			return '<div class="nbuf-message nbuf-message-info">' .
				esc_html__( 'Magic links are not enabled.', 'nobloat-user-foundry' ) .
				'</div>';
		}

		if ( is_user_logged_in() ) {
			return self::render_already_logged_in();
		}

		return NBUF_Magic_Links::shortcode_form( array() );
	}

	/**
	 * Render Terms of Service acceptance page.
	 *
	 * @since  1.5.2
	 * @return string HTML.
	 */
	private static function render_accept_tos() {
		/* Check if ToS is enabled */
		if ( ! class_exists( 'NBUF_ToS' ) || ! NBUF_ToS::is_enabled() ) {
			return '<div class="nbuf-tos-wrapper"><div class="nbuf-tos-message nbuf-tos-message-info">' .
				esc_html__( 'Terms of Service tracking is not enabled.', 'nobloat-user-foundry' ) .
				'</div></div>';
		}

		/* Redirect non-logged-in users to login page */
		if ( ! is_user_logged_in() ) {
			$login_url = self::get_url( 'login' );
			wp_safe_redirect( $login_url );
			exit;
		}

		return NBUF_ToS::render_acceptance_page();
	}

	/**
	 * Render "already logged in" message.
	 *
	 * @return string HTML.
	 */
	private static function render_already_logged_in() {
		$user = wp_get_current_user();

		$output  = '<div class="nbuf-message nbuf-message-info">';
		$output .= '<p>' . sprintf(
			/* translators: %s: user display name */
			esc_html__( 'You are already logged in as %s.', 'nobloat-user-foundry' ),
			'<strong>' . esc_html( $user->display_name ) . '</strong>'
		) . '</p>';
		$output .= '<p><a href="' . esc_url( self::get_url( 'account' ) ) . '">' . esc_html__( 'Go to Account', 'nobloat-user-foundry' ) . '</a>';
		$output .= ' | ';
		$output .= '<a href="' . esc_url( self::get_url( 'logout' ) ) . '">' . esc_html__( 'Logout', 'nobloat-user-foundry' ) . '</a></p>';
		$output .= '</div>';

		return $output;
	}
}
