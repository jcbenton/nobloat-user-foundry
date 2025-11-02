<?php
/**
 * Merge Accounts > Easy Digital Downloads Tab
 *
 * EDD-specific account merging functionality.
 * Handles customer records, email addresses, orders, and download permissions.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Check if EDD is active */
$edd_active = class_exists( 'Easy_Digital_Downloads' );
?>

<div class="nbuf-edd-merge">
	<?php if ( ! $edd_active ) : ?>
		<!-- EDD Not Installed -->
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Easy Digital Downloads Not Detected', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'This feature requires Easy Digital Downloads to be installed and activated.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php else : ?>
		<!-- Coming Soon Notice -->
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-hammer" style="font-size: 20px; vertical-align: middle; margin-right: 5px;"></span>
				<strong><?php esc_html_e( 'Easy Digital Downloads Account Merging - Coming Soon!', 'nobloat-user-foundry' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'This feature is currently under development and will be available in a future update.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<h3><?php esc_html_e( 'Planned Features', 'nobloat-user-foundry' ); ?></h3>
		<ul style="list-style: disc; margin-left: 25px; line-height: 1.8;">
			<li><?php esc_html_e( 'Merge customer records from edd_customers table', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Consolidate email addresses using edd_customer_email_addresses table', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Merge customer address records', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Reassign all orders/payments to primary account', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Combine download permissions and file access limits', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Merge software licensing data (if EDD Software Licensing is active)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Update customer lifetime value and purchase counts', 'nobloat-user-foundry' ); ?></li>
		</ul>

		<div class="nbuf-merge-success" style="margin-top: 20px;">
			<p>
				<span class="dashicons dashicons-info" style="font-size: 18px; vertical-align: middle;"></span>
				<strong><?php esc_html_e( 'Good News!', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Easy Digital Downloads has excellent multi-email support built-in, which makes account merging much cleaner than WooCommerce. This feature will be able to properly consolidate all customer email addresses into the EDD email management system.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<p style="margin-top: 20px;">
			<?php
			printf(
				/* translators: %s: URL to WordPress merge tab */
				esc_html__( 'In the meantime, you can merge WordPress core data on the %s tab.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=wordpress' ) ) . '">' . esc_html__( 'WordPress Accounts', 'nobloat-user-foundry' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>
</div>
