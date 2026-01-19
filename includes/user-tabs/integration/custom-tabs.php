<?php
/**
 * Integration > Custom Tabs Tab
 *
 * Create custom tabs for the frontend account page with shortcode content.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Delete action is handled in NBUF_Settings::handle_custom_tab_delete() on admin_init */

/* Handle edit mode */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit mode selection.
$nbuf_edit_tab_id = isset( $_GET['edit_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_tab'] ) ) : '';

$nbuf_editing_tab = null;
if ( $nbuf_edit_tab_id ) {
	$nbuf_editing_tab = NBUF_Custom_Tabs::get( $nbuf_edit_tab_id );
}

/* Get all custom tabs */
$nbuf_custom_tabs = NBUF_Custom_Tabs::get_all();

/* Get available roles */
$nbuf_wp_roles = wp_roles()->get_names();

/* Show success/error messages */
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only
if ( isset( $_GET['deleted'] ) ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom tab deleted.', 'nobloat-user-foundry' ) . '</p></div>';
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>

<style>
.nbuf-custom-tabs-wrap {
	max-width: 900px;
}
.nbuf-custom-tabs-wrap .form-table th {
	width: 150px;
	padding-top: 15px;
}
.nbuf-drag-handle {
	cursor: move;
	color: #999;
	font-size: 18px;
	width: 30px !important;
	text-align: center;
}
.nbuf-drag-handle:hover {
	color: #666;
}
#nbuf-custom-tabs-list tr.ui-sortable-helper {
	background: #fff;
	box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
#nbuf-custom-tabs-list tr.ui-sortable-placeholder {
	visibility: visible !important;
	background: #f0f6fc;
	border: 2px dashed #2271b1;
}
.nbuf-roles-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
	gap: 8px;
	max-width: 600px;
}
.nbuf-roles-grid label {
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 4px 8px;
	background: #f6f7f7;
	border-radius: 3px;
}
.nbuf-slug-preview {
	color: #666;
	font-family: monospace;
	margin-left: 10px;
}
.nbuf-tab-status-enabled {
	color: #00a32a;
}
.nbuf-tab-status-disabled {
	color: #d63638;
}
.nbuf-info-box {
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
	padding: 12px 16px;
	margin-bottom: 20px;
}
.nbuf-info-box p {
	margin: 0 0 8px 0;
}
.nbuf-info-box p:last-child {
	margin-bottom: 0;
}
.nbuf-info-box code {
	background: #fff;
	padding: 2px 6px;
}
</style>

