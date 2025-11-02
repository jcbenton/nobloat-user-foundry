/**
 * User Notes Page - JavaScript
 *
 * Handles user search autocomplete and notes management functionality.
 * Uses wp_localize_script for AJAX URL and nonce.
 *
 * @package NoBloat_User_Foundry
 * @since 1.0.0
 *
 * Expects NBUF_UserNotes object with:
 * - ajax_url: WordPress AJAX URL
 * - nonce: Security nonce for AJAX requests
 */

		jQuery(document).ready(function($) {
			var selectedUserId = null;
			var searchTimeout = null;

			/* Native autocomplete for user search */
			var searchInput = $('#nbuf-user-search-input');
			var resultsDiv = $('<div class=\"nbuf-autocomplete-results\"></div>');
			searchInput.after(resultsDiv);

			searchInput.on('input', function() {
				var searchTerm = $(this).val().trim();

				clearTimeout(searchTimeout);

				if (searchTerm.length < 2) {
					resultsDiv.hide().empty();
					return;
				}

				searchTimeout = setTimeout(function() {
					$.post(NBUF_UserNotes.ajax_url, {
						action: 'nbuf_search_users',
						nonce: NBUF_UserNotes.nonce,
						search: searchTerm
					}, function(data) {
						resultsDiv.empty();

						if (data.results && data.results.length > 0) {
							data.results.forEach(function(user) {
								var item = $('<div class=\"nbuf-autocomplete-item\"></div>')
									.text(user.text)
									.data('user-id', user.id)
									.on('click', function() {
										selectedUserId = $(this).data('user-id');
										searchInput.val(user.text);
										resultsDiv.hide().empty();
										loadUserNotes(selectedUserId);
									});
								resultsDiv.append(item);
							});
							resultsDiv.show();
						} else {
							resultsDiv.hide();
						}
					});
				}, 300);
			});

			/* Hide results when clicking outside */
			$(document).on('click', function(e) {
				if (!$(e.target).closest('#nbuf-user-search-input, .nbuf-autocomplete-results').length) {
					resultsDiv.hide();
				}
			});

			/* Check for pre-selected user from URL */
			var urlParams = new URLSearchParams(window.location.search);
			var preselectedUserId = urlParams.get('user_id');
			if (preselectedUserId) {
				/* Load user info and display */
				$.post(NBUF_UserNotes.ajax_url, {
					action: 'nbuf_search_users',
					nonce: NBUF_UserNotes.nonce,
					user_id: preselectedUserId
				}, function(response) {
					if (response.success && response.data.user) {
						var user = response.data.user;
						searchInput.val(user.text);
						selectedUserId = user.id;
						loadUserNotes(selectedUserId);
					}
				});
			}

			/* Load user notes */
			function loadUserNotes(userId) {
				$('.nbuf-notes-section').removeClass('active');
				$('#nbuf-notes-loading').addClass('active');

				$.post(NBUF_UserNotes.ajax_url, {
					action: 'nbuf_get_user_notes',
					nonce: NBUF_UserNotes.nonce,
					user_id: userId
				}, function(response) {
					$('#nbuf-notes-loading').removeClass('active');
					if (response.success) {
						displayNotes(response.data.notes, response.data.user);
						$('#nbuf-notes-list').addClass('active');
					} else {
						alert(response.data.message || 'Failed to load notes');
					}
				});
			}

			/* Display notes */
			function displayNotes(notes, user) {
				var container = $('#nbuf-notes-container');
				container.empty();

				if (notes.length === 0) {
					container.html('<div class=\"nbuf-empty-state\"><p>No notes found for this user.</p></div>');
				} else {
					notes.forEach(function(note) {
						var noteHtml = buildNoteHtml(note);
						container.append(noteHtml);
					});
				}

				$('#nbuf-selected-user-name').text(user.display_name);
			}

			/* Build note HTML */
			function buildNoteHtml(note) {
				var html = '<div class=\"nbuf-note-item\" data-note-id=\"' + note.id + '\">';
				html += '<div class=\"nbuf-note-header\">';
				html += '<span class=\"nbuf-note-meta\">' + note.created_at_formatted + ' - ' + note.author_name + '</span>';
				html += '</div>';
				html += '<div class=\"nbuf-note-content\">' + escapeHtml(note.note_content) + '</div>';
				html += '<div class=\"nbuf-note-actions\">';
				html += '<button class=\"button button-small nbuf-edit-note-btn\">Edit</button>';
				html += '<button class=\"button button-small button-link-delete nbuf-delete-note-btn\">Delete</button>';
				html += '</div>';
				html += '<div class=\"nbuf-note-edit-form\">';
				html += '<textarea class=\"large-text\" rows=\"4\">' + escapeHtml(note.note_content) + '</textarea>';
				html += '<div style=\"margin-top: 10px;\">';
				html += '<button class=\"button button-primary nbuf-save-note-btn\">Save</button> ';
				html += '<button class=\"button nbuf-cancel-edit-btn\">Cancel</button>';
				html += '</div>';
				html += '</div>';
				html += '</div>';
				return html;
			}

			/* Escape HTML */
			function escapeHtml(text) {
				var div = document.createElement('div');
				div.textContent = text;
				return div.innerHTML;
			}

			/* Edit note */
			$(document).on('click', '.nbuf-edit-note-btn', function() {
				var noteItem = $(this).closest('.nbuf-note-item');
				noteItem.find('.nbuf-note-content, .nbuf-note-actions').hide();
				noteItem.find('.nbuf-note-edit-form').addClass('active');
			});

			/* Cancel edit */
			$(document).on('click', '.nbuf-cancel-edit-btn', function() {
				var noteItem = $(this).closest('.nbuf-note-item');
				noteItem.find('.nbuf-note-content, .nbuf-note-actions').show();
				noteItem.find('.nbuf-note-edit-form').removeClass('active');
			});

			/* Save edited note */
			$(document).on('click', '.nbuf-save-note-btn', function() {
				var btn = $(this);
				var noteItem = btn.closest('.nbuf-note-item');
				var noteId = noteItem.data('note-id');
				var content = noteItem.find('textarea').val();

				btn.prop('disabled', true).text('Saving...');

				$.post(NBUF_UserNotes.ajax_url, {
					action: 'nbuf_update_note',
					nonce: NBUF_UserNotes.nonce,
					note_id: noteId,
					note_content: content
				}, function(response) {
					if (response.success) {
						loadUserNotes(selectedUserId);
					} else {
						alert(response.data.message || 'Failed to update note');
						btn.prop('disabled', false).text('Save');
					}
				});
			});

			/* Delete note */
			$(document).on('click', '.nbuf-delete-note-btn', function() {
				if (!confirm('Are you sure you want to delete this note?')) {
					return;
				}

				var btn = $(this);
				var noteItem = btn.closest('.nbuf-note-item');
				var noteId = noteItem.data('note-id');

				btn.prop('disabled', true);

				$.post(NBUF_UserNotes.ajax_url, {
					action: 'nbuf_delete_note',
					nonce: NBUF_UserNotes.nonce,
					note_id: noteId
				}, function(response) {
					if (response.success) {
						loadUserNotes(selectedUserId);
					} else {
						alert(response.data.message || 'Failed to delete note');
						btn.prop('disabled', false);
					}
				});
			});

			/* Add new note */
			$('#nbuf-add-note-btn').on('click', function() {
				var btn = $(this);
				var content = $('#nbuf-new-note-content').val();

				if (!content.trim()) {
					alert('Please enter note content');
					return;
				}

				btn.prop('disabled', true).text('Adding...');

				$.post(NBUF_UserNotes.ajax_url, {
					action: 'nbuf_add_note',
					nonce: NBUF_UserNotes.nonce,
					user_id: selectedUserId,
					note_content: content
				}, function(response) {
					if (response.success) {
						$('#nbuf-new-note-content').val('');
						loadUserNotes(selectedUserId);
					} else {
						alert(response.data.message || 'Failed to add note');
					}
					btn.prop('disabled', false).text('Add Note');
				});
			});
		});
