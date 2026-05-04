/**
 * GDPR Admin Export Button Handler
 *
 * Handles the admin-side user data export button on user profile pages.
 *
 * @package NoBloat_User_Foundry
 * @since 1.4.0
 */

/* global jQuery, ajaxurl, nbufAdminExport */

(function($) {
	'use strict';

	$(document).ready(function() {
		$('#nbuf-admin-export-btn').on('click', function() {
			var $btn = $(this);
			var $status = $('#nbuf-admin-export-status');
			var userId = $btn.data('user-id');

			$btn.prop('disabled', true).text(nbufAdminExport.i18n.generating);
			$status.html('<span style="color: #0073aa;">' + nbufAdminExport.i18n.generating_export + '</span>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'nbuf_admin_export',
					nonce: nbufAdminExport.nonce,
					user_id: userId
				},
				success: function(response) {
					if (response.success) {
						/*
						 * Use .text() rather than .html() so server-supplied
						 * message strings cannot inject HTML even if a future
						 * handler adds user-derived data to the message.
						 */
						$status.empty().append(
							$('<span/>').css('color', '#46b450').text(response.data.message)
						);
						/* Validate download_url to a same-origin admin path before navigating. */
						var downloadUrl = String(response.data.download_url || '');
						if (/^(https?:)?\/\//i.test(downloadUrl)) {
							var allowedOrigin = window.location.origin;
							try {
								var parsed = new URL(downloadUrl, allowedOrigin);
								if (parsed.origin === allowedOrigin) {
									window.location.href = parsed.href;
								}
							} catch (e) { /* malformed; ignore */ }
						} else if (downloadUrl.charAt(0) === '/') {
							window.location.href = downloadUrl;
						}
						setTimeout(function() {
							$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
						}, 2000);
					} else {
						$status.empty().append(
							$('<span/>').css('color', '#dc3232').text(response.data || '')
						);
						$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
					}
				},
				error: function() {
					$status.empty().append(
						$('<span/>').css('color', '#dc3232').text(nbufAdminExport.i18n.error)
					);
					$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
				}
			});
		});
	});
})(jQuery);
