<?php
/**
 * Terms of Service Admin Page
 *
 * Admin interface for managing ToS versions and viewing acceptances.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NBUF_ToS_Admin class.
 *
 * Provides admin UI for Terms of Service management.
 *
 * @since 1.5.2
 */
class NBUF_ToS_Admin {

	/**
	 * Initialize admin hooks.
	 *
	 * @since 1.5.2
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ), 18 );
		add_action( 'admin_post_nbuf_save_tos_version', array( __CLASS__, 'handle_save_version' ) );
		add_action( 'admin_post_nbuf_delete_tos_version', array( __CLASS__, 'handle_delete_version' ) );
		add_action( 'admin_post_nbuf_export_tos_acceptances', array( __CLASS__, 'handle_export_acceptances' ) );
		add_action( 'wp_ajax_nbuf_load_default_tos', array( __CLASS__, 'ajax_load_default_tos' ) );
	}

	/**
	 * Add admin menu page.
	 *
	 * @since 1.5.2
	 */
	public static function add_admin_menu(): void {
		add_submenu_page(
			'nobloat-foundry',
			__( 'Terms of Service', 'nobloat-user-foundry' ),
			__( 'Terms of Service', 'nobloat-user-foundry' ),
			'manage_options',
			'nbuf-tos',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.5.2
	 */
	public static function render_admin_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation parameter, not form data. Capability checked at menu registration.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		echo '<div class="wrap">';

		switch ( $action ) {
			case 'new':
			case 'edit':
				self::render_edit_form();
				break;
			case 'view':
				self::render_view_version();
				break;
			case 'acceptances':
				self::render_acceptances();
				break;
			default:
				self::render_versions_list();
		}

		echo '</div>';
	}

	/**
	 * Render versions list.
	 *
	 * @since 1.5.2
	 */
	private static function render_versions_list(): void {
		$versions = NBUF_ToS::get_all_versions();

		/* Check if ToS is enabled */
		$tos_enabled = NBUF_Options::get( 'nbuf_tos_enabled', false );
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Terms of Service', 'nobloat-user-foundry' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New Version', 'nobloat-user-foundry' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php if ( ! $tos_enabled ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: link to settings */
						esc_html__( 'Terms of Service tracking is currently disabled. Enable it in %s to require users to accept.', 'nobloat-user-foundry' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=system&subtab=general' ) ) . '">' . esc_html__( 'Settings', 'nobloat-user-foundry' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display parameter for success messages, not form data.
		if ( isset( $_GET['message'] ) ) :
			?>
			<?php
			$messages = array(
				'saved'   => __( 'Terms of Service version saved.', 'nobloat-user-foundry' ),
				'deleted' => __( 'Terms of Service version deleted.', 'nobloat-user-foundry' ),
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display parameter, validated against allowlist.
			$message_key = sanitize_key( $_GET['message'] );
			if ( isset( $messages[ $message_key ] ) ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $messages[ $message_key ] ); ?></p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( empty( $versions ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No Terms of Service versions created yet. Create one to start tracking acceptances.', 'nobloat-user-foundry' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Version', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Effective Date', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Acceptances', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'nobloat-user-foundry' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $versions as $version ) : ?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=edit&id=' . $version->id ) ); ?>">
										<?php echo esc_html( $version->version ); ?>
									</a>
								</strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=edit&id=' . $version->id ) ); ?>">
											<?php esc_html_e( 'Edit', 'nobloat-user-foundry' ); ?>
										</a> |
									</span>
									<span class="view">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=view&id=' . $version->id ) ); ?>">
											<?php esc_html_e( 'View', 'nobloat-user-foundry' ); ?>
										</a> |
									</span>
									<span class="acceptances">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=acceptances&id=' . $version->id ) ); ?>">
											<?php esc_html_e( 'Acceptances', 'nobloat-user-foundry' ); ?>
										</a> |
									</span>
									<span class="trash">
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbuf_delete_tos_version&id=' . $version->id ), 'nbuf_delete_tos_' . $version->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this version? This will also delete all acceptance records for this version.', 'nobloat-user-foundry' ); ?>');" class="submitdelete">
											<?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?>
										</a>
									</span>
								</div>
							</td>
							<td><?php echo esc_html( $version->title ); ?></td>
							<td>
								<?php
								/* effective_date is stored in local time, so use DateTime with site timezone */
								$effective_dt = new DateTime( $version->effective_date, wp_timezone() );
								echo esc_html( wp_date( get_option( 'date_format' ), $effective_dt->getTimestamp() ) );
								?>
							</td>
							<td>
								<?php if ( $version->is_active ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
									<?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-marker" style="color: #999;"></span>
									<?php esc_html_e( 'Inactive', 'nobloat-user-foundry' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$count = NBUF_ToS::get_acceptance_count( $version->id );
								echo esc_html( number_format_i18n( $count ) );
								?>
							</td>
							<td>
								<?php
								$creator = get_userdata( $version->created_by );
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $version->created_at ) ) );
								if ( $creator ) {
									echo '<br><small>' . esc_html( $creator->display_name ) . '</small>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render edit/create form.
	 *
	 * @since 1.5.2
	 */
	private static function render_edit_form(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation parameter for loading record to edit.
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$version = $id ? NBUF_ToS::get_version( $id ) : null;
		$is_new  = ! $version;

		$title = $is_new ? __( 'Add New ToS Version', 'nobloat-user-foundry' ) : __( 'Edit ToS Version', 'nobloat-user-foundry' );
		?>
		<h1><?php echo esc_html( $title ); ?></h1>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'nbuf_save_tos_version' ); ?>
			<input type="hidden" name="action" value="nbuf_save_tos_version">
			<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="tos_version"><?php esc_html_e( 'Version Number', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<input type="text" name="tos_version" id="tos_version" class="regular-text" value="<?php echo esc_attr( $version ? $version->version : '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g., 1.0, 2.0', 'nobloat-user-foundry' ); ?>">
						<p class="description"><?php esc_html_e( 'A unique version identifier (e.g., 1.0, 2.0, 2024-01).', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tos_title"><?php esc_html_e( 'Title', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<input type="text" name="tos_title" id="tos_title" class="large-text" value="<?php echo esc_attr( $version ? $version->title : '' ); ?>" required placeholder="<?php esc_attr_e( 'Terms of Service', 'nobloat-user-foundry' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tos_effective_date"><?php esc_html_e( 'Effective Date', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<?php
						/* Use local time for both new and existing versions */
						if ( $version ) {
							/* For existing versions, effective_date is stored in local time */
							$effective_value = str_replace( ' ', 'T', substr( $version->effective_date, 0, 16 ) );
						} else {
							/* For new versions, default to current local time */
							$effective_value = current_time( 'Y-m-d\TH:i' );
						}
						?>
						<input type="datetime-local" name="tos_effective_date" id="tos_effective_date" value="<?php echo esc_attr( $effective_value ); ?>" required>
						<p class="description"><?php esc_html_e( 'When this version becomes effective. Users will not be required to accept until after this date.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tos_content"><?php esc_html_e( 'Content', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<?php
						wp_editor(
							$version ? $version->content : '',
							'tos_content',
							array(
								'textarea_name' => 'tos_content',
								'textarea_rows' => 20,
								'media_buttons' => false,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'The full Terms of Service content that users must accept.', 'nobloat-user-foundry' ); ?></p>
						<p style="margin-top: 10px;">
							<button type="button" class="button" id="nbuf-load-default-tos">
								<?php esc_html_e( 'Load Default Template', 'nobloat-user-foundry' ); ?>
							</button>
							<span class="spinner" style="float: none; margin-top: 0;"></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="tos_is_active" value="1" <?php checked( $version && $version->is_active ); ?>>
							<?php esc_html_e( 'Set as active version', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Only one version can be active at a time. Activating this version will deactivate any other active version.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( $is_new ? __( 'Create Version', 'nobloat-user-foundry' ) : __( 'Update Version', 'nobloat-user-foundry' ) ); ?>
		</form>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos' ) ); ?>">
				&larr; <?php esc_html_e( 'Back to versions list', 'nobloat-user-foundry' ); ?>
			</a>
		</p>

		<script>
		jQuery(document).ready(function($) {
			$('#nbuf-load-default-tos').on('click', function() {
				var $button = $(this);
				var $spinner = $button.next('.spinner');

				if (!confirm('<?php echo esc_js( __( 'This will replace the current content with the default template. Continue?', 'nobloat-user-foundry' ) ); ?>')) {
					return;
				}

				$button.prop('disabled', true);
				$spinner.addClass('is-active');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'nbuf_load_default_tos',
						nonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_load_default_tos' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							/* Set content in TinyMCE or textarea */
							if (typeof tinymce !== 'undefined' && tinymce.get('tos_content')) {
								tinymce.get('tos_content').setContent(response.data.content);
							} else {
								$('#tos_content').val(response.data.content);
							}
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Failed to load template.', 'nobloat-user-foundry' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Failed to load template.', 'nobloat-user-foundry' ) ); ?>');
					},
					complete: function() {
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render view version page.
	 *
	 * @since 1.5.2
	 */
	private static function render_view_version(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation parameter for loading record to view.
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$version = $id ? NBUF_ToS::get_version( $id ) : null;

		if ( ! $version ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Version not found.', 'nobloat-user-foundry' ) . '</p></div>';
			return;
		}

		$creator = get_userdata( $version->created_by );
		?>
		<h1><?php echo esc_html( $version->title ); ?></h1>

		<?php
		/* Convert local datetime strings to proper timestamps for display */
		$effective_dt = new DateTime( $version->effective_date, wp_timezone() );
		$created_dt   = new DateTime( $version->created_at, wp_timezone() );
		$date_format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="card" style="max-width: 800px;">
			<p>
				<strong><?php esc_html_e( 'Version:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( $version->version ); ?><br>
				<strong><?php esc_html_e( 'Effective:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( wp_date( $date_format, $effective_dt->getTimestamp() ) ); ?><br>
				<strong><?php esc_html_e( 'Status:', 'nobloat-user-foundry' ); ?></strong> <?php echo $version->is_active ? esc_html__( 'Active', 'nobloat-user-foundry' ) : esc_html__( 'Inactive', 'nobloat-user-foundry' ); ?><br>
				<strong><?php esc_html_e( 'Created:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( wp_date( $date_format, $created_dt->getTimestamp() ) ); ?>
				<?php if ( $creator ) : ?>
					<?php esc_html_e( 'by', 'nobloat-user-foundry' ); ?> <?php echo esc_html( $creator->display_name ); ?>
				<?php endif; ?>
			</p>

			<hr>

			<div style="max-height: 400px; overflow-y: auto; padding: 10px; background: #f9f9f9; border: 1px solid #e0e0e0;">
				<?php echo wp_kses_post( $version->content ); ?>
			</div>
		</div>

		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=edit&id=' . $version->id ) ); ?>" class="button">
				<?php esc_html_e( 'Edit', 'nobloat-user-foundry' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos&action=acceptances&id=' . $version->id ) ); ?>" class="button">
				<?php esc_html_e( 'View Acceptances', 'nobloat-user-foundry' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos' ) ); ?>">
				&larr; <?php esc_html_e( 'Back to versions list', 'nobloat-user-foundry' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render acceptances list.
	 *
	 * @since 1.5.2
	 */
	private static function render_acceptances(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation parameter, not form data.
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$version = $id ? NBUF_ToS::get_version( $id ) : null;

		if ( ! $version ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Version not found.', 'nobloat-user-foundry' ) . '</p></div>';
			return;
		}

		$acceptances = NBUF_ToS::get_acceptances_for_export( $id );
		?>
		<h1>
			<?php
			printf(
				/* translators: %s: version number */
				esc_html__( 'Acceptances for Version %s', 'nobloat-user-foundry' ),
				esc_html( $version->version )
			);
			?>
		</h1>

		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nbuf_export_tos_acceptances&id=' . $id ), 'nbuf_export_tos_' . $id ) ); ?>" class="button">
				<?php esc_html_e( 'Export to CSV', 'nobloat-user-foundry' ); ?>
			</a>
		</p>

		<?php if ( empty( $acceptances ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No users have accepted this version yet.', 'nobloat-user-foundry' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'User', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Accepted At', 'nobloat-user-foundry' ); ?></th>
						<th scope="col"><?php esc_html_e( 'IP Address', 'nobloat-user-foundry' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $acceptances as $acceptance ) : ?>
						<tr>
							<td>
								<?php if ( $acceptance->user_login ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $acceptance->user_id ) ); ?>">
										<?php echo esc_html( $acceptance->display_name ? $acceptance->display_name : $acceptance->user_login ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Deleted user', 'nobloat-user-foundry' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $acceptance->user_email ? $acceptance->user_email : '-' ); ?></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $acceptance->accepted_at ) ) ); ?></td>
							<td><code><?php echo esc_html( $acceptance->ip_address ? $acceptance->ip_address : '-' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-tos' ) ); ?>">
				&larr; <?php esc_html_e( 'Back to versions list', 'nobloat-user-foundry' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Handle save version form submission.
	 *
	 * @since 1.5.2
	 */
	public static function handle_save_version(): void {
		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'nbuf_save_tos_version' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Check permissions */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Terms of Service.', 'nobloat-user-foundry' ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$data = array(
			'version'        => isset( $_POST['tos_version'] ) ? sanitize_text_field( wp_unslash( $_POST['tos_version'] ) ) : '',
			'title'          => isset( $_POST['tos_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tos_title'] ) ) : '',
			'content'        => isset( $_POST['tos_content'] ) ? wp_kses_post( wp_unslash( $_POST['tos_content'] ) ) : '',
			'effective_date' => isset( $_POST['tos_effective_date'] ) ? sanitize_text_field( wp_unslash( $_POST['tos_effective_date'] ) ) : '',
			'is_active'      => ! empty( $_POST['tos_is_active'] ),
		);

		if ( $id ) {
			NBUF_ToS::update_version( $id, $data );
		} else {
			NBUF_ToS::create_version( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nbuf-tos&message=saved' ) );
		exit;
	}

	/**
	 * Handle delete version.
	 *
	 * @since 1.5.2
	 */
	public static function handle_delete_version(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'nbuf_delete_tos_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Check permissions */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Terms of Service.', 'nobloat-user-foundry' ) );
		}

		if ( $id ) {
			NBUF_ToS::delete_version( $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nbuf-tos&message=deleted' ) );
		exit;
	}

	/**
	 * Handle export acceptances to CSV.
	 *
	 * @since 1.5.2
	 */
	public static function handle_export_acceptances(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		/* Verify nonce */
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'nbuf_export_tos_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'nobloat-user-foundry' ) );
		}

		/* Check permissions */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'nobloat-user-foundry' ) );
		}

		$acceptances = NBUF_ToS::get_acceptances_for_export( $id );
		$version     = $id ? NBUF_ToS::get_version( $id ) : null;

		$filename = 'tos-acceptances';
		if ( $version ) {
			$filename .= '-v' . sanitize_file_name( $version->version );
		}
		$filename .= '-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		/* CSV header */
		fputcsv(
			$output,
			array(
				'User ID',
				'Username',
				'Email',
				'Display Name',
				'Version',
				'Accepted At',
				'IP Address',
				'User Agent',
			)
		);

		/* CSV data */
		foreach ( $acceptances as $acceptance ) {
			fputcsv(
				$output,
				array(
					$acceptance->user_id,
					$acceptance->user_login ? $acceptance->user_login : 'deleted',
					$acceptance->user_email ? $acceptance->user_email : '',
					$acceptance->display_name ? $acceptance->display_name : '',
					$acceptance->version ? $acceptance->version : '',
					$acceptance->accepted_at,
					$acceptance->ip_address ? $acceptance->ip_address : '',
					$acceptance->user_agent ? $acceptance->user_agent : '',
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Writing to php://output for streaming CSV download.
		fclose( $output );
		exit;
	}

	/**
	 * AJAX handler to load default ToS template.
	 *
	 * @since 1.5.2
	 */
	public static function ajax_load_default_tos(): void {
		check_ajax_referer( 'nbuf_load_default_tos', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nobloat-user-foundry' ) ) );
		}

		$template_path = NBUF_TEMPLATES_DIR . 'tos-default.html';

		if ( ! file_exists( $template_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Default template not found.', 'nobloat-user-foundry' ) ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$content = file_get_contents( $template_path );

		/* Replace placeholders */
		$content = str_replace(
			array( '{site_name}', '{site_url}', '{current_date}' ),
			array( get_bloginfo( 'name' ), home_url(), wp_date( get_option( 'date_format' ) ) ),
			$content
		);

		wp_send_json_success( array( 'content' => $content ) );
	}
}
