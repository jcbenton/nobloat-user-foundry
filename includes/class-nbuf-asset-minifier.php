<?php
/**
 * Asset Minifier
 *
 * Handles on-the-fly JavaScript minification with caching.
 * Minified files are stored in wp-content/uploads/nobloat/cache/
 * and regenerated when source files change or plugin version updates.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Asset_Minifier
 *
 * Provides JavaScript minification without external dependencies.
 *
 * @since 1.5.0
 */
class NBUF_Asset_Minifier {

	/**
	 * Cache directory path.
	 *
	 * @var string
	 */
	private static $cache_dir = null;

	/**
	 * Cache directory URL.
	 *
	 * @var string
	 */
	private static $cache_url = null;

	/**
	 * Get the cache directory path.
	 *
	 * @since 1.5.0
	 * @return string Cache directory path with trailing slash.
	 */
	public static function get_cache_dir() {
		if ( null === self::$cache_dir ) {
			$upload_dir     = wp_upload_dir();
			self::$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'nobloat/cache/';
		}
		return self::$cache_dir;
	}

	/**
	 * Get the cache directory URL.
	 *
	 * @since 1.5.0
	 * @return string Cache directory URL with trailing slash.
	 */
	public static function get_cache_url() {
		if ( null === self::$cache_url ) {
			$upload_dir     = wp_upload_dir();
			self::$cache_url = trailingslashit( $upload_dir['baseurl'] ) . 'nobloat/cache/';
		}
		return self::$cache_url;
	}

	/**
	 * Ensure cache directory exists.
	 *
	 * @since 1.5.0
	 * @return bool True if directory exists or was created.
	 */
	private static function ensure_cache_dir() {
		$cache_dir = self::get_cache_dir();

		if ( ! file_exists( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( ! mkdir( $cache_dir, 0755, true ) ) {
				return false;
			}

			/* Create index.php for security */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $cache_dir . 'index.php', '<?php // Silence is golden.' );
		}

		return true;
	}

	/**
	 * Get the minified script URL for a source file.
	 *
	 * Returns the cached minified URL if available and valid,
	 * otherwise generates the minified file first.
	 *
	 * @since 1.5.0
	 * @param string $source_path Absolute path to source JS file.
	 * @param string $handle      Script handle for naming.
	 * @param string $version     Version string for cache busting.
	 * @return string|false URL to minified file, or false on failure.
	 */
	public static function get_minified_url( $source_path, $handle, $version ) {
		/* If SCRIPT_DEBUG is enabled, return false to use source */
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return false;
		}

		/* Verify source file exists */
		if ( ! file_exists( $source_path ) ) {
			return false;
		}

		/* Build cache filename with version for cache busting */
		$cache_filename = $handle . '.' . $version . '.min.js';
		$cache_path     = self::get_cache_dir() . $cache_filename;
		$cache_url      = self::get_cache_url() . $cache_filename;

		/* Check if cached file exists and is newer than source */
		if ( file_exists( $cache_path ) ) {
			$source_mtime = filemtime( $source_path );
			$cache_mtime  = filemtime( $cache_path );

			if ( $cache_mtime >= $source_mtime ) {
				return $cache_url;
			}
		}

		/* Generate minified file */
		if ( self::generate_minified_file( $source_path, $cache_path, $handle ) ) {
			return $cache_url;
		}

