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

/* Get active merge tab */
$merge_tab = isset( $_GET['merge_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['merge_tab'] ) ) : 'wordpress';
$valid_tabs = array( 'wordpress', 'woocommerce', 'edd' );
if ( ! in_array( $merge_tab, $valid_tabs, true ) ) {
	$merge_tab = 'wordpress';
}

/* Check if accounts were pre-selected via bulk action */
$preselected_accounts = array();
if ( isset( $_GET['users'] ) && ! empty( $_GET['users'] ) ) {
	$users_param = sanitize_text_field( wp_unslash( $_GET['users'] ) );
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
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=wordpress' ) ); ?>"
		   class="nav-tab <?php echo ( $merge_tab === 'wordpress' ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'WordPress Accounts', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=woocommerce' ) ); ?>"
		   class="nav-tab <?php echo ( $merge_tab === 'woocommerce' ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'WooCommerce', 'nobloat-user-foundry' ); ?>
			<span class="dashicons dashicons-cart" style="font-size: 16px; vertical-align: middle;"></span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=tools&subtab=merge-accounts&merge_tab=edd' ) ); ?>"
		   class="nav-tab <?php echo ( $merge_tab === 'edd' ) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Easy Digital Downloads', 'nobloat-user-foundry' ); ?>
			<span class="dashicons dashicons-download" style="font-size: 16px; vertical-align: middle;"></span>
		</a>
	</h2>

	<div class="nbuf-merge-tab-content" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none;">
		<?php
		/* WordPress Accounts Tab */
		if ( $merge_tab === 'wordpress' ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/wordpress.php';
		}

		/* WooCommerce Tab */
		elseif ( $merge_tab === 'woocommerce' ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/woocommerce.php';
		}

		/* Easy Digital Downloads Tab */
		elseif ( $merge_tab === 'edd' ) {
			include NBUF_INCLUDE_DIR . 'user-tabs/tools/merge-tabs/edd.php';
		}
		?>
	</div>
</div>

<style>
.nbuf-merge-accounts-wrapper {
	max-width: 1200px;
}

.nbuf-merge-tab-content {
	margin-bottom: 20px;
}

.nbuf-account-selector {
	background: #f9f9f9;
	padding: 15px;
	margin: 15px 0;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.nbuf-conflict-resolution {
	margin-top: 20px;
}

.nbuf-conflict-item {
	background: #fff;
	padding: 15px;
	margin: 10px 0;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.nbuf-conflict-options {
	display: flex;
	gap: 20px;
	margin-top: 10px;
}

.nbuf-conflict-option {
	flex: 1;
	padding: 10px;
	background: #f0f0f1;
	border: 2px solid transparent;
	border-radius: 4px;
	cursor: pointer;
	transition: all 0.2s;
}

.nbuf-conflict-option:hover {
	background: #e0e0e1;
}

.nbuf-conflict-option.selected {
	border-color: #2271b1;
	background: #f0f6fc;
}

.nbuf-merge-preview {
	background: #f0f6fc;
	padding: 15px;
	margin: 20px 0;
	border-left: 4px solid #2271b1;
}

.nbuf-merge-warning {
	background: #fcf0f1;
	padding: 15px;
	margin: 20px 0;
	border-left: 4px solid #d63638;
}

.nbuf-merge-success {
	background: #edfaef;
	padding: 15px;
	margin: 20px 0;
	border-left: 4px solid #00a32a;
}
</style>
