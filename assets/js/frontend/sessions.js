/**
 * Sessions Management JavaScript
 *
 * Handles session revocation via AJAX.
 *
 * @package NoBloat_User_Foundry
 * @since 1.5.2
 */

(function() {
	'use strict';

	/**
	 * Initialize session management handlers.
	 */
	function init() {
		/* Revoke single session */
		document.querySelectorAll('.nbuf-revoke-session').forEach(function(btn) {
			btn.addEventListener('click', handleRevokeSession);
		});

		/* Revoke all other sessions */
		document.querySelectorAll('.nbuf-revoke-all-sessions').forEach(function(btn) {
			btn.addEventListener('click', handleRevokeAllSessions);
		});
	}

	/**
	 * Handle revoking a single session.
	 *
	 * @param {Event} e Click event.
	 */
	function handleRevokeSession(e) {
		var btn = e.target;
		var tokenHash = btn.getAttribute('data-token');
		var nonce = btn.getAttribute('data-nonce');
		var sessionItem = btn.closest('.nbuf-session-item');
		var originalText = btn.textContent;

		if (!tokenHash || !nonce) {
			return;
		}

		/* Update button state */
		btn.disabled = true;
		btn.textContent = NBUF_Sessions.i18n.revoking;

		/* Make AJAX request */
		var formData = new FormData();
		formData.append('action', 'nbuf_revoke_session');
		formData.append('token_hash', tokenHash);
		formData.append('nonce', nonce);

		fetch(NBUF_Sessions.ajax_url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.success) {
				/* Remove session item with animation */
				sessionItem.style.opacity = '0';
				sessionItem.style.transform = 'translateX(20px)';
				setTimeout(function() {
					sessionItem.remove();
					/* Check if no more sessions to revoke */
					var remainingNonCurrent = document.querySelectorAll('.nbuf-session-item:not(.nbuf-session-current)');
					if (remainingNonCurrent.length === 0) {
						var revokeAllBtn = document.querySelector('.nbuf-revoke-all-sessions');
						if (revokeAllBtn) {
							revokeAllBtn.parentElement.remove();
						}
					}
				}, 300);
				showToast(NBUF_Sessions.i18n.session_revoked, 'success');
			} else {
				btn.disabled = false;
				btn.textContent = originalText;
				showToast(data.data.message || NBUF_Sessions.i18n.error, 'error');
			}
		})
		.catch(function() {
			btn.disabled = false;
			btn.textContent = originalText;
			showToast(NBUF_Sessions.i18n.error, 'error');
		});
	}

	/**
	 * Handle revoking all other sessions.
	 *
	 * @param {Event} e Click event.
	 */
	function handleRevokeAllSessions(e) {
		var btn = e.target;
		var nonce = btn.getAttribute('data-nonce');
		var originalText = btn.textContent;

		if (!nonce) {
			return;
		}

		/* Confirm action */
		if (!confirm(NBUF_Sessions.i18n.confirm_revoke_all)) {
			return;
		}

		/* Update button state */
		btn.disabled = true;
		btn.textContent = NBUF_Sessions.i18n.revoking;

		/* Make AJAX request */
		var formData = new FormData();
		formData.append('action', 'nbuf_revoke_other_sessions');
		formData.append('nonce', nonce);

		fetch(NBUF_Sessions.ajax_url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.success) {
				/* Remove all non-current session items */
				document.querySelectorAll('.nbuf-session-item:not(.nbuf-session-current)').forEach(function(item) {
					item.style.opacity = '0';
					item.style.transform = 'translateX(20px)';
					setTimeout(function() {
						item.remove();
					}, 300);
				});
				/* Remove the "Revoke All" button */
				btn.parentElement.remove();
				showToast(NBUF_Sessions.i18n.all_revoked, 'success');
			} else {
				btn.disabled = false;
				btn.textContent = originalText;
				showToast(data.data.message || NBUF_Sessions.i18n.error, 'error');
			}
		})
		.catch(function() {
			btn.disabled = false;
			btn.textContent = originalText;
			showToast(NBUF_Sessions.i18n.error, 'error');
		});
	}

	/**
	 * Show toast notification.
	 *
	 * @param {string} message Toast message.
	 * @param {string} type    Toast type (success, error).
	 */
	function showToast(message, type) {
		/* Use existing toast system if available */
		var existingToast = document.querySelector('.nbuf-message');
		if (existingToast) {
			existingToast.textContent = message;
			existingToast.className = 'nbuf-message nbuf-message-' + type;
			existingToast.style.display = 'block';
			return;
		}

		/* Create simple toast */
		var toast = document.createElement('div');
		toast.className = 'nbuf-toast nbuf-toast-' + type;
		toast.textContent = message;
		toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; padding: 12px 24px; border-radius: 4px; z-index: 9999; opacity: 0; transition: opacity 0.3s;';

		if (type === 'success') {
			toast.style.backgroundColor = '#4caf50';
			toast.style.color = '#fff';
		} else {
			toast.style.backgroundColor = '#f44336';
			toast.style.color = '#fff';
		}

		document.body.appendChild(toast);

		/* Fade in */
		setTimeout(function() {
			toast.style.opacity = '1';
		}, 10);

		/* Remove after 3 seconds */
		setTimeout(function() {
			toast.style.opacity = '0';
			setTimeout(function() {
				toast.remove();
			}, 300);
		}, 3000);
	}

	/* Initialize when DOM is ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
