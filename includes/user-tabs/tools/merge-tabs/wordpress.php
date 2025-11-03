<?php
/**
 * Merge Accounts > WordPress Tab
 *
 * WordPress core account merging functionality.
 * Handles user data, posts, comments, and profile fields.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get preselected accounts from bulk action */
$preselected_accounts = array();
if ( isset( $_GET['users'] ) && ! empty( $_GET['users'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk_action_nonce' ) ) {
	$users_param          = sanitize_text_field( wp_unslash( $_GET['users'] ) );
	$preselected_accounts = array_map( 'intval', explode( ',', $users_param ) );
}

/* Get all users for selection */
$all_users = get_users(
	array(
		'orderby' => 'display_name',
		'order'   => 'ASC',
	)
);
?>

<div class="nbuf-wordpress-merge">
	<form id="nbuf-merge-form" method="post" action="">
		<?php wp_nonce_field( 'nbuf_merge_accounts', 'nbuf_merge_nonce' ); ?>

		<!-- Step 1: Select Accounts to Merge -->
		<div class="nbuf-account-selector">
			<h3>
				<span class="dashicons dashicons-admin-users" style="font-size: 20px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Step 1: Select Accounts to Merge', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Select 2 or more user accounts to merge. You will choose which account becomes the primary account in the next step.', 'nobloat-user-foundry' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="nbuf-merge-accounts"><?php esc_html_e( 'Accounts to Merge', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<select id="nbuf-merge-accounts" name="nbuf_merge_accounts[]" multiple="multiple" style="width: 100%; min-height: 200px;">
							<?php foreach ( $all_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>"
								<?php echo in_array( $user->ID, $preselected_accounts, true ) ? 'selected' : ''; ?>>
								<?php
								printf(
									'%s (%s) - ID: %d',
									esc_html( $user->display_name ),
									esc_html( $user->user_email ),
									(int) $user->ID
								);
								?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple accounts.', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" id="nbuf-load-accounts" class="button button-primary">
					<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Load Selected Accounts', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>

		<!-- Step 2: Choose Primary Account (Hidden until accounts loaded) -->
		<div id="nbuf-primary-selector" class="nbuf-account-selector" style="display: none; margin-top: 20px;">
			<h3>
				<span class="dashicons dashicons-star-filled" style="font-size: 20px; vertical-align: middle; color: #f0b849;"></span>
				<?php esc_html_e( 'Step 2: Choose Primary Account', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Select which account will be kept as the primary account. This account will retain its user ID, username, and login credentials. All other accounts will be merged into this one.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-primary-options" class="nbuf-conflict-options">
				<!-- Populated via JavaScript -->
			</div>
		</div>

		<!-- Step 3: Email Consolidation (Hidden until primary selected) -->
		<div id="nbuf-email-consolidation" class="nbuf-account-selector" style="display: none; margin-top: 20px;">
			<h3>
				<span class="dashicons dashicons-email" style="font-size: 20px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Step 3: Email Consolidation', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'All email addresses from the merged accounts will be consolidated. The primary account\'s email will remain as the login email. Additional emails will be stored in secondary and tertiary email fields.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-email-preview" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 10px;">
				<!-- Populated via JavaScript -->
			</div>
		</div>

		<!-- Step 4: Resolve Profile Data Conflicts (Hidden until emails reviewed) -->
		<div id="nbuf-conflict-resolution" class="nbuf-account-selector" style="display: none; margin-top: 20px;">
			<h3>
				<span class="dashicons dashicons-list-view" style="font-size: 20px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Step 4: Resolve Profile Data Conflicts', 'nobloat-user-foundry' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'When multiple accounts have different values for the same field, choose which value to keep. Empty values will be ignored automatically.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-conflicts-container">
				<!-- Populated via JavaScript with conflict resolution UI -->
			</div>
		</div>

		<!-- Step 5: Merge Options -->
		<div id="nbuf-merge-options" class="nbuf-account-selector" style="display: none; margin-top: 20px;">
			<h3>
				<span class="dashicons dashicons-admin-settings" style="font-size: 20px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Step 5: Merge Options', 'nobloat-user-foundry' ); ?>
			</h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Merge Content', 'nobloat-user-foundry' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="nbuf_merge_posts" value="1" checked>
							<?php esc_html_e( 'Reassign all posts and pages to primary account', 'nobloat-user-foundry' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="nbuf_merge_comments" value="1" checked>
							<?php esc_html_e( 'Reassign all comments to primary account', 'nobloat-user-foundry' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="nbuf_merge_meta" value="1" checked>
							<?php esc_html_e( 'Merge user meta data (non-conflicting)', 'nobloat-user-foundry' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'After Merge', 'nobloat-user-foundry' ); ?></th>
					<td>
						<label>
							<input type="radio" name="nbuf_secondary_action" value="delete" checked>
							<?php esc_html_e( 'Delete secondary accounts permanently', 'nobloat-user-foundry' ); ?>
						</label><br>
						<label>
							<input type="radio" name="nbuf_secondary_action" value="disable">
							<?php esc_html_e( 'Disable secondary accounts (keep in database)', 'nobloat-user-foundry' ); ?>
						</label><br>
						<p class="description">
							<?php esc_html_e( 'Recommended: Delete permanently. Disabled accounts cannot be used for login but remain in the database.', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notifications', 'nobloat-user-foundry' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="nbuf_notify_user" value="1">
							<?php esc_html_e( 'Send email notification to all merged email addresses', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Notify the user that their accounts have been merged and provide the new login details.', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Step 6: Review and Execute -->
		<div id="nbuf-merge-execute" class="nbuf-account-selector" style="display: none; margin-top: 20px;">
			<h3>
				<span class="dashicons dashicons-yes-alt" style="font-size: 20px; vertical-align: middle; color: #00a32a;"></span>
				<?php esc_html_e( 'Step 6: Review and Execute Merge', 'nobloat-user-foundry' ); ?>
			</h3>

			<div class="nbuf-merge-warning">
				<p>
					<span class="dashicons dashicons-warning" style="font-size: 20px; vertical-align: middle;"></span>
					<strong><?php esc_html_e( 'Warning: This action cannot be easily undone!', 'nobloat-user-foundry' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'Once you merge these accounts, the secondary accounts will be deleted or disabled, and all their data will be transferred to the primary account. Please review your selections carefully before proceeding.', 'nobloat-user-foundry' ); ?>
				</p>
				<p style="margin-top: 10px;">
					<strong><?php esc_html_e( 'Important:', 'nobloat-user-foundry' ); ?></strong>
					<?php esc_html_e( 'Do not attempt to merge the same accounts from multiple browser windows or by multiple administrators simultaneously. This could result in data corruption or conflicts. Ensure only one merge operation is performed at a time.', 'nobloat-user-foundry' ); ?>
				</p>
			</div>

			<div id="nbuf-merge-summary" class="nbuf-merge-preview">
				<!-- Populated via JavaScript with merge summary -->
			</div>

			<p>
				<label style="font-weight: 600;">
					<input type="checkbox" id="nbuf-confirm-merge" name="nbuf_confirm_merge" value="1">
					<?php esc_html_e( 'I understand that this action cannot be easily undone and I have reviewed all merge details.', 'nobloat-user-foundry' ); ?>
				</label>
			</p>

			<p>
				<button type="submit" id="nbuf-execute-merge" class="button button-primary button-hero" disabled>
					<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Execute Account Merge', 'nobloat-user-foundry' ); ?>
				</button>
				<button type="button" id="nbuf-cancel-merge" class="button button-secondary button-hero" style="margin-left: 10px;">
					<span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>
	</form>
</div>

<?php
/* Enqueue merge accounts JavaScript */

/* Check if minified version exists, otherwise use regular version */
$script_file = file_exists( NBUF_PLUGIN_DIR . 'assets/js/admin/merge-accounts.min.js' )
	? 'assets/js/admin/merge-accounts.min.js'
	: 'assets/js/admin/merge-accounts.js';

wp_enqueue_script(
	'nbuf-merge-accounts',
	NBUF_PLUGIN_URL . $script_file,
	array( 'jquery' ),
	NBUF_VERSION,
	true
);

/* Localize script with dynamic data and i18n strings */
wp_localize_script(
	'nbuf-merge-accounts',
	'NBUF_Merge',
	array(
		'ajaxurl'     => admin_url( 'admin-ajax.php' ),
		'nonce'       => wp_create_nonce( 'nbuf_merge_load' ),
		'preselected' => $preselected_accounts,
		'i18n'        => array(
			'minimum_users'        => __( 'Please select at least 2 accounts to merge.', 'nobloat-user-foundry' ),
			'loading'              => __( 'Loading...', 'nobloat-user-foundry' ),
			'load_accounts'        => __( 'Load Selected Accounts', 'nobloat-user-foundry' ),
			'error_loading'        => __( 'Error loading accounts', 'nobloat-user-foundry' ),
			'email'                => __( 'Email', 'nobloat-user-foundry' ),
			'username'             => __( 'Username', 'nobloat-user-foundry' ),
			'user_id'              => __( 'User ID', 'nobloat-user-foundry' ),
			'posts'                => __( 'Posts', 'nobloat-user-foundry' ),
			'comments'             => __( 'Comments', 'nobloat-user-foundry' ),
			'primary_email'        => __( 'Primary Email', 'nobloat-user-foundry' ),
			'secondary_email'      => __( 'Secondary Email', 'nobloat-user-foundry' ),
			'tertiary_email'       => __( 'Tertiary Email', 'nobloat-user-foundry' ),
			'none'                 => __( '(none)', 'nobloat-user-foundry' ),
			'email_limit_warning'  => __( 'Warning: Only 3 email addresses can be stored. Additional emails will not be saved.', 'nobloat-user-foundry' ),
			'no_conflicts'         => __( 'No conflicts found! All profile data can be merged automatically.', 'nobloat-user-foundry' ),
			'conflict_instruction' => __( 'The following fields have different values across accounts. Please select which value to keep:', 'nobloat-user-foundry' ),
			'confirm_cancel'       => __( 'Are you sure you want to cancel? All selections will be lost.', 'nobloat-user-foundry' ),
		),
	)
);
?>
