<?php
/**
 * User Notes Admin Page
 *
 * Displays admin interface for managing user notes.
 * Includes searchable user dropdown and note management.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User notes admin page class.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @author     NoBloat
 */
class NBUF_User_Notes_Page {


	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 17 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_nbuf_search_users', array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'wp_ajax_nbuf_get_user_notes', array( __CLASS__, 'ajax_get_user_notes' ) );
		add_action( 'wp_ajax_nbuf_add_note', array( __CLASS__, 'ajax_add_note' ) );
		add_action( 'wp_ajax_nbuf_update_note', array( __CLASS__, 'ajax_update_note' ) );
		add_action( 'wp_ajax_nbuf_delete_note', array( __CLASS__, 'ajax_delete_note' ) );
	}

	/**
	 * Add user notes menu page.
	 *
	 * @since 1.0.0
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'nobloat-foundry',
			__( 'User Notes', 'nobloat-user-foundry' ),
			__( 'User Notes', 'nobloat-user-foundry' ),
			'manage_options',
			'nobloat-foundry-notes',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		/* Only load on user notes page */
		if ( 'nobloat-foundry_page_nobloat-foundry-notes' !== $hook ) {
			return;
		}

		/*
		* No external dependencies - using native HTML with AJAX
		*/
		/* Enqueue custom styles */
		wp_add_inline_style( 'wp-admin', self::get_custom_css() );

		/* Enqueue custom script */
		wp_enqueue_script( 'nbuf-user-notes', NBUF_PLUGIN_URL . 'assets/js/admin/user-notes.js', array( 'jquery' ), NBUF_VERSION, true );

		/* Pass AJAX URL and nonce to JavaScript */
		wp_localize_script(
			'nbuf-user-notes',
			'NBUF_UserNotes',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nbuf_user_notes_nonce' ),
			)
		);
	}

	/**
	 * Get custom CSS for user notes page.
	 *
	 * @since  1.0.0
	 * @return string    CSS code.
	 */
	private static function get_custom_css() {
		return '
		.nbuf-user-notes-container {
			max-width: 1200px;
			margin: 20px 0;
		}
		.nbuf-user-selector {
			background: #fff;
			padding: 20px;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			margin-bottom: 20px;
		}
		.nbuf-notes-section {
			background: #fff;
			padding: 20px;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			display: none;
		}
		.nbuf-notes-section.active {
			display: block;
		}
		.nbuf-note-item {
			padding: 15px;
			border: 1px solid #e5e5e5;
			border-radius: 4px;
			margin-bottom: 15px;
			background: #f9f9f9;
		}
		.nbuf-note-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
			font-size: 12px;
			color: #666;
		}
		.nbuf-note-content {
			margin-bottom: 10px;
			white-space: pre-wrap;
			word-wrap: break-word;
		}
		.nbuf-note-actions {
			display: flex;
			gap: 10px;
		}
		.nbuf-note-edit-form {
			display: none;
		}
		.nbuf-note-edit-form.active {
			display: block;
		}
		.nbuf-add-note-form {
			margin-top: 20px;
			padding-top: 20px;
			border-top: 1px solid #e5e5e5;
		}
		.nbuf-empty-state {
			text-align: center;
			padding: 40px 20px;
			color: #666;
		}
		#nbuf-user-search-input {
			min-width: 400px;
			padding: 8px 12px;
			font-size: 14px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
		}
		.nbuf-autocomplete-results {
			position: absolute;
			background: white;
			border: 1px solid #8c8f94;
			border-top: none;
			max-height: 300px;
			overflow-y: auto;
			min-width: 400px;
			z-index: 1000;
			display: none;
		}
		.nbuf-autocomplete-item {
			padding: 10px;
			cursor: pointer;
			border-bottom: 1px solid #f0f0f1;
		}
		.nbuf-autocomplete-item:hover {
			background: #f6f7f7;
		}
		.nbuf-note-meta {
			font-style: italic;
		}
		';
	}


	/**
	 * Render the user notes page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'User Notes', 'nobloat-user-foundry' ); ?></h1>
			<p class="description">
		<?php esc_html_e( 'Manage administrative notes about users. Search for a user below to view and manage their notes.', 'nobloat-user-foundry' ); ?>
			</p>

			<div class="nbuf-user-notes-container">
				<!-- User Selector -->
				<div class="nbuf-user-selector">
					<h2><?php esc_html_e( 'Select User', 'nobloat-user-foundry' ); ?></h2>
					<p class="description">
		<?php esc_html_e( 'Search by username, email, or display name (minimum 2 characters).', 'nobloat-user-foundry' ); ?>
					</p>
					<div style="position: relative;">
						<input type="text" id="nbuf-user-search-input" placeholder="<?php esc_attr_e( 'Search for a user...', 'nobloat-user-foundry' ); ?>" autocomplete="off">
					</div>
				</div>

				<!-- Loading State -->
				<div id="nbuf-notes-loading" class="nbuf-notes-section">
					<p><?php esc_html_e( 'Loading notes...', 'nobloat-user-foundry' ); ?></p>
				</div>

				<!-- Notes List -->
				<div id="nbuf-notes-list" class="nbuf-notes-section">
					<h2>
		<?php esc_html_e( 'Notes for:', 'nobloat-user-foundry' ); ?>
						<span id="nbuf-selected-user-name"></span>
					</h2>

					<div id="nbuf-notes-container"></div>

					<!-- Add Note Form -->
					<div class="nbuf-add-note-form">
						<h3><?php esc_html_e( 'Add New Note', 'nobloat-user-foundry' ); ?></h3>
						<textarea id="nbuf-new-note-content" class="large-text" rows="4" placeholder="<?php esc_attr_e( 'Enter note content...', 'nobloat-user-foundry' ); ?>"></textarea>
						<p>
							<button id="nbuf-add-note-btn" class="button button-primary"><?php esc_html_e( 'Add Note', 'nobloat-user-foundry' ); ?></button>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Search users.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_search_users() {
		check_ajax_referer( 'nbuf_user_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		/* Handle pre-selected user (direct ID) */
		if ( isset( $_POST['user_id'] ) ) {
			$user_id = intval( $_POST['user_id'] );
			$user    = get_user_by( 'id', $user_id );

			if ( $user ) {
				wp_send_json_success(
					array(
						'user' => array(
							'id'   => $user->ID,
							'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
						),
					)
				);
			}

			wp_send_json_error( array( 'message' => 'User not found' ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( empty( $search ) ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		/* Search users */
		$users = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 20,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Get user notes.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_get_user_notes() {
		check_ajax_referer( 'nbuf_user_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user ID' ) );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'User not found' ) );
		}

		$notes = NBUF_User_Notes::get_user_notes( $user_id );

		/* Format notes for display */
		$formatted_notes = array();
		foreach ( $notes as $note ) {
			$author            = get_user_by( 'id', $note->created_by );
			$formatted_notes[] = array(
				'id'                   => $note->id,
				'note_content'         => $note->note_content,
				'created_at'           => $note->created_at,
				'created_at_formatted' => mysql2date( 'M j, Y g:i A', $note->created_at ),
				'updated_at'           => $note->updated_at,
				'author_name'          => $author ? $author->display_name : __( 'Unknown', 'nobloat-user-foundry' ),
			);
		}

		wp_send_json_success(
			array(
				'notes' => $formatted_notes,
				'user'  => array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
				),
			)
		);
	}

	/**
	 * AJAX: Add note.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_add_note() {
		check_ajax_referer( 'nbuf_user_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$content = isset( $_POST['note_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) : '';

		if ( ! $user_id || empty( $content ) ) {
			wp_send_json_error( array( 'message' => 'Missing required fields' ) );
		}

		/* Verify target user exists */
		if ( ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid user ID' ) );
		}

		$current_user_id = get_current_user_id();
		$note_id         = NBUF_User_Notes::add_note( $user_id, $content, $current_user_id );

		if ( $note_id ) {
			wp_send_json_success( array( 'note_id' => $note_id ) );
		}

		wp_send_json_error( array( 'message' => 'Failed to add note' ) );
	}

	/**
	 * AJAX: Update note.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_update_note() {
		check_ajax_referer( 'nbuf_user_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
		$content = isset( $_POST['note_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) : '';

		if ( ! $note_id || empty( $content ) ) {
			wp_send_json_error( array( 'message' => 'Missing required fields' ) );
		}

		$success = NBUF_User_Notes::update_note( $note_id, $content );

		if ( $success ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => 'Failed to update note' ) );
	}

	/**
	 * AJAX: Delete note.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_delete_note() {
		check_ajax_referer( 'nbuf_user_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;

		if ( ! $note_id ) {
			wp_send_json_error( array( 'message' => 'Invalid note ID' ) );
		}

		$success = NBUF_User_Notes::delete_note( $note_id );

		if ( $success ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => 'Failed to delete note' ) );
	}
}

/* Initialize */
NBUF_User_Notes_Page::init();
