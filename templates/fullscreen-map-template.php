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
		$color = '#4a7789';
		$icon_url = null;

		if ( $first_type ) {
			$term_color = get_term_meta( $first_type->term_id, 'pam_color', true );
			if ( $term_color ) {
				$color = $term_color;
			}
			$term_icon_id = get_term_meta( $first_type->term_id, 'pam_icon', true );
			var_dump( $term_icon_id );
			$icon_url = '';
			if ( $term_icon_id ) {
				$icon_info = wp_get_attachment_image_src( $term_icon_id, 'thumbnail' );
				if ( $icon_info ) {
					$icon_url = esc_url_raw( $icon_info[0] );
				}
			}
		}

		$location_data[] = array(
			'title' => get_the_title( $location ),
			'lat'   => $lat,
			'lng'   => $lng,
			'types' => wp_list_pluck( $types, 'slug' ),
			'color' => $color,
			'icon'  => $icon_url,
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
	.mapboxgl-marker img {

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
			const baseColor = loc.color || '#4a7789';

			const svg = `
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.5 164.1" width="30" height="43">
					<defs>
						<filter id="drop-shadow-1" x="-9.6" y="-3.1" width="139" height="179" filterUnits="userSpaceOnUse">
							<feOffset dx="0" dy="4"/>
							<feGaussianBlur result="blur" stdDeviation="2"/>
							<feFlood flood-color="#000" flood-opacity=".8"/>
							<feComposite in2="blur" operator="in"/>
							<feComposite in="SourceGraphic"/>
						</filter>
					</defs>
					<path fill="${baseColor}" stroke="#fff" stroke-width="4" stroke-miterlimit="10"
						filter="url(#drop-shadow-1)"
						d="M60,8.4s0,0-.1,0C30.2,8.4,6.1,32.4,6.1,62.2s15.1,43.5,36.1,50.8c8.3,24,17.9,43.2,17.9,43.2,0,0,9.2-19.1,17.4-43.1,21.1-7.3,36.2-27.3,36.2-50.8S89.7,8.4,60,8.4Z"/>
				</svg>
			`;

			const markerEl = document.createElement('div');
			markerEl.innerHTML = svg;
			markerEl.style.transform = 'translate(-50%, -100%)'; // center base of marker

			// Optional icon overlay
			if (loc.icon) {
				const iconImg = document.createElement('img');
				iconImg.src = loc.icon;
				iconImg.style.position = 'absolute';
				iconImg.style.left = '50%';
				iconImg.style.top = '34%';
				iconImg.style.width = '20px';
				iconImg.style.height = '20px';
				iconImg.style.transform = 'translate(-50%, -50%)';
				iconImg.style.pointerEvents = 'none';
				iconImg.style.zIndex = 1; // Ensure icon is above the SVG
				iconImg.style.borderRadius = '50%';
				iconImg.style.overflow = 'hidden';
				markerEl.appendChild(iconImg);
			}

			const marker = new mapboxgl.Marker(markerEl)
				.setLngLat([loc.lng, loc.lat])
				.setPopup(new mapboxgl.Popup().setText(loc.title))
				.addTo(map);

			markers.push(marker);
			bounds.extend([loc.lng, loc.lat]);
		});

		const isSmallScreen = window.innerWidth < 600;
		map.fitBounds(bounds, { padding: isSmallScreen ? 100 : 200 });
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

<?php wp_footer(); ?>
</body>
</html>