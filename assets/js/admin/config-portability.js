/**
 * Configuration Export/Import - JavaScript
 *
 * Handles AJAX-based configuration export and import functionality.
 * Pure JavaScript with no PHP dependencies - all dynamic data passed via ajaxurl.
 *
 * @package NoBloat_User_Foundry
 * @since 1.3.0
 */

jQuery(document).ready(function($) {
	let currentTransientKey = null;

	/* Handle configuration export */
	$('#nbuf-export-config').on('click', function() {
		const exportSettings = $('#export_settings').is(':checked') ? '1' : '0';
		const exportTemplates = $('#export_templates').is(':checked') ? '1' : '0';
		const exportSensitive = $('#export_sensitive').is(':checked') ? '1' : '0';
		const prettyPrint = $('#pretty_print').is(':checked') ? '1' : '0';

		if (exportSettings === '0' && exportTemplates === '0') {
			alert('Please select at least one export option (Settings or Templates).');
			return;
		}

		$('#nbuf-export-config').prop('disabled', true).text('Exporting...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_export_config',
				nonce: $('#nbuf_config_nonce').val(),
				export_settings: exportSettings,
				export_templates: exportTemplates,
				export_sensitive: exportSensitive,
				pretty_print: prettyPrint
			},
			success: function(response) {
				if (response.success) {
					/* Create download link */
					const blob = new Blob([response.data.json], { type: 'application/json' });
					const url = window.URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = url;
					a.download = response.data.filename;
					document.body.appendChild(a);
					a.click();
					window.URL.revokeObjectURL(url);
					document.body.removeChild(a);

					/* Show success message */
					const size = (response.data.size / 1024).toFixed(2);
					$('#nbuf-export-results').html(
						'<div class="notice notice-success"><p><strong>Export successful!</strong> Downloaded ' +
						response.data.filename + ' (' + size + ' KB)</p></div>'
					).show();
				} else {
					alert('Export error: ' + response.data.message);
				}
				$('#nbuf-export-config').prop('disabled', false).html(
					'<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Export Configuration'
				);
			},
			error: function() {
				alert('An error occurred during export.');
				$('#nbuf-export-config').prop('disabled', false).html(
					'<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Export Configuration'
				);
			}
		});
	});

	/* Handle configuration validation */
	$('#nbuf-import-form').on('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		formData.append('action', 'nbuf_validate_import_config');
		formData.append('nonce', $('#nbuf_config_import_nonce').val());

		$('#nbuf-validate-config').prop('disabled', true).text('Validating...');
		$('#nbuf-import-preview').hide();
		$('#nbuf-import-results').hide();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					currentTransientKey = response.data.transient_key;
					showImportPreview(response.data.preview);
				} else {
					alert('Validation error: ' + response.data.message);
				}
				$('#nbuf-validate-config').prop('disabled', false).html(
					'<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Validate &amp; Preview Import'
				);
			},
			error: function() {
				alert('An error occurred during validation.');
				$('#nbuf-validate-config').prop('disabled', false).html(
					'<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Validate &amp; Preview Import'
				);
			}
		});
	});

	/* Show import preview */
	function showImportPreview(preview) {
		let html = '<div class="nbuf-import-preview-box">';
		html += '<h4>Configuration Preview</h4>';

		html += '<table class="widefat" style="margin-top: 10px;">';
		html += '<tr><th>Plugin Version</th><td>' + preview.plugin_version + '</td></tr>';
		html += '<tr><th>Exported Date</th><td>' + preview.exported_at + '</td></tr>';
		html += '<tr><th>Source Site</th><td>' + preview.site_url + '</td></tr>';
		html += '<tr><th>Settings Count</th><td><strong>' + preview.settings_count + '</strong></td></tr>';
		html += '<tr><th>Template Count</th><td><strong>' + preview.template_count + '</strong></td></tr>';
		html += '</table>';

		if (Object.keys(preview.categories).length > 0) {
			html += '<h4 style="margin-top: 20px;">Categories to Import</h4>';
			html += '<ul>';
			for (const [category, count] of Object.entries(preview.categories)) {
				const displayName = category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
				html += '<li><strong>' + displayName + ':</strong> ' + count + ' items</li>';
			}
			html += '</ul>';
		}

		html += '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">';
		html += '<p style="margin: 0;"><strong>⚠️ Important:</strong> Importing will modify your current plugin settings. ';
		html += 'Make sure to export a backup of your current configuration first!</p>';
		html += '</div>';

		html += '<p style="margin-top: 20px;">';
		html += '<button type="button" class="button button-primary" id="nbuf-confirm-import">Confirm &amp; Import</button> ';
		html += '<button type="button" class="button" id="nbuf-cancel-import">Cancel</button>';
		html += '</p>';

		html += '</div>';

		$('#nbuf-import-preview').html(html).show();
	}

	/* Confirm and import */
	$(document).on('click', '#nbuf-confirm-import', function() {
		if (!confirm('Are you sure you want to import this configuration? This will modify your current settings.')) {
			return;
		}

		const importMode = $('#import_mode').val();

		$(this).prop('disabled', true).text('Importing...');
		$('#nbuf-cancel-import').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_import_config',
				nonce: $('#nbuf_config_import_nonce').val(),
				transient_key: currentTransientKey,
				import_mode: importMode
			},
			success: function(response) {
				if (response.success) {
					showImportResults(response.data);
					$('#nbuf-import-preview').hide();
				} else {
					alert('Import error: ' + response.data.message);
					$('#nbuf-confirm-import').prop('disabled', false).text('Confirm & Import');
					$('#nbuf-cancel-import').prop('disabled', false);
				}
			},
			error: function() {
				alert('An error occurred during import.');
				$('#nbuf-confirm-import').prop('disabled', false).text('Confirm & Import');
				$('#nbuf-cancel-import').prop('disabled', false);
			}
		});
	});

	/* Cancel import */
	$(document).on('click', '#nbuf-cancel-import', function() {
		currentTransientKey = null;
		$('#nbuf-import-preview').hide();
		$('#nbuf-import-form')[0].reset();
	});

	/* Show import results */
	function showImportResults(data) {
		let html = '<div class="notice notice-success"><p><strong>Import Complete!</strong></p></div>';

		html += '<table class="widefat" style="margin-top: 15px;">';
		html += '<tr><th>Settings Imported</th><td><strong style="color: green;">' + data.settings_imported + '</strong></td></tr>';
		html += '<tr><th>Settings Skipped</th><td>' + data.settings_skipped + '</td></tr>';
		html += '<tr><th>Templates Imported</th><td><strong style="color: green;">' + data.templates_imported + '</strong></td></tr>';
		html += '</table>';

		if (data.errors.length > 0) {
			html += '<h4 style="margin-top: 20px; color: red;">Errors</h4>';
			html += '<ul style="color: red;">';
			data.errors.forEach(function(error) {
				html += '<li>' + error + '</li>';
			});
			html += '</ul>';
		}

		html += '<div style="margin-top: 20px; padding: 15px; background: #d1ecf1; border-left: 4px solid #0c5460;">';
		html += '<p style="margin: 0;"><strong>Note:</strong> You may need to refresh this page to see the imported settings take effect.</p>';
		html += '</div>';

		html += '<p style="margin-top: 20px;">';
		html += '<button type="button" class="button button-primary" onclick="location.reload()">Refresh Page</button> ';
		html += '<button type="button" class="button" onclick="location.reload()">Start New Import</button>';
		html += '</p>';

		$('#nbuf-import-results').html(html).show();
	}
});
