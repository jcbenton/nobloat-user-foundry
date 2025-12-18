/**
 * NoBloat User Foundry - Anti-Bot Protection
 *
 * Client-side component for multi-layered bot detection.
 * Works with NBUF_Antibot PHP class for server-side validation.
 *
 * Features:
 * - Interaction tracking (mouse, keyboard, focus, scroll)
 * - JavaScript token generation (SHA-256)
 * - Proof of Work solver (background computation)
 *
 * @package NoBloat_User_Foundry
 * @since   1.5.0
 */

(function() {
	'use strict';

	/* Abort if config not available */
	if (typeof nbufAntibot === 'undefined') {
		return;
	}

	const config = nbufAntibot;

	/* Interaction tracking counters */
	const interactions = {
		mouse: 0,
		keyboard: 0,
		focus: 0,
		scroll: 0
	};

	/* Pre-solved PoW nonce (computed in background) */
	let preSolvedPowNonce = '';

	/**
	 * Set session cookie for server-side tracking.
	 */
	function setSessionCookie() {
		document.cookie = 'nbuf_antibot_session=' + config.sessionId +
			';path=/;SameSite=Strict;max-age=3600';
	}

	/* =========================================================
	   INTERACTION TRACKING
	   ========================================================= */

	/**
	 * Set up interaction event listeners on the form.
	 *
	 * @param {HTMLFormElement} form The registration form element.
	 */
	function trackInteractions(form) {
		if (!config.interactionEnabled) {
			return;
		}

		/* Mouse movement (throttled via passive) */
		let lastMouseTime = 0;
		form.addEventListener('mousemove', function() {
			const now = Date.now();
			if (now - lastMouseTime > 100) {
				interactions.mouse++;
				lastMouseTime = now;
			}
		}, { passive: true });

		/* Mouse clicks count more */
		form.addEventListener('click', function() {
			interactions.mouse += 5;
		}, { passive: true });

		/* Keyboard input */
		form.addEventListener('keydown', function() {
			interactions.keyboard++;
		}, { passive: true });

		/* Focus changes */
		form.addEventListener('focusin', function() {
			interactions.focus++;
		}, { passive: true });

		/* Scroll events (throttled) */
		let lastScrollTime = 0;
		window.addEventListener('scroll', function() {
			const now = Date.now();
			if (now - lastScrollTime > 200) {
				interactions.scroll++;
				lastScrollTime = now;
			}
		}, { passive: true });
	}

	/* =========================================================
	   JAVASCRIPT TOKEN GENERATION
	   ========================================================= */

	/**
	 * Generate JavaScript token using Web Crypto API.
	 *
	 * Computes: SHA256(seed + timestamp + sessionId)
	 * where seed and timestamp are provided by server in config.
	 *
	 * @returns {Promise<string>} Hex-encoded SHA-256 hash.
	 */
	async function generateJsToken() {
		if (!config.jsTokenEnabled || !config.jsSeed || !config.jsTimestamp) {
			return '';
		}

		try {
			/* Compute hash: seed + timestamp + sessionId (using server-provided timestamp) */
			const data = config.jsSeed + config.jsTimestamp + config.sessionId;
			const encoder = new TextEncoder();
			const dataBuffer = encoder.encode(data);

			const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
			const hashArray = Array.from(new Uint8Array(hashBuffer));
			const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

			return hashHex;
		} catch (error) {
			console.warn('NBUF Antibot: JS token generation failed', error);
			return '';
		}
	}

	/* =========================================================
	   PROOF OF WORK SOLVER
	   ========================================================= */

	/**
	 * Solve Proof of Work challenge.
	 *
	 * Finds a nonce where SHA256(challenge + nonce) starts with N zeros.
	 * Runs in background with periodic yields to prevent UI blocking.
	 *
	 * @returns {Promise<string>} Nonce that solves the challenge.
	 */
	async function solveProofOfWork() {
		if (!config.powEnabled || !config.powChallenge) {
			return '';
		}

		const difficulty = config.powDifficulty || 3;
		const prefix = '0'.repeat(difficulty);
		const challenge = config.powChallenge;

		let nonce = 0;
		const maxIterations = 10000000;
		const batchSize = 1000;

		try {
			while (nonce < maxIterations) {
				/* Process in batches */
				for (let i = 0; i < batchSize && nonce < maxIterations; i++, nonce++) {
					const data = challenge + nonce.toString();
					const encoder = new TextEncoder();
					const dataBuffer = encoder.encode(data);

					const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
					const hashArray = Array.from(new Uint8Array(hashBuffer));
					const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

					if (hashHex.startsWith(prefix)) {
						return nonce.toString();
					}
				}

				/* Yield to event loop every batch to prevent UI blocking */
				await new Promise(resolve => setTimeout(resolve, 0));
			}

			console.warn('NBUF Antibot: PoW max iterations reached');
			return '';
		} catch (error) {
			console.warn('NBUF Antibot: PoW solving failed', error);
			return '';
		}
	}

	/* =========================================================
	   FORM SUBMISSION HANDLER
	   ========================================================= */

	/**
	 * Handle form submission.
	 *
	 * Intercepts submit to add anti-bot tokens before sending.
	 *
	 * @param {Event} event Submit event.
	 */
	async function handleSubmit(event) {
		const form = event.target;

		/* Prevent immediate submission */
		event.preventDefault();

		/* Show loading state on submit button */
		const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
		let originalText = '';
		let originalValue = '';

		if (submitBtn) {
			submitBtn.disabled = true;
			if (submitBtn.tagName === 'BUTTON') {
				originalText = submitBtn.textContent;
				submitBtn.textContent = 'Processing...';
			} else {
				originalValue = submitBtn.value;
				submitBtn.value = 'Processing...';
			}
		}

		try {
			/* Generate JS token */
			const jsToken = await generateJsToken();
			const jsTokenInput = form.querySelector('input[name="nbuf_js_token"]');
			if (jsTokenInput) {
				jsTokenInput.value = jsToken;
			}

			/* Use pre-solved PoW or solve now */
			let powNonce = preSolvedPowNonce;
			if (!powNonce && config.powEnabled) {
				powNonce = await solveProofOfWork();
			}
			const powInput = form.querySelector('input[name="nbuf_pow_nonce"]');
			if (powInput) {
				powInput.value = powNonce;
			}

			/* Encode interaction data */
			const interactionInput = form.querySelector('input[name="nbuf_interaction"]');
			if (interactionInput) {
				interactionInput.value = btoa(JSON.stringify(interactions));
			}

			/*
			 * Add hidden input for submit button value.
			 * When form.submit() is called programmatically, the submit button's
			 * name/value is NOT included in POST data. We need to add it manually.
			 */
			if (!form.querySelector('input[name="nbuf_register"]')) {
				const registerInput = document.createElement('input');
				registerInput.type = 'hidden';
				registerInput.name = 'nbuf_register';
				registerInput.value = '1';
				form.appendChild(registerInput);
			}

			/* Submit the form */
			form.submit();

		} catch (error) {
			/* Restore button state */
			if (submitBtn) {
				submitBtn.disabled = false;
				if (submitBtn.tagName === 'BUTTON') {
					submitBtn.textContent = originalText;
				} else {
					submitBtn.value = originalValue;
				}
			}

			/* Add nbuf_register hidden input before fallback submit */
			if (!form.querySelector('input[name="nbuf_register"]')) {
				const registerInput = document.createElement('input');
				registerInput.type = 'hidden';
				registerInput.name = 'nbuf_register';
				registerInput.value = '1';
				form.appendChild(registerInput);
			}

			/* Still try to submit (server will validate) */
			form.submit();
		}
	}

	/* =========================================================
	   INITIALIZATION
	   ========================================================= */

	/**
	 * Initialize anti-bot protection.
	 */
	function init() {
		/* Set session cookie */
		setSessionCookie();

		/* Find registration form */
		const form = document.querySelector(config.formSelector);
		if (!form) {
			return;
		}

		/* Start tracking interactions immediately */
		trackInteractions(form);

		/* Hook form submission */
		form.addEventListener('submit', handleSubmit);

		/* Start solving PoW in background (ready before user submits) */
		if (config.powEnabled && config.powChallenge) {
			solveProofOfWork().then(nonce => {
				preSolvedPowNonce = nonce;
			});
		}
	}

	/* Run on DOM ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
