<?php
/**
 * Expiration Tab
 *
 * Account expiration settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Load current expiration values */
$enable_expiration = NBUF_Options::get( 'nbuf_enable_expiration', false );
$warning_days      = NBUF_Options::get( 'nbuf_expiration_warning_days', 7 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php NBUF_Settings::settings_nonce_field(); ?>
	<input type="hidden" name="nbuf_active_tab" value="users">
	<input type="hidden" name="nbuf_active_subtab" value="expiration">

	<h2><?php esc_html_e( 'Account Expiry Settings', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure account expiration functionality. When enabled, you can set expiration dates for user accounts. Expired accounts are automatically disabled and users cannot log in.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Enable Expiration Feature -->
		<tr>
			<th scope="row">
				<label for="enable_expiration"><?php esc_html_e( 'Enable Account Expiration', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="enable_expiration" name="nbuf_enable_expiration" value="1" <?php checked( $enable_expiration, 1 ); ?>>
					<?php esc_html_e( 'Enable expiration feature', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, you can set expiration dates on user profiles and the "Expires" column will appear in the users list.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Warning Days -->
		<tr>
			<th scope="row">
				<label for="expiration_warning_days"><?php esc_html_e( 'Warning Days', 'nobloat-user-foundry' ); ?></label>
			</th>
			<td>
				<input type="number" id="expiration_warning_days" name="nbuf_expiration_warning_days" value="<?php echo esc_attr( $warning_days ); ?>" min="1" max="90" class="small-text">
				<?php esc_html_e( 'days before expiration', 'nobloat-user-foundry' ); ?>
				<p class="description">
					<?php esc_html_e( 'Send a warning email this many days before the account expires. Default: 7 days.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="nbuf-info-box" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 15px; margin: 20px 0;">
		<p style="margin: 0;">
			<strong><?php esc_html_e( 'Email Templates:', 'nobloat-user-foundry' ); ?></strong>
			<?php
			printf(
				/* translators: %s: link to email templates */
				esc_html__( 'Customize expiration warning and notification emails in %s.', 'nobloat-user-foundry' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-appearance&tab=templates&subtab=expiration' ) ) . '">' . esc_html__( 'Appearance → Email Templates → Expiration', 'nobloat-user-foundry' ) . '</a>'
			);
			?>
		</p>
	</div>

	<?php submit_button( __( 'Save Settings', 'nobloat-user-foundry' ) ); ?>
</form>


