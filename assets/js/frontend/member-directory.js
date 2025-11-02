/**
 * Member Directory JavaScript
 *
 * Handles AJAX search and filter functionality for member directories.
 * Provides smooth user experience without page reloads.
 *
 * @package NoBloat_User_Foundry
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Member Directory Handler
	 */
	const MemberDirectory = {

		/**
		 * Initialize directory
		 */
		init: function() {
			this.cacheSelectors();
			this.bindEvents();
		},

		/**
		 * Cache jQuery selectors
		 */
		cacheSelectors: function() {
			this.$directory = $('.nbuf-member-directory');
			this.$form = $('.nbuf-directory-form');
			this.$searchInput = $('.nbuf-search-input');
			this.$roleFilter = $('select[name="member_role"]');
			this.$searchButton = $('.nbuf-search-button');
			this.$filterButton = $('.nbuf-filter-button');
			this.$results = $('.nbuf-members-grid, .nbuf-members-list');
			this.$stats = $('.nbuf-directory-stats');
			this.$pagination = $('.nbuf-directory-pagination');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			/* Prevent default form submission */
			this.$form.on('submit', function(e) {
				e.preventDefault();
				self.performSearch(1);
				return false;
			});

			/* Search button click */
			this.$searchButton.on('click', function(e) {
				e.preventDefault();
				self.performSearch(1);
			});

			/* Filter button click */
			this.$filterButton.on('click', function(e) {
				e.preventDefault();
				self.performSearch(1);
			});

			/* Pagination clicks */
			$(document).on('click', '.nbuf-page-number, .nbuf-page-prev, .nbuf-page-next', function(e) {
				e.preventDefault();
				const url = new URL($(this).attr('href'), window.location.origin);
				const page = url.searchParams.get('member_page') || 1;
				self.performSearch(page);
				/* Scroll to top of directory */
				$('html, body').animate({
					scrollTop: self.$directory.offset().top - 100
				}, 400);
			});

			/* Enter key in search input */
			this.$searchInput.on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.performSearch(1);
				}
			});
		},

		/**
		 * Perform AJAX search
		 *
		 * @param {number} page Page number
		 */
		performSearch: function(page) {
			const self = this;

			/* Get form values */
			const searchTerm = this.$searchInput.val();
			const role = this.$roleFilter.val();
			const perPage = this.$directory.data('per-page') || 20;

			/* Show loading state */
			this.showLoading();

			/* Perform AJAX request */
			$.ajax({
				url: nbufDirectory.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nbuf_directory_search',
					nonce: nbufDirectory.nonce,
					search: searchTerm,
					role: role,
					page: page,
					per_page: perPage
				},
				success: function(response) {
					if (response.success) {
						self.updateResults(response.data);
						self.updateURL(searchTerm, role, page);
					} else {
						self.showError(response.data.message || 'An error occurred.');
					}
				},
				error: function() {
					self.showError('Failed to load members. Please try again.');
				},
				complete: function() {
					self.hideLoading();
				}
			});
		},

		/**
		 * Update results display
		 *
		 * @param {Object} data Response data with members and pagination
		 */
		updateResults: function(data) {
			const self = this;
			const members = data.members;
			const total = data.total;
			const pages = data.pages;

			/* Update stats */
			const statsText = total === 1
				? total + ' member found'
				: total + ' members found';
			this.$stats.text(statsText);

			/* Build member cards/items HTML */
			let html = '';

			if (members.length === 0) {
				html = '<div class="nbuf-no-members">' +
					'<p>No members found.</p>' +
					'<p><a href="#" class="nbuf-clear-all">Clear filters and show all members</a></p>' +
					'</div>';
			} else {
				members.forEach(function(member) {
					html += self.buildMemberCard(member);
				});
			}

			/* Update results container */
			this.$results.html(html);

			/* Update pagination if needed */
			if (pages > 1) {
				this.buildPagination(pages, parseInt(data.current_page || 1));
			} else {
				this.$pagination.html('');
			}
		},

		/**
		 * Build member card HTML
		 *
		 * @param {Object} member Member data
		 * @return {string} HTML
		 */
		buildMemberCard: function(member) {
			/* Check if this is grid or list view */
			const isGrid = this.$results.hasClass('nbuf-members-grid');

			if (isGrid) {
				return this.buildGridCard(member);
			} else {
				return this.buildListItem(member);
			}
		},

		/**
		 * Build grid view card
		 *
		 * @param {Object} member Member data
		 * @return {string} HTML
		 */
		buildGridCard: function(member) {
			let html = '<div class="nbuf-member-card" data-user-id="' + member.ID + '">';
			html += '<div class="nbuf-member-avatar">';
			html += '<img src="' + member.avatar + '" alt="' + this.escapeHtml(member.display_name) + '">';
			html += '</div>';
			html += '<div class="nbuf-member-info">';
			html += '<h3 class="nbuf-member-name">' + this.escapeHtml(member.display_name) + '</h3>';

			if (member.bio) {
				html += '<div class="nbuf-member-bio">' + this.escapeHtml(member.bio_excerpt) + '</div>';
			}

			if (member.location) {
				html += '<div class="nbuf-member-location">';
				html += '<span class="dashicons dashicons-location"></span>';
				html += this.escapeHtml(member.location);
				html += '</div>';
			}

			if (member.website) {
				html += '<div class="nbuf-member-website">';
				html += '<a href="' + member.website + '" target="_blank" rel="noopener noreferrer">Visit Website</a>';
				html += '</div>';
			}

			html += '<div class="nbuf-member-meta">';
			html += '<span class="nbuf-member-joined">Joined ' + member.joined + '</span>';
			html += '</div>';
			html += '</div>';
			html += '</div>';

			return html;
		},

		/**
		 * Build list view item
		 *
		 * @param {Object} member Member data
		 * @return {string} HTML
		 */
		buildListItem: function(member) {
			let html = '<div class="nbuf-member-item" data-user-id="' + member.ID + '">';
			html += '<div class="nbuf-member-avatar-small">';
			html += '<img src="' + member.avatar_small + '" alt="' + this.escapeHtml(member.display_name) + '">';
			html += '</div>';
			html += '<div class="nbuf-member-details">';
			html += '<h4 class="nbuf-member-name">' + this.escapeHtml(member.display_name) + '</h4>';
			html += '<div class="nbuf-member-meta-inline">';

			if (member.location) {
				html += '<span class="nbuf-member-location-inline">';
				html += '<span class="dashicons dashicons-location"></span>';
				html += this.escapeHtml(member.location);
				html += '</span>';
			}

			html += '<span class="nbuf-member-joined-inline">Joined ' + member.joined + '</span>';
			html += '</div>';

			if (member.bio) {
				html += '<div class="nbuf-member-bio-inline">' + this.escapeHtml(member.bio_excerpt) + '</div>';
			}

			html += '</div>';

			if (member.website) {
				html += '<div class="nbuf-member-actions">';
				html += '<a href="' + member.website + '" target="_blank" rel="noopener noreferrer" class="nbuf-member-link">Website</a>';
				html += '</div>';
			}

			html += '</div>';

			return html;
		},

		/**
		 * Build pagination HTML
		 *
		 * @param {number} totalPages Total pages
		 * @param {number} currentPage Current page
		 */
		buildPagination: function(totalPages, currentPage) {
			/* For now, use server-side pagination (page reload) */
			/* This method is here for potential future AJAX pagination enhancement */
		},

		/**
		 * Update browser URL without reload
		 *
		 * @param {string} search Search term
		 * @param {string} role Role filter
		 * @param {number} page Page number
		 */
		updateURL: function(search, role, page) {
			const url = new URL(window.location);

			if (search) {
				url.searchParams.set('member_search', search);
			} else {
				url.searchParams.delete('member_search');
			}

			if (role) {
				url.searchParams.set('member_role', role);
			} else {
				url.searchParams.delete('member_role');
			}

			if (page > 1) {
				url.searchParams.set('member_page', page);
			} else {
				url.searchParams.delete('member_page');
			}

			window.history.pushState({}, '', url);
		},

		/**
		 * Show loading state
		 */
		showLoading: function() {
			this.$results.css('opacity', '0.5');
			this.$searchButton.prop('disabled', true).text('Searching...');
			this.$filterButton.prop('disabled', true);
		},

		/**
		 * Hide loading state
		 */
		hideLoading: function() {
			this.$results.css('opacity', '1');
			this.$searchButton.prop('disabled', false).text('Search');
			this.$filterButton.prop('disabled', false);
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function(message) {
			const html = '<div class="nbuf-no-members"><p>' + this.escapeHtml(message) + '</p></div>';
			this.$results.html(html);
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
		if ($('.nbuf-member-directory').length) {
			MemberDirectory.init();
		}
	});

})(jQuery);
