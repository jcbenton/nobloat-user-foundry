<?php
/**
 * WooCommerce Integration Tab
 *
 * WooCommerce-specific settings for email verification,
 * account expiration, and customer registration hooks.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$nbuf_settings = NBUF_Options::get( 'nbuf_settings', array() );
$nbuf_hooks    = (array) ( $nbuf_settings['hooks'] ?? array() );

/* WooCommerce expiration settings */
$nbuf_wc_prevent_active_subs   = NBUF_Options::get( 'nbuf_wc_prevent_active_subs', false );
$nbuf_wc_prevent_recent_orders = NBUF_Options::get( 'nbuf_wc_prevent_recent_orders', false );
$nbuf_wc_recent_order_days     = NBUF_Options::get( 'nbuf_wc_recent_order_days', 90 );

?>

<div class="nbuf-woocommerce-tab">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="integration">
	<input type="hidden" name="nbuf_active_subtab" value="woocommerce">

		<h2><?php esc_html_e( 'Email Verification Integration', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'WooCommerce Customer Registration', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_settings[hooks][]" value="woocommerce_created_customer" <?php checked( in_array( 'woocommerce_created_customer', $nbuf_hooks, true ) ); ?>>
						<?php esc_html_e( 'Require email verification for WooCommerce customers', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, customers who register during WooCommerce checkout will need to verify their email address. Hooks into woocommerce_created_customer action.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Account Expiration Integration', 'nobloat-user-foundry' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure WooCommerce-specific rules for account expiration. These settings work in conjunction with the Expiration tab settings.', 'nobloat-user-foundry' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active Subscriptions', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_wc_prevent_active_subs" value="1" <?php checked( $nbuf_wc_prevent_active_subs, true ); ?>>
						<?php esc_html_e( 'Prevent expiration for users with active subscriptions', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, accounts with active WooCommerce subscriptions will not expire automatically. Requires WooCommerce Subscriptions plugin.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Recent Orders', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_wc_prevent_recent_orders" value="1" <?php checked( $nbuf_wc_prevent_recent_orders, true ); ?>>
						<?php esc_html_e( 'Prevent expiration for users with recent orders', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, accounts with recent WooCommerce orders will not expire automatically. Define "recent" using the threshold below.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Recent Order Threshold', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="number" name="nbuf_wc_recent_order_days" value="<?php echo esc_attr( $nbuf_wc_recent_order_days ); ?>" min="1" max="3650" class="small-text">
					<span><?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?></span>
					<p class="description">
						<?php esc_html_e( 'Accounts with orders within this many days will not expire. Default: 90 days', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save WooCommerce Settings', 'nobloat-user-foundry' ) ); ?>
	</form>

	<!-- Helper Information -->
	<div class="nbuf-wc-info" style="background: #f9f9f9; padding: 1.5rem; border-radius: 4px; margin-top: 2rem;">
		<h3><?php esc_html_e( 'WooCommerce Integration Notes', 'nobloat-user-foundry' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Email verification: Works with standard WooCommerce checkout and account registration forms.', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Active subscriptions: Requires WooCommerce Subscriptions plugin to be installed and active.', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Recent orders: Checks all WooCommerce orders regardless of status (completed, processing, etc.).', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Expiration must be enabled on the Expiration tab for these settings to have any effect.', 'nobloat-user-foundry' ); ?></li>
		</ul>
	</div>
</div>
