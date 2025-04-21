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
			'color'   => $color,
			'icon'    => $icon_url,
			'thumb'   => $thumb,
			'url'     => get_permalink( $location ),
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
	.pam-marker-wrapper {
		width: 30px;
		height: 30px;
		transform: translate(-50%, -100%);
	}
	.pam-marker-content {
		width: 100%;
		height: 100%;
		background-size: cover;
		background-position: center;
		border-radius: 50%;
		box-shadow: 0 2px 6px rgba(0,0,0,0.3);
		border: 2px solid white;
		transition: transform 0.2s ease;
	}
	.pam-marker-content:hover {
		transform: scale(5);
		cursor: pointer;
	}
	.mapboxgl-popup-content {
		padding:0;
		width: 220px;
		height: auto;
		padding:5px;
	}
	.mapboxgl-popup-content img {
		width: 100%;
		height: auto;
		display: block;
	}
	.mapboxgl-popup-content p {
		margin: 0;
		text-align: center;
		font-size:18px;
		text-align:left;
	}
	.mapboxgl-popup-content p small {
		font-size:14px;
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
			
			// const popupContent = `<a href="${loc.url}" style="text-decoration:none; color:inherit;"><p><strong>${loc.title}</strong></a>`;
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