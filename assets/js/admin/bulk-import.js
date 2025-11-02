/**
 * Bulk User CSV Import - JavaScript
 *
 * Handles AJAX-based CSV upload, validation, and batch import processing.
 * Pure JavaScript with no PHP dependencies - all dynamic data passed via ajaxurl.
 *
 * @package NoBloat_User_Foundry
 * @since 1.3.0
 */

jQuery(document).ready(function($) {
	let currentTransientKey = null;

	/* Handle CSV upload and validation */
	$('#nbuf-import-form').on('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);
		formData.append('action', 'nbuf_upload_import_csv');
		formData.append('nonce', $('#nbuf_import_nonce').val());

		$('#nbuf-upload-csv').prop('disabled', true).text('Validating...');
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
					showPreview(response.data);
				} else {
					alert('Error: ' + response.data.message);
					$('#nbuf-upload-csv').prop('disabled', false).text('Upload & Validate CSV');
				}
			},
			error: function() {
				alert('An error occurred during upload.');
				$('#nbuf-upload-csv').prop('disabled', false).text('Upload & Validate CSV');
			}
		});
	});

	/* Show validation preview */
	function showPreview(data) {
		const preview = data.preview;
		let html = '<div class="nbuf-import-preview">';

		html += '<h4>Validation Results</h4>';
		html += '<p><strong>Total Rows:</strong> ' + data.total_rows + '</p>';
		html += '<p><strong>Valid:</strong> <span style="color: green;">' + preview.valid + '</span></p>';
		html += '<p><strong>Invalid:</strong> <span style="color: red;">' + preview.invalid + '</span></p>';

		/* Show errors if any */
		if (preview.errors.length > 0) {
			html += '<h4 style="margin-top: 20px;">Errors Found</h4>';
			html += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';
			html += '<table style="width: 100%; border-collapse: collapse;">';
			html += '<tr><th style="text-align: left; padding: 5px; border-bottom: 2px solid #ddd;">Line</th><th style="text-align: left; padding: 5px; border-bottom: 2px solid #ddd;">Error</th></tr>';

			preview.errors.slice(0, 20).forEach(function(error) {
				html += '<tr>';
				html += '<td style="padding: 5px; border-bottom: 1px solid #eee;">' + error.line + '</td>';
				html += '<td style="padding: 5px; border-bottom: 1px solid #eee;">' + error.message + '</td>';
				html += '</tr>';
			});

			if (preview.errors.length > 20) {
				html += '<tr><td colspan="2" style="padding: 10px; text-align: center; font-style: italic;">... and ' + (preview.errors.length - 20) + ' more errors</td></tr>';
			}

			html += '</table>';
			html += '</div>';
		}

		/* Show sample valid rows */
		if (preview.samples.length > 0) {
			html += '<h4 style="margin-top: 20px;">Sample Valid Rows (Preview)</h4>';
			html += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';

			preview.samples.forEach(function(sample) {
				html += '<p><strong>Line ' + sample.line + ':</strong> ' + sample.data.user_email + ' (' + sample.data.user_login + ')</p>';
			});

			html += '</div>';
		}

		/* Import button */
		if (preview.valid > 0) {
			html += '<p style="margin-top: 20px;">';
			html += '<button type="button" class="button button-primary" id="nbuf-start-import">Import ' + preview.valid + ' Valid Users</button> ';
			html += '<button type="button" class="button" id="nbuf-cancel-import">Cancel</button>';
			html += '</p>';
		} else {
			html += '<p style="margin-top: 20px; color: red;"><strong>Cannot import: All rows have errors.</strong></p>';
		}

		html += '</div>';

		$('#nbuf-import-results').html(html).show();
		$('#nbuf-upload-csv').prop('disabled', false).text('Upload & Validate CSV');
	}

	/* Start import */
	$(document).on('click', '#nbuf-start-import', function() {
		if (!confirm('Start importing users? This cannot be undone.')) {
			return;
		}

		$('#nbuf-import-results').hide();
		$('#nbuf-import-progress').show();
		processImportBatch(0);
	});

	/* Cancel import */
	$(document).on('click', '#nbuf-cancel-import', function() {
		currentTransientKey = null;
		$('#nbuf-import-results').hide();
		$('#nbuf-import-form')[0].reset();
	});

	/* Process import in batches */
	function processImportBatch(offset) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_import_users',
				nonce: $('#nbuf_import_nonce').val(),
				transient_key: currentTransientKey,
				offset: offset
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;
					const percent = Math.round((data.processed / data.total) * 100);

					$('#nbuf-progress-bar').css('width', percent + '%');
					$('#nbuf-progress-text').text('Processed ' + data.processed + ' of ' + data.total + ' rows...');

					if (data.complete) {
						showImportResults(data);
					} else {
						/* Continue with next batch */
						processImportBatch(data.processed);
					}
				} else {
					alert('Import error: ' + response.data.message);
					$('#nbuf-import-progress').hide();
				}
			},
			error: function() {
				alert('An error occurred during import.');
				$('#nbuf-import-progress').hide();
			}
		});
	}

	/* Show final import results */
	function showImportResults(data) {
		let html = '<div class="notice notice-success"><p><strong>Import Complete!</strong></p></div>';

		html += '<table class="widefat" style="margin-top: 15px;">';
		html += '<tr><th>Imported Successfully</th><td><strong style="color: green;">' + data.success + '</strong></td></tr>';
		html += '<tr><th>Skipped/Errors</th><td><strong style="color: red;">' + data.skipped + '</strong></td></tr>';
		html += '<tr><th>Total Processed</th><td><strong>' + data.total + '</strong></td></tr>';
		html += '</table>';

		if (data.errors > 0) {
			html += '<p style="margin-top: 15px;">';
			html += '<a href="' + ajaxurl + '?action=nbuf_download_error_report&error_key=' + data.error_key + '&nonce=' + $('#nbuf_import_nonce').val() + '" class="button">Download Error Report (CSV)</a>';
			html += '</p>';
		}

		html += '<p style="margin-top: 20px;"><button type="button" class="button" onclick="location.reload()">Start New Import</button></p>';

		$('#nbuf-import-progress').hide();
		$('#nbuf-import-results').html(html).show();
	}
});
