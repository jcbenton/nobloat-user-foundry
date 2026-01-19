<?php
/**
 * Merge Accounts > WordPress Tab
 *
 * WordPress core account merging functionality.
 * Two-account selection with field-by-field merge options.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="nbuf-merge-accounts">
	<p class="description" style="margin-bottom: 20px;">
		<?php esc_html_e( 'Select two accounts to merge. The source account will be merged into the target account, then deleted.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- Account Selection -->
	<div class="nbuf-merge-selection" style="display: flex; gap: 30px; margin-bottom: 30px;">
		<!-- Source Account (will be deleted) -->
		<div class="nbuf-merge-account-box" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
			<h3 style="margin-top: 0; color: #d63638;">
				<span class="dashicons dashicons-minus-circle" style="color: #d63638;"></span>
				<?php esc_html_e( 'Source Account', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description"><?php esc_html_e( 'This account will be merged and then deleted.', 'nobloat-user-foundry' ); ?></p>
			<div class="nbuf-user-search">
				<input type="text"
						id="nbuf-source-search"
						class="regular-text nbuf-user-search-input"
						placeholder="<?php esc_attr_e( 'Search by name, email, or username...', 'nobloat-user-foundry' ); ?>"
						autocomplete="off">
				<div id="nbuf-source-results" class="nbuf-search-results" style="display: none;"></div>
				<input type="hidden" id="nbuf-source-id" name="nbuf_source_account" value="">
			</div>
			<div id="nbuf-source-selected" class="nbuf-selected-user" style="display: none; margin-top: 15px; padding: 15px; background: #fef7f1; border-left: 4px solid #d63638;"></div>
		</div>

		<!-- Arrow -->
		<div style="display: flex; align-items: center; font-size: 30px; color: #2271b1;">
			<span class="dashicons dashicons-arrow-right-alt" style="font-size: 40px; width: 40px; height: 40px;"></span>
		</div>

		<!-- Target Account (will be kept) -->
		<div class="nbuf-merge-account-box" style="flex: 1; background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
			<h3 style="margin-top: 0; color: #00a32a;">
				<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
				<?php esc_html_e( 'Target Account', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description"><?php esc_html_e( 'This account will be kept and receive the merged data.', 'nobloat-user-foundry' ); ?></p>
			<div class="nbuf-user-search">
				<input type="text"
						id="nbuf-target-search"
						class="regular-text nbuf-user-search-input"
						placeholder="<?php esc_attr_e( 'Search by name, email, or username...', 'nobloat-user-foundry' ); ?>"
						autocomplete="off">
				<div id="nbuf-target-results" class="nbuf-search-results" style="display: none;"></div>
				<input type="hidden" id="nbuf-target-id" name="nbuf_target_account" value="">
			</div>
			<div id="nbuf-target-selected" class="nbuf-selected-user" style="display: none; margin-top: 15px; padding: 15px; background: #edfaef; border-left: 4px solid #00a32a;"></div>
		</div>
	</div>

	<!-- Merge Options (shown after both accounts selected) -->
	<div id="nbuf-merge-options-panel" style="display: none;">
		<form id="nbuf-merge-form" method="post" action="">
			<?php wp_nonce_field( 'nbuf_merge_accounts', 'nbuf_merge_nonce' ); ?>
			<input type="hidden" name="nbuf_source_account" id="nbuf-form-source">
			<input type="hidden" name="nbuf_target_account" id="nbuf-form-target">

			<!-- Field Selection -->
			<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
				<h3 style="margin-top: 0;">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Choose Which Values to Keep', 'nobloat-user-foundry' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'For each field, select which account\'s value should be used in the merged account.', 'nobloat-user-foundry' ); ?></p>

				<div id="nbuf-field-choices" style="margin-top: 20px;">
					<!-- WordPress Core Fields -->
					<h4 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;"><?php esc_html_e( 'WordPress Fields', 'nobloat-user-foundry' ); ?></h4>
					<table class="widefat" id="nbuf-wp-fields-table">
						<thead>
							<tr>
								<th style="width: 20%;"><?php esc_html_e( 'Field', 'nobloat-user-foundry' ); ?></th>
								<th style="width: 35%;"><?php esc_html_e( 'Source Value', 'nobloat-user-foundry' ); ?></th>
								<th style="width: 35%;"><?php esc_html_e( 'Target Value', 'nobloat-user-foundry' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Keep', 'nobloat-user-foundry' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<!-- Populated via JavaScript -->
						</tbody>
					</table>

					<!-- NoBloat Extended Fields -->
					<div id="nbuf-extended-fields-section" style="display: none; margin-top: 30px;">
						<h4 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;"><?php esc_html_e( 'Extended Profile Fields', 'nobloat-user-foundry' ); ?></h4>
						<table class="widefat" id="nbuf-extended-fields-table">
							<thead>
								<tr>
									<th style="width: 20%;"><?php esc_html_e( 'Field', 'nobloat-user-foundry' ); ?></th>
									<th style="width: 35%;"><?php esc_html_e( 'Source Value', 'nobloat-user-foundry' ); ?></th>
									<th style="width: 35%;"><?php esc_html_e( 'Target Value', 'nobloat-user-foundry' ); ?></th>
									<th style="width: 10%;"><?php esc_html_e( 'Keep', 'nobloat-user-foundry' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<!-- Populated via JavaScript -->
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Content Options -->
			<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
				<h3 style="margin-top: 0;">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Content & Data', 'nobloat-user-foundry' ); ?>
				</h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Reassign Content', 'nobloat-user-foundry' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="nbuf_merge_posts" value="1" checked>
								<?php esc_html_e( 'Posts and pages', 'nobloat-user-foundry' ); ?>
								<span id="nbuf-source-post-count" class="description"></span>
							</label><br>
							<label>
								<input type="checkbox" name="nbuf_merge_comments" value="1" checked>
								<?php esc_html_e( 'Comments', 'nobloat-user-foundry' ); ?>
								<span id="nbuf-source-comment-count" class="description"></span>
							</label><br>
							<label>
								<input type="checkbox" name="nbuf_merge_meta" value="1" checked>
								<?php esc_html_e( 'User meta (plugin data, preferences)', 'nobloat-user-foundry' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Copies non-duplicate meta keys from source to target. Core WordPress meta (capabilities, sessions) is excluded.', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email Addresses', 'nobloat-user-foundry' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="nbuf_consolidate_emails" value="1" checked>
								<?php esc_html_e( 'Store source email as secondary email on target account', 'nobloat-user-foundry' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'The source account\'s email will be added as a secondary email, allowing login with either address.', 'nobloat-user-foundry' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notifications', 'nobloat-user-foundry' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="nbuf_notify_user" value="1">
								<?php esc_html_e( 'Send email notification about the merge', 'nobloat-user-foundry' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Execute -->
			<div style="background: #fcf0f1; border: 1px solid #d63638; padding: 20px; border-radius: 4px;">
				<h3 style="margin-top: 0; color: #d63638;">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Confirm Merge', 'nobloat-user-foundry' ); ?>
				</h3>
				<p><?php esc_html_e( 'This action will permanently delete the source account after merging its data into the target account. This cannot be undone.', 'nobloat-user-foundry' ); ?></p>
				<p>
					<label style="font-weight: 600;">
						<input type="checkbox" id="nbuf-confirm-merge" name="nbuf_confirm_merge" value="1">
						<?php esc_html_e( 'I understand this action cannot be undone', 'nobloat-user-foundry' ); ?>
					</label>
				</p>
				<p style="margin-top: 15px;">
					<button type="submit" id="nbuf-execute-merge" class="button button-primary button-hero" disabled>
						<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Merge Accounts', 'nobloat-user-foundry' ); ?>
					</button>
					<button type="button" id="nbuf-cancel-merge" class="button button-secondary" style="margin-left: 10px;">
						<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
					</button>
				</p>
			</div>
		</form>
	</div>
</div>

<style>
.nbuf-user-search {
	position: relative;
}
.nbuf-user-search-input {
	width: 100% !important;
}
.nbuf-search-results {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-top: none;
	max-height: 250px;
	overflow-y: auto;
	z-index: 1000;
	box-shadow: 0 3px 5px rgba(0,0,0,0.1);
}
.nbuf-search-result {
	padding: 10px 15px;
	cursor: pointer;
	border-bottom: 1px solid #f0f0f0;
}
.nbuf-search-result:hover {
	background: #f0f6fc;
}
.nbuf-search-result:last-child {
	border-bottom: none;
}
.nbuf-search-result .user-name {
	font-weight: 600;
}
.nbuf-search-result .user-email {
	color: #646970;
	font-size: 12px;
}
.nbuf-search-result .user-meta {
	color: #8c8f94;
	font-size: 11px;
	margin-top: 3px;
}
.nbuf-selected-user .user-avatar {
	float: left;
	margin-right: 15px;
}
.nbuf-selected-user .user-info {
	overflow: hidden;
}
.nbuf-selected-user .user-name {
	font-weight: 600;
	font-size: 14px;
}
.nbuf-selected-user .user-details {
	color: #646970;
	font-size: 12px;
	margin-top: 5px;
}
.nbuf-selected-user .remove-selection {
	float: right;
	color: #d63638;
	cursor: pointer;
	text-decoration: none;
}
.nbuf-selected-user .remove-selection:hover {
	text-decoration: underline;
}
#nbuf-wp-fields-table td,
#nbuf-extended-fields-table td {
	vertical-align: middle;
}
#nbuf-wp-fields-table .field-empty,
#nbuf-extended-fields-table .field-empty {
	color: #8c8f94;
	font-style: italic;
}
</style>

<?php
/* Enqueue merge accounts JavaScript */
wp_enqueue_script(
	'nbuf-merge-accounts',
	NBUF_PLUGIN_URL . 'assets/js/admin/merge-accounts.js',
	array( 'jquery' ),
	NBUF_VERSION,
	true
);

