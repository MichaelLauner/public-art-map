<?php
add_action( 'init', 'pam_register_map_location_cpt' );

function pam_register_map_location_cpt() {
    $labels = array(
        'name'               => 'Map Locations',
        'singular_name'      => 'Map Location',
        'menu_name'          => 'Map Locations',
        'name_admin_bar'     => 'Map Location',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Location',
        'new_item'           => 'New Location',
        'edit_item'          => 'Edit Location',
        'view_item'          => 'View Location',
        'all_items'          => 'All Locations',
        'search_items'       => 'Search Locations',
        'parent_item_colon'  => 'Parent Locations:',
        'not_found'          => 'No locations found.',
        'not_found_in_trash' => 'No locations found in Trash.',
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
