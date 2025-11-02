<?php
/**
 * Access Restrictions Tab
 *
 * Settings for menu and content access restrictions.
 * Control visibility of menu items, posts, and pages based on
 * login status and user roles.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$restrictions_enabled      = NBUF_Options::get( 'nbuf_restrictions_enabled', false );
$menu_enabled              = NBUF_Options::get( 'nbuf_restrictions_menu_enabled', false );
$content_enabled           = NBUF_Options::get( 'nbuf_restrictions_content_enabled', false );
$shortcode_enabled         = NBUF_Options::get( 'nbuf_restrict_content_shortcode_enabled', false );
$widgets_enabled           = NBUF_Options::get( 'nbuf_restrict_widgets_enabled', false );
$taxonomies_enabled        = NBUF_Options::get( 'nbuf_restrict_taxonomies_enabled', false );
$post_types                = NBUF_Options::get( 'nbuf_restrictions_post_types', array( 'post', 'page' ) );
$taxonomies_list           = NBUF_Options::get( 'nbuf_restrict_taxonomies_list', array( 'category', 'post_tag' ) );
$hide_from_queries         = NBUF_Options::get( 'nbuf_restrictions_hide_from_queries', false );
$filter_taxonomy_queries   = NBUF_Options::get( 'nbuf_restrict_taxonomies_filter_queries', false );

/* Ensure post_types is an array */
if ( ! is_array( $post_types ) ) {
	$post_types = array( 'post', 'page' );
}

/* Get all public post types */
$all_post_types = get_post_types( array( 'public' => true ), 'objects' );

?>

