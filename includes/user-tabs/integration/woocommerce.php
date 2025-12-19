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

/* WooCommerce verification setting */
$nbuf_wc_require_verification = NBUF_Options::get( 'nbuf_wc_require_verification', false );

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
		<!-- Declare checkboxes on this form for proper unchecked handling -->
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_wc_require_verification">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_wc_prevent_active_subs">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_wc_prevent_recent_orders">

		<h2><?php esc_html_e( 'Email Verification Integration', 'nobloat-user-foundry' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'WooCommerce Customer Registration', 'nobloat-user-foundry' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="nbuf_wc_require_verification" value="1" <?php checked( $nbuf_wc_require_verification, true ); ?>>
						<?php esc_html_e( 'Require email verification for WooCommerce customers', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, customers who register during WooCommerce checkout will need to verify their email address.', 'nobloat-user-foundry' ); ?>
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

		<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
	</form>
</div>
<?php
/*
 * DOCUMENTATION NOTE (for AI doc generator):
 * WooCommerce Integration documentation should cover:
 * - Email verification works with standard WooCommerce checkout and account registration forms
 * - Active subscriptions protection requires WooCommerce Subscriptions plugin
 * - Recent orders check includes all order statuses (completed, processing, etc.)
 * - Expiration settings must be enabled on the Expiration tab for these protections to work
 */
