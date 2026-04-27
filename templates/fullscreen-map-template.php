<?php
/**
 * Template for Fullscreen Public Art Map
 */ ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php
// Get the Mapbox API key from the settings

$mapbox_key = get_option( 'pam_mapbox_api_key' );
$logo_url = esc_url( get_option( 'pam_site_logo' ) );

// Pin location data
$locations = get_posts(array(
	'post_type'      => 'map_location',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
));

// Artwork type data
$terms = get_terms(array(
	'taxonomy'   => 'artwork_type',
	'hide_empty' => true,
));

$term_map = array();
foreach ( $terms as $term ) {
	$term_map[] = array(
		'id'    => $term->term_id,
		'slug'  => $term->slug,
		'name'  => $term->name,
	);
}

$location_data = array();
foreach ( $locations as $location ) {
	$coords = get_post_meta( $location->ID, 'pam_coordinates', true );
	if ( ! empty( $coords ) && strpos( $coords, ',' ) !== false ) {
		list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coords ) );

		$types = wp_get_post_terms( $location->ID, 'artwork_type', array( 'fields' => 'all' ) );
		$first_type = $types[0] ?? null;

		$collection_terms_for_location = wp_get_post_terms( $location->ID, 'artwork_collection', array( 'fields' => 'all' ) );
		$collections = wp_list_pluck( $collection_terms_for_location, 'slug' );

		$color = '#4a7789';
		$icon_url = null;

		if ( $first_type ) {
			$term_color = get_term_meta( $first_type->term_id, 'pam_color', true );
			if ( $term_color ) {
				$color = $term_color;
			}
			$term_icon_id = get_term_meta( $first_type->term_id, 'pam_icon', true );
			$icon_url = '';
			if ( $term_icon_id ) {
				$icon_info = wp_get_attachment_image_src( $term_icon_id, 'thumbnail' );
				if ( $icon_info ) {
					$icon_url = esc_url_raw( $icon_info[0] );
				}
			}
		}

		$thumb = get_the_post_thumbnail_url( $location->ID, 'medium' );
		$image = get_the_post_thumbnail_url( $location->ID, 'large' ) ?: $thumb;
		$address = trim( get_post_meta( $location->ID, 'pam_address', true ) );
		$city = trim( get_post_meta( $location->ID, 'pam_city', true ) );
		$state = trim( get_post_meta( $location->ID, 'pam_state', true ) );
		$zip = trim( get_post_meta( $location->ID, 'pam_zip', true ) );
		$location_parts = array_filter( array( $address, $city, $state, $zip ) );

		$location_data[] = array(
			'id'      => $location->ID,
			'title'   => wp_specialchars_decode( get_the_title( $location ), ENT_QUOTES ),
			'artist'  => get_post_meta( $location->ID, 'pam_artist', true ),
			'location'=> implode( ', ', $location_parts ),
			'lat'     => $lat,
			'lng'     => $lng,
			'types'   => wp_list_pluck( $types, 'slug' ),
			'typeNames' => wp_list_pluck( $types, 'name' ),
			'collections'=> $collections,
			'collectionNames'=> wp_list_pluck( $collection_terms_for_location, 'name' ),
			'color'   => $color,
			'icon'    => $icon_url,
			'thumb'   => $thumb,
			'image'   => $image,
			'url'     => get_permalink( $location ),
			'directionsUrl' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng ) . '&travelmode=walking',
		);

		$collection_terms = get_terms(array(
			'taxonomy'   => 'artwork_collection',
			'hide_empty' => true,
		));
		$collection_map = array();
		foreach ( $collection_terms as $term ) {
			$collection_map[] = array(
				'id'   => $term->term_id,
				'slug' => $term->slug,
				'name' => $term->name,
			);
		}

	}
}

?>

<div id="pam-ui">
	<?php if ( $logo_url ) : ?>
		<a href="<?php echo esc_url( home_url() ); ?>" id="pam-site-logo">
			<img src="<?php echo $logo_url; ?>" alt="Return to Site" />
		</a>
	<?php endif; ?>

	<button id="pam-filter-toggle" aria-controls="pam-filter-drawer" aria-expanded="false">
		☰ Filter
	</button>

	<div id="pam-active-filters" class="pam-active-filters"></div>