/* Get field registry for extended fields display (with custom labels) */
$nbuf_field_registry = array();
if ( class_exists( 'NBUF_Profile_Data' ) ) {
	$nbuf_registry = NBUF_Profile_Data::get_field_registry_with_labels();
	foreach ( $nbuf_registry as $nbuf_category_data ) {
		$nbuf_field_registry = array_merge( $nbuf_field_registry, $nbuf_category_data['fields'] );
	}
}

/* Localize script */
wp_localize_script(
	'nbuf-merge-accounts',
	'NBUF_Merge',
	array(
		'ajaxurl'        => admin_url( 'admin-ajax.php' ),
		'nonce'          => wp_create_nonce( 'nbuf_merge_accounts' ),
		'field_registry' => $nbuf_field_registry,
		'i18n'           => array(
			'searching'      => __( 'Searching...', 'nobloat-user-foundry' ),
			'no_results'     => __( 'No users found', 'nobloat-user-foundry' ),
			'min_chars'      => __( 'Type at least 2 characters to search', 'nobloat-user-foundry' ),
			'same_account'   => __( 'Source and target cannot be the same account', 'nobloat-user-foundry' ),
			'loading'        => __( 'Loading account data...', 'nobloat-user-foundry' ),
			'error'          => __( 'Error loading account data', 'nobloat-user-foundry' ),
			'remove'         => __( 'Remove', 'nobloat-user-foundry' ),
			'empty'          => __( '(empty)', 'nobloat-user-foundry' ),
			'source'         => __( 'Source', 'nobloat-user-foundry' ),
			'target'         => __( 'Target', 'nobloat-user-foundry' ),
			'posts'          => __( 'posts', 'nobloat-user-foundry' ),
			'comments'       => __( 'comments', 'nobloat-user-foundry' ),
			'confirm_cancel' => __( 'Are you sure you want to cancel?', 'nobloat-user-foundry' ),
			'display_name'   => __( 'Display Name', 'nobloat-user-foundry' ),
			'first_name'     => __( 'First Name', 'nobloat-user-foundry' ),
			'last_name'      => __( 'Last Name', 'nobloat-user-foundry' ),
			'nickname'       => __( 'Nickname', 'nobloat-user-foundry' ),
			'description'    => __( 'Biography', 'nobloat-user-foundry' ),
			'user_url'       => __( 'Website', 'nobloat-user-foundry' ),
		),
	)
);
?>
