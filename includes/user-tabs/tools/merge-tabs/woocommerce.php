<?php
/**
 * Merge Accounts > WooCommerce Tab
 *
 * WooCommerce-specific account merging functionality.
 * Handles customer records, orders, subscriptions, and download permissions.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Check if WooCommerce is active */
$wc_active = class_exists( 'WooCommerce' );
?>

<div class="nbuf-woocommerce-merge">
	<?php if ( ! $wc_active ) : ?>
		<!-- WooCommerce Not Installed -->
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'WooCommerce Not Detected', 'nobloat-user-foundry' ); ?></strong><br>
		<?php esc_html_e( 'This feature requires WooCommerce to be installed and activated.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php else : ?>
		<!-- Coming Soon Notice -->
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-hammer" style="font-size: 20px; vertical-align: middle; margin-right: 5px;"></span>
				<strong><?php esc_html_e( 'WooCommerce Account Merging - Coming Soon!', 'nobloat-user-foundry' ); ?></strong>
			</p>
			<p>
		<?php esc_html_e( 'This feature is currently under development and will be available in a future update.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<h3><?php esc_html_e( 'Planned Features', 'nobloat-user-foundry' ); ?></h3>
		<ul style="list-style: disc; margin-left: 25px; line-height: 1.8;">
			<li><?php esc_html_e( 'Merge customer records from wc_customer_lookup table', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Reassign all orders to primary account', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Consolidate billing and shipping addresses', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Merge download permissions for digital products', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Combine subscription data (if WooCommerce Subscriptions is active)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Merge membership history (if WooCommerce Memberships is active)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Update customer lifetime value and purchase counts', 'nobloat-user-foundry' ); ?></li>
		</ul>

		<p style="margin-top: 20px;">
		<?php
		printf(
		/* translators: %s: URL to WordPress merge tab */
			esc_html__( 'In the meantime, you can merge WordPress core data on the %s tab.', 'nobloat-user-foundry' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=WordPress' ) ) . '">' . esc_html__( 'WordPress Accounts', 'nobloat-user-foundry' ) . '</a>'
		);
		?>
		</p>
	<?php endif; ?>
</div>
