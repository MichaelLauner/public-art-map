<?php
/**
 * Public Art Map - Meta Fields
 *
 * @package Public Art Map
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Enqueue Mapbox GL JS and CSS
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( in_array( $hook, array('post.php','post-new.php') ) && get_post_type() === 'map_location' ) {
		wp_enqueue_media();

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script( 'mapbox-gl', pam_get_mapbox_asset_url( 'mapbox-gl.js' ), array(), '2.15.0', true );
		wp_enqueue_style( 'mapbox-gl', pam_get_mapbox_asset_url( 'mapbox-gl.css' ), array(), '2.15.0' );

		wp_enqueue_script( 'pam-gallery-js', plugin_dir_url(__FILE__).'../js/pam-gallery.js', [ 'jquery' ], '1.0', true );
		wp_enqueue_style( 'pam-gallery-css-admin', plugin_dir_url(__FILE__).'../css/pam-gallery-admin.css' );
		wp_localize_script(
			'pam-gallery-js',
			'pamGalleryL10n',
			array(
				'selectImages' => __( 'Select Images', PAM_TEXT_DOMAIN ),
				'addToGallery' => __( 'Add to gallery', PAM_TEXT_DOMAIN ),
				'removeImage'  => __( 'Remove image', PAM_TEXT_DOMAIN ),
			)
		);

		wp_enqueue_script(
			'pam-admin-map',
			plugin_dir_url(__FILE__) . '../js/pam-admin-map.js',
			array( 'mapbox-gl', 'jquery' ),
			'1.0',
			true
		);

		$coords = get_post_meta( get_the_ID(), 'pam_coordinates', true );
		list( $lat, $lng ) = array_map(
			'floatval',
			array_pad( explode( ',', $coords ), 2, '' )
		);
		if ( ! $lat && ! $lng ) {
			$lat = 41.139;
			$lng = -104.820;
		}

		wp_localize_script(
			'pam-admin-map',
			'pamAdmin',
			array(
				'mapboxKey'        => get_option( 'pam_mapbox_api_key' ),
				'lat'              => $lat,
				'lng'              => $lng,
				'selectCoordinates' => __( 'Select coordinates on the map', PAM_TEXT_DOMAIN ),
			)
		);

		wp_enqueue_script(
			'pam-editor-refresh-map',
			plugin_dir_url(__FILE__) . '../js/pam-editor-refresh-map.js',
			array( 'wp-data' ),
			'1.0',
			true
		);

	}
});


/**
 * Enqueue Gallery CSS for Frontend
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'pam-gallery-css', plugin_dir_url(__FILE__) . '../css/pam-gallery.css', [], '1.0' );
});


/**
 * Enqueue JS for Gallery Modal
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( is_singular( 'map_location' ) ) {
		wp_enqueue_script( 'pam-gallery-modal', plugin_dir_url(__FILE__) . '../js/pam-gallery-modal.js', [ 'jquery' ], '1.0', true );
		wp_localize_script( 'pam-gallery-modal', 'pamGallery', [
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'pam_gallery_nonce' ),
			'closeModal'  => __( 'Close image gallery', PAM_TEXT_DOMAIN ),
			'previous'    => __( 'Previous image', PAM_TEXT_DOMAIN ),
			'next'        => __( 'Next image', PAM_TEXT_DOMAIN ),
		] );
	}
});


/**
 * Register Meta Fields for Map Location
 */
