<?php
namespace PublicArtMap\Rest;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestController implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerExportRoute' ) );
		add_action( 'rest_api_init', array( $this, 'registerCoordinatesField' ) );
	}

	public function registerCoordinatesField(): void {
		register_rest_field(
			'map_location',
			'pam_coordinates',
			array(
				'get_callback'    => array( $this, 'getCoordinatesField' ),
				'update_callback' => array( $this, 'updateCoordinatesField' ),
				'schema'          => array(
					'type'        => 'string',
					'description' => __( 'Latitude and longitude as "lat,lng"', $this->context->textDomain() ),
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	public function getCoordinatesField( array $object ) {
		return get_post_meta( $object['id'], 'pam_coordinates', true );
	}

	public function updateCoordinatesField( $value, $object ): void {
		update_post_meta( $object->ID, 'pam_coordinates', sanitize_text_field( $value ) );
	}

	public function registerExportRoute(): void {
		register_rest_route(
			'public-art-map/v1',
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleExportRequest' ),
				'permission_callback' => array( $this, 'canAccessExportFeed' ),
				'args'                => array(
					'include_images' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'include_terms'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'token'          => array(
						'type'        => 'string',
						'required'    => false,
						'description' => __( 'Optional shared secret configured in plugin settings.', $this->context->textDomain() ),
					),
				),
			)
		);
	}

	public function canAccessExportFeed( WP_REST_Request $request ) {
		if ( ! get_option( 'pam_export_enabled', false ) ) {
			return new WP_Error(
				'pam_export_disabled',
				__( 'Public Art Map data sharing is disabled on this site.', $this->context->textDomain() ),
				array( 'status' => 403 )
			);
		}

		$configured_token = trim( (string) get_option( 'pam_export_token', '' ) );

		if ( '' === $configured_token ) {
			return true;
		}

		$provided_token = (string) $request->get_param( 'token' );
		if ( '' === $provided_token ) {
			$provided_token = (string) $request->get_header( 'X-PAM-Token' );
		}

		if ( '' === $provided_token || ! hash_equals( $configured_token, $provided_token ) ) {
			return new WP_Error(
				'pam_export_forbidden',
				__( 'A valid Public Art Map export token is required.', $this->context->textDomain() ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	public function handleExportRequest( WP_REST_Request $request ): WP_REST_Response {
		$include_images      = rest_sanitize_boolean( $request->get_param( 'include_images' ) );
		$include_terms       = rest_sanitize_boolean( $request->get_param( 'include_terms' ) );
		$artwork_types       = $include_terms ? $this->getExportTerms( 'artwork_type', $include_images ) : array();
		$artwork_collections = $include_terms ? $this->getExportTerms( 'artwork_collection', $include_images ) : array();
		$locations           = $this->getExportLocations( $include_images );

		return rest_ensure_response(
			array(
				'schema_version' => 1,
				'generated_at'   => gmdate( 'c' ),
				'source_site'    => array(
					'name'           => get_bloginfo( 'name' ),
					'home_url'       => home_url( '/' ),
					'export_url'     => rest_url( 'public-art-map/v1/export' ),
					'plugin_version' => $this->context->version(),
				),
				'counts'         => array(
					'locations'           => count( $locations ),
					'artwork_types'       => count( $artwork_types ),
					'artwork_collections' => count( $artwork_collections ),
				),
				'terms'          => array(
					'artwork_type'       => $artwork_types,
					'artwork_collection' => $artwork_collections,
				),
				'locations'      => $locations,
			)
		);
	}

	private function getExportLocations( bool $include_images ): array {
		$locations = get_posts(
			array(
				'post_type'      => 'map_location',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$payload = array();

		foreach ( $locations as $location ) {
			$featured_image_id = get_post_thumbnail_id( $location->ID );
			$gallery_image_ids = get_post_meta( $location->ID, 'pam_images', true );
			$gallery_image_ids = is_array( $gallery_image_ids ) ? array_values( array_map( 'absint', $gallery_image_ids ) ) : array();

			$payload[] = array(
				'source_id'    => (int) $location->ID,
				'slug'         => $location->post_name,
				'status'       => $location->post_status,
				'title'        => get_the_title( $location ),
				'content'      => $location->post_content,
				'excerpt'      => $location->post_excerpt,
				'permalink'    => get_permalink( $location ),
				'modified_gmt' => get_post_modified_time( 'c', true, $location ),
				'meta'         => array(
					'artist'       => get_post_meta( $location->ID, 'pam_artist', true ),
					'description'  => get_post_meta( $location->ID, 'pam_description', true ),
					'address'      => get_post_meta( $location->ID, 'pam_address', true ),
					'city'         => get_post_meta( $location->ID, 'pam_city', true ),
					'state'        => get_post_meta( $location->ID, 'pam_state', true ),
					'zip'          => get_post_meta( $location->ID, 'pam_zip', true ),
					'coordinates'  => get_post_meta( $location->ID, 'pam_coordinates', true ),
					'auto_geocode' => (bool) get_post_meta( $location->ID, 'pam_auto_geocode', true ),
				),
				'taxonomies'   => array(
					'artwork_type'       => $this->getExportPostTermRefs( $location->ID, 'artwork_type' ),
					'artwork_collection' => $this->getExportPostTermRefs( $location->ID, 'artwork_collection' ),
				),
				'images'       => $include_images ? array(
					'featured' => $featured_image_id ? $this->getExportAttachmentData( $featured_image_id ) : null,
					'gallery'  => array_values( array_filter( array_map( array( $this, 'getExportAttachmentData' ), $gallery_image_ids ) ) ),
				) : array(
					'featured' => null,
					'gallery'  => array(),
				),
			);
		}

		return $payload;
	}

	private function getExportTerms( string $taxonomy, bool $include_images ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$payload = array();

		foreach ( $terms as $term ) {
			$icon_id = 'artwork_type' === $taxonomy ? (int) get_term_meta( $term->term_id, 'pam_icon', true ) : 0;

			$payload[] = array(
				'source_id'   => (int) $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent_slug' => $term->parent ? $this->getTermSlug( $term->parent, $taxonomy ) : '',
				'meta'        => array(
					'color' => get_term_meta( $term->term_id, 'pam_color', true ),
					'icon'  => ( $include_images && $icon_id ) ? $this->getExportAttachmentData( $icon_id ) : null,
				),
			);
		}

		return $payload;
	}

	private function getExportPostTermRefs( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$payload = array();

		foreach ( $terms as $term ) {
			$payload[] = array(
				'source_id' => (int) $term->term_id,
				'slug'      => $term->slug,
				'name'      => $term->name,
			);
		}

		return $payload;
	}

	private function getExportAttachmentData( int $attachment_id ): ?array {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return null;
		}

		return array(
			'source_id'   => (int) $attachment_id,
			'url'         => $url,
			'mime_type'   => get_post_mime_type( $attachment_id ),
			'title'       => get_the_title( $attachment_id ),
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'     => wp_get_attachment_caption( $attachment_id ),
			'description' => $attachment->post_content,
		);
	}

	private function getTermSlug( int $term_id, string $taxonomy ): string {
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		return $term->slug;
	}
}
