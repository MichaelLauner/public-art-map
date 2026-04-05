<?php
namespace PublicArtMap\Meta;

use PublicArtMap\Admin\AdminNoticeManager;
use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;
use PublicArtMap\Service\GeocodingService;

class MetaManager implements Service {
	private PluginContext $context;
	private GeocodingService $geocoder;
	private AdminNoticeManager $notice_manager;

	public function __construct( PluginContext $context, GeocodingService $geocoder, AdminNoticeManager $notice_manager ) {
		$this->context        = $context;
		$this->geocoder       = $geocoder;
		$this->notice_manager = $notice_manager;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'registerMetaFields' ) );
		add_action( 'init', array( $this, 'registerGalleryMeta' ) );
		add_action( 'add_meta_boxes', array( $this, 'addLocationMetaBox' ) );
		add_action( 'add_meta_boxes', array( $this, 'addGalleryMetaBox' ) );
		add_action( 'save_post_map_location', array( $this, 'saveLocationMetaFields' ) );
		add_action( 'save_post_map_location', array( $this, 'geocodeCoordinatesOnSave' ), 20, 2 );
		add_action( 'save_post_map_location', array( $this, 'saveGalleryImages' ), 20 );
	}

	public function registerMetaFields(): void {
		$fields = array(
			'pam_artist'      => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_description' => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_address'     => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_city'        => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_state'       => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_zip'         => array( 'type' => 'string', 'show_in_rest' => true ),
			'pam_auto_geocode'=> array( 'type' => 'boolean', 'show_in_rest' => true ),
		);

		foreach ( $fields as $meta_key => $args ) {
			register_post_meta(
				'map_location',
				$meta_key,
				array(
					'show_in_rest'  => $args['show_in_rest'],
					'single'        => true,
					'type'          => $args['type'],
					'auth_callback' => array( $this, 'canEditPosts' ),
				)
			);
		}

		register_post_meta(
			'map_location',
			'pam_coordinates',
			array(
				'show_in_rest'  => array(
					'schema' => array(
						'type'    => 'string',
						'pattern' => '^-?\d+(\.\d+)?,-?\d+(\.\d+)?$',
					),
				),
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => array( $this, 'canEditPosts' ),
			)
		);
	}

	public function registerGalleryMeta(): void {
		register_post_meta(
			'map_location',
			'pam_images',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'single'            => true,
				'type'              => 'array',
				'auth_callback'     => array( $this, 'canEditPosts' ),
				'sanitize_callback' => array( $this, 'sanitizeImages' ),
			)
		);
	}

	public function canEditPosts(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function addLocationMetaBox(): void {
		add_meta_box(
			'pam_location_details',
			__( 'Location Details', $this->context->textDomain() ),
			array( $this, 'renderLocationMetaBox' ),
			'map_location',
			'normal',
			'default'
		);
	}

	public function renderLocationMetaBox( $post ): void {
		$artist       = get_post_meta( $post->ID, 'pam_artist', true );
		$description  = get_post_meta( $post->ID, 'pam_description', true );
		$address      = get_post_meta( $post->ID, 'pam_address', true );
		$city         = get_post_meta( $post->ID, 'pam_city', true );
		$state        = get_post_meta( $post->ID, 'pam_state', true );
		$zip          = get_post_meta( $post->ID, 'pam_zip', true );
		$coordinates  = get_post_meta( $post->ID, 'pam_coordinates', true );
		$auto_geocode = get_post_meta( $post->ID, 'pam_auto_geocode', true );
		$coords       = explode( ',', (string) $coordinates );
		$lat          = (float) ( $coords[0] ?? 41.139 );
		$lng          = (float) ( $coords[1] ?? -104.820 );
		$has_coords   = ! empty( $coordinates ) && false !== strpos( (string) $coordinates, ',' );

		wp_nonce_field( 'pam_save_location_meta', 'pam_location_nonce' );
		?>
		<p>
			<label for="pam_artist"><strong><?php esc_html_e( 'Artist:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_artist" id="pam_artist" value="<?php echo esc_attr( $artist ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="pam_description"><strong><?php esc_html_e( 'Short Description (max 280 characters):', $this->context->textDomain() ); ?></strong></label><br>
			<textarea name="pam_description" id="pam_description" maxlength="280" rows="4" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
		</p>
		<?php if ( $has_coords ) : ?>
			<?php list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coordinates ) ); ?>
			<p>
				<label><strong><?php esc_html_e( 'Map Preview:', $this->context->textDomain() ); ?></strong></label>
				<div id="pam-map-admin" style="height: 300px; width: 100%; margin-top: 10px;"></div>
			</p>
		<?php endif; ?>
		<p>
			<label for="pam_address"><strong><?php esc_html_e( 'Address:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_address" id="pam_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="pam_city"><strong><?php esc_html_e( 'City:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_city" id="pam_city" value="<?php echo esc_attr( $city ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="pam_state"><strong><?php esc_html_e( 'State:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_state" id="pam_state" value="<?php echo esc_attr( $state ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="pam_zip"><strong><?php esc_html_e( 'ZIP Code:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_zip" id="pam_zip" value="<?php echo esc_attr( $zip ); ?>" style="width:100%;">
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Coordinates:', $this->context->textDomain() ); ?></strong></label><br>
			<input type="text" name="pam_coordinates" id="pam_coordinates" value="<?php echo esc_attr( $coordinates ); ?>" style="width:100%;" readonly>
		</p>
		<p>
			<input type="checkbox" id="pam_auto_geocode" name="pam_auto_geocode" value="1" <?php checked( $auto_geocode, true ); ?>>
			<label for="pam_auto_geocode"><?php esc_html_e( 'Automatically update latitude and longitude from the address when saving (requires a Mapbox API key).', $this->context->textDomain() ); ?></label>
		</p>
		<?php
	}

	public function saveLocationMetaFields( int $post_id ): void {
		if ( ! $this->canSaveMapLocationMeta( $post_id, 'pam_location_nonce', 'pam_save_location_meta' ) ) {
			return;
		}

		$fields = array(
			'pam_artist'      => 'sanitize_text_field',
			'pam_description' => 'sanitize_textarea_field',
			'pam_address'     => 'sanitize_text_field',
			'pam_city'        => 'sanitize_text_field',
			'pam_state'       => 'sanitize_text_field',
			'pam_zip'         => 'sanitize_text_field',
			'pam_coordinates' => 'sanitize_text_field',
		);

		foreach ( $fields as $field => $sanitizer ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, $sanitizer( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		update_post_meta( $post_id, 'pam_auto_geocode', isset( $_POST['pam_auto_geocode'] ) ? 1 : 0 );
	}

	public function geocodeCoordinatesOnSave( int $post_id, $post ): void {
		if ( ! $this->canSaveMapLocationMeta( $post_id, 'pam_location_nonce', 'pam_save_location_meta' ) ) {
			return;
		}

		if ( ! get_post_meta( $post_id, 'pam_auto_geocode', true ) ) {
			return;
		}

		$full_address = $this->geocoder->buildPostAddress( $post_id );
		if ( '' === $full_address ) {
			return;
		}

		if ( ! $this->geocoder->hasMapboxToken() ) {
			$this->notice_manager->setNotice( __( 'Mapbox API key is missing. Add it in Public Art Map settings before geocoding addresses.', $this->context->textDomain() ), 'error' );
			return;
		}

		$result = $this->geocoder->updatePostCoordinates( $post_id );
		if ( is_wp_error( $result ) ) {
			$this->notice_manager->setNotice(
				sprintf(
					/* translators: %s: error message. */
					__( 'Mapbox request failed: %s', $this->context->textDomain() ),
					$result->get_error_message()
				),
				'error'
			);
			return;
		}

		if ( null === $result ) {
			$this->notice_manager->setNotice(
				sprintf(
					/* translators: %s: address string. */
					__( 'No geocoding result found for: %s', $this->context->textDomain() ),
					$full_address
				),
				'warning'
			);
			return;
		}

		update_post_meta( $post_id, 'pam_auto_geocode', 0 );
		$this->notice_manager->setNotice(
			sprintf(
				/* translators: %s: address string. */
				__( 'Coordinates successfully updated for: %s', $this->context->textDomain() ),
				$full_address
			),
			'success'
		);
	}

	public function sanitizeImages( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'absint', $value );
	}

	public function addGalleryMetaBox(): void {
		add_meta_box(
			'pam_gallery',
			__( 'Gallery Images', $this->context->textDomain() ),
			array( $this, 'renderGalleryMetabox' ),
			'map_location',
			'normal',
			'default'
		);
	}

	public function renderGalleryMetabox( $post ): void {
		$images = get_post_meta( $post->ID, 'pam_images', true ) ?: array();
		wp_nonce_field( 'pam_save_gallery_meta', 'pam_gallery_meta_nonce' );
		?>
		<div id="pam-gallery-wrapper">
			<ul id="pam-gallery-list">
				<?php foreach ( $images as $attachment_id ) : ?>
					<?php $thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' ); ?>
					<?php if ( ! $thumb ) { continue; } ?>
					<li data-id="<?php echo esc_attr( $attachment_id ); ?>">
						<img src="<?php echo esc_url( $thumb[0] ); ?>" alt="<?php echo esc_attr__( 'Gallery image thumbnail', $this->context->textDomain() ); ?>" />
						<button class="remove-image" aria-label="<?php echo esc_attr__( 'Remove image', $this->context->textDomain() ); ?>">×</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" id="pam_images" name="pam_images" value="<?php echo esc_attr( implode( ',', $images ) ); ?>" />
			<p>
				<button type="button" class="button" id="pam-add-images"><?php esc_html_e( 'Add Images', $this->context->textDomain() ); ?></button>
			</p>
		</div>
		<?php
	}

	public function saveGalleryImages( int $post_id ): void {
		if ( ! $this->canSaveMapLocationMeta( $post_id, 'pam_gallery_meta_nonce', 'pam_save_gallery_meta' ) ) {
			return;
		}

		if ( isset( $_POST['pam_images'] ) ) {
			$ids = array_filter( explode( ',', sanitize_text_field( wp_unslash( $_POST['pam_images'] ) ) ) );
			update_post_meta( $post_id, 'pam_images', $ids );
		}
	}

	private function canSaveMapLocationMeta( int $post_id, string $field, string $action ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		if ( ! isset( $_POST[ $field ] ) ) {
			return false;
		}

		return wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action );
	}
}
