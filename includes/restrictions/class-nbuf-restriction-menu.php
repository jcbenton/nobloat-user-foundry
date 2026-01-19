<?php
/**
 * NoBloat User Foundry - Menu Restrictions
 *
 * Handles menu item visibility restrictions based on login status and user roles.
 * Filters menu items and provides admin UI for menu editor integration.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restriction_Menu
 *
 * Handles menu item visibility restrictions.
 */
class NBUF_Restriction_Menu extends NBUF_Abstract_Restriction {

	/**
	 * Request-level cache for all menu restrictions.
	 *
	 * Loaded once per request to avoid duplicate queries when multiple
	 * menus are rendered on the same page.
	 *
	 * @var array|null
	 */
	private static $restrictions_cache = null;

	/**
	 * Initialize menu restrictions
	 */
	public static function init() {
		/* Hook into menu rendering */
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter_menu_items' ), 10, 2 );

		/* Admin: Add fields to menu editor */
		if ( is_admin() ) {
			add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'add_menu_fields' ), 10, 4 );
			add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_menu_fields' ), 10, 3 );
		}
	}

	/**
	 * Filter menu items based on restrictions
	 *
	 * @param  array $items Menu items.
	 * @param  array $args  Menu arguments.
	 * @return array Filtered items.
	 */
	public static function filter_menu_items( $items, $args ) {
		/* Get all menu item IDs */
		$menu_item_ids = wp_list_pluck( $items, 'ID' );

		if ( empty( $menu_item_ids ) ) {
			return $items;
		}

		/* Load all restrictions once per request. */
		$restrictions = self::get_all_restrictions();

		/* Filter items */
		$filtered_items = array();
		$hidden_parents = array();

		foreach ( $items as $item ) {
			/* Check if restricted */
			if ( isset( $restrictions[ $item->ID ] ) ) {
				$restriction = $restrictions[ $item->ID ];

				/* Parse allowed_roles JSON */
				$allowed_roles = array();
				if ( ! empty( $restriction->allowed_roles ) ) {
					$allowed_roles = json_decode( $restriction->allowed_roles, true );
					if ( ! is_array( $allowed_roles ) ) {
						$allowed_roles = array();
					}
				}

				/* Check access */
				if ( ! self::check_access( $restriction->visibility, $allowed_roles ) ) {
					/* Mark as hidden and skip */
					$hidden_parents[ $item->ID ] = true;
					continue;
				}
			}

			/* Check if parent is hidden */
			if ( $item->menu_item_parent && isset( $hidden_parents[ $item->menu_item_parent ] ) ) {
				/* Parent was hidden, hide this child too */
				$hidden_parents[ $item->ID ] = true;
				continue;
			}

			/* Item passed all checks, include it */
			$filtered_items[ $item->ID ] = $item;
		}

		return array_values( $filtered_items );
	}

	/**
	 * Get all menu restrictions, cached per request.
	 *
	 * Loads all restrictions once per page load and caches them.
	 * This avoids duplicate queries when multiple menus render.
	 *
	 * @since  1.5.0
	 * @return array Restrictions keyed by menu_item_id.
	 */
	private static function get_all_restrictions(): array {
		/* Return cached if available. */
		if ( null !== self::$restrictions_cache ) {
			return self::$restrictions_cache;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_menu_restrictions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom menu restrictions table, request-level cached.
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i', $table ),
			OBJECT_K
		);

		self::$restrictions_cache = is_array( $results ) ? $results : array();

		return self::$restrictions_cache;
	}

	/**
	 * Add restriction fields to menu editor
	 *
	 * @param int    $item_id Menu item ID.
	 * @param object $item    Menu item object.
	 * @param int    $depth   Item depth.
	 * @param array  $args    Menu arguments.
	 */
	public static function add_menu_fields( $item_id, $item, $depth, $args ) {
		/* Get existing restriction from cache. */
		$restrictions = self::get_all_restrictions();
		$restriction  = isset( $restrictions[ $item_id ] ) ? $restrictions[ $item_id ] : null;

		/* Current values */
		$visibility    = $restriction ? $restriction->visibility : 'everyone';
		$allowed_roles = array();
		if ( $restriction && ! empty( $restriction->allowed_roles ) ) {
			$allowed_roles = json_decode( $restriction->allowed_roles, true );
			if ( ! is_array( $allowed_roles ) ) {
				$allowed_roles = array();
			}
		}

		/* Nonce field */
		wp_nonce_field( 'nbuf_menu_restriction_' . $item_id, 'nbuf_menu_restriction_nonce_' . $item_id );
		?>
		<p class="description description-wide nbuf-menu-restriction-visibility">
			<label for="nbuf_menu_visibility_<?php echo esc_attr( $item_id ); ?>">
		<?php esc_html_e( 'Access Restriction', 'nobloat-user-foundry' ); ?>
			</label>
			<select name="nbuf_menu_visibility[<?php echo esc_attr( $item_id ); ?>]" id="nbuf_menu_visibility_<?php echo esc_attr( $item_id ); ?>" class="widefat nbuf-menu-visibility-select">
				<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'nobloat-user-foundry' ); ?></option>
				<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users', 'nobloat-user-foundry' ); ?></option>
				<option value="logged_out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged Out Users', 'nobloat-user-foundry' ); ?></option>
				<option value="role_based" <?php selected( $visibility, 'role_based' ); ?>><?php esc_html_e( 'Specific Roles', 'nobloat-user-foundry' ); ?></option>
			</select>
		</p>

		<p class="description description-wide nbuf-menu-restriction-roles" id="nbuf_menu_roles_<?php echo esc_attr( $item_id ); ?>" style="display: <?php echo 'role_based' === $visibility ? 'block' : 'none'; ?>;">
			<label><?php esc_html_e( 'Allowed Roles:', 'nobloat-user-foundry' ); ?></label><br>
		<?php
		$wp_roles = wp_roles()->get_names();
		foreach ( $wp_roles as $role_slug => $role_name ) {
			$checked = in_array( $role_slug, $allowed_roles, true );
			?>
				<label style="display: inline-block; margin-right: 10px;">
					<input type="checkbox" name="nbuf_menu_roles[<?php echo esc_attr( $item_id ); ?>][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $checked ); ?>>
			<?php echo esc_html( $role_name ); ?>
				</label>
			<?php
		}
		?>
		</p>

		<script>
		jQuery(document).ready(function($) {
			$('#nbuf_menu_visibility_<?php echo esc_js( $item_id ); ?>').on('change', function() {
				var rolesDiv = $('#nbuf_menu_roles_<?php echo esc_js( $item_id ); ?>');
				if ($(this).val() === 'role_based') {
					rolesDiv.show();
				} else {
					rolesDiv.hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Save menu restriction fields
	 *
	 * @param int   $menu_id      Menu ID.
	 * @param int   $menu_item_id Menu item ID.
	 * @param array $args         Menu item arguments.
	 */
	public static function save_menu_fields( $menu_id, $menu_item_id, $args ) {
		/* Verify nonce */
		$nonce_name = 'nbuf_menu_restriction_nonce_' . $menu_item_id;
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), 'nbuf_menu_restriction_' . $menu_item_id ) ) {
			return;
		}

		/* Check capability */
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		/* Get visibility value */
		$visibility = 'everyone';
		if ( isset( $_POST['nbuf_menu_visibility'][ $menu_item_id ] ) ) {
			$visibility = self::sanitize_visibility( sanitize_text_field( wp_unslash( $_POST['nbuf_menu_visibility'][ $menu_item_id ] ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nbuf_menu_restrictions';

		/* If visibility is "everyone", delete restriction and return */
		if ( 'everyone' === $visibility ) {
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$table,
				array( 'menu_item_id' => $menu_item_id ),
				array( '%d' )
			);
			self::$restrictions_cache = null; /* Clear cache on delete. */
			return;
		}

		/* Get allowed roles */
		$allowed_roles = array();
		if ( 'role_based' === $visibility && ! empty( $_POST['nbuf_menu_roles'][ $menu_item_id ] ) ) {
			$raw_roles     = array_map( 'sanitize_text_field', wp_unslash( $_POST['nbuf_menu_roles'][ $menu_item_id ] ) );
			$allowed_roles = self::sanitize_roles( $raw_roles );
		}

		/* Prepare data */
		$data = array(
			'menu_item_id'  => $menu_item_id,
			'visibility'    => $visibility,
			'allowed_roles' => wp_json_encode( $allowed_roles ),
			'updated_at'    => self::get_current_timestamp(),
		);

		/*
		 * Check if exists.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom menu restrictions table.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT menu_item_id FROM %i WHERE menu_item_id = %d',
				$table,
				$menu_item_id
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
				array( 'menu_item_id' => $menu_item_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			/* Insert new */
			$data['created_at'] = self::get_current_timestamp();
         // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				$data,
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		/* Clear cache after save. */
		self::$restrictions_cache = null;
	}
}