<div class="nbuf-restrictions-tab">
	<form method="post" action="options.php">
		<?php settings_fields( 'nbuf_settings_group' ); ?>
		<input type="hidden" name="nbuf_active_tab" value="integration">
		<input type="hidden" name="nbuf_active_subtab" value="restrictions">

		<h2><?php esc_html_e( 'Access Restrictions', 'nobloat-user-foundry' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Control access to menu items, posts, and pages based on login status and user roles. This provides a lightweight alternative to Ultimate Member\'s access restrictions.', 'nobloat-user-foundry' ); ?>
		</p>

		<table class="form-table">
			<!-- Master Toggle -->
			<tr>
				<th><?php esc_html_e( 'Enable Access Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrictions_enabled" value="1" <?php checked( $restrictions_enabled, true ); ?>>
						<?php esc_html_e( 'Enable the access restrictions system', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Master toggle for all restriction features. When disabled, no restrictions will be enforced.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Menu Restrictions -->
			<tr>
				<th><?php esc_html_e( 'Menu Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrictions_menu_enabled" value="1" <?php checked( $menu_enabled, true ); ?>>
						<?php esc_html_e( 'Enable menu item visibility restrictions', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Control which menu items are visible based on login status and user roles. Configure restrictions in Appearance → Menus.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Content Restrictions -->
			<tr>
				<th><?php esc_html_e( 'Content Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrictions_content_enabled" value="1" <?php checked( $content_enabled, true ); ?>>
						<?php esc_html_e( 'Enable post/page access restrictions', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Restrict access to posts and pages based on login status and user roles. Configure restrictions in the post/page editor sidebar.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Restricted Post Types -->
			<tr>
				<th><?php esc_html_e( 'Restricted Post Types', 'nobloat-user-foundry' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Select which post types can have content restrictions', 'nobloat-user-foundry' ); ?></legend>
						<?php
						foreach ( $all_post_types as $post_type ) {
							$checked = in_array( $post_type->name, $post_types, true );
							?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" name="nbuf_restrictions_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( $checked ); ?>>
								<?php echo esc_html( $post_type->label ); ?>
								<span class="description">(<?php echo esc_html( $post_type->name ); ?>)</span>
							</label>
							<?php
						}
						?>
						<p class="description">
							<?php esc_html_e( 'Select which post types can have access restrictions applied. The restriction metabox will appear in the editor for selected post types.', 'nobloat-user-foundry' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- Shortcode Restrictions -->
			<tr>
				<th><?php esc_html_e( 'Shortcode Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrict_content_shortcode_enabled" value="1" <?php checked( $shortcode_enabled, true ); ?>>
						<?php esc_html_e( 'Enable [nbuf_restrict] shortcode', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow restricting specific sections of content using shortcodes. Separate toggle to avoid content parsing overhead when not needed.', 'nobloat-user-foundry' ); ?>
						<br>
						<a href="#" class="nbuf-toggle-docs" data-target="nbuf-shortcode-docs" style="font-weight: 600;">
							<?php esc_html_e( '📖 View Shortcode Documentation', 'nobloat-user-foundry' ); ?>
						</a>
					</p>

					<!-- Expandable Shortcode Documentation -->
					<div id="nbuf-shortcode-docs" class="nbuf-docs-section" style="display:none; margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
						<h4><?php esc_html_e( 'Shortcode Documentation', 'nobloat-user-foundry' ); ?></h4>

						<p><strong><?php esc_html_e( 'Basic Usage:', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict]<?php esc_html_e( 'Your restricted content here', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>

						<h5 style="margin-top: 20px;"><?php esc_html_e( 'Available Attributes:', 'nobloat-user-foundry' ); ?></h5>
						<table class="widefat" style="margin-top: 10px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Attribute', 'nobloat-user-foundry' ); ?></th>
									<th><?php esc_html_e( 'Values', 'nobloat-user-foundry' ); ?></th>
									<th><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>logged_in</code></td>
									<td>"yes" or "no"</td>
									<td><?php esc_html_e( 'Require user to be logged in (yes) or logged out (no)', 'nobloat-user-foundry' ); ?></td>
								</tr>
								<tr>
									<td><code>role</code></td>
									<td><?php esc_html_e( 'Comma-separated roles', 'nobloat-user-foundry' ); ?></td>
									<td><?php esc_html_e( 'Require user to have one of these roles (e.g., "administrator,editor")', 'nobloat-user-foundry' ); ?></td>
								</tr>
								<tr>
									<td><code>verified</code></td>
									<td>"yes" or "no"</td>
									<td><?php esc_html_e( 'Require user email to be verified (yes) or unverified (no)', 'nobloat-user-foundry' ); ?></td>
								</tr>
								<tr>
									<td><code>expired</code></td>
									<td>"yes" or "no"</td>
									<td><?php esc_html_e( 'Show only to expired accounts (yes) or non-expired (no)', 'nobloat-user-foundry' ); ?></td>
								</tr>
								<tr>
									<td><code>message</code></td>
									<td><?php esc_html_e( 'Custom text', 'nobloat-user-foundry' ); ?></td>
									<td><?php esc_html_e( 'Custom message shown to unauthorized users', 'nobloat-user-foundry' ); ?></td>
								</tr>
							</tbody>
						</table>

						<h5 style="margin-top: 20px;"><?php esc_html_e( 'Examples:', 'nobloat-user-foundry' ); ?></h5>

						<p><strong><?php esc_html_e( 'Example 1: Logged-in users only', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict logged_in="yes"]<?php esc_html_e( 'This content is only for logged-in users', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>

						<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Example 2: Specific roles', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict role="subscriber,customer"]<?php esc_html_e( 'VIP content for subscribers and customers', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>

						<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Example 3: Verified users with custom message', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict verified="yes" message="Please verify your email to view this content"]<?php esc_html_e( 'Premium content', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>

						<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Example 4: Combined conditions', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict logged_in="yes" verified="yes" role="subscriber"]<?php esc_html_e( 'Verified subscriber content', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>

						<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Example 5: Non-expired accounts only', 'nobloat-user-foundry' ); ?></strong></p>
						<code>[nbuf_restrict expired="no"]<?php esc_html_e( 'This content is hidden from expired accounts', 'nobloat-user-foundry' ); ?>[/nbuf_restrict]</code>
					</div>
				</td>
			</tr>

			<!-- Widget Restrictions -->
			<tr>
				<th><?php esc_html_e( 'Widget Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrict_widgets_enabled" value="1" <?php checked( $widgets_enabled, true ); ?>>
						<?php esc_html_e( 'Enable widget visibility restrictions', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Control which widgets are visible based on login status and user roles. Configure restrictions in Appearance → Widgets.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Taxonomy Restrictions -->
			<tr>
				<th><?php esc_html_e( 'Taxonomy Restrictions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrict_taxonomies_enabled" value="1" <?php checked( $taxonomies_enabled, true ); ?>>
						<?php esc_html_e( 'Enable taxonomy archive restrictions', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Restrict access to taxonomy archives (categories, tags) based on login status and user roles. Configure restrictions when editing terms.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Restricted Taxonomies List -->
			<tr>
				<th><?php esc_html_e( 'Restricted Taxonomies', 'nobloat-user-foundry' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Select which taxonomies can have restrictions', 'nobloat-user-foundry' ); ?></legend>
						<?php
						$all_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
						foreach ( $all_taxonomies as $taxonomy ) {
							$checked = in_array( $taxonomy->name, $taxonomies_list, true );
							?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" name="nbuf_restrict_taxonomies_list[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php checked( $checked ); ?>>
								<?php echo esc_html( $taxonomy->label ); ?>
								<span class="description">(<?php echo esc_html( $taxonomy->name ); ?>)</span>
							</label>
							<?php
						}
						?>
						<p class="description">
							<?php esc_html_e( 'Select which taxonomies can have access restrictions applied. Restriction fields will appear when editing terms in these taxonomies.', 'nobloat-user-foundry' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- Taxonomy Query Filtering -->
			<tr>
				<th><?php esc_html_e( 'Hide Restricted Taxonomies', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrict_taxonomies_filter_queries" value="1" <?php checked( $filter_taxonomy_queries, true ); ?>>
						<?php esc_html_e( 'Hide restricted taxonomy terms from term lists', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description" style="color: #d63638;">
						<strong><?php esc_html_e( 'Performance Note:', 'nobloat-user-foundry' ); ?></strong>
						<?php esc_html_e( 'When enabled, restricted terms will be completely hidden from term lists and navigation. This adds query filtering which may impact performance. Leave disabled for better performance (restricted terms will still be blocked when accessed directly).', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>

			<!-- Hide from Queries -->
			<tr>
				<th><?php esc_html_e( 'Hide from Archives', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_restrictions_hide_from_queries" value="1" <?php checked( $hide_from_queries, true ); ?>>
						<?php esc_html_e( 'Hide restricted content from archives and search results', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description" style="color: #d63638;">
						<strong><?php esc_html_e( 'Performance Note:', 'nobloat-user-foundry' ); ?></strong>
						<?php esc_html_e( 'When enabled, restricted posts/pages will be completely hidden from archives, category listings, and search results. This adds query modification which may impact performance on sites with many restrictions. Leave disabled for better performance (restricted content will still be blocked when accessed directly).', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Restrictions Settings', 'nobloat-user-foundry' ) ); ?>
	</form>

	<!-- Helper Information -->
	<div class="nbuf-restrictions-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'How to Use Access Restrictions', 'nobloat-user-foundry' ); ?></h3>

		<h4><?php esc_html_e( 'Menu Item Restrictions', 'nobloat-user-foundry' ); ?></h4>
		<ol>
			<li><?php esc_html_e( 'Go to Appearance → Menus', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Select a menu item to edit', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Configure "Access Restriction" settings', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Save the menu', 'nobloat-user-foundry' ); ?></li>
		</ol>

		<h4><?php esc_html_e( 'Post/Page Restrictions', 'nobloat-user-foundry' ); ?></h4>
		<ol>
			<li><?php esc_html_e( 'Edit any post or page', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Look for "Access Restriction" metabox in the sidebar', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Choose who can access the content', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Choose what happens when access is denied (message, redirect, or 404)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Publish or update the post/page', 'nobloat-user-foundry' ); ?></li>
		</ol>

		<h4><?php esc_html_e( 'Visibility Options', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><strong><?php esc_html_e( 'Everyone:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'No restrictions (default)', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Logged In Users:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Only visible to users who are logged in', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Logged Out Users:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Only visible to visitors who are not logged in', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Specific Roles:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Only visible to users with selected roles', 'nobloat-user-foundry' ); ?></li>
		</ul>

		<h4><?php esc_html_e( 'Restriction Actions (Content Only)', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><strong><?php esc_html_e( 'Show Message:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Display a custom message instead of the content', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Redirect to URL:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Redirect users to a specific page (e.g., login page)', 'nobloat-user-foundry' ); ?></li>
			<li><strong><?php esc_html_e( 'Show 404 Page:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Make the content appear as if it doesn\'t exist', 'nobloat-user-foundry' ); ?></li>
		</ul>

		<h4><?php esc_html_e( 'Performance Tips', 'nobloat-user-foundry' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Keep "Hide from Archives" disabled unless absolutely necessary', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Restrictions are loaded efficiently with single database queries', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Menu restrictions automatically hide child items when parent is restricted', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>

	<?php if ( class_exists( 'UM' ) ) : ?>
		<!-- Ultimate Member Migration Notice -->
		<div class="nbuf-um-migration-notice" style="background: #e7f5fe; border-left: 4px solid #0073aa; padding: 1.5rem; margin-top: 2rem;">
			<h3><?php esc_html_e( 'Ultimate Member Detected', 'nobloat-user-foundry' ); ?></h3>
			<p>
				<?php esc_html_e( 'We detected that Ultimate Member is installed. You can migrate your existing content restrictions from Ultimate Member to NoBloat User Foundry using the migration tool in the Tools → Migration tab.', 'nobloat-user-foundry' ); ?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=migration' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Go to Migration Tool', 'nobloat-user-foundry' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<!-- JavaScript for Documentation Toggle -->
	<script>
	jQuery(document).ready(function($) {
		$('.nbuf-toggle-docs').on('click', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			$('#' + target).slideToggle(300);

			/* Update link text */
			var currentText = $(this).text();
			if (currentText.includes('View')) {
				$(this).text('📖 <?php esc_html_e( 'Hide Shortcode Documentation', 'nobloat-user-foundry' ); ?>');
			} else {
				$(this).text('📖 <?php esc_html_e( 'View Shortcode Documentation', 'nobloat-user-foundry' ); ?>');
			}
		});
	});
	</script>
</div>