<div class="nbuf-custom-tabs-wrap">

	<h2><?php esc_html_e( 'Custom Account Page Tabs', 'nobloat-user-foundry' ); ?></h2>

	<div class="nbuf-info-box">
		<p>
			<?php esc_html_e( 'Add custom tabs to the frontend account page. Each tab can display shortcode content, allowing you to integrate third-party plugins like WooCommerce, Easy Digital Downloads, or any other plugin that provides shortcodes.', 'nobloat-user-foundry' ); ?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %1$s: example shortcode, %2$s: example shortcode */
				esc_html__( 'Examples: %1$s for WooCommerce orders, %2$s for EDD purchase history.', 'nobloat-user-foundry' ),
				'<code>[woocommerce_my_account]</code>',
				'<code>[edd_order_history]</code>'
			);
			?>
		</p>
	</div>

	<!-- Add/Edit Form -->
	<h3><?php echo $nbuf_editing_tab ? esc_html__( 'Edit Custom Tab', 'nobloat-user-foundry' ) : esc_html__( 'Add Custom Tab', 'nobloat-user-foundry' ); ?></h3>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		NBUF_Settings::settings_nonce_field();
		settings_errors( 'nbuf_settings' );
		?>

		<input type="hidden" name="nbuf_active_tab" value="integration">
		<input type="hidden" name="nbuf_active_subtab" value="custom-tabs">
		<input type="hidden" name="nbuf_custom_tab_action" value="<?php echo $nbuf_editing_tab ? 'update' : 'create'; ?>">
		<?php if ( $nbuf_editing_tab ) : ?>
			<input type="hidden" name="nbuf_custom_tab_id" value="<?php echo esc_attr( $nbuf_editing_tab['id'] ); ?>">
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf_tab_name"><?php esc_html_e( 'Tab Name', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<input type="text" name="nbuf_custom_tab[name]" id="nbuf_tab_name" class="regular-text"
						value="<?php echo esc_attr( $nbuf_editing_tab['name'] ?? '' ); ?>" required>
					<p class="description"><?php esc_html_e( 'The label displayed on the tab button.', 'nobloat-user-foundry' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_tab_slug"><?php esc_html_e( 'URL Slug', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<input type="text" name="nbuf_custom_tab[slug]" id="nbuf_tab_slug" class="regular-text"
						value="<?php echo esc_attr( $nbuf_editing_tab['slug'] ?? '' ); ?>"
						pattern="[a-z0-9-]+" required>
					<span class="nbuf-slug-preview"></span>
					<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and hyphens only. Used in URLs like /account/your-slug/', 'nobloat-user-foundry' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_tab_content"><?php esc_html_e( 'Content', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<textarea name="nbuf_custom_tab[content]" id="nbuf_tab_content" rows="6" class="large-text code"><?php echo esc_textarea( $nbuf_editing_tab['content'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter shortcode(s) or HTML content. Shortcodes will be processed when the tab is displayed.', 'nobloat-user-foundry' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Role Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Select roles that can see this tab', 'nobloat-user-foundry' ); ?></legend>
						<div class="nbuf-roles-grid">
							<?php
							$nbuf_selected_roles = $nbuf_editing_tab['roles'] ?? array();
							foreach ( $nbuf_wp_roles as $nbuf_role_slug => $nbuf_role_name ) :
								?>
								<label>
									<input type="checkbox" name="nbuf_custom_tab[roles][]"
										value="<?php echo esc_attr( $nbuf_role_slug ); ?>"
										<?php checked( in_array( $nbuf_role_slug, $nbuf_selected_roles, true ) ); ?>>
									<?php echo esc_html( $nbuf_role_name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description" style="margin-top: 10px;">
							<?php esc_html_e( 'Leave all unchecked to show this tab to all logged-in users.', 'nobloat-user-foundry' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_tab_icon"><?php esc_html_e( 'Icon (optional)', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<input type="text" name="nbuf_custom_tab[icon]" id="nbuf_tab_icon" class="regular-text"
						value="<?php echo esc_attr( $nbuf_editing_tab['icon'] ?? '' ); ?>"
						placeholder="dashicons-cart">
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to Dashicons reference */
							esc_html__( 'Enter a %s class name (e.g., dashicons-cart, dashicons-download).', 'nobloat-user-foundry' ),
							'<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer">Dashicon</a>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_tab_priority"><?php esc_html_e( 'Priority', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<input type="number" name="nbuf_custom_tab[priority]" id="nbuf_tab_priority" class="small-text"
						value="<?php echo esc_attr( $nbuf_editing_tab['priority'] ?? 50 ); ?>"
						min="0" max="100">
					<p class="description"><?php esc_html_e( 'Lower numbers appear first. Default is 50. You can also drag to reorder in the list below.', 'nobloat-user-foundry' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_custom_tab[enabled]" value="1"
							<?php checked( $nbuf_editing_tab['enabled'] ?? true ); ?>>
						<?php esc_html_e( 'Enabled - Show this tab on the account page', 'nobloat-user-foundry' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php
		submit_button(
			$nbuf_editing_tab ? __( 'Update Tab', 'nobloat-user-foundry' ) : __( 'Add Tab', 'nobloat-user-foundry' ),
			'primary',
			'submit',
			true
		);

		if ( $nbuf_editing_tab ) :
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=custom-tabs' ) ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<hr style="margin: 30px 0;">

	<!-- Existing Tabs List -->
	<h3><?php esc_html_e( 'Existing Custom Tabs', 'nobloat-user-foundry' ); ?></h3>

	<?php if ( empty( $nbuf_custom_tabs ) ) : ?>
		<p class="description"><?php esc_html_e( 'No custom tabs configured yet. Add one using the form above.', 'nobloat-user-foundry' ); ?></p>
	<?php else : ?>
		<p class="description" style="margin-bottom: 10px;">
			<?php esc_html_e( 'Drag rows to reorder tabs. Changes are saved automatically.', 'nobloat-user-foundry' ); ?>
		</p>
		<table class="wp-list-table widefat fixed striped" id="nbuf-custom-tabs-list">
			<thead>
				<tr>
					<th style="width: 30px;"></th>
					<th><?php esc_html_e( 'Name', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'nobloat-user-foundry' ); ?></th>
					<th><?php esc_html_e( 'Roles', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 70px;"><?php esc_html_e( 'Priority', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 70px;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Actions', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $nbuf_custom_tabs as $nbuf_tab ) : ?>
					<tr data-tab-id="<?php echo esc_attr( $nbuf_tab['id'] ); ?>">
						<td class="nbuf-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'nobloat-user-foundry' ); ?>">&#8801;</td>
						<td>
							<?php if ( ! empty( $nbuf_tab['icon'] ) ) : ?>
								<span class="dashicons <?php echo esc_attr( $nbuf_tab['icon'] ); ?>" style="margin-right: 5px; color: #666;"></span>
							<?php endif; ?>
							<strong><?php echo esc_html( $nbuf_tab['name'] ); ?></strong>
						</td>
						<td><code><?php echo esc_html( $nbuf_tab['slug'] ); ?></code></td>
						<td>
							<?php
							if ( empty( $nbuf_tab['roles'] ) ) {
								echo '<em>' . esc_html__( 'All users', 'nobloat-user-foundry' ) . '</em>';
							} else {
								$nbuf_role_labels = array();
								foreach ( $nbuf_tab['roles'] as $nbuf_role_key ) {
									if ( isset( $nbuf_wp_roles[ $nbuf_role_key ] ) ) {
										$nbuf_role_labels[] = $nbuf_wp_roles[ $nbuf_role_key ];
									}
								}
								echo esc_html( implode( ', ', $nbuf_role_labels ) );
							}
							?>
						</td>
						<td><?php echo esc_html( $nbuf_tab['priority'] ); ?></td>
						<td>
							<?php if ( ! empty( $nbuf_tab['enabled'] ) ) : ?>
								<span class="dashicons dashicons-yes-alt nbuf-tab-status-enabled" title="<?php esc_attr_e( 'Enabled', 'nobloat-user-foundry' ); ?>"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss nbuf-tab-status-disabled" title="<?php esc_attr_e( 'Disabled', 'nobloat-user-foundry' ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'edit_tab', $nbuf_tab['id'] ) ); ?>">
								<?php esc_html_e( 'Edit', 'nobloat-user-foundry' ); ?>
							</a>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'delete_tab', $nbuf_tab['id'] ), 'delete_custom_tab_' . $nbuf_tab['id'] ) ); ?>"
								onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this tab?', 'nobloat-user-foundry' ); ?>');"
								style="color: #b32d2e;">
								<?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

</div>

<script>
jQuery(document).ready(function($) {
	/* Auto-generate slug from name */
	$('#nbuf_tab_name').on('input', function() {
		var $slugField = $('#nbuf_tab_slug');
		/* Only auto-fill if slug is empty or was auto-generated */
		if (!$slugField.data('manual-edit')) {
			var slug = $(this).val()
				.toLowerCase()
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/\s+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-|-$/g, '');
			$slugField.val(slug);
		}
	});

	/* Mark slug as manually edited */
	$('#nbuf_tab_slug').on('input', function() {
		$(this).data('manual-edit', true);
	});

	/* Initialize sortable */
	if ($('#nbuf-custom-tabs-list tbody tr').length > 1) {
		$('#nbuf-custom-tabs-list tbody').sortable({
			handle: '.nbuf-drag-handle',
			cursor: 'move',
			axis: 'y',
			containment: 'parent',
			placeholder: 'ui-sortable-placeholder',
			update: function(event, ui) {
				var order = [];
				$(this).find('tr').each(function() {
					order.push($(this).data('tab-id'));
				});

				/* Save new order via AJAX */
				$.post(ajaxurl, {
					action: 'nbuf_reorder_custom_tabs',
					order: order,
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_reorder_custom_tabs' ) ); ?>'
				}).done(function(response) {
					if (response.success) {
						/* Brief highlight effect */
						$('#nbuf-custom-tabs-list tbody tr').css('background', '#e7f7ed');
						setTimeout(function() {
							$('#nbuf-custom-tabs-list tbody tr').css('background', '');
						}, 500);
					}
				});
			}
		});
	}
});
</script>
