/**
 * Activity Dashboard JavaScript
 *
 * Handles loading more activity items via AJAX.
 *
 * @package NoBloat_User_Foundry
 * @since 1.5.2
 */

(function() {
	'use strict';

	const loadMoreBtn = document.getElementById('nbuf-load-more-activity');
	if (!loadMoreBtn) return;

	const timeline = document.getElementById('nbuf-activity-timeline');
	const countDisplay = loadMoreBtn.parentElement.querySelector('.nbuf-activity-count');

	loadMoreBtn.addEventListener('click', function() {
		const page = parseInt(this.dataset.page, 10);
		const nonce = this.dataset.nonce;

		/* Disable button and show loading state */
		this.disabled = true;
		const originalText = this.textContent;
		this.textContent = nbuf_activity.loading_text || 'Loading...';

		const formData = new FormData();
		formData.append('action', 'nbuf_load_activity');
		formData.append('nonce', nonce);
		formData.append('page', page);

		fetch(nbuf_activity.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				/* Append new items */
				timeline.insertAdjacentHTML('beforeend', data.data.html);

				/* Update count display */
				if (countDisplay) {
					countDisplay.textContent = nbuf_activity.count_text
						.replace('%1$d', data.data.shown_count)
						.replace('%2$d', data.data.total_count);
				}

				/* Update page number or hide button */
				if (data.data.has_more) {
					loadMoreBtn.dataset.page = page + 1;
					loadMoreBtn.disabled = false;
					loadMoreBtn.textContent = originalText;
				} else {
					loadMoreBtn.style.display = 'none';
				}
			} else {
				loadMoreBtn.disabled = false;
				loadMoreBtn.textContent = originalText;
			}
		})
		.catch(error => {
			loadMoreBtn.disabled = false;
			loadMoreBtn.textContent = originalText;
		});
	});
})();
