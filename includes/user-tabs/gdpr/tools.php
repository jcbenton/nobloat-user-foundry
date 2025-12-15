<?php
/**
 * GDPR > Tools Tab
 *
 * Privacy tools for data export and erasure requests.
 * Integrates with WordPress core privacy features.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/GDPR
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Privacy Tools', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Export or erase personal data for a specific user. These tools integrate with WordPress core privacy features.', 'nobloat-user-foundry' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row">
			<?php esc_html_e( 'Export Personal Data', 'nobloat-user-foundry' ); ?>
		</th>
		<td>
			<a href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>" class="button">
				<?php esc_html_e( 'Export Personal Data', 'nobloat-user-foundry' ); ?>
			</a>
			<p class="description">
				<?php esc_html_e( 'Generate a downloadable file containing all personal data for a user.', 'nobloat-user-foundry' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<?php esc_html_e( 'Erase Personal Data', 'nobloat-user-foundry' ); ?>
		</th>
		<td>
			<a href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>" class="button">
				<?php esc_html_e( 'Erase Personal Data', 'nobloat-user-foundry' ); ?>
			</a>
			<p class="description">
				<?php esc_html_e( 'Anonymize or delete all personal data for a user.', 'nobloat-user-foundry' ); ?>
			</p>
		</td>
	</tr>
</table>
