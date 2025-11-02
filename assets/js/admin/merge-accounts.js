/**
 * Account Merger - Admin UI
 *
 * Handles the interactive account merging workflow for WordPress users.
 * Supports account selection, conflict resolution, and merge execution.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

(function($) {
	'use strict';

	/**
	 * Account Merger Object
	 */
	const NBUF_MergeAccounts = {

		/* State variables */
		selectedAccounts: [],
		primaryAccount: null,
		accountData: {},

		/**
		 * Initialize the merge accounts UI
		 */
		init: function() {
			/* Only run if merge form exists */
			if (!$('#nbuf-merge-form').length) {
				return;
			}

			this.bindEvents();
			this.checkPreselected();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$('#nbuf-load-accounts').on('click', this.loadAccounts.bind(this));
			$('#nbuf-confirm-merge').on('change', this.toggleMergeButton.bind(this));
			$('#nbuf-cancel-merge').on('click', this.cancelMerge.bind(this));
			$(document).on('click', '.nbuf-conflict-option', this.selectConflictOption);
		},

		/**
		 * Check if accounts were preselected via bulk action
		 */
		checkPreselected: function() {
			if (NBUF_Merge.preselected && NBUF_Merge.preselected.length > 0) {
				const self = this;
				setTimeout(function() {
					self.loadAccounts();
				}, 500);
			}
		},

		/**
		 * Load selected accounts via AJAX
		 */
		loadAccounts: function() {
			const self = this;
			self.selectedAccounts = $('#nbuf-merge-accounts').val();

			if (!self.selectedAccounts || self.selectedAccounts.length < 2) {
				alert(NBUF_Merge.i18n.minimum_users);
				return;
			}

			/* Show loading state */
			$('#nbuf-load-accounts').prop('disabled', true).text(NBUF_Merge.i18n.loading);

			/* Load account data via AJAX */
			$.post(NBUF_Merge.ajaxurl, {
				action: 'nbuf_load_merge_accounts',
				nonce: NBUF_Merge.nonce,
				accounts: self.selectedAccounts
			}, function(response) {
				$('#nbuf-load-accounts').prop('disabled', false).html(
					'<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> ' +
					NBUF_Merge.i18n.load_accounts
				);

				if (response.success) {
					self.accountData = response.data;
					self.showPrimarySelector();
				} else {
					alert(response.data.message || NBUF_Merge.i18n.error_loading);
				}
			}).fail(function() {
				$('#nbuf-load-accounts').prop('disabled', false).html(
					'<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> ' +
					NBUF_Merge.i18n.load_accounts
				);
				alert(NBUF_Merge.i18n.error_loading);
			});
		},

		/**
		 * Show primary account selector
		 */
		showPrimarySelector: function() {
			const self = this;
			let html = '';

			$.each(self.accountData.accounts, function(userId, user) {
				html += '<div class="nbuf-conflict-option" data-user-id="' + userId + '">';
				html += '<h4>' + self.escapeHtml(user.display_name) + '</h4>';
				html += '<p><strong>' + NBUF_Merge.i18n.email + ':</strong> ' + self.escapeHtml(user.user_email) + '<br>';
				html += '<strong>' + NBUF_Merge.i18n.username + ':</strong> ' + self.escapeHtml(user.user_login) + '<br>';
				html += '<strong>' + NBUF_Merge.i18n.user_id + ':</strong> ' + userId + '<br>';
				html += '<strong>' + NBUF_Merge.i18n.posts + ':</strong> ' + user.post_count + ' | ';
				html += '<strong>' + NBUF_Merge.i18n.comments + ':</strong> ' + user.comment_count + '</p>';
				html += '</div>';
			});

			$('#nbuf-primary-options').html(html);
			$('#nbuf-primary-selector').slideDown();

			/* Handle primary selection */
			$('#nbuf-primary-options .nbuf-conflict-option').on('click', function() {
				$('#nbuf-primary-options .nbuf-conflict-option').removeClass('selected');
				$(this).addClass('selected');
				self.primaryAccount = $(this).data('user-id');
				self.showEmailConsolidation();
			});
		},

		/**
		 * Show email consolidation preview
		 */
		showEmailConsolidation: function() {
			const self = this;
			const emails = [];
			const primaryEmail = self.accountData.accounts[self.primaryAccount].user_email;

			emails.push('<strong>' + NBUF_Merge.i18n.primary_email + ':</strong> ' +
				self.escapeHtml(primaryEmail) +
				' <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>');

			const secondaryEmails = [];
			$.each(self.accountData.accounts, function(userId, user) {
				if (userId != self.primaryAccount && user.user_email !== primaryEmail) {
					secondaryEmails.push(user.user_email);
				}
			});

			if (secondaryEmails.length > 0) {
				emails.push('<strong>' + NBUF_Merge.i18n.secondary_email + ':</strong> ' +
					self.escapeHtml(secondaryEmails[0] || NBUF_Merge.i18n.none));
			}
			if (secondaryEmails.length > 1) {
				emails.push('<strong>' + NBUF_Merge.i18n.tertiary_email + ':</strong> ' +
					self.escapeHtml(secondaryEmails[1] || NBUF_Merge.i18n.none));
			}
			if (secondaryEmails.length > 2) {
				emails.push('<p class="description" style="color: #d63638;">' +
					NBUF_Merge.i18n.email_limit_warning + '</p>');
			}

			$('#nbuf-email-preview').html('<p>' + emails.join('<br>') + '</p>');
			$('#nbuf-email-consolidation').slideDown();

			setTimeout(function() {
				self.showConflictResolution();
			}, 1000);
		},

		/**
		 * Show conflict resolution UI
		 */
		showConflictResolution: function() {
			const self = this;

			if (!self.accountData.conflicts || self.accountData.conflicts.length === 0) {
				$('#nbuf-conflicts-container').html('<p>' + NBUF_Merge.i18n.no_conflicts + '</p>');
			} else {
				let html = '<p>' + NBUF_Merge.i18n.conflict_instruction + '</p>';

				$.each(self.accountData.conflicts, function(index, conflict) {
					html += '<div class="nbuf-conflict-item">';
					html += '<h4>' + self.escapeHtml(conflict.label) + '</h4>';
					html += '<div class="nbuf-conflict-options">';

					$.each(conflict.values, function(userId, value) {
						if (value) {
							const userName = self.accountData.accounts[userId].display_name;
							const isSelected = (userId == self.primaryAccount) ? ' selected' : '';
							html += '<div class="nbuf-conflict-option' + isSelected + '" data-field="' +
								self.escapeHtml(conflict.field) + '" data-value="' + self.escapeHtml(value) + '">';
							html += '<strong>' + self.escapeHtml(userName) + ':</strong><br>' + self.escapeHtml(value);
							html += '</div>';
						}
					});

					html += '</div></div>';
				});

				$('#nbuf-conflicts-container').html(html);
			}

			$('#nbuf-conflict-resolution').slideDown();
			$('#nbuf-merge-options').slideDown();
			$('#nbuf-merge-execute').slideDown();
		},

		/**
		 * Handle conflict option selection
		 */
		selectConflictOption: function() {
			$(this).siblings('.nbuf-conflict-option').removeClass('selected');
			$(this).addClass('selected');
		},

		/**
		 * Toggle merge button based on confirmation checkbox
		 */
		toggleMergeButton: function() {
			$('#nbuf-execute-merge').prop('disabled', !$('#nbuf-confirm-merge').is(':checked'));
		},

		/**
		 * Cancel merge process
		 */
		cancelMerge: function(e) {
			e.preventDefault();
			if (confirm(NBUF_Merge.i18n.confirm_cancel)) {
				location.reload();
			}
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		NBUF_MergeAccounts.init();
	});

})(jQuery);
