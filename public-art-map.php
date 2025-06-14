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

// Tools
require_once plugin_dir_path(__FILE__) . 'includes/tools.php';