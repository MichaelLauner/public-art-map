<?php
/**
 * Public Art Map CLI Tools
 *
 * @package Public_Art_Map
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class PAM_CLI {

		/**
		 * Fill all missing coordinates using the Mapbox API.
		 *
		 * ## EXAMPLES
		 *
		 *     wp pam fill-coordinates
		 *
		 * @when after_wp_load
		 */
		public function fill_coordinates( $args, $assoc_args ) {
			WP_CLI::log( 'Starting coordinate fill process...' );

			$count = 0;
			$token = get_option( 'pam_mapbox_api_key' );

			if ( ! $token ) {
				WP_CLI::error( 'No Mapbox API key found in settings.' );
			}

			$posts = get_posts(array(
				'post_type'      => 'map_location',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => 'pam_coordinates',
						'compare' => 'NOT EXISTS'
					)
				)
			));

			foreach ( $posts as $post ) {
				$address = trim( get_post_meta( $post->ID, 'pam_address', true ) );
				$city    = trim( get_post_meta( $post->ID, 'pam_city', true ) );
				$state   = trim( get_post_meta( $post->ID, 'pam_state', true ) );
				$zip     = trim( get_post_meta( $post->ID, 'pam_zip', true ) );

				$full_address = implode( ', ', array_filter( [ $address, $city, $state, $zip ] ) );
				if ( ! $full_address ) {
					WP_CLI::warning( "Skipping post {$post->ID} – no address." );
					continue;
				}

				$url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode( $full_address ) . '.json?access_token=' . $token;
				$response = wp_remote_get( $url );

				if ( is_wp_error( $response ) ) {
					WP_CLI::warning( "Error for post {$post->ID}: " . $response->get_error_message() );
					continue;
				}

				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $data['features'][0]['center'] ) ) {
					WP_CLI::warning( "No coordinates found for post {$post->ID}" );
					continue;
				}

				$lng = $data['features'][0]['center'][0];
				$lat = $data['features'][0]['center'][1];
				update_post_meta( $post->ID, 'pam_coordinates', "{$lat},{$lng}" );
				$count++;

				WP_CLI::log( "Updated post {$post->ID} with coordinates: {$lat}, {$lng}" );
			}

			WP_CLI::success( "Done. Updated $count posts." );
		}
	}

	WP_CLI::add_command( 'pam', 'PAM_CLI' );
}