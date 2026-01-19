<?php
/**
 * NoBloat User Foundry - Widget Restrictions
 *
 * Handles widget visibility restrictions based on login status and user roles.
 * Filters widgets and provides admin UI for widget editor integration.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/restrictions
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NBUF_Restriction_Widget
 *
 * Handles widget visibility restrictions.
 */
class NBUF_Restriction_Widget extends NBUF_Abstract_Restriction {


	/**
	 * Initialize widget restrictions
	 */
	public static function init() {
		/* Hook into widget display */
		add_filter( 'widget_display_callback', array( __CLASS__, 'filter_widget_display' ), 10, 3 );

		/* Admin: Add fields to widget form */
		if ( is_admin() ) {
			add_filter( 'in_widget_form', array( __CLASS__, 'add_widget_fields' ), 10, 3 );
			add_filter( 'widget_update_callback', array( __CLASS__, 'save_widget_fields' ), 10, 4 );
		}
	}

	/**
	 * Filter widget display based on restrictions
	 *
	 * @param  array     $instance Widget instance.
	 * @param  WP_Widget $widget   Widget object.
	 * @param  array     $args     Widget arguments.
	 * @return array|false Widget instance or false to hide.
	 */
	public static function filter_widget_display( $instance, $widget, $args ) {
		/* Skip if no visibility setting */
		if ( empty( $instance['nbuf_visibility'] ) || 'everyone' === $instance['nbuf_visibility'] ) {
			return $instance;
		}

		/* Get allowed roles */
		$allowed_roles = array();
		if ( ! empty( $instance['nbuf_allowed_roles'] ) ) {
			if ( is_array( $instance['nbuf_allowed_roles'] ) ) {
				$allowed_roles = $instance['nbuf_allowed_roles'];
			} elseif ( is_string( $instance['nbuf_allowed_roles'] ) ) {
				$allowed_roles = json_decode( $instance['nbuf_allowed_roles'], true );
				if ( ! is_array( $allowed_roles ) ) {
					$allowed_roles = array();
				}
			}
		}

		/* Check access */
		$has_access = self::check_access( $instance['nbuf_visibility'], $allowed_roles );

		/* If no access, return false to hide widget */
		if ( ! $has_access ) {
			return false;
		}

		return $instance;
	}

	/**
	 * Add restriction fields to widget form
	 *
	 * @param  WP_Widget $widget   Widget object.
	 * @param  null      $return   Return value (not used).
	 * @param  array     $instance Widget instance.
	 * @return null
	 */
	public static function add_widget_fields( $widget, $return, $instance ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Universal.NamingConventions.NoReservedKeywordParameterNames.returnFound -- $return parameter required by WordPress in_widget_form filter hook signature.
		/* Current values */
		$visibility    = ! empty( $instance['nbuf_visibility'] ) ? $instance['nbuf_visibility'] : 'everyone';
		$allowed_roles = array();
		if ( ! empty( $instance['nbuf_allowed_roles'] ) ) {
			if ( is_array( $instance['nbuf_allowed_roles'] ) ) {
				$allowed_roles = $instance['nbuf_allowed_roles'];
			} elseif ( is_string( $instance['nbuf_allowed_roles'] ) ) {
				$allowed_roles = json_decode( $instance['nbuf_allowed_roles'], true );
				if ( ! is_array( $allowed_roles ) ) {
					$allowed_roles = array();
				}
			}
		}

		$widget_id = $widget->id;
		?>
		<div class="nbuf-widget-restriction" style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">
			<p>
				<strong><?php esc_html_e( 'Access Restriction', 'nobloat-user-foundry' ); ?></strong>
			</p>
			<p>
				<label for="<?php echo esc_attr( $widget->get_field_id( 'nbuf_visibility' ) ); ?>">
		<?php esc_html_e( 'Who can see this widget:', 'nobloat-user-foundry' ); ?>
				</label>
				<select
					id="<?php echo esc_attr( $widget->get_field_id( 'nbuf_visibility' ) ); ?>"
					name="<?php echo esc_attr( $widget->get_field_name( 'nbuf_visibility' ) ); ?>"
					class="widefat nbuf-widget-visibility-select"
					data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
				>
					<option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users', 'nobloat-user-foundry' ); ?></option>
					<option value="logged_out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged Out Users', 'nobloat-user-foundry' ); ?></option>
					<option value="role_based" <?php selected( $visibility, 'role_based' ); ?>><?php esc_html_e( 'Specific Roles', 'nobloat-user-foundry' ); ?></option>
				</select>
			</p>

			<div
				class="nbuf-widget-roles-wrap"
				id="nbuf_widget_roles_<?php echo esc_attr( $widget_id ); ?>"
				style="display: <?php echo 'role_based' === $visibility ? 'block' : 'none'; ?>;"
			>
				<p>
					<label><?php esc_html_e( 'Allowed Roles:', 'nobloat-user-foundry' ); ?></label><br>
		<?php
		$wp_roles = wp_roles()->get_names();
		foreach ( $wp_roles as $role_slug => $role_name ) {
			$checked = in_array( $role_slug, $allowed_roles, true );
			?>
						<label style="display: block; margin: 5px 0;">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $widget->get_field_name( 'nbuf_allowed_roles' ) ); ?>[]"
								value="<?php echo esc_attr( $role_slug ); ?>"
			<?php checked( $checked ); ?>
							>
			<?php echo esc_html( $role_name ); ?>
						</label>
			<?php
		}
		?>
				</p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			/* Toggle roles visibility based on dropdown */
			$('.nbuf-widget-visibility-select').on('change', function() {
				var widgetId = $(this).data('widget-id');
				var rolesDiv = $('#nbuf_widget_roles_' + widgetId);
				if ($(this).val() === 'role_based') {
					rolesDiv.show();
				} else {
					rolesDiv.hide();
				}
			});
		});
		</script>

		
		<?php

		return null;
	}

	/**
	 * Save widget restriction fields
	 *
	 * @param  array     $instance     Current widget instance.
	 * @param  array     $new_instance New widget instance.
	 * @param  array     $old_instance Old widget instance.
	 * @param  WP_Widget $widget       Widget object.
	 * @return array Updated widget instance.
	 */
	public static function save_widget_fields( $instance, $new_instance, $old_instance, $widget ) {
		/* Save visibility */
		if ( isset( $new_instance['nbuf_visibility'] ) ) {
			$instance['nbuf_visibility'] = self::sanitize_visibility( $new_instance['nbuf_visibility'] );
		} else {
			$instance['nbuf_visibility'] = 'everyone';
		}

		/* Save allowed roles */
		if ( 'role_based' === $instance['nbuf_visibility'] && ! empty( $new_instance['nbuf_allowed_roles'] ) ) {
			$raw_roles                      = array_map( 'sanitize_text_field', $new_instance['nbuf_allowed_roles'] );
			$instance['nbuf_allowed_roles'] = self::sanitize_roles( $raw_roles );
		} else {
			$instance['nbuf_allowed_roles'] = array();
		}

		return $instance;
	}
}
