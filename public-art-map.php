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


/**
 * Register Custom Meta Fields for Map Locations
 */
add_action( 'init', 'pam_register_meta_fields' );

function pam_register_meta_fields() {
    register_post_meta( 'map_location', 'pam_artist', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );

    register_post_meta( 'map_location', 'pam_address', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );

    register_post_meta( 'map_location', 'pam_coordinates', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );
}


/**
 * Add Meta Box for Location Details
 */
add_action( 'add_meta_boxes', 'pam_add_location_meta_box' );

function pam_add_location_meta_box() {
    add_meta_box(
        'pam_location_details',
        'Location Details',
        'pam_render_location_meta_box',
        'map_location',
        'normal',
        'default'
    );
}

function pam_render_location_meta_box( $post ) {
    $artist      = get_post_meta( $post->ID, 'pam_artist', true );
    $address     = get_post_meta( $post->ID, 'pam_address', true );
    $coordinates = get_post_meta( $post->ID, 'pam_coordinates', true );

    ?>
    <p>
        <label for="pam_artist"><strong>Artist:</strong></label><br>
        <input type="text" name="pam_artist" id="pam_artist" value="<?php echo esc_attr( $artist ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_address"><strong>Address:</strong></label><br>
        <input type="text" name="pam_address" id="pam_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_coordinates"><strong>Coordinates (lat,lng):</strong></label><br>
        <input type="text" name="pam_coordinates" id="pam_coordinates" value="<?php echo esc_attr( $coordinates ); ?>" style="width:100%;" placeholder="e.g. 41.139,-104.820">
    </p>
    <?php
}


/**
 * Save Meta Fields
 */
add_action( 'save_post_map_location', 'pam_save_location_meta_fields' );

function pam_save_location_meta_fields( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

    if ( isset($_POST['pam_artist']) ) {
        update_post_meta( $post_id, 'pam_artist', sanitize_text_field($_POST['pam_artist']) );
    }
    if ( isset($_POST['pam_address']) ) {
        update_post_meta( $post_id, 'pam_address', sanitize_text_field($_POST['pam_address']) );
    }
    if ( isset($_POST['pam_coordinates']) ) {
        update_post_meta( $post_id, 'pam_coordinates', sanitize_text_field($_POST['pam_coordinates']) );
    }
}

