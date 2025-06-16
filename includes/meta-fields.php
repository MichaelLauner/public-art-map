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
	if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
        wp_enqueue_media();

        wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js' );
		wp_enqueue_style( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' );

        wp_enqueue_script( 'pam-gallery-js', plugin_dir_url(__FILE__).'../js/pam-gallery.js', [ 'jquery' ], '1.0', true );
        wp_enqueue_style( 'pam-gallery-css-admin', plugin_dir_url(__FILE__).'../css/pam-gallery-admin.css' );
	}
});

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'pam-gallery-css', plugin_dir_url(__FILE__) . '../css/pam-gallery.css', [], '1.0' );
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

    register_post_meta( 'map_location', 'pam_coordinates', array(
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );

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
        'Location Details',
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

    <p>
        <label for="pam_artist"><strong>Artist:</strong></label><br>
        <input type="text" name="pam_artist" id="pam_artist" value="<?php echo esc_attr( $artist ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_description"><strong>Short Description (max 280 characters):</strong></label><br>
        <textarea name="pam_description" id="pam_description" maxlength="280" rows="4" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
    </p>
    <?php
    if ( $has_coords ) {
        list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coordinates ) ); ?>
        <p>
            <label><strong>Map Preview:</strong></label>
            <div id="pam-map-admin" style="height: 300px; width: 100%; margin-top: 10px;"></div>
        </p>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            mapboxgl.accessToken = '<?php echo esc_js( get_option( 'pam_mapbox_api_key' ) ); ?>';

            const map = new mapboxgl.Map({
                container: 'pam-map-admin',
                style: 'mapbox://styles/mapbox/streets-v11',
                center: [<?php echo $lng; ?>, <?php echo $lat; ?>],
                zoom: 15
            });

            const marker = new mapboxgl.Marker({ draggable: true })
                .setLngLat([<?php echo $lng; ?>, <?php echo $lat; ?>])
                .addTo(map);

            marker.on('dragend', function () {
                const lngLat = marker.getLngLat();
                document.getElementById('pam_coordinates').value =
                    `${lngLat.lat.toFixed(6)},${lngLat.lng.toFixed(6)}`;
            });
        });
        </script>
        <?php
    } ?>
    <p>
        <label for="pam_address"><strong>Address:</strong></label><br>
        <input type="text" name="pam_address" id="pam_address" value="<?php echo esc_attr( $address ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_city"><strong>City:</strong></label><br>
        <input type="text" name="pam_city" id="pam_city" value="<?php echo esc_attr( $city ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_state"><strong>State:</strong></label><br>
        <input type="text" name="pam_state" id="pam_state" value="<?php echo esc_attr( $state ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_zip"><strong>ZIP Code:</strong></label><br>
        <input type="text" name="pam_zip" id="pam_zip" value="<?php echo esc_attr( $zip ); ?>" style="width:100%;">
    </p>
    <p>
        <label><strong>Coordinates:</strong></label><br>
        <input type="text" name="pam_coordinates" id="pam_coordinates" value="<?php echo esc_attr( $coordinates ); ?>" style="width:100%;" readonly>
    </p>
    <p>
        <label>
            <input type="checkbox" name="pam_auto_geocode" value="1" <?php checked( $auto_geocode, true ); ?>>
            Automatically update latitude and longitude from address when saving (requires Google Maps API key)
        </label>
    </p>

    <?php
}


/**
 * Save Meta Fields
 */
add_action( 'save_post_map_location', 'pam_save_location_meta_fields' );

function pam_save_location_meta_fields( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

    if ( isset($_POST['pam_artist']) ) {
        update_post_meta( $post_id, 'pam_artist', sanitize_text_field($_POST['pam_artist']) );
    }
    if ( isset($_POST['pam_description']) ) {
        update_post_meta( $post_id, 'pam_description', sanitize_textarea_field($_POST['pam_description']) );
    }
    if ( isset($_POST['pam_address']) ) {
        update_post_meta( $post_id, 'pam_address', sanitize_text_field($_POST['pam_address']) );
    }
    if ( isset($_POST['pam_city']) ) {
        update_post_meta( $post_id, 'pam_city', sanitize_text_field($_POST['pam_city']) );
    }
    if ( isset($_POST['pam_state']) ) {
        update_post_meta( $post_id, 'pam_state', sanitize_text_field($_POST['pam_state']) );
    }
    if ( isset($_POST['pam_zip']) ) {
        update_post_meta( $post_id, 'pam_zip', sanitize_text_field($_POST['pam_zip']) );
    }
    if ( isset($_POST['pam_coordinates']) ) {
        update_post_meta( $post_id, 'pam_coordinates', sanitize_text_field($_POST['pam_coordinates']) );
    }
    update_post_meta( $post_id, 'pam_auto_geocode', isset($_POST['pam_auto_geocode']) ? 1 : 0 );

}

