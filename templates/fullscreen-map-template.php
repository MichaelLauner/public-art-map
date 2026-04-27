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

		$collections = wp_get_post_terms( $location->ID, 'artwork_collection', array( 'fields' => 'slugs' ) );

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

		$location_data[] = array(
			'title'   => get_the_title( $location ),
			'lat'     => $lat,
			'lng'     => $lng,
			'types'   => wp_list_pluck( $types, 'slug' ),
			'collections'=> $collections,
			'color'   => $color,
			'icon'    => $icon_url,
			'thumb'   => $thumb,
			'url'     => get_permalink( $location ),
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

	function getPopupContent(loc) {
		return `
			<span>
				<p>
				<strong><a href="${loc.url}" style="text-decoration:none; color:inherit;">${loc.title}</a></strong><br />
				<a
					href="${loc.url}"
					style="text-decoration:none; color:inherit;" ><small>Information</small></a>&nbsp;|&nbsp;
				<a
					href="https://www.google.com/maps/dir/?api=1&destination=${loc.lat},${loc.lng}&travelmode=walking"
					target="_blank"
					rel="noopener noreferrer"
					style="text-decoration:none; color:inherit;" ><small>Directions</small></a>
				</p>
			</span>`;
	}

	function createMarker(loc, targetMap, popupOffset = [0, -25], shouldAttachPopup = true) {
		const baseColor = loc.color || '#fff';
		const imageUrl = loc.thumb || loc.icon;

		const markerEl = document.createElement('div');
		markerEl.className = 'pam-marker-wrapper';

		const markerContent = document.createElement('div');
		markerContent.className = 'pam-marker-content';
		if (imageUrl) {
			markerContent.style.backgroundImage = `url('${imageUrl}')`;
		}
		markerContent.style.backgroundColor = baseColor;
		markerContent.style.border = '2px solid ' + baseColor;

		markerEl.appendChild(markerContent);

		const marker = new mapboxgl.Marker(markerEl)
			.setLngLat([loc.lng, loc.lat])
			.addTo(targetMap);

		if (shouldAttachPopup) {
			marker.setPopup(new mapboxgl.Popup({ offset: popupOffset }).setHTML(getPopupContent(loc)));
		}

		return marker;
	}

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
		markers.forEach(marker => marker.remove());
		markers = [];
		insetMarkers.forEach(marker => marker.remove());
		insetMarkers = [];

		const splitLocations = splitInsetLocations(locations);
		const canShowInset = splitLocations.active && !insetDismissed;
		const mainLocations = splitLocations.active && activeMapView === 'distant'
			? splitLocations.distant
			: splitLocations.nearby;
		const insetLocations = splitLocations.active && activeMapView === 'distant'
			? splitLocations.nearby
			: splitLocations.distant;

		mainLocations.forEach(loc => {
			markers.push(createMarker(loc, map));
		});

		const isSmallScreen = window.innerWidth < 600;
		fitMapToLocations(map, mainLocations, isSmallScreen ? 100 : 200, activeMapView === 'distant' ? 10 : 13);

		if (canShowInset) {
			setInsetLabel(activeMapView);
			insetContainer.classList.add('is-visible');
			insetLocations.forEach(loc => {
				const marker = createMarker(loc, insetMap, [0, -20], false);
				marker.getElement().addEventListener('click', event => {
					event.stopPropagation();
					swapMapView();
				});
				insetMarkers.push(marker);
			});
			requestAnimationFrame(() => {
				insetMap.resize();
				fitMapToLocations(insetMap, insetLocations, 45, activeMapView === 'distant' ? 13 : 10);
			});
		} else {
			insetContainer.classList.remove('is-visible');
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

	filterLocations();

	map.on('load', () => {
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
	});
});
</script>

<?php wp_footer(); ?>
</body>
</html>
