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
	 * Track whether combined CSS has been loaded.
	 *
	 * @var bool
	 */
	private static $combined_css_loaded = false;

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
		/* Check if combined CSS is enabled and should be loaded instead */
		$use_combined = NBUF_Options::get( 'nbuf_css_combine_files', true );

		if ( $use_combined ) {
			/* Try to load combined CSS file */
			if ( self::enqueue_combined_css() ) {
				/* Combined CSS loaded successfully, skip individual file */
				return;
			}
			/* Combined file not available, fall through to individual loading */
		}

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
	 * Enqueue the combined CSS file.
	 *
	 * Loads timestamped nobloat-combined.{timestamp}.min.css
	 * for CDN-safe cache busting.
	 *
	 * @return bool True if combined CSS was loaded, false if not available.
	 */
	public static function enqueue_combined_css() {
		/* Already loaded this request */
		if ( self::$combined_css_loaded ) {
			return true;
		}

		$handle = 'nbuf-combined';

		/* Already enqueued check */
		if ( wp_style_is( $handle, 'enqueued' ) ) {
			self::$combined_css_loaded = true;
			return true;
		}

		$ui_dir     = NBUF_PLUGIN_DIR . 'assets/css/frontend/';
		$ui_dir_url = NBUF_PLUGIN_URL . 'assets/css/frontend/';

		/* Get stored timestamp for combined file */
		$version_key  = 'nbuf_css_version_nobloat-combined';
		$timestamp    = NBUF_Options::get( $version_key );
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );

		if ( $timestamp ) {
			/* Check for timestamped combined minified file */
			if ( $use_minified ) {
				$min_path = $ui_dir . 'nobloat-combined.' . $timestamp . '.min.css';
				if ( file_exists( $min_path ) ) {
					wp_enqueue_style( $handle, $ui_dir_url . 'nobloat-combined.' . $timestamp . '.min.css', array(), (string) $timestamp );
					self::$combined_css_loaded = true;
					return true;
				}
			}

			/* Check for timestamped combined non-minified file */
			$live_path = $ui_dir . 'nobloat-combined.' . $timestamp . '.css';
			if ( file_exists( $live_path ) ) {
				wp_enqueue_style( $handle, $ui_dir_url . 'nobloat-combined.' . $timestamp . '.css', array(), (string) $timestamp );
				self::$combined_css_loaded = true;
				return true;
			}
		}

		/* Combined file not found */
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
	 * Rebuild combined CSS file
	 *
	 * Combines all frontend CSS files into a single file.
	 * Called when any individual CSS file is saved and combine is enabled.
	 *
	 * @return bool True if write successful, false otherwise.
	 */
	public static function rebuild_combined_css() {
		/* Check if combine is enabled */
		$combine_enabled = NBUF_Options::get( 'nbuf_css_combine_files', true );
		if ( ! $combine_enabled ) {
			return true;
		}

		/* Load all CSS - from DB or defaults */
		$css_files = array(
			'reset-page'        => 'nbuf_reset_page_css',
			'login-page'        => 'nbuf_login_page_css',
			'registration-page' => 'nbuf_registration_page_css',
			'account-page'      => 'nbuf_account_page_css',
			'2fa-setup'         => 'nbuf_2fa_page_css',
			'profile'           => 'nbuf_profile_custom_css',
			'member-directory'  => 'nbuf_member_directory_custom_css',
			'version-history'   => 'nbuf_version_history_custom_css',
			'data-export'       => 'nbuf_data_export_custom_css',
		);

		$combined_parts = array();

		foreach ( $css_files as $filename => $db_option ) {
			$css = NBUF_Options::get( $db_option );
			if ( empty( $css ) ) {
				$css = self::load_default_css( $filename );
			}
			if ( ! empty( $css ) ) {
				$combined_parts[] = $css;
			}
		}

		$combined_css = implode( "\n\n", $combined_parts );

		return self::save_css_to_disk( $combined_css, 'nobloat-combined', 'nbuf_css_write_failed_combined' );
	}
}
