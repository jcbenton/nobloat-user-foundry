<?php
/**
 * NoBloat User Foundry - CSS Manager
 *
 * Handles CSS minification, file writing, and loading with
 * token-based write failure detection for performance.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSS management and optimization
 *
 * @since 1.0.0
 */
class NBUF_CSS_Manager {

	/**
	 * Minify CSS.
	 *
	 * Strips whitespace and comments from CSS.
	 * No external library needed - pure PHP regex.
	 *
	 * @param  string $css CSS content to minify.
	 * @return string Minified CSS.
	 */
	public static function minify( $css ) {
		if ( empty( $css ) ) {
			return '';
		}

		// Remove comments.
		$css = preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Remove excess whitespace.
		$css = preg_replace( '/\s+/', ' ', $css );

		// Remove spaces around CSS punctuation.
		$css = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $css );

		// Trim and return.
		return trim( $css );
	}

	/**
	 * Sanitize CSS input from admin.
	 *
	 * Removes potentially dangerous CSS while preserving valid styles.
	 * Blocks: javascript: URLs, @import, data URIs, expressions
	 *
	 * @param  string $css Raw CSS input.
	 * @return string Sanitized CSS.
	 */
	public static function sanitize_css( $css ) {
		if ( empty( $css ) ) {
			return '';
		}

		/* Remove any HTML tags first */
		$css = wp_strip_all_tags( $css );

		/* Remove javascript: protocol */
		$css = preg_replace( '/javascript\s*:/i', '', $css );

		/* Remove data: URIs (potential XSS vector) */
		$css = preg_replace( '/url\s*\(\s*[\'"]?data:/i', 'url(', $css );

		/* Remove @import (prevents external resource loading) */
		$css = preg_replace( '/@import\s+/i', '', $css );

		/* Remove expression() for old IE */
		$css = preg_replace( '/expression\s*\(/i', '', $css );

		/* Remove -moz-binding (Firefox XSS vector) */
		$css = preg_replace( '/-moz-binding\s*:/i', '', $css );

		/* Remove behavior: (IE XSS vector) */
		$css = preg_replace( '/behavior\s*:/i', '', $css );

		return $css;
	}

	/**
	 * Save CSS to disk
	 *
	 * Writes CSS to timestamped .css and .min.css files for CDN-safe caching.
	 * Cleans up old versions and stores timestamp for loading.
	 *
	 * @param  string $css       CSS content to save.
	 * @param  string $filename  Base filename (e.g., 'reset-page').
	 * @param  string $token_key Option name for write failure token.
	 * @return bool True if write successful, false otherwise.
	 */
	public static function save_css_to_disk( $css, $filename, $token_key = 'nbuf_css_write_failed' ) {
		$ui_dir = NBUF_PLUGIN_DIR . 'assets/css/frontend/';

		/* Ensure directory exists */
		if ( ! is_dir( $ui_dir ) ) {
			if ( ! wp_mkdir_p( $ui_dir ) ) {
				NBUF_Options::update( $token_key, 1, true, 'system' );
				return false;
			}
		}

		/* Check if CSS actually changed (hash comparison with stored version) */
		$new_hash        = md5( $css );
		$version_key     = 'nbuf_css_version_' . $filename;
		$stored_version  = NBUF_Options::get( $version_key );
		$old_hash        = '';

		if ( $stored_version ) {
			$old_path = $ui_dir . $filename . '.' . $stored_version . '.min.css';
			if ( file_exists( $old_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$old_content = file_get_contents( $old_path );
				if ( false !== $old_content ) {
					/* Compare against minified content */
					$old_hash = md5( self::minify( $css ) ) === md5( $old_content ) ? $new_hash : '';
				}
			}
		}

		/* Skip write if CSS unchanged */
		if ( $new_hash === $old_hash && ! empty( $old_hash ) ) {
			return true;
		}

		/* Generate new timestamp for filename */
		$timestamp = time();
		$live_path = $ui_dir . $filename . '.' . $timestamp . '.css';
		$min_path  = $ui_dir . $filename . '.' . $timestamp . '.min.css';

		/* Clean up old versions before writing new ones */
		self::cleanup_old_css_versions( $filename, $timestamp );

		/* Write unminified file */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$wrote_css = file_put_contents( $live_path, $css );

		if ( false === $wrote_css ) {
			NBUF_Options::update( $token_key, 1, true, 'system' );
			NBUF_Security_Log::log(
				'css_write_failed',
				'critical',
				'Failed to write CSS file to disk',
				array(
					'file_path' => $live_path,
					'filename'  => $filename,
					'css_size'  => strlen( $css ),
				)
			);
			return false;
		}

		/* Minify and write minified version */
		$minified = self::minify( $css );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$wrote_min = file_put_contents( $min_path, $minified );

		if ( false === $wrote_min ) {
			NBUF_Options::update( $token_key, 1, true, 'system' );
			NBUF_Security_Log::log(
				'css_minify_write_failed',
				'critical',
				'Failed to write minified CSS file to disk',
				array(
					'file_path'     => $min_path,
					'filename'      => $filename,
					'minified_size' => strlen( $minified ),
				)
			);

			/* Delete the CSS file we just wrote to keep them in sync */
			wp_delete_file( $live_path );
			return false;
		}

		/* Store timestamp for loading and clear failure token */
		NBUF_Options::update( $version_key, $timestamp, true, 'system' );
		NBUF_Options::delete( $token_key );
		return true;
	}

	/**
	 * Clean up old CSS versions.
	 *
	 * Removes previous timestamped versions and legacy -live.css files.
	 *
	 * @since 1.5.0
	 * @param string $filename      Base filename (e.g., 'reset-page').
	 * @param int    $new_timestamp Timestamp of new file (to exclude from deletion).
	 * @return int Number of old files deleted.
	 */
	private static function cleanup_old_css_versions( $filename, $new_timestamp ) {
		$ui_dir  = NBUF_PLUGIN_DIR . 'assets/css/frontend/';
		$deleted = 0;

		/* Clean up legacy -live.css and -live.min.css files */
		$legacy_files = array(
			$ui_dir . $filename . '-live.css',
			$ui_dir . $filename . '-live.min.css',
		);
		foreach ( $legacy_files as $legacy_file ) {
			if ( file_exists( $legacy_file ) ) {
				wp_delete_file( $legacy_file );
				++$deleted;
			}
		}

		/* Clean up old timestamped versions */
		$pattern = $ui_dir . $filename . '.*.css';
		$files   = glob( $pattern );

		if ( ! empty( $files ) && is_array( $files ) ) {
			$new_base = $filename . '.' . $new_timestamp;
			foreach ( $files as $file ) {
				$basename = basename( $file );
				/* Don't delete files we're about to create */
				if ( strpos( $basename, $new_base ) === 0 ) {
					continue;
				}
				/* Only delete timestamped versions (filename.timestamp.css or filename.timestamp.min.css) */
				if ( preg_match( '/^' . preg_quote( $filename, '/' ) . '\.\d+\.(?:min\.)?css$/', $basename ) ) {
					wp_delete_file( $file );
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Load CSS
	 *
	 * Loads CSS with token-based performance optimization.
	 * Priority: timestamped.min.css → timestamped.css → default → DB fallback.
	 *
	 * @param  string $filename  Base filename (e.g., 'reset-page').
	 * @param  string $db_option Option name for DB fallback.
	 * @param  string $token_key Option name for write failure token.
	 * @return string CSS content.
	 */
	public static function load_css( $filename, $db_option, $token_key = 'nbuf_css_write_failed' ) {
		/* Check token first - if write failed, skip disk and use DB */
		if ( NBUF_Options::get( $token_key ) ) {
			$css = NBUF_Options::get( $db_option );
			if ( $css ) {
				return $css;
			}
			/* DB empty too - fallback to default template */
			return self::load_default_css( $filename );
		}

		/* Token doesn't exist - check disk files */
		$ui_dir        = NBUF_PLUGIN_DIR . 'assets/css/frontend/';
		$templates_dir = NBUF_TEMPLATES_DIR;

		/* Get stored timestamp for this file */
		$version_key = 'nbuf_css_version_' . $filename;
		$timestamp   = NBUF_Options::get( $version_key );

		if ( $timestamp ) {
			/* Priority 1: Timestamped minified file */
			$min_path = $ui_dir . $filename . '.' . $timestamp . '.min.css';
			if ( file_exists( $min_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $min_path );
				if ( false !== $content ) {
					return $content;
				}
			}

			/* Priority 2: Timestamped unminified file */
			$live_path = $ui_dir . $filename . '.' . $timestamp . '.css';
			if ( file_exists( $live_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $live_path );
				if ( false !== $content ) {
					return $content;
				}
			}
		}

		/* Priority 3: Default template from /templates/ */
		$default_path = $templates_dir . $filename . '.css';
		if ( file_exists( $default_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $default_path );
			if ( false !== $content ) {
				return $content;
			}
		}

		/* Priority 4: Database fallback */
		$css = NBUF_Options::get( $db_option );
		if ( $css ) {
			return $css;
		}

		/* Nothing found - return empty */
		return '';
	}

	/**
	 * Load default CSS
	 *
	 * Loads CSS from /templates/ directory.
	 *
	 * @param  string $filename Base filename (e.g., 'reset-page').
	 * @return string CSS content or empty string.
	 */
	public static function load_default_css( $filename ) {
		$path = NBUF_TEMPLATES_DIR . $filename . '.css';
		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local CSS file, not remote URL
			$content = file_get_contents( $path );
			if ( false === $content ) {
				NBUF_Security_Log::log(
					'css_read_failed',
					'critical',
					'Failed to read default CSS template in load_default_css()',
					array(
						'file_path' => $path,
						'filename'  => $filename,
					)
				);
				return '';
			}
			return $content;
		}
		return '';
	}

	/**
	 * Enqueue CSS
	 *
	 * Enqueues CSS inline or as file based on token status.
	 * Uses timestamped filenames for CDN-safe cache busting.
	 *
	 * @param string $handle    Handle for wp_enqueue_style.
	 * @param string $filename  Base filename (e.g., 'reset-page').
	 * @param string $db_option Option name for DB fallback.
	 * @param string $token_key Option name for write failure token.
	 */
	public static function enqueue_css( $handle, $filename, $db_option, $token_key = 'nbuf_css_write_failed' ) {
		/* If token exists, load from DB and inline it */
		if ( NBUF_Options::get( $token_key ) ) {
			$css = NBUF_Options::get( $db_option );
			if ( ! $css ) {
				$css = self::load_default_css( $filename );
			}
			if ( $css ) {
				wp_register_style( $handle, false, array(), md5( $css ) );
				wp_enqueue_style( $handle );
				wp_add_inline_style( $handle, $css );
			}
			return;
		}

		/* No token - try to load from disk */
		$ui_dir_url        = NBUF_PLUGIN_URL . 'assets/css/frontend/';
		$templates_dir_url = NBUF_PLUGIN_URL . 'templates/';
		$ui_dir            = NBUF_PLUGIN_DIR . 'assets/css/frontend/';
		$templates_dir     = NBUF_TEMPLATES_DIR;

		/* Get stored timestamp for this file */
		$version_key  = 'nbuf_css_version_' . $filename;
		$timestamp    = NBUF_Options::get( $version_key );
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );

		if ( $timestamp ) {
			/* Check for timestamped minified file */
			if ( $use_minified ) {
				$min_path = $ui_dir . $filename . '.' . $timestamp . '.min.css';
				if ( file_exists( $min_path ) ) {
					wp_enqueue_style( $handle, $ui_dir_url . $filename . '.' . $timestamp . '.min.css', array(), (string) $timestamp );
					return;
				}
			}

			/* Check for timestamped unminified file */
			$live_path = $ui_dir . $filename . '.' . $timestamp . '.css';
			if ( file_exists( $live_path ) ) {
				wp_enqueue_style( $handle, $ui_dir_url . $filename . '.' . $timestamp . '.css', array(), (string) $timestamp );
				return;
			}
		}

		/* Check for default template */
		$default_path = $templates_dir . $filename . '.css';
		if ( file_exists( $default_path ) ) {
			$version = filemtime( $default_path );
			wp_enqueue_style( $handle, $templates_dir_url . $filename . '.css', array(), $version );
			return;
		}

		/* Last resort - inline from DB */
		$css = NBUF_Options::get( $db_option );
		if ( $css ) {
			wp_register_style( $handle, false, array(), md5( $css ) );
			wp_enqueue_style( $handle );
			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * Enqueue combined CSS file.
	 *
	 * Loads a single combined CSS file containing all frontend styles.
	 * Falls back to individual files if combined file is not available.
	 *
	 * @return bool True if combined CSS was enqueued, false if not available.
	 */
	public static function enqueue_combined_css() {
		$ui_dir     = NBUF_PLUGIN_DIR . 'assets/css/frontend/';
		$ui_dir_url = NBUF_PLUGIN_URL . 'assets/css/frontend/';

		/* Check for combined minified file */
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );

		if ( $use_minified ) {
			$combined_min_path = $ui_dir . 'nobloat-combined.min.css';
			if ( file_exists( $combined_min_path ) ) {
				$version = filemtime( $combined_min_path );
				wp_enqueue_style( 'nbuf-combined', $ui_dir_url . 'nobloat-combined.min.css', array(), $version );
				return true;
			}
		}

		/* Check for combined unminified file */
		$combined_path = $ui_dir . 'nobloat-combined.css';
		if ( file_exists( $combined_path ) ) {
			$version = filemtime( $combined_path );
			wp_enqueue_style( 'nbuf-combined', $ui_dir_url . 'nobloat-combined.css', array(), $version );
			return true;
		}

		/* Combined file not available */
		return false;
	}

	/**
	 * Get write failure status
	 *
	 * Checks if there's a write failure token set.
	 * Used for displaying admin notices.
	 *
	 * @param  string $token_key Option name for write failure token.
	 * @return bool True if write failures detected.
	 */
	public static function has_write_failure( $token_key = 'nbuf_css_write_failed' ) {
		return (bool) NBUF_Options::get( $token_key );
	}

	/**
	 * Clear write failure token
	 *
	 * Manually clears the write failure token.
	 * Useful for retry operations.
	 *
	 * @param string $token_key Option name for write failure token.
	 */
	public static function clear_write_failure_token( $token_key = 'nbuf_css_write_failed' ) {
		NBUF_Options::delete( $token_key );
	}

	/**
	 * Get CSS template registry.
	 *
	 * Returns array of all CSS templates with their configuration.
	 * Dynamically discovers templates from /templates/ directory.
	 *
	 * @since 1.5.3
	 * @return array Array of template configurations.
	 */
	public static function get_css_templates(): array {
		/* Static mapping of template file to DB option and token key */
		$template_config = array(
			'login-page'        => array(
				'db_option' => 'nbuf_login_page_css',
				'token_key' => 'nbuf_css_write_failed_login',
				'label'     => __( 'Login', 'nobloat-user-foundry' ),
			),
			'registration-page' => array(
				'db_option' => 'nbuf_registration_page_css',
				'token_key' => 'nbuf_css_write_failed_registration',
				'label'     => __( 'Register', 'nobloat-user-foundry' ),
			),
			'account-page'      => array(
				'db_option' => 'nbuf_account_page_css',
				'token_key' => 'nbuf_css_write_failed_account',
				'label'     => __( 'Account', 'nobloat-user-foundry' ),
			),
			'reset-page'        => array(
				'db_option' => 'nbuf_reset_page_css',
				'token_key' => 'nbuf_css_write_failed_reset',
				'label'     => __( 'Reset', 'nobloat-user-foundry' ),
			),
			'2fa-setup'         => array(
				'db_option' => 'nbuf_2fa_page_css',
				'token_key' => 'nbuf_css_write_failed_2fa',
				'label'     => __( '2FA', 'nobloat-user-foundry' ),
			),
			'profile'           => array(
				'db_option' => 'nbuf_profile_custom_css',
				'token_key' => 'nbuf_css_write_failed_profile',
				'label'     => __( 'Profiles', 'nobloat-user-foundry' ),
			),
			'member-directory'  => array(
				'db_option' => 'nbuf_member_directory_custom_css',
				'token_key' => 'nbuf_css_write_failed_member_directory',
				'label'     => __( 'Member Directory', 'nobloat-user-foundry' ),
			),
			'version-history'   => array(
				'db_option' => 'nbuf_version_history_custom_css',
				'token_key' => 'nbuf_css_write_failed_version_history',
				'label'     => __( 'Version History', 'nobloat-user-foundry' ),
			),
			'data-export'       => array(
				'db_option' => 'nbuf_data_export_custom_css',
				'token_key' => 'nbuf_css_write_failed_data_export',
				'label'     => __( 'Data Export', 'nobloat-user-foundry' ),
			),
			'tos-acceptance'    => array(
				'db_option' => 'nbuf_tos_acceptance_css',
				'token_key' => 'nbuf_css_write_failed_tos',
				'label'     => __( 'Terms of Service', 'nobloat-user-foundry' ),
			),
		);

		/* Scan templates directory to find available CSS files */
		$templates_dir = NBUF_TEMPLATES_DIR;
		$templates     = array();

		if ( is_dir( $templates_dir ) ) {
			$files = glob( $templates_dir . '*.css' );
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					$filename = basename( $file, '.css' );
					if ( isset( $template_config[ $filename ] ) ) {
						$templates[ $filename ] = array_merge(
							$template_config[ $filename ],
							array( 'file' => $file )
						);
					}
				}
			}
		}

		return $templates;
	}

	/**
	 * Regenerate all CSS files.
	 *
	 * Reads CSS from database (or defaults if empty) and rewrites to disk.
	 * Useful for refreshing minified files after manual edits.
	 *
	 * @since 1.5.3
	 * @return array Results with success/failure counts and details.
	 */
	public static function regenerate_all_css(): array {
		$templates = self::get_css_templates();
		$results   = array(
			'success' => 0,
			'failed'  => 0,
			'details' => array(),
		);

		foreach ( $templates as $filename => $config ) {
			/* Get CSS from database first, fall back to default */
			$css = NBUF_Options::get( $config['db_option'] );
			if ( empty( $css ) ) {
				$css = self::load_default_css( $filename );
			}

			if ( empty( $css ) ) {
				$results['details'][ $filename ] = array(
					'status'  => 'skipped',
					'message' => __( 'No CSS content found', 'nobloat-user-foundry' ),
				);
				continue;
			}

			/* Clear any existing failure token before attempting write */
			self::clear_write_failure_token( $config['token_key'] );

			/* Write to disk */
			$success = self::save_css_to_disk( $css, $filename, $config['token_key'] );

			if ( $success ) {
				++$results['success'];
				$results['details'][ $filename ] = array(
					'status'  => 'success',
					'message' => __( 'Regenerated successfully', 'nobloat-user-foundry' ),
				);
			} else {
				++$results['failed'];
				$results['details'][ $filename ] = array(
					'status'  => 'failed',
					'message' => __( 'Write failed - check permissions', 'nobloat-user-foundry' ),
				);
			}
		}

		return $results;
	}

	/**
	 * Reset all CSS to defaults.
	 *
	 * Loads default CSS from templates and saves to both database and disk.
	 * This overwrites any customizations.
	 *
	 * @since 1.5.3
	 * @return array Results with success/failure counts and details.
	 */
	public static function reset_all_to_defaults(): array {
		$templates = self::get_css_templates();
		$results   = array(
			'success' => 0,
			'failed'  => 0,
			'details' => array(),
		);

		foreach ( $templates as $filename => $config ) {
			/* Load default CSS from template file */
			$default_css = self::load_default_css( $filename );

			if ( empty( $default_css ) ) {
				$results['details'][ $filename ] = array(
					'status'  => 'skipped',
					'message' => __( 'No default template found', 'nobloat-user-foundry' ),
				);
				continue;
			}

			/* Save to database */
			NBUF_Options::update( $config['db_option'], $default_css, false, 'css' );

			/* Clear any existing failure token */
			self::clear_write_failure_token( $config['token_key'] );

			/* Write to disk */
			$success = self::save_css_to_disk( $default_css, $filename, $config['token_key'] );

			if ( $success ) {
				++$results['success'];
				$results['details'][ $filename ] = array(
					'status'  => 'success',
					'message' => __( 'Reset to default', 'nobloat-user-foundry' ),
				);
			} else {
				++$results['failed'];
				$results['details'][ $filename ] = array(
					'status'  => 'partial',
					'message' => __( 'Saved to DB but disk write failed', 'nobloat-user-foundry' ),
				);
			}
		}

		return $results;
	}

	/**
	 * Register AJAX handlers for CSS operations.
	 *
	 * @since 1.5.3
	 */
	public static function register_ajax_handlers(): void {
		add_action( 'wp_ajax_nbuf_regenerate_all_css', array( __CLASS__, 'ajax_regenerate_all_css' ) );
		add_action( 'wp_ajax_nbuf_reset_all_css', array( __CLASS__, 'ajax_reset_all_css' ) );
	}

	/**
	 * AJAX handler for regenerating all CSS files.
	 *
	 * @since 1.5.3
	 */
	public static function ajax_regenerate_all_css(): void {
		/* Verify nonce and capability */
		if ( ! check_ajax_referer( 'nbuf_css_operations', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
		}

		$results = self::regenerate_all_css();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for resetting all CSS to defaults.
	 *
	 * @since 1.5.3
	 */
	public static function ajax_reset_all_css(): void {
		/* Verify nonce and capability */
		if ( ! check_ajax_referer( 'nbuf_css_operations', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'nobloat-user-foundry' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
		}

		$results = self::reset_all_to_defaults();

		wp_send_json_success( $results );
	}
}

/* Register AJAX handlers on admin_init */
add_action( 'admin_init', array( 'NBUF_CSS_Manager', 'register_ajax_handlers' ) );
