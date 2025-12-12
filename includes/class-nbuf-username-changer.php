<?php
/**
 * NoBloat User Foundry - Username Changer
 *
 * Allows administrators to change usernames for other users.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Username Changer Class
 *
 * Handles username changes with proper validation and security.
 */
class NBUF_Username_Changer {

	/**
	 * Initialize username changer functionality
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		/* Modify the existing username field via JavaScript */
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		/* Handle the save */
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_username' ), 10 );

		/* Add nonce field to the form */
		add_action( 'edit_user_profile', array( __CLASS__, 'add_nonce_field' ), 1 );
	}

	/**
	 * Enqueue assets for user profile pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'user-edit.php' !== $hook ) {
			return;
		}

		/* Only for admins */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.nbuf-username-warning {
				margin-top: 10px;
				padding: 10px 12px;
				background: #fff8e5;
				border-left: 4px solid #dba617;
				color: #6e4e00;
				font-size: 13px;
			}
			.nbuf-username-warning ul {
				margin: 8px 0 0 18px;
				padding: 0;
			}
			.nbuf-username-warning li {
				margin-bottom: 2px;
			}
			'
		);

		/* Pass current user ID to JavaScript */
		$current_user_id = get_current_user_id();

		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				/* Get user ID from hidden form field (works after POST too) */
				var \$userIdField = $('input#user_id');
				var editingUserId = \$userIdField.length ? parseInt(\$userIdField.val(), 10) : 0;
				var currentUserId = {$current_user_id};

				/* Don't modify if editing own profile or no user_id */
				if (!editingUserId || editingUserId === currentUserId) {
					return;
				}

				/* Find the username row */
				var \$usernameRow = $('input#user_login').closest('tr');
				if (\$usernameRow.length === 0) return;

				/* Get the current username */
				var \$usernameTd = \$usernameRow.find('td');
				var \$existingInput = \$usernameTd.find('input#user_login');
				var currentUsername = \$existingInput.val() || \$usernameTd.contents().filter(function() { return this.nodeType === 3; }).text().trim();

				/* Replace the td contents with an editable field */
				\$usernameTd.html(
					'<input type=\"text\" name=\"nbuf_new_username\" id=\"nbuf_new_username\" value=\"' + currentUsername + '\" class=\"regular-text\" autocomplete=\"off\">' +
					'<p class=\"description\">' + 'Allowed: letters, numbers, spaces, underscores, hyphens, periods, @' + '</p>' +
					'<div class=\"nbuf-username-warning\">' +
						'<strong>Warning: Changing a username has important implications:</strong>' +
						'<ul>' +
							'<li>The user must log in with the new username</li>' +
							'<li>Third-party integrations referencing the username may be affected</li>' +
						'</ul>' +
					'</div>'
				);
			});
			"
		);
	}

	/**
	 * Add nonce field to user edit form
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function add_nonce_field( $user ) {
		/* Only for admins editing other users */
		if ( ! current_user_can( 'manage_options' ) || get_current_user_id() === $user->ID ) {
			return;
		}

		wp_nonce_field( 'nbuf_username_change', 'nbuf_username_nonce' );
	}

	/**
	 * Save username change
	 *
	 * @param int $user_id User ID being updated.
	 */
	public static function save_username( $user_id ) {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_username_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_username_nonce'] ) ), 'nbuf_username_change' ) ) {
			return;
		}

		/* Check permissions - must be admin */
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Can't edit own username */
		if ( get_current_user_id() === $user_id ) {
			return;
		}

		/* Get new username */
		$new_username = isset( $_POST['nbuf_new_username'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_new_username'] ) ) : '';

		/* If empty, no change requested */
		if ( empty( $new_username ) ) {
			return;
		}

		/* Get current user data */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* If same as current, no change needed */
		if ( $new_username === $user->user_login ) {
			return;
		}

		/* Validate username format using WordPress rules */
		$sanitized = sanitize_user( $new_username, true );
		if ( $sanitized !== $new_username ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'username_invalid',
						__( '<strong>Error:</strong> Username contains invalid characters. Allowed: letters, numbers, spaces, underscores, hyphens, periods, and @ symbol.', 'nobloat-user-foundry' )
					);
				}
			);
			return;
		}

		/* Check WordPress username validation */
		if ( ! validate_username( $new_username ) ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'username_invalid',
						__( '<strong>Error:</strong> Username is not valid according to WordPress rules.', 'nobloat-user-foundry' )
					);
				}
			);
			return;
		}

		/* Check minimum length */
		if ( strlen( $new_username ) < 3 ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'username_short',
						__( '<strong>Error:</strong> Username must be at least 3 characters long.', 'nobloat-user-foundry' )
					);
				}
			);
			return;
		}

		/* Check if username already exists */
		if ( username_exists( $new_username ) ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'username_exists',
						__( '<strong>Error:</strong> This username is already in use by another account.', 'nobloat-user-foundry' )
					);
				}
			);
			return;
		}

		/* Store old username for logging */
		$old_username = $user->user_login;

		/* Perform the update */
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->users,
			array( 'user_login' => $new_username ),
			array( 'ID' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			add_action(
				'user_profile_update_errors',
				function ( $errors ) {
					$errors->add(
						'username_update_failed',
						__( '<strong>Error:</strong> Failed to update username in database.', 'nobloat-user-foundry' )
					);
				}
			);
			return;
		}

		/* Clear user cache */
		clean_user_cache( $user_id );

		/* Log the change */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			NBUF_Audit_Log::log(
				$user_id,
				'account',
				'username_changed',
				sprintf(
					/* translators: 1: old username, 2: new username */
					__( 'Username changed from "%1$s" to "%2$s"', 'nobloat-user-foundry' ),
					$old_username,
					$new_username
				),
				array(
					'old_username' => $old_username,
					'new_username' => $new_username,
					'changed_by'   => get_current_user_id(),
				)
			);
		}

		/* Add success notice */
		add_action(
			'admin_notices',
			function () use ( $old_username, $new_username ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: old username, 2: new username */
							esc_html__( 'Username successfully changed from "%1$s" to "%2$s".', 'nobloat-user-foundry' ),
							esc_html( $old_username ),
							esc_html( $new_username )
						);
						?>
					</p>
				</div>
				<?php
			}
		);
	}
}
