<?php
/**
 * Template for Fullscreen Public Art Map
 */
get_header();
$mapbox_key = get_option( 'pam_mapbox_api_key' );

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
		$types = wp_get_post_terms( $location->ID, 'artwork_type', array( 'fields' => 'slugs' ) );

		$location_data[] = array(
			'title' => get_the_title( $location ),
			'lat'   => $lat,
			'lng'   => $lng,
			'types' => $types,
		);
	}
}
?>

<style>
	html, body, #pam-map {
		margin: 0;
		padding: 0;
		height: 100vh;
		width: 100%;
		position: absolute;
		top: 0;
		left: 0;
	}
	#pam-map {
		position: relative;
		z-index: 10;
	}
	#pam-filter {
		position: absolute;
		bottom: 1rem;
		left: 1rem;
		background: white;
		padding: 1rem;
		z-index: 999;
		border-radius: 0.5rem;
		box-shadow: 0 0 10px rgba(0,0,0,0.2);
		font-family: sans-serif;
		max-height: 60vh;
		overflow-y: auto;
	}
</style>

<div id="pam-filter">
	<strong>Filter by Type</strong><br>
	<div id="pam-filter-options"></div>
</div>

<div id="pam-map"></div>

<script>
const pamLocations = <?php echo wp_json_encode( $location_data ); ?>;
const pamTypes = <?php echo wp_json_encode( $term_map ); ?>;

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
			const marker = new mapboxgl.Marker()
				.setLngLat([loc.lng, loc.lat])
				.setPopup(new mapboxgl.Popup().setText(loc.title))
				.addTo(map);
			markers.push(marker);
			bounds.extend([loc.lng, loc.lat]);
		});

		if (locations.length === 1) {
			map.setCenter(bounds.getCenter());
			map.setZoom(15);
		} else if (locations.length > 1) {
			map.fitBounds(bounds, {
				padding: 60,
				maxZoom: 16,
				duration: 1000
			});
		}
	}

	function getSelectedTypes() {
		return Array.from(document.querySelectorAll('#pam-filter-options input:checked'))
			.map(cb => cb.value);
	}

	function filterLocations() {
		const selected = getSelectedTypes();
		if (selected.length === 0) {
			renderMarkers(pamLocations);
			return;
		}

		const filtered = pamLocations.filter(loc =>
			loc.types.some(type => selected.includes(type))
		);
		renderMarkers(filtered);
	}

	// Render filter checkboxes
	pamTypes.forEach(term => {
		const wrapper = document.createElement('div');

		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.value = term.slug;
		checkbox.addEventListener('change', filterLocations);

		const label = document.createElement('label');
		label.appendChild(checkbox);
		label.append(` ${term.name}`);

		wrapper.appendChild(label);
		document.getElementById('pam-filter-options').appendChild(wrapper);
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

<?php get_footer(); ?>
