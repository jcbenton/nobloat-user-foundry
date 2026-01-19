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
			$upload_dir      = wp_upload_dir();
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
			$upload_dir      = wp_upload_dir();
			self::$cache_url = trailingslashit( $upload_dir['baseurl'] ) . 'nobloat/cache/';
		}
		return self::$cache_url;
	}

	/**
	 * Ensure cache directory exists with proper security.
	 *
	 * @since 1.5.0
	 * @return bool True if directory exists or was created.
	 */
	private static function ensure_cache_dir() {
		$cache_dir  = self::get_cache_dir();
		$parent_dir = dirname( rtrim( $cache_dir, '/' ) );

		if ( ! file_exists( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( ! mkdir( $cache_dir, 0755, true ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'NBUF Asset Minifier: Failed to create cache directory: ' . $cache_dir );
				}
				return false;
			}

			/*
			 * Ensure correct permissions (umask may have affected them).
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			chmod( $cache_dir, 0755 );
		}

		/* Create index.php in cache directory for security */
		$index_file = $cache_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}

		/* Create index.php in parent nobloat directory */
		$parent_index = $parent_dir . '/index.php';
		if ( ! file_exists( $parent_index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $parent_index, '<?php // Silence is golden.' );
		}

		/* Create .htaccess to prevent PHP execution in cache directory */
		$htaccess_file = $cache_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content  = "# Disable PHP execution\n";
			$htaccess_content .= "<FilesMatch \"\\.php$\">\n";
			$htaccess_content .= "    Order Deny,Allow\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</FilesMatch>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess_file, $htaccess_content );
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

		/* Validate handle - only allow alphanumeric, dashes, and underscores */
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $handle ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Invalid handle format: ' . esc_html( $handle ) );
			}
			return false;
		}

		/* Validate version - only allow safe version strings */
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $version ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Invalid version format: ' . esc_html( $version ) );
			}
			return false;
		}

		/*
		 * Verify source file exists and resolve to absolute path.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath
		$real_source_path = realpath( $source_path );
		if ( false === $real_source_path ) {
			return false;
		}

		/*
		 * Ensure source is within plugin directory (prevent arbitrary file reads).
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath
		$plugin_dir = realpath( NBUF_PLUGIN_DIR );
		if ( false === $plugin_dir || 0 !== strpos( $real_source_path, $plugin_dir ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Source path outside plugin directory: ' . esc_html( $source_path ) );
			}
			return false;
		}

		/* Build cache filename with version for cache busting */
		$cache_filename = $handle . '.' . $version . '.min.js';
		$cache_path     = self::get_cache_dir() . $cache_filename;
		$cache_url      = self::get_cache_url() . $cache_filename;

		/* Check if cached file exists and is newer than source */
		if ( file_exists( $cache_path ) ) {
			$source_mtime = filemtime( $real_source_path );
			$cache_mtime  = filemtime( $cache_path );

			if ( false !== $source_mtime && false !== $cache_mtime && $cache_mtime >= $source_mtime ) {
				return $cache_url;
			}
		}

		/* Generate minified file */
		if ( self::generate_minified_file( $real_source_path, $cache_path, $handle ) ) {
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

		/*
		 * Read source file.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source = file_get_contents( $source_path );
		if ( false === $source ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Failed to read source file: ' . esc_html( $source_path ) );
			}
			return false;
		}

		/* Minify the JavaScript */
		$minified = self::minify_js( $source );

		/* Write to temporary file first for atomic operation */
		$temp_path = $cache_path . '.' . wp_generate_password( 12, false ) . '.tmp';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $temp_path, $minified, LOCK_EX );

		if ( false === $result ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Failed to write temp file: ' . esc_html( $temp_path ) );
			}
			return false;
		}

		/*
		 * Atomic rename to final location.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $temp_path, $cache_path ) ) {
			wp_delete_file( $temp_path );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'NBUF Asset Minifier: Failed to rename temp file to: ' . esc_html( $cache_path ) );
			}
			return false;
		}

		return true;
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

		/* Escape glob special characters in handle to prevent pattern injection */
		$safe_handle = addcslashes( $handle, '[]?*' );
		$pattern     = $cache_dir . $safe_handle . '.*.min.js';
		$files       = glob( $pattern );
		$deleted     = 0;

		if ( ! empty( $files ) && is_array( $files ) ) {
			/*
			 * Get real cache dir path for validation.
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath
			$real_cache_dir = realpath( $cache_dir );

			foreach ( $files as $file ) {
				/* Don't delete the file we're about to create */
				if ( $file === $new_path ) {
					continue;
				}

				/*
				 * Verify file is within cache directory (defense in depth).
				 */
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_realpath
				$real_file = realpath( $file );

				if ( $real_file && $real_cache_dir && 0 === strpos( $real_file, $real_cache_dir ) ) {
					if ( is_file( $real_file ) ) {
						wp_delete_file( $real_file );
						++$deleted;
					}
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
		/*
		 * State machine approach: process character by character.
		 * 1. Remove comments first (they may contain apostrophes like "don't")
		 * 2. Preserve strings/templates/regex with placeholders
		 * 3. Then apply whitespace reduction
		 */

		$result    = '';
		$len       = strlen( $js );
		$i         = 0;
		$preserved = array();
		$index     = 0;

		while ( $i < $len ) {
			$char = $js[ $i ];

			/* Check for single-line comment */
			if ( '/' === $char && $i + 1 < $len && '/' === $js[ $i + 1 ] ) {
				/* Skip until end of line */
				while ( $i < $len && "\n" !== $js[ $i ] && "\r" !== $js[ $i ] ) {
					++$i;
				}
				continue;
			}

			/* Check for multi-line comment */
			if ( '/' === $char && $i + 1 < $len && '*' === $js[ $i + 1 ] ) {
				$i += 2;
				/* Skip until closing */
				while ( $i < $len ) {
					if ( '*' === $js[ $i ] && $i + 1 < $len && '/' === $js[ $i + 1 ] ) {
						$i += 2;
						break;
					}
					++$i;
				}
				/* Add space to prevent token merging */
				$result .= ' ';
				continue;
			}

			/* Check for string literals */
			if ( '"' === $char || "'" === $char ) {
				$quote  = $char;
				$string = $char;
				++$i;
				while ( $i < $len ) {
					$c = $js[ $i ];
					/* Unescaped newline = unterminated string in source */
					if ( "\n" === $c || "\r" === $c ) {
						break;
					}
					$string .= $c;
					if ( '\\' === $c && $i + 1 < $len ) {
						++$i;
						$string .= $js[ $i ];
					} elseif ( $c === $quote ) {
						break;
					}
					++$i;
				}
				++$i;
				$placeholder               = '___NBUF_STR_' . $index . '___';
				$preserved[ $placeholder ] = $string;
				++$index;
				$result .= $placeholder;
				continue;
			}

			/* Check for template literals */
			if ( '`' === $char ) {
				$string = $char;
				++$i;
				$depth = 0;
				while ( $i < $len ) {
					$c       = $js[ $i ];
					$string .= $c;

					if ( '\\' === $c && $i + 1 < $len ) {
						++$i;
						$string .= $js[ $i ];
					} elseif ( '$' === $c && $i + 1 < $len && '{' === $js[ $i + 1 ] ) {
						++$depth;
						++$i;
						$string .= $js[ $i ];
					} elseif ( $depth > 0 && ( '"' === $c || "'" === $c ) ) {
						/*
						 * String inside ${} expression - consume it to protect } chars.
						 * e.g., `${obj['}']}` - the } inside the string shouldn't close the expression.
						 */
						$inner_quote = $c;
						++$i;
						while ( $i < $len ) {
							$c2      = $js[ $i ];
							$string .= $c2;
							if ( '\\' === $c2 && $i + 1 < $len ) {
								++$i;
								$string .= $js[ $i ];
							} elseif ( $c2 === $inner_quote ) {
								break;
							}
							++$i;
						}
					} elseif ( '}' === $c && $depth > 0 ) {
						--$depth;
					} elseif ( '`' === $c && 0 === $depth ) {
						break;
					}
					++$i;
				}
				++$i;
				$placeholder               = '___NBUF_TPL_' . $index . '___';
				$preserved[ $placeholder ] = $string;
				++$index;
				$result .= $placeholder;
				continue;
			}

			/* Check for regex literals (after certain tokens) */
			if ( '/' === $char && $i + 1 < $len && '/' !== $js[ $i + 1 ] && '*' !== $js[ $i + 1 ] ) {
				/* Look back to see if this could be a regex */
				$lookback = rtrim( $result );
				$last     = ! empty( $lookback ) ? substr( $lookback, -1 ) : '';

				/*
				 * Conservative regex detection.
				 * Removed ) and } from triggers - they're ambiguous without full parsing.
				 * e.g., (a + b) / c is division, but if (x) /pattern/ is regex.
				 */
				$regex_triggers = array( '=', '(', ',', '[', '!', '&', '|', '?', ':', ';', '{', "\n", '' );
				$is_regex       = in_array( $last, $regex_triggers, true );

				if ( ! $is_regex ) {
					/* Check for keywords that precede regex */
					$keywords = array( 'return', 'typeof', 'void', 'delete', 'throw', 'new', 'case', 'in' );
					foreach ( $keywords as $kw ) {
						if ( preg_match( '/' . preg_quote( $kw, '/' ) . '$/', $lookback ) ) {
							$is_regex = true;
							break;
						}
					}
				}

				if ( $is_regex ) {
					$regex = $char;
					++$i;
					while ( $i < $len ) {
						$c      = $js[ $i ];
						$regex .= $c;
						if ( '\\' === $c && $i + 1 < $len ) {
							++$i;
							$regex .= $js[ $i ];
						} elseif ( '[' === $c ) {
							/*
							 * Character class - / is allowed inside [].
							 * e.g., /[a/b]/ is valid regex matching 'a', '/', or 'b'.
							 */
							++$i;
							while ( $i < $len ) {
								$c2     = $js[ $i ];
								$regex .= $c2;
								if ( '\\' === $c2 && $i + 1 < $len ) {
									++$i;
									$regex .= $js[ $i ];
								} elseif ( ']' === $c2 ) {
									break;
								}
								++$i;
							}
						} elseif ( '/' === $c ) {
							++$i;
							/* Capture flags - including 'd' for ES2022 hasIndices */
							while ( $i < $len && preg_match( '/[dgimsuyv]/', $js[ $i ] ) ) {
								$regex .= $js[ $i ];
								++$i;
							}
							break;
						}
						++$i;
					}
					$placeholder               = '___NBUF_RGX_' . $index . '___';
					$preserved[ $placeholder ] = $regex;
					++$index;
					$result .= $placeholder;
					continue;
				}
			}

			$result .= $char;
			++$i;
		}

		$js = $result;

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

		/*
		 * Replace remaining newlines with space (not remove entirely).
		 * This preserves ASI for keywords like return, throw, break, continue.
		 * e.g., "return\nvalue" becomes "return value" not "returnvalue"
		 */
		$js = str_replace( "\n", ' ', $js );

		/* Clean up any double spaces created */
		$js = preg_replace( '/  +/', ' ', $js );

		/*
		 * Ensure space after } and ) when followed by identifier/keyword.
		 * This prevents }var, }if, }function, )function etc from being concatenated.
		 */
		$js = preg_replace( '/}([a-zA-Z_$])/', '} $1', $js );
		$js = preg_replace( '/\)([a-zA-Z_$])/', ') $1', $js );

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

		/*
		 * Remove the directory.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		return rmdir( $cache_dir );
	}

	/**
	 * Helper method to enqueue a script with automatic minification.
	 *
	 * Use this instead of wp_enqueue_script() for plugin scripts.
	 *
	 * @since 1.5.0
	 * @param string             $handle    Script handle.
	 * @param string             $src       Relative path from plugin root (e.g., 'assets/js/frontend/account-page.js').
	 * @param array<int, string> $deps    Script dependencies.
	 * @param string|bool|null   $ver       Version string. Default: NBUF_VERSION.
	 * @param bool               $in_footer Whether to enqueue in footer.
	 * @return void
	 */
	public static function enqueue_script( $handle, $src, $deps = array(), $ver = null, $in_footer = true ): void {
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
