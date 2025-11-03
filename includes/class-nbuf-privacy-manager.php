<?php
/**
 * NoBloat User Foundry - Privacy Manager
 *
 * Handles user privacy settings and visibility controls.
 * Integrates with member directories and profile access.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Direct database access is architectural for privacy settings management.
 * Custom nbuf_user_data table stores privacy preferences and cannot use
 * WordPress's standard meta APIs.
 */

/**
 * Class NBUF_Privacy_Manager
 *
 * Manages user privacy settings and visibility controls.
 */
class NBUF_Privacy_Manager {


	/**
	 * Privacy levels
	 */
	const PRIVACY_PUBLIC       = 'public';
	const PRIVACY_MEMBERS_ONLY = 'members_only';
	const PRIVACY_PRIVATE      = 'private';

	/**
	 * Initialize privacy manager
	 */
	public static function init() {
		/* Hook into profile save */
		add_action( 'nbuf_after_profile_update', array( __CLASS__, 'save_privacy_settings' ), 10, 2 );

		/* Add privacy section to profile */
		add_action( 'nbuf_profile_privacy_section', array( __CLASS__, 'render_privacy_section' ) );
	}

	/**
	 * Get user's privacy level
	 *
	 * @param  int $user_id User ID.
	 * @return string Privacy level (public, members_only, private).
	 */
	public static function get_user_privacy_level( $user_id ) {
		global $wpdb;
		$table = NBUF_Database::get_table_name( 'user_data' );

		$level = wp_cache_get( "nbuf_privacy_level_{$user_id}", 'nbuf_privacy' );
		if ( false === $level ) {
			$level = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT profile_privacy FROM %i WHERE user_id = %d',
					$table,
					$user_id
				)
			);