add_action( 'admin_init', 'pam_register_settings' );


/**
 * Auto Geocode Coordinates
 */
add_action( 'save_post_map_location', 'pam_geocode_coordinates_on_save', 20, 2 );
function pam_geocode_coordinates_on_save( $post_id, $post ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$auto = get_post_meta( $post_id, 'pam_auto_geocode', true );
	if ( ! $auto ) return;

	$address = trim( get_post_meta( $post_id, 'pam_address', true ) );
	$city    = trim( get_post_meta( $post_id, 'pam_city', true ) );
	$state   = trim( get_post_meta( $post_id, 'pam_state', true ) );
	$zip     = trim( get_post_meta( $post_id, 'pam_zip', true ) );

	if ( ! $address && ! $city && ! $state && ! $zip ) return;

	$full_address = implode( ', ', array_filter( [ $address, $city, $state, $zip ] ) );

	$mapbox_token = get_option( 'pam_mapbox_api_key' );

	$request_url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode( $full_address ) . '.json?access_token=' . $mapbox_token;

	$response = wp_remote_get( $request_url );

	if ( is_wp_error( $response ) ) {
		pam_set_admin_notice( 'Mapbox request failed: ' . $response->get_error_message(), 'error' );
		return;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['features'][0]['center'] ) ) {
		pam_set_admin_notice( 'No geocoding result found for: ' . esc_html( $full_address ), 'warning' );
		return;
	}

	$lng = $body['features'][0]['center'][0];
	$lat = $body['features'][0]['center'][1];
	$coords = "{$lat},{$lng}";

	update_post_meta( $post_id, 'pam_coordinates', $coords );
	update_post_meta( $post_id, 'pam_auto_geocode', 0 ); // uncheck checkbox

	pam_set_admin_notice( 'Coordinates successfully updated for: ' . esc_html( $full_address ), 'success' );
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
    add_meta_box( 'pam_gallery', 'Gallery Images', 'pam_render_gallery_metabox', 'map_location', 'normal', 'default' );
} );

/**
 * Render Gallery Meta Box
 */
function pam_render_gallery_metabox( $post ) {
    $images = get_post_meta( $post->ID, 'pam_images', true ) ?: [];
    ?>
    <div id="pam-gallery-wrapper">
        <ul id="pam-gallery-list">
        <?php foreach ( $images as $attachment_id ): 
            $thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
            if ( ! $thumb ) continue;
        ?>
            <li data-id="<?php echo esc_attr( $attachment_id ); ?>">
                <img src="<?php echo esc_url( $thumb[0] ); ?>" />
                <button class="remove-image">×</button>
            </li>
        <?php endforeach; ?>
        </ul>
        <input type="hidden" id="pam_images" name="pam_images" value="<?php echo esc_attr( implode( ',', $images ) ); ?>" />
        <p>
            <button type="button" class="button" id="pam-add-images">Add Images</button>
        </p>
    </div>
    <?php
}

/**
 * Save Gallery Images
 */
add_action( 'save_post_map_location', function( $post_id ){
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( isset($_POST['pam_images']) ) {
        $ids = array_filter( explode( ',', sanitize_text_field($_POST['pam_images']) ) );
        update_post_meta( $post_id, 'pam_images', $ids );
    }
}, 20 );


// This is our meta import list of fields, saving here for easy cut/paste
// {primaryimageurl[1]}
// {additionalimage1url[1]}
// {additionalimage2url[1]}
// {additionalimage3url[1]}
// {additionalimage4url[1]}
// {additionalimage5url[1]}
// {additionalimage6url[1]}
// {additionalimage7url[1]}
// {additionalimage8url[1]}
// {additionalimage9url[1]}
// {additionalimage10url[1]}
// {additionalimage11url[1]}
// {additionalimage12url[1]}
// {additionalimage13url[1]}
// {additionalimage14url[1]}
// {additionalimage15url[1]}
// {additionalimage16url[1]}
// {additionalimage17url[1]}
// {additionalimage18url[1]}
// {additionalimage19url[1]}
// {additionalimage20url[1]}
// {additionalimage21url[1]}
// {additionalimage22url[1]}
// {additionalimage23url[1]}
// {additionalimage24url[1]}
// {additionalimage25url[1]}
// {additionalimage26url[1]}
// {additionalimage27url[1]}
// {additionalimage28url[1]}
// {additionalimage29url[1]}
// {additionalimage30url[1]}