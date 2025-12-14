<?php
/**
 * NoBloat User Foundry - Appearance Page
 *
 * Handles the Appearance submenu page with tabs for:
 * - Email Templates
 * - Policy Templates
 * - Form Templates (HTML for login, registration, reset, account forms)
 * - CSS Styles
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appearance Page Class
 */
class NBUF_Appearance {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu_page' ), 11 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add the Appearance submenu page.
	 */
	public static function add_submenu_page() {
		add_submenu_page(
			'nobloat-foundry',
			__( 'Appearance', 'nobloat-user-foundry' ),
			__( 'Appearance', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-appearance',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		/* Check for both possible hook variants (same pattern as Settings class) */
		$allowed_hooks = array(
			'nobloat-foundry_page_nobloat-foundry-appearance',
			'user-foundry_page_nobloat-foundry-appearance',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		/* Enqueue admin CSS (contains tab styles) */
		wp_enqueue_style(
			'nbuf-admin-css',
			NBUF_PLUGIN_URL . 'assets/css/admin/admin.css',
			array(),
			NBUF_VERSION
		);

		/* Enqueue consolidated admin UI styles (extracted from inline styles) */
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );
		$ui_css_file  = $use_minified ? 'admin-ui.min.css' : 'admin-ui.css';
		wp_enqueue_style(
			'nbuf-admin-ui',
			NBUF_PLUGIN_URL . 'assets/css/admin/' . $ui_css_file,
			array(),
			NBUF_VERSION
		);

		/* Enqueue admin JavaScript */
		wp_enqueue_script(
			'nbuf-admin-js',
			NBUF_PLUGIN_URL . 'assets/js/admin/admin.js',
			array( 'jquery' ),
			NBUF_VERSION,
			true
		);

		wp_localize_script(
			'nbuf-admin-js',
			'nobloatEV',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nbuf_ajax' ),
			)
		);
	}

	/**
	 * Get the tab structure for the Appearance page.
	 *
	 * @return array Tab structure definition.
	 */
	public static function get_tab_structure() {
		return array(
			'emails'     => array(
				'label'   => __( 'Email Templates', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'verification'       => __( 'Verification', 'nobloat-user-foundry' ),
					'welcome'            => __( 'Welcome', 'nobloat-user-foundry' ),
					'expiration'         => __( 'Expiration', 'nobloat-user-foundry' ),
					'2fa'                => __( '2FA', 'nobloat-user-foundry' ),
					'password-reset'     => __( 'Password Reset', 'nobloat-user-foundry' ),
					'admin-notification' => __( 'Admin Notification', 'nobloat-user-foundry' ),
				),
			),
			'policies'   => array(
				'label'   => __( 'Policy Templates', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'privacy' => __( 'Privacy Policy', 'nobloat-user-foundry' ),
					'terms'   => __( 'Terms of Use', 'nobloat-user-foundry' ),
				),
			),
			'forms'      => array(
				'label'   => __( 'Form Templates', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'login'         => __( 'Login Form', 'nobloat-user-foundry' ),
					'registration'  => __( 'Registration Form', 'nobloat-user-foundry' ),
					'request-reset' => __( 'Request Reset', 'nobloat-user-foundry' ),
					'reset'         => __( 'Password Reset', 'nobloat-user-foundry' ),
					'account'       => __( 'Account Page', 'nobloat-user-foundry' ),
					'2fa'           => __( '2FA Verify', 'nobloat-user-foundry' ),
				),
			),
			'styles'     => array(
				'label'   => __( 'CSS Styles', 'nobloat-user-foundry' ),
				'subtabs' => array(
					'login'    => __( 'Login', 'nobloat-user-foundry' ),
					'register' => __( 'Register', 'nobloat-user-foundry' ),
					'account'  => __( 'Account', 'nobloat-user-foundry' ),
					'reset'    => __( 'Reset', 'nobloat-user-foundry' ),
					'2fa'      => __( '2FA', 'nobloat-user-foundry' ),
					'profiles' => __( 'Profiles', 'nobloat-user-foundry' ),
					'settings' => __( 'Settings', 'nobloat-user-foundry' ),
				),
			),
		);
	}

	/**
	 * Get the currently active outer tab.
	 *
	 * @return string Active tab slug.
	 */
	public static function get_active_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		$tab       = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'emails';
		$structure = self::get_tab_structure();

		/* Backwards compatibility: 'templates' maps to 'emails' */
		if ( 'templates' === $tab ) {
			$tab = 'emails';
		}

		if ( ! isset( $structure[ $tab ] ) ) {
			return 'emails';
		}

		return $tab;
	}

