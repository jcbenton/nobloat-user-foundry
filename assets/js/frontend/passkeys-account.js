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
	async function registerCredential(credential, deviceName) {
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
	async function deletePasskey(passkeyId) {
		const data = getPasskeyData();

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_delete');
		formData.append('nonce', data.nonce);
		formData.append('passkey_id', passkeyId);

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
	async function renamePasskey(passkeyId, newName) {
		const data = getPasskeyData();

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_rename');
		formData.append('nonce', data.nonce);
		formData.append('passkey_id', passkeyId);
		formData.append('device_name', newName);

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
			await registerCredential(credential, deviceName);

			/* Success - reload page to show new passkey with passkeys subtab active */
			reloadWithPasskeysTab();

		} catch (error) {
			console.error('Passkey registration error:', error);

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

		if (!confirm('Delete this passkey? You will not be able to use it to sign in.')) {
			return;
		}

		try {
			button.disabled = true;
			button.textContent = 'Deleting...';

			await deletePasskey(passkeyId);

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
			console.error('Delete error:', error);
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

		try {
			button.disabled = true;
			const originalText = button.textContent;
			button.textContent = 'Saving...';

			await renamePasskey(passkeyId, newName.trim());

			/* Reload page to show updated name */
			reloadWithPasskeysTab();

		} catch (error) {
			console.error('Rename error:', error);
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
