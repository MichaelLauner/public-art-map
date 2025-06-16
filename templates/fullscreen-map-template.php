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

<!-- Filter Options -->
<div id="pam-filter">
	<strong>Filter by Type</strong><br>
	<div id="pam-filter-options-desktop"></div>
	<strong style="margin-top:1em;display:block;">Filter by Collection</strong>
	<div id="pam-filter-collections-desktop"></div>
</div>

<!-- Filter Toggle Button (only visible on mobile) -->
<button id="pam-filter-toggle" aria-controls="pam-filter-drawer" aria-expanded="false">
	☰ Filter
</button>

<!-- Site Logo -->
<?php if ( $logo_url ) : ?>
	<a href="<?php echo esc_url( home_url() ); ?>" id="pam-site-logo">
		<img src="<?php echo $logo_url; ?>" alt="Return to Site" />
	</a>
<?php endif; ?>

<!-- Slide-In Drawer for Mobile -->
<div id="pam-filter-drawer">
	<strong>Filter by Type</strong><br>
	<div id="pam-filter-options-mobile"></div>
	<strong style="margin-top:1em;display:block;">Filter by Collection</strong>
	<div id="pam-filter-collections-mobile"></div>
	<button id="pam-filter-close">Close</button>
</div>

<div id="pam-map"></div>

<script>
const pamLocations = <?php echo wp_json_encode( $location_data ); ?>;
const pamTypes = <?php echo wp_json_encode( $term_map ); ?>;
const pamCollections = <?php echo wp_json_encode( $collection_map ); ?>;

document.addEventListener('DOMContentLoaded', function () {
	const toggleBtn = document.getElementById('pam-filter-toggle');
	const drawer = document.getElementById('pam-filter-drawer');
	const closeBtn = document.getElementById('pam-filter-close');

	if (toggleBtn && drawer && closeBtn) {
		toggleBtn.addEventListener('click', () => {
			drawer.classList.add('is-visible');
			toggleBtn.setAttribute('aria-expanded', 'true');
		});

		closeBtn.addEventListener('click', () => {
			drawer.classList.remove('is-visible');
			toggleBtn.setAttribute('aria-expanded', 'false');
		});
	}
});

document.addEventListener('DOMContentLoaded', function () {
	mapboxgl.accessToken = '<?php echo esc_js( $mapbox_key ); ?>';

	const map = new mapboxgl.Map({
		container: 'pam-map',
		style: 'mapbox://styles/mapbox/streets-v11',
		pitch: 45,
		bearing: 0,
		antialias: true
	});

	map.addControl(new mapboxgl.NavigationControl());

	const bounds = new mapboxgl.LngLatBounds();
	let markers = [];

	function renderMarkers(locations) {
		markers.forEach(marker => marker.remove());
		markers = [];

		locations.forEach(loc => {
			const baseColor = loc.color || '#fff';

			const imageUrl = loc.thumb || loc.icon;

			const markerEl = document.createElement('div');
			markerEl.className = 'pam-marker-wrapper';

			const markerContent = document.createElement('div');
			markerContent.className = 'pam-marker-content';
			markerContent.style.backgroundImage = `url('${imageUrl}')`;
			markerContent.style.backgroundColor = baseColor;
			markerContent.style.border = '2px solid ' + baseColor;

			markerEl.appendChild(markerContent);
			
			const popupContent = `
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

			const marker = new mapboxgl.Marker(markerEl)
				.setLngLat([loc.lng, loc.lat])
				.setPopup(new mapboxgl.Popup({ offset: [0, -25] }).setHTML(popupContent))
				.addTo(map);

			markers.push(marker);
			bounds.extend([loc.lng, loc.lat]);
		});

		const isSmallScreen = window.innerWidth < 600;
		map.fitBounds(bounds, { padding: isSmallScreen ? 100 : 200 });
	}

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

		renderMarkers(filtered);
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

	// Initial render
	renderMarkers(pamLocations);

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