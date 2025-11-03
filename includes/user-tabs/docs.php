<?php
/**
 * Documentation Tab
 *
 * Loads and displays HTML documentation from plugin-docs.html
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$docs_path = NBUF_PLUGIN_DIR . 'docs/plugin-docs.html';
echo '<h2>' . esc_html__( 'Documentation', 'nobloat-user-foundry' ) . '</h2>';

if ( file_exists( $docs_path ) ) {
	/*
	 * Load HTML documentation file
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local documentation file, not remote URL.
	$content = file_get_contents( $docs_path );

	/* Extract just the body content (skip the full HTML wrapper) */
	if ( preg_match( '/<body[^>]*>(.*?)<\/body>/s', $content, $matches ) ) {
		/* Found body tag - extract content from within it */
		$content = $matches[1];
	} elseif ( preg_match( '/<div class="docs-container">(.*?)<\/div>\s*<\/body>/s', $content, $matches ) ) {
		/* Alternative: extract from docs-container div */
		$content = '<div class="docs-container">' . $matches[1] . '</div>';
	}

	/* Extract and output the styles */
	if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $content, $style_matches ) ) {
		echo ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS from plugin docs file.
		/* Remove style tag from content since we already output it */
		$content = preg_replace( '/<style[^>]*>.*?<\/style>/s', '', $content );
	}

	/* Output the content */
	echo wp_kses_post( $content );

} else {
	echo '<p>' . esc_html__( 'No documentation file found. Place an HTML file at /docs/plugin-docs.html.', 'nobloat-user-foundry' ) . '</p>';
}
