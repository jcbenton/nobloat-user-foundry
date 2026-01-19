/**
 * Data Migration Tool - JavaScript
 *
 * Handles plugin selection, field mapping, and batch migration from other WordPress plugins.
 * Uses wp_localize_script for AJAX URL, nonce, plugin data, and translations.
 *
 * @package NoBloat_User_Foundry
 * @since 1.0.0
 *
 * Expects NBUF_Migration object with:
 * - ajax_url: WordPress AJAX URL
 * - nonce: Security nonce for AJAX requests
 * - plugins_data: Array of available migration plugins
 * - i18n: Translated strings object (42 strings)
 */

jQuery(document).ready(function($) {
	const nonce = NBUF_Migration.nonce;
	let selectedPlugin = null;
	let fieldMappings = {};
	let migrationTypes = [];
	let cumulativeResults = {}; /* Track totals across batches */

	/**
	 * Escape HTML to prevent XSS attacks
	 *
	 * @param {string} text - Text to escape
	 * @return {string} Escaped text safe for HTML insertion
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/* Available plugins data */
	const pluginsData = NBUF_Migration.plugins_data;

	/* Handle plugin selection */
	$('#nbuf-source-plugin').on('change', function() {
		const pluginSlug = $(this).val();

		if (!pluginSlug) {
			$('#nbuf-migration-options').slideUp();
			return;
		}

		selectedPlugin = pluginSlug;
		loadPluginData(pluginSlug);
	});

	/* Load plugin data */
	function loadPluginData(pluginSlug) {
		$('#nbuf-migration-loader').slideDown();
		$('#nbuf-migration-options').slideUp();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_load_migration_plugin',
				nonce: nonce,
				plugin_slug: pluginSlug
			},
			success: function(response) {
				if (response.success) {
					displayPluginStatus(response.data);
					displayMigrationTypes(response.data);
					$('#nbuf-migration-options').slideDown();
				} else {
					alert(response.data.message || NBUF_Migration.i18n.error_loading);
				}
			},
			error: function() {
				alert(NBUF_Migration.i18n.error_loading);
			},
			complete: function() {
				$('#nbuf-migration-loader').slideUp();
			}
		});
	}

	/* Display plugin status */
	function displayPluginStatus(data) {
		let html = '<h3>' + NBUF_Migration.i18n.source_plugin_status + '</h3>';
		html += '<div class="nbuf-status-grid">';

		html += '<div class="nbuf-status-item">';
		html += '<strong>' + NBUF_Migration.i18n.plugin_status + '</strong>';
		html += '<div class="value">' + (data.is_active ? '✓ ' + NBUF_Migration.i18n.active : '○ ' + NBUF_Migration.i18n.inactive) + '</div>';
		html += '</div>';

		html += '<div class="nbuf-status-item">';
		html += '<strong>' + NBUF_Migration.i18n.users_with_data + '</strong>';
		html += '<div class="value">' + data.user_count.toLocaleString() + '</div>';
		html += '</div>';

		if (data.profile_fields_count) {
			html += '<div class="nbuf-status-item">';
			html += '<strong>' + NBUF_Migration.i18n.profile_fields + '</strong>';
			html += '<div class="value">' + data.profile_fields_count + '</div>';
			html += '</div>';
		}

		if (data.restrictions_count) {
			html += '<div class="nbuf-status-item">';
			html += '<strong>' + NBUF_Migration.i18n.content_restrictions + '</strong>';
			html += '<div class="value">' + data.restrictions_count + '</div>';
			html += '</div>';
		}

		html += '</div>';

		$('#nbuf-plugin-status').html(html);
	}

	/* Display migration type checkboxes */
	function displayMigrationTypes(data) {
		const supports = pluginsData[selectedPlugin].supports;
		let html = '<div class="nbuf-checkbox-group">';

		if (supports.includes('profile_data')) {
			html += '<label>';
			html += '<input type="checkbox" class="nbuf-migration-type" value="profile_data" checked>';
			html += '<div class="checkbox-label">';
			html += '<strong>' + NBUF_Migration.i18n.profile_data + '</strong>';
			html += '<p class="description">' + NBUF_Migration.i18n.profile_data_desc + '</p>';
			html += '</div>';
			html += '</label>';

			/* Add copy photos sub-option */
			html += '<label style="margin-left: 30px;">';
			html += '<input type="checkbox" id="nbuf-copy-photos" value="1" checked>';
			html += '<div class="checkbox-label">';
			html += '<strong>' + NBUF_Migration.i18n.copy_photos + '</strong>';
			html += '<p class="description">' + NBUF_Migration.i18n.copy_photos_desc + '</p>';
			html += '</div>';
			html += '</label>';
		}

		if (supports.includes('restrictions')) {
			html += '<label>';
			html += '<input type="checkbox" class="nbuf-migration-type" value="restrictions">';
			html += '<div class="checkbox-label">';
			html += '<strong>' + NBUF_Migration.i18n.content_restrictions + '</strong>';
			html += '<p class="description">' + NBUF_Migration.i18n.restrictions_desc + '</p>';
			html += '</div>';
			html += '</label>';
		}

		/* Adopt orphaned roles - show if any orphaned roles exist */
		if (data.orphaned_roles_count > 0) {
			html += '<label>';
			html += '<input type="checkbox" class="nbuf-migration-type" value="adopt_roles" checked>';
			html += '<div class="checkbox-label">';
			html += '<strong>' + NBUF_Migration.i18n.adopt_roles + '</strong>';
			html += '<p class="description">' + NBUF_Migration.i18n.adopt_roles_desc.replace('%d', data.orphaned_roles_count) + '</p>';
			html += '</div>';
			html += '</label>';
		} else {
			/* No orphaned roles - show info message */
			html += '<div class="nbuf-roles-info" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-left: 4px solid #72aee6;">';
			html += '<span class="dashicons dashicons-info" style="color: #72aee6;"></span> ';
			html += '<strong>' + NBUF_Migration.i18n.user_roles + ':</strong> ';
			html += NBUF_Migration.i18n.roles_retained;
			html += '</div>';
		}

		html += '</div>';

		$('#nbuf-migration-checkboxes').html(html);

		/* Load field mappings for checked types */
		$('.nbuf-migration-type:checked').each(function() {
			loadMigrationData($(this).val());
		});

		/* Enable migrate button if any types are checked by default */
		updateMigrateButton();
	}

	/* Handle migration type checkbox changes */
	$(document).on('change', '.nbuf-migration-type', function() {
		const type = $(this).val();

		if ($(this).is(':checked')) {
			loadMigrationData(type);
			migrationTypes.push(type);
		} else {
			migrationTypes = migrationTypes.filter(t => t !== type);
			if (type === 'profile_data') {
				$('#nbuf-field-mapping-section').slideUp();
			} else if (type === 'restrictions') {
				$('#nbuf-restrictions-mapping-section').slideUp();
			}
		}

		updateMigrateButton();
	});

	/* Load migration data for type */
	function loadMigrationData(type) {
		if (type === 'profile_data') {
			loadFieldMappings();
		} else if (type === 'restrictions') {
			loadRestrictionsPreview();
		}
	}

	/* Load field mappings */
	function loadFieldMappings() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_get_field_mappings',
				nonce: nonce,
				plugin_slug: selectedPlugin
			},
			success: function(response) {
				if (response.success) {
					displayFieldMappings(response.data.mappings);
					$('#nbuf-field-mapping-section').slideDown();
				}
			}
		});
	}

	/* Display field mappings table */
	function displayFieldMappings(mappings) {
		let html = '<table class="nbuf-mapping-table">';
		html += '<thead><tr>';
		html += '<th>' + NBUF_Migration.i18n.source_field + '</th>';
		html += '<th>' + NBUF_Migration.i18n.map_to + '</th>';
		html += '<th>' + NBUF_Migration.i18n.status + '</th>';
		html += '<th>' + NBUF_Migration.i18n.sample_value + '</th>';
		html += '</tr></thead><tbody>';

		for (const [sourceField, data] of Object.entries(mappings)) {
			html += '<tr>';
			/* Show field label if available (for custom fields), otherwise show field key */
			const fieldDisplay = data.field_label ? escapeHtml(data.field_label) + ' <code style="font-size: 11px; color: #666;">(' + escapeHtml(sourceField) + ')</code>' : '<code>' + escapeHtml(sourceField) + '</code>';
			html += '<td>' + fieldDisplay + '</td>';
			html += '<td>' + buildTargetSelect(sourceField, data.target) + '</td>';

			const status = data.auto_mapped ? 'auto' : (data.target ? 'manual' : 'unmapped');
			const statusText = status === 'auto' ? '✓ ' + NBUF_Migration.i18n.auto : (status === 'manual' ? '✎ ' + NBUF_Migration.i18n.manual : '○ ' + NBUF_Migration.i18n.skip);
			html += '<td><span class="nbuf-mapping-status ' + status + '">' + statusText + '</span></td>';
			html += '<td>' + (data.sample ? escapeHtml(data.sample) : '<em>' + NBUF_Migration.i18n.no_data + '</em>') + '</td>';
			html += '</tr>';

			/* Store initial mapping */
			if (data.target) {
				fieldMappings[sourceField] = data.target;
			}
		}

		html += '</tbody></table>';
		$('#nbuf-field-mapping-table').html(html);
	}

	/* Build target field select dropdown */
	function buildTargetSelect(sourceField, selectedTarget) {
		/*
		 * Use field registry from PHP (NBUF_Profile_Data::get_field_registry())
		 * This ensures the migration tool always has the complete, up-to-date field list.
		 * Format: { 'Category Label': { 'field_key': 'Field Label', ... }, ... }
		 */
		const targetFields = NBUF_Migration.field_registry || {};

		let html = '<select class="nbuf-target-field" data-source="' + escapeHtml(sourceField) + '">';
		html += '<option value="">' + NBUF_Migration.i18n.skip_field + '</option>';

		for (const [category, fields] of Object.entries(targetFields)) {
			html += '<optgroup label="' + escapeHtml(category) + '">';
			/* fields is an object: { field_key: 'Field Label' } */
			for (const [fieldKey, fieldLabel] of Object.entries(fields)) {
				const selected = (fieldKey === selectedTarget) ? ' selected' : '';
				html += '<option value="' + escapeHtml(fieldKey) + '"' + selected + '>' + escapeHtml(fieldLabel) + '</option>';
			}
			html += '</optgroup>';
		}

		html += '</select>';
		return html;
	}

	/* Format field name for display */
	function formatFieldName(field) {
		return field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
	}

	/* Handle target field selection change */
	$(document).on('change', '.nbuf-target-field', function() {
		const sourceField = $(this).data('source');
		const targetField = $(this).val();

		if (targetField) {
			fieldMappings[sourceField] = targetField;
			/* Update status */
			$(this).closest('tr').find('.nbuf-mapping-status')
				.removeClass('unmapped').addClass('manual')
				.text('✎ Manual');
		} else {
			delete fieldMappings[sourceField];
			/* Update status */
			$(this).closest('tr').find('.nbuf-mapping-status')
				.removeClass('manual auto').addClass('unmapped')
				.text('○ Skip');
		}
	});

	/* Load restrictions preview */
	function loadRestrictionsPreview() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_get_restrictions_preview',
				nonce: nonce,
				plugin_slug: selectedPlugin
			},
			success: function(response) {
				if (response.success) {
					displayRestrictionsPreview(response.data.restrictions);
					$('#nbuf-restrictions-mapping-section').slideDown();
				}
			}
		});
	}

	/* Display restrictions preview */
	function displayRestrictionsPreview(restrictions) {
		if (restrictions.length === 0) {
			$('#nbuf-restrictions-preview').html('<p><em>' + NBUF_Migration.i18n.no_restrictions + '</em></p>');
			return;
		}

		let html = '<p>' + NBUF_Migration.i18n.restrictions_will_migrate + '</p>';
		html += '<table class="wp-list-table widefat fixed striped">';
		html += '<thead><tr>';
		html += '<th>' + NBUF_Migration.i18n.content + '</th>';
		html += '<th>' + NBUF_Migration.i18n.type + '</th>';
		html += '<th>' + NBUF_Migration.i18n.restriction + '</th>';
		html += '</tr></thead><tbody>';

		restrictions.slice(0, 10).forEach(function(item) {
			html += '<tr>';
			html += '<td>' + escapeHtml(item.title) + '</td>';
			html += '<td>' + escapeHtml(item.post_type) + '</td>';
			html += '<td>' + escapeHtml(item.restriction_summary) + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';

		if (restrictions.length > 10) {
			html += '<p class="description">' + NBUF_Migration.i18n.showing_first_10.replace('%d', restrictions.length) + '</p>';
		}

		$('#nbuf-restrictions-preview').html(html);
	}

	/* Update migrate button state */
	function updateMigrateButton() {
		const hasSelection = $('.nbuf-migration-type:checked').length > 0;
		$('#nbuf-start-migration-btn').prop('disabled', !hasSelection);
	}

	/* Start migration */
	$('#nbuf-start-migration-btn').on('click', function() {
		if (!confirm(NBUF_Migration.i18n.confirm_migration)) {
			return;
		}

		/* Get selected migration types */
		migrationTypes = [];
		$('.nbuf-migration-type:checked').each(function() {
			migrationTypes.push($(this).val());
		});

		/* Get copy photos setting */
		const copyPhotos = $('#nbuf-copy-photos').is(':checked');

		/* Reset cumulative results for new migration */
		cumulativeResults = {};

		$('#nbuf-migration-progress').slideDown();
		$('#nbuf-start-migration-btn').prop('disabled', true);

		executeMigrationBatch(0, copyPhotos);
	});

	/* Execute migration in batches */
	function executeMigrationBatch(offset, copyPhotos) {
		const batchSize = 50;

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_execute_migration_batch',
				nonce: nonce,
				plugin_slug: selectedPlugin,
				migration_types: JSON.stringify(migrationTypes),
				field_mappings: JSON.stringify(fieldMappings),
				batch_offset: offset,
				batch_size: batchSize,
				copy_photos: copyPhotos
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;

					/* Accumulate results across batches */
					accumulateBatchResults(data.results);

					/* Update progress bar with sanitized values */
					const processed = parseInt(data.processed, 10) || 0;
					const total = parseInt(data.total, 10) || 1;
					const percent = Math.round((processed / total) * 100);
					const statusText = 'Processing ' + processed + ' of ' + total + ' users...';
					updateProgress(percent, escapeHtml(statusText));

					/* Continue batch processing if not completed */
					if (!data.completed) {
						executeMigrationBatch(data.offset, copyPhotos);
					} else {
						/* Migration complete - display cumulative results */
						updateProgress(100, 'Complete!');
						displayResults(cumulativeResults, data.users_with_data);
					}
				} else {
					const errorMsg = (response.data && response.data.message) ? escapeHtml(response.data.message) : 'Migration failed.';
					alert(errorMsg);
					$('#nbuf-start-migration-btn').prop('disabled', false);
					$('#nbuf-migration-progress').slideUp();
				}
			},
			error: function() {
				alert('Migration failed. Please try again.');
				$('#nbuf-start-migration-btn').prop('disabled', false);
				$('#nbuf-migration-progress').slideUp();
			}
		});
	}

	/* Accumulate batch results into cumulative totals */
	function accumulateBatchResults(batchResults) {
		for (const [type, data] of Object.entries(batchResults)) {
			if (!cumulativeResults[type]) {
				cumulativeResults[type] = {
					imported: 0,
					migrated: 0,
					skipped: 0,
					total: 0,
					errors: []
				};
			}

			cumulativeResults[type].imported += (data.imported || 0);
			cumulativeResults[type].migrated += (data.migrated || 0);
			cumulativeResults[type].skipped += (data.skipped || 0);
			cumulativeResults[type].total += (data.total || 0);

			if (data.errors && data.errors.length > 0) {
				cumulativeResults[type].errors = cumulativeResults[type].errors.concat(data.errors);
			}
		}
	}

	/* Update progress bar */
	function updateProgress(percent, status) {
		$('.nbuf-progress-fill').css('width', percent + '%').text(percent + '%');
		$('#nbuf-progress-status').text(status);
	}

	/* Display migration results */
	function displayResults(results, usersWithData) {
		let html = '<div class="notice notice-success inline"><p>';
		html += '<strong>' + NBUF_Migration.i18n.migration_complete + '</strong><br><br>';

		for (const [type, data] of Object.entries(results)) {
			const typeLabel = formatFieldName(type);
			const migrated = data.imported || data.migrated || 0;
			const total = data.total || migrated;
			const skipped = data.skipped || 0;
			const errors = (data.errors && data.errors.length > 0) ? data.errors.length : 0;

			html += '<strong>' + typeLabel + ':</strong><br>';
			html += '• ' + NBUF_Migration.i18n.processed + ': ' + total + ' users<br>';
			html += '• ' + NBUF_Migration.i18n.migrated + ': ' + migrated + '<br>';

			if (skipped > 0) {
				html += '• ' + NBUF_Migration.i18n.skipped + ': ' + skipped + '<br>';
			}

			if (errors > 0) {
				html += '• ' + NBUF_Migration.i18n.errors + ': ' + errors + '<br>';
			}

			html += '<br>';
		}

		html += '</p></div>';

		$('#nbuf-migration-results').html(html).slideDown();
	}
});
