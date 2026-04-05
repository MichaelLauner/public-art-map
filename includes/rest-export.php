<?php
/**
 * Export map data for reuse on another site.
 *
 * @package Public_Art_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'pam_register_export_rest_routes' );

/**
 * Register the export route.
 */
function pam_register_export_rest_routes() {
	register_rest_route(
		'public-art-map/v1',
		'/export',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'pam_handle_export_request',
			'permission_callback' => 'pam_can_access_export_feed',
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
					'description' => __( 'Optional shared secret configured in plugin settings.', PAM_TEXT_DOMAIN ),
				),
			),
		)
	);
}

/**
 * Ensure data sharing is enabled and the request is authorized.
 *
 * @param WP_REST_Request $request REST request.
 * @return true|WP_Error
 */
function pam_can_access_export_feed( WP_REST_Request $request ) {
	if ( ! get_option( 'pam_export_enabled', false ) ) {
		return new WP_Error(
			'pam_export_disabled',
			__( 'Public Art Map data sharing is disabled on this site.', PAM_TEXT_DOMAIN ),
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
			__( 'A valid Public Art Map export token is required.', PAM_TEXT_DOMAIN ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Build the export payload.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function pam_handle_export_request( WP_REST_Request $request ) {
	$include_images = rest_sanitize_boolean( $request->get_param( 'include_images' ) );
	$include_terms  = rest_sanitize_boolean( $request->get_param( 'include_terms' ) );

	$artwork_types       = $include_terms ? pam_get_export_terms( 'artwork_type', $include_images ) : array();
	$artwork_collections = $include_terms ? pam_get_export_terms( 'artwork_collection', $include_images ) : array();
	$locations           = pam_get_export_locations( $include_images );

	$response = array(
		'schema_version' => 1,
		'generated_at'   => gmdate( 'c' ),
		'source_site'    => array(
			'name'           => get_bloginfo( 'name' ),
			'home_url'       => home_url( '/' ),
			'export_url'     => rest_url( 'public-art-map/v1/export' ),
			'plugin_version' => defined( 'PAM_VERSION' ) ? PAM_VERSION : null,
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
	);

	return rest_ensure_response( $response );
}

/**
 * Export all publishable map locations.
 *
 * @param bool $include_images Whether to include image data.
 * @return array<int, array<string, mixed>>
 */
function pam_get_export_locations( $include_images ) {
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
				'artwork_type'       => pam_get_export_post_term_refs( $location->ID, 'artwork_type' ),
				'artwork_collection' => pam_get_export_post_term_refs( $location->ID, 'artwork_collection' ),
			),
			'images'       => $include_images ? array(
				'featured' => $featured_image_id ? pam_get_export_attachment_data( $featured_image_id ) : null,
				'gallery'  => array_values(
					array_filter(
						array_map( 'pam_get_export_attachment_data', $gallery_image_ids )
					)
				),
			) : array(
				'featured' => null,
				'gallery'  => array(),
			),
		);
	}

	return $payload;
}

/**
 * Export taxonomy term data.
 *
 * @param string $taxonomy       Taxonomy name.
 * @param bool   $include_images Whether to include image data.
 * @return array<int, array<string, mixed>>
 */
function pam_get_export_terms( $taxonomy, $include_images ) {
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
			'parent_slug' => $term->parent ? pam_get_term_slug( $term->parent, $taxonomy ) : '',
			'meta'        => array(
				'color' => get_term_meta( $term->term_id, 'pam_color', true ),
				'icon'  => ( $include_images && $icon_id ) ? pam_get_export_attachment_data( $icon_id ) : null,
			),
		);
	}

	return $payload;
}

/**
 * Export term references attached to a post.
 *
 * @param int    $post_id   Post ID.
 * @param string $taxonomy  Taxonomy name.
 * @return array<int, array<string, mixed>>
 */
function pam_get_export_post_term_refs( $post_id, $taxonomy ) {
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

/**
 * Build normalized attachment data.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<string, mixed>|null
 */
function pam_get_export_attachment_data( $attachment_id ) {
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

/**
 * Safely resolve a parent term slug.
 *
 * @param int    $term_id   Term ID.
 * @param string $taxonomy  Taxonomy name.
 * @return string
 */
function pam_get_term_slug( $term_id, $taxonomy ) {
	$term = get_term( $term_id, $taxonomy );

	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	return $term->slug;
}
