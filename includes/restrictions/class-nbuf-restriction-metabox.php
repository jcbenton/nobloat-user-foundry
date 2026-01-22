<?php
/**
 * NoBloat User Foundry - Restriction Metabox
 *
 * Provides metabox UI for post/page editor to set access restrictions.
 * Lean implementation with only 4-5 fields (vs Ultimate Member's 10).
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restriction_Metabox
 *
 * Provides metabox UI for post/page editor to set access restrictions.
 */
class NBUF_Restriction_Metabox {


	/**
	 * Initialize metabox
	 *
	 * @return void
	 */
	public static function init(): void {
		/* Register metabox for enabled post types */
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );

		/* Save metabox data */
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Register metabox for enabled post types
	 *
	 * @return void
	 */
	public static function add_metabox(): void {
		/* Get enabled post types from settings */
		$post_types = NBUF_Options::get( 'nbuf_restrictions_post_types', array( 'post', 'page' ) );

		/* Ensure it's an array */
		if ( ! is_array( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'nbuf_content_restriction',
				__( 'Access Restriction', 'nobloat-user-foundry' ),
				array( __CLASS__, 'render_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render metabox (LEAN - only 4-5 fields)
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_metabox( $post ): void {
		/* Check if content restrictions are enabled */
		$content_enabled = NBUF_Options::get( 'nbuf_restrictions_content_enabled', false );
		if ( ! $content_enabled ) {
			?>
			<div class="notice notice-warning inline" style="margin: 0 0 10px 0; padding: 8px 12px;">
				<p style="margin: 0;">
					<?php
					printf(
						/* translators: %s: Link to settings page */
						esc_html__( 'Content restrictions are currently disabled. %s to enable enforcement.', 'nobloat-user-foundry' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=restrictions' ) ) . '">' . esc_html__( 'Go to Settings', 'nobloat-user-foundry' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}

		/* Get existing restriction */
		$restriction = NBUF_Restrictions::get_content_restriction( $post->ID, $post->post_type );

		/* Current values */
		$visibility         = $restriction ? $restriction['visibility'] : 'everyone';
		$allowed_roles      = $restriction && ! empty( $restriction['allowed_roles'] ) ? $restriction['allowed_roles'] : array();
		$restriction_action = $restriction && ! empty( $restriction['restriction_action'] ) ? $restriction['restriction_action'] : 'message';
		$custom_message     = $restriction && ! empty( $restriction['custom_message'] ) ? $restriction['custom_message'] : '';
		$redirect_url       = $restriction && ! empty( $restriction['redirect_url'] ) ? $restriction['redirect_url'] : '';

		/* Nonce field */
		wp_nonce_field( 'nbuf_restriction_metabox', 'nbuf_restriction_nonce' );
		?>
		<div class="nbuf-restriction-metabox">
			<!-- Field 1: Visibility -->
			<p>
				<label for="nbuf_visibility"><?php esc_html_e( 'Who can access:', 'nobloat-user-foundry' ); ?></label>
				<select name="nbuf_visibility" id="nbuf_visibility" class="widefat">
					<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged Out Users', 'nobloat-user-foundry' ); ?></option>
					<option value="role_based" <?php selected( $visibility, 'role_based' ); ?>><?php esc_html_e( 'Specific Roles', 'nobloat-user-foundry' ); ?></option>
				</select>
			</p>

			<!-- Field 2: Roles (conditional - only shown if role_based selected) -->
			<p id="nbuf_roles_wrap" style="display: <?php echo 'role_based' === $visibility ? 'block' : 'none'; ?>;">
				<label><?php esc_html_e( 'Allowed Roles:', 'nobloat-user-foundry' ); ?></label><br>
		<?php
		$wp_roles = wp_roles()->get_names();
		foreach ( $wp_roles as $role_slug => $role_name ) {
			$checked = in_array( $role_slug, $allowed_roles, true );
			?>
					<label style="display: block; margin: 5px 0;">
						<input type="checkbox" name="nbuf_allowed_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $checked ); ?>>
			<?php echo esc_html( $role_name ); ?>
					</label>
			<?php
		}
		?>
			</p>

			<!-- Field 3: Restriction Action -->
			<p>
				<label for="nbuf_restriction_action"><?php esc_html_e( 'If access denied:', 'nobloat-user-foundry' ); ?></label>
				<select name="nbuf_restriction_action" id="nbuf_restriction_action" class="widefat">
					<option value="message" <?php selected( $restriction_action, 'message' ); ?>><?php esc_html_e( 'Show Message', 'nobloat-user-foundry' ); ?></option>
					<option value="redirect" <?php selected( $restriction_action, 'redirect' ); ?>><?php esc_html_e( 'Redirect to URL', 'nobloat-user-foundry' ); ?></option>
					<option value="404" <?php selected( $restriction_action, '404' ); ?>><?php esc_html_e( 'Show 404 Page', 'nobloat-user-foundry' ); ?></option>
				</select>
			</p>

			<!-- Field 4: Custom Message (conditional) -->
			<p id="nbuf_message_wrap" style="display: <?php echo 'message' === $restriction_action ? 'block' : 'none'; ?>;">
				<label for="nbuf_custom_message"><?php esc_html_e( 'Custom Message:', 'nobloat-user-foundry' ); ?></label>
				<textarea name="nbuf_custom_message" id="nbuf_custom_message" rows="4" class="widefat"><?php echo esc_textarea( $custom_message ); ?></textarea>
				<span class="description"><?php esc_html_e( 'HTML allowed. Leave blank for default message.', 'nobloat-user-foundry' ); ?></span>
			</p>

			<!-- Field 5: Redirect URL (conditional) -->
			<p id="nbuf_redirect_wrap" style="display: <?php echo 'redirect' === $restriction_action ? 'block' : 'none'; ?>;">
				<label for="nbuf_redirect_url"><?php esc_html_e( 'Redirect URL:', 'nobloat-user-foundry' ); ?></label>
				<input type="url" name="nbuf_redirect_url" id="nbuf_redirect_url" class="widefat" placeholder="https://" value="<?php echo esc_url( $redirect_url ); ?>">
				<span class="description"><?php esc_html_e( 'Leave blank to redirect to login page.', 'nobloat-user-foundry' ); ?></span>
			</p>

			<script>
			/* Toggle conditional fields based on selections */
			(function($) {
				$(document).ready(function() {
					$('#nbuf_visibility').on('change', function() {
						$('#nbuf_roles_wrap').toggle($(this).val() === 'role_based');
					});

					$('#nbuf_restriction_action').on('change', function() {
						var action = $(this).val();
						$('#nbuf_message_wrap').toggle(action === 'message');
						$('#nbuf_redirect_wrap').toggle(action === 'redirect');
					});
				});
			})(jQuery);
			</script>

			
		</div>
		<?php
	}

	/**
	 * Save metabox data
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_metabox( $post_id, $post ): void {
		/* Security checks */
		if ( ! isset( $_POST['nbuf_restriction_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_restriction_nonce'] ) ), 'nbuf_restriction_metabox' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* Get values */
		$visibility = isset( $_POST['nbuf_visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_visibility'] ) ) : 'everyone';

		/* Validate visibility */
		$allowed_visibility = array( 'everyone', 'logged_in', 'logged_out', 'role_based' );
		if ( ! in_array( $visibility, $allowed_visibility, true ) ) {
			$visibility = 'everyone';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_content_restrictions';

		/* If visibility is "everyone", delete restriction and return */
		if ( 'everyone' === $visibility ) {
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$table,
				array(
					'content_id'   => $post_id,
					'content_type' => $post->post_type,
				),
				array( '%d', '%s' )
			);

			/* Log removal to admin audit (restriction changes are admin actions) */
			if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
				NBUF_Admin_Audit_Log::log(
					get_current_user_id(),
					'restriction_removed',
					'success',
					sprintf(
					/* translators: 1: Post type, 2: Post title */
						__( 'Removed access restriction from %1$s "%2$s"', 'nobloat-user-foundry' ),
						$post->post_type,
						$post->post_title
					),
					null,
					array(
						'content_id'   => $post_id,
						'content_type' => $post->post_type,
					)
				);
			}

			return;
		}

		/* Build restriction data */
		$allowed_roles = array();
		if ( 'role_based' === $visibility && ! empty( $_POST['nbuf_allowed_roles'] ) ) {
			$raw_roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_allowed_roles'] ) );
			/* Validate against actual WordPress roles */
			$wp_roles = array_keys( wp_roles()->get_names() );
			foreach ( $raw_roles as $role ) {
				if ( in_array( $role, $wp_roles, true ) ) {
					$allowed_roles[] = $role;
				}
			}
		}

		$restriction_action = isset( $_POST['nbuf_restriction_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_restriction_action'] ) ) : 'message';

		/* Validate restriction action */
		$allowed_actions = array( 'message', 'redirect', '404' );
		if ( ! in_array( $restriction_action, $allowed_actions, true ) ) {
			$restriction_action = 'message';
		}

		$custom_message = '';
		if ( 'message' === $restriction_action && ! empty( $_POST['nbuf_custom_message'] ) ) {
			$custom_message = wp_kses_post( wp_unslash( $_POST['nbuf_custom_message'] ) );
		}

		$redirect_url = '';
		if ( 'redirect' === $restriction_action && ! empty( $_POST['nbuf_redirect_url'] ) ) {
			$redirect_url = esc_url_raw( wp_unslash( $_POST['nbuf_redirect_url'] ) );
		}

		/* Prepare data for database */
		$data = array(
			'content_id'         => $post_id,
			'content_type'       => $post->post_type,
			'visibility'         => $visibility,
			'allowed_roles'      => wp_json_encode( $allowed_roles ),
			'restriction_action' => $restriction_action,
			'custom_message'     => $custom_message,
			'redirect_url'       => $redirect_url,
			'updated_at'         => current_time( 'mysql', true ),
		);

		/*
		 * Check if restriction exists.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom restrictions table.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT content_id FROM %i WHERE content_id = %d AND content_type = %s',
				$table,
				$post_id,
				$post->post_type
			)
		);

		if ( $exists ) {
			/*
			* Update existing
			*/
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				$data,
				array(
					'content_id'   => $post_id,
					'content_type' => $post->post_type,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);

			$action = 'restriction_updated';
			/* translators: 1: Post type, 2: Post title */
			$action_text = __( 'Updated access restriction for %1$s "%2$s"', 'nobloat-user-foundry' );
		} else {
			/* Insert new */
			$data['created_at'] = current_time( 'mysql', true );
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			$action = 'restriction_added';
			/* translators: 1: Post type, 2: Post title */
			$action_text = __( 'Added access restriction to %1$s "%2$s"', 'nobloat-user-foundry' );
		}

		/* Log to admin audit (restriction changes are admin actions) */
		if ( class_exists( 'NBUF_Admin_Audit_Log' ) ) {
			NBUF_Admin_Audit_Log::log(
				get_current_user_id(),
				$action,
				'success',
				sprintf(
				/* translators: 1: Post type, 2: Post title */
					$action_text,
					$post->post_type,
					$post->post_title
				),
				null,
				array(
					'content_id'   => $post_id,
					'content_type' => $post->post_type,
					'visibility'   => $visibility,
				)
			);
		}

		/* Clear cache */
		$cache_keys = array(
			'nbuf_excluded_posts_' . $post->post_type . '_guest',
			'nbuf_excluded_posts_' . $post->post_type . '_' . get_current_user_id(),
		);
		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key, 'nbuf_restrictions' );
		}
	}
}
