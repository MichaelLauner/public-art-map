<?php
/**
 * Template for Fullscreen Public Art Map
 */

 get_header();
 $mapbox_key = get_option( 'pam_mapbox_api_key' );


 // Build the map data
 $locations = get_posts(array(
	 'post_type'      => 'map_location',
	 'posts_per_page' => -1,
	 'post_status'    => 'publish',
 ));
 
 $location_data = array();
 
 foreach ( $locations as $location ) {
	 $coords = get_post_meta( $location->ID, 'pam_coordinates', true );
	 if ( ! empty( $coords ) && strpos( $coords, ',' ) !== false ) {
		 list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coords ) );
		 $location_data[] = array(
			 'title' => get_the_title( $location ),
			 'lat'   => $lat,
			 'lng'   => $lng,
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
		 z-index: 1;
	 }
 </style>
 
 <div id="pam-map">
	//* Mapbox map will be rendered here */
 </div>
 
<script>
const pamLocations = <?php echo wp_json_encode( $location_data ); ?>;

document.addEventListener('DOMContentLoaded', function () {
	mapboxgl.accessToken = '<?php echo esc_js( $mapbox_key ); ?>';

	const map = new mapboxgl.Map({
		container: 'pam-map',
		style: 'mapbox://styles/mapbox/streets-v11',
		pitch: 45,
		bearing: 0,
		antialias: true
	});

	const bounds = new mapboxgl.LngLatBounds();

	map.addControl(new mapboxgl.NavigationControl());

	pamLocations.forEach(loc => {
		const marker = new mapboxgl.Marker()
			.setLngLat([loc.lng, loc.lat])
			.setPopup(new mapboxgl.Popup().setText(loc.title))
			.addTo(map);

		bounds.extend([loc.lng, loc.lat]);
	});

	if (pamLocations.length === 1) {
		// Zoom in closer for single point
		map.setCenter(bounds.getCenter());
		map.setZoom(15);
	} else if (pamLocations.length > 1) {
		map.fitBounds(bounds, {
			padding: 60,
			maxZoom: 16,
			duration: 1000
		});
	}

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
 