<?php
add_action( 'wp_enqueue_scripts', 'pam_enqueue_map_assets' );

function pam_enqueue_map_assets() {
    $map_page_id = get_option( 'pam_map_page' );

    // Only enqueue on the selected map page
    if ( is_page( $map_page_id ) ) {
        wp_enqueue_style( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' );
        wp_enqueue_script( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), null, true );
    }
}
