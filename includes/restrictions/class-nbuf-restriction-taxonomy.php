<?php
/**
 * NoBloat User Foundry - Taxonomy Restrictions
 *
 * Handles taxonomy term visibility restrictions based on login status and user roles.
 * Filters taxonomy archives and provides admin UI for term editor integration.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restriction_Taxonomy
 *
 * Handles taxonomy term visibility restrictions.
 */
class NBUF_Restriction_Taxonomy extends NBUF_Abstract_Restriction {


	/**
	 * Initialize taxonomy restrictions
	 */
	public static function init() {
		/* Hook into taxonomy archive access */
		add_action( 'template_redirect', array( __CLASS__, 'check_taxonomy_access' ), 1 );

		/* Optionally filter taxonomy queries */
		$filter_queries = NBUF_Options::get( 'nbuf_restrict_taxonomies_filter_queries', false );
		if ( $filter_queries ) {
			add_filter( 'get_terms_args', array( __CLASS__, 'filter_terms_query' ), 10, 2 );
		}

		/* Admin: Add fields to term editor */
		if ( is_admin() ) {
			/* Get enabled taxonomies from settings */
			$taxonomies = NBUF_Options::get( 'nbuf_restrict_taxonomies_list', array( 'category', 'post_tag' ) );

			/* Ensure it's an array */
			if ( ! is_array( $taxonomies ) ) {
				$taxonomies = array( 'category', 'post_tag' );
			}

			/* Register form fields for each taxonomy */
			foreach ( $taxonomies as $taxonomy ) {
				add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'add_term_fields_new' ), 10, 1 );
				add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'add_term_fields_edit' ), 10, 2 );
				add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term_fields' ), 10, 2 );
				add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term_fields' ), 10, 2 );
			}
		}
	}

	/**
	 * Check taxonomy archive access and handle restrictions
	 */
	public static function check_taxonomy_access() {
		/* Only on taxonomy archives */
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return;
		}

		/* Get current term */
		$queried_object = get_queried_object();
		if ( ! $queried_object || ! isset( $queried_object->term_id ) ) {
			return;
		}

		$term_id  = $queried_object->term_id;
		$taxonomy = $queried_object->taxonomy;

		/* Get restriction settings */
		$visibility = get_term_meta( $term_id, '_nbuf_visibility', true );

		/* No restriction = allow access */
		if ( empty( $visibility ) || 'everyone' === $visibility ) {
			return;
		}

		/* Get allowed roles */
		$allowed_roles = get_term_meta( $term_id, '_nbuf_allowed_roles', true );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array();
		}

		/* Check access */
		$has_access = self::check_access( $visibility, $allowed_roles );

		/* Access granted */
		if ( $has_access ) {
			return;
		}

		/* Access denied - get restriction action */
		$restriction_action = get_term_meta( $term_id, '_nbuf_restriction_action', true );

		/* Handle restriction action */
		switch ( $restriction_action ) {
			case 'redirect':
				/* Redirect to custom URL or login */
				$redirect_url = get_term_meta( $term_id, '_nbuf_redirect_url', true );
				if ( empty( $redirect_url ) ) {
					$redirect_url = wp_login_url( get_term_link( $term_id, $taxonomy ) );
				}

				/* Log access denial */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						get_current_user_id(),
						'restrictions',
						'taxonomy_access_denied_redirect',
						sprintf(
						/* translators: 1: Term name, 2: Taxonomy */
							__( 'Access denied to %1$s (%2$s) - redirected', 'nobloat-user-foundry' ),
							$queried_object->name,
							$taxonomy
						),
						array(
							'term_id'    => $term_id,
							'taxonomy'   => $taxonomy,
							'visibility' => $visibility,
						)
					);
				}

				wp_safe_redirect( esc_url( $redirect_url ) );
				exit;

			case '404':
			default:
				/* Log access denial */
				if ( class_exists( 'NBUF_Audit_Log' ) ) {
					NBUF_Audit_Log::log(
						get_current_user_id(),
						'restrictions',
						'taxonomy_access_denied_404',
						sprintf(
						/* translators: 1: Term name, 2: Taxonomy */
							__( 'Access denied to %1$s (%2$s) - 404 shown', 'nobloat-user-foundry' ),
							$queried_object->name,
							$taxonomy
						),
						array(
							'term_id'    => $term_id,
							'taxonomy'   => $taxonomy,
							'visibility' => $visibility,
						)
					);
				}

				/* Set 404 */
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();

				/* Load 404 template */
				include get_query_template( '404' );
				exit;
		}
	}

	/**
	 * Filter terms query to exclude restricted terms (optional)
	 *
	 * @param  array $args       Term query arguments.
	 * @param  array $taxonomies Taxonomies being queried.
	 * @return array Modified arguments.
	 */
	public static function filter_terms_query( $args, $taxonomies ) {
		/* Skip in admin */
		if ( is_admin() ) {
			return $args;
		}

		/* Get excluded term IDs */
		$excluded_ids = self::get_excluded_term_ids( $taxonomies );

		if ( ! empty( $excluded_ids ) ) {
			/* Merge with existing exclude */
			$existing = isset( $args['exclude'] ) ? (array) $args['exclude'] : array();
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Exclude is required for role-based taxonomy restrictions; list is typically small and cached.
			$args['exclude'] = array_merge( $existing, $excluded_ids );
		}

		return $args;
	}

	/**
	 * Get list of term IDs to exclude from queries
	 *
	 * @param  array $taxonomies Taxonomies being queried.
	 * @return array Array of term IDs to exclude.
	 */
	private static function get_excluded_term_ids( $taxonomies ) {
		/*
		 * Try to get from cache
		 *
		 */
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Storing role arrays in termmeta, used only for cache key generation.
		$cache_key = 'nbuf_excluded_terms_' . md5( serialize( $taxonomies ) ) . '_' . ( is_user_logged_in() ? get_current_user_id() : 'guest' );
		$cached    = wp_cache_get( $cache_key, 'nbuf_restrictions' );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		/* Build query for term meta */
		$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

		/*
		* Get all terms with restrictions in these taxonomies
		*/
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN clause
		$restricted_terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, tm1.meta_value as visibility, tm2.meta_value as allowed_roles
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->termmeta} tm1 ON t.term_id = tm1.term_id AND tm1.meta_key = '_nbuf_visibility'
				LEFT JOIN {$wpdb->termmeta} tm2 ON t.term_id = tm2.term_id AND tm2.meta_key = '_nbuf_allowed_roles'
			WHERE tt.taxonomy IN ($placeholders) AND tm1.meta_value != 'everyone'",
				...$taxonomies
			)
		);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$excluded = array();

		foreach ( $restricted_terms as $term ) {
			/* Parse allowed_roles */
			$allowed_roles = array();
			if ( ! empty( $term->allowed_roles ) ) {
				$allowed_roles = maybe_unserialize( $term->allowed_roles );
				if ( ! is_array( $allowed_roles ) ) {
					$allowed_roles = array();
				}
			}

			/* Check if current user has access */
			$has_access = self::check_access( $term->visibility, $allowed_roles );

			/* If no access, add to excluded list */
			if ( ! $has_access ) {
				$excluded[] = (int) $term->term_id;
			}
		}

		/* Cache for 5 minutes */
		wp_cache_set( $cache_key, $excluded, 'nbuf_restrictions', 300 );

		return $excluded;
	}

	/**
	 * Add restriction fields to new term form
	 *
	 * @param string $taxonomy Current taxonomy slug.
	 */
	public static function add_term_fields_new( $taxonomy ) {
		wp_nonce_field( 'nbuf_term_restriction', 'nbuf_term_restriction_nonce' );
		?>
		<div class="form-field nbuf-term-restriction-wrap">
			<label for="nbuf_term_visibility"><?php esc_html_e( 'Access Restriction', 'nobloat-user-foundry' ); ?></label>
			<select name="nbuf_term_visibility" id="nbuf_term_visibility" class="postform">
				<option value="everyone"><?php esc_html_e( 'Everyone', 'nobloat-user-foundry' ); ?></option>
				<option value="logged_in"><?php esc_html_e( 'Logged In Users', 'nobloat-user-foundry' ); ?></option>
				<option value="logged_out"><?php esc_html_e( 'Logged Out Users', 'nobloat-user-foundry' ); ?></option>
				<option value="role_based"><?php esc_html_e( 'Specific Roles', 'nobloat-user-foundry' ); ?></option>
			</select>
			<p><?php esc_html_e( 'Control who can access this term\'s archive page.', 'nobloat-user-foundry' ); ?></p>
		</div>

		<div class="form-field nbuf-term-roles-wrap" id="nbuf_term_roles_wrap" style="display:none;">
			<label><?php esc_html_e( 'Allowed Roles', 'nobloat-user-foundry' ); ?></label>
		<?php
		$wp_roles = wp_roles()->get_names();
		foreach ( $wp_roles as $role_slug => $role_name ) {
			?>
				<label style="display: block; margin: 5px 0;">
					<input type="checkbox" name="nbuf_term_allowed_roles[]" value="<?php echo esc_attr( $role_slug ); ?>">
			<?php echo esc_html( $role_name ); ?>
				</label>
			<?php
		}
		?>
			<p><?php esc_html_e( 'Select which roles can access this term.', 'nobloat-user-foundry' ); ?></p>
		</div>

		<div class="form-field nbuf-term-action-wrap">
			<label for="nbuf_term_restriction_action"><?php esc_html_e( 'If Access Denied', 'nobloat-user-foundry' ); ?></label>
			<select name="nbuf_term_restriction_action" id="nbuf_term_restriction_action" class="postform">
				<option value="404"><?php esc_html_e( 'Show 404 Page', 'nobloat-user-foundry' ); ?></option>
				<option value="redirect"><?php esc_html_e( 'Redirect to URL', 'nobloat-user-foundry' ); ?></option>
			</select>
			<p><?php esc_html_e( 'What happens when a user without access tries to view this term.', 'nobloat-user-foundry' ); ?></p>
		</div>

		<div class="form-field nbuf-term-redirect-wrap" id="nbuf_term_redirect_wrap" style="display:none;">
			<label for="nbuf_term_redirect_url"><?php esc_html_e( 'Redirect URL', 'nobloat-user-foundry' ); ?></label>
			<input type="url" name="nbuf_term_redirect_url" id="nbuf_term_redirect_url" class="postform" placeholder="https://">
			<p><?php esc_html_e( 'Leave blank to redirect to login page.', 'nobloat-user-foundry' ); ?></p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#nbuf_term_visibility').on('change', function() {
				$('#nbuf_term_roles_wrap').toggle($(this).val() === 'role_based');
			});

			$('#nbuf_term_restriction_action').on('change', function() {
				$('#nbuf_term_redirect_wrap').toggle($(this).val() === 'redirect');
			});
		});
		</script>
		<?php
	}

	/**
	 * Add restriction fields to edit term form
	 *
	 * @param WP_Term $term     Current term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 */
	public static function add_term_fields_edit( $term, $taxonomy ) {
		/* Get current values */
		$visibility = get_term_meta( $term->term_id, '_nbuf_visibility', true );
		$visibility = ! empty( $visibility ) ? $visibility : 'everyone';

		$allowed_roles = get_term_meta( $term->term_id, '_nbuf_allowed_roles', true );
		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array();
		}

		$restriction_action = get_term_meta( $term->term_id, '_nbuf_restriction_action', true );
		$restriction_action = ! empty( $restriction_action ) ? $restriction_action : '404';

		$redirect_url = get_term_meta( $term->term_id, '_nbuf_redirect_url', true );

		wp_nonce_field( 'nbuf_term_restriction', 'nbuf_term_restriction_nonce' );
		?>
		<tr class="form-field nbuf-term-restriction-wrap">
			<th scope="row">
				<label for="nbuf_term_visibility"><?php esc_html_e( 'Access Restriction', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<select name="nbuf_term_visibility" id="nbuf_term_visibility" class="postform">
					<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged Out Users', 'nobloat-user-foundry' ); ?></option>
					<option value="role_based" <?php selected( $visibility, 'role_based' ); ?>><?php esc_html_e( 'Specific Roles', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Control who can access this term\'s archive page.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>

		<tr class="form-field nbuf-term-roles-wrap" id="nbuf_term_roles_wrap" style="display: <?php echo 'role_based' === $visibility ? 'table-row' : 'none'; ?>;">
			<th scope="row">
				<label><?php esc_html_e( 'Allowed Roles', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
		<?php
		$wp_roles = wp_roles()->get_names();
		foreach ( $wp_roles as $role_slug => $role_name ) {
			$checked = in_array( $role_slug, $allowed_roles, true );
			?>
					<label style="display: block; margin: 5px 0;">
						<input type="checkbox" name="nbuf_term_allowed_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $checked ); ?>>
			<?php echo esc_html( $role_name ); ?>
					</label>
			<?php
		}
		?>
				<p class="description"><?php esc_html_e( 'Select which roles can access this term.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>

		<tr class="form-field nbuf-term-action-wrap">
			<th scope="row">
				<label for="nbuf_term_restriction_action"><?php esc_html_e( 'If Access Denied', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<select name="nbuf_term_restriction_action" id="nbuf_term_restriction_action" class="postform">
					<option value="404" <?php selected( $restriction_action, '404' ); ?>><?php esc_html_e( 'Show 404 Page', 'nobloat-user-foundry' ); ?></option>
					<option value="redirect" <?php selected( $restriction_action, 'redirect' ); ?>><?php esc_html_e( 'Redirect to URL', 'nobloat-user-foundry' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'What happens when a user without access tries to view this term.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>

		<tr class="form-field nbuf-term-redirect-wrap" id="nbuf_term_redirect_wrap" style="display: <?php echo 'redirect' === $restriction_action ? 'table-row' : 'none'; ?>;">
			<th scope="row">
				<label for="nbuf_term_redirect_url"><?php esc_html_e( 'Redirect URL', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="url" name="nbuf_term_redirect_url" id="nbuf_term_redirect_url" class="regular-text" placeholder="https://" value="<?php echo esc_url( $redirect_url ); ?>">
				<p class="description"><?php esc_html_e( 'Leave blank to redirect to login page.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>

		<script>
		jQuery(document).ready(function($) {
			$('#nbuf_term_visibility').on('change', function() {
				$('#nbuf_term_roles_wrap').toggle($(this).val() === 'role_based');
			});

			$('#nbuf_term_restriction_action').on('change', function() {
				$('#nbuf_term_redirect_wrap').toggle($(this).val() === 'redirect');
			});
		});
		</script>
		<?php
	}

	/**
	 * Save term restriction fields
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function save_term_fields( $term_id, $taxonomy ) {
		/* Verify nonce */
		if ( ! isset( $_POST['nbuf_term_restriction_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nbuf_term_restriction_nonce'] ) ), 'nbuf_term_restriction' ) ) {
			return;
		}

		/* Check capability */
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		/* Save visibility */
		$visibility = isset( $_POST['nbuf_term_visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_term_visibility'] ) ) : 'everyone';
		$visibility = self::sanitize_visibility( $visibility );

		/* If everyone, delete all meta and return */
		if ( 'everyone' === $visibility ) {
			delete_term_meta( $term_id, '_nbuf_visibility' );
			delete_term_meta( $term_id, '_nbuf_allowed_roles' );
			delete_term_meta( $term_id, '_nbuf_restriction_action' );
			delete_term_meta( $term_id, '_nbuf_redirect_url' );
			return;
		}

		update_term_meta( $term_id, '_nbuf_visibility', $visibility );

		/* Save allowed roles */
		$allowed_roles = array();
		if ( 'role_based' === $visibility && ! empty( $_POST['nbuf_term_allowed_roles'] ) ) {
			$raw_roles     = array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_term_allowed_roles'] ) );
			$allowed_roles = self::sanitize_roles( $raw_roles );
		}
		update_term_meta( $term_id, '_nbuf_allowed_roles', $allowed_roles );

		/* Save restriction action */
		$restriction_action = isset( $_POST['nbuf_term_restriction_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_term_restriction_action'] ) ) : '404';
		$allowed_actions    = array( '404', 'redirect' );
		if ( ! in_array( $restriction_action, $allowed_actions, true ) ) {
			$restriction_action = '404';
		}
		update_term_meta( $term_id, '_nbuf_restriction_action', $restriction_action );

		/* Save redirect URL */
		$redirect_url = '';
		if ( 'redirect' === $restriction_action && ! empty( $_POST['nbuf_term_redirect_url'] ) ) {
			$redirect_url = esc_url_raw( wp_unslash( $_POST['nbuf_term_redirect_url'] ) );
		}
		update_term_meta( $term_id, '_nbuf_redirect_url', $redirect_url );

		/* Clear cache */
		$cache_keys = array(
			'nbuf_excluded_terms_' . md5( $taxonomy ) . '_guest',
			'nbuf_excluded_terms_' . md5( $taxonomy ) . '_' . get_current_user_id(),
		);
		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key, 'nbuf_restrictions' );
		}
	}
}
