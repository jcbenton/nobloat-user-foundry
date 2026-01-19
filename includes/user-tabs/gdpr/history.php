<?php
/**
 * GDPR > History Tab
 *
 * Configure profile change tracking, timeline viewing, diff comparison, and revert capabilities.
 * Supports GDPR compliance through audit trails and data subject access requests.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/GDPR
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Security: Verify user has permission to access this page */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
}

/* Get current settings */
$nbuf_enabled           = NBUF_Options::get( 'nbuf_version_history_enabled', true );
$nbuf_user_visible      = NBUF_Options::get( 'nbuf_version_history_user_visible', false );
$nbuf_allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
$nbuf_retention_days    = NBUF_Options::get( 'nbuf_version_history_retention_days', 365 );
$nbuf_max_versions      = NBUF_Options::get( 'nbuf_version_history_max_versions', 50 );
$nbuf_ip_tracking       = NBUF_Options::get( 'nbuf_version_history_ip_tracking', 'anonymized' );
$nbuf_auto_cleanup      = NBUF_Options::get( 'nbuf_version_history_auto_cleanup', true );

?>

<div class="nbuf-version-history-tab">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php NBUF_Settings::settings_nonce_field(); ?>
		<input type="hidden" name="nbuf_active_tab" value="gdpr">
		<input type="hidden" name="nbuf_active_subtab" value="history">
		<!-- Declare checkboxes on this form for proper unchecked handling -->
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_version_history_enabled">
		<?php if ( $nbuf_enabled ) : ?>
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_version_history_user_visible">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_version_history_allow_user_revert">
		<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_version_history_auto_cleanup">
		<?php endif; ?>

		<h2><?php esc_html_e( 'Profile Version History', 'nobloat-user-foundry' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Track all user profile changes with complete snapshots for audit trails, timeline viewing, diff comparison, and revert capability. Enabled by default for comprehensive user management.', 'nobloat-user-foundry' ); ?>
		</p>

		<table class="form-table">

			<!-- Master Toggle -->
			<tr>
				<th><?php esc_html_e( 'Enable Version History', 'nobloat-user-foundry' ); ?></th>
				<td>
					<input type="hidden" name="nbuf_version_history_enabled" value="0">
					<label>
						<input type="checkbox" name="nbuf_version_history_enabled" value="1" <?php checked( $nbuf_enabled, true ); ?>>
						<?php esc_html_e( 'Track all profile changes with version history', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Master toggle for version history system. When disabled, no profile changes are tracked and all history features are hidden. Enabled by default.', 'nobloat-user-foundry' ); ?>
					</p>

					<?php if ( $nbuf_enabled ) : ?>
						<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin-top: 10px;">
							<strong style="color: #155724;">✅ <?php esc_html_e( 'Version History Enabled', 'nobloat-user-foundry' ); ?></strong>
							<p style="margin: 5px 0 0 0; color: #155724;">
						<?php esc_html_e( 'All profile changes are being tracked. Users and admins can view timeline history, compare versions, and revert changes based on your settings below.', 'nobloat-user-foundry' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 10px;">
							<strong style="color: #856404;">⚠️ <?php esc_html_e( 'Version History Disabled', 'nobloat-user-foundry' ); ?></strong>
							<p style="margin: 5px 0 0 0; color: #856404;">
						<?php esc_html_e( 'Profile changes are not being tracked. Enable this feature to maintain audit trails and allow version comparison/revert.', 'nobloat-user-foundry' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</td>
			</tr>

			<?php if ( $nbuf_enabled ) : ?>

				<!-- User Visibility -->
				<tr>
					<th><?php esc_html_e( 'User Access', 'nobloat-user-foundry' ); ?></th>
					<td>
						<input type="hidden" name="nbuf_version_history_user_visible" value="0">
						<label>
							<input type="checkbox" name="nbuf_version_history_user_visible" value="1" <?php checked( $nbuf_user_visible, true ); ?>>
				<?php esc_html_e( 'Show "History" tab in user account page', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
				<?php esc_html_e( 'When enabled, users can see their profile change history in the "History" tab on their account page. When disabled, only admins can view version history. Default: OFF (admin-only).', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>

				<!-- User Revert Permission -->
				<tr>
					<th><?php esc_html_e( 'User Revert Permission', 'nobloat-user-foundry' ); ?></th>
					<td>
						<input type="hidden" name="nbuf_version_history_allow_user_revert" value="0">
						<label>
							<input type="checkbox" name="nbuf_version_history_allow_user_revert" value="1" <?php checked( $nbuf_allow_user_revert, true ); ?>>
				<?php esc_html_e( 'Allow users to revert their own profiles to previous versions', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
				<?php esc_html_e( 'When enabled, users can restore their profile to any previous version. When disabled, only admins can revert profiles. Default: OFF (admin-only).', 'nobloat-user-foundry' ); ?>
						</p>

				<?php if ( ! $nbuf_allow_user_revert ) : ?>
							<div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin-top: 10px;">
								<strong style="color: #0c5460;">ℹ️ <?php esc_html_e( 'Admin-Only Revert', 'nobloat-user-foundry' ); ?></strong>
								<p style="margin: 5px 0 0 0; color: #0c5460;">
					<?php esc_html_e( 'Currently, only administrators can revert user profiles. Users can view their history but cannot restore previous versions.', 'nobloat-user-foundry' ); ?>
								</p>
							</div>
				<?php endif; ?>
					</td>
				</tr>

				<!-- Retention Period -->
				<tr>
					<th><?php esc_html_e( 'Retention Period', 'nobloat-user-foundry' ); ?></th>
					<td>
						<input type="number" name="nbuf_version_history_retention_days" value="<?php echo esc_attr( $nbuf_retention_days ); ?>" min="1" max="3650" class="small-text">
				<?php esc_html_e( 'days', 'nobloat-user-foundry' ); ?>
						<p class="description">
				<?php esc_html_e( 'Automatically delete version history older than this many days. Recommended: 365 days (1 year). Maximum: 3650 days (10 years).', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>

				<!-- Max Versions Per User -->
				<tr>
					<th><?php esc_html_e( 'Maximum Versions Per User', 'nobloat-user-foundry' ); ?></th>
					<td>
						<input type="number" name="nbuf_version_history_max_versions" value="<?php echo esc_attr( $nbuf_max_versions ); ?>" min="10" max="500" class="small-text">
				<?php esc_html_e( 'versions', 'nobloat-user-foundry' ); ?>
						<p class="description">
				<?php esc_html_e( 'Maximum number of versions to keep per user. When exceeded, oldest versions are deleted. Recommended: 50 versions. Range: 10-500.', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>

				<!-- IP Address Tracking -->
				<tr>
					<th><?php esc_html_e( 'IP Address Tracking', 'nobloat-user-foundry' ); ?></th>
					<td>
						<select name="nbuf_version_history_ip_tracking" class="regular-text">
							<option value="off" <?php selected( $nbuf_ip_tracking, 'off' ); ?>>
								<?php esc_html_e( 'Off - Do not track IP addresses', 'nobloat-user-foundry' ); ?>
							</option>
							<option value="anonymized" <?php selected( $nbuf_ip_tracking, 'anonymized' ); ?>>
								<?php esc_html_e( 'Anonymized - Track anonymized IPs (last octet removed)', 'nobloat-user-foundry' ); ?>
							</option>
							<option value="on" <?php selected( $nbuf_ip_tracking, 'on' ); ?>>
								<?php esc_html_e( 'On - Track full IP addresses', 'nobloat-user-foundry' ); ?>
							</option>
						</select>
						<p class="description">
				<?php esc_html_e( 'Choose whether to track IP addresses when profile changes occur. Default: Anonymized (balances privacy and security).', 'nobloat-user-foundry' ); ?>
						</p>

						<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 10px;">
							<strong style="color: #856404;">⚠️ <?php esc_html_e( 'GDPR Compliance Note:', 'nobloat-user-foundry' ); ?></strong>
							<p style="margin: 5px 0 0 0; color: #856404;">
								<?php esc_html_e( 'IP addresses are considered personal data under GDPR. Consider your data protection requirements:', 'nobloat-user-foundry' ); ?>
							</p>
							<ul style="margin: 10px 0 0 20px; color: #856404;">
								<li><strong><?php esc_html_e( 'Off:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'No IP addresses stored (most privacy-friendly, recommended for EU/GDPR)', 'nobloat-user-foundry' ); ?></li>
								<li><strong><?php esc_html_e( 'Anonymized:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Last octet removed (192.168.1.XXX) - balances privacy and security', 'nobloat-user-foundry' ); ?></li>
								<li><strong><?php esc_html_e( 'On:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Full IP addresses - requires explicit consent and data protection measures', 'nobloat-user-foundry' ); ?></li>
							</ul>
						</div>
					</td>
				</tr>

				<!-- Auto-Cleanup -->
				<tr>
					<th><?php esc_html_e( 'Automatic Cleanup', 'nobloat-user-foundry' ); ?></th>
					<td>
						<input type="hidden" name="nbuf_version_history_auto_cleanup" value="0">
						<label>
							<input type="checkbox" name="nbuf_version_history_auto_cleanup" value="1" <?php checked( $nbuf_auto_cleanup, true ); ?>>
				<?php esc_html_e( 'Automatically delete old versions based on retention period', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description">
				<?php esc_html_e( 'When enabled, a daily cleanup task removes versions older than your retention period. Recommended: ON to prevent database bloat.', 'nobloat-user-foundry' ); ?>
						</p>
					</td>
				</tr>

			<?php endif; ?>

		</table>

		<?php submit_button(); ?>
	</form>
</div>


