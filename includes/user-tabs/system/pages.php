<?php
/**
 * System > Pages Tab
 *
 * Page assignments for plugin functionality.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Plugin page IDs */
$page_verification  = NBUF_Options::get( 'nbuf_page_verification', 0 );
$page_reset         = NBUF_Options::get( 'nbuf_page_password_reset', 0 );
$page_request_reset = NBUF_Options::get( 'nbuf_page_request_reset', 0 );
$page_login         = NBUF_Options::get( 'nbuf_page_login', 0 );
$page_registration  = NBUF_Options::get( 'nbuf_page_registration', 0 );
$page_account       = NBUF_Options::get( 'nbuf_page_account', 0 );
$page_profile       = NBUF_Options::get( 'nbuf_page_profile', 0 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="system">
	<input type="hidden" name="nbuf_active_subtab" value="pages">

	<h2><?php esc_html_e( 'Plugin Pages', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'These pages are automatically created during plugin activation. You can reassign them if needed.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Verification Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_verification',
						'selected'          => absint( $page_verification ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_verify_page] shortcode. Auto-created as "NoBloat Verification" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Password Reset Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_password_reset',
						'selected'          => absint( $page_reset ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_reset_form] shortcode. Auto-created as "NoBloat Password Reset" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Request Password Reset Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_request_reset',
						'selected'          => absint( $page_request_reset ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_request_reset_form] shortcode. Auto-created as "NoBloat Request Password Reset" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Login Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_login',
						'selected'          => absint( $page_login ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_login_form] shortcode. Auto-created as "NoBloat Login" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Registration Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_registration',
						'selected'          => absint( $page_registration ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_registration_form] shortcode. Auto-created as "NoBloat Register" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Account Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_account',
						'selected'          => absint( $page_account ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_account_page] shortcode. Auto-created as "NoBloat User Account" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Public Profile Page', 'nobloat-user-foundry' ); ?></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'nbuf_page_profile',
						'selected'          => absint( $page_profile ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nobloat-user-foundry' ),
						'option_none_value' => 0,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Page must contain [nbuf_profile] shortcode. Auto-created as "NoBloat Profile" during activation.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>
