<?php
/**
 * NoBloat User Foundry - Roles Management Page
 *
 * Admin interface for creating and managing custom user roles.
 * Provides lightweight, efficient role management with WordPress integration.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Roles_Page {

	/**
	 * Initialize roles page
	 */
	public static function init() {
		/* Add AJAX handlers */
		add_action( 'wp_ajax_nbuf_save_role', array( __CLASS__, 'ajax_save_role' ) );
		add_action( 'wp_ajax_nbuf_delete_role', array( __CLASS__, 'ajax_delete_role' ) );
		add_action( 'wp_ajax_nbuf_export_role', array( __CLASS__, 'ajax_export_role' ) );
	}

	/**
	 * Render roles management page
	 */
	public static function render_page() {
		/* Check user capabilities */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
		}

		/* Determine view */
		$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$role_id = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'User Roles', 'nobloat-user-foundry' ); ?></h1>
			<?php if ( 'list' === $action ) : ?>
				<a href="?page=nobloat-foundry-roles&action=add" class="page-title-action"><?php esc_html_e( 'Add New Role', 'nobloat-user-foundry' ); ?></a>
			<?php elseif ( 'edit' === $action || 'add' === $action ) : ?>
				<a href="?page=nobloat-foundry-roles" class="page-title-action"><?php esc_html_e( 'Back to Roles', 'nobloat-user-foundry' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php
			switch ( $action ) {
				case 'add':
					self::render_role_editor( null );
					break;
				case 'edit':
					self::render_role_editor( $role_id );
					break;
				default:
					self::render_role_list();
					break;
			}
			?>
		</div>

		<style>
		.nbuf-roles-table {
			margin-top: 20px;
		}
		.nbuf-roles-table th {
			text-align: left;
			padding: 8px 10px;
		}
		.nbuf-roles-table td {
			padding: 8px 10px;
		}
		.role-actions {
			visibility: hidden;
		}
		tr:hover .role-actions {
			visibility: visible;
		}
		.role-badge {
			display: inline-block;
			padding: 3px 8px;
			background: #f0f0f1;
			border-radius: 3px;
			font-size: 11px;
			margin-left: 5px;
		}
		.role-badge.native {
			background: #d7dce5;
		}
		.role-editor-tabs {
			margin: 20px 0;
			border-bottom: 1px solid #c3c4c7;
		}
		.role-editor-tabs button {
			background: none;
			border: none;
			padding: 10px 20px;
			cursor: pointer;
			border-bottom: 2px solid transparent;
			font-size: 14px;
		}
		.role-editor-tabs button.active {
			border-bottom-color: #2271b1;
			color: #2271b1;
		}
		.role-editor-tab-content {
			display: none;
			padding: 20px 0;
		}
		.role-editor-tab-content.active {
			display: block;
		}
		.capability-group {
			margin-bottom: 30px;
		}
		.capability-group h3 {
			margin-bottom: 10px;
			border-bottom: 1px solid #f0f0f1;
			padding-bottom: 5px;
		}
		.capability-checkboxes {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
			gap: 10px;
			margin-top: 15px;
		}
		.capability-checkboxes label {
			display: flex;
			align-items: center;
		}
		</style>
		<?php
	}

	/**
	 * Render role list table
	 */
	private static function render_role_list() {
		/* Get all roles (WordPress + custom) */
		$wp_roles     = wp_roles()->roles;
		$custom_roles = NBUF_Role_Manager::get_all_roles();

		?>
		<table class="wp-list-table widefat fixed striped nbuf-roles-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Role Name', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Role Key', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Users', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Capabilities', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Type', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$native_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

				foreach ( $wp_roles as $role_key => $role_data ) :
					$is_native  = in_array( $role_key, $native_roles, true );
					$is_custom  = isset( $custom_roles[ $role_key ] );
					$user_count = NBUF_Role_Manager::get_user_count( $role_key );
					$cap_count  = count( $role_data['capabilities'] );
					$priority   = $is_custom ? $custom_roles[ $role_key ]['priority'] : 0;
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $role_data['name'] ); ?></strong>
							<div class="role-actions">
								<?php if ( $is_custom ) : ?>
									<a href="?page=nobloat-foundry-roles&action=edit&role=<?php echo esc_attr( $role_key ); ?>"><?php esc_html_e( 'Edit', 'nobloat-user-foundry' ); ?></a> |
									<a href="#" class="nbuf-delete-role" data-role="<?php echo esc_attr( $role_key ); ?>" data-name="<?php echo esc_attr( $role_data['name'] ); ?>"><?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?></a> |
									<a href="#" class="nbuf-export-role" data-role="<?php echo esc_attr( $role_key ); ?>"><?php esc_html_e( 'Export', 'nobloat-user-foundry' ); ?></a>
								<?php else : ?>
									<span style="color: #888;"><?php esc_html_e( 'Native WordPress role', 'nobloat-user-foundry' ); ?></span>
								<?php endif; ?>
							</div>
						</td>
						<td><code><?php echo esc_html( $role_key ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $user_count ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $cap_count ) ); ?></td>
						<td>
							<?php if ( $is_native ) : ?>
								<span class="role-badge native"><?php esc_html_e( 'WordPress', 'nobloat-user-foundry' ); ?></span>
							<?php elseif ( $is_custom ) : ?>
								<span class="role-badge"><?php esc_html_e( 'Custom', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $priority ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		jQuery(document).ready(function($) {
			/* Delete role */
			$('.nbuf-delete-role').on('click', function(e) {
				e.preventDefault();
				const roleKey = $(this).data('role');
				const roleName = $(this).data('name');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this role?', 'nobloat-user-foundry' ) ); ?>\n\n' + roleName + '\n\n<?php echo esc_js( __( 'All users with this role will be reassigned to Subscriber.', 'nobloat-user-foundry' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_delete_role',
						nonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_roles_nonce' ) ); ?>',
						role_key: roleKey
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error:', 'nobloat-user-foundry' ) ); ?> ' + response.data.message);
						}
					}
				});
			});

			/* Export role */
			$('.nbuf-export-role').on('click', function(e) {
				e.preventDefault();
				const roleKey = $(this).data('role');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_export_role',
						nonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_roles_nonce' ) ); ?>',
						role_key: roleKey
					},
					success: function(response) {
						if (response.success) {
							/* Download JSON file */
							const blob = new Blob([response.data.json], {type: 'application/json'});
							const url = URL.createObjectURL(blob);
							const a = document.createElement('a');
							a.href = url;
							a.download = 'role-' + roleKey + '.json';
							a.click();
						} else {
							alert('<?php echo esc_js( __( 'Error:', 'nobloat-user-foundry' ) ); ?> ' + response.data.message);
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render role editor (add/edit)
	 *
	 * @param string|null $role_key Role key for editing, null for new
	 */
	private static function render_role_editor( $role_key ) {
		$is_edit = ! empty( $role_key );
		$role_data = $is_edit ? NBUF_Role_Manager::get_role( $role_key ) : null;

		/* If editing and role not found */
		if ( $is_edit && ! $role_data ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Role not found.', 'nobloat-user-foundry' ) . '</p></div>';
			return;
		}

		/* Get all capabilities organized by category */
		$all_capabilities = NBUF_Role_Manager::get_all_capabilities();
		$current_caps = $is_edit ? $role_data['capabilities'] : array();

		/* Get all roles for parent selection */
		$all_roles = wp_roles()->get_names();
		?>

		<form id="nbuf-role-editor-form" method="post" style="max-width: 1200px;">
			<input type="hidden" name="action" value="nbuf_save_role">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'nbuf_roles_nonce' ) ); ?>">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="role_key" value="<?php echo esc_attr( $role_key ); ?>">
				<input type="hidden" name="is_edit" value="1">
			<?php endif; ?>

			<!-- Basic Information -->
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="role_name"><?php esc_html_e( 'Role Name', 'nobloat-user-foundry' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="role_name" id="role_name" class="regular-text"
								value="<?php echo $is_edit ? esc_attr( $role_data['name'] ) : ''; ?>" required>
							<p class="description"><?php esc_html_e( 'Display name for the role (e.g., "Team Manager")', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>

					<?php if ( ! $is_edit ) : ?>
					<tr>
						<th scope="row">
							<label for="role_key"><?php esc_html_e( 'Role Key', 'nobloat-user-foundry' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="role_key" id="role_key" class="regular-text"
								pattern="[a-z0-9_]+" required>
							<p class="description"><?php esc_html_e( 'Unique key for the role (lowercase letters, numbers, underscores only, e.g., "team_manager")', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>

					<tr>
						<th scope="row">
							<label for="parent_role"><?php esc_html_e( 'Inherit From', 'nobloat-user-foundry' ); ?></label>
						</th>
						<td>
							<select name="parent_role" id="parent_role" class="regular-text">
								<option value=""><?php esc_html_e( '-- No Inheritance --', 'nobloat-user-foundry' ); ?></option>
								<?php foreach ( $all_roles as $r_key => $r_name ) : ?>
									<?php if ( $is_edit && $r_key === $role_key ) continue; // Skip self ?>
									<option value="<?php echo esc_attr( $r_key ); ?>" <?php selected( $is_edit && $role_data['parent_role'], $r_key ); ?>>
										<?php echo esc_html( $r_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Inherit all capabilities from another role and add custom ones.', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="priority"><?php esc_html_e( 'Priority', 'nobloat-user-foundry' ); ?></label>
						</th>
						<td>
							<input type="number" name="priority" id="priority" class="small-text"
								value="<?php echo $is_edit ? esc_attr( $role_data['priority'] ) : '0'; ?>" min="0" max="999">
							<p class="description"><?php esc_html_e( 'Higher priority roles take precedence when a user has multiple roles. Default: 0', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Capabilities Tabs -->
			<h2><?php esc_html_e( 'Capabilities', 'nobloat-user-foundry' ); ?></h2>
			<div class="role-editor-tabs">
				<button type="button" class="role-tab-btn active" data-tab="content"><?php esc_html_e( 'Content', 'nobloat-user-foundry' ); ?></button>
				<button type="button" class="role-tab-btn" data-tab="users"><?php esc_html_e( 'Users', 'nobloat-user-foundry' ); ?></button>
				<button type="button" class="role-tab-btn" data-tab="settings"><?php esc_html_e( 'Settings', 'nobloat-user-foundry' ); ?></button>
				<button type="button" class="role-tab-btn" data-tab="plugins"><?php esc_html_e( 'Plugins', 'nobloat-user-foundry' ); ?></button>
				<button type="button" class="role-tab-btn" data-tab="themes"><?php esc_html_e( 'Themes', 'nobloat-user-foundry' ); ?></button>
				<button type="button" class="role-tab-btn" data-tab="other"><?php esc_html_e( 'Other', 'nobloat-user-foundry' ); ?></button>
			</div>

			<?php foreach ( $all_capabilities as $category => $caps ) : ?>
			<div class="role-editor-tab-content <?php echo 'content' === $category ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $category ); ?>">
				<div class="capability-checkboxes">
					<?php foreach ( $caps as $cap ) : ?>
						<label>
							<input type="checkbox" name="capabilities[]" value="<?php echo esc_attr( $cap ); ?>"
								<?php checked( isset( $current_caps[ $cap ] ) && $current_caps[ $cap ] ); ?>>
							<code style="margin-left: 5px;"><?php echo esc_html( $cap ); ?></code>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>

			<?php submit_button( $is_edit ? __( 'Update Role', 'nobloat-user-foundry' ) : __( 'Create Role', 'nobloat-user-foundry' ) ); ?>
		</form>

		<script>
		jQuery(document).ready(function($) {
			/* Tab switching */
			$('.role-tab-btn').on('click', function() {
				const tab = $(this).data('tab');

				$('.role-tab-btn').removeClass('active');
				$(this).addClass('active');

				$('.role-editor-tab-content').removeClass('active');
				$('.role-editor-tab-content[data-tab="' + tab + '"]').addClass('active');
			});

			/* Form submission */
			$('#nbuf-role-editor-form').on('submit', function(e) {
				e.preventDefault();

				const formData = $(this).serialize();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					success: function(response) {
						if (response.success) {
							window.location.href = '?page=nobloat-foundry-roles';
						} else {
							alert('<?php echo esc_js( __( 'Error:', 'nobloat-user-foundry' ) ); ?> ' + response.data.message);
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Save role (create or update)
	 */
	public static function ajax_save_role() {
		check_ajax_referer( 'nbuf_roles_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$is_edit    = ! empty( $_POST['is_edit'] );
		$role_key   = isset( $_POST['role_key'] ) ? sanitize_text_field( wp_unslash( $_POST['role_key'] ) ) : '';
		$role_name  = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$parent     = isset( $_POST['parent_role'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_role'] ) ) : null;
		$priority   = isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 0;
		$capabilities = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['capabilities'] ) ) : array();

		/* Convert capabilities array to associative (cap => true) */
		$caps_assoc = array();
		foreach ( $capabilities as $cap ) {
			$caps_assoc[ $cap ] = true;
		}

		if ( $is_edit ) {
			$result = NBUF_Role_Manager::update_role( $role_key, array(
				'role_name'    => $role_name,
				'capabilities' => $caps_assoc,
				'parent_role'  => $parent,
				'priority'     => $priority,
			) );
		} else {
			$result = NBUF_Role_Manager::create_role( $role_key, $role_name, $caps_assoc, $parent, $priority );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Delete role
	 */
	public static function ajax_delete_role() {
		check_ajax_referer( 'nbuf_roles_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$role_key = isset( $_POST['role_key'] ) ? sanitize_text_field( wp_unslash( $_POST['role_key'] ) ) : '';
		$result   = NBUF_Role_Manager::delete_role( $role_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Export role as JSON
	 */
	public static function ajax_export_role() {
		check_ajax_referer( 'nbuf_roles_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'nobloat-user-foundry' ) ) );
		}

		$role_key = isset( $_POST['role_key'] ) ? sanitize_text_field( wp_unslash( $_POST['role_key'] ) ) : '';
		$json     = NBUF_Role_Manager::export_role( $role_key );

		if ( is_wp_error( $json ) ) {
			wp_send_json_error( array( 'message' => $json->get_error_message() ) );
		}

		wp_send_json_success( array( 'json' => $json ) );
	}
}

// Initialize Roles Page.
NBUF_Roles_Page::init();
