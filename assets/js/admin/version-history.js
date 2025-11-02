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
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_get_version_timeline',
				nonce: NBUF_VersionHistory.nonce,
				user_id: userId,
				page: page
			},
			success: function(response) {
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
					alert(response.data.message || 'Failed to load version history.');
					$viewer.find('.nbuf-vh-loading').hide();
				}
			},
			error: function() {
				alert('An error occurred while loading version history.');
				$viewer.find('.nbuf-vh-loading').hide();
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
				'registration': NBUF_VersionHistory.i18n.registration',
				'profile_update': NBUF_VersionHistory.i18n.profile_update',
				'admin_update': NBUF_VersionHistory.i18n.admin_update',
				'import': NBUF_VersionHistory.i18n.import',
				'revert': NBUF_VersionHistory.i18n.reverted'
			};

			const icons = {
				'registration': 'admin-users',
				'profile_update': 'edit',
				'admin_update': 'admin-tools',
				'import': 'upload',
				'revert': 'backup'
			};

			const date = new Date(item.changed_at);
			const dateStr = date.toLocaleDateString();
			const timeStr = date.toLocaleTimeString();

			let changedByText = NBUF_VersionHistory.i18n.self';
			if (item.changed_by) {
				changedByText = NBUF_VersionHistory.i18n.admin'; // Could fetch actual admin name
			}

			let html = template
				.replace(/{{version_id}}/g, item.id)
				.replace(/{{icon}}/g, icons[item.change_type] || 'edit')
				.replace(/{{date}}/g, dateStr)
				.replace(/{{time}}/g, timeStr)
				.replace(/{{change_type}}/g, item.change_type)
				.replace(/{{change_type_label}}/g, changeTypeLabels[item.change_type] || item.change_type)
				.replace(/{{changed_by}}/g, changedByText)
				.replace(/{{fields_changed}}/g, fieldsDisplay);

			/* Handle optional IP address */
			if (item.ip_address) {
				html = html.replace(/{{#ip_address}}([\s\S]*?){{\/ip_address}}/g, '$1');
				html = html.replace(/{{ip_address}}/g, item.ip_address);
			} else {
				html = html.replace(/{{#ip_address}}[\s\S]*?{{\/ip_address}}/g, '');
			}

			/* Handle previous version */
			if (index < items.length - 1) {
				html = html.replace(/{{#has_previous}}([\s\S]*?){{\/has_previous}}/g, '$1');
				html = html.replace(/{{previous_id}}/g, items[index + 1].id);
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
	$viewer.on('click', '.nbuf-vh-view-details', function() {
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
	$viewer.on('click', '.nbuf-vh-compare', function() {
		const versionId = $(this).data('version-id');
		const previousId = $(this).data('previous-id');

		showDiffModal(previousId, versionId);
	});

	/* Revert Version */
	$viewer.on('click', '.nbuf-vh-revert', function() {
		const versionId = $(this).data('version-id');

		if (!confirm(NBUF_VersionHistory.i18n.confirm_revert')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'nbuf_revert_version',
				nonce: NBUF_VersionHistory.nonce,
				version_id: versionId
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || NBUF_VersionHistory.i18n.revert_success');
					loadTimeline(1); // Reload timeline
				} else {
					alert(response.data.message || NBUF_VersionHistory.i18n.revert_failed');
				}
			},
			error: function() {
				alert(NBUF_VersionHistory.i18n.error');
			}
		});
	});

	/* Show snapshot modal */
	function showSnapshotModal(version) {
		alert('Snapshot viewer coming soon!\n\nVersion ID: ' + version.id + '\nChange Type: ' + version.change_type);
		// TODO: Implement full snapshot viewer in future update
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
			url: ajaxurl,
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

		const date1 = new Date(version1.changed_at).toLocaleString();
		const date2 = new Date(version2.changed_at).toLocaleString();

		let html = '<div class="nbuf-vh-diff-header-info">';
		html += '<div class="nbuf-vh-diff-version"><strong>NBUF_VersionHistory.i18n.before</strong> ' + date1 + '</div>';
		html += '<div class="nbuf-vh-diff-version"><strong>NBUF_VersionHistory.i18n.after</strong> ' + date2 + '</div>';
		html += '</div>';

		html += '<table class="nbuf-vh-diff-table">';
		html += '<thead><tr><th>NBUF_VersionHistory.i18n.field</th><th>NBUF_VersionHistory.i18n.before_value</th><th>NBUF_VersionHistory.i18n.after_value</th></tr></thead>';
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
	$viewer.on('click', '.nbuf-vh-close-diff, .nbuf-vh-diff-overlay', function() {
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
