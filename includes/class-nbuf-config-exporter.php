<?php
/**
 * Configuration Exporter Class
 *
 * Exports plugin settings and templates to JSON
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Config_Exporter
 *
 * Handles exporting plugin configuration to JSON.
 */
class NBUF_Config_Exporter {


	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_nbuf_export_config', array( $this, 'ajax_export_config' ) );
	}

	/**
	 * AJAX: Export configuration
	 */
	public function ajax_export_config() {
		check_ajax_referer( 'nbuf_config_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		/* Get export options */
		$export_settings  = isset( $_POST['export_settings'] ) && '1' === $_POST['export_settings'];
		$export_templates = isset( $_POST['export_templates'] ) && '1' === $_POST['export_templates'];
		$export_sensitive = isset( $_POST['export_sensitive'] ) && '1' === $_POST['export_sensitive'];
		$pretty_print     = isset( $_POST['pretty_print'] ) && '1' === $_POST['pretty_print'];

		/* Build export data */
		$export_data = array(
			'nbuf_config_version' => '1.0',
			'plugin_version'      => $this->get_plugin_version(),
			'exported_at'         => current_time( 'c' ),
			'site_url'            => get_site_url(),
		);

		if ( $export_settings ) {
			$export_data['settings'] = $this->export_settings( $export_sensitive );
		}

		if ( $export_templates ) {
			$export_data['templates'] = $this->export_templates();
		}

		/* Generate JSON */
		$json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ( $pretty_print ) {
			$json_flags |= JSON_PRETTY_PRINT;
		}

		$json = wp_json_encode( $export_data, $json_flags );

		if ( false === $json ) {
			wp_send_json_error( array( 'message' => 'Failed to generate JSON' ) );
		}

		/* Generate filename */
		$filename = 'nobloat-config-' . gmdate( 'Y-m-d-His' ) . '.json';

		/* Return download URL via data URI */
		wp_send_json_success(
			array(
				'json'     => $json,
				'filename' => $filename,
				'size'     => strlen( $json ),
			)
		);
	}

	/**
	 * Export all settings
	 *
	 * @param  bool $include_sensitive Include sensitive data.
	 * @return array Settings organized by category.
	 */
	private function export_settings( $include_sensitive = false ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_options';

		/*
		Get all options from database
		*/
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Export operation.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name, option_value FROM %i ORDER BY option_name',
				$table_name
			),
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		/* Organize settings by category */
		$settings = array(
			'system'       => array(),
			'users'        => array(),
			'security'     => array(),
			'templates'    => array(),
			'restrictions' => array(),
			'profiles'     => array(),
			'integration'  => array(),
			'tools'        => array(),
			'other'        => array(),
		);

		/* Sensitive option patterns to exclude */
		$sensitive_patterns = array(
			'api_key',
			'secret',
			'token',
			'password',
			'private_key',
		);

		foreach ( $results as $row ) {
			$option_name  = $row['option_name'];
			$option_value = $row['option_value'];

			/* Skip sensitive data if not included */
			if ( ! $include_sensitive ) {
				$is_sensitive = false;
				foreach ( $sensitive_patterns as $pattern ) {
					if ( false !== stripos( $option_name, $pattern ) ) {
						$is_sensitive = true;
						break;
					}
				}
				if ( $is_sensitive ) {
					continue;
				}
			}

			/* Unserialize if needed */
			$maybe_unserialized = maybe_unserialize( $option_value );
			if ( $maybe_unserialized !== $option_value ) {
				$option_value = $maybe_unserialized;
			}

			/* Categorize setting */
			$category                              = $this->categorize_setting( $option_name );
			$settings[ $category ][ $option_name ] = $option_value;
		}

		/* Remove empty categories */
		foreach ( $settings as $category => $options ) {
			if ( empty( $options ) ) {
				unset( $settings[ $category ] );
			}
		}

		return $settings;
	}

	/**
	 * Export templates (CSS and Email)
	 *
	 * @return array Templates.
	 */
	private function export_templates() {
		$templates = array(
			'css'   => array(),
			'email' => array(),
		);

		/* CSS templates */
		$css_options = array(
			'nbuf_reset_page_css',
			'nbuf_login_page_css',
			'nbuf_registration_page_css',
			'nbuf_verify_page_css',
			'nbuf_account_page_css',
			'nbuf_2fa_verify_css',
			'nbuf_2fa_setup_css',
			'nbuf_profile_custom_css',
		);

		foreach ( $css_options as $option ) {
			$value = NBUF_Options::get( $option, '' );
			if ( ! empty( $value ) ) {
				$templates['css'][ $option ] = $value;
			}
		}

		/* Email templates */
		$email_options = array(
			'nbuf_email_template_html',
			'nbuf_email_template_text',
			'nbuf_email_verification_subject',
			'nbuf_email_verification_body',
			'nbuf_email_welcome_subject',
			'nbuf_email_welcome_body',
			'nbuf_email_expiring_subject',
			'nbuf_email_expiring_body',
			'nbuf_email_expired_subject',
			'nbuf_email_expired_body',
			'nbuf_email_2fa_subject',
			'nbuf_email_2fa_body',
			'nbuf_email_password_reset_subject',
			'nbuf_email_password_reset_body',
		);

		foreach ( $email_options as $option ) {
			$value = NBUF_Options::get( $option, '' );
			if ( ! empty( $value ) ) {
				$templates['email'][ $option ] = $value;
			}
		}

		/* Remove empty categories */
		if ( empty( $templates['css'] ) ) {
			unset( $templates['css'] );
		}
		if ( empty( $templates['email'] ) ) {
			unset( $templates['email'] );
		}

		return $templates;
	}

	/**
	 * Categorize setting by prefix
	 *
	 * @param  string $option_name Option name.
	 * @return string Category name.
	 */
	private function categorize_setting( $option_name ) {
		/* System settings */
		if ( 0 === strpos( $option_name, 'nbuf_master_toggle' )
			|| 0 === strpos( $option_name, 'nbuf_page_' )
			|| 0 === strpos( $option_name, 'nbuf_enable_' )
		) {
			return 'system';
		}

		/* User settings */
		if ( 0 === strpos( $option_name, 'nbuf_registration_' )
			|| 0 === strpos( $option_name, 'nbuf_profile_field_' )
			|| 0 === strpos( $option_name, 'nbuf_expiration_' )
			|| 0 === strpos( $option_name, 'nbuf_verification_' )
		) {
			return 'users';
		}

		/* Security settings */
		if ( 0 === strpos( $option_name, 'nbuf_2fa_' )
			|| 0 === strpos( $option_name, 'nbuf_login_limit_' )
			|| 0 === strpos( $option_name, 'nbuf_password_' )
			|| 0 === strpos( $option_name, 'nbuf_security_' )
		) {
			return 'security';
		}

		/* Template settings */
		if ( 0 === strpos( $option_name, 'nbuf_email_' )
			|| false !== strpos( $option_name, '_css' )
			|| false !== strpos( $option_name, '_template' )
		) {
			return 'templates';
		}

		/* Restriction settings */
		if ( 0 === strpos( $option_name, 'nbuf_restrict' ) ) {
			return 'restrictions';
		}

		/* Profile settings */
		if ( 0 === strpos( $option_name, 'nbuf_profile_' )
			|| 0 === strpos( $option_name, 'nbuf_avatar_' )
			|| 0 === strpos( $option_name, 'nbuf_cover_' )
		) {
			return 'profiles';
		}

		/* Integration settings */
		if ( 0 === strpos( $option_name, 'nbuf_woocommerce_' )
			|| 0 === strpos( $option_name, 'nbuf_integration_' )
		) {
			return 'integration';
		}

		/* Tools settings */
		if ( 0 === strpos( $option_name, 'nbuf_import_' )
			|| 0 === strpos( $option_name, 'nbuf_export_' )
			|| 0 === strpos( $option_name, 'nbuf_migration_' )
		) {
			return 'tools';
		}

		return 'other';
	}

	/**
	 * Get plugin version
	 *
	 * @return string Plugin version.
	 */
	private function get_plugin_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = dirname( __DIR__ ) . '/nobloat-user-foundry.php';
		$plugin_data = get_plugin_data( $plugin_file );

		return $plugin_data['Version'] ?? '1.3.0';
	}

	/**
	 * Get export statistics
	 *
	 * @return array Statistics.
	 */
	public function get_export_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nbuf_options';

     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name from $wpdb->prefix, stats query.
		$total_settings = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		return array(
			'total_settings' => (int) $total_settings,
			'plugin_version' => $this->get_plugin_version(),
		);
	}
}
