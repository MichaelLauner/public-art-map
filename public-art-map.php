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

require_once PAM_PLUGIN_DIR . 'src/Infrastructure/Autoloader.php';

PublicArtMap\Infrastructure\Autoloader::register( 'PublicArtMap', PAM_PLUGIN_DIR . 'src' );

require_once PAM_PLUGIN_DIR . 'includes/compat.php';

PublicArtMap\Plugin::boot(
	new PublicArtMap\Infrastructure\PluginContext(
		PAM_PLUGIN_FILE,
		PAM_VERSION,
		PAM_TEXT_DOMAIN
	)
);
