<?php
/**
 * Plugin Name: Public Art Map
 * Description: Adds a custom post type for mapping public art locations.
 * Version: 0.1.0
 * Author: Your Name
 * License: GPL2+
 */

defined( 'ABSPATH' ) || exit;


/**
 * Custom Post Type for Map Locations
 */
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


/**
 * Register Custom Taxonomy for Artwork Types
 */
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
