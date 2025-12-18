/**
 * Profile Version History - JavaScript
 *
 * Handles timeline display, diff viewing, and version revert functionality.
 * Uses wp_localize_script for AJAX URL, nonce, permissions, and translations.
 *
 * @package NoBloat_User_Foundry
 * @since 1.4.0
 *
 * Expects NBUF_VersionHistory object with:
 * - ajax_url: WordPress AJAX URL
 * - nonce: Security nonce for AJAX requests
 * - can_revert: Boolean indicating if user can revert versions
 * - i18n: Translated strings object
 */

jQuery(document).ready(function($) {
	'use strict';

	const $viewer = $('.nbuf-version-history-viewer');

	/* Check if viewer exists */
	if (!$viewer.length) {
		return;
	}

	/* Check if localization object exists */
	if (typeof NBUF_VersionHistory === 'undefined') {
		$viewer.find('.nbuf-vh-loading').hide();
		$viewer.find('.nbuf-vh-empty').show().find('p:first').text('Version History is not available. Please check plugin settings.');
		return;
	}

	const userId = $viewer.data('user-id');
	const context = $viewer.data('context');
	const canRevert = NBUF_VersionHistory.can_revert;

	let currentPage = 1;
	let totalVersions = 0;
	let versions = [];

	/* Load timeline */
	function loadTimeline(page) {
		page = page || 1;
		currentPage = page;

		$viewer.find('.nbuf-vh-loading').show();
		$viewer.find('.nbuf-vh-timeline').hide();
		$viewer.find('.nbuf-vh-pagination').hide();
		$viewer.find('.nbuf-vh-empty').hide();

		$.ajax({
			url: NBUF_VersionHistory.ajax_url,
			type: 'POST',
			data: {
				action: 'nbuf_get_version_timeline',
				nonce: NBUF_VersionHistory.nonce,
				user_id: userId,
				page: page
			},
			success: function(response) {
				/* Check for WordPress AJAX error (action not registered) */
				if (response === 0 || response === '0' || response === '-1') {
					$viewer.find('.nbuf-vh-loading').hide();
					$viewer.find('.nbuf-vh-empty').show().find('p:first').text('Version History AJAX handler not available. The feature may be disabled.');
					return;
				}

				if (response.success) {
					versions = response.data.versions;
					totalVersions = response.data.total;

					if (versions.length === 0) {
						$viewer.find('.nbuf-vh-loading').hide();
						$viewer.find('.nbuf-vh-empty').show();
						return;
					}

					renderTimeline(versions);

					/* Update pagination */
					const totalPages = Math.ceil(totalVersions / response.data.per_page);
					$viewer.find('.nbuf-vh-page-info').text('Page ' + currentPage + ' of ' + totalPages);
					$viewer.find('.nbuf-vh-prev-page').prop('disabled', currentPage <= 1);
					$viewer.find('.nbuf-vh-next-page').prop('disabled', currentPage >= totalPages);

					$viewer.find('.nbuf-vh-loading').hide();
					$viewer.find('.nbuf-vh-timeline').show();
					$viewer.find('.nbuf-vh-pagination').show();
				} else {
					var message = (response.data && response.data.message) ? response.data.message : 'Failed to load version history.';
					$viewer.find('.nbuf-vh-loading').hide();
					$viewer.find('.nbuf-vh-empty').show().find('p:first').text(message);
				}
			},
			error: function(xhr, status, error) {
				$viewer.find('.nbuf-vh-loading').hide();
				$viewer.find('.nbuf-vh-empty').show().find('p:first').text('Failed to load version history: ' + error);
			}
		});
	}

	/* Render timeline */
	function renderTimeline(items) {
		const $timeline = $viewer.find('.nbuf-vh-timeline');
		$timeline.empty();

		const template = $('#nbuf-vh-timeline-item-template').html();

		items.forEach(function(item, index) {
			const fieldsChanged = item.fields_changed || [];
			const fieldsDisplay = fieldsChanged.length > 5
				? fieldsChanged.slice(0, 5).join(', ') + ' +' + (fieldsChanged.length - 5) + ' more'
				: fieldsChanged.join(', ');

			const changeTypeLabels = {
				'registration': NBUF_VersionHistory.i18n.registration,
				'profile_update': NBUF_VersionHistory.i18n.profile_update,
				'admin_update': NBUF_VersionHistory.i18n.admin_update,
				'import': NBUF_VersionHistory.i18n.import,
				'revert': NBUF_VersionHistory.i18n.revert
			};

			const icons = {
				'registration': 'admin-users',
				'profile_update': 'edit',
				'admin_update': 'admin-tools',
				'import': 'upload',
				'revert': 'backup'
			};

			/* Use pre-formatted timestamps from PHP (converted to user's browser timezone) */
			const dateStr = item.changed_at_date || item.changed_at;
			const timeStr = item.changed_at_time || '';

			let changedByText = NBUF_VersionHistory.i18n.self;
			if (item.changed_by) {
				changedByText = NBUF_VersionHistory.i18n.admin; // Could fetch actual admin name
			}

			let html = template
				.replace(/{{version_id}}/g, escapeHtml(String(item.id)))
				.replace(/{{icon}}/g, escapeHtml(icons[item.change_type] || 'edit'))
				.replace(/{{date}}/g, escapeHtml(dateStr))
				.replace(/{{time}}/g, escapeHtml(timeStr))
				.replace(/{{change_type}}/g, escapeHtml(item.change_type))
				.replace(/{{change_type_label}}/g, escapeHtml(changeTypeLabels[item.change_type] || item.change_type))
				.replace(/{{changed_by}}/g, escapeHtml(changedByText))
				.replace(/{{fields_changed}}/g, escapeHtml(fieldsDisplay));

			/* Handle optional IP address */
			if (item.ip_address) {
				html = html.replace(/{{#ip_address}}([\s\S]*?){{\/ip_address}}/g, '$1');
				html = html.replace(/{{ip_address}}/g, escapeHtml(item.ip_address));
			} else {
				html = html.replace(/{{#ip_address}}[\s\S]*?{{\/ip_address}}/g, '');
			}

			/* Handle previous version */
			if (index < items.length - 1) {
				html = html.replace(/{{#has_previous}}([\s\S]*?){{\/has_previous}}/g, '$1');
				html = html.replace(/{{previous_id}}/g, escapeHtml(String(items[index + 1].id)));
			} else {
				html = html.replace(/{{#has_previous}}[\s\S]*?{{\/has_previous}}/g, '');
			}

			/* Handle revert permission */
			if (canRevert && item.change_type !== 'registration') {
				html = html.replace(/{{#can_revert}}([\s\S]*?){{\/can_revert}}/g, '$1');
			} else {
				html = html.replace(/{{#can_revert}}[\s\S]*?{{\/can_revert}}/g, '');
			}

			$timeline.append(html);
		});
	}

	/* Pagination */
	$viewer.on('click', '.nbuf-vh-prev-page', function() {
		if (currentPage > 1) {
			loadTimeline(currentPage - 1);
		}
	});

	$viewer.on('click', '.nbuf-vh-next-page', function() {
		loadTimeline(currentPage + 1);
	});

	/* View Details (Snapshot) */
	$viewer.on('click', '.nbuf-vh-view-details', function(e) {
		e.preventDefault();
		e.stopPropagation();

		const versionId = $(this).data('version-id');
		const version = versions.find(v => v.id == versionId);

		if (!version || !version.snapshot_data) {
			alert('Version data not available.');
			return;
		}

		/* Show snapshot in modal */
		showSnapshotModal(version);
	});

	/* Compare Versions */
	$viewer.on('click', '.nbuf-vh-compare', function(e) {
		e.preventDefault();
		e.stopPropagation();

		const versionId = $(this).data('version-id');
		const previousId = $(this).data('previous-id');

		showDiffModal(previousId, versionId);
	});

	/* Revert Version */
	$viewer.on('click', '.nbuf-vh-revert', function(e) {
		e.preventDefault();
		e.stopPropagation();

		const versionId = $(this).data('version-id');

		if (!confirm(NBUF_VersionHistory.i18n.confirm_revert)) {
			return;
		}

		$.ajax({
			url: NBUF_VersionHistory.ajax_url,
			type: 'POST',
			data: {
				action: 'nbuf_revert_version',
				nonce: NBUF_VersionHistory.nonce,
				version_id: versionId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || NBUF_VersionHistory.i18n.revert_success);
					location.reload(); // Refresh page to update all data
				} else {
					alert(response.data.message || NBUF_VersionHistory.i18n.revert_failed);
				}
			},
			error: function() {
				alert(NBUF_VersionHistory.i18n.error);
			}
		});
	});

	/* Show snapshot modal */
	function showSnapshotModal(version) {
		const $modal = $viewer.find('.nbuf-vh-diff-modal');
		const $loading = $modal.find('.nbuf-vh-diff-loading');
		const $result = $modal.find('.nbuf-vh-diff-result');

		/* Update modal title */
		$modal.find('.nbuf-vh-diff-header h3').text('Profile Snapshot');

		$modal.show();
		$loading.hide();
		$result.show();

		/* Use pre-formatted timestamp from PHP (converted to user's browser timezone) */
		const date = version.changed_at_full || version.changed_at;
		const snapshot = version.snapshot_data || {};

		let html = '<div class="nbuf-vh-snapshot-header">';
		html += '<p><strong>Date:</strong> ' + date + '</p>';
		html += '<p><strong>Change Type:</strong> ' + (version.change_type || 'Unknown') + '</p>';
		html += '</div>';

		html += '<table class="nbuf-vh-diff-table nbuf-vh-snapshot-table">';
		html += '<thead><tr><th>Field</th><th>Value</th></tr></thead>';
		html += '<tbody>';

		/* Core user fields */
		const coreFields = ['user_login', 'user_email', 'display_name', 'first_name', 'last_name', 'description', 'user_url', 'role'];
		coreFields.forEach(function(field) {
			if (snapshot[field] !== undefined && snapshot[field] !== '') {
				html += '<tr>';
				html += '<td><strong>' + formatFieldName(field) + '</strong></td>';
				html += '<td>' + escapeHtml(String(snapshot[field])) + '</td>';
				html += '</tr>';
			}
		});

		/* NBUF user data */
		if (snapshot.nbuf_user_data) {
			Object.keys(snapshot.nbuf_user_data).forEach(function(field) {
				const value = snapshot.nbuf_user_data[field];
				if (value !== null && value !== '') {
					html += '<tr>';
					html += '<td><strong>' + formatFieldName(field) + '</strong></td>';
					html += '<td>' + escapeHtml(String(value)) + '</td>';
					html += '</tr>';
				}
			});
		}

		/* NBUF profile fields */
		if (snapshot.nbuf_profile) {
			Object.keys(snapshot.nbuf_profile).forEach(function(field) {
				const value = snapshot.nbuf_profile[field];
				if (value !== null && value !== '') {
					html += '<tr>';
					html += '<td><strong>' + formatFieldName(field) + '</strong></td>';
					html += '<td>' + escapeHtml(String(value)) + '</td>';
					html += '</tr>';
				}
			});
		}

		/* Meta fields */
		const metaFields = ['nbuf_2fa_enabled', 'nbuf_profile_privacy', 'nbuf_show_in_directory'];
		metaFields.forEach(function(field) {
			if (snapshot[field] !== undefined && snapshot[field] !== '') {
				html += '<tr>';
				html += '<td><strong>' + formatFieldName(field) + '</strong></td>';
				html += '<td>' + escapeHtml(String(snapshot[field])) + '</td>';
				html += '</tr>';
			}
		});

		html += '</tbody></table>';

		$result.html(html);
	}

	/* Format field name for display */
	function formatFieldName(field) {
		return field
			.replace(/^nbuf_/, '')
			.replace(/_/g, ' ')
			.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
	}

	/* Show diff modal */
	function showDiffModal(versionId1, versionId2) {
		const $modal = $viewer.find('.nbuf-vh-diff-modal');
		const $loading = $modal.find('.nbuf-vh-diff-loading');
		const $result = $modal.find('.nbuf-vh-diff-result');

		$modal.show();
		$loading.show();
		$result.hide().empty();

		$.ajax({
			url: NBUF_VersionHistory.ajax_url,
			type: 'POST',
			data: {
				action: 'nbuf_get_version_diff',
				nonce: NBUF_VersionHistory.nonce,
				version_id_1: versionId1,
				version_id_2: versionId2
			},
			success: function(response) {
				if (response.success) {
					renderDiff(response.data.diff, response.data.version1, response.data.version2);
					$loading.hide();
					$result.show();
				} else {
					alert(response.data.message || 'Failed to compare versions.');
					$modal.hide();
				}
			},
			error: function() {
				alert('An error occurred while comparing versions.');
				$modal.hide();
			}
		});
	}

	/* Render diff */
	function renderDiff(diff, version1, version2) {
		const $result = $viewer.find('.nbuf-vh-diff-result');

		/* Use pre-formatted timestamps from PHP (converted to user's browser timezone) */
		const date1 = version1.changed_at_full || version1.changed_at;
		const date2 = version2.changed_at_full || version2.changed_at;

		let html = '<div class="nbuf-vh-diff-header-info">';
		html += '<div class="nbuf-vh-diff-version"><strong>' + NBUF_VersionHistory.i18n.before + '</strong> ' + date1 + '</div>';
		html += '<div class="nbuf-vh-diff-version"><strong>' + NBUF_VersionHistory.i18n.after + '</strong> ' + date2 + '</div>';
		html += '</div>';

		html += '<table class="nbuf-vh-diff-table">';
		html += '<thead><tr><th>' + NBUF_VersionHistory.i18n.field + '</th><th>' + NBUF_VersionHistory.i18n.before_value + '</th><th>' + NBUF_VersionHistory.i18n.after_value + '</th></tr></thead>';
		html += '<tbody>';

		Object.keys(diff).forEach(function(key) {
			const item = diff[key];

			/* Only show changed fields */
			if (item.status === 'unchanged') {
				return;
			}

			const statusClass = 'nbuf-vh-diff-' + item.status;
			const beforeValue = item.before || '(empty)';
			const afterValue = item.after || '(empty)';

			html += '<tr class="' + statusClass + '">';
			html += '<td><strong>' + item.field + '</strong></td>';
			html += '<td>' + escapeHtml(String(beforeValue)) + '</td>';
			html += '<td>' + escapeHtml(String(afterValue)) + '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';

		$result.html(html);
	}

	/* Close diff modal */
	$viewer.on('click', '.nbuf-vh-close-diff, .nbuf-vh-diff-overlay', function(e) {
		e.preventDefault();
		e.stopPropagation();
		$viewer.find('.nbuf-vh-diff-modal').hide();
	});

	/* Escape HTML */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	/* Initial load */
	loadTimeline(1);
});
