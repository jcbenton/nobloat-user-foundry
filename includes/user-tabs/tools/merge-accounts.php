<?php
/**
 * Tools > Merge Accounts Tab
 *
 * Merge multiple WordPress user accounts into a single account.
 * Consolidates emails, profile data, posts, and comments.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Check if accounts were pre-selected via bulk action */
$nbuf_preselected_accounts = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI pre-population parameter
if ( isset( $_GET['users'] ) && ! empty( $_GET['users'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI pre-population parameter
	$nbuf_users_param          = sanitize_text_field( wp_unslash( $_GET['users'] ) );
	$nbuf_preselected_accounts = array_map( 'intval', explode( ',', $nbuf_users_param ) );
}
?>

<div class="nbuf-merge-accounts-wrapper">
	<h2><?php esc_html_e( 'Merge User Accounts', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Combine multiple user accounts into a single account. This will consolidate emails, profile data, posts, and comments.', 'nobloat-user-foundry' ); ?>
	</p>

	<div class="nbuf-merge-content" style="margin-top: 20px;">
		<?php include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/wordpress.php'; ?>
	</div>
</div>
