<?php
/**
 * Account Data Export Template
 *
 * Displays GDPR data export UI on user account page.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage Templates
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Check if feature is enabled */
if ( ! NBUF_GDPR_Export::is_enabled() ) {
	return;
}

/* Check if user is logged in */
if ( ! is_user_logged_in() ) {
	return;
}

$nbuf_user_id    = get_current_user_id();
$nbuf_rate_check = NBUF_GDPR_Export::check_rate_limit( $nbuf_user_id );
$nbuf_counts     = NBUF_GDPR_Export::get_data_counts( $nbuf_user_id );

$nbuf_last_export = get_user_meta( $nbuf_user_id, 'nbuf_last_data_export', true );
?>

<div class="nbuf-gdpr-export-container" style="margin: 30px 0; padding: 25px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
	<h2 style="margin-top: 0; color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
		<?php echo esc_html_x( '📥 Download Your Personal Data', 'GDPR export section title', 'nobloat-user-foundry' ); ?>
	</h2>

	<div style="background: white; padding: 20px; border-radius: 3px; margin-bottom: 20px;">
		<p>
			<?php esc_html_e( 'Under GDPR Article 15 (Right of Access), you have the right to receive a copy of your personal data we store.', 'nobloat-user-foundry' ); ?>
		</p>

		<h3><?php esc_html_e( 'This export includes:', 'nobloat-user-foundry' ); ?></h3>
		<ul style="list-style: disc; margin-left: 25px;">
			<li><?php esc_html_e( 'Profile information (name, email, custom fields)', 'nobloat-user-foundry' ); ?></li>
			<li><?php esc_html_e( 'Account verification status and history', 'nobloat-user-foundry' ); ?></li>
			<?php if ( NBUF_Options::get( 'nbuf_gdpr_include_woo', false ) && function_exists( 'wc_get_orders' ) ) : ?>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of orders */
							__( 'WooCommerce orders and addresses (%d orders)', 'nobloat-user-foundry' ),
							$nbuf_counts['woo_orders']
						)
					);
					?>
				</li>
			<?php endif; ?>
			<?php if ( NBUF_Options::get( 'nbuf_gdpr_include_edd', false ) && function_exists( 'edd_get_users_purchases' ) ) : ?>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of purchases */
							__( 'Easy Digital Downloads purchases (%d purchases)', 'nobloat-user-foundry' ),
							$nbuf_counts['edd_purchases']
						)
					);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<p>
			<strong><?php esc_html_e( 'Estimated file size:', 'nobloat-user-foundry' ); ?></strong>
			<?php echo esc_html( size_format( $nbuf_counts['estimated_size'] ) ); ?>
			<br>
			<strong><?php esc_html_e( 'Format:', 'nobloat-user-foundry' ); ?></strong>
			<?php esc_html_e( 'ZIP archive (JSON + HTML)', 'nobloat-user-foundry' ); ?>
		</p>

		<div style="text-align: center; margin: 25px 0;">
			<?php if ( $nbuf_rate_check['can_export'] ) : ?>
				<button type="button" id="nbuf-request-export" class="button button-primary button-large">
					<?php esc_html_e( 'Download My Data', 'nobloat-user-foundry' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="button button-large" disabled>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: minutes to wait */
							__( 'Available in %d minutes', 'nobloat-user-foundry' ),
							$nbuf_rate_check['wait_minutes']
						)
					);
					?>
				</button>
			<?php endif; ?>
		</div>

		<div id="nbuf-export-messages" style="margin-top: 15px;"></div>
	</div>

	<div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa; font-size: 0.9em;">
		<h4 style="margin-top: 0;"><?php esc_html_e( '📊 Export History:', 'nobloat-user-foundry' ); ?></h4>
		<?php if ( $nbuf_last_export ) : ?>
			<p>
				<strong><?php esc_html_e( 'Last exported:', 'nobloat-user-foundry' ); ?></strong>
				<?php echo esc_html( human_time_diff( $nbuf_last_export, time() ) . ' ' . __( 'ago', 'nobloat-user-foundry' ) ); ?>
				<br>
				<?php if ( ! $nbuf_rate_check['can_export'] ) : ?>
					<strong><?php esc_html_e( 'Next available:', 'nobloat-user-foundry' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: minutes to wait */
							__( 'in %d minutes', 'nobloat-user-foundry' ),
							$nbuf_rate_check['wait_minutes']
						)
					);
					?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Next available:', 'nobloat-user-foundry' ); ?></strong>
					<?php esc_html_e( 'Now', 'nobloat-user-foundry' ); ?>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'You have not exported your data yet.', 'nobloat-user-foundry' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<!-- Password Confirmation Modal -->
<div id="nbuf-password-modal" style="display: none;">
	<div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;">
		<div style="background: white; padding: 30px; border-radius: 5px; max-width: 400px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
			<h3 style="margin-top: 0; color: #0073aa;">
				<?php echo esc_html_x( '🔐 Confirm Your Password', 'Password modal title', 'nobloat-user-foundry' ); ?>
			</h3>
			<p><?php esc_html_e( 'For security, please confirm your password to download your personal data.', 'nobloat-user-foundry' ); ?></p>
			<p>
				<label for="nbuf-export-password" style="display: block; margin-bottom: 5px; font-weight: bold;">
					<?php esc_html_e( 'Password:', 'nobloat-user-foundry' ); ?>
				</label>
				<input type="password" id="nbuf-export-password" class="regular-text" style="width: 100%;" />
			</p>
			<div id="nbuf-password-error" style="color: #d63301; margin-bottom: 15px; display: none;"></div>
			<p style="text-align: right; margin-bottom: 0;">
				<button type="button" id="nbuf-cancel-export" class="button">
					<?php esc_html_e( 'Cancel', 'nobloat-user-foundry' ); ?>
				</button>
				<button type="button" id="nbuf-confirm-export" class="button button-primary">
					<?php esc_html_e( 'Confirm & Download', 'nobloat-user-foundry' ); ?>
				</button>
			</p>
		</div>
	</div>
</div>

<style>
	.nbuf-export-notice {
		padding: 10px 15px;
		border-left: 4px solid;
		margin: 15px 0;
		border-radius: 3px;
	}
	.nbuf-export-notice.success {
		background: #d4edda;
		border-color: #28a745;
		color: #155724;
	}
	.nbuf-export-notice.error {
		background: #f8d7da;
		border-color: #dc3545;
		color: #721c24;
	}
	.nbuf-export-notice.info {
		background: #d1ecf1;
		border-color: #17a2b8;
		color: #0c5460;
	}
	.nbuf-export-spinner {
		display: inline-block;
		width: 16px;
		height: 16px;
		border: 2px solid #f3f3f3;
		border-top: 2px solid #0073aa;
		border-radius: 50%;
		animation: nbuf-spin 1s linear infinite;
		margin-right: 8px;
		vertical-align: middle;
	}
	@keyframes nbuf-spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}
</style>
