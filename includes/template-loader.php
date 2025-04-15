<?php
add_filter( 'template_include', 'pam_load_custom_map_template' );

function pam_load_custom_map_template( $template ) {
    if ( is_admin() ) return $template;

    $map_page_id = get_option( 'pam_map_page' );
    if ( is_page( $map_page_id ) ) {
        return plugin_dir_path( __FILE__ ) . '../templates/fullscreen-map-template.php';
    }

    return $template;
}
