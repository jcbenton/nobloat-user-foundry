<?php
/**
 * Docs - Overview
 *
 * Loads and displays HTML documentation from plugin-docs.html
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_docs_path = NBUF_PLUGIN_DIR . 'docs/plugin-docs.html';
?>

<h2><?php esc_html_e( 'Plugin Overview', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'NoBloat User Foundry is a comprehensive user management solution for WordPress.', 'nobloat-user-foundry' ); ?>
</p>

<?php
if ( file_exists( $nbuf_docs_path ) ) {
	/*
	 * Load HTML documentation file
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local documentation file, not remote URL
	$nbuf_content = file_get_contents( $nbuf_docs_path );

	if ( false === $nbuf_content ) {
		NBUF_Security_Log::log(
			'file_read_failed',
			'warning',
			'Failed to read documentation file',
			array(
				'file_path' => $nbuf_docs_path,
				'context'   => 'documentation_tab_load',
				'user_id'   => get_current_user_id(),
			)
		);
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Error: Unable to read documentation file.', 'nobloat-user-foundry' ) . '</p></div>';
		return;
	}

	/* Extract just the body content (skip the full HTML wrapper) */
	if ( preg_match( '/<body[^>]*>(.*?)<\/body>/s', $nbuf_content, $matches ) ) {
		/* Found body tag - extract content from within it */
		$nbuf_content = $matches[1];
	} elseif ( preg_match( '/<div class="docs-container">(.*?)<\/div>\s*<\/body>/s', $nbuf_content, $matches ) ) {
		/* Alternative: extract from docs-container div */
		$nbuf_content = '<div class="docs-container">' . $matches[1] . '</div>';
	}

	/* Extract and output the styles */
	if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $nbuf_content, $style_matches ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin documentation CSS.
		echo '';
		/* Remove style tag from content since we already output it */
		$nbuf_content = preg_replace( '/<style[^>]*>.*?<\/style>/s', '', $nbuf_content );
	}

	/*
	 * Output the content.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin documentation HTML.
	echo $nbuf_content;

} else {
	echo '<p>' . esc_html__( 'No documentation file found. Place an HTML file at /docs/plugin-docs.html.', 'nobloat-user-foundry' ) . '</p>';
}