			wp_cache_set( "nbuf_privacy_level_{$user_id}", $level, 'nbuf_privacy', 3600 );
		}

		/* If no level set, use default from settings */
		if ( ! $level ) {
			$level = NBUF_Options::get( 'nbuf_directory_default_privacy', self::PRIVACY_PRIVATE );
		}

		return $level;
	}

	/**
	 * Check if user can view profile
	 *
	 * @param  int $target_user_id User to view.
	 * @param  int $viewer_user_id Viewer (0 = guest).
	 * @return bool Can view.
	 */
	public static function can_view_profile( $target_user_id, $viewer_user_id = 0 ) {
		/* Always allow viewing own profile */
		if ( $viewer_user_id === $target_user_id ) {
			return true;
		}

		/* Admins can view all profiles */
		if ( $viewer_user_id && user_can( $viewer_user_id, 'manage_options' ) ) {
			return true;
		}

		$privacy_level = self::get_user_privacy_level( $target_user_id );

		switch ( $privacy_level ) {
			case self::PRIVACY_PUBLIC:
				return true;

			case self::PRIVACY_MEMBERS_ONLY:
				return is_user_logged_in();

			case self::PRIVACY_PRIVATE:
				return false;

			default:
				return false;
		}
	}

	/**
	 * Check if user should appear in directory
	 *
	 * @param  int $user_id   User ID.
	 * @param  int $viewer_id Viewer ID (0 = guest).
	 * @return bool Should show.
	 */
	public static function show_in_directory( $user_id, $viewer_id = 0 ) {
		global $wpdb;
		$table = NBUF_Database::get_table_name( 'user_data' );

		$cache_key = "nbuf_directory_show_{$user_id}";
		$row       = wp_cache_get( $cache_key, 'nbuf_privacy' );

		if ( false === $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT show_in_directory, profile_privacy FROM %i WHERE user_id = %d',
					$table,
					$user_id
				)
			);

			wp_cache_set( $cache_key, $row, 'nbuf_privacy', 3600 );
		}

		if ( ! $row || ! $row->show_in_directory ) {
			return false;
		}

		/* Check privacy level */
		return self::can_view_profile( $user_id, $viewer_id );
	}

	/**
	 * Get field privacy setting
	 *
	 * @param  int    $user_id    User ID.
	 * @param  string $field_name Field name.
	 * @return string Privacy level for field.
	 */
	public static function get_field_privacy( $user_id, $field_name ) {
		global $wpdb;
		$table = NBUF_Database::get_table_name( 'user_data' );

		$cache_key     = "nbuf_privacy_settings_{$user_id}";
		$settings_json = wp_cache_get( $cache_key, 'nbuf_privacy' );

		if ( false === $settings_json ) {
			$settings_json = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT privacy_settings FROM %i WHERE user_id = %d',
					$table,
					$user_id
				)
			);

			wp_cache_set( $cache_key, $settings_json, 'nbuf_privacy', 3600 );
		}

		if ( ! $settings_json ) {
			/* Use default from settings */
			return NBUF_Options::get( 'nbuf_directory_default_privacy', self::PRIVACY_PRIVATE );
		}

		$settings = json_decode( $settings_json, true );
		$default  = NBUF_Options::get( 'nbuf_directory_default_privacy', self::PRIVACY_PRIVATE );
		return isset( $settings[ $field_name ] ) ? $settings[ $field_name ] : $default;
	}

	/**
	 * Check if viewer can see specific field
	 *
	 * @param  int    $user_id    Profile owner.
	 * @param  string $field_name Field to check.
	 * @param  int    $viewer_id  Viewer (0 = guest).
	 * @return bool Can view field.
	 */
	public static function can_view_field( $user_id, $field_name, $viewer_id = 0 ) {
		/* Own profile - always visible */
		if ( $user_id === $viewer_id ) {
			return true;
		}

		/* Admins can view all fields */
		if ( $viewer_id && user_can( $viewer_id, 'manage_options' ) ) {
			return true;
		}

		$field_privacy = self::get_field_privacy( $user_id, $field_name );

		switch ( $field_privacy ) {
			case self::PRIVACY_PUBLIC:
				return true;

			case self::PRIVACY_MEMBERS_ONLY:
				return is_user_logged_in();

			case self::PRIVACY_PRIVATE:
				return false;

			default:
				return false;
		}
	}

	/**
	 * Save privacy settings
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Form data.
	 */
	public static function save_privacy_settings( $user_id, $data ) {
		/* Check if users are allowed to adjust privacy settings */
		$allow_user_control = NBUF_Options::get( 'nbuf_allow_user_privacy_control', false );
		if ( ! $allow_user_control ) {
			/* User control disabled - do not save privacy settings from user input */
			return;
		}

		global $wpdb;
		$table = NBUF_Database::get_table_name( 'user_data' );

		$privacy_data = array();

		/* Overall privacy level */
		if ( isset( $data['profile_privacy'] ) ) {
			$privacy_data['profile_privacy'] = sanitize_text_field( $data['profile_privacy'] );
		}

		/* Directory opt-in */
		if ( isset( $data['show_in_directory'] ) ) {
			$privacy_data['show_in_directory'] = (int) $data['show_in_directory'];
		} else {
			/* Checkbox not checked = 0 */
			$privacy_data['show_in_directory'] = 0;
		}

		/* Per-field settings */
		$field_settings  = array();
		$fields_to_check = array( 'profile_photo', 'bio', 'email', 'phone', 'social_links', 'location' );

		foreach ( $fields_to_check as $field ) {
			if ( isset( $data[ "privacy_{$field}" ] ) ) {
				$field_settings[ $field ] = sanitize_text_field( $data[ "privacy_{$field}" ] );
			}
		}

		if ( ! empty( $field_settings ) ) {
			$privacy_data['privacy_settings'] = wp_json_encode( $field_settings );
		}

		/* Update database */
		if ( ! empty( $privacy_data ) ) {
			$wpdb->update(
				$table,
				$privacy_data,
				array( 'user_id' => $user_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		/* Clear cache */
		wp_cache_delete( "nbuf_privacy_level_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_directory_show_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_privacy_settings_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_user_data_{$user_id}", 'nbuf_users' );
	}

	/**
	 * Render privacy settings section
	 *
	 * @param int $user_id User ID.
	 */
	public static function render_privacy_section( $user_id ) {
		global $wpdb;
		$table = NBUF_Database::get_table_name( 'user_data' );

		/* Check if users are allowed to adjust privacy settings */
		$allow_user_control = NBUF_Options::get( 'nbuf_allow_user_privacy_control', false );

		/* If user control is disabled, check if we should display read-only view */
		if ( ! $allow_user_control ) {
			$display_when_disabled = NBUF_Options::get( 'nbuf_display_privacy_when_disabled', false );

			/* If display is also disabled, don't show privacy section at all */
			if ( ! $display_when_disabled ) {
				/* Privacy is assumed to be private - no UI needed */
				return;
			}
		}

		$privacy_level = self::get_user_privacy_level( $user_id );

		/* Get show_in_directory value */
		$show_directory = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT show_in_directory FROM %i WHERE user_id = %d',
				$table,
				$user_id
			)
		);

		/* If user control is disabled but display is enabled, show read-only view */
		if ( ! $allow_user_control ) {
			?>
			<div class="nbuf-privacy-section">
				<h3><?php esc_html_e( 'Privacy Settings', 'nobloat-user-foundry' ); ?></h3>
				<p class="description" style="font-style: italic; color: #646970;">
			<?php esc_html_e( 'Privacy settings are managed by the site administrator. Contact support if you need to change your privacy settings.', 'nobloat-user-foundry' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Profile Privacy', 'nobloat-user-foundry' ); ?></th>
						<td>
							<strong><?php echo esc_html( self::get_privacy_label( $privacy_level ) ); ?></strong>
							<p class="description"><?php echo esc_html( self::get_privacy_description( $privacy_level ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show in Directory', 'nobloat-user-foundry' ); ?></th>
						<td>
							<strong><?php echo $show_directory ? esc_html__( 'Yes', 'nobloat-user-foundry' ) : esc_html__( 'No', 'nobloat-user-foundry' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Whether your profile appears in member directories.', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
			<?php
			return;
		}

		/* User control enabled - show editable form */
		?>
		<div class="nbuf-privacy-section">
			<h3><?php esc_html_e( 'Privacy Settings', 'nobloat-user-foundry' ); ?></h3>

			<table class="form-table">
				<tr>
					<th><label for="profile_privacy"><?php esc_html_e( 'Profile Privacy', 'nobloat-user-foundry' ); ?></label></th>
					<td>
						<select name="profile_privacy" id="profile_privacy">
							<option value="public" <?php selected( $privacy_level, 'public' ); ?>>
								<?php esc_html_e( 'Public - Anyone can view', 'nobloat-user-foundry' ); ?>
							</option>
							<option value="members_only" <?php selected( $privacy_level, 'members_only' ); ?>>
								<?php esc_html_e( 'Members Only - Logged in users', 'nobloat-user-foundry' ); ?>
							</option>
							<option value="private" <?php selected( $privacy_level, 'private' ); ?>>
								<?php esc_html_e( 'Private - Only me', 'nobloat-user-foundry' ); ?>
							</option>
						</select>
						<p class="description"><?php esc_html_e( 'Control who can view your profile information.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>

				<tr>
					<th><label for="show_in_directory"><?php esc_html_e( 'Show in Directory', 'nobloat-user-foundry' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="show_in_directory" id="show_in_directory" value="1" <?php checked( $show_directory ); ?>>
		<?php esc_html_e( 'Include my profile in member directories', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Your profile will respect your privacy settings above.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Get privacy level label
	 *
	 * @param  string $level Privacy level.
	 * @return string Human-readable label.
	 */
	public static function get_privacy_label( $level ) {
		switch ( $level ) {
			case self::PRIVACY_PUBLIC:
				return __( 'Public', 'nobloat-user-foundry' );

			case self::PRIVACY_MEMBERS_ONLY:
				return __( 'Members Only', 'nobloat-user-foundry' );

			case self::PRIVACY_PRIVATE:
				return __( 'Private', 'nobloat-user-foundry' );

			default:
				return __( 'Unknown', 'nobloat-user-foundry' );
		}
	}

	/**
	 * Get privacy level description
	 *
	 * @param  string $level Privacy level.
	 * @return string Human-readable description.
	 */
	public static function get_privacy_description( $level ) {
		switch ( $level ) {
			case self::PRIVACY_PUBLIC:
				return __( 'Anyone can view your profile, including guests who are not logged in.', 'nobloat-user-foundry' );

			case self::PRIVACY_MEMBERS_ONLY:
				return __( 'Only logged-in members can view your profile. Guests cannot view it.', 'nobloat-user-foundry' );

			case self::PRIVACY_PRIVATE:
				return __( 'Your profile is completely private. Only you and site administrators can view it.', 'nobloat-user-foundry' );

			default:
				return '';
		}
	}

	/**
	 * Invalidate all privacy caches for a user
	 *
	 * @param int $user_id User ID.
	 */
	public static function clear_user_cache( $user_id ) {
		wp_cache_delete( "nbuf_privacy_level_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_directory_show_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_privacy_settings_{$user_id}", 'nbuf_privacy' );
		wp_cache_delete( "nbuf_user_data_{$user_id}", 'nbuf_users' );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/* Initialize */
NBUF_Privacy_Manager::init();