</div>

<!-- Filter Options -->
<div id="pam-filter">
	<strong>Filter by Type</strong><br>
	<div id="pam-filter-options-desktop"></div>
	<strong style="margin-top:1em;display:block;">Filter by Collection</strong>
	<div id="pam-filter-collections-desktop"></div>
</div>

<!-- Slide-In Drawer for Mobile -->
<div id="pam-filter-drawer">
	<strong>Filter by Type</strong><br>
	<div id="pam-filter-options-mobile"></div>
	<strong style="margin-top:1em;display:block;">Filter by Collection</strong>
	<div id="pam-filter-collections-mobile"></div>
	<button id="pam-filter-close">Close</button>
</div>

<div id="pam-map"></div>
<div id="pam-inset" class="pam-inset" aria-label="Distant public art locations">
	<div class="pam-inset-label">Further away</div>
	<button type="button" id="pam-inset-close" class="pam-inset-close" aria-label="Hide inset map">×</button>
	<div id="pam-inset-map"></div>
</div>

<div id="pam-detail-overlay" class="pam-detail-overlay" hidden></div>
<aside id="pam-detail-panel" class="pam-detail-panel" aria-hidden="true" aria-label="Public art location details">
	<button type="button" id="pam-detail-close" class="pam-detail-close" aria-label="Close location details">×</button>
	<div class="pam-detail-handle" aria-hidden="true"></div>
	<img id="pam-detail-image" class="pam-detail-image" alt="">
	<div class="pam-detail-body">
		<h2 id="pam-detail-title"></h2>
		<dl class="pam-detail-list">
			<div class="pam-detail-row" data-field="artist">
				<dt>Artist</dt>
				<dd id="pam-detail-artist"></dd>
			</div>
			<div class="pam-detail-row" data-field="location">
				<dt>Location</dt>
				<dd>
					<span id="pam-detail-location"></span>
					<a id="pam-detail-directions" class="pam-detail-directions" target="_blank" rel="noopener noreferrer">Walking directions</a>
				</dd>
			</div>
			<div class="pam-detail-row" data-field="type">
				<dt>Type</dt>
				<dd id="pam-detail-type"></dd>
			</div>
			<div class="pam-detail-row" data-field="collection">
				<dt>Collection</dt>
				<dd id="pam-detail-collection"></dd>
			</div>
		</dl>
	</div>
</aside>

<script>
const pamLocations = <?php echo wp_json_encode( $location_data ); ?>;
const pamTypes = <?php echo wp_json_encode( $term_map ); ?>;
const pamCollections = <?php echo wp_json_encode( $collection_map ); ?>;

// Global filter init
function getInitialFilters() {
	const urlParams = new URLSearchParams(window.location.search);
	return {
		types: urlParams.getAll('type'),
		collections: urlParams.getAll('collection')
	};
}
const initialFilters = getInitialFilters();

document.addEventListener('DOMContentLoaded', function () {
	const toggleBtn = document.getElementById('pam-filter-toggle');
	const drawer = document.getElementById('pam-filter-drawer');
	const closeBtn = document.getElementById('pam-filter-close');

	if (toggleBtn && drawer && closeBtn) {
		toggleBtn.addEventListener('click', () => {
			drawer.classList.add('is-visible');
			toggleBtn.setAttribute('aria-expanded', 'true');
			// Add listener for click outside
			document.addEventListener('click', handleOutsideClick);
		});

		closeBtn.addEventListener('click', () => {
			closeDrawer();
		});

		function closeDrawer() {
			drawer.classList.remove('is-visible');
			toggleBtn.setAttribute('aria-expanded', 'false');
			document.removeEventListener('click', handleOutsideClick);
		}

		function handleOutsideClick(e) {
			if (
				!drawer.contains(e.target) &&
				!toggleBtn.contains(e.target)
			) {
				closeDrawer();
			}
		}
	}
});

