<?php
/**
 * Tools > Feature Tests Tab
 *
 * Testing tools for webhooks and anti-bot features.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_webhooks_enabled = NBUF_Options::get( 'nbuf_webhooks_enabled', false );
$nbuf_antibot_enabled  = NBUF_Options::get( 'nbuf_antibot_enabled', true );
?>

<h2><?php esc_html_e( 'Feature Tests', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Test webhook delivery and anti-bot protection features to ensure they are configured and working correctly.', 'nobloat-user-foundry' ); ?>
</p>

<hr style="margin: 30px 0;">

<!-- Webhook Delivery Tests -->
<h3><?php esc_html_e( 'Webhook Delivery Test', 'nobloat-user-foundry' ); ?></h3>

<?php if ( ! $nbuf_webhooks_enabled ) : ?>
	<div class="notice notice-warning inline" style="margin: 10px 0 20px;">
		<p>
			<?php esc_html_e( 'Webhooks are currently disabled.', 'nobloat-user-foundry' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks' ) ); ?>">
				<?php esc_html_e( 'Enable webhooks in settings', 'nobloat-user-foundry' ); ?>
			</a>
		</p>
	</div>
<?php else : ?>
	<p class="description">
		<?php esc_html_e( 'Send a test payload to configured webhook endpoints to verify connectivity and response handling.', 'nobloat-user-foundry' ); ?>
	</p>

	<?php
	$nbuf_webhooks = NBUF_Webhooks::get_all();

	if ( empty( $nbuf_webhooks ) ) :
		?>
		<div class="notice notice-info inline" style="margin: 10px 0 20px;">
			<p>
				<?php esc_html_e( 'No webhooks configured.', 'nobloat-user-foundry' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks' ) ); ?>">
					<?php esc_html_e( 'Configure webhooks', 'nobloat-user-foundry' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>
		<table class="widefat striped" style="margin-top: 15px;">
			<thead>
				<tr>
					<th style="width: 25%;"><?php esc_html_e( 'Webhook Name', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 30%;"><?php esc_html_e( 'URL', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 10%;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Last Status', 'nobloat-user-foundry' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'Action', 'nobloat-user-foundry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $nbuf_webhooks as $nbuf_webhook ) : ?>
					<tr data-webhook-id="<?php echo esc_attr( $nbuf_webhook->id ); ?>">
						<td>
							<strong><?php echo esc_html( $nbuf_webhook->name ); ?></strong>
							<?php if ( ! empty( $nbuf_webhook->secret ) ) : ?>
								<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'HMAC signature enabled', 'nobloat-user-foundry' ); ?>" style="font-size: 14px; color: #666;"></span>
							<?php endif; ?>
						</td>
						<td>
							<code style="font-size: 11px; word-break: break-all;"><?php echo esc_html( $nbuf_webhook->url ); ?></code>
						</td>
						<td>
							<?php if ( $nbuf_webhook->enabled ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e( 'Enabled', 'nobloat-user-foundry' ); ?>"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: #dc3232;" title="<?php esc_attr_e( 'Disabled', 'nobloat-user-foundry' ); ?>"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="webhook-last-status">
							<?php if ( $nbuf_webhook->last_status ) : ?>
								<?php
								$nbuf_status_class = ( $nbuf_webhook->last_status >= 200 && $nbuf_webhook->last_status < 300 ) ? 'color: #46b450;' : 'color: #dc3232;';
								?>
								<span style="<?php echo esc_attr( $nbuf_status_class ); ?>">
									<?php echo esc_html( $nbuf_webhook->last_status ); ?>
								</span>
								<?php if ( $nbuf_webhook->failure_count > 0 ) : ?>
									<br><small style="color: #dc3232;">
										<?php
										/* translators: %d: number of failures */
										printf( esc_html__( '%d failures', 'nobloat-user-foundry' ), (int) $nbuf_webhook->failure_count );
										?>
									</small>
								<?php endif; ?>
							<?php else : ?>
								<span style="color: #888;"><?php esc_html_e( 'Never tested', 'nobloat-user-foundry' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-secondary nbuf-test-webhook" data-webhook-id="<?php echo esc_attr( $nbuf_webhook->id ); ?>">
								<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
								<?php esc_html_e( 'Send Test', 'nobloat-user-foundry' ); ?>
							</button>
							<span class="spinner" style="float: none; margin-top: 0;"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description" style="margin-top: 15px;">
			<?php esc_html_e( 'Test payloads include sample data and are logged in the webhook delivery log. Click "Logs" next to any webhook in the', 'nobloat-user-foundry' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks' ) ); ?>">
				<?php esc_html_e( 'webhooks settings', 'nobloat-user-foundry' ); ?>
			</a>
			<?php esc_html_e( 'to view delivery history.', 'nobloat-user-foundry' ); ?>
		</p>
	<?php endif; ?>
<?php endif; ?>

<hr style="margin: 40px 0;">

<!-- Anti-Bot Challenge Tests -->
<h3><?php esc_html_e( 'Anti-Bot Challenge Test', 'nobloat-user-foundry' ); ?></h3>

<?php if ( ! $nbuf_antibot_enabled ) : ?>
	<div class="notice notice-warning inline" style="margin: 10px 0 20px;">
		<p>
			<?php esc_html_e( 'Anti-bot protection is currently disabled.', 'nobloat-user-foundry' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=registration&subtab=antibot' ) ); ?>">
				<?php esc_html_e( 'Enable anti-bot protection', 'nobloat-user-foundry' ); ?>
			</a>
		</p>
	</div>
<?php else : ?>
	<p class="description">
		<?php esc_html_e( 'Test each anti-bot protection layer to ensure it is properly configured and functional.', 'nobloat-user-foundry' ); ?>
	</p>

	<table class="widefat striped" style="margin-top: 15px;">
		<thead>
			<tr>
				<th style="width: 25%;"><?php esc_html_e( 'Protection Layer', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 40%;"><?php esc_html_e( 'Configuration', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Test Result', 'nobloat-user-foundry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<!-- Honeypot Fields -->
			<?php
			$nbuf_honeypot_enabled = NBUF_Options::get( 'nbuf_antibot_honeypot', true );
			$nbuf_honeypot_fields  = $nbuf_honeypot_enabled ? NBUF_Antibot::get_honeypot_fields() : array();
			?>
			<tr>
				<td>
					<strong><?php esc_html_e( 'Honeypot Fields', 'nobloat-user-foundry' ); ?></strong>
					<p class="description" style="margin: 5px 0 0;">
						<?php esc_html_e( 'Hidden fields that bots typically fill out.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
				<td>
					<?php if ( $nbuf_honeypot_enabled ) : ?>
						<span style="color: #46b450;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php else : ?>
						<span style="color: #888;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_honeypot_enabled && ! empty( $nbuf_honeypot_fields ) ) : ?>
						<code style="font-size: 11px;">
							<?php echo esc_html( implode( ', ', $nbuf_honeypot_fields ) ); ?>
						</code>
						<p class="description" style="margin: 5px 0 0;">
							<?php esc_html_e( 'Field names rotate hourly for unpredictability.', 'nobloat-user-foundry' ); ?>
						</p>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_honeypot_enabled ) : ?>
						<?php
						/* Test honeypot validation with empty fields (should pass) */
						$nbuf_test_pass = NBUF_Antibot::validate_honeypot( array() );
						if ( $nbuf_test_pass ) :
							?>
							<span style="color: #46b450;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Pass', 'nobloat-user-foundry' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Fail', 'nobloat-user-foundry' ); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Time Check -->
			<?php
			$nbuf_time_check_enabled = NBUF_Options::get( 'nbuf_antibot_time_check', true );
			$nbuf_min_time           = NBUF_Options::get( 'nbuf_antibot_min_time', 3 );
			?>
			<tr>
				<td>
					<strong><?php esc_html_e( 'Minimum Time Check', 'nobloat-user-foundry' ); ?></strong>
					<p class="description" style="margin: 5px 0 0;">
						<?php esc_html_e( 'Requires time to pass before form submission.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
				<td>
					<?php if ( $nbuf_time_check_enabled ) : ?>
						<span style="color: #46b450;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php else : ?>
						<span style="color: #888;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_time_check_enabled ) : ?>
						<?php
						/* translators: %d: number of seconds */
						printf( esc_html__( 'Minimum %d seconds required', 'nobloat-user-foundry' ), (int) $nbuf_min_time );
						?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_time_check_enabled ) : ?>
						<?php
						/* Test token generation */
						$nbuf_test_token = NBUF_Antibot::generate_time_token();
						if ( ! empty( $nbuf_test_token ) ) :
							?>
							<span style="color: #46b450;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Token generated', 'nobloat-user-foundry' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Token failed', 'nobloat-user-foundry' ); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- JavaScript Token -->
			<?php $nbuf_js_token_enabled = NBUF_Options::get( 'nbuf_antibot_js_token', true ); ?>
			<tr>
				<td>
					<strong><?php esc_html_e( 'JavaScript Token', 'nobloat-user-foundry' ); ?></strong>
					<p class="description" style="margin: 5px 0 0;">
						<?php esc_html_e( 'Requires JavaScript execution to generate token.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
				<td>
					<?php if ( $nbuf_js_token_enabled ) : ?>
						<span style="color: #46b450;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php else : ?>
						<span style="color: #888;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_js_token_enabled ) : ?>
						<?php esc_html_e( 'SHA256 hash of seed + timestamp + session', 'nobloat-user-foundry' ); ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_js_token_enabled ) : ?>
						<?php
						/* Test seed generation */
						$nbuf_test_session = bin2hex( random_bytes( 16 ) );
						$nbuf_seed_data    = NBUF_Antibot::generate_js_seed( $nbuf_test_session );
						if ( ! empty( $nbuf_seed_data['seed'] ) ) :
							?>
							<span style="color: #46b450;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Seed generated', 'nobloat-user-foundry' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Seed failed', 'nobloat-user-foundry' ); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Interaction Detection -->
			<?php
			$nbuf_interaction_enabled = NBUF_Options::get( 'nbuf_antibot_interaction', true );
			$nbuf_min_interactions    = NBUF_Options::get( 'nbuf_antibot_min_interactions', 3 );
			?>
			<tr>
				<td>
					<strong><?php esc_html_e( 'Interaction Detection', 'nobloat-user-foundry' ); ?></strong>
					<p class="description" style="margin: 5px 0 0;">
						<?php esc_html_e( 'Tracks mouse, keyboard, and scroll events.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
				<td>
					<?php if ( $nbuf_interaction_enabled ) : ?>
						<span style="color: #46b450;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php else : ?>
						<span style="color: #888;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_interaction_enabled ) : ?>
						<?php
						/* translators: %d: number of interactions */
						printf( esc_html__( 'Minimum %d interactions required', 'nobloat-user-foundry' ), (int) $nbuf_min_interactions );
						?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_interaction_enabled ) : ?>
						<?php
						/* Test with valid interaction data */
						$nbuf_test_interaction = base64_encode(
							wp_json_encode(
								array( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
								'mouse'    => 5,
								'keyboard' => 10,
								'focus'    => 3,
								'scroll'   => 2,
								)
							)
						);
						$nbuf_interaction_pass = NBUF_Antibot::validate_interaction( $nbuf_test_interaction );
						if ( $nbuf_interaction_pass ) :
							?>
							<span style="color: #46b450;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Validation works', 'nobloat-user-foundry' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Validation failed', 'nobloat-user-foundry' ); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Proof of Work -->
			<?php
			$nbuf_pow_enabled    = NBUF_Options::get( 'nbuf_antibot_pow', true );
			$nbuf_pow_difficulty = NBUF_Options::get( 'nbuf_antibot_pow_difficulty', 'medium' );
			$nbuf_pow_zeros      = NBUF_Antibot::get_pow_difficulty();
			?>
			<tr>
				<td>
					<strong><?php esc_html_e( 'Proof of Work', 'nobloat-user-foundry' ); ?></strong>
					<p class="description" style="margin: 5px 0 0;">
						<?php esc_html_e( 'CPU-intensive challenge to deter automated submissions.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
				<td>
					<?php if ( $nbuf_pow_enabled ) : ?>
						<span style="color: #46b450;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php else : ?>
						<span style="color: #888;">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?>
						</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_pow_enabled ) : ?>
						<?php
						printf(
							/* translators: 1: difficulty level (e.g., "Medium"), 2: number of leading zeros required */
							esc_html__( 'Difficulty: %1$s (%2$d leading zeros)', 'nobloat-user-foundry' ),
							esc_html( ucfirst( $nbuf_pow_difficulty ) ),
							(int) $nbuf_pow_zeros
						);
						?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_pow_enabled ) : ?>
						<?php
						/* Test challenge generation */
						$nbuf_test_session = bin2hex( random_bytes( 16 ) );
						$nbuf_challenge    = NBUF_Antibot::generate_pow_challenge( $nbuf_test_session );
						if ( ! empty( $nbuf_challenge ) ) :
							?>
							<span style="color: #46b450;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Challenge generated', 'nobloat-user-foundry' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232;">
								<span class="dashicons dashicons-no"></span>
								<?php esc_html_e( 'Challenge failed', 'nobloat-user-foundry' ); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #888;">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="description" style="margin-top: 15px;">
		<?php esc_html_e( 'Anti-bot challenges are validated during registration form submission. Failed challenges are logged to the security log.', 'nobloat-user-foundry' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=registration&subtab=antibot' ) ); ?>">
			<?php esc_html_e( 'Configure anti-bot settings', 'nobloat-user-foundry' ); ?>
		</a>
	</p>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
	$('.nbuf-test-webhook').on('click', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $row = $button.closest('tr');
		var $spinner = $row.find('.spinner');
		var $statusCell = $row.find('.webhook-last-status');
		var webhookId = $button.data('webhook-id');

		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_test_webhook',
				webhook_id: webhookId,
				_wpnonce: '<?php echo esc_js( wp_create_nonce( 'nbuf_test_webhook' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					var statusHtml = '<span style="color: #46b450;">' + response.data.code + '</span>';
					$statusCell.html(statusHtml);
					$button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-yes');
					setTimeout(function() {
						$button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update');
					}, 2000);
				} else {
					var statusHtml = '<span style="color: #dc3232;">' + (response.data.code || 'Error') + '</span>';
					if (response.data.message) {
						statusHtml += '<br><small style="color: #dc3232;">' + response.data.message.substring(0, 50) + '</small>';
					}
					$statusCell.html(statusHtml);
				}
			},
			error: function() {
				$statusCell.html('<span style="color: #dc3232;">AJAX Error</span>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});
});
</script>
