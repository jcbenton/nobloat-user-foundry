/**
 * Passkeys Account JavaScript
 *
 * Handles passkey registration and management on account page.
 *
 * @package NoBloat_User_Foundry
 * @since 1.5.0
 */

(function() {
	'use strict';

	/* Check if WebAuthn is supported */
	function isWebAuthnSupported() {
		return window.PublicKeyCredential !== undefined &&
			typeof window.PublicKeyCredential === 'function';
	}

	/* Base64URL decode */
	function base64urlDecode(str) {
		str = str.replace(/-/g, '+').replace(/_/g, '/');
		while (str.length % 4) {
			str += '=';
		}
		const binary = atob(str);
		const bytes = new Uint8Array(binary.length);
		for (let i = 0; i < binary.length; i++) {
			bytes[i] = binary.charCodeAt(i);
		}
		return bytes;
	}

	/* Base64URL encode */
	function base64urlEncode(buffer) {
		const bytes = new Uint8Array(buffer);
		let binary = '';
		for (let i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		return btoa(binary)
			.replace(/\+/g, '-')
			.replace(/\//g, '_')
			.replace(/=/g, '');
	}

	/* Get data from inline script or global */
	function getPasskeyData() {
		return window.nbufPasskeyData || {};
	}

	/* Reload page with passkeys subtab active */
	function reloadWithPasskeysTab() {
		const url = new URL(window.location.href);
		url.searchParams.set('subtab', 'passkeys');
		/* Also set tab param for non-virtual pages */
		if (!url.pathname.includes('/account/')) {
			url.searchParams.set('tab', 'security');
		}
		window.location.href = url.toString();
	}

	/* Get registration options from server */
	async function getRegistrationOptions() {
		const data = getPasskeyData();
		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_registration_options');
		formData.append('nonce', data.nonce);

		const response = await fetch(data.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await response.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Failed to get registration options');
		}

		return result.data;
	}

	/* Send registration to server */
	async function registerCredential(credential, deviceName, password) {
		const data = getPasskeyData();

		/* Get transports if available */
		let transports = [];
		if (credential.response.getTransports) {
			transports = credential.response.getTransports();
		}

		const response = {
			id: credential.id,
			rawId: base64urlEncode(credential.rawId),
			clientDataJSON: base64urlEncode(credential.response.clientDataJSON),
			attestationObject: base64urlEncode(credential.response.attestationObject),
			transports: transports
		};

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_register');
		formData.append('nonce', data.nonce);
		formData.append('response', JSON.stringify(response));
		formData.append('device_name', deviceName);
		formData.append('current_password', password);

		const fetchResponse = await fetch(data.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await fetchResponse.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Registration failed');
		}

		return result.data;
	}

	/* Delete a passkey */
	async function deletePasskey(passkeyId, password) {
		const data = getPasskeyData();

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_delete');
		formData.append('nonce', data.nonce);
		formData.append('passkey_id', passkeyId);
		formData.append('current_password', password);

		const response = await fetch(data.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await response.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Failed to delete passkey');
		}

		return result.data;
	}

	/* Rename a passkey */
	async function renamePasskey(passkeyId, newName, password) {
		const data = getPasskeyData();

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_rename');
		formData.append('nonce', data.nonce);
		formData.append('passkey_id', passkeyId);
		formData.append('device_name', newName);
		formData.append('current_password', password);

		const response = await fetch(data.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await response.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Failed to rename passkey');
		}

		return result.data;
	}

	/* Inject the masked-password modal styles once. */
	function ensurePasswordModalStyles() {
		if (document.getElementById('nbuf-pw-modal-styles')) {
			return;
		}
		const style = document.createElement('style');
		style.id = 'nbuf-pw-modal-styles';
		style.textContent =
			'.nbuf-pw-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;' +
			'align-items:center;justify-content:center;z-index:100000;padding:16px;}' +
			'.nbuf-pw-modal{background:#fff;color:#1e1e1e;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.25);' +
			'max-width:400px;width:100%;padding:24px;box-sizing:border-box;}' +
			'.nbuf-pw-modal-message{margin:0 0 14px;font-size:14px;line-height:1.5;}' +
			'.nbuf-pw-modal-input{width:100%;padding:10px 12px;font-size:15px;border:1px solid #8c8f94;' +
			'border-radius:6px;box-sizing:border-box;margin-bottom:18px;}' +
			'.nbuf-pw-modal-input:focus{outline:2px solid #2271b1;outline-offset:1px;border-color:#2271b1;}' +
			'.nbuf-pw-modal-actions{display:flex;gap:10px;justify-content:flex-end;}';
		document.head.appendChild(style);
	}

	/* Masked password prompt. Replaces window.prompt(), which renders the typed
	   password in cleartext. Resolves to the entered value, or null if cancelled. */
	function promptPassword(message) {
		return new Promise(function(resolve) {
			ensurePasswordModalStyles();

			const overlay = document.createElement('div');
			overlay.className = 'nbuf-pw-modal-overlay';
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');

			const modal = document.createElement('div');
			modal.className = 'nbuf-pw-modal';

			const msg = document.createElement('p');
			msg.className = 'nbuf-pw-modal-message';
			msg.textContent = message;

			const input = document.createElement('input');
			input.type = 'password';
			input.className = 'nbuf-pw-modal-input';
			input.setAttribute('autocomplete', 'current-password');
			input.setAttribute('aria-label', 'Current password');

			const actions = document.createElement('div');
			actions.className = 'nbuf-pw-modal-actions';

			const cancelBtn = document.createElement('button');
			cancelBtn.type = 'button';
			cancelBtn.className = 'nbuf-button nbuf-button-secondary nbuf-pw-modal-cancel';
			cancelBtn.textContent = 'Cancel';

			const okBtn = document.createElement('button');
			okBtn.type = 'button';
			okBtn.className = 'nbuf-button nbuf-button-primary nbuf-pw-modal-ok';
			okBtn.textContent = 'Confirm';

			actions.appendChild(cancelBtn);
			actions.appendChild(okBtn);
			modal.appendChild(msg);
			modal.appendChild(input);
			modal.appendChild(actions);
			overlay.appendChild(modal);
			document.body.appendChild(overlay);

			function close(value) {
				document.removeEventListener('keydown', onKeydown);
				if (overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
				resolve(value);
			}
			function onKeydown(e) {
				if (e.key === 'Escape') {
					close(null);
				} else if (e.key === 'Enter' && document.activeElement === input) {
					e.preventDefault();
					close(input.value);
				}
			}
			okBtn.addEventListener('click', function() { close(input.value); });
			cancelBtn.addEventListener('click', function() { close(null); });
			overlay.addEventListener('click', function(e) { if (e.target === overlay) { close(null); } });
			document.addEventListener('keydown', onKeydown);
			input.focus();
		});
	}

	/* Register new passkey flow */
	async function handleRegister() {
		const registerBtn = document.getElementById('nbuf-register-passkey');
		const deviceNameInput = document.getElementById('nbuf-passkey-name');

		if (!registerBtn) return;

		/* Check browser support */
		if (!isWebAuthnSupported()) {
			alert('Your browser does not support passkeys.');
			return;
		}

		const deviceName = deviceNameInput ? deviceNameInput.value.trim() : '';

		/* Re-authentication: require the current password to enroll a new authenticator. */
		const password = await promptPassword('Enter your current password to add a new passkey:');
		if (!password) {
			return;
		}

		/* Update UI */
		registerBtn.disabled = true;
		const originalText = registerBtn.innerHTML;
		registerBtn.innerHTML = '<span class="nbuf-passkey-icon">&#8987;</span> Registering...';

		try {
			/* Get registration options */
			const options = await getRegistrationOptions();

			/* Build WebAuthn options */
			const publicKeyOptions = {
				challenge: base64urlDecode(options.challenge),
				rp: {
					name: options.rp.name,
					id: options.rp.id
				},
				user: {
					id: base64urlDecode(options.user.id),
					name: options.user.name,
					displayName: options.user.displayName
				},
				pubKeyCredParams: options.pubKeyCredParams,
				timeout: options.timeout || 60000,
				attestation: options.attestation || 'none',
				authenticatorSelection: options.authenticatorSelection || {
					residentKey: 'preferred',
					userVerification: 'preferred'
				}
			};

			/* Add exclude credentials if provided */
			if (options.excludeCredentials && options.excludeCredentials.length > 0) {
				publicKeyOptions.excludeCredentials = options.excludeCredentials.map(cred => ({
					type: cred.type,
					id: base64urlDecode(cred.id)
				}));
			}

			/* Call WebAuthn API */
			const credential = await navigator.credentials.create({
				publicKey: publicKeyOptions
			});

			if (!credential) {
				throw new Error('No credential returned');
			}

			/* Send to server */
			await registerCredential(credential, deviceName, password);

			/* Success - reload page to show new passkey with passkeys subtab active */
			reloadWithPasskeysTab();

		} catch (error) {
			let message = 'Registration failed. Please try again.';
			if (error.name === 'NotAllowedError') {
				message = 'Registration was canceled or timed out.';
			} else if (error.name === 'InvalidStateError') {
				message = 'This device is already registered.';
			} else if (error.message) {
				message = error.message;
			}

			alert(message);

			/* Restore button */
			registerBtn.disabled = false;
			registerBtn.innerHTML = originalText;
		}
	}

	/* Handle delete button click */
	async function handleDelete(event) {
		const button = event.target.closest('.nbuf-passkey-delete');
		if (!button) return;

		const passkeyId = button.dataset.passkeyId;
		if (!passkeyId) return;

		const password = await promptPassword('Enter your current password to delete this passkey. You will not be able to use it to sign in afterward.');
		if (!password) {
			return;
		}

		try {
			button.disabled = true;
			button.textContent = 'Deleting...';

			await deletePasskey(passkeyId, password);

			/* Remove row from table */
			const row = button.closest('tr');
			if (row) {
				row.remove();
			}

			/* Check if table is now empty */
			const tbody = document.querySelector('.nbuf-passkeys-table tbody');
			if (tbody && tbody.children.length === 0) {
				/* Reload with passkeys subtab active */
				reloadWithPasskeysTab();
			}

		} catch (error) {
			alert(error.message || 'Failed to delete passkey.');
			button.disabled = false;
			button.textContent = 'Delete';
		}
	}

	/* Handle rename button click */
	async function handleRename(event) {
		const button = event.target.closest('.nbuf-passkey-rename');
		if (!button) return;

		const passkeyId = button.dataset.passkeyId;
		if (!passkeyId) return;

		const row = button.closest('tr');
		const nameSpan = row.querySelector('.nbuf-passkey-device-name');
		const currentName = nameSpan ? nameSpan.textContent : '';

		const newName = prompt('Enter a new name for this passkey:', currentName);
		if (newName === null || newName.trim() === '' || newName === currentName) {
			return;
		}

		const password = await promptPassword('Enter your current password to rename this passkey:');
		if (!password) {
			return;
		}

		try {
			button.disabled = true;
			const originalText = button.textContent;
			button.textContent = 'Saving...';

			await renamePasskey(passkeyId, newName.trim(), password);

			/* Reload page to show updated name */
			reloadWithPasskeysTab();

		} catch (error) {
			alert(error.message || 'Failed to rename passkey.');
			button.disabled = false;
			button.textContent = 'Rename';
		}
	}

	/* Initialize */
	function init() {
		/* Check WebAuthn support and show/hide browser warning */
		const browserCheck = document.querySelector('.nbuf-passkeys-browser-check');
		if (browserCheck && !isWebAuthnSupported()) {
			browserCheck.style.display = 'block';
			const registerBtn = document.getElementById('nbuf-register-passkey');
			if (registerBtn) {
				registerBtn.disabled = true;
			}
			return;
		}

		/* Register button */
		const registerBtn = document.getElementById('nbuf-register-passkey');
		if (registerBtn) {
			registerBtn.addEventListener('click', handleRegister);
		}

		/* Delete buttons */
		document.querySelectorAll('.nbuf-passkey-delete').forEach(button => {
			button.addEventListener('click', handleDelete);
		});

		/* Rename buttons */
		document.querySelectorAll('.nbuf-passkey-rename').forEach(button => {
			button.addEventListener('click', handleRename);
		});
	}

	/* Wait for DOM */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
