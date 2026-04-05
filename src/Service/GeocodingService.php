<?php
namespace PublicArtMap\Service;

use WP_Error;

class GeocodingService {
	public function hasMapboxToken(): bool {
		return '' !== $this->getMapboxToken();
	}

	public function getMapboxToken(): string {
		return trim( (string) get_option( 'pam_mapbox_api_key', '' ) );
	}

	public function buildAddress( string $address, string $city, string $state, string $zip ): string {
		return implode( ', ', array_filter( array( trim( $address ), trim( $city ), trim( $state ), trim( $zip ) ) ) );
	}

	public function buildPostAddress( int $post_id ): string {
		return $this->buildAddress(
			(string) get_post_meta( $post_id, 'pam_address', true ),
			(string) get_post_meta( $post_id, 'pam_city', true ),
			(string) get_post_meta( $post_id, 'pam_state', true ),
			(string) get_post_meta( $post_id, 'pam_zip', true )
		);
	}

	public function getPostsMissingCoordinates( int $limit = 5 ): array {
		return get_posts(
			array(
				'post_type'      => 'map_location',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_query'     => array(
					array(
						'key'     => 'pam_coordinates',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	public function requestCoordinates( string $full_address ) {
		$token = $this->getMapboxToken();
		if ( '' === $token ) {
			return new WP_Error( 'pam_missing_mapbox_token', __( 'Mapbox API key is missing.', PAM_TEXT_DOMAIN ) );
		}

		$response = wp_remote_get(
			'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode( $full_address ) . '.json?access_token=' . rawurlencode( $token )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['features'][0]['center'] ) ) {
			return null;
		}

		return array(
			'lng' => (float) $body['features'][0]['center'][0],
			'lat' => (float) $body['features'][0]['center'][1],
		);
	}

	public function updatePostCoordinates( int $post_id ) {
		$full_address = $this->buildPostAddress( $post_id );
		if ( '' === $full_address ) {
			return null;
		}

		$coordinates = $this->requestCoordinates( $full_address );
		if ( is_wp_error( $coordinates ) || null === $coordinates ) {
			return $coordinates;
		}

		update_post_meta( $post_id, 'pam_coordinates', $coordinates['lat'] . ',' . $coordinates['lng'] );

		return array(
			'address' => $full_address,
			'lat'     => $coordinates['lat'],
			'lng'     => $coordinates['lng'],
		);
	}
}
