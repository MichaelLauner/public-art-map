<?php
/**
 * Plugin Name: Public Art Map
 * Description: Adds a custom post type for mapping public art locations.
 * Version: 0.1.0
 * Author: Michael Launer
 * License: GPL2+
 */

defined( 'ABSPATH' ) || exit;

// Post Type and Taxonomy Registration
require_once plugin_dir_path(__FILE__) . 'includes/cpt-map-location.php';
require_once plugin_dir_path(__FILE__) . 'includes/taxonomy-artwork-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/taxonomy-artwork-collection.php';

require_once plugin_dir_path(__FILE__) . 'includes/assets.php';
require_once plugin_dir_path(__FILE__) . 'includes/meta-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/template-loader.php';

// Template
require_once plugin_dir_path(__FILE__) . 'templates/single-project-display.php';

// Tools
require_once plugin_dir_path(__FILE__) . 'includes/tools.php';

add_action( 'rest_api_init', function() {
    register_rest_field(
        'map_location',            // CPT slug
        'pam_coordinates',         // the new REST field
        [
            'get_callback'    => function( $object ) {
                // Return the stored meta value
                return get_post_meta( $object['id'], 'pam_coordinates', true );
            },
            'update_callback' => function( $value, $object ) {
                // Allow updating via the REST API if needed
                update_post_meta(
                    $object->ID,
                    'pam_coordinates',
                    sanitize_text_field( $value )
                );
            },
            'schema'          => [
                'type'        => 'string',
                'description' => 'Latitude and longitude as "lat,lng"',
                'context'     => [ 'view', 'edit' ],
            ],
        ]
    );
});
