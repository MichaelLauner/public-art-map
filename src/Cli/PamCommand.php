<?php
namespace PublicArtMap\Cli;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;
use PublicArtMap\Service\GeocodingService;

class PamCommand implements Service {
	private PluginContext $context;
	private GeocodingService $geocoder;

	public function __construct( PluginContext $context, GeocodingService $geocoder ) {
		$this->context  = $context;
		$this->geocoder = $geocoder;
	}

	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pam', $this );
		}
	}

	/**
	 * Fill all missing coordinates using the Mapbox API.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pam fill-coordinates
	 *
	 * @when after_wp_load
	 */
	public function fill_coordinates( $args, $assoc_args ): void {
		\WP_CLI::log( 'Starting coordinate fill process...' );

		if ( ! $this->geocoder->hasMapboxToken() ) {
			\WP_CLI::error( 'No Mapbox API key found in settings.' );
		}

		$count = 0;
		$posts = $this->geocoder->getPostsMissingCoordinates( -1 );

		foreach ( $posts as $post ) {
			$full_address = $this->geocoder->buildPostAddress( $post->ID );
			if ( '' === $full_address ) {
				\WP_CLI::warning( "Skipping post {$post->ID} - no address." );
				continue;
			}

			$result = $this->geocoder->updatePostCoordinates( $post->ID );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::warning( "Error for post {$post->ID}: " . $result->get_error_message() );
				continue;
			}

			if ( null === $result ) {
				\WP_CLI::warning( "No coordinates found for post {$post->ID}" );
				continue;
			}

			$count++;
			\WP_CLI::log( "Updated post {$post->ID} with coordinates: {$result['lat']}, {$result['lng']}" );
		}

		\WP_CLI::success( "Done. Updated {$count} posts." );
	}
}
