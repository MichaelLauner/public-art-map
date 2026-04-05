<?php
add_action( 'init', 'pam_register_map_location_cpt' );

function pam_register_map_location_cpt() {
    $labels = array(
        'name'               => __( 'Map Locations', PAM_TEXT_DOMAIN ),
        'singular_name'      => __( 'Map Location', PAM_TEXT_DOMAIN ),
        'menu_name'          => __( 'Map Locations', PAM_TEXT_DOMAIN ),
        'name_admin_bar'     => __( 'Map Location', PAM_TEXT_DOMAIN ),
        'add_new'            => __( 'Add New', PAM_TEXT_DOMAIN ),
        'add_new_item'       => __( 'Add New Location', PAM_TEXT_DOMAIN ),
        'new_item'           => __( 'New Location', PAM_TEXT_DOMAIN ),
        'edit_item'          => __( 'Edit Location', PAM_TEXT_DOMAIN ),
        'view_item'          => __( 'View Location', PAM_TEXT_DOMAIN ),
        'all_items'          => __( 'All Locations', PAM_TEXT_DOMAIN ),
        'search_items'       => __( 'Search Locations', PAM_TEXT_DOMAIN ),
        'parent_item_colon'  => __( 'Parent Locations:', PAM_TEXT_DOMAIN ),
        'not_found'          => __( 'No locations found.', PAM_TEXT_DOMAIN ),
        'not_found_in_trash' => __( 'No locations found in Trash.', PAM_TEXT_DOMAIN ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'menu_icon'          => 'dashicons-location-alt',
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'map-locations' ),
        'show_in_rest'       => true, // Enables Gutenberg + REST API
    );

    register_post_type( 'map_location', $args );
}
