<?php
namespace PublicArtMap\Assets;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class AssetManager implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueFrontendAssets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueGalleryModalAssets' ) );
	}

	public function getMapboxAssetUrl( string $asset ): string {
		return $this->context->mapboxAssetUrl( $asset );
	}

	public function enqueueAdminAssets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'map_location' !== get_post_type() ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script( 'mapbox-gl', $this->getMapboxAssetUrl( 'mapbox-gl.js' ), array(), '2.15.0', true );
		wp_enqueue_style( 'mapbox-gl', $this->getMapboxAssetUrl( 'mapbox-gl.css' ), array(), '2.15.0' );

		wp_enqueue_script( 'pam-gallery-js', $this->context->assetUrl( 'js/pam-gallery.js' ), array( 'jquery' ), '1.0', true );
		wp_enqueue_style( 'pam-gallery-css-admin', $this->context->assetUrl( 'css/pam-gallery-admin.css' ), array(), '1.0' );
		wp_localize_script(
			'pam-gallery-js',
			'pamGalleryL10n',
			array(
				'selectImages' => __( 'Select Images', $this->context->textDomain() ),
				'addToGallery' => __( 'Add to gallery', $this->context->textDomain() ),
				'removeImage'  => __( 'Remove image', $this->context->textDomain() ),
			)
		);

		wp_enqueue_script(
			'pam-admin-map',
			$this->context->assetUrl( 'js/pam-admin-map.js' ),
			array( 'mapbox-gl', 'jquery' ),
			'1.0',
			true
		);

		$coords = get_post_meta( get_the_ID(), 'pam_coordinates', true );
		list( $lat, $lng ) = array_map(
			'floatval',
			array_pad( explode( ',', (string) $coords ), 2, '' )
		);

		if ( ! $lat && ! $lng ) {
			$lat = 41.139;
			$lng = -104.820;
		}

		wp_localize_script(
			'pam-admin-map',
			'pamAdmin',
			array(
				'mapboxKey'         => get_option( 'pam_mapbox_api_key' ),
				'lat'               => $lat,
				'lng'               => $lng,
				'selectCoordinates' => __( 'Select coordinates on the map', $this->context->textDomain() ),
			)
		);

		wp_enqueue_script(
			'pam-editor-refresh-map',
			$this->context->assetUrl( 'js/pam-editor-refresh-map.js' ),
			array( 'wp-data' ),
			'1.0',
			true
		);
	}

	public function enqueueFrontendAssets(): void {
		wp_enqueue_style( 'pam-gallery-css', $this->context->assetUrl( 'css/pam-gallery.css' ), array(), '1.0' );

		$map_page_id = get_option( 'pam_map_page' );
		if ( is_page( $map_page_id ) ) {
			wp_enqueue_style( 'mapbox-gl', $this->getMapboxAssetUrl( 'mapbox-gl.css' ), array(), '2.15.0' );
			wp_enqueue_script( 'mapbox-gl', $this->getMapboxAssetUrl( 'mapbox-gl.js' ), array(), '2.15.0', true );
		}
	}

	public function enqueueGalleryModalAssets(): void {
		if ( ! is_singular( 'map_location' ) ) {
			return;
		}

		wp_enqueue_script( 'pam-gallery-modal', $this->context->assetUrl( 'js/pam-gallery-modal.js' ), array( 'jquery' ), '1.0', true );
		wp_localize_script(
			'pam-gallery-modal',
			'pamGallery',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'pam_gallery_nonce' ),
				'closeModal' => __( 'Close image gallery', $this->context->textDomain() ),
				'previous'   => __( 'Previous image', $this->context->textDomain() ),
				'next'       => __( 'Next image', $this->context->textDomain() ),
			)
		);
	}
}
