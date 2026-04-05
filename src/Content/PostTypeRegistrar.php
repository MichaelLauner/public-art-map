<?php
namespace PublicArtMap\Content;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class PostTypeRegistrar implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'registerPostType' ) );
	}

	public function registerPostType(): void {
		$text_domain = $this->context->textDomain();

		$labels = array(
			'name'               => __( 'Map Locations', $text_domain ),
			'singular_name'      => __( 'Map Location', $text_domain ),
			'menu_name'          => __( 'Map Locations', $text_domain ),
			'name_admin_bar'     => __( 'Map Location', $text_domain ),
			'add_new'            => __( 'Add New', $text_domain ),
			'add_new_item'       => __( 'Add New Location', $text_domain ),
			'new_item'           => __( 'New Location', $text_domain ),
			'edit_item'          => __( 'Edit Location', $text_domain ),
			'view_item'          => __( 'View Location', $text_domain ),
			'all_items'          => __( 'All Locations', $text_domain ),
			'search_items'       => __( 'Search Locations', $text_domain ),
			'parent_item_colon'  => __( 'Parent Locations:', $text_domain ),
			'not_found'          => __( 'No locations found.', $text_domain ),
			'not_found_in_trash' => __( 'No locations found in Trash.', $text_domain ),
		);

		register_post_type(
			'map_location',
			array(
				'labels'       => $labels,
				'public'       => true,
				'menu_icon'    => 'dashicons-location-alt',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'map-locations' ),
				'show_in_rest' => true,
			)
		);
	}
}
