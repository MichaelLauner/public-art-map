<?php
add_action( 'init', 'pam_register_artwork_type_taxonomy' );

function pam_register_artwork_type_taxonomy() {
    $labels = array(
        'name'              => 'Artwork Types',
        'singular_name'     => 'Artwork Type',
        'search_items'      => 'Search Artwork Types',
        'all_items'         => 'All Artwork Types',
        'parent_item'       => 'Parent Artwork Type',
        'parent_item_colon' => 'Parent Artwork Type:',
        'edit_item'         => 'Edit Artwork Type',
        'update_item'       => 'Update Artwork Type',
        'add_new_item'      => 'Add New Artwork Type',
        'new_item_name'     => 'New Artwork Type Name',
        'menu_name'         => 'Artwork Types',
    );

    $args = array(
        'hierarchical'      => true, // Like categories (set false for tag-like)
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'artwork-type' ),
        'show_in_rest'      => true, // enables block editor and REST support
    );

    register_taxonomy( 'artwork_type', array( 'map_location' ), $args );
}