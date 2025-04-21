<?php
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
    $coordinates = get_post_meta( $post->ID, 'pam_coordinates', true ); ?>

    <p>
        <label for="pam_artist"><strong>Artist:</strong></label><br>
        <input type="text" name="pam_artist" id="pam_artist" value="<?php echo esc_attr( $artist ); ?>" style="width:100%;">
    </p>
    <p>
        <label for="pam_description"><strong>Short Description (max 280 characters):</strong></label><br>
        <textarea name="pam_description" id="pam_description" maxlength="280" rows="4" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
    </p>
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
        <label for="pam_coordinates"><strong>Coordinates (lat,lng):</strong></label><br>
        <input type="text" name="pam_coordinates" id="pam_coordinates" value="<?php echo esc_attr( $coordinates ); ?>" style="width:100%;" placeholder="e.g. 41.139,-104.820">
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
}

add_action( 'admin_init', 'pam_register_settings' );

