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
					alert(response.data.message || 'NBUF_Migration.i18n['Error loading plugin data.']');
				}
			},
			error: function() {
				alert('NBUF_Migration.i18n['Error loading plugin data. Please try again.']');
			},
			complete: function() {
				$('#nbuf-migration-loader').slideUp();
			}
		});
	}

	/* Display plugin status */
	function displayPluginStatus(data) {
		let html = '<h3>NBUF_Migration.i18n['Source Plugin Status']</h3>';
		html += '<div class="nbuf-status-grid">';

		html += '<div class="nbuf-status-item">';
		html += '<strong>NBUF_Migration.i18n['Plugin Status']</strong>';
		html += '<div class="value">' + (data.is_active ? '✓ Active' : '○ Inactive') + '</div>';
		html += '</div>';

		html += '<div class="nbuf-status-item">';
		html += '<strong>NBUF_Migration.i18n['Users with Data']</strong>';
		html += '<div class="value">' + data.user_count.toLocaleString() + '</div>';
		html += '</div>';

		if (data.profile_fields_count) {
			html += '<div class="nbuf-status-item">';
			html += '<strong>NBUF_Migration.i18n['Profile Fields']</strong>';
			html += '<div class="value">' + data.profile_fields_count + '</div>';
			html += '</div>';
		}

		if (data.restrictions_count) {
			html += '<div class="nbuf-status-item">';
			html += '<strong>NBUF_Migration.i18n['Content Restrictions']</strong>';
			html += '<div class="value">' + data.restrictions_count + '</div>';
			html += '</div>';
		}

		if (data.roles_count) {
			html += '<div class="nbuf-status-item">';
			html += '<strong>NBUF_Migration.i18n['Custom Roles']</strong>';
			html += '<div class="value">' + data.roles_count + '</div>';
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
			html += '<strong>NBUF_Migration.i18n['Profile Data']</strong>';
			html += '<p class="description">NBUF_Migration.i18n['Migrate user profile fields (phone, company, address, social media, etc.)']</p>';
			html += '</div>';
			html += '</label>';
		}

		if (supports.includes('restrictions')) {
			html += '<label>';
			html += '<input type="checkbox" class="nbuf-migration-type" value="restrictions">';
			html += '<div class="checkbox-label">';
			html += '<strong>NBUF_Migration.i18n['Content Restrictions']</strong>';
			html += '<p class="description">NBUF_Migration.i18n['Migrate post/page access restrictions and visibility settings']</p>';
			html += '</div>';
			html += '</label>';
		}

		if (supports.includes('roles')) {
			html += '<label>';
			html += '<input type="checkbox" class="nbuf-migration-type" value="roles">';
			html += '<div class="checkbox-label">';
			html += '<strong>NBUF_Migration.i18n['Custom User Roles']</strong>';
			html += '<p class="description">NBUF_Migration.i18n['Migrate custom roles with their capabilities and settings']</p>';
			html += '</div>';
			html += '</label>';
		}

		html += '</div>';

		$('#nbuf-migration-checkboxes').html(html);

		/* Load field mappings for checked types */
		$('.nbuf-migration-type:checked').each(function() {
			loadMigrationData($(this).val());
		});
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
			} else if (type === 'roles') {
				$('#nbuf-roles-mapping-section').slideUp();
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
		} else if (type === 'roles') {
			loadRolesPreview();
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
		html += '<th>NBUF_Migration.i18n['Source Field']</th>';
		html += '<th>NBUF_Migration.i18n['Sample Value']</th>';
		html += '<th>NBUF_Migration.i18n['Map To']</th>';
		html += '<th>NBUF_Migration.i18n['Status']</th>';
		html += '</tr></thead><tbody>';

		for (const [sourceField, data] of Object.entries(mappings)) {
			html += '<tr>';
			html += '<td><code>' + sourceField + '</code></td>';
			html += '<td>' + (data.sample || '<em>NBUF_Migration.i18n['No data']</em>') + '</td>';
			html += '<td>' + buildTargetSelect(sourceField, data.target) + '</td>';

			const status = data.auto_mapped ? 'auto' : (data.target ? 'manual' : 'unmapped');
			const statusText = status === 'auto' ? '✓ Auto' : (status === 'manual' ? '✎ Manual' : '○ Skip');
			html += '<td><span class="nbuf-mapping-status ' + status + '">' + statusText + '</span></td>';
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
		/* Get NBUF field registry */
		const targetFields = {
			'Basic Contact': ['phone', 'mobile_phone', 'work_phone', 'fax', 'preferred_name', 'nickname', 'pronouns', 'gender', 'date_of_birth', 'timezone', 'secondary_email'],
			'Address': ['address', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'],
			'Professional': ['company', 'job_title', 'department', 'division', 'employee_id', 'badge_number', 'manager_name', 'supervisor_email', 'office_location', 'hire_date', 'termination_date', 'work_email', 'employment_type', 'license_number', 'professional_memberships', 'security_clearance', 'shift', 'remote_status'],
			'Education': ['student_id', 'school_name', 'degree', 'major', 'graduation_year', 'gpa', 'certifications'],
			'Social Media': ['twitter', 'facebook', 'linkedin', 'instagram', 'github', 'youtube', 'tiktok', 'discord_username', 'whatsapp', 'telegram', 'viber', 'twitch', 'reddit', 'snapchat', 'soundcloud', 'vimeo', 'spotify', 'pinterest'],
			'Personal': ['bio', 'website', 'nationality', 'languages', 'emergency_contact']
		};

		let html = '<select class="nbuf-target-field" data-source="' + sourceField + '">';
		html += '<option value="">NBUF_Migration.i18n['-- Skip this field --']</option>';

		for (const [category, fields] of Object.entries(targetFields)) {
			html += '<optgroup label="' + category + '">';
			fields.forEach(field => {
				const selected = (field === selectedTarget) ? ' selected' : '';
				html += '<option value="' + field + '"' + selected + '>' + formatFieldName(field) + '</option>';
			});
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
			$('#nbuf-restrictions-preview').html('<p><em>NBUF_Migration.i18n['No content restrictions found.']</em></p>');
			return;
		}

		let html = '<p>NBUF_Migration.i18n['The following content restrictions will be migrated:']</p>';
		html += '<table class="wp-list-table widefat fixed striped">';
		html += '<thead><tr>';
		html += '<th>NBUF_Migration.i18n['Content']</th>';
		html += '<th>NBUF_Migration.i18n['Type']</th>';
		html += '<th>NBUF_Migration.i18n['Restriction']</th>';
		html += '</tr></thead><tbody>';

		restrictions.slice(0, 10).forEach(function(item) {
			html += '<tr>';
			html += '<td>' + item.title + '</td>';
			html += '<td>' + item.post_type + '</td>';
			html += '<td>' + item.restriction_summary + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';

		if (restrictions.length > 10) {
			html += '<p class="description">NBUF_Migration.i18n['Showing first 10 of'] ' + restrictions.length + ' NBUF_Migration.i18n['restrictions']</p>';
		}

		$('#nbuf-restrictions-preview').html(html);
	}

	/* Load roles preview */
	function loadRolesPreview() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_get_roles_preview',
				nonce: nonce,
				plugin_slug: selectedPlugin
			},
			success: function(response) {
				if (response.success) {
					displayRolesPreview(response.data.roles);
					$('#nbuf-roles-mapping-section').slideDown();
				}
			}
		});
	}

	/* Display roles preview */
	function displayRolesPreview(roles) {
		if (roles.length === 0) {
			$('#nbuf-roles-preview').html('<p><em>NBUF_Migration.i18n['No custom roles found.']</em></p>');
			return;
		}

		let html = '<p>NBUF_Migration.i18n['The following custom roles will be migrated:']</p>';
		html += '<table class="wp-list-table widefat fixed striped">';
		html += '<thead><tr>';
		html += '<th>NBUF_Migration.i18n['Role Name']</th>';
		html += '<th>NBUF_Migration.i18n['Role Key']</th>';
		html += '<th>NBUF_Migration.i18n['Capabilities']</th>';
		html += '<th>NBUF_Migration.i18n['Users']</th>';
		html += '<th>NBUF_Migration.i18n['Priority']</th>';
		html += '</tr></thead><tbody>';

		roles.forEach(function(role) {
			html += '<tr>';
			html += '<td><strong>' + role.role_name + '</strong></td>';
			html += '<td><code>' + role.role_key + '</code></td>';
			html += '<td>' + role.capabilities + '</td>';
			html += '<td>' + role.users + '</td>';
			html += '<td>' + role.priority + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';

		$('#nbuf-roles-preview').html(html);
	}

	/* Update migrate button state */
	function updateMigrateButton() {
		const hasSelection = $('.nbuf-migration-type:checked').length > 0;
		$('#nbuf-start-migration-btn').prop('disabled', !hasSelection);
	}

	/* Start migration */
	$('#nbuf-start-migration-btn').on('click', function() {
		if (!confirm('NBUF_Migration.i18n['Are you sure you want to start the migration? This will update data in your database.']')) {
			return;
		}

		/* Get selected migration types */
		migrationTypes = [];
		$('.nbuf-migration-type:checked').each(function() {
			migrationTypes.push($(this).val());
		});

		$('#nbuf-migration-progress').slideDown();
		$('#nbuf-start-migration-btn').prop('disabled', true);

		executeMigration();
	});

	/* Execute migration */
	function executeMigration() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_execute_migration',
				nonce: nonce,
				plugin_slug: selectedPlugin,
				migration_types: JSON.stringify(migrationTypes),
				field_mappings: JSON.stringify(fieldMappings)
			},
			success: function(response) {
				if (response.success) {
					updateProgress(100, 'NBUF_Migration.i18n['Complete']');
					displayResults(response.data);
				} else {
					alert(response.data.message || 'NBUF_Migration.i18n['Migration failed.']');
					$('#nbuf-start-migration-btn').prop('disabled', false);
				}
			},
			error: function() {
				alert('NBUF_Migration.i18n['Migration failed. Please try again.']');
				$('#nbuf-start-migration-btn').prop('disabled', false);
			}
		});
	}

	/* Update progress bar */
	function updateProgress(percent, status) {
		$('.nbuf-progress-fill').css('width', percent + '%').text(percent + '%');
		$('#nbuf-progress-status').text(status);
	}

	/* Display migration results */
	function displayResults(results) {
		let html = '<div class="notice notice-success inline"><p>';
		html += '<strong>NBUF_Migration.i18n['Migration Complete!']</strong><br><br>';

		for (const [type, data] of Object.entries(results)) {
			html += '<strong>' + formatFieldName(type) + ':</strong><br>';
			html += '• NBUF_Migration.i18n['Total:'] ' + data.total + '<br>';
			html += '• NBUF_Migration.i18n['Migrated:'] ' + data.migrated + '<br>';
			html += '• NBUF_Migration.i18n['Skipped:'] ' + data.skipped + '<br>';
			if (data.errors && data.errors.length > 0) {
				html += '• ' + NBUF_Migration.i18n['errors'] + ' ' + data.errors.length + '<br>';
			}
			html += '<br>';
		}

		html += '</p></div>';

		$('#nbuf-migration-results').html(html).slideDown();
	}
});
