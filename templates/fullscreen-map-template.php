<?php
/**
 * Template for Fullscreen Public Art Map
 */

 get_header();
 $mapbox_key = get_option( 'pam_mapbox_api_key' );
 ?>
 
 <style>
	 html, body, #pam-map {
		 margin: 0;
		 padding: 0;
		 height: 80vh;
		 width: 100%;
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
document.addEventListener('DOMContentLoaded', function () {
    mapboxgl.accessToken = '<?php echo esc_js( $mapbox_key ); ?>';

    const map = new mapboxgl.Map({
        container: 'pam-map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [-104.820, 41.139],
        zoom: 16,
        pitch: 45,
        bearing: 0,
        antialias: true
    });

    map.addControl(new mapboxgl.NavigationControl());

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
 