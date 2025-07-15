/**
 * pam-admin-map.js
 * Render/update Mapbox map in admin block-editor and sync meta.
 */
(function () {
	// Feature detect Gutenberg data API
	const hasGutenberg = window.wp && window.wp.data && window.wp.data.select;

	let select, subscribe, dispatch;
	let wasSaving, prevCoords;
	if (hasGutenberg) {
		({ select, subscribe, dispatch } = wp.data);
		wasSaving = select('core/editor').isSavingPost();
		prevCoords = (
			select('core/editor').getEditedPostAttribute('meta')?.pam_coordinates ||
			select('core/editor').getCurrentPost()?.pam_coordinates ||
			''
		);
	} else {
		// fallback for Classic
		wasSaving = false;
		prevCoords = '';
	}

	let waitingForAutosave = false;
	let nextCoords = null;

	/**
	 * Render or move the map & marker based on lat/lng
	 * @param {number} lat
	 * @param {number} lng
	 */
	function pamRenderMap(lat, lng) {
		console.log('[pam] ▶ pamRenderMap called with', lat, lng);
		if (!isFinite(lat) || !isFinite(lng)) {
			console.warn('[pam] Invalid coordinates, aborting render');
			return;
		}
		// Initialize vs update
		if (window.pamAdminMap) {
			console.log('[pam] Moving existing marker');
			window.pamAdminMap.setCenter([lng, lat]);
			window.pamAdminMarker.setLngLat([lng, lat]);
		} else {
			console.log('[pam] Initializing Mapbox map');
			mapboxgl.accessToken = pamAdmin.mapboxKey;
			window.pamAdminMap = new mapboxgl.Map({
				container: 'pam-map-admin',
				style: 'mapbox://styles/mapbox/streets-v11',
				center: [lng, lat],
				zoom: 15
			});
			window.pamAdminMarker = new mapboxgl.Marker({ draggable: true })
				.setLngLat([lng, lat])
				.addTo(window.pamAdminMap)
				.on('dragend', () => {
					const { lat: dLat, lng: dLng } = window.pamAdminMarker.getLngLat();
					const newCoords = `${dLat.toFixed(6)},${dLng.toFixed(6)}`;
					console.log('[pam] Marker dragged to', newCoords);

					// Update the coordinates input field (works everywhere)
					const coordsInput = document.getElementById('pam_coordinates');
					if (coordsInput) coordsInput.value = newCoords;

					// Uncheck auto-geocode
					const cb = document.getElementById('pam_auto_geocode');
					if (cb) cb.checked = false;

					// Update Gutenberg meta if available (block editor only)
					if (window.wp && window.wp.data && window.wp.data.dispatch) {
						window.wp.data.dispatch('core/editor').editPost({
							meta: { pam_coordinates: newCoords, pam_auto_geocode: 0 }
						});
					}
				});

		}
		// Clear auto-geocode checkbox
		const cb = document.getElementById('pam_auto_geocode');
		if (cb) {
			console.log('[pam] Clearing auto-geocode checkbox');
			cb.checked = false;
		}
	}

	// Initial render using localized PHP values
	document.addEventListener('DOMContentLoaded', () => {
		console.log('[pam] DOMContentLoaded — rendering initial map', pamAdmin.lat, pamAdmin.lng);
		pamRenderMap(pamAdmin.lat, pamAdmin.lng);
	});

	if (hasGutenberg) {
		subscribe(() => {
			const meta = select('core/editor').getEditedPostAttribute('meta') || {};
			const root = select('core/editor').getCurrentPost() || {};
			const curr = meta.pam_coordinates || root.pam_coordinates || '';

			// Detect meta change in client state
			if (curr !== prevCoords) {
				console.log('[pam] pam_coordinates changed:', prevCoords, '→', curr);
				prevCoords = curr;
				const [newLat, newLng] = curr.split(',').map(parseFloat);
				pamRenderMap(newLat, newLng);
				// If we are waiting for autosave and just set these coords, autosave now!
				if (waitingForAutosave && curr === nextCoords) {
					console.log('[pam] Now autosaving after confirming meta update:', curr);
					waitingForAutosave = false;
					nextCoords = null;
					setTimeout(() => dispatch('core/editor').autosave(), 250); // <-- 250ms delay
				}
			}

			// Detect save completed
			const isSaving = select('core/editor').isSavingPost();
			const isAutosaving = select('core/editor').isAutosavingPost();

			if (wasSaving && !isSaving && !isAutosaving) {
				console.log('[pam] Save completed—fetching fresh meta from REST API…');
				// Fetch fresh data via REST
				const postId = select('core/editor').getCurrentPostId();
				wp.apiRequest({
					path: `/wp/v2/map_location/${postId}`
				}).then(post => {
					// Support both 'meta' (array) and top-level (string) formats
					const refreshed =
						(post.meta && post.meta.pam_coordinates) ||
						post.pam_coordinates ||
						'';
					console.log('[pam] REST API returned pam_coordinates:', refreshed);
					if (refreshed && refreshed !== prevCoords) {
						prevCoords = refreshed;
						const [rLat, rLng] = refreshed.split(',').map(parseFloat);
						pamRenderMap(rLat, rLng);
						// Also update the meta in the editor, so UI stays in sync:
						dispatch('core/editor').editPost({ meta: { pam_coordinates: refreshed } });
					}
					// Clear the checkbox after successful update:
					const cb = document.getElementById('pam_auto_geocode');
					if (cb) cb.checked = false;
				});
			}

			wasSaving = isSaving;
		});
	}

	// Expose globally if needed
	window.pamRenderMap = pamRenderMap;
})();
