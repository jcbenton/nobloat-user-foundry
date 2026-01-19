<?php
/**
 * Security > Registration Protection Tab
 *
 * Anti-bot protection settings for registration forms.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$nbuf_enabled          = NBUF_Options::get( 'nbuf_antibot_enabled', true );
$nbuf_honeypot_enabled = NBUF_Options::get( 'nbuf_antibot_honeypot', true );
$nbuf_time_check       = NBUF_Options::get( 'nbuf_antibot_time_check', true );
$nbuf_min_time         = NBUF_Options::get( 'nbuf_antibot_min_time', 3 );
$nbuf_js_token         = NBUF_Options::get( 'nbuf_antibot_js_token', true );
$nbuf_interaction      = NBUF_Options::get( 'nbuf_antibot_interaction', true );
$nbuf_min_interactions = NBUF_Options::get( 'nbuf_antibot_min_interactions', 3 );
$nbuf_pow_enabled      = NBUF_Options::get( 'nbuf_antibot_pow', true );
$nbuf_pow_difficulty   = NBUF_Options::get( 'nbuf_antibot_pow_difficulty', 'medium' );

/* Statistics - count blocked attempts in last 30 days */
$nbuf_blocked_count = 0;
if ( class_exists( 'NBUF_Security_Log' ) ) {
	global $wpdb;
	$nbuf_table_name = $wpdb->prefix . 'nbuf_security_log';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom security log table statistics.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nbuf_table_name ) ) === $nbuf_table_name ) {
		$nbuf_date_from     = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$nbuf_blocked_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(occurrence_count), 0) FROM %i WHERE event_type = %s AND timestamp >= %s',
				$nbuf_table_name,
				'registration_bot_blocked',
				$nbuf_date_from
			)
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_security' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="security">
	<input type="hidden" name="nbuf_active_subtab" value="registration-protection">
	<!-- Declare checkboxes so unchecked state is saved -->
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_enabled">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_honeypot">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_time_check">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_js_token">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_interaction">
	<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_antibot_pow">

	<h2><?php esc_html_e( 'Registration Anti-Bot Protection', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Protect your registration forms from automated bot submissions using multiple detection techniques. All checks run silently without affecting legitimate users.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Enable Anti-Bot Protection', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_enabled" value="1" <?php checked( $nbuf_enabled, true ); ?>>
					<?php esc_html_e( 'Enable anti-bot protection for registration forms', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Master toggle for all registration anti-bot measures below.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Detection Methods', 'nobloat-user-foundry' ); ?></h3>

	<table class="form-table">
		<!-- Honeypot -->
		<tr>
			<th><?php esc_html_e( 'Dynamic Honeypot Fields', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_honeypot" value="1" <?php checked( $nbuf_honeypot_enabled, true ); ?>>
					<?php esc_html_e( 'Add invisible honeypot fields', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Adds hidden form fields that bots typically fill out but humans cannot see. Field names rotate hourly to avoid pattern detection. Uses CSS off-screen positioning instead of display:none for better bot detection.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Time Check -->
		<tr>
			<th><?php esc_html_e( 'Minimum Time Check', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_time_check" value="1" <?php checked( $nbuf_time_check, true ); ?>>
					<?php esc_html_e( 'Require minimum time before submission', 'nobloat-user-foundry' ); ?>
				</label>
				<br><br>
				<input type="number" name="nbuf_antibot_min_time" value="<?php echo esc_attr( $nbuf_min_time ); ?>" min="1" max="30" class="small-text">
				<span><?php esc_html_e( 'seconds minimum', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Reject forms submitted faster than this threshold. Real users need time to read and fill the form, while bots submit instantly. Default: 3 seconds.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- JavaScript Token -->
		<tr>
			<th><?php esc_html_e( 'JavaScript Token Validation', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_js_token" value="1" <?php checked( $nbuf_js_token, true ); ?>>
					<?php esc_html_e( 'Require JavaScript-generated token', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Generates a cryptographic token using JavaScript that must be present on submission. Simple bots that do not execute JavaScript will fail this check. Uses Web Crypto API (SHA-256).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Interaction Detection -->
		<tr>
			<th><?php esc_html_e( 'Interaction Detection', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_interaction" value="1" <?php checked( $nbuf_interaction, true ); ?>>
					<?php esc_html_e( 'Track mouse and keyboard interactions', 'nobloat-user-foundry' ); ?>
				</label>
				<br><br>
				<input type="number" name="nbuf_antibot_min_interactions" value="<?php echo esc_attr( $nbuf_min_interactions ); ?>" min="1" max="50" class="small-text">
				<span><?php esc_html_e( 'minimum interactions required', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Monitors for human-like behavior: mouse movements, clicks, key presses, and focus changes. Forms without sufficient interaction are rejected. Default: 3 interactions.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<!-- Proof of Work -->
		<tr>
			<th><?php esc_html_e( 'Proof of Work Challenge', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_antibot_pow" value="1" <?php checked( $nbuf_pow_enabled, true ); ?>>
					<?php esc_html_e( 'Require computational proof of work', 'nobloat-user-foundry' ); ?>
				</label>
				<br><br>
				<label><?php esc_html_e( 'Difficulty:', 'nobloat-user-foundry' ); ?></label>
				<select name="nbuf_antibot_pow_difficulty">
					<option value="low" <?php selected( $nbuf_pow_difficulty, 'low' ); ?>>
						<?php esc_html_e( 'Low (~10ms, ~256 iterations)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="medium" <?php selected( $nbuf_pow_difficulty, 'medium' ); ?>>
						<?php esc_html_e( 'Medium (~50ms, ~4,096 iterations)', 'nobloat-user-foundry' ); ?>
					</option>
					<option value="high" <?php selected( $nbuf_pow_difficulty, 'high' ); ?>>
						<?php esc_html_e( 'High (~200ms, ~65,536 iterations)', 'nobloat-user-foundry' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Requires the browser to solve a computational puzzle (finding a hash with leading zeros) before submission. Makes bulk registration attempts computationally expensive. Uses Web Crypto API for SHA-256 hashing.', 'nobloat-user-foundry' ); ?>
					<br>
					<?php esc_html_e( 'Higher difficulty = more CPU work required. A single registration is barely noticeable, but 1000 registrations would take significant time.', 'nobloat-user-foundry' ); ?>
					<br>
					<strong><?php esc_html_e( 'This is completely transparent to users and requires no interaction. The puzzle is solved automatically in the background while the user fills out the form.', 'nobloat-user-foundry' ); ?></strong>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
</form>

<h2><?php esc_html_e( 'Statistics', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php
	if ( $nbuf_blocked_count > 0 ) {
		printf(
			esc_html(
				/* translators: %d: number of blocked attempts */
				_n(
					'%d bot registration attempt blocked in the last 30 days.',
					'%d bot registration attempts blocked in the last 30 days.',
					$nbuf_blocked_count,
					'nobloat-user-foundry'
				)
			),
			(int) $nbuf_blocked_count
		);
	} else {
		esc_html_e( 'No bot registration attempts blocked in the last 30 days.', 'nobloat-user-foundry' );
	}
	?>
</p>
<p class="description">
	<?php
	printf(
		/* translators: %s: link to Security Log */
		esc_html__( 'View detailed logs in the %s.', 'nobloat-user-foundry' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-security-log&event_type=registration_bot_blocked' ) ) . '">' .
		esc_html__( 'Security Log', 'nobloat-user-foundry' ) . '</a>'
	);
	?>
</p>