		return false;
	}

	/**
	 * Generate a minified JavaScript file.
	 *
	 * @since 1.5.0
	 * @param string $source_path Absolute path to source file.
	 * @param string $cache_path  Absolute path to cache file.
	 * @param string $handle      Script handle for cleanup of old versions.
	 * @return bool True on success, false on failure.
	 */
	private static function generate_minified_file( $source_path, $cache_path, $handle = '' ) {
		/* Ensure cache directory exists */
		if ( ! self::ensure_cache_dir() ) {
			return false;
		}

		/* Clean up old versions of this handle before creating new one */
		if ( $handle ) {
			self::cleanup_old_versions( $handle, $cache_path );
		}

		/* Read source file */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source = file_get_contents( $source_path );
		if ( false === $source ) {
			return false;
		}

		/* Minify the JavaScript */
		$minified = self::minify_js( $source );

		/* Write minified file */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $cache_path, $minified );

		return false !== $result;
	}

	/**
	 * Remove old versions of a cached script.
	 *
	 * When a new version is generated, this removes any previous
	 * versions of the same handle to prevent cache buildup.
	 *
	 * @since 1.5.0
	 * @param string $handle     Script handle.
	 * @param string $new_path   Path to the new cache file (to exclude from deletion).
	 * @return int Number of old files deleted.
	 */
	private static function cleanup_old_versions( $handle, $new_path ) {
		$cache_dir = self::get_cache_dir();
		$pattern   = $cache_dir . $handle . '.*.min.js';
		$files     = glob( $pattern );
		$deleted   = 0;

		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $files as $file ) {
				/* Don't delete the file we're about to create */
				if ( $file === $new_path ) {
					continue;
				}

				if ( is_file( $file ) ) {
					wp_delete_file( $file );
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Minify JavaScript code.
	 *
	 * Removes comments and unnecessary whitespace while preserving
	 * string literals, regex literals, and ensuring code remains valid.
	 *
	 * @since 1.5.0
	 * @param string $js JavaScript source code.
	 * @return string Minified JavaScript.
	 */
	public static function minify_js( $js ) {
		/* Preserve string literals by replacing with placeholders */
		$preserved = array();
		$index     = 0;

		/* Replace string literals (single, double, and template) */
		$js = preg_replace_callback(
			'/([\'"])(?:\\\\.|(?!\1)[^\\\\])*\1/',
			function ( $match ) use ( &$preserved, &$index ) {
				$placeholder              = '___NBUF_STR_' . $index . '___';
				$preserved[ $placeholder ] = $match[0];
				++$index;
				return $placeholder;
			},
			$js
		);

		/* Handle template literals separately (can contain expressions) */
		$js = preg_replace_callback(
			'/`(?:[^`\\\\]|\\\\.)*`/',
			function ( $match ) use ( &$preserved, &$index ) {
				$placeholder              = '___NBUF_TPL_' . $index . '___';
				$preserved[ $placeholder ] = $match[0];
				++$index;
				return $placeholder;
			},
			$js
		);

		/*
		 * Preserve regex literals to prevent them being treated as comments.
		 * Regex literals can appear after: = ( , [ ! & | ? : ; { } return
		 * This pattern looks for these contexts followed by /.../ with optional flags.
		 */
		$js = preg_replace_callback(
			'/([\=\(\,\[\!\&\|\?\:\;\{\}]|return)\s*(\/(?:[^\/\\\\\n]|\\\\.)+\/[gimsuvy]*)/',
			function ( $match ) use ( &$preserved, &$index ) {
				$placeholder              = '___NBUF_RGX_' . $index . '___';
				$preserved[ $placeholder ] = $match[2];
				++$index;
				return $match[1] . $placeholder;
			},
			$js
		);

		/* Remove single-line comments */
		$js = preg_replace( '#//[^\n]*#', '', $js );

		/* Remove multi-line comments */
		$js = preg_replace( '#/\*[\s\S]*?\*/#', '', $js );

		/* Normalize line endings */
		$js = str_replace( array( "\r\n", "\r" ), "\n", $js );

		/* Remove leading/trailing whitespace from each line */
		$js = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $js );

		/* Collapse multiple spaces/tabs to single space */
		$js = preg_replace( '/[ \t]+/', ' ', $js );

		/* Remove spaces around safe punctuation (not operators that could be unary) */
		$js = preg_replace( '/\s*([{}()[\];,])\s*/', '$1', $js );

		/* Remove space before colons and after colons (object literals, ternary) */
		$js = preg_replace( '/\s*:\s*/', ':', $js );

		/* Remove newlines but preserve one where needed for ASI */
		$js = preg_replace( '/\n+/', "\n", $js );

		/* Remove newlines that are safe to remove */
		$js = preg_replace( '/\n(?=[{}()[\];,.:])/', '', $js );
		$js = preg_replace( '/([{}()[\];,])\n/', '$1', $js );

		/* Collapse remaining newlines to nothing (statements end with ; or }) */
		$js = str_replace( "\n", '', $js );

		/* Restore preserved strings */
		foreach ( $preserved as $placeholder => $original ) {
			$js = str_replace( $placeholder, $original, $js );
		}

		return trim( $js );
	}

	/**
	 * Clear all cached minified files.
	 *
	 * Called during plugin deactivation or when cache needs refresh.
	 *
	 * @since 1.5.0
	 * @return int Number of files deleted.
	 */
	public static function clear_cache() {
		$cache_dir = self::get_cache_dir();

		if ( ! file_exists( $cache_dir ) ) {
			return 0;
		}

		$deleted = 0;
		$files   = glob( $cache_dir . '*.min.js' );

		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Delete the entire cache directory.
	 *
	 * Called during plugin uninstall.
	 *
	 * @since 1.5.0
	 * @return bool True if directory was removed.
	 */
	public static function delete_cache_directory() {
		$cache_dir = self::get_cache_dir();

		if ( ! file_exists( $cache_dir ) ) {
			return true;
		}

		/* Delete all files in cache directory */
		$files = glob( $cache_dir . '*' );

		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}

		/* Remove the directory */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return rmdir( $cache_dir );
	}

	/**
	 * Helper method to enqueue a script with automatic minification.
	 *
	 * Use this instead of wp_enqueue_script() for plugin scripts.
	 *
	 * @since 1.5.0
	 * @param string           $handle    Script handle.
	 * @param string           $src       Relative path from plugin root (e.g., 'assets/js/frontend/account-page.js').
	 * @param array            $deps      Script dependencies.
	 * @param string|bool|null $ver       Version string. Default: NBUF_VERSION.
	 * @param bool             $in_footer Whether to enqueue in footer.
	 * @return void
	 */
	public static function enqueue_script( $handle, $src, $deps = array(), $ver = null, $in_footer = true ) {
		if ( null === $ver ) {
			$ver = defined( 'NBUF_VERSION' ) ? NBUF_VERSION : '1.0.0';
		}

		$source_path = NBUF_PLUGIN_DIR . $src;
		$source_url  = NBUF_PLUGIN_URL . $src;

		/* Try to get minified URL */
		$minified_url = self::get_minified_url( $source_path, $handle, $ver );

		if ( $minified_url ) {
			/* Use minified version - version is baked into filename */
			wp_enqueue_script( $handle, $minified_url, $deps, $ver, $in_footer );
		} else {
			/* Fall back to source */
			wp_enqueue_script( $handle, $source_url, $deps, $ver, $in_footer );
		}
	}
}