	/**
	 * Get the currently active inner tab (subtab).
	 *
	 * @return string Active subtab slug.
	 */
	public static function get_active_subtab() {
		$active_tab = self::get_active_tab();
		$structure  = self::get_tab_structure();
		$subtabs    = array_keys( $structure[ $active_tab ]['subtabs'] );

		/* Handle tabs with no subtabs */
		if ( empty( $subtabs ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only subtab selection.
		$subtab = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : $subtabs[0];

		/* Validate subtab exists in current tab */
		if ( ! in_array( $subtab, $subtabs, true ) ) {
			$subtab = $subtabs[0];
		}

		return $subtab;
	}

	/**
	 * Render the Appearance page.
	 */
	public static function render_page() {
		$structure     = self::get_tab_structure();
		$active_tab    = self::get_active_tab();
		$active_subtab = self::get_active_subtab();

		?>
		<div class="wrap" id="nbuf-settings">
			<h1><?php esc_html_e( 'Appearance', 'nobloat-user-foundry' ); ?></h1>

			<!-- Outer tabs (main navigation) -->
			<div class="nbuf-outer-tabs">
		<?php foreach ( $structure as $tab_key => $tab_data ) : ?>
					<a href="?page=nobloat-foundry-appearance&tab=<?php echo esc_attr( $tab_key ); ?>"
						class="nbuf-outer-tab-link<?php echo $active_tab === $tab_key ? ' active' : ''; ?>"
						data-tab="<?php echo esc_attr( $tab_key ); ?>">
			<?php echo esc_html( $tab_data['label'] ); ?>
					</a>
		<?php endforeach; ?>
			</div>

			<!-- Tab content areas -->
		<?php foreach ( $structure as $tab_key => $tab_data ) : ?>
				<div id="nbuf-tab-<?php echo esc_attr( $tab_key ); ?>"
					class="nbuf-outer-tab-content<?php echo $active_tab === $tab_key ? ' active' : ''; ?>">

					<!-- Inner tabs (sub-navigation) -->
					<div class="nbuf-inner-tabs">
			<?php
			$subtab_count  = 0;
			$total_subtabs = count( $tab_data['subtabs'] );
			foreach ( $tab_data['subtabs'] as $subtab_key => $subtab_label ) :
				++$subtab_count;
				$is_active = ( $active_tab === $tab_key && $active_subtab === $subtab_key );
				?>
							<a href="?page=nobloat-foundry-appearance&tab=<?php echo esc_attr( $tab_key ); ?>&subtab=<?php echo esc_attr( $subtab_key ); ?>"
								class="nbuf-inner-tab-link<?php echo $is_active ? ' active' : ''; ?>"
								data-subtab="<?php echo esc_attr( $subtab_key ); ?>">
				<?php echo esc_html( $subtab_label ); ?>
							</a>
				<?php
				if ( $subtab_count < $total_subtabs ) :
					?>
								|
								<?php
				endif;
				?>
			<?php endforeach; ?>
					</div>

					<!-- Subtab content areas -->
			<?php
			foreach ( $tab_data['subtabs'] as $subtab_key => $subtab_label ) :
				$is_active = ( $active_tab === $tab_key && $active_subtab === $subtab_key );
				?>
						<div id="nbuf-subtab-<?php echo esc_attr( $subtab_key ); ?>"
							class="nbuf-inner-tab-content<?php echo $is_active ? ' active' : ''; ?>">
				<?php self::load_subtab_content( $tab_key, $subtab_key ); ?>
						</div>
			<?php endforeach; ?>

				</div>
		<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Load the content for a subtab.
	 *
	 * @param string $tab    The outer tab slug.
	 * @param string $subtab The subtab slug.
	 */
	private static function load_subtab_content( $tab, $subtab ) {
		/* Map tabs to file paths */
		$file_map = array(
			/* Email Templates */
			'emails' => array(
				'verification'       => 'templates/verification.php',
				'welcome'            => 'templates/welcome.php',
				'expiration'         => 'templates/expiration.php',
				'2fa'                => 'templates/2fa.php',
				'password-reset'     => 'templates/password-reset.php',
				'admin-notification' => 'templates/admin-notification.php',
			),
			/* Policy Templates */
			'policies' => array(
				'privacy' => 'policies/privacy.php',
				'terms'   => 'policies/terms.php',
			),
			/* Form Templates */
			'forms' => array(
				'login'         => 'forms/login.php',
				'registration'  => 'forms/registration.php',
				'request-reset' => 'forms/request-reset.php',
				'reset'         => 'forms/reset.php',
				'account'       => 'forms/account.php',
				'2fa'           => 'forms/2fa.php',
			),
			/* CSS Styles */
			'styles' => array(
				'login'    => 'styles/login.php',
				'register' => 'styles/register.php',
				'account'  => 'styles/account.php',
				'reset'    => 'styles/reset.php',
				'2fa'      => 'styles/2fa.php',
				'profiles' => 'styles/profiles.php',
				'settings' => 'styles/settings.php',
			),
		);

		$file_path = '';

		if ( isset( $file_map[ $tab ][ $subtab ] ) ) {
			$file_path = NBUF_PLUGIN_DIR . 'includes/user-tabs/' . $file_map[ $tab ][ $subtab ];
		}

		if ( $file_path && file_exists( $file_path ) ) {
			include $file_path;
		} else {
			echo '<p>' . esc_html__( 'Content not found.', 'nobloat-user-foundry' ) . '</p>';
		}
	}
}
