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

		/*
		 * Also count input/paste/change as keyboard-equivalent activity so
		 * password-manager autofill and paste-only flows (which fire no keydown)
		 * are recognized as human input instead of being blocked.
		 */
		form.addEventListener('input', function() {
			interactions.keyboard++;
		}, { passive: true });
		form.addEventListener('paste', function() {
			interactions.keyboard += 2;
		}, { passive: true });
		form.addEventListener('change', function() {
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
	   SHA-256 (synchronous, secure-context-independent)
	   ========================================================= */

	/**
	 * Compute the hex SHA-256 of an ASCII string without the Web Crypto API.
	 * crypto.subtle is only available in secure contexts (HTTPS/localhost), so
	 * relying on it blocked every visitor on plain-HTTP pages. Inputs here are
	 * hex/numeric ASCII (seed/timestamp/sessionId, challenge+nonce).
	 *
	 * @param {string} ascii ASCII input.
	 * @returns {string} Lowercase hex digest, or '' on non-ASCII input.
	 */
	function sha256(ascii) {
		function rightRotate(value, amount) { return (value >>> amount) | (value << (32 - amount)); }
		var mathPow = Math.pow;
		var maxWord = mathPow(2, 32);
		var result = '';
		var words = [];
		var asciiBitLength = ascii.length * 8;
		var hash = sha256.h = sha256.h || [];
		var k = sha256.k = sha256.k || [];
		var primeCounter = k.length;
		var isComposite = {};
		for (var candidate = 2; primeCounter < 64; candidate++) {
			if (!isComposite[candidate]) {
				for (var i = 0; i < 313; i += candidate) { isComposite[i] = candidate; }
				hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
				k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
			}
		}
		ascii += '\x80';
		while (ascii.length % 64 - 56) ascii += '\x00';
		for (i = 0; i < ascii.length; i++) {
			var j = ascii.charCodeAt(i);
			if (j >> 8) return '';
			words[i >> 2] |= j << ((3 - i) % 4) * 8;
		}
		words[words.length] = ((asciiBitLength / maxWord) | 0);
		words[words.length] = (asciiBitLength);
		for (j = 0; j < words.length;) {
			var w = words.slice(j, j += 16);
			var oldHash = hash;
			hash = hash.slice(0, 8);
			for (i = 0; i < 64; i++) {
				var w15 = w[i - 15], w2 = w[i - 2];
				var a = hash[0], e = hash[4];
				var temp1 = hash[7]
					+ (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
					+ ((e & hash[5]) ^ ((~e) & hash[6]))
					+ k[i]
					+ (w[i] = (i < 16) ? w[i] : (
							w[i - 16]
							+ (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
							+ w[i - 7]
							+ (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
						) | 0
					);
				var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
					+ ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
				hash = [(temp1 + temp2) | 0].concat(hash);
				hash[4] = (hash[4] + temp1) | 0;
			}
			for (i = 0; i < 8; i++) { hash[i] = (hash[i] + oldHash[i]) | 0; }
		}
		for (i = 0; i < 8; i++) {
			for (j = 3; j + 1; j--) {
				var b = (hash[i] >> (j * 8)) & 255;
				result += ((b < 16) ? 0 : '') + b.toString(16);
			}
		}
		return result;
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
			/* SHA256(seed + timestamp + sessionId), computed without crypto.subtle. */
			return sha256(config.jsSeed + config.jsTimestamp + config.sessionId);
		} catch (error) {
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
				/* Process in batches, hashing synchronously (no per-iteration await). */
				for (let i = 0; i < batchSize && nonce < maxIterations; i++, nonce++) {
					if (sha256(challenge + nonce.toString()).startsWith(prefix)) {
						return nonce.toString();
					}
				}

				/* Yield to event loop every batch to prevent UI blocking */
				await new Promise(resolve => setTimeout(resolve, 0));
			}

			return '';
		} catch (error) {
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
