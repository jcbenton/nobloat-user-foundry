/**
 * Profile & Cover Photos - JavaScript
 *
 * Handles AJAX upload and deletion of profile and cover photos.
 * Uses wp_localize_script for AJAX URL, nonces, and translations.
 *
 * @package NoBloat_User_Foundry
 * @since 1.3.0
 *
 * Expects NBUF_ProfilePhotos object with:
 * - ajax_url: WordPress AJAX URL
 * - nonces: Object with upload/delete nonces
 * - i18n: Translated strings
 */

jQuery(document).ready(function($) {
	/**
	 * Helper function to reload page while preserving tab state.
	 *
	 * @param {string} subtab The subtab to navigate to (profile-photo or cover-photo).
	 */
	function reloadWithSubtab(subtab) {
		var url = new URL(window.location.href);
		var pathParts = url.pathname.split('/').filter(Boolean);

		/* Check if using Universal Router (path contains 'account') */
		var accountIndex = pathParts.indexOf('account');
		if (accountIndex !== -1) {
			/* Universal Router mode - build path URL */
			var basePath = '/' + pathParts.slice(0, accountIndex + 1).join('/');
			basePath += '/profile/';
			if (subtab) {
				url.search = '?subtab=' + encodeURIComponent(subtab);
			}
			url.pathname = basePath;
		} else {
			/* Legacy mode - use query parameters */
			url.searchParams.set('tab', 'profile');
			if (subtab) {
				url.searchParams.set('subtab', subtab);
			}
		}

		window.location.href = url.toString();
	}

	/* Button click triggers file input */
	$('#nbuf_profile_photo_upload_btn').on('click', function() {
		$('#nbuf_profile_photo_upload').trigger('click');
	});

	$('#nbuf_cover_photo_upload_btn').on('click', function() {
		$('#nbuf_cover_photo_upload').trigger('click');
	});

	/* Profile Photo Upload */
	$('#nbuf_profile_photo_upload').on('change', function(e) {
		var file = e.target.files[0];
		if (!file) return;

		var formData = new FormData();
		formData.append('profile_photo', file);
		formData.append('action', 'nbuf_upload_profile_photo');
		formData.append('nonce', NBUF_ProfilePhotos.nonces.upload_profile);

		$.ajax({
			url: NBUF_ProfilePhotos.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					reloadWithSubtab('profile-photo');
				} else {
					alert(response.data.message || NBUF_ProfilePhotos.i18n.upload_failed);
				}
			},
			error: function() {
				alert(NBUF_ProfilePhotos.i18n.upload_error);
			}
		});
	});

	/* Cover Photo Upload */
	$('#nbuf_cover_photo_upload').on('change', function(e) {
		var file = e.target.files[0];
		if (!file) return;

		var formData = new FormData();
		formData.append('cover_photo', file);
		formData.append('action', 'nbuf_upload_cover_photo');
		formData.append('nonce', NBUF_ProfilePhotos.nonces.upload_cover);

		$.ajax({
			url: NBUF_ProfilePhotos.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					reloadWithSubtab('cover-photo');
				} else {
					alert(response.data.message || NBUF_ProfilePhotos.i18n.upload_failed);
				}
			},
			error: function() {
				alert(NBUF_ProfilePhotos.i18n.upload_error);
			}
		});
	});

	/* Delete Profile Photo */
	$('#nbuf_delete_profile_photo').on('click', function(e) {
		e.preventDefault();

		if (!confirm(NBUF_ProfilePhotos.i18n.confirm_delete_profile)) {
			return;
		}

		$.ajax({
			url: NBUF_ProfilePhotos.ajax_url,
			type: 'POST',
			data: {
				action: 'nbuf_delete_profile_photo',
				nonce: NBUF_ProfilePhotos.nonces.delete_profile
			},
			success: function(response) {
				if (response.success) {
					reloadWithSubtab('profile-photo');
				} else {
					alert(response.data.message || NBUF_ProfilePhotos.i18n.delete_failed);
				}
			},
			error: function() {
				alert(NBUF_ProfilePhotos.i18n.delete_failed);
			}
		});
	});

	/* Delete Cover Photo */
	$('#nbuf_delete_cover_photo').on('click', function(e) {
		e.preventDefault();

		if (!confirm(NBUF_ProfilePhotos.i18n.confirm_delete_cover)) {
			return;
		}

		$.ajax({
			url: NBUF_ProfilePhotos.ajax_url,
			type: 'POST',
			data: {
				action: 'nbuf_delete_cover_photo',
				nonce: NBUF_ProfilePhotos.nonces.delete_cover
			},
			success: function(response) {
				if (response.success) {
					reloadWithSubtab('cover-photo');
				} else {
					alert(response.data.message || NBUF_ProfilePhotos.i18n.delete_failed);
				}
			},
			error: function() {
				alert(NBUF_ProfilePhotos.i18n.delete_failed);
			}
		});
	});
});
