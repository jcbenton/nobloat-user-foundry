/**
 * GDPR Data Export - Frontend JavaScript
 *
 * @package NoBloat_User_Foundry
 * @since   1.4.0
 */

(function ($) {
	'use strict';

	/**
	 * GDPR Export Handler
	 */
	var NBUFGDPRExport = {

		/**
		 * Initialize
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function () {
			$( document ).on( 'click', '#nbuf-request-export', this.handleExportRequest.bind( this ) );
			$( document ).on( 'click', '#nbuf-cancel-export', this.hidePasswordModal.bind( this ) );
			$( document ).on( 'click', '#nbuf-confirm-export', this.confirmExport.bind( this ) );
			$( document ).on(
				'keypress',
				'#nbuf-export-password',
				function (e) {
					if (e.which === 13) {
						$( '#nbuf-confirm-export' ).trigger( 'click' );
					}
				}
			);
		},

		/**
		 * Handle export request button click
		 */
		handleExportRequest: function (e) {
			e.preventDefault();

			/* Check if password confirmation is required */
			if (nbuf_gdpr_vars.require_password) {
				this.showPasswordModal();
			} else {
				this.requestExport( '' );
			}
		},

		/**
		 * Show password confirmation modal
		 */
		showPasswordModal: function () {
			$( '#nbuf-password-modal' ).fadeIn( 200 );
			$( '#nbuf-export-password' ).val( '' ).focus();
			$( '#nbuf-password-error' ).hide();
		},

		/**
		 * Hide password confirmation modal
		 */
		hidePasswordModal: function () {
			$( '#nbuf-password-modal' ).fadeOut( 200 );
			$( '#nbuf-export-password' ).val( '' );
			$( '#nbuf-password-error' ).hide();
		},

		/**
		 * Confirm export with password
		 */
		confirmExport: function (e) {
			e.preventDefault();

			var password = $( '#nbuf-export-password' ).val();

			if ( ! password) {
				this.showPasswordError( nbuf_gdpr_vars.i18n.password_required );
				return;
			}

			/* Disable button */
			$( '#nbuf-confirm-export' ).prop( 'disabled', true ).text( nbuf_gdpr_vars.i18n.processing );

			this.requestExport( password );
		},

		/**
		 * Show password error
		 */
		showPasswordError: function (message) {
			$( '#nbuf-password-error' ).text( message ).fadeIn( 200 );
		},

		/**
		 * Request data export via AJAX
		 */
		requestExport: function (password) {
			var self = this;

			/* Show loading message */
			this.showMessage( 'info', '<span class="nbuf-export-spinner"></span>' + nbuf_gdpr_vars.i18n.generating_export );

			/* Disable export button */
			$( '#nbuf-request-export' ).prop( 'disabled', true );

			/* AJAX request */
			$.ajax(
				{
					url: nbuf_gdpr_vars.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'nbuf_request_export',
						nonce: nbuf_gdpr_vars.nonce,
						password: password
					},
					success: function (response) {
						if (response.success) {
							self.handleExportSuccess( response.data );
						} else {
							self.handleExportError( response.data );
						}
					},
					error: function (xhr, status, error) {
						self.handleExportError( nbuf_gdpr_vars.i18n.ajax_error );
					},
					complete: function () {
						$( '#nbuf-confirm-export' ).prop( 'disabled', false ).text( nbuf_gdpr_vars.i18n.confirm_download );
					}
				}
			);
		},

		/**
		 * Handle successful export
		 */
		handleExportSuccess: function (data) {
			this.hidePasswordModal();

			if (data.method === 'email') {
				/* Email method */
				this.showMessage( 'success', data.message );
				$( '#nbuf-request-export' ).prop( 'disabled', true ).text( nbuf_gdpr_vars.i18n.email_sent );

				/* Reload page after 3 seconds to update UI */
				setTimeout(
					function () {
						window.location.reload();
					},
					3000
				);
			} else {
				/* Direct download method */
				this.showMessage( 'success', data.message );

				/* Trigger download */
				window.location.href = data.download_url;

				/* Reload page after 2 seconds to update UI */
				setTimeout(
					function () {
						window.location.reload();
					},
					2000
				);
			}
		},

		/**
		 * Handle export error
		 */
		handleExportError: function (message) {
			if (typeof message === 'object' && message !== null) {
				message = message.message || message.error || nbuf_gdpr_vars.i18n.unknown_error;
			}

			if (message.toLowerCase().indexOf( 'password' ) !== -1) {
				/* Password error - show in modal */
				this.showPasswordError( message );
			} else {
				/* General error - hide modal and show in main area */
				this.hidePasswordModal();
				this.showMessage( 'error', message );
				$( '#nbuf-request-export' ).prop( 'disabled', false );
			}
		},

		/**
		 * Show message in messages area
		 */
		showMessage: function (type, message) {
			var $messagesArea = $( '#nbuf-export-messages' );
			var html          = '<div class="nbuf-export-notice ' + type + '">' + message + '</div>';
			$messagesArea.html( html ).fadeIn( 200 );
		}
	};

	/**
	 * Initialize on document ready
	 */
	$( document ).ready(
		function () {
			if (typeof nbuf_gdpr_vars !== 'undefined') {
				NBUFGDPRExport.init();
			}
		}
	);

})( jQuery );
