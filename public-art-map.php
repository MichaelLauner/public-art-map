<?php
/**
 * Plugin Name: Public Art Map
 * Plugin URI:  https://github.com/MichaelLauner/public-art-map
 * Description: Adds a custom post type for mapping public art locations.
 * Version:     0.1.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      Michael Launer
 * License:     GPL2+
 * Text Domain: public-art-map
 * Domain Path: /languages
 * Update URI:  https://github.com/MichaelLauner/public-art-map
 */

defined( 'ABSPATH' ) || exit;

define( 'PAM_VERSION', '0.1.1' );
define( 'PAM_TEXT_DOMAIN', 'public-art-map' );
define( 'PAM_PLUGIN_FILE', __FILE__ );
define( 'PAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'pam_load_textdomain' );

/**
 * Load the plugin translations.
 */
function pam_load_textdomain() {
	load_plugin_textdomain(
		PAM_TEXT_DOMAIN,
		false,
		dirname( plugin_basename( PAM_PLUGIN_FILE ) ) . '/languages'
	);
}

// Post Type and Taxonomy Registration
require_once PAM_PLUGIN_DIR . 'includes/plugin-updates.php';
require_once PAM_PLUGIN_DIR . 'includes/rest-export.php';
require_once PAM_PLUGIN_DIR . 'includes/cpt-map-location.php';
require_once PAM_PLUGIN_DIR . 'includes/taxonomy-artwork-type.php';
require_once PAM_PLUGIN_DIR . 'includes/taxonomy-artwork-collection.php';

require_once PAM_PLUGIN_DIR . 'includes/assets.php';
require_once PAM_PLUGIN_DIR . 'includes/meta-fields.php';
require_once PAM_PLUGIN_DIR . 'includes/settings-page.php';
require_once PAM_PLUGIN_DIR . 'includes/template-loader.php';

// Template
require_once PAM_PLUGIN_DIR . 'templates/single-project-display.php';

// Tools
require_once PAM_PLUGIN_DIR . 'includes/tools.php';

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
                'description' => __( 'Latitude and longitude as "lat,lng"', PAM_TEXT_DOMAIN ),
                'context'     => [ 'view', 'edit' ],
            ],
        ]
    );
});
