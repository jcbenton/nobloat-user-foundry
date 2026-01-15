/**
 * Passkeys Login JavaScript - Two-Step Flow
 *
 * Handles two-step login:
 * 1. User enters username/email, clicks Continue
 * 2. System checks if user has passkeys:
 *    - If yes: Shows passkey prompt with password fallback
 *    - If no: Shows password field
 *
 * @package NoBloat_User_Foundry
 * @since 1.5.0
 */

(function() {
	'use strict';

	/* State tracking */
	let currentStep = 1;
	let currentUsername = '';
	let userHasPasskeys = false;

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

	/* Check if user has passkeys */
	async function checkUserPasskeys(login) {
		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_check_user');
		formData.append('login', login);

		const response = await fetch(nbufPasskeyLogin.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await response.json();
		if (!result.success) {
			const error = new Error(result.data?.message || 'Failed to check user');
			error.ip_blocked = result.data?.ip_blocked || false;
			throw error;
		}

		return result.data;
	}

	/* Get authentication options from server */
	async function getAuthOptions(username) {
		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_auth_options');
		if (username) {
			formData.append('username', username);
		}

		const response = await fetch(nbufPasskeyLogin.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await response.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Failed to get authentication options');
		}

		return result.data;
	}

	/* Verify authentication with server */
	async function verifyAuthentication(credential, sessionId) {
		const response = {
			id: credential.id,
			rawId: base64urlEncode(credential.rawId),
			clientDataJSON: base64urlEncode(credential.response.clientDataJSON),
			authenticatorData: base64urlEncode(credential.response.authenticatorData),
			signature: base64urlEncode(credential.response.signature),
			userHandle: credential.response.userHandle ?
				base64urlEncode(credential.response.userHandle) : null,
			sessionId: sessionId
		};

		const formData = new FormData();
		formData.append('action', 'nbuf_passkey_authenticate');
		formData.append('response', JSON.stringify(response));
		formData.append('redirect_to', nbufPasskeyLogin.redirectUrl);

		const fetchResponse = await fetch(nbufPasskeyLogin.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		});

		const result = await fetchResponse.json();
		if (!result.success) {
			throw new Error(result.data?.message || 'Authentication failed');
		}

		return result.data;
	}

	/* Show error message */
	function showError(message) {
		const wrapper = document.querySelector('.nbuf-login-wrapper');
		if (!wrapper) return;

		/* Always use/create an error div at the top of the wrapper */
		let errorDiv = wrapper.querySelector(':scope > #nbuf-passkey-error');

		if (!errorDiv) {
			errorDiv = document.createElement('div');
			errorDiv.id = 'nbuf-passkey-error';
			errorDiv.className = 'nbuf-message nbuf-message-error nbuf-login-error';
			wrapper.insertBefore(errorDiv, wrapper.firstChild);
		}

		errorDiv.textContent = message;
		errorDiv.style.display = 'block';
	}

	/* Hide error message */
	function hideError() {
		const errorDiv = document.getElementById('nbuf-passkey-error') ||
			document.querySelector('.nbuf-login-error');
		if (errorDiv) {
			errorDiv.style.display = 'none';
		}
	}

	/* Get DOM elements */
	function getElements() {
		/* Find password field first, then get its parent row */
		const passwordField = document.getElementById('user_pass') || document.getElementById('nbuf_password') || document.querySelector('input[name="pwd"]');
		let passwordRow = document.querySelector('.login-password') || document.querySelector('.nbuf-form-row-password') || document.getElementById('nbuf-password-row');

		/* If no specific password row found, get parent of password field */
		if (!passwordRow && passwordField) {
			passwordRow = passwordField.closest('.nbuf-form-group') || passwordField.closest('p');
		}

		return {
			form: document.getElementById('loginform') || document.querySelector('.nbuf-login-form') || document.querySelector('form'),
			usernameField: document.getElementById('user_login') || document.getElementById('nbuf_username') || document.querySelector('input[name="log"]'),
			passwordRow: passwordRow,
			passwordField: passwordField,
			submitButton: document.getElementById('wp-submit') || document.querySelector('.nbuf-login-button') || document.querySelector('input[type="submit"]') || document.querySelector('button[type="submit"]'),
			continueButton: document.getElementById('nbuf-continue-btn'),
			passkeySection: document.getElementById('nbuf-passkey-section'),
			passkeyButton: document.getElementById('nbuf-passkey-login-btn'),
			usePasswordLink: document.getElementById('nbuf-use-password-link'),
			rememberRow: document.querySelector('.login-remember') || document.querySelector('.nbuf-remember-group') || document.querySelector('.nbuf-form-row-remember'),
			forgotLink: document.querySelector('.login-forgot') || document.querySelector('.nbuf-login-links') || document.querySelector('a[href*="forgot"]')?.parentElement,
			magicLinkDivider: document.querySelector('.nbuf-magic-link-divider'),
			magicLinkButton: document.querySelector('.nbuf-magic-link-button')
		};
	}

	/* Update step indicator */
	function updateStepIndicator(step) {
		const dots = document.querySelectorAll('.nbuf-step-dot');
		dots.forEach((dot, index) => {
			dot.classList.toggle('active', index === step - 1);
		});
	}

	/* Update current user display */
	function updateCurrentUserDisplay(username, show) {
		const display = document.getElementById('nbuf-current-user');
		if (display) {
			if (show && username) {
				display.querySelector('.nbuf-current-user-name').textContent = username;
				display.style.display = 'flex';
			} else {
				display.style.display = 'none';
			}
		}
	}

	/* Show Step 1: Username entry */
	function showStep1() {
		const el = getElements();
		currentStep = 1;
		currentUsername = '';
		userHasPasskeys = false;

		/* Update step indicator */
		updateStepIndicator(1);

		/* Hide current user display */
		updateCurrentUserDisplay('', false);

		/* Show username field */
		if (el.usernameField) {
			const usernameRow = el.usernameField.closest('.login-username, .nbuf-form-group, .nbuf-form-row, p');
			if (usernameRow) usernameRow.style.display = '';
			el.usernameField.value = '';
			el.usernameField.focus();
		}

		/* Hide password field */
		if (el.passwordRow) {
			el.passwordRow.style.display = 'none';
		}

		/* Show continue button, hide submit */
		if (el.continueButton) el.continueButton.style.display = '';
		if (el.submitButton) el.submitButton.style.display = 'none';

		/* Hide passkey section */
		if (el.passkeySection) el.passkeySection.style.display = 'none';

		/* Hide remember me for now */
		if (el.rememberRow) el.rememberRow.style.display = 'none';

		/* Show forgot/register links */
		if (el.forgotLink) el.forgotLink.style.display = '';

		/* Show magic link option if available */
		if (el.magicLinkDivider) el.magicLinkDivider.style.display = '';
		if (el.magicLinkButton) el.magicLinkButton.style.display = '';

		hideError();
	}

	/* Show Step 2a: Passkey authentication */
	function showStep2Passkey() {
		const el = getElements();
		currentStep = 2;

		/* Update step indicator */
		updateStepIndicator(2);

		/* Show current user display */
		updateCurrentUserDisplay(currentUsername, true);

		/* Hide username field */
		if (el.usernameField) {
			const usernameRow = el.usernameField.closest('.login-username, .nbuf-form-group, .nbuf-form-row, p');
			if (usernameRow) usernameRow.style.display = 'none';
		}

		/* Hide password field */
		if (el.passwordRow) {
			el.passwordRow.style.display = 'none';
		}

		/* Hide continue button */
		if (el.continueButton) el.continueButton.style.display = 'none';

		/* Hide regular submit */
		if (el.submitButton) el.submitButton.style.display = 'none';

		/* Show passkey section */
		if (el.passkeySection) {
			el.passkeySection.style.display = 'block';
		}

		/* Show remember me */
		if (el.rememberRow) el.rememberRow.style.display = '';

		/* Show forgot/register links */
		if (el.forgotLink) el.forgotLink.style.display = '';

		/* Hide magic link option */
		if (el.magicLinkDivider) el.magicLinkDivider.style.display = 'none';
		if (el.magicLinkButton) el.magicLinkButton.style.display = 'none';
	}

	/* Show Step 2b: Password entry */
	function showStep2Password() {
		const el = getElements();
		currentStep = 2;

		/* Update step indicator */
		updateStepIndicator(2);

		/* Show current user display */
		updateCurrentUserDisplay(currentUsername, true);

		/* Hide username field */
		if (el.usernameField) {
			const usernameRow = el.usernameField.closest('.login-username, .nbuf-form-group, .nbuf-form-row, p');
			if (usernameRow) usernameRow.style.display = 'none';
		}

		/* Show password field */
		if (el.passwordRow) {
			el.passwordRow.style.display = '';
		}

		/* Hide continue button */
		if (el.continueButton) el.continueButton.style.display = 'none';

		/* Show submit button */
		if (el.submitButton) el.submitButton.style.display = '';

		/* Hide passkey section */
		if (el.passkeySection) el.passkeySection.style.display = 'none';

		/* Show remember me */
		if (el.rememberRow) el.rememberRow.style.display = '';

		/* Show forgot/register links */
		if (el.forgotLink) el.forgotLink.style.display = '';

		/* Hide magic link option */
		if (el.magicLinkDivider) el.magicLinkDivider.style.display = 'none';
		if (el.magicLinkButton) el.magicLinkButton.style.display = 'none';

		/* Focus password field */
		if (el.passwordField) {
			setTimeout(() => el.passwordField.focus(), 100);
		}
	}

	/* Handle Continue button click */
	async function handleContinue(e) {
		e.preventDefault();
		const el = getElements();

		const username = el.usernameField?.value?.trim();
		if (!username) {
			showError(nbufPasskeyLogin.strings?.enterUsername || 'Please enter a username or email.');
			el.usernameField?.focus();
			return;
		}

		currentUsername = username;
		hideError();

		/* Check if passkeys are supported and enabled */
		if (!isWebAuthnSupported() || !nbufPasskeyLogin.passkeysEnabled) {
			showStep2Password();
			return;
		}

		/* Disable button during check */
		const continueBtn = el.continueButton;
		if (continueBtn) {
			continueBtn.disabled = true;
			continueBtn.textContent = nbufPasskeyLogin.strings?.checking || 'Checking...';
		}

		try {
			const result = await checkUserPasskeys(username);
			userHasPasskeys = result.has_passkeys;

			if (userHasPasskeys) {
				showStep2Passkey();
			} else {
				showStep2Password();
			}
		} catch (error) {
			/* If IP is blocked, show error and don't proceed */
			if (error.ip_blocked) {
				showError(error.message);
				return;
			}
			/* On other errors, fall back to password */
			showStep2Password();
		} finally {
			if (continueBtn) {
				continueBtn.disabled = false;
				continueBtn.textContent = nbufPasskeyLogin.strings?.continue || 'Continue';
			}
		}
	}

	/* Handle passkey authentication */
	async function handlePasskeyAuth() {
		const el = getElements();
		const button = el.passkeyButton;

		if (!button) return;

		button.disabled = true;
		const originalText = button.innerHTML;
		button.innerHTML = nbufPasskeyLogin.strings?.authenticating || 'Authenticating...';
		hideError();

		try {
			const options = await getAuthOptions(currentUsername);

			const publicKeyOptions = {
				challenge: base64urlDecode(options.challenge),
				timeout: options.timeout || 60000,
				rpId: options.rpId,
				userVerification: options.userVerification || 'preferred'
			};

			if (options.allowCredentials && options.allowCredentials.length > 0) {
				publicKeyOptions.allowCredentials = options.allowCredentials.map(cred => ({
					type: cred.type,
					id: base64urlDecode(cred.id),
					transports: cred.transports || undefined
				}));
			}

			const credential = await navigator.credentials.get({
				publicKey: publicKeyOptions
			});

			if (!credential) {
				throw new Error('No credential returned');
			}

			const result = await verifyAuthentication(credential, options.sessionId);

			if (result.requires_2fa) {
				if (result.twofa_redirect) {
					window.location.href = result.twofa_redirect;
				} else {
					showError(result.message || 'Please complete 2FA verification.');
				}
				return;
			}

			if (result.redirect_url) {
				window.location.href = result.redirect_url;
			} else {
				window.location.reload();
			}

		} catch (error) {
			console.error('Passkey authentication error:', error);

			if (error.name === 'NotAllowedError') {
				showError(nbufPasskeyLogin.strings?.canceled || 'Authentication canceled.');
			} else {
				showError(error.message || nbufPasskeyLogin.strings?.error || 'Authentication failed.');
			}

			button.disabled = false;
			button.innerHTML = originalText;
		}
	}

	/* Handle "Use password instead" click */
	function handleUsePassword(e) {
		e.preventDefault();
		showStep2Password();
	}

	/* Handle "Back" / change user click */
	function handleBack(e) {
		e.preventDefault();
		showStep1();
	}

	/* Inject two-step UI elements */
	function injectTwoStepUI() {
		const el = getElements();
		if (!el.form) return false;

		/* Don't inject if already done */
		if (document.getElementById('nbuf-continue-btn')) return true;

		/* Create step indicator */
		const stepIndicator = document.createElement('div');
		stepIndicator.className = 'nbuf-step-indicator';
		stepIndicator.innerHTML = `
			<div class="nbuf-step-dot active"></div>
			<div class="nbuf-step-dot"></div>
		`;

		/* Create current user display (hidden initially) */
		const currentUserDisplay = document.createElement('div');
		currentUserDisplay.id = 'nbuf-current-user';
		currentUserDisplay.className = 'nbuf-current-user';
		currentUserDisplay.style.display = 'none';
		currentUserDisplay.innerHTML = `
			<div class="nbuf-current-user-info">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
					<circle cx="12" cy="7" r="4"></circle>
				</svg>
				<span class="nbuf-current-user-name"></span>
			</div>
			<a href="#" class="nbuf-change-user-link">${nbufPasskeyLogin.strings?.changeUser || 'Change'}</a>
		`;

		/* Create Continue button */
		const continueBtn = document.createElement('button');
		continueBtn.type = 'button';
		continueBtn.id = 'nbuf-continue-btn';
		continueBtn.className = 'button button-primary wp-element-button nbuf-button nbuf-button-primary';
		continueBtn.textContent = nbufPasskeyLogin.strings?.continue || 'Continue';

		/* Create passkey section */
		const passkeySection = document.createElement('div');
		passkeySection.id = 'nbuf-passkey-section';
		passkeySection.style.display = 'none';
		passkeySection.innerHTML = `
			<p class="nbuf-passkey-message">
				${nbufPasskeyLogin.strings?.passkeyAvailable || 'A passkey is available for this account.'}
			</p>
			<button type="button" id="nbuf-passkey-login-btn" class="button button-primary wp-element-button nbuf-button nbuf-button-primary">
				${nbufPasskeyLogin.strings?.signInPasskey || 'Sign in with Passkey'}
			</button>
			<div id="nbuf-passkey-error" style="display: none;"></div>
			<div class="nbuf-passkey-alternative">
				<a href="#" id="nbuf-use-password-link">
					${nbufPasskeyLogin.strings?.usePassword || 'Use password instead'}
				</a>
			</div>
		`;

		/* Insert step indicator at top of form */
		const firstFormElement = el.form.querySelector('.login-username, .nbuf-form-group, p, label');
		if (firstFormElement) {
			el.form.insertBefore(currentUserDisplay, firstFormElement);
			el.form.insertBefore(stepIndicator, currentUserDisplay);
		} else {
			el.form.prepend(currentUserDisplay);
			el.form.prepend(stepIndicator);
		}

		/* Insert Continue button and passkey section before submit */
		if (el.submitButton) {
			const submitParent = el.submitButton.closest('.login-submit, .submit, p, .nbuf-form-row');
			if (submitParent) {
				submitParent.parentNode.insertBefore(continueBtn, submitParent);
				submitParent.parentNode.insertBefore(passkeySection, submitParent);
			} else {
				el.submitButton.parentNode.insertBefore(continueBtn, el.submitButton);
				el.submitButton.parentNode.insertBefore(passkeySection, el.submitButton);
			}
		}

		return true;
	}

	/* Initialize two-step login */
	function initTwoStep() {
		/* Check if we should enable two-step flow */
		if (!nbufPasskeyLogin?.twoStepEnabled) {
			/* Fall back to original single-step behavior */
			initLegacy();
			return;
		}

		if (!injectTwoStepUI()) {
			/* Could not inject UI, use legacy */
			initLegacy();
			return;
		}

		const el = getElements();

		/* Bind events */
		if (el.continueButton) {
			el.continueButton.addEventListener('click', handleContinue);
		}

		/* Rebind after injection */
		const passkeyBtn = document.getElementById('nbuf-passkey-login-btn');
		if (passkeyBtn) {
			passkeyBtn.addEventListener('click', handlePasskeyAuth);
		}

		const usePasswordLink = document.getElementById('nbuf-use-password-link');
		if (usePasswordLink) {
			usePasswordLink.addEventListener('click', handleUsePassword);
		}

		/* Bind change user link */
		const changeUserLink = document.querySelector('.nbuf-change-user-link');
		if (changeUserLink) {
			changeUserLink.addEventListener('click', handleBack);
		}

		/* Handle Enter key on username field */
		if (el.usernameField) {
			el.usernameField.addEventListener('keypress', function(e) {
				if (e.key === 'Enter' && currentStep === 1) {
					e.preventDefault();
					handleContinue(e);
				}
			});
		}

		/* Start at step 1 */
		showStep1();
	}

	/* Legacy single-step initialization (fallback) */
	function initLegacy() {
		const button = document.getElementById('nbuf-passkey-login-btn');
		if (button) {
			button.addEventListener('click', handlePasskeyAuth);

			if (!isWebAuthnSupported()) {
				button.disabled = true;
				button.title = nbufPasskeyLogin.strings?.browserError || 'Your browser does not support passkeys.';
			}
		}
	}

	/* Initialize when DOM is ready */
	function init() {
		/* Check if passkeys login config exists */
		if (typeof nbufPasskeyLogin === 'undefined') {
			return;
		}

		initTwoStep();
	}

	/* Wait for DOM */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
