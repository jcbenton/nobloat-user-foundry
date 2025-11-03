<?php
/**
 * Tools > Merge Accounts Tab
 *
 * Merge multiple WordPress user accounts into a single account.
 * Consolidates emails, profile data, orders, and content.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Get active merge tab
 */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation
$merge_tab  = isset( $_GET['merge_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['merge_tab'] ) ) : 'WordPress';
$valid_tabs = array( 'wordpress', 'woocommerce', 'edd' );
if ( ! in_array( $merge_tab, $valid_tabs, true ) ) {
	$merge_tab = 'WordPress';
}

/* Check if accounts were pre-selected via bulk action */
$preselected_accounts = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI pre-population parameter
if ( isset( $_GET['users'] ) && ! empty( $_GET['users'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI pre-population parameter
	$users_param          = sanitize_text_field( wp_unslash( $_GET['users'] ) );
	$preselected_accounts = array_map( 'intval', explode( ',', $users_param ) );
}
?>

<div class="nbuf-merge-accounts-wrapper">
	<h2><?php esc_html_e( 'Merge User Accounts', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Combine multiple user accounts into a single account. This will consolidate emails, profile data, posts, comments, and optionally WooCommerce orders or Easy Digital Downloads purchases.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- Merge Type Tabs -->
	<h2 class="nav-tab-wrapper" style="margin-top: 20px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=WordPress' ) ); ?>"
			class="nav-tab <?php echo ( 'WordPress' === $merge_tab ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'WordPress Accounts', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=woocommerce' ) ); ?>"
			class="nav-tab <?php echo ( 'woocommerce' === $merge_tab ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'WooCommerce', 'nobloat-user-foundry' ); ?>
			<span class="dashicons dashicons-cart" style="font-size: 16px; vertical-align: middle;"></span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=edd' ) ); ?>"
			class="nav-tab <?php echo ( 'edd' === $merge_tab ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Easy Digital Downloads', 'nobloat-user-foundry' ); ?>
			<span class="dashicons dashicons-download" style="font-size: 16px; vertical-align: middle;"></span>
		</a>
	</h2>

	<div class="nbuf-merge-tab-content" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none;">
		<?php
		/* WordPress Accounts Tab */
		if ( 'WordPress' === $merge_tab ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/wordpress.php';
			/* WooCommerce Tab */
		} elseif ( 'woocommerce' === $merge_tab ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/woocommerce.php';
			/* Easy Digital Downloads Tab */
		} elseif ( 'edd' === $merge_tab ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/edd.php';
		}
		?>
	</div>
</div>


