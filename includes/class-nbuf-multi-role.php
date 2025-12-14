<?php
/**
 * NoBloat User Foundry - Multi-Role Assignment
 *
 * Adds multi-role assignment capability to user profile pages.
 * Allows administrators to assign multiple roles to users.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-Role Assignment Class
 *
 * Handles displaying and saving multiple roles for users.
 */
class NBUF_Multi_Role {

	/**
	 * Initialize multi-role functionality
	 */
	public static function init() {
		/* Only for users who can promote users (typically administrators) */
		if ( ! is_admin() ) {
			return;
		}

		/* Add multi-role section to user profile */
		add_action( 'show_user_profile', array( __CLASS__, 'render_roles_section' ), 5 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_roles_section' ), 5 );

		/* Save roles on profile update - use high priority to run AFTER WordPress default handling */
		add_action( 'personal_options_update', array( __CLASS__, 'save_roles' ), 99 );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_roles' ), 99 );

		/* Enqueue styles */
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		/* Remove/disable default role dropdown when our multi-role section is shown */
		add_action( 'admin_head', array( __CLASS__, 'hide_default_role_selector' ) );
		add_action( 'admin_footer', array( __CLASS__, 'disable_default_role_field' ) );
	}

	/**
	 * Enqueue assets for user profile pages
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		/* Only if user can assign roles */
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.nbuf-roles-section {
				margin: 0;
				padding: 0;
			}
			.nbuf-roles-section h2 {
				margin: 0;
				padding: 23px 10px 10px 0;
				font-size: 1.3em;
				font-weight: 600;
			}
			.nbuf-roles-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
				gap: 10px;
				margin-top: 15px;
			}
			.nbuf-role-item {
				display: flex;
				align-items: center;
				padding: 10px 12px;
				background: #f6f7f7;
				border: 2px solid #dcdcde;
				border-radius: 4px;
				cursor: pointer;
				transition: all 0.15s ease;
			}
			.nbuf-role-item:hover {
				border-color: #2271b1;
				background: #f0f6fc;
			}
			.nbuf-role-item.selected {
				border-color: #2271b1;
				background: #f0f6fc;
			}
			.nbuf-role-item input[type="checkbox"] {
				margin: 0;
				margin-right: 10px;
				width: 18px;
				height: 18px;
			}
			.nbuf-role-item .role-info {
				flex: 1;
			}
			.nbuf-role-item .role-name {
				font-weight: 600;
				color: #1d2327;
			}
			.nbuf-role-item .role-key {
				font-size: 12px;
				color: #646970;
				font-family: monospace;
			}
			.nbuf-role-item.is-admin {
				background: #fef8ee;
				border-color: #dba617;
			}
			.nbuf-role-item.is-admin.selected {
				background: #fef8ee;
				border-color: #996800;
			}
			.nbuf-roles-warning {
				margin-top: 15px;
				padding: 10px 15px;
				background: #fcf0f1;
				border-left: 4px solid #d63638;
				color: #8a2424;
			}
			.nbuf-roles-info {
				margin-top: 15px;
				padding: 10px 15px;
				background: #f0f6fc;
				border-left: 4px solid #2271b1;
				color: #1d2327;
			}
			'
		);

		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				/* Update visual state when checkboxes change */
				$('.nbuf-role-item input[type=\"checkbox\"]').on('change', function() {
					var \$item = $(this).closest('.nbuf-role-item');
					if ($(this).is(':checked')) {
						\$item.addClass('selected');
					} else {
						\$item.removeClass('selected');
					}

					/* Warn if no roles selected */
					var checkedCount = $('.nbuf-role-item input[type=\"checkbox\"]:checked').length;
					if (checkedCount === 0) {
						$('.nbuf-no-role-warning').show();
					} else {
						$('.nbuf-no-role-warning').hide();
					}
				});

				/* Make entire row clickable */
				$('.nbuf-role-item').on('click', function(e) {
					if (e.target.type !== 'checkbox') {
						var \$checkbox = $(this).find('input[type=\"checkbox\"]');
						\$checkbox.prop('checked', !\$checkbox.prop('checked')).trigger('change');
					}
				});
			});
			"
		);
	}

	/**
	 * Hide default WordPress role selector
	 */
	public static function hide_default_role_selector() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'profile' !== $screen->id && 'user-edit' !== $screen->id ) ) {
			return;
		}

		/* Only if user can assign roles */
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		/* CSS moved to assets/css/admin/admin.css */
	}

	/**
	 * Disable the default role field so it doesn't get submitted
	 *
	 * This prevents WordPress from overwriting our multi-role selection
	 */
	public static function disable_default_role_field() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'profile' !== $screen->id && 'user-edit' !== $screen->id ) ) {
			return;
		}

		/* Only if user can assign roles */
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		?>
		<script>
		jQuery(document).ready(function($) {
			/* Disable the default role dropdown so it doesn't submit */
			$('select#role').prop('disabled', true).attr('name', 'role_disabled');
		});
		</script>
		<?php
	}

	/**
	 * Render the multi-role selection section
	 *
	 * @param WP_User $user User object being edited.
	 */
	public static function render_roles_section( $user ) {
		/* Only show to users who can promote users */
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		/* Don't allow editing super admin roles in multisite */
		if ( is_multisite() && is_super_admin( $user->ID ) && ! is_super_admin() ) {
			return;
		}

		/* Get all available roles */
		$all_roles = wp_roles()->get_names();

		/* Get user's current roles */
		$user_roles = $user->roles;

		/* Security nonce */
		wp_nonce_field( 'nbuf_multi_role_update', 'nbuf_multi_role_nonce' );

		?>
		<h2><?php esc_html_e( 'Roles', 'nobloat-user-foundry' ); ?></h2>
		<div class="nbuf-roles-section">
			<p class="description">
				<?php esc_html_e( 'Select one or more roles for this user. Users with multiple roles will have the combined capabilities of all assigned roles.', 'nobloat-user-foundry' ); ?>
			</p>

			<div class="nbuf-roles-grid">
				<?php foreach ( $all_roles as $role_key => $role_name ) : ?>
					<?php
					$is_selected = in_array( $role_key, $user_roles, true );
					$is_admin    = ( 'administrator' === $role_key );
					$item_class  = 'nbuf-role-item';
					if ( $is_selected ) {
						$item_class .= ' selected';
					}
					if ( $is_admin ) {
						$item_class .= ' is-admin';
					}
					?>
					<label class="<?php echo esc_attr( $item_class ); ?>">
						<input type="checkbox"
							name="nbuf_user_roles[]"
							value="<?php echo esc_attr( $role_key ); ?>"
							<?php checked( $is_selected ); ?>>
						<span class="role-info">
							<span class="role-name"><?php echo esc_html( translate_user_role( $role_name ) ); ?></span>
							<span class="role-key"><?php echo esc_html( $role_key ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="nbuf-no-role-warning nbuf-roles-warning" style="<?php echo empty( $user_roles ) ? '' : 'display: none;'; ?>">
				<?php esc_html_e( 'Warning: User has no roles assigned. They will have very limited access.', 'nobloat-user-foundry' ); ?>
			</div>

			<?php if ( count( $user_roles ) > 1 ) : ?>
				<div class="nbuf-roles-info">
					<?php
					printf(
						/* translators: %d: number of roles */
						esc_html__( 'This user currently has %d roles assigned.', 'nobloat-user-foundry' ),
						count( $user_roles )
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save user roles on profile update
	 *
	 * @param int $user_id User ID being updated.
	 */
	public static function save_roles( $user_id ) {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_multi_role_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_multi_role_nonce'] ) ), 'nbuf_multi_role_update' ) ) {
			return;
		}

		/* Check permissions */
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		/* Can't edit own roles (prevents locking yourself out) */
		if ( get_current_user_id() === $user_id && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Don't allow editing super admin roles in multisite */
		if ( is_multisite() && is_super_admin( $user_id ) && ! is_super_admin() ) {
			return;
		}

		/* Get selected roles */
		$new_roles = isset( $_POST['nbuf_user_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_user_roles'] ) ) : array();

		/* Validate roles exist */
		$valid_roles = array_keys( wp_roles()->get_names() );
		$new_roles   = array_intersect( $new_roles, $valid_roles );

		/* Get user object */
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		/* Get current roles */
		$current_roles = $user->roles;

		/* Prevent removing administrator role from self (safety check) */
		if ( get_current_user_id() === $user_id ) {
			if ( in_array( 'administrator', $current_roles, true ) && ! in_array( 'administrator', $new_roles, true ) ) {
				$new_roles[] = 'administrator';
			}
		}

		/* Remove roles that are no longer selected */
		foreach ( $current_roles as $role ) {
			if ( ! in_array( $role, $new_roles, true ) ) {
				$user->remove_role( $role );
			}
		}

		/* Add new roles */
		foreach ( $new_roles as $role ) {
			if ( ! in_array( $role, $current_roles, true ) ) {
				$user->add_role( $role );
			}
		}

		/* If no roles selected, default to subscriber (safety) */
		$updated_user = get_userdata( $user_id );
		if ( empty( $updated_user->roles ) ) {
			$updated_user->add_role( 'subscriber' );
		}

		/* Log the change */
		if ( class_exists( 'NBUF_Audit_Log' ) ) {
			$added   = array_diff( $new_roles, $current_roles );
			$removed = array_diff( $current_roles, $new_roles );

			if ( ! empty( $added ) || ! empty( $removed ) ) {
				NBUF_Audit_Log::log(
					$user_id,
					'account',
					'roles_changed',
					sprintf(
						/* translators: %s: User display name */
						__( 'Roles updated for user "%s"', 'nobloat-user-foundry' ),
						$user->display_name
					),
					array(
						'added'      => $added,
						'removed'    => $removed,
						'changed_by' => get_current_user_id(),
					)
				);
			}
		}
	}
}
