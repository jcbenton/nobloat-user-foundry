/**
 * Merge Accounts - Admin UI
 *
 * Handles the two-account merge workflow with user search,
 * selection, field comparison, and merge execution.
 *
 * @package NoBloat_User_Foundry
 * @since   1.6.0
 */

(function ($) {
	'use strict';

	/**
	 * Merge Accounts Controller
	 */
	const NBUF_MergeAccounts = {

		/* Selected account data */
		sourceUser: null,
		targetUser: null,

		/* Search timeout for debouncing */
		searchTimeout: null,

		/**
		 * Initialize
		 */
		init: function () {
			if (!$('.nbuf-merge-accounts').length) {
				return;
			}

			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			/* Search input handlers with debouncing */
			$('#nbuf-source-search').on('input', function () {
				self.handleSearch($(this), 'source');
			});

			$('#nbuf-target-search').on('input', function () {
				self.handleSearch($(this), 'target');
			});

			/* Close dropdowns when clicking outside */
			$(document).on('click', function (e) {
				if (!$(e.target).closest('.nbuf-user-search').length) {
					$('.nbuf-search-results').hide();
				}
			});

			/* Remove selection handlers */
			$(document).on('click', '.remove-selection', function (e) {
				e.preventDefault();
				const type = $(this).data('type');
				self.clearSelection(type);
			});

			/* Confirmation checkbox */
			$('#nbuf-confirm-merge').on('change', function () {
				$('#nbuf-execute-merge').prop('disabled', !$(this).is(':checked'));
			});

			/* Cancel button */
			$('#nbuf-cancel-merge').on('click', function (e) {
				e.preventDefault();
				if (confirm(NBUF_Merge.i18n.confirm_cancel)) {
					location.reload();
				}
			});

			/* Form submission */
			$('#nbuf-merge-form').on('submit', function (e) {
				return self.handleSubmit(e);
			});
		},

		/**
		 * Handle search input with debouncing
		 *
		 * @param {jQuery} $input Input element
		 * @param {string} type   'source' or 'target'
		 */
		handleSearch: function ($input, type) {
			const self = this;
			const query = $input.val().trim();
			const $results = $('#nbuf-' + type + '-results');

			/* Clear previous timeout */
			if (self.searchTimeout) {
				clearTimeout(self.searchTimeout);
			}

			/* Minimum characters check */
			if (query.length < 2) {
				$results.hide().empty();
				return;
			}

			/* Show loading state */
			$results.html('<div class="nbuf-search-loading">' + NBUF_Merge.i18n.searching + '</div>').show();

			/* Debounce search */
			self.searchTimeout = setTimeout(function () {
				self.performSearch(query, type);
			}, 300);
		},

		/**
		 * Perform AJAX user search
		 *
		 * @param {string} query Search query
		 * @param {string} type  'source' or 'target'
		 */
		performSearch: function (query, type) {
			const self = this;
			const $results = $('#nbuf-' + type + '-results');

			$.post(NBUF_Merge.ajaxurl, {
				action: 'nbuf_search_users',
				nonce: NBUF_Merge.nonce,
				search: query
			}, function (response) {
				if (response.success && response.data.users.length > 0) {
					self.displaySearchResults(response.data.users, type);
				} else {
					$results.html('<div class="nbuf-search-no-results">' + NBUF_Merge.i18n.no_results + '</div>');
				}
			}).fail(function () {
				$results.html('<div class="nbuf-search-error">' + NBUF_Merge.i18n.error + '</div>');
			});
		},

		/**
		 * Display search results
		 *
		 * @param {Array}  users Array of user objects
		 * @param {string} type  'source' or 'target'
		 */
		displaySearchResults: function (users, type) {
			const self = this;
			const $results = $('#nbuf-' + type + '-results');
			const otherUser = (type === 'source') ? self.targetUser : self.sourceUser;

			let html = '';
			users.forEach(function (user) {
				/* Skip if this user is already selected as the other account */
				if (otherUser && otherUser.id === user.id) {
					return;
				}

				html += '<div class="nbuf-search-result" data-user-id="' + user.id + '" data-type="' + type + '">';
				html += '<img src="' + self.escapeHtml(user.avatar) + '" alt="" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:10px;">';
				html += '<span class="user-name">' + self.escapeHtml(user.display_name) + '</span>';
				html += '<span class="user-email"> &lt;' + self.escapeHtml(user.user_email) + '&gt;</span>';
				html += '<div class="user-meta">@' + self.escapeHtml(user.user_login) + ' &bull; ' + self.escapeHtml(user.roles) + '</div>';
				html += '</div>';
			});

			if (html === '') {
				html = '<div class="nbuf-search-no-results">' + NBUF_Merge.i18n.no_results + '</div>';
			}

			$results.html(html).show();

			/* Bind click handlers to results */
			$results.find('.nbuf-search-result').on('click', function () {
				const userId = $(this).data('user-id');
				const resultType = $(this).data('type');
				self.selectUser(userId, resultType);
			});
		},

		/**
		 * Select a user account
		 *
		 * @param {number} userId User ID
		 * @param {string} type   'source' or 'target'
		 */
		selectUser: function (userId, type) {
			const self = this;
			const $results = $('#nbuf-' + type + '-results');
			const $selected = $('#nbuf-' + type + '-selected');
			const $search = $('#nbuf-' + type + '-search');
			const $hiddenId = $('#nbuf-' + type + '-id');

			/* Hide results */
			$results.hide();

			/* Show loading in selected area */
			$selected.html('<p>' + NBUF_Merge.i18n.loading + '</p>').show();

			/* Get full user details */
			$.post(NBUF_Merge.ajaxurl, {
				action: 'nbuf_get_user_details',
				nonce: NBUF_Merge.nonce,
				user_id: userId
			}, function (response) {
				if (response.success) {
					/* Store user data */
					if (type === 'source') {
						self.sourceUser = response.data;
					} else {
						self.targetUser = response.data;
					}

					/* Update hidden field */
					$hiddenId.val(userId);

					/* Display selected user */
					self.displaySelectedUser(response.data, type);

					/* Clear and disable search input */
					$search.val('').prop('disabled', true);

					/* Check if both users are selected */
					self.checkBothSelected();
				} else {
					$selected.html('<p class="error">' + NBUF_Merge.i18n.error + '</p>');
				}
			}).fail(function () {
				$selected.html('<p class="error">' + NBUF_Merge.i18n.error + '</p>');
			});
		},

		/**
		 * Display selected user card
		 *
		 * @param {Object} user User data
		 * @param {string} type 'source' or 'target'
		 */
		displaySelectedUser: function (user, type) {
			const self = this;
			const $selected = $('#nbuf-' + type + '-selected');

			let html = '<a href="#" class="remove-selection" data-type="' + type + '">' + NBUF_Merge.i18n.remove + '</a>';
			html += '<div class="user-avatar"><img src="' + self.escapeHtml(user.avatar) + '" alt="" style="width:64px;height:64px;border-radius:50%;"></div>';
			html += '<div class="user-info">';
			html += '<div class="user-name">' + self.escapeHtml(user.display_name) + '</div>';
			html += '<div class="user-details">';
			html += '<strong>@' + self.escapeHtml(user.user_login) + '</strong><br>';
			html += self.escapeHtml(user.user_email) + '<br>';
			html += NBUF_Merge.i18n.posts + ': ' + user.post_count + ' &bull; ' + NBUF_Merge.i18n.comments + ': ' + user.comment_count + '<br>';
			html += 'Role: ' + self.escapeHtml(user.roles) + '<br>';
			html += 'Registered: ' + self.escapeHtml(user.registered);
			html += '</div></div>';

			$selected.html(html).show();
		},

		/**
		 * Clear user selection
		 *
		 * @param {string} type 'source' or 'target'
		 */
		clearSelection: function (type) {
			const self = this;
			const $selected = $('#nbuf-' + type + '-selected');
			const $search = $('#nbuf-' + type + '-search');
			const $hiddenId = $('#nbuf-' + type + '-id');

			/* Clear stored data */
			if (type === 'source') {
				self.sourceUser = null;
			} else {
				self.targetUser = null;
			}

			/* Reset UI */
			$selected.hide().empty();
			$search.prop('disabled', false).val('').focus();
			$hiddenId.val('');

			/* Hide merge options panel */
			$('#nbuf-merge-options-panel').hide();
		},

		/**
		 * Check if both users are selected and show merge options
		 */
		checkBothSelected: function () {
			const self = this;

			if (self.sourceUser && self.targetUser) {
				/* Validate they're different */
				if (self.sourceUser.id === self.targetUser.id) {
					alert(NBUF_Merge.i18n.same_account);
					self.clearSelection('source');
					return;
				}

				/* Update form hidden fields */
				$('#nbuf-form-source').val(self.sourceUser.id);
				$('#nbuf-form-target').val(self.targetUser.id);

				/* Update content counts */
				$('#nbuf-source-post-count').text('(' + self.sourceUser.post_count + ')');
				$('#nbuf-source-comment-count').text('(' + self.sourceUser.comment_count + ')');

				/* Populate field comparison tables */
				self.populateFieldTables();

				/* Show merge options panel */
				$('#nbuf-merge-options-panel').slideDown();
			}
		},

		/**
		 * Populate field comparison tables
		 */
		populateFieldTables: function () {
			const self = this;

			/* WordPress fields */
			const wpFields = [
				{ key: 'display_name', label: NBUF_Merge.i18n.display_name },
				{ key: 'first_name', label: NBUF_Merge.i18n.first_name },
				{ key: 'last_name', label: NBUF_Merge.i18n.last_name },
				{ key: 'nickname', label: NBUF_Merge.i18n.nickname },
				{ key: 'description', label: NBUF_Merge.i18n.description },
				{ key: 'user_url', label: NBUF_Merge.i18n.user_url }
			];

			let wpHtml = '';
			wpFields.forEach(function (field) {
				const sourceVal = self.sourceUser.wp_fields[field.key] || '';
				const targetVal = self.targetUser.wp_fields[field.key] || '';

				wpHtml += '<tr>';
				wpHtml += '<td><strong>' + self.escapeHtml(field.label) + '</strong></td>';
				wpHtml += '<td class="' + (sourceVal ? '' : 'field-empty') + '">' + (sourceVal ? self.escapeHtml(sourceVal) : NBUF_Merge.i18n.empty) + '</td>';
				wpHtml += '<td class="' + (targetVal ? '' : 'field-empty') + '">' + (targetVal ? self.escapeHtml(targetVal) : NBUF_Merge.i18n.empty) + '</td>';
				wpHtml += '<td>';
				wpHtml += '<select name="nbuf_field_' + field.key + '" style="width:100%;">';
				wpHtml += '<option value="target"' + (targetVal ? ' selected' : '') + '>' + NBUF_Merge.i18n.target + '</option>';
				wpHtml += '<option value="source"' + (!targetVal && sourceVal ? ' selected' : '') + '>' + NBUF_Merge.i18n.source + '</option>';
				wpHtml += '</select>';
				wpHtml += '</td>';
				wpHtml += '</tr>';
			});

			$('#nbuf-wp-fields-table tbody').html(wpHtml);

			/* Extended fields */
			const extendedFields = [
				{ key: 'phone', label: 'Phone Number' },
				{ key: 'mobile_phone', label: 'Mobile Phone' },
				{ key: 'work_phone', label: 'Work Phone' },
				{ key: 'address', label: 'Address' },
				{ key: 'city', label: 'City' },
				{ key: 'state', label: 'State' },
				{ key: 'postal_code', label: 'Postal Code' },
				{ key: 'country', label: 'Country' },
				{ key: 'company', label: 'Company' },
				{ key: 'job_title', label: 'Job Title' },
				{ key: 'secondary_email', label: 'Secondary Email' }
			];

			let extHtml = '';
			let hasExtendedData = false;

			extendedFields.forEach(function (field) {
				const sourceVal = self.sourceUser.extended_fields[field.key] || '';
				const targetVal = self.targetUser.extended_fields[field.key] || '';

				/* Only show if at least one account has data */
				if (sourceVal || targetVal) {
					hasExtendedData = true;

					extHtml += '<tr>';
					extHtml += '<td><strong>' + self.escapeHtml(field.label) + '</strong></td>';
					extHtml += '<td class="' + (sourceVal ? '' : 'field-empty') + '">' + (sourceVal ? self.escapeHtml(sourceVal) : NBUF_Merge.i18n.empty) + '</td>';
					extHtml += '<td class="' + (targetVal ? '' : 'field-empty') + '">' + (targetVal ? self.escapeHtml(targetVal) : NBUF_Merge.i18n.empty) + '</td>';
					extHtml += '<td>';
					extHtml += '<select name="nbuf_field_' + field.key + '" style="width:100%;">';
					extHtml += '<option value="target"' + (targetVal ? ' selected' : '') + '>' + NBUF_Merge.i18n.target + '</option>';
					extHtml += '<option value="source"' + (!targetVal && sourceVal ? ' selected' : '') + '>' + NBUF_Merge.i18n.source + '</option>';
					extHtml += '</select>';
					extHtml += '</td>';
					extHtml += '</tr>';
				}
			});

			if (hasExtendedData) {
				$('#nbuf-extended-fields-table tbody').html(extHtml);
				$('#nbuf-extended-fields-section').show();
			} else {
				$('#nbuf-extended-fields-section').hide();
			}
		},

		/**
		 * Handle form submission
		 *
		 * @param {Event} e Submit event
		 * @return {boolean} Whether to proceed with submission
		 */
		handleSubmit: function (e) {
			const self = this;

			/* Validate both users selected */
			if (!self.sourceUser || !self.targetUser) {
				alert('Please select both source and target accounts.');
				e.preventDefault();
				return false;
			}

			/* Validate confirmation checked */
			if (!$('#nbuf-confirm-merge').is(':checked')) {
				alert('Please confirm that you understand this action cannot be undone.');
				e.preventDefault();
				return false;
			}

			/* Disable submit button to prevent double submission */
			$('#nbuf-execute-merge').prop('disabled', true).html(
				'<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> ' +
				'Merging Accounts...'
			);

			return true;
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function (text) {
			if (text === null || text === undefined) {
				return '';
			}
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function (m) {
				return map[m];
			});
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		NBUF_MergeAccounts.init();
	});

})(jQuery);
