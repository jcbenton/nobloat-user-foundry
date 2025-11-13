<?php
/**
 * Logs Menu Handler
 *
 * Creates a top-level "Logs" menu with submenu items for the 3 enterprise logging tables:
 * - User Activity Log (user-initiated actions)
 * - Admin Actions Log (admin actions on users/settings)
 * - Security Events Log (system security events)
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Includes
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs Menu Class
 *
 * @since 1.4.0
 */
class NBUF_Logs_Menu {

	/**
	 * Initialize logs menu
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ), 20 );
	}

	/**
	 * Add logs menu pages
	 *
	 * @return void
	 */
	public static function add_menu_pages() {
		/* Add top-level Logs menu */
		add_menu_page(
			__( 'Logs', 'nobloat-user-foundry' ),
			__( 'Logs', 'nobloat-user-foundry' ),
			'manage_options',
			'nbuf-logs',
			array( __CLASS__, 'render_logs_overview' ),
			'dashicons-list-view',
			59
		);

		/* User Activity Log submenu */
		add_submenu_page(
			'nbuf-logs',
			__( 'User Activity Log', 'nobloat-user-foundry' ),
			__( 'User Activity', 'nobloat-user-foundry' ),
			'manage_options',
			'nbuf-user-audit-log',
			array( 'NBUF_Audit_Log_Page', 'render_page' )
		);

		/* Admin Actions Log submenu */
		add_submenu_page(
			'nbuf-logs',
			__( 'Admin Actions Log', 'nobloat-user-foundry' ),
			__( 'Admin Actions', 'nobloat-user-foundry' ),
			'manage_options',
			'nbuf-admin-audit-log',
			array( 'NBUF_Admin_Audit_Log_Page', 'render' )
		);

		/* Security Events Log submenu */
		add_submenu_page(
			'nbuf-logs',
			__( 'Security Events Log', 'nobloat-user-foundry' ),
			__( 'Security Events', 'nobloat-user-foundry' ),
			'manage_options',
			'nbuf-security-log',
			array( 'NBUF_Security_Log_Page', 'render_page' )
		);

		/* Remove duplicate "Logs" submenu item that WordPress automatically adds */
		remove_submenu_page( 'nbuf-logs', 'nbuf-logs' );
	}

	/**
	 * Render logs overview page (not used - redirects to first submenu)
	 *
	 * @return void
	 */
	public static function render_logs_overview() {
		/* Redirect to User Activity Log */
		wp_safe_redirect( admin_url( 'admin.php?page=nbuf-user-audit-log' ) );
		exit;
	}
}
