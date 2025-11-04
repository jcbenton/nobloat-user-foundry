<?php
/**
 * User Notes Data Management
 *
 * Handles all user notes data stored in custom table.
 * Allows admins to add, edit, and delete notes about users.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/*
 * Direct database access is architectural for user notes management.
 * Custom nbuf_user_notes table stores admin notes and cannot use
 * WordPress's standard meta APIs.
 */

/**
 * User notes data management class.
 *
 * Provides interface for managing admin notes about users.
 *
 * @since      1.0.0
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @author     NoBloat
 */
class NBUF_User_Notes {


	/**
	 * Get all notes for a specific user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id  User ID.
	 * @param  string $order_by Order by column (default: 'created_at').
	 * @param  string $order    Sort order (ASC or DESC, default: DESC).
	 * @return array               Array of note objects.
	 */
	public static function get_user_notes( $user_id, $order_by = 'created_at', $order = 'DESC' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		/* Validate order direction */
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		/* Validate order_by column */
		$allowed_columns = array( 'created_at', 'updated_at', 'id' );
		if ( ! in_array( $order_by, $allowed_columns, true ) ) {
			$order_by = 'created_at';
		}

		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d ORDER BY $order_by $order", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);

		return $notes ? $notes : array();
	}

	/**
	 * Get a specific note by ID.
	 *
	 * @since  1.0.0
	 * @param  int $note_id Note ID.
	 * @return object|null              Note object or null if not found.
	 */
	public static function get_note( $note_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		$note = $wpdb->get_row(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT * FROM $table_name WHERE id = %d",
				$note_id
			)
		);

		return $note;
	}

	/**
	 * Add a new note for a user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id      User ID the note is about.
	 * @param  string $note_content Note content.
	 * @param  int    $created_by   Admin user ID creating the note.
	 * @return int|false                Note ID on success, false on failure.
	 */
	public static function add_note( $user_id, $note_content, $created_by ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'      => (int) $user_id,
				'note_content' => sanitize_textarea_field( $note_content ),
				'created_by'   => (int) $created_by,
				'created_at'   => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( $inserted ) {
			/* Log note creation */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				$user  = get_user_by( 'id', $user_id );
				$admin = get_user_by( 'id', $created_by );
				NBUF_Audit_Log::log(
					$user_id,
					'user_note_added',
					'info',
					sprintf( 'Admin note added by %s', $admin ? $admin->user_login : 'Unknown' ),
					array(
						'note_id'  => $wpdb->insert_id,
						'admin_id' => $created_by,
					)
				);
			}

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing note.
	 *
	 * @since  1.0.0
	 * @param  int    $note_id      Note ID to update.
	 * @param  string $note_content New note content.
	 * @return bool                     True on success, false on failure.
	 */
	public static function update_note( $note_id, $note_content ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		/* Get note before update for logging */
		$note = self::get_note( $note_id );

		$updated = $wpdb->update(
			$table_name,
			array(
				'note_content' => sanitize_textarea_field( $note_content ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $note_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated && $note ) {
			/* Log note update */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				$admin_id = get_current_user_id();
				$admin    = get_user_by( 'id', $admin_id );
				NBUF_Audit_Log::log(
					$note->user_id,
					'user_note_updated',
					'info',
					sprintf( 'Admin note updated by %s', $admin ? $admin->user_login : 'Unknown' ),
					array(
						'note_id'  => $note_id,
						'admin_id' => $admin_id,
					)
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Delete a note.
	 *
	 * @since  1.0.0
	 * @param  int $note_id Note ID to delete.
	 * @return bool               True on success, false on failure.
	 */
	public static function delete_note( $note_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		/* Get note before deletion for logging */
		$note = self::get_note( $note_id );

		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => (int) $note_id ),
			array( '%d' )
		);

		if ( $deleted && $note ) {
			/* Log note deletion */
			if ( class_exists( 'NBUF_Audit_Log' ) ) {
				$admin_id = get_current_user_id();
				$admin    = get_user_by( 'id', $admin_id );
				NBUF_Audit_Log::log(
					$note->user_id,
					'user_note_deleted',
					'info',
					sprintf( 'Admin note deleted by %s', $admin ? $admin->user_login : 'Unknown' ),
					array(
						'note_id'  => $note_id,
						'admin_id' => $admin_id,
					)
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Get count of notes for a user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return int                 Number of notes.
	 */
	public static function get_note_count( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		$count = $wpdb->get_var(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		return (int) $count;
	}

	/**
	 * Delete all notes for a specific user.
	 *
	 * Used when a user is deleted from WordPress.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return int|false          Number of rows deleted or false on failure.
	 */
	public static function delete_user_notes( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		$deleted = $wpdb->delete(
			$table_name,
			array( 'user_id' => (int) $user_id ),
			array( '%d' )
		);

		return $deleted;
	}

	/**
	 * Search for users with notes containing specific text.
	 *
	 * @since  1.0.0
	 * @param  string $search_term Search term.
	 * @param  int    $limit       Maximum results (default: 50).
	 * @return array                   Array of user IDs with matching notes.
	 */
	public static function search_notes( $search_term, $limit = 50 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_notes';

		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT DISTINCT user_id FROM $table_name WHERE note_content LIKE %s LIMIT %d",
				'%' . $wpdb->esc_like( $search_term ) . '%',
				$limit
			)
		);

		return $user_ids ? $user_ids : array();
	}

	/**
	 * Initialize user notes profile link.
	 *
	 * Adds a link to the user notes page in the WordPress user editor.
	 *
	 * @since 1.0.0
	 */
	public static function init_profile_link() {
		if ( is_admin() ) {
			add_action( 'show_user_profile', array( __CLASS__, 'render_profile_link' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_link' ) );
		}
	}

	/**
	 * Render user notes link in user profile.
	 *
	 * @since 1.0.0
	 * @param WP_User $user User object.
	 */
	public static function render_profile_link( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$note_count = self::get_note_count( $user->ID );
		$notes_url  = add_query_arg(
			array(
				'page'    => 'nobloat-foundry-notes',
				'user_id' => $user->ID,
			),
			admin_url( 'admin.php' )
		);
		?>
		<h2><?php esc_html_e( 'User Notes', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Admin Notes', 'nobloat-user-foundry' ); ?></th>
				<td>
					<p>
						<a href="<?php echo esc_url( $notes_url ); ?>" class="button button-secondary">
		<?php
			/* translators: %d: number of notes */
			printf( esc_html__( 'View Notes (%d)', 'nobloat-user-foundry' ), (int) $note_count );
		?>
						</a>
					</p>
					<p class="description">
		<?php esc_html_e( 'View and manage administrative notes for this user.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/* Initialize profile link */
NBUF_User_Notes::init_profile_link();
