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
						$status.html('<span style="color: #46b450;">' + response.data.message + '</span>');
						window.location.href = response.data.download_url;
						setTimeout(function() {
							$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
						}, 2000);
					} else {
						$status.html('<span style="color: #dc3232;">' + response.data + '</span>');
						$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
					}
				},
				error: function() {
					$status.html('<span style="color: #dc3232;">' + nbufAdminExport.i18n.error + '</span>');
					$btn.prop('disabled', false).text(nbufAdminExport.i18n.download_button);
				}
			});
		});
	});
})(jQuery);
