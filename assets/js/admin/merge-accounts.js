/**
 * Account Merger - Admin UI
 *
 * Handles the interactive account merging workflow for WordPress users.
 * Supports account selection, conflict resolution, and merge execution.
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

(function ($) {
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
		init: function () {
			/* Only run if merge form exists */
			if ( ! $( '#nbuf-merge-form' ).length) {
				return;
			}

			this.bindEvents();
			this.checkPreselected();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			$( '#nbuf-load-accounts' ).on( 'click', this.loadAccounts.bind( this ) );
			$( '#nbuf-confirm-merge' ).on( 'change', this.toggleMergeButton.bind( this ) );
			$( '#nbuf-cancel-merge' ).on( 'click', this.cancelMerge.bind( this ) );
			$( document ).on( 'click', '.nbuf-conflict-option', this.selectConflictOption );
			$( '#nbuf-merge-form' ).on( 'submit', this.handleFormSubmit.bind( this ) );
		},

		/**
		 * Check if accounts were preselected via bulk action
		 */
		checkPreselected: function () {
			if (NBUF_Merge.preselected && NBUF_Merge.preselected.length > 0) {
				const self = this;
				setTimeout(
					function () {
						self.loadAccounts();
					},
					500
				);
			}
		},

		/**
		 * Load selected accounts via AJAX
		 */
		loadAccounts: function () {
			const self            = this;
			self.selectedAccounts = $( '#nbuf-merge-accounts' ).val();

			if ( ! self.selectedAccounts || self.selectedAccounts.length < 2) {
				alert( NBUF_Merge.i18n.minimum_users );
				return;
			}

			/* Show loading state */
			$( '#nbuf-load-accounts' ).prop( 'disabled', true ).text( NBUF_Merge.i18n.loading );

			/* Load account data via AJAX */
			$.post(
				NBUF_Merge.ajaxurl,
				{
					action: 'nbuf_load_merge_accounts',
					nonce: NBUF_Merge.nonce,
					accounts: self.selectedAccounts
				},
				function (response) {
					$( '#nbuf-load-accounts' ).prop( 'disabled', false ).html(
						'<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> ' +
						NBUF_Merge.i18n.load_accounts
					);

					if (response.success) {
						self.accountData = response.data;
						self.showPrimarySelector();
					} else {
						alert( response.data.message || NBUF_Merge.i18n.error_loading );
					}
				}
			).fail(
				function () {
					$( '#nbuf-load-accounts' ).prop( 'disabled', false ).html(
						'<span class="dashicons dashicons-arrow-right-alt" style="vertical-align: middle;"></span> ' +
						NBUF_Merge.i18n.load_accounts
					);
					alert( NBUF_Merge.i18n.error_loading );
				}
			);
		},

		/**
		 * Show primary account selector
		 */
		showPrimarySelector: function () {
			const self = this;
			let html   = '';

			$.each(
				self.accountData.accounts,
				function (userId, user) {
					html += '<div class="nbuf-conflict-option" data-user-id="' + userId + '">';
					html += '<h4>' + self.escapeHtml( user.display_name ) + '</h4>';
					html += '<p><strong>' + NBUF_Merge.i18n.email + ':</strong> ' + self.escapeHtml( user.user_email ) + '<br>';
					html += '<strong>' + NBUF_Merge.i18n.username + ':</strong> ' + self.escapeHtml( user.user_login ) + '<br>';
					html += '<strong>' + NBUF_Merge.i18n.user_id + ':</strong> ' + userId + '<br>';
					html += '<strong>' + NBUF_Merge.i18n.posts + ':</strong> ' + user.post_count + ' | ';
					html += '<strong>' + NBUF_Merge.i18n.comments + ':</strong> ' + user.comment_count + '</p>';
					html += '</div>';
				}
			);

			$( '#nbuf-primary-options' ).html( html );
			$( '#nbuf-primary-selector' ).slideDown();

			/* Handle primary selection */
			$( '#nbuf-primary-options .nbuf-conflict-option' ).on(
				'click',
				function () {
					$( '#nbuf-primary-options .nbuf-conflict-option' ).removeClass( 'selected' );
					$( this ).addClass( 'selected' );
					self.primaryAccount = $( this ).data( 'user-id' );
					self.showEmailConsolidation();
				}
			);
		},

		/**
		 * Show email consolidation preview
		 */
		showEmailConsolidation: function () {
			const self         = this;
			const emails       = [];
			const primaryEmail = self.accountData.accounts[self.primaryAccount].user_email;

			emails.push(
				'<strong>' + NBUF_Merge.i18n.primary_email + ':</strong> ' +
				self.escapeHtml( primaryEmail ) +
				' <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>'
			);

			const secondaryEmails = [];
			$.each(
				self.accountData.accounts,
				function (userId, user) {
					if (userId != self.primaryAccount && user.user_email !== primaryEmail) {
						secondaryEmails.push( user.user_email );
					}
				}
			);

			if (secondaryEmails.length > 0) {
				emails.push(
					'<strong>' + NBUF_Merge.i18n.secondary_email + ':</strong> ' +
					self.escapeHtml( secondaryEmails[0] || NBUF_Merge.i18n.none )
				);
			}
			if (secondaryEmails.length > 1) {
				emails.push(
					'<strong>' + NBUF_Merge.i18n.tertiary_email + ':</strong> ' +
					self.escapeHtml( secondaryEmails[1] || NBUF_Merge.i18n.none )
				);
			}
			if (secondaryEmails.length > 2) {
				emails.push(
					'<p class="description" style="color: #d63638;">' +
					NBUF_Merge.i18n.email_limit_warning + '</p>'
				);
			}

			$( '#nbuf-email-preview' ).html( '<p>' + emails.join( '<br>' ) + '</p>' );
			$( '#nbuf-email-consolidation' ).slideDown();

			setTimeout(
				function () {
					self.showConflictResolution();
				},
				1000
			);
		},

		/**
		 * Show conflict resolution UI
		 */
		showConflictResolution: function () {
			const self = this;

			if ( ! self.accountData.conflicts || self.accountData.conflicts.length === 0) {
				$( '#nbuf-conflicts-container' ).html( '<p>' + NBUF_Merge.i18n.no_conflicts + '</p>' );
			} else {
				let html = '<p>' + NBUF_Merge.i18n.conflict_instruction + '</p>';

				$.each(
					self.accountData.conflicts,
					function (index, conflict) {
						if (conflict.type === 'photo') {
							html += self.renderPhotoConflict( conflict );
						} else if (conflict.type === 'role') {
							html += self.renderRoleConflict( conflict );
						} else {
							// Standard profile field conflict.
							html += '<div class="nbuf-conflict-item">';
							html += '<h4>' + self.escapeHtml( conflict.label ) + '</h4>';
							html += '<div class="nbuf-conflict-options">';

							$.each(
								conflict.values,
								function (userId, value) {
									if (value) {
										const userName   = self.accountData.accounts[userId].display_name;
										const isSelected = (userId == self.primaryAccount) ? ' selected' : '';
										html            += '<div class="nbuf-conflict-option' + isSelected + '" data-field="' +
										self.escapeHtml( conflict.field ) + '" data-value="' + self.escapeHtml( value ) + '">';
										html            += '<strong>' + self.escapeHtml( userName ) + ':</strong><br>' + self.escapeHtml( value );
										html            += '</div>';
									}
								}
							);

							html += '</div></div>';
						}
					}
				);

				$( '#nbuf-conflicts-container' ).html( html );
			}

			$( '#nbuf-conflict-resolution' ).slideDown();
			$( '#nbuf-merge-options' ).slideDown();
			$( '#nbuf-merge-execute' ).slideDown();
		},

		/**
		 * Render photo conflict UI with image previews
		 *
		 * @param {Object} conflict Photo conflict data
		 * @return {string} HTML for photo conflict
		 */
		renderPhotoConflict: function (conflict) {
			const self = this;
			let html   = '<div class="nbuf-conflict-item nbuf-photo-conflict">';
			html      += '<h4>' + self.escapeHtml( conflict.label ) + '</h4>';
			html      += '<div class="nbuf-conflict-options nbuf-photo-options">';

			$.each(
				conflict.values,
				function (userId, photoData) {
					if (photoData && photoData.url) {
						/* SECURITY: Validate photo URL to prevent XSS */
						const safeUrl = self.validateUrl( photoData.url );
						if ( ! safeUrl) {
							console.warn( 'NBUF Security: Skipping photo with invalid URL for user ' + userId );
							return; /* Skip this photo. */
						}

						const userName   = self.accountData.accounts[userId].display_name;
						const isSelected = (userId == self.primaryAccount) ? ' selected' : '';
						html            += '<div class="nbuf-conflict-option nbuf-photo-option' + isSelected + '" ' +
						'data-field="' + self.escapeHtml( conflict.field ) + '" ' +
						'data-user-id="' + userId + '">';
						html            += '<img src="' + safeUrl + '" ' +
						'alt="' + self.escapeHtml( userName ) + '" ' +
						'style="max-width: 150px; max-height: 150px; display: block; margin: 0 auto 10px;">';
						html            += '<strong>' + self.escapeHtml( userName ) + '</strong>';
						html            += '</div>';
					}
				}
			);

			// Add "Delete All" option.
			html += '<div class="nbuf-conflict-option nbuf-photo-option" ' +
				'data-field="' + self.escapeHtml( conflict.field ) + '" ' +
				'data-user-id="delete_all" ' +
				'style="border: 2px dashed #d63638;">';
			html += '<span class="dashicons dashicons-trash" style="font-size: 48px; color: #d63638; margin: 20px auto; display: block;"></span>';
			html += '<strong style="color: #d63638;">Delete All Photos</strong>';
			html += '<p style="font-size: 12px; margin-top: 5px;">Remove all photos during merge</p>';
			html += '</div>';

			html += '</div></div>';
			return html;
		},

		/**
		 * Render role conflict UI
		 *
		 * @param {Object} conflict Role conflict data
		 * @return {string} HTML for role conflict
		 */
		renderRoleConflict: function (conflict) {
			const self = this;
			let html   = '<div class="nbuf-conflict-item nbuf-role-conflict">';
			html      += '<h4>' + self.escapeHtml( conflict.label ) + '</h4>';
			html      += '<p class="description" style="color: #d63638; font-weight: 600;">Warning: Changing user roles affects permissions and access levels. Review carefully before proceeding.</p>';
			html      += '<div class="nbuf-conflict-options">';

			$.each(
				conflict.values,
				function (userId, roles) {
					if (roles && roles.length > 0) {
						const userName   = self.accountData.accounts[userId].display_name;
						const isSelected = (userId == self.primaryAccount) ? ' selected' : '';

						// Convert role slugs to readable names.
						const roleLabels = [];
						roles.forEach(
							function (roleSlug) {
								if (conflict.names && conflict.names[roleSlug]) {
									roleLabels.push( conflict.names[roleSlug] );
								} else {
									roleLabels.push( roleSlug );
								}
							}
						);

						html += '<div class="nbuf-conflict-option nbuf-role-option' + isSelected + '" ' +
							'data-field="' + self.escapeHtml( conflict.field ) + '" ' +
							'data-user-id="' + userId + '" ' +
							'data-roles="' + self.escapeHtml( roles.join( ',' ) ) + '">';
						html += '<strong>' + self.escapeHtml( userName ) + ':</strong><br>';
						html += '<span class="nbuf-role-badges">';
						roleLabels.forEach(
							function (roleName) {
								html += '<span class="nbuf-role-badge" style="display: inline-block; background: #2271b1; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 12px; margin: 2px;">' + self.escapeHtml( roleName ) + '</span> ';
							}
						);
						html += '</span>';
						html += '</div>';
					}
				}
			);

			html += '</div></div>';
			return html;
		},

		/**
		 * Handle conflict option selection
		 */
		selectConflictOption: function () {
			$( this ).siblings( '.nbuf-conflict-option' ).removeClass( 'selected' );
			$( this ).addClass( 'selected' );
		},

		/**
		 * Toggle merge button based on confirmation checkbox
		 */
		toggleMergeButton: function () {
			$( '#nbuf-execute-merge' ).prop( 'disabled', ! $( '#nbuf-confirm-merge' ).is( ':checked' ) );
		},

		/**
		 * Cancel merge process
		 */
		cancelMerge: function (e) {
			e.preventDefault();
			if (confirm( NBUF_Merge.i18n.confirm_cancel )) {
				location.reload();
			}
		},

		/**
		 * Handle form submission
		 * Collects all conflict selections and adds them as hidden fields
		 */
		handleFormSubmit: function (e) {
			const self = this;

			/* Check for delete_all photo selections and confirm */
			let hasDeleteAll    = false;
			let deleteAllFields = [];

			$( '.nbuf-conflict-option.selected' ).each(
				function () {
					const value = $( this ).data( 'user-id' );
					if (value === 'delete_all') {
						hasDeleteAll = true;
						const field  = $( this ).data( 'field' );
						/* Convert field name to readable format */
						const fieldLabel = field.replace( '_', ' ' ).replace(
							/\b\w/g,
							function (l) {
								return l.toUpperCase();
							}
						);
						deleteAllFields.push( fieldLabel );
					}
				}
			);

			if (hasDeleteAll) {
				const fieldsText     = deleteAllFields.join( ', ' );
				const confirmMessage = 'WARNING: You selected "Delete All" for: ' + fieldsText + '.\n\n' +
					'This will PERMANENTLY DELETE all photos from all accounts being merged. ' +
					'This action CANNOT BE UNDONE.\n\n' +
					'Are you absolutely sure you want to continue?';

				if ( ! confirm( confirmMessage )) {
					return false; /* Cancel form submission */
				}
			}

			/* Remove any existing conflict fields */
			$( 'input[name^="nbuf_conflict_"]' ).remove();

			/* Add primary account selection */
			$( '<input>' ).attr(
				{
					type: 'hidden',
					name: 'nbuf_primary_account',
					value: self.primaryAccount
				}
			).appendTo( '#nbuf-merge-form' );

			/* Collect all selected conflict options */
			$( '.nbuf-conflict-option.selected' ).each(
				function () {
					const field = $( this ).data( 'field' );
					let value;

					/* Handle photo conflicts */
					if ($( this ).hasClass( 'nbuf-photo-option' )) {
						value = $( this ).data( 'user-id' );
					} /* Handle role conflicts */ else if ($( this ).hasClass( 'nbuf-role-option' )) {
						value = $( this ).data( 'user-id' );
					} /* Handle standard profile field conflicts */ else {
						value = $( this ).data( 'value' );
					}

					if (field && value) {
						$( '<input>' ).attr(
							{
								type: 'hidden',
								name: 'nbuf_conflict_' + field,
								value: value
							}
						).appendTo( '#nbuf-merge-form' );
					}
				}
			);

			/* Allow form to submit */
			return true;
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function (text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String( text ).replace(
				/[&<>"']/g,
				function (m) {
					return map[m];
				}
			);
		},

		/**
		 * Validate and sanitize URL for safe insertion
		 *
		 * Prevents XSS attacks via JavaScript data URIs and other protocol handlers.
		 * Only allows HTTP, HTTPS, and SVG data URIs (for generated avatars).
		 *
		 * @param {string} url URL to validate
		 * @return {string} Safe URL or empty string if invalid
		 */
		validateUrl: function (url) {
			if ( ! url) {
				return '';
			}

			/* Convert to string and trim */
			url = String( url ).trim();

			/* Only allow http, https, and WordPress SVG data URIs */
			const allowedProtocols = /^(https?:|data:image\/svg\+xml;base64,)/i;

			if ( ! allowedProtocols.test( url )) {
				console.warn( 'NBUF Security: Blocked potentially malicious URL protocol:', url.substring( 0, 50 ) );
				return '';
			}

			/* For data URIs, ensure it's only SVG (generated avatars) */
			if (url.startsWith( 'data:' )) {
				if ( ! url.startsWith( 'data:image/svg+xml;base64,' )) {
					console.warn( 'NBUF Security: Blocked non-SVG data URI:', url.substring( 0, 50 ) );
					return '';
				}
				/* Validate base64 portion is reasonable length (SVG avatars are ~500-1000 chars) */
				if (url.length > 5000) {
					console.warn( 'NBUF Security: Blocked suspiciously large data URI' );
					return '';
				}
			}

			/* Escape HTML entities in URL */
			return this.escapeHtml( url );
		}
	};

	/**
	 * Initialize on document ready
	 */
	$( document ).ready(
		function () {
			NBUF_MergeAccounts.init();
		}
	);

})( jQuery );
