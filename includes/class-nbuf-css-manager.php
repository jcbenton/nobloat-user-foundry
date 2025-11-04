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
	 * Writes CSS to both .css and .min.css files.
	 * Updates or removes write failure token based on success.
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

		$live_path = $ui_dir . $filename . '-live.css';
		$min_path  = $ui_dir . $filename . '-live.min.css';

		/* Check if CSS actually changed (hash comparison) */
		$new_hash = md5( $css );
		$old_hash = '';

		if ( file_exists( $live_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local CSS file, not remote URL
			$old_content = file_get_contents( $live_path );
			if ( false === $old_content ) {
				NBUF_Security_Log::log(
					'css_read_failed',
					'warning',
					'Failed to read existing CSS file for hash comparison',
					array(
						'file_path' => $live_path,
						'filename'  => $filename,
					)
				);
				/* Continue anyway - will regenerate CSS */
			} else {
				$old_hash = md5( $old_content );
			}
		}

		/* Skip write if CSS unchanged */
		if ( $new_hash === $old_hash ) {
			return true;
		}

		/*
		 * CSS changed - write live file
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing CSS to assets directory; WP_Filesystem not practical for dynamic CSS generation.
		$wrote_css = file_put_contents( $live_path, $css );

		if ( false === $wrote_css ) {
			/* Write failed - set token */
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing CSS to assets directory
		$wrote_min = file_put_contents( $min_path, $minified );

		if ( false === $wrote_min ) {
			/* Minified write failed - set token */
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

			/*
			 * Delete the CSS file we just wrote to keep them in sync
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup after failed CSS write; WP_Filesystem not practical here.
			$unlink_result = unlink( $live_path );
			if ( false === $unlink_result ) {
				NBUF_Security_Log::log(
					'css_cleanup_failed',
					'warning',
					'Failed to delete orphaned CSS file after minified write failure',
					array(
						'file_path' => $live_path,
						'filename'  => $filename,
					)
				);
			}
			return false;
		}

		/* Both writes successful - clear token */
		NBUF_Options::delete( $token_key );
		return true;
	}

	/**
	 * Load CSS
	 *
	 * Loads CSS with token-based performance optimization.
	 * Priority: -live.min.css → -live.css → default → DB fallback.
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

		/* Priority 1: Minified live file */
		$min_path = $ui_dir . $filename . '-live.min.css';
		if ( file_exists( $min_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local CSS file, not remote URL
			$content = file_get_contents( $min_path );
			if ( false === $content ) {
				NBUF_Security_Log::log(
					'css_read_failed',
					'warning',
					'Failed to read minified CSS file',
					array(
						'file_path' => $min_path,
						'filename'  => $filename,
					)
				);
				/* Fall through to next priority */
			} else {
				return $content;
			}
		}

		/* Priority 2: Live CSS file */
		$live_path = $ui_dir . $filename . '-live.css';
		if ( file_exists( $live_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local CSS file, not remote URL
			$content = file_get_contents( $live_path );
			if ( false === $content ) {
				NBUF_Security_Log::log(
					'css_read_failed',
					'warning',
					'Failed to read live CSS file',
					array(
						'file_path' => $live_path,
						'filename'  => $filename,
					)
				);
				/* Fall through to next priority */
			} else {
				return $content;
			}
		}

		/* Priority 3: Default template from /templates/ */
		$default_path = $templates_dir . $filename . '.css';
		if ( file_exists( $default_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local CSS file, not remote URL
			$content = file_get_contents( $default_path );
			if ( false === $content ) {
				NBUF_Security_Log::log(
					'css_read_failed',
					'critical',
					'Failed to read default CSS template',
					array(
						'file_path' => $default_path,
						'filename'  => $filename,
					)
				);
				/* Fall through to DB fallback */
			} else {
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

		/* Check minified preference */
		$use_minified = NBUF_Options::get( 'nbuf_css_use_minified', true );

		/* Check for minified live file (if minified enabled) */
		if ( $use_minified ) {
			$min_path = $ui_dir . $filename . '-live.min.css';
			if ( file_exists( $min_path ) ) {
				$version = filemtime( $min_path );
				wp_enqueue_style( $handle, $ui_dir_url . $filename . '-live.min.css', array(), $version );
				return;
			}
		}

		/* Check for live file */
		$live_path = $ui_dir . $filename . '-live.css';
		if ( file_exists( $live_path ) ) {
			$version = filemtime( $live_path );
			wp_enqueue_style( $handle, $ui_dir_url . $filename . '-live.css', array(), $version );
			return;
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
}
