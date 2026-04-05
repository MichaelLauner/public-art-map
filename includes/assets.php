<?php
add_action( 'wp_enqueue_scripts', 'pam_enqueue_map_assets' );

/**
 * Return the URL to a bundled Mapbox GL asset.
 *
 * @param string $asset Relative asset path.
 * @return string
 */
function pam_get_mapbox_asset_url( $asset ) {
	return PAM_PLUGIN_URL . 'vendor/mapbox-gl/dist/' . ltrim( $asset, '/' );
}

function pam_enqueue_map_assets() {
    $map_page_id = get_option( 'pam_map_page' );

    // Only enqueue on the selected map page
    if ( is_page( $map_page_id ) ) {
        wp_enqueue_style( 'mapbox-gl', pam_get_mapbox_asset_url( 'mapbox-gl.css' ), array(), '2.15.0' );
        wp_enqueue_script( 'mapbox-gl', pam_get_mapbox_asset_url( 'mapbox-gl.js' ), array(), '2.15.0', true );
    }
}
