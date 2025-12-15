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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $order_by and $order are whitelisted above, user_id is prepared.
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d ORDER BY $order_by $order",
				$table_name,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

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
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
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
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$table_name,
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
				'SELECT DISTINCT user_id FROM %i WHERE note_content LIKE %s LIMIT %d',
				$table_name,
				'%' . $wpdb->esc_like( $search_term ) . '%',
				$limit
			)
		);

		return $user_ids ? $user_ids : array();
	}

	/**
	 * Initialize user notes profile section.
	 *
	 * Adds the notes tab to the WordPress user editor.
	 *
	 * @since 1.0.0
	 */
	public static function init_profile_link() {
		if ( is_admin() ) {
			add_action( 'show_user_profile', array( __CLASS__, 'render_notes_section' ), 99 );
			add_action( 'edit_user_profile', array( __CLASS__, 'render_notes_section' ), 99 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_profile_assets' ) );
			add_action( 'wp_ajax_nbuf_profile_add_note', array( __CLASS__, 'ajax_profile_add_note' ) );
			add_action( 'wp_ajax_nbuf_profile_delete_note', array( __CLASS__, 'ajax_profile_delete_note' ) );
		}
	}

	/**
	 * Enqueue assets for user profile notes section.
	 *
	 * @since 1.5.0
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_profile_assets( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.nbuf-notes-list {
				margin-bottom: 20px;
			}
			.nbuf-note-item {
				padding: 15px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				margin-bottom: 10px;
				background: #f9f9f9;
			}
			.nbuf-note-header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-bottom: 8px;
			}
			.nbuf-note-meta {
				font-size: 12px;
				color: #646970;
			}
			.nbuf-note-actions a {
				color: #b32d2e;
				text-decoration: none;
				font-size: 12px;
			}
			.nbuf-note-actions a:hover {
				color: #a00;
			}
			.nbuf-note-content {
				white-space: pre-wrap;
				word-wrap: break-word;
				line-height: 1.5;
			}
			.nbuf-add-note-form textarea {
				width: 100%;
				max-width: 600px;
			}
			.nbuf-notes-empty {
				color: #646970;
				font-style: italic;
				padding: 20px 0;
			}
			'
		);
	}

	/**
	 * Render full notes section in user profile.
	 *
	 * @since 1.5.0
	 * @param WP_User $user User object.
	 */
	public static function render_notes_section( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$notes = self::get_user_notes( $user->ID );
		$nonce = wp_create_nonce( 'nbuf_profile_notes_nonce' );
		?>
		<h2><?php esc_html_e( 'Notes', 'nobloat-user-foundry' ); ?></h2>
		<div class="nbuf-notes-section-content">
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Administrative notes about this user. Only visible to admins.', 'nobloat-user-foundry' ); ?>
			</p>

			<!-- Existing Notes -->
			<div class="nbuf-notes-list" id="nbuf-profile-notes-list">
				<?php if ( empty( $notes ) ) : ?>
					<p class="nbuf-notes-empty"><?php esc_html_e( 'No notes yet.', 'nobloat-user-foundry' ); ?></p>
				<?php else : ?>
					<?php foreach ( $notes as $note ) : ?>
						<?php
						$author = get_user_by( 'id', $note->created_by );
						?>
						<div class="nbuf-note-item" data-note-id="<?php echo esc_attr( $note->id ); ?>">
							<div class="nbuf-note-header">
								<span class="nbuf-note-meta">
									<?php
									printf(
										/* translators: 1: author name, 2: date */
										esc_html__( 'By %1$s on %2$s', 'nobloat-user-foundry' ),
										'<strong>' . esc_html( $author ? $author->display_name : __( 'Unknown', 'nobloat-user-foundry' ) ) . '</strong>',
										esc_html( mysql2date( 'M j, Y \a\t g:i A', $note->created_at ) )
									);
									?>
								</span>
								<span class="nbuf-note-actions">
									<a href="#" class="nbuf-delete-note" data-note-id="<?php echo esc_attr( $note->id ); ?>">
										<?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?>
									</a>
								</span>
							</div>
							<div class="nbuf-note-content"><?php echo esc_html( $note->note_content ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Add Note Form -->
			<div class="nbuf-add-note-form">
				<h3 style="font-size: 14px; margin-bottom: 10px;"><?php esc_html_e( 'Add Note', 'nobloat-user-foundry' ); ?></h3>
				<textarea id="nbuf-new-profile-note" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Enter note...', 'nobloat-user-foundry' ); ?>"></textarea>
				<p style="margin-top: 10px;">
					<button type="button" id="nbuf-add-profile-note-btn" class="button button-primary">
						<?php esc_html_e( 'Add Note', 'nobloat-user-foundry' ); ?>
					</button>
				</p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var userId = <?php echo absint( $user->ID ); ?>;
			var nonce = '<?php echo esc_js( $nonce ); ?>';

			/* Add note */
			$('#nbuf-add-profile-note-btn').on('click', function() {
				var $btn = $(this);
				var content = $('#nbuf-new-profile-note').val().trim();

				if (!content) {
					alert('<?php echo esc_js( __( 'Please enter a note.', 'nobloat-user-foundry' ) ); ?>');
					return;
				}

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Adding...', 'nobloat-user-foundry' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_profile_add_note',
						user_id: userId,
						note_content: content,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error adding note.', 'nobloat-user-foundry' ) ); ?>');
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Add Note', 'nobloat-user-foundry' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Error adding note.', 'nobloat-user-foundry' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Add Note', 'nobloat-user-foundry' ) ); ?>');
					}
				});
			});

			/* Delete note */
			$(document).on('click', '.nbuf-delete-note', function(e) {
				e.preventDefault();

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this note?', 'nobloat-user-foundry' ) ); ?>')) {
					return;
				}

				var $link = $(this);
				var noteId = $link.data('note-id');
				var $noteItem = $link.closest('.nbuf-note-item');

				$link.text('<?php echo esc_js( __( 'Deleting...', 'nobloat-user-foundry' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_profile_delete_note',
						note_id: noteId,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							$noteItem.fadeOut(300, function() {
								$(this).remove();
								/* Check if empty */
								if ($('#nbuf-profile-notes-list .nbuf-note-item').length === 0) {
									$('#nbuf-profile-notes-list').html('<p class="nbuf-notes-empty"><?php echo esc_js( __( 'No notes yet.', 'nobloat-user-foundry' ) ); ?></p>');
								}
							});
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Error deleting note.', 'nobloat-user-foundry' ) ); ?>');
							$link.text('<?php echo esc_js( __( 'Delete', 'nobloat-user-foundry' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Error deleting note.', 'nobloat-user-foundry' ) ); ?>');
						$link.text('<?php echo esc_js( __( 'Delete', 'nobloat-user-foundry' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Add note from profile page.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_profile_add_note() {
		check_ajax_referer( 'nbuf_profile_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'nobloat-user-foundry' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$content = isset( $_POST['note_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) : '';

		if ( ! $user_id || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'nobloat-user-foundry' ) ) );
		}

		/* Verify target user exists */
		if ( ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'nobloat-user-foundry' ) ) );
		}

		$note_id = self::add_note( $user_id, $content, get_current_user_id() );

		if ( $note_id ) {
			wp_send_json_success( array( 'note_id' => $note_id ) );
		}

		wp_send_json_error( array( 'message' => __( 'Failed to add note.', 'nobloat-user-foundry' ) ) );
	}

	/**
	 * AJAX: Delete note from profile page.
	 *
	 * @since 1.5.0
	 */
	public static function ajax_profile_delete_note() {
		check_ajax_referer( 'nbuf_profile_notes_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'nobloat-user-foundry' ) ) );
		}

		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;

		if ( ! $note_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note ID.', 'nobloat-user-foundry' ) ) );
		}

		$success = self::delete_note( $note_id );

		if ( $success ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Failed to delete note.', 'nobloat-user-foundry' ) ) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/* Initialize profile link */
NBUF_User_Notes::init_profile_link();
