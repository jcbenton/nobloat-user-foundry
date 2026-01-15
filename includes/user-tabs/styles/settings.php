<?php
/**
 * Styles Settings Tab
 *
 * CSS optimization settings.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* CSS optimization settings */
$nbuf_css_load_on_pages = NBUF_Options::get( 'nbuf_css_load_on_pages', true );
$nbuf_css_use_minified  = NBUF_Options::get( 'nbuf_css_use_minified', true );

/* Get CSS templates for display */
$nbuf_css_templates = NBUF_CSS_Manager::get_css_templates();

/*
==========================================================
	CHECK FOR WRITE FAILURES
	==========================================================
 */
$nbuf_has_write_failure = false;
foreach ( $nbuf_css_templates as $nbuf_template ) {
	if ( NBUF_CSS_Manager::has_write_failure( $nbuf_template['token_key'] ) ) {
		$nbuf_has_write_failure = true;
		break;
	}
}

?>

<div class="nbuf-styles-tab">

	<?php if ( $nbuf_has_write_failure ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'File Write Permission Issue:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'Unable to write CSS files to disk. Styles are being loaded from database (slower performance). Please check file permissions on the /assets/css/frontend/ directory.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php
		NBUF_Settings::settings_nonce_field();
		settings_errors( 'nbuf_styles' );
		?>
		<input type="hidden" name="nbuf_active_tab" value="styles">
		<input type="hidden" name="nbuf_active_subtab" value="settings">

		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Load CSS on NoBloat Pages', 'nobloat-user-foundry' ); ?>
				</th>
				<td>
					<input type="hidden" name="nbuf_css_load_on_pages" value="0">
					<label>
						<input type="checkbox" name="nbuf_css_load_on_pages" value="1" <?php checked( $nbuf_css_load_on_pages, true ); ?>>
						<?php esc_html_e( 'Load CSS on plugin pages only', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, CSS files are loaded only on NoBloat-specific pages (verification, reset, login, registration, account). When disabled, CSS will not load at all. Plugin CSS never loads globally on other pages. Recommended: Enabled', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Use Minified CSS Files', 'nobloat-user-foundry' ); ?>
				</th>
				<td>
					<input type="hidden" name="nbuf_css_use_minified" value="0">
					<label>
						<input type="checkbox" name="nbuf_css_use_minified" value="1" <?php checked( $nbuf_css_use_minified, true ); ?>>
						<?php esc_html_e( 'Load minified CSS files', 'nobloat-user-foundry' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, loads minified .min.css files for better performance. When disabled, loads unminified .css files (useful for debugging). Recommended: Enabled', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Changes', 'nobloat-user-foundry' ) ); ?>
	</form>

	<hr style="margin: 30px 0;">

	<h2><?php esc_html_e( 'CSS File Management', 'nobloat-user-foundry' ); ?></h2>

	<p class="description">
		<?php esc_html_e( 'Manage all CSS files for the plugin. Use these tools to regenerate minified files or reset all styles to defaults.', 'nobloat-user-foundry' ); ?>
	</p>

	<p style="margin-bottom: 10px;">
		<strong><?php esc_html_e( 'CSS Templates:', 'nobloat-user-foundry' ); ?></strong>
		<?php
		$nbuf_labels = array();
		foreach ( $nbuf_css_templates as $nbuf_template ) {
			$nbuf_labels[] = $nbuf_template['label'];
		}
		echo esc_html( implode( ' | ', $nbuf_labels ) );
		?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Regenerate All CSS', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<button type="button" id="nbuf-regenerate-css-btn" class="button button-secondary">
					<?php esc_html_e( 'Regenerate All CSS Files', 'nobloat-user-foundry' ); ?>
				</button>
				<span id="nbuf-regenerate-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
				<p class="description">
					<?php esc_html_e( 'Rewrites all CSS files to disk from the database. Use this if CSS files are out of sync or after manual database changes.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Reset All to Defaults', 'nobloat-user-foundry' ); ?>
			</th>
			<td>
				<button type="button" id="nbuf-reset-css-btn" class="button button-secondary" style="color: #b32d2e;">
					<?php esc_html_e( 'Reset All CSS to Defaults', 'nobloat-user-foundry' ); ?>
				</button>
				<span id="nbuf-reset-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
				<p class="description" style="color: #b32d2e;">
					<?php esc_html_e( 'Warning: This will overwrite all custom CSS styles with the plugin defaults. This action cannot be undone.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div id="nbuf-css-results" style="display: none; margin-top: 20px;"></div>

</div>

<script>
jQuery(document).ready(function($) {
	var nonce = '<?php echo esc_js( wp_create_nonce( 'nbuf_css_operations' ) ); ?>';

	function showResults(results, action) {
		var $container = $('#nbuf-css-results');
		var html = '<div class="notice notice-' + (results.failed > 0 ? 'warning' : 'success') + ' inline">';
		html += '<p><strong>' + (action === 'regenerate' ? '<?php echo esc_js( __( 'CSS Regeneration Complete', 'nobloat-user-foundry' ) ); ?>' : '<?php echo esc_js( __( 'CSS Reset Complete', 'nobloat-user-foundry' ) ); ?>') + '</strong></p>';
		html += '<p><?php echo esc_js( __( 'Success:', 'nobloat-user-foundry' ) ); ?> ' + results.success + ' | <?php echo esc_js( __( 'Failed:', 'nobloat-user-foundry' ) ); ?> ' + results.failed + '</p>';

		if (results.details) {
			html += '<ul style="margin-left: 20px;">';
			$.each(results.details, function(filename, detail) {
				var icon = detail.status === 'success' ? '✓' : (detail.status === 'failed' ? '✗' : '○');
				html += '<li>' + icon + ' <strong>' + filename + '</strong>: ' + detail.message + '</li>';
			});
			html += '</ul>';
		}

		html += '</div>';
		$container.html(html).show();
	}

	$('#nbuf-regenerate-css-btn').on('click', function() {
		var $btn = $(this);
		var $spinner = $('#nbuf-regenerate-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$('#nbuf-css-results').hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_regenerate_all_css',
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					showResults(response.data, 'regenerate');
				} else {
					$('#nbuf-css-results').html('<div class="notice notice-error inline"><p>' + (response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'nobloat-user-foundry' ) ); ?>') + '</p></div>').show();
				}
			},
			error: function() {
				$('#nbuf-css-results').html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'nobloat-user-foundry' ) ); ?></p></div>').show();
			},
			complete: function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	$('#nbuf-reset-css-btn').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reset ALL CSS files to defaults? This will overwrite any custom styles you have made.', 'nobloat-user-foundry' ) ); ?>')) {
			return;
		}

		var $btn = $(this);
		var $spinner = $('#nbuf-reset-spinner');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$('#nbuf-css-results').hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_reset_all_css',
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					showResults(response.data, 'reset');
				} else {
					$('#nbuf-css-results').html('<div class="notice notice-error inline"><p>' + (response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'nobloat-user-foundry' ) ); ?>') + '</p></div>').show();
				}
			},
			error: function() {
				$('#nbuf-css-results').html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'nobloat-user-foundry' ) ); ?></p></div>').show();
			},
			complete: function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});
});
</script>