document.addEventListener('DOMContentLoaded', function () {
	mapboxgl.accessToken = '<?php echo esc_js( $mapbox_key ); ?>';
	const INSET_DISTANCE_MILES = 12;
	const INSET_MIN_NEARBY_LOCATIONS = 5;

	const map = new mapboxgl.Map({
		container: 'pam-map',
		style: 'mapbox://styles/mapbox/streets-v11',
		pitch: 45,
		bearing: 0,
		antialias: true
	});

	map.addControl(new mapboxgl.NavigationControl());

	const insetContainer = document.getElementById('pam-inset');
	const insetLabel = insetContainer.querySelector('.pam-inset-label');
	const insetClose = document.getElementById('pam-inset-close');
	const insetMap = new mapboxgl.Map({
		container: 'pam-inset-map',
		style: 'mapbox://styles/mapbox/streets-v11',
		interactive: true,
		attributionControl: false
	});

	let markers = [];
	let insetMarkers = [];
	let insetDismissed = false;
	let activeMapView = 'nearby';
	let currentLocations = [];
	let mapsReady = false;
	let mainRenderer = null;
	let insetRenderer = null;

	const MAIN_SOURCE_ID = 'pam-main-locations';
	const INSET_SOURCE_ID = 'pam-inset-locations';
	const locationById = new Map(pamLocations.map(loc => [String(loc.id), loc]));
	const detailOverlay = document.getElementById('pam-detail-overlay');
	const detailPanel = document.getElementById('pam-detail-panel');
	const detailClose = document.getElementById('pam-detail-close');
	const detailImage = document.getElementById('pam-detail-image');
	const detailTitle = document.getElementById('pam-detail-title');
	const detailArtist = document.getElementById('pam-detail-artist');
	const detailLocation = document.getElementById('pam-detail-location');
	const detailDirections = document.getElementById('pam-detail-directions');
	const detailType = document.getElementById('pam-detail-type');
	const detailCollection = document.getElementById('pam-detail-collection');

	function whenMapLoads(targetMap) {
		return new Promise(resolve => {
			if (targetMap.loaded()) {
				resolve();
				return;
			}

			targetMap.once('load', resolve);
		});
	}

	function createFeatureCollection(locations) {
		return {
			type: 'FeatureCollection',
			features: locations.map(loc => ({
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: [loc.lng, loc.lat]
				},
				properties: {
					id: String(loc.id)
				}
			}))
		};
	}

	function ensureClusterSource(targetMap, sourceId) {
		if (!targetMap.getSource(sourceId)) {
			targetMap.addSource(sourceId, {
				type: 'geojson',
				data: createFeatureCollection([]),
				cluster: true,
				clusterMaxZoom: 17,
				clusterRadius: 36
			});
		}

		if (!targetMap.getLayer(`${sourceId}-clusters-hit`)) {
			targetMap.addLayer({
				id: `${sourceId}-clusters-hit`,
				type: 'circle',
				source: sourceId,
				filter: ['has', 'point_count'],
				paint: {
					'circle-radius': 1,
					'circle-opacity': 0
				}
			});
		}

		if (!targetMap.getLayer(`${sourceId}-points-hit`)) {
			targetMap.addLayer({
				id: `${sourceId}-points-hit`,
				type: 'circle',
				source: sourceId,
				filter: ['!', ['has', 'point_count']],
				paint: {
					'circle-radius': 1,
					'circle-opacity': 0
				}
			});
		}
	}

	function createLocationMarkerElement(loc) {
		const baseColor = loc.color || '#fff';
		const imageUrl = loc.thumb || loc.icon || '';

		const markerEl = document.createElement('div');
		markerEl.className = 'pam-marker-wrapper';
		markerEl.style.setProperty('--pam-marker-color', baseColor);

		const markerContent = document.createElement('div');
		markerContent.className = 'pam-marker-content';
		if (imageUrl) {
			markerContent.style.backgroundImage = `url('${imageUrl}')`;
		}
		markerContent.style.backgroundColor = baseColor;

		markerEl.appendChild(markerContent);
		markerEl.addEventListener('mouseenter', () => {
			markerEl.classList.add('is-hovered');
			markerEl.style.zIndex = '1000';
		});
		markerEl.addEventListener('mouseleave', () => {
			markerEl.classList.remove('is-hovered');
			markerEl.style.zIndex = '';
		});

		return markerEl;
	}

	function createClusterMarkerElement(count) {
		const markerEl = document.createElement('button');
		markerEl.type = 'button';
		markerEl.className = 'pam-cluster-marker';
		markerEl.setAttribute('aria-label', `${count} public art locations`);

		const stack = document.createElement('span');
		stack.className = 'pam-cluster-stack';
		for (let index = 0; index < 3; index++) {
			const image = document.createElement('span');
			image.className = 'pam-cluster-thumb';
			stack.appendChild(image);
		}

		const countBadge = document.createElement('span');
		countBadge.className = 'pam-cluster-count';
		countBadge.textContent = count;

		markerEl.append(stack, countBadge);
		return markerEl;
	}

	function hydrateClusterMarker(targetMap, sourceId, markerEl, clusterId) {
		const source = targetMap.getSource(sourceId);
		if (!source || !source.getClusterLeaves) {
			return;
		}

		source.getClusterLeaves(clusterId, 3, 0, (error, leaves) => {
			if (error || !leaves) {
				return;
			}

			const thumbs = markerEl.querySelectorAll('.pam-cluster-thumb');
			leaves.forEach((leaf, index) => {
				const loc = locationById.get(String(leaf.properties.id));
				const imageUrl = loc?.thumb || loc?.icon || '';
				if (thumbs[index] && imageUrl) {
					thumbs[index].style.backgroundImage = `url('${imageUrl}')`;
					thumbs[index].style.backgroundColor = loc.color || '#4a7789';
				}
			});
		});
	}

	function createClusteredMarkerRenderer(targetMap, sourceId, options = {}) {
		const markerCache = {};
		let markersOnScreen = {};
		let hasData = false;
		let updateScheduled = false;

		ensureClusterSource(targetMap, sourceId);

		function clearMarkers() {
			Object.values(markersOnScreen).forEach(marker => marker.remove());
			markersOnScreen = {};
		}

		function scheduleUpdate() {
			if (updateScheduled) {
				return;
			}

			updateScheduled = true;
			requestAnimationFrame(() => {
				updateScheduled = false;
				updateMarkers();
			});
		}

		function updateMarkers() {
			if (!hasData || !targetMap.getSource(sourceId)) {
				clearMarkers();
				return;
			}

			const features = targetMap.querySourceFeatures(sourceId);
			const nextMarkers = {};
			const seen = new Set();

			features.forEach(feature => {
				const props = feature.properties || {};
				const isCluster = Boolean(props.cluster);
				const key = isCluster ? `cluster-${props.cluster_id}` : `location-${props.id}`;
				const coords = feature.geometry.coordinates;

				if (seen.has(key)) {
					return;
				}
				seen.add(key);

				let marker = markerCache[key];
				if (!marker) {
					if (isCluster) {
						const markerEl = createClusterMarkerElement(props.point_count_abbreviated || props.point_count);
						markerEl.addEventListener('click', event => {
							event.stopPropagation();
							if (options.onClusterClick) {
								options.onClusterClick(markerEl.pamClusterFeature);
							}
						});
						marker = markerCache[key] = new mapboxgl.Marker({ element: markerEl, anchor: 'center' }).setLngLat(coords);
						hydrateClusterMarker(targetMap, sourceId, markerEl, props.cluster_id);
					} else {
						const loc = locationById.get(String(props.id));
						if (!loc) {
							return;
						}
						const markerEl = createLocationMarkerElement(loc);
						markerEl.addEventListener('click', event => {
							event.stopPropagation();
							if (options.onLocationClick) {
								options.onLocationClick(loc);
							}
						});
						marker = markerCache[key] = new mapboxgl.Marker({ element: markerEl, anchor: 'bottom' }).setLngLat([loc.lng, loc.lat]);
					}
				}

				if (isCluster) {
					marker.getElement().pamClusterFeature = feature;
				}
				marker.setLngLat(coords);
				nextMarkers[key] = marker;
				if (!markersOnScreen[key]) {
					marker.addTo(targetMap);
				}
			});

			Object.keys(markersOnScreen).forEach(key => {
				if (!nextMarkers[key]) {
					markersOnScreen[key].remove();
				}
			});

			markersOnScreen = nextMarkers;
		}

		targetMap.on('render', scheduleUpdate);
		targetMap.on('moveend', scheduleUpdate);
		targetMap.on('zoomend', scheduleUpdate);

		return {
			setLocations(locations) {
				hasData = locations.length > 0;
				targetMap.getSource(sourceId).setData(createFeatureCollection(locations));
				targetMap.once('idle', updateMarkers);
				scheduleUpdate();
			},
			clear() {
				hasData = false;
				targetMap.getSource(sourceId).setData(createFeatureCollection([]));
				clearMarkers();
			}
		};
	}

	function expandCluster(targetMap, sourceId, feature) {
		const source = targetMap.getSource(sourceId);
		if (!source || !source.getClusterExpansionZoom) {
			return;
		}

		source.getClusterExpansionZoom(feature.properties.cluster_id, (error, zoom) => {
			if (error) {
				return;
			}

			targetMap.easeTo({
				center: feature.geometry.coordinates,
				zoom
			});
		});
	}

	function setText(element, value) {
		element.textContent = value || '';
	}

	function setDetailRow(field, value, targetElement) {
		const row = detailPanel.querySelector(`[data-field="${field}"]`);
		const hasValue = Boolean(value);
		row.hidden = !hasValue;
		if (targetElement) {
			setText(targetElement, value);
		}
	}

	function showLocationDetails(loc) {
		detailImage.hidden = !loc.image;
		if (loc.image) {
			detailImage.src = loc.image;
			detailImage.alt = loc.title ? `${loc.title} artwork image` : 'Artwork image';
		} else {
			detailImage.removeAttribute('src');
			detailImage.alt = '';
		}

		setText(detailTitle, loc.title);
		setDetailRow('artist', loc.artist, detailArtist);
		setDetailRow('location', loc.location, detailLocation);
		setDetailRow('type', (loc.typeNames || []).join(', '), detailType);
		setDetailRow('collection', (loc.collectionNames || []).join(', '), detailCollection);

		detailDirections.href = loc.directionsUrl || '#';
		detailDirections.hidden = !loc.directionsUrl || !loc.location;
		detailOverlay.hidden = false;
		detailPanel.setAttribute('aria-hidden', 'false');
		detailOverlay.classList.add('is-visible');
		detailPanel.classList.add('is-visible');
	}

	function closeLocationDetails() {
		detailOverlay.classList.remove('is-visible');
		detailPanel.classList.remove('is-visible');
		detailPanel.setAttribute('aria-hidden', 'true');
		setTimeout(() => {
			if (!detailPanel.classList.contains('is-visible')) {
				detailOverlay.hidden = true;
			}
		}, 240);
	}

	let detailTouchStartY = null;
	detailPanel.addEventListener('touchstart', event => {
		detailTouchStartY = event.touches[0].clientY;
	}, { passive: true });
	detailPanel.addEventListener('touchend', event => {
		if (detailTouchStartY === null) {
			return;
		}
		const touchEndY = event.changedTouches[0].clientY;
		if (touchEndY - detailTouchStartY > 60) {
			closeLocationDetails();
		}
		detailTouchStartY = null;
	}, { passive: true });
	detailOverlay.addEventListener('click', closeLocationDetails);
	detailClose.addEventListener('click', closeLocationDetails);

	function getMedian(values) {
		const sorted = [...values].sort((a, b) => a - b);
		const middle = Math.floor(sorted.length / 2);

		if (sorted.length % 2) {
			return sorted[middle];
		}

		return (sorted[middle - 1] + sorted[middle]) / 2;
	}

	function getDistanceMiles(a, b) {
		const earthRadiusMiles = 3958.8;
		const toRadians = degrees => degrees * Math.PI / 180;
		const latDelta = toRadians(b.lat - a.lat);
		const lngDelta = toRadians(b.lng - a.lng);
		const startLat = toRadians(a.lat);
		const endLat = toRadians(b.lat);
		const haversine =
			Math.sin(latDelta / 2) * Math.sin(latDelta / 2) +
			Math.cos(startLat) * Math.cos(endLat) *
			Math.sin(lngDelta / 2) * Math.sin(lngDelta / 2);

		return 2 * earthRadiusMiles * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
	}

	function splitInsetLocations(locations) {
		if (locations.length < INSET_MIN_NEARBY_LOCATIONS + 1) {
			return {
				active: false,
				nearby: locations,
				distant: []
			};
		}

		const anchor = {
			lat: getMedian(locations.map(loc => loc.lat)),
			lng: getMedian(locations.map(loc => loc.lng))
		};
		const nearby = [];
		const distant = [];

		locations.forEach(loc => {
			if (getDistanceMiles(anchor, loc) > INSET_DISTANCE_MILES) {
				distant.push(loc);
			} else {
				nearby.push(loc);
			}
		});

		const active = nearby.length >= INSET_MIN_NEARBY_LOCATIONS && distant.length > 0;

		return {
			active,
			nearby,
			distant
		};
	}

	function fitMapToLocations(targetMap, locations, padding, fallbackZoom) {
		if (locations.length === 0) {
			return;
		}

		if (locations.length === 1) {
			targetMap.flyTo({
				center: [locations[0].lng, locations[0].lat],
				zoom: fallbackZoom
			});
			return;
		}

		const bounds = new mapboxgl.LngLatBounds();
		locations.forEach(loc => bounds.extend([loc.lng, loc.lat]));
		targetMap.fitBounds(bounds, { padding });
	}

	function setInsetLabel(view) {
		insetLabel.textContent = view === 'distant' ? 'Main cluster' : 'Further away';
		insetContainer.setAttribute(
			'aria-label',
			view === 'distant' ? 'Main cluster public art locations' : 'Further away public art locations'
		);
	}

	function swapMapView() {
		activeMapView = activeMapView === 'distant' ? 'nearby' : 'distant';
		renderMarkers(currentLocations);
	}

	function renderMarkers(locations) {
		if (!mapsReady) {
			return;
		}

		const splitLocations = splitInsetLocations(locations);
		const canShowInset = splitLocations.active && !insetDismissed;
		const mainLocations = splitLocations.active && activeMapView === 'distant'
			? splitLocations.distant
			: splitLocations.nearby;
		const insetLocations = splitLocations.active && activeMapView === 'distant'
			? splitLocations.nearby
			: splitLocations.distant;

		mainRenderer.setLocations(mainLocations);

		const isSmallScreen = window.innerWidth < 600;
		fitMapToLocations(map, mainLocations, isSmallScreen ? 100 : 200, activeMapView === 'distant' ? 10 : 13);

		if (canShowInset) {
			setInsetLabel(activeMapView);
			insetContainer.classList.add('is-visible');
			insetRenderer.setLocations(insetLocations);
			requestAnimationFrame(() => {
				insetMap.resize();
				fitMapToLocations(insetMap, insetLocations, 45, activeMapView === 'distant' ? 13 : 10);
			});
		} else {
			insetContainer.classList.remove('is-visible');
			insetRenderer.clear();
		}
	}

	insetContainer.addEventListener('click', event => {
		if (
			event.target.closest('#pam-inset-close') ||
			event.target.closest('.mapboxgl-popup') ||
			event.target.closest('.mapboxgl-ctrl')
		) {
			return;
		}

		swapMapView();
	});

	insetClose.addEventListener('click', event => {
		event.stopPropagation();
		insetDismissed = true;
		insetContainer.classList.remove('is-visible');
	});

	function getSelectedTypes() {
		return Array.from(document.querySelectorAll('.type-filter:checked'))
			.map(cb => cb.value);
	}

	function getSelectedCollections() {
		return Array.from(document.querySelectorAll('.collection-filter:checked'))
			.map(cb => cb.value);
	}

	function filterLocations() {
		const selectedTypes = getSelectedTypes();
		const selectedCollections = getSelectedCollections();

		const filtered = pamLocations.filter(loc => {
			const matchesType = selectedTypes.length === 0 || loc.types.some(type => selectedTypes.includes(type));
			const matchesCollection = selectedCollections.length === 0 || loc.collections.some(col => selectedCollections.includes(col));
			return matchesType && matchesCollection;
		});

		insetDismissed = false;
		activeMapView = 'nearby';
		currentLocations = filtered;
		closeLocationDetails();
		renderMarkers(filtered);
		updateActiveFiltersDisplay();

		const params = new URLSearchParams();

		getSelectedTypes().forEach(type => params.append('type', type));
		getSelectedCollections().forEach(col => params.append('collection', col));

		const newUrl = `${window.location.pathname}?${params.toString()}`;
		window.history.replaceState({}, '', newUrl);
	}

	function updateActiveFiltersDisplay() {
		const container = document.getElementById('pam-active-filters');
		container.innerHTML = ''; // Clear old

		const activeCheckboxes = document.querySelectorAll('input[type="checkbox"]:checked');
		const seenSlugs = new Set();

		activeCheckboxes.forEach(checkbox => {
			const slug = checkbox.getAttribute('data-slug');

			if (seenSlugs.has(slug)) return; // Already added this one
			seenSlugs.add(slug);

			const labelText = checkbox.parentNode.textContent.trim();

			const chip = document.createElement('span');
			chip.className = 'pam-filter-chip';
			chip.textContent = labelText;

			const close = document.createElement('button');
			close.textContent = '×';
			close.setAttribute('aria-label', `Remove ${labelText}`);
			close.addEventListener('click', () => {
				// Uncheck all checkboxes with matching data-slug
				document.querySelectorAll(`input[data-slug="${slug}"]`).forEach(cb => {
					cb.checked = false;
				});
				filterLocations();
				updateActiveFiltersDisplay();
			});

			chip.appendChild(close);
			container.appendChild(chip);
		});
	}

	function createCheckbox(term, type = 'type') {
		const wrapper = document.createElement('div');

		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.value = term.slug;
		checkbox.setAttribute('data-slug', term.slug); // used for syncing

		if (type === 'collection') {
			checkbox.classList.add('collection-filter');
		} else {
			checkbox.classList.add('type-filter');
		}

		if ((type === 'type' && initialFilters.types.includes(term.slug)) ||
			(type === 'collection' && initialFilters.collections.includes(term.slug))) {
			checkbox.checked = true;
		}

		// Add change listener with sync
		checkbox.addEventListener('change', function () {
			const matching = document.querySelectorAll(`input[data-slug="${term.slug}"]`);
			matching.forEach(cb => {
				if (cb !== this) cb.checked = this.checked;
			});
			filterLocations(); // update map
		});

		const label = document.createElement('label');
		label.appendChild(checkbox);
		label.append(` ${term.name}`);

		wrapper.appendChild(label);
		return wrapper;
	}

	pamTypes.forEach(term => {
		document.getElementById('pam-filter-options-desktop')
			.appendChild(createCheckbox(term, 'type'));
		document.getElementById('pam-filter-options-mobile')
			.appendChild(createCheckbox(term, 'type'));
	});

	pamCollections.forEach(term => {
		document.getElementById('pam-filter-collections-desktop')
			.appendChild(createCheckbox(term, 'collection'));
		document.getElementById('pam-filter-collections-mobile')
			.appendChild(createCheckbox(term, 'collection'));
	});

	Promise.all([whenMapLoads(map), whenMapLoads(insetMap)]).then(() => {
		mapsReady = true;
		mainRenderer = createClusteredMarkerRenderer(map, MAIN_SOURCE_ID, {
			onLocationClick: showLocationDetails,
			onClusterClick: feature => expandCluster(map, MAIN_SOURCE_ID, feature)
		});
		insetRenderer = createClusteredMarkerRenderer(insetMap, INSET_SOURCE_ID, {
			onLocationClick: swapMapView,
			onClusterClick: swapMapView
		});

		const layers = map.getStyle().layers;
		const labelLayerId = layers.find(
			layer => layer.type === 'symbol' && layer.layout['text-field']
		)?.id;

		map.addLayer(
			{
				'id': '3d-buildings',
				'source': 'composite',
				'source-layer': 'building',
				'filter': ['==', 'extrude', 'true'],
				'type': 'fill-extrusion',
				'minzoom': 15,
				'paint': {
					'fill-extrusion-color': '#aaa',
					'fill-extrusion-height': ['get', 'height'],
					'fill-extrusion-base': ['get', 'min_height'],
					'fill-extrusion-opacity': 0.6
				}
			},
			labelLayerId
		);
		filterLocations();
	});
});
</script>

<?php wp_footer(); ?>
</body>
</html>