add_action( 'init', 'pam_register_meta_fields' );
function pam_register_meta_fields() {
	register_post_meta( 'map_location', 'pam_artist', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'map_location', 'pam_description', array(
		'show_in_rest'  => true,
		'single'        => true,
		'type'          => 'string',
		'auth_callback' => function() {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'map_location', 'pam_address', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'map_location', 'pam_city', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );
	
	register_post_meta( 'map_location', 'pam_state', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );
	
	register_post_meta( 'map_location', 'pam_zip', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );    

	register_post_meta( 'map_location', 'pam_coordinates', [
		'show_in_rest'   => [
			'schema' => [
			'type' => 'string',
			'pattern' => '^-?\d+(\.\d+)?,-?\d+(\.\d+)?$',
			],
		],
		'single'         => true,
		'type'           => 'string',
		'auth_callback'  => function() {
			return current_user_can( 'edit_posts' );
		},
	] );

	register_post_meta( 'map_location', 'pam_auto_geocode', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'boolean',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
	) );

}


/**
 * Add Meta Box for Location Details
 */
add_action( 'add_meta_boxes', 'pam_add_location_meta_box' );

function pam_add_location_meta_box() {
	add_meta_box(
		'pam_location_details',
		__( 'Location Details', PAM_TEXT_DOMAIN ),
		'pam_render_location_meta_box',
		'map_location',
		'normal',
		'default'
	);
}

function pam_render_location_meta_box( $post ) {
	$artist      = get_post_meta( $post->ID, 'pam_artist', true );
	$description = get_post_meta( $post->ID, 'pam_description', true );
	$address     = get_post_meta( $post->ID, 'pam_address', true );
	$city = get_post_meta( $post->ID, 'pam_city', true );
	$state = get_post_meta( $post->ID, 'pam_state', true );
	$zip = get_post_meta( $post->ID, 'pam_zip', true );
	$coordinates = get_post_meta( $post->ID, 'pam_coordinates', true );
	$auto_geocode = get_post_meta( $post->ID, 'pam_auto_geocode', true );
	
	$mapbox_key = get_option( 'pam_mapbox_api_key' );
	$coords = explode( ',', $coordinates );
	$lat = floatval( $coords[0] ?? 41.139 ); // Default to Cheyenne
	$lng = floatval( $coords[1] ?? -104.820 );
	$has_coords = !empty( $coordinates ) && strpos( $coordinates, ',' ) !== false;
	?>
	<?php wp_nonce_field( 'pam_save_location_meta', 'pam_location_nonce' ); ?>

	<p>
		<label for="pam_artist"><strong><?php esc_html_e( 'Artist:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_artist" id="pam_artist" value="<?php echo esc_attr( $artist ); ?>" style="width:100%;">
	</p>
	<p>
		<label for="pam_description"><strong><?php esc_html_e( 'Short Description (max 280 characters):', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<textarea name="pam_description" id="pam_description" maxlength="280" rows="4" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
	</p>
	<?php
	if ( $has_coords ) {
		list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coordinates ) ); ?>
		<p>
			<label><strong><?php esc_html_e( 'Map Preview:', PAM_TEXT_DOMAIN ); ?></strong></label>
			<div id="pam-map-admin" style="height: 300px; width: 100%; margin-top: 10px;"></div>
		</p>
		<?php
	} ?>
	<p>
		<label for="pam_address"><strong><?php esc_html_e( 'Address:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_address" id="pam_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;">
	</p>
	<p>
		<label for="pam_city"><strong><?php esc_html_e( 'City:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_city" id="pam_city" value="<?php echo esc_attr( $city ); ?>" style="width:100%;">
	</p>
	<p>
		<label for="pam_state"><strong><?php esc_html_e( 'State:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_state" id="pam_state" value="<?php echo esc_attr( $state ); ?>" style="width:100%;">
	</p>
	<p>
		<label for="pam_zip"><strong><?php esc_html_e( 'ZIP Code:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_zip" id="pam_zip" value="<?php echo esc_attr( $zip ); ?>" style="width:100%;">
	</p>
	<p>
		<label><strong><?php esc_html_e( 'Coordinates:', PAM_TEXT_DOMAIN ); ?></strong></label><br>
		<input type="text" name="pam_coordinates" id="pam_coordinates" value="<?php echo esc_attr( $coordinates ); ?>" style="width:100%;" readonly>
	</p>
	<p>
		<input type="checkbox" id="pam_auto_geocode" name="pam_auto_geocode" value="1" <?php checked( $auto_geocode, true ); ?>>
		<label for="pam_auto_geocode"><?php esc_html_e( 'Automatically update latitude and longitude from the address when saving (requires a Mapbox API key).', PAM_TEXT_DOMAIN ); ?></label>
	</p>

	<?php
}


/**
 * Save Meta Fields
 */
add_action( 'save_post_map_location', 'pam_save_location_meta_fields' );

function pam_save_location_meta_fields( $post_id ) {
	if ( ! pam_can_save_map_location_meta( $post_id, 'pam_location_nonce', 'pam_save_location_meta' ) ) {
		return;
	}

	if ( isset($_POST['pam_artist']) ) {
		update_post_meta( $post_id, 'pam_artist', sanitize_text_field( wp_unslash( $_POST['pam_artist'] ) ) );
	}
	if ( isset($_POST['pam_description']) ) {
		update_post_meta( $post_id, 'pam_description', sanitize_textarea_field( wp_unslash( $_POST['pam_description'] ) ) );
	}
	if ( isset($_POST['pam_address']) ) {
		update_post_meta( $post_id, 'pam_address', sanitize_text_field( wp_unslash( $_POST['pam_address'] ) ) );
	}
	if ( isset($_POST['pam_city']) ) {
		update_post_meta( $post_id, 'pam_city', sanitize_text_field( wp_unslash( $_POST['pam_city'] ) ) );
	}
	if ( isset($_POST['pam_state']) ) {
		update_post_meta( $post_id, 'pam_state', sanitize_text_field( wp_unslash( $_POST['pam_state'] ) ) );
	}
	if ( isset($_POST['pam_zip']) ) {
		update_post_meta( $post_id, 'pam_zip', sanitize_text_field( wp_unslash( $_POST['pam_zip'] ) ) );
	}
	if ( isset($_POST['pam_coordinates']) ) {
		update_post_meta( $post_id, 'pam_coordinates', sanitize_text_field( wp_unslash( $_POST['pam_coordinates'] ) ) );
	}

	update_post_meta( $post_id, 'pam_auto_geocode', isset($_POST['pam_auto_geocode']) ? 1 : 0 );

}

add_action( 'admin_init', 'pam_register_settings' );


/**
 * Auto Geocode Coordinates
 */
add_action( 'save_post_map_location', 'pam_geocode_coordinates_on_save', 20, 2 );
function pam_geocode_coordinates_on_save( $post_id, $post ) {
	if ( ! pam_can_save_map_location_meta( $post_id, 'pam_location_nonce', 'pam_save_location_meta' ) ) {
		return;
	}

	$auto = get_post_meta( $post_id, 'pam_auto_geocode', true );
	if ( ! $auto ) return;

	$address = trim( get_post_meta( $post_id, 'pam_address', true ) );
	$city    = trim( get_post_meta( $post_id, 'pam_city', true ) );
	$state   = trim( get_post_meta( $post_id, 'pam_state', true ) );
	$zip     = trim( get_post_meta( $post_id, 'pam_zip', true ) );

	if ( ! $address && ! $city && ! $state && ! $zip ) return;

	$full_address = implode( ', ', array_filter( [ $address, $city, $state, $zip ] ) );

	$mapbox_token = get_option( 'pam_mapbox_api_key' );
	if ( empty( $mapbox_token ) ) {
		pam_set_admin_notice( __( 'Mapbox API key is missing. Add it in Public Art Map settings before geocoding addresses.', PAM_TEXT_DOMAIN ), 'error' );
		return;
	}

	$request_url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode( $full_address ) . '.json?access_token=' . $mapbox_token;

	$response = wp_remote_get( $request_url );

	if ( is_wp_error( $response ) ) {
		pam_set_admin_notice(
			sprintf(
				/* translators: %s: error message. */
				__( 'Mapbox request failed: %s', PAM_TEXT_DOMAIN ),
				$response->get_error_message()
			),
			'error'
		);
		return;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['features'][0]['center'] ) ) {
		pam_set_admin_notice(
			sprintf(
				/* translators: %s: address string. */
				__( 'No geocoding result found for: %s', PAM_TEXT_DOMAIN ),
				$full_address
			),
			'warning'
		);
		return;
	}

	$lng = $body['features'][0]['center'][0];
	$lat = $body['features'][0]['center'][1];
	$coords = "{$lat},{$lng}";

	update_post_meta( $post_id, 'pam_coordinates', $coords );
	update_post_meta( $post_id, 'pam_auto_geocode', 0 ); // uncheck checkbox

	pam_set_admin_notice(
		sprintf(
			/* translators: %s: address string. */
			__( 'Coordinates successfully updated for: %s', PAM_TEXT_DOMAIN ),
			$full_address
		),
		'success'
	);
}

function pam_set_admin_notice( $message, $type = 'success' ) {
	set_transient( 'pam_admin_notice', array(
		'message' => $message,
		'type'    => $type,
	), 30 );
}

add_action( 'admin_notices', function() {
	if ( $notice = get_transient( 'pam_admin_notice' ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
		delete_transient( 'pam_admin_notice' );
	}
} );

/**
 * Register Meta Fields for Gallery
 */
add_action( 'init', 'pam_register_gallery_meta' );
function pam_register_gallery_meta() {
	register_post_meta( 'map_location', 'pam_images', [
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'array',
		'auth_callback'     => function() {
			return current_user_can( 'edit_posts' );
		},
		'sanitize_callback' => 'pam_sanitize_images',
	] );
}

function pam_sanitize_images( $value ) {
	// Expect array of ints
	if ( ! is_array( $value ) ) {
		return [];
	}
	return array_map( 'absint', $value );
}

/**
 * Add Meta Box for Gallery
 */
add_action( 'add_meta_boxes', function() {
	add_meta_box( 'pam_gallery', __( 'Gallery Images', PAM_TEXT_DOMAIN ), 'pam_render_gallery_metabox', 'map_location', 'normal', 'default' );
} );

/**
 * Render Gallery Meta Box
 */
function pam_render_gallery_metabox( $post ) {
	$images = get_post_meta( $post->ID, 'pam_images', true ) ?: [];
	?>
	<?php wp_nonce_field( 'pam_save_gallery_meta', 'pam_gallery_meta_nonce' ); ?>
	<div id="pam-gallery-wrapper">
		<ul id="pam-gallery-list">
		<?php foreach ( $images as $attachment_id ): 
			$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			if ( ! $thumb ) continue;
		?>
			<li data-id="<?php echo esc_attr( $attachment_id ); ?>">
				<img src="<?php echo esc_url( $thumb[0] ); ?>" alt="<?php echo esc_attr__( 'Gallery image thumbnail', PAM_TEXT_DOMAIN ); ?>" />
				<button class="remove-image" aria-label="<?php echo esc_attr__( 'Remove image', PAM_TEXT_DOMAIN ); ?>">×</button>
			</li>
		<?php endforeach; ?>
		</ul>
		<input type="hidden" id="pam_images" name="pam_images" value="<?php echo esc_attr( implode( ',', $images ) ); ?>" />
		<p>
			<button type="button" class="button" id="pam-add-images"><?php esc_html_e( 'Add Images', PAM_TEXT_DOMAIN ); ?></button>
		</p>
	</div>
	<?php
}

/**
 * Save Gallery Images
 */
add_action( 'save_post_map_location', function( $post_id ){
	if ( ! pam_can_save_map_location_meta( $post_id, 'pam_gallery_meta_nonce', 'pam_save_gallery_meta' ) ) {
		return;
	}
	if ( isset($_POST['pam_images']) ) {
		$ids = array_filter( explode( ',', sanitize_text_field( wp_unslash( $_POST['pam_images'] ) ) ) );
		update_post_meta( $post_id, 'pam_images', $ids );
	}
}, 20 );

/**
 * Validate the map location save request.
 *
 * @param int    $post_id Post ID.
 * @param string $field   Nonce field name.
 * @param string $action  Nonce action.
 * @return bool
 */
function pam_can_save_map_location_meta( $post_id, $field, $action ) {
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

	$nonce = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
	return wp_verify_nonce( $nonce, $action );
}
