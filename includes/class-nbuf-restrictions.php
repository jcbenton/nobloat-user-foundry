<?php
/**
 * NoBloat User Foundry - Restrictions Orchestrator
 *
 * Central orchestrator for all restriction features (menu and content).
 * Initializes restriction modules based on settings and provides
 * helper methods for checking access.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restrictions
 *
 * Manages content, menu, widget, and taxonomy restrictions.
 */
class NBUF_Restrictions {


	/**
	 * Initialize restrictions system
	 */
	public static function init() {
		/* Check if restrictions system is enabled */
		$enabled = NBUF_Options::get( 'nbuf_restrictions_enabled', false );
		if ( ! $enabled ) {
			return;
		}

		/* Initialize menu restrictions if enabled */
		$menu_enabled = NBUF_Options::get( 'nbuf_restrictions_menu_enabled', false );
		if ( $menu_enabled ) {
			NBUF_Restriction_Menu::init();
		}

		/* Initialize content restrictions if enabled */
		$content_enabled = NBUF_Options::get( 'nbuf_restrictions_content_enabled', false );
		if ( $content_enabled ) {
			NBUF_Restriction_Content::init();
		}

		/* Initialize widget restrictions if enabled */
		$widgets_enabled = NBUF_Options::get( 'nbuf_restrict_widgets_enabled', false );
		if ( $widgets_enabled ) {
			NBUF_Restriction_Widget::init();
		}

		/* Initialize taxonomy restrictions if enabled */
		$taxonomies_enabled = NBUF_Options::get( 'nbuf_restrict_taxonomies_enabled', false );
		if ( $taxonomies_enabled ) {
			NBUF_Restriction_Taxonomy::init();
		}

		/* Register admin UI */
		if ( is_admin() ) {
			NBUF_Restriction_Metabox::init();
		}
	}

	/**
	 * Create database tables for restrictions
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		/* Create menu restrictions table */
		$menu_table = $wpdb->prefix . 'nbuf_menu_restrictions';
		$menu_sql   = "CREATE TABLE IF NOT EXISTS `{$menu_table}` (
			menu_item_id BIGINT(20) UNSIGNED NOT NULL,
			visibility VARCHAR(20) NOT NULL DEFAULT 'everyone',
			allowed_roles TEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (menu_item_id),
			KEY visibility (visibility)
		) {$charset_collate};";

		/* Create content restrictions table */
		$content_table = $wpdb->prefix . 'nbuf_content_restrictions';
		$content_sql   = "CREATE TABLE IF NOT EXISTS `{$content_table}` (
			content_id BIGINT(20) UNSIGNED NOT NULL,
			content_type VARCHAR(20) NOT NULL DEFAULT 'post',
			visibility VARCHAR(20) NOT NULL DEFAULT 'everyone',
			allowed_roles TEXT,
			restriction_action VARCHAR(20) NOT NULL DEFAULT 'message',
			custom_message TEXT,
			redirect_url VARCHAR(255),
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (content_id, content_type),
			KEY visibility (visibility),
			KEY content_type (content_type),
			KEY composite_lookup (content_type, visibility)
		) {$charset_collate};";

		/* Execute table creation */
		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $menu_sql );
		dbDelta( $content_sql );
	}

	/**
	 * Check if user can access content
	 *
	 * @param  int    $content_id   Content ID (post/page ID).
	 * @param  string $content_type Content type (post, page, etc.).
	 * @param  int    $user_id      Optional user ID (defaults to current user).
	 * @return bool True if user has access, false otherwise.
	 */
	public static function can_access_content( $content_id, $content_type = 'post', $user_id = null ) {
		/* Get restriction */
		$restriction = self::get_content_restriction( $content_id, $content_type );

		/* No restriction = allow access */
		if ( ! $restriction ) {
			return true;
		}

		/* Use abstract class method to check access */
		return Abstract_NBUF_Restriction::check_access(
			$restriction['visibility'],
			$restriction['allowed_roles'],
			$user_id
		);
	}

	/**
	 * Get content restriction details
	 *
	 * @param  int    $content_id   Content ID.
	 * @param  string $content_type Content type.
	 * @return array|null Restriction data or null if no restrictions.
	 */
	public static function get_content_restriction( $content_id, $content_type = 'post' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_content_restrictions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		$restriction = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE content_id = %d AND content_type = %s',
				$table,
				$content_id,
				$content_type
			),
			ARRAY_A
		);

		/* Return null if no restriction found */
		if ( ! $restriction ) {
			return null;
		}

		/* Parse allowed_roles JSON */
		if ( ! empty( $restriction['allowed_roles'] ) ) {
			$restriction['allowed_roles'] = json_decode( $restriction['allowed_roles'], true );
			if ( ! is_array( $restriction['allowed_roles'] ) ) {
				$restriction['allowed_roles'] = array();
			}
		} else {
			$restriction['allowed_roles'] = array();
		}

		return $restriction;
	}

	/**
	 * Get menu item restriction
	 *
	 * @param  int $menu_item_id Menu item ID.
	 * @return array|null Restriction data or null if no restrictions.
	 */
	public static function get_menu_restriction( $menu_item_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nbuf_menu_restrictions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations
		$restriction = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE menu_item_id = %d',
				$table,
				$menu_item_id
			),
			ARRAY_A
		);

		/* Return null if no restriction found */
		if ( ! $restriction ) {
			return null;
		}

		/* Parse allowed_roles JSON */
		if ( ! empty( $restriction['allowed_roles'] ) ) {
			$restriction['allowed_roles'] = json_decode( $restriction['allowed_roles'], true );
			if ( ! is_array( $restriction['allowed_roles'] ) ) {
				$restriction['allowed_roles'] = array();
			}
		} else {
			$restriction['allowed_roles'] = array();
		}

		return $restriction;
	}

	/**
	 * Delete all restriction data (for uninstall)
	 */
	public static function delete_all_data() {
		global $wpdb;

		$menu_table    = $wpdb->prefix . 'nbuf_menu_restrictions';
		$content_table = $wpdb->prefix . 'nbuf_content_restrictions';

     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $menu_table ) );
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $content_table ) );
	}
}
