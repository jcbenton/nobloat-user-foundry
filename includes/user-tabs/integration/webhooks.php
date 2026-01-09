<?php
/**
 * Integration > Webhooks Tab
 *
 * Webhook configuration and management interface.
 *
 * @package NoBloat_User_Foundry
 * @since   1.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Handle webhook actions */
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in NBUF_Settings::init()
$nbuf_webhook_action = isset( $_GET['webhook_action'] ) ? sanitize_text_field( wp_unslash( $_GET['webhook_action'] ) ) : '';
$nbuf_webhook_id     = isset( $_GET['webhook_id'] ) ? absint( $_GET['webhook_id'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

/* Get current settings */
$nbuf_webhooks_enabled = NBUF_Options::get( 'nbuf_webhooks_enabled', false );
$nbuf_available_events = NBUF_Webhooks::get_available_events();
$nbuf_webhooks         = NBUF_Webhooks::get_all();

/* Handle edit mode */
$nbuf_editing_webhook = null;
if ( 'edit' === $nbuf_webhook_action && $nbuf_webhook_id ) {
	$nbuf_editing_webhook = NBUF_Webhooks::get( $nbuf_webhook_id );
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php
	NBUF_Settings::settings_nonce_field();
	settings_errors( 'nbuf_settings' );
	?>

	<!-- Hidden inputs to preserve tab state after save -->
	<input type="hidden" name="nbuf_active_tab" value="integration">
	<input type="hidden" name="nbuf_active_subtab" value="webhooks">

	<h2><?php esc_html_e( 'Webhook Configuration', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Send HTTP POST notifications to external services when user events occur.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- Master toggle -->
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Webhooks', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_webhooks_enabled" value="1" <?php checked( $nbuf_webhooks_enabled ); ?>>
					<?php esc_html_e( 'Enable webhook notifications', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, configured webhooks will be triggered on user events.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Settings', 'nobloat-user-foundry' ), 'primary', 'submit', true ); ?>
</form>

<hr>

<!-- Add/Edit Webhook Form -->
<h3><?php echo $nbuf_editing_webhook ? esc_html__( 'Edit Webhook', 'nobloat-user-foundry' ) : esc_html__( 'Add New Webhook', 'nobloat-user-foundry' ); ?></h3>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nbuf-webhook-form">
	<?php
	NBUF_Settings::settings_nonce_field();
	?>

	<input type="hidden" name="nbuf_active_tab" value="integration">
	<input type="hidden" name="nbuf_active_subtab" value="webhooks">
	<input type="hidden" name="nbuf_webhook_action" value="<?php echo $nbuf_editing_webhook ? 'update' : 'create'; ?>">
	<?php if ( $nbuf_editing_webhook ) : ?>
		<input type="hidden" name="nbuf_webhook_id" value="<?php echo esc_attr( $nbuf_editing_webhook->id ); ?>">
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="webhook_name"><?php esc_html_e( 'Name', 'nobloat-user-foundry' ); ?></label></th>
			<td>
				<input type="text" name="webhook_name" id="webhook_name" class="regular-text"
					value="<?php echo $nbuf_editing_webhook ? esc_attr( $nbuf_editing_webhook->name ) : ''; ?>" required>
				<p class="description"><?php esc_html_e( 'A friendly name to identify this webhook.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="webhook_url"><?php esc_html_e( 'URL', 'nobloat-user-foundry' ); ?></label></th>
			<td>
				<input type="url" name="webhook_url" id="webhook_url" class="large-text"
					value="<?php echo $nbuf_editing_webhook ? esc_url( $nbuf_editing_webhook->url ) : ''; ?>"
					placeholder="https://example.com/webhook" required>
				<p class="description"><?php esc_html_e( 'The URL that will receive POST requests with event data.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="webhook_secret"><?php esc_html_e( 'Secret', 'nobloat-user-foundry' ); ?></label></th>
			<td>
				<input type="text" name="webhook_secret" id="webhook_secret" class="regular-text"
					value="<?php echo $nbuf_editing_webhook ? esc_attr( $nbuf_editing_webhook->secret ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'Optional', 'nobloat-user-foundry' ); ?>">
				<p class="description">
					<?php esc_html_e( 'If set, requests will include an X-Webhook-Signature header for verification (HMAC-SHA256).', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Events', 'nobloat-user-foundry' ); ?></th>
			<td>
				<fieldset>
					<?php
					$nbuf_selected_events = $nbuf_editing_webhook ? $nbuf_editing_webhook->events : array();
					foreach ( $nbuf_available_events as $nbuf_event_key => $nbuf_event_label ) :
						?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="webhook_events[]" value="<?php echo esc_attr( $nbuf_event_key ); ?>"
								<?php checked( in_array( $nbuf_event_key, $nbuf_selected_events, true ) ); ?>>
							<?php echo esc_html( $nbuf_event_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Select which events should trigger this webhook.', 'nobloat-user-foundry' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="webhook_enabled" value="1"
						<?php checked( ! $nbuf_editing_webhook || $nbuf_editing_webhook->enabled ); ?>>
					<?php esc_html_e( 'Enabled', 'nobloat-user-foundry' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<?php
	if ( $nbuf_editing_webhook ) {
		submit_button( __( 'Update Webhook', 'nobloat-user-foundry' ), 'primary', 'submit', true );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks' ) ) . '" class="button">' . esc_html__( 'Cancel', 'nobloat-user-foundry' ) . '</a>';
	} else {
		submit_button( __( 'Add Webhook', 'nobloat-user-foundry' ), 'secondary', 'submit', true );
	}
	?>
</form>

<hr>

<!-- Existing Webhooks List -->
<h3><?php esc_html_e( 'Configured Webhooks', 'nobloat-user-foundry' ); ?></h3>

<?php if ( empty( $nbuf_webhooks ) ) : ?>
	<p class="description"><?php esc_html_e( 'No webhooks configured yet.', 'nobloat-user-foundry' ); ?></p>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 20%;"><?php esc_html_e( 'Name', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 25%;"><?php esc_html_e( 'URL', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Events', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 10%;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 10%;"><?php esc_html_e( 'Last Status', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Actions', 'nobloat-user-foundry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $nbuf_webhooks as $nbuf_webhook ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $nbuf_webhook->name ); ?></strong>
						<?php if ( $nbuf_webhook->failure_count >= 5 ) : ?>
							<br><span style="color: #d63638;">
							<?php
							/* translators: %d: number of consecutive webhook delivery failures */
							printf( esc_html__( '%d failures', 'nobloat-user-foundry' ), (int) $nbuf_webhook->failure_count );
							?>
							</span>
						<?php endif; ?>
					</td>
					<td>
						<code style="font-size: 11px;"><?php echo esc_html( substr( $nbuf_webhook->url, 0, 50 ) . ( strlen( $nbuf_webhook->url ) > 50 ? '...' : '' ) ); ?></code>
					</td>
					<td>
						<?php
						$nbuf_event_labels = array();
						foreach ( $nbuf_webhook->events as $nbuf_event ) {
							if ( isset( $nbuf_available_events[ $nbuf_event ] ) ) {
								$nbuf_event_labels[] = $nbuf_available_events[ $nbuf_event ];
							}
						}
						echo esc_html( implode( ', ', array_slice( $nbuf_event_labels, 0, 3 ) ) );
						if ( count( $nbuf_event_labels ) > 3 ) {
							/* translators: %d: number of additional webhook events not shown */
							printf( ' ' . esc_html__( '+%d more', 'nobloat-user-foundry' ), count( $nbuf_event_labels ) - 3 );
						}
						?>
					</td>
					<td>
						<?php if ( $nbuf_webhook->enabled ) : ?>
							<span style="color: #00a32a;">&#9679; <?php esc_html_e( 'Active', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span style="color: #d63638;">&#9679; <?php esc_html_e( 'Disabled', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $nbuf_webhook->last_status ) : ?>
							<span style="color: <?php echo $nbuf_webhook->last_status >= 200 && $nbuf_webhook->last_status < 300 ? '#00a32a' : '#d63638'; ?>;">
								<?php echo esc_html( $nbuf_webhook->last_status ); ?>
							</span>
							<?php if ( $nbuf_webhook->last_triggered ) : ?>
								<br><small><?php echo esc_html( human_time_diff( strtotime( $nbuf_webhook->last_triggered ) ) ); ?> ago</small>
							<?php endif; ?>
						<?php else : ?>
							<span style="color: #888;"><?php esc_html_e( 'Never', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$nbuf_edit_url   = wp_nonce_url(
							admin_url( 'admin.php?page=nobloat-foundry-users&tab=integration&subtab=webhooks&webhook_action=edit&webhook_id=' . $nbuf_webhook->id ),
							'nbuf_webhook_edit_' . $nbuf_webhook->id
						);
						$nbuf_test_url   = wp_nonce_url(
							admin_url( 'admin-post.php?action=nbuf_save_settings&nbuf_active_tab=integration&nbuf_active_subtab=webhooks&nbuf_webhook_action=test&nbuf_webhook_id=' . $nbuf_webhook->id ),
							'nbuf_save_settings',
							'nbuf_settings_nonce'
						);
						$nbuf_delete_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=nbuf_save_settings&nbuf_active_tab=integration&nbuf_active_subtab=webhooks&nbuf_webhook_action=delete&nbuf_webhook_id=' . $nbuf_webhook->id ),
							'nbuf_save_settings',
							'nbuf_settings_nonce'
						);
						?>
						<a href="<?php echo esc_url( $nbuf_edit_url ); ?>"><?php esc_html_e( 'Edit', 'nobloat-user-foundry' ); ?></a> |
						<a href="<?php echo esc_url( $nbuf_test_url ); ?>"><?php esc_html_e( 'Test', 'nobloat-user-foundry' ); ?></a> |
						<a href="<?php echo esc_url( $nbuf_delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this webhook?', 'nobloat-user-foundry' ); ?>');" style="color: #d63638;"><?php esc_html_e( 'Delete', 'nobloat-user-foundry' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<hr>

<!-- Documentation -->
<h3><?php esc_html_e( 'Webhook Payload Format', 'nobloat-user-foundry' ); ?></h3>
<p class="description"><?php esc_html_e( 'Webhooks are sent as HTTP POST requests with a JSON body:', 'nobloat-user-foundry' ); ?></p>

<pre style="background: #f0f0f1; padding: 15px; border-radius: 4px; overflow-x: auto;">
{
  "event": "user_registered",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "webhook_id": 1,
  "site_url": "https://example.com",
  "data": {
    "user_id": 123,
    "user_email": "user@example.com",
    "user_login": "username",
    "display_name": "John Doe",
    "roles": ["subscriber"]
  }
}
</pre>

<p class="description">
	<strong><?php esc_html_e( 'Signature Verification:', 'nobloat-user-foundry' ); ?></strong>
	<?php esc_html_e( 'If a secret is configured, the X-Webhook-Signature header will contain "sha256=" followed by the HMAC-SHA256 hash of the raw JSON payload using your secret.', 'nobloat-user-foundry' ); ?>
</p>
