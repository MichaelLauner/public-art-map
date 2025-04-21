<?php
add_action( 'admin_menu', 'pam_add_settings_page' );

function pam_add_settings_page() {
	add_options_page(
		'Public Art Map Settings',
		'Public Art Map',
		'manage_options',
		'public-art-map',
		'pam_render_settings_page'
	);
}

add_action( 'admin_init', 'pam_register_settings' );

function pam_register_settings() {
	register_setting( 'pam_settings_group', 'pam_map_page', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );

	register_setting( 'pam_settings_group', 'pam_mapbox_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
	
	add_settings_field(
		'pam_mapbox_api_key',
		'Mapbox API Key',
		'pam_mapbox_api_key_input',
		'public-art-map',
		'pam_main_settings'
	);

	add_settings_section(
		'pam_main_settings',
		'Map Display Settings',
		'__return_null',
		'public-art-map'
	);

	add_settings_field(
		'pam_map_page',
		'Map Display Page',
		'pam_map_page_dropdown',
		'public-art-map',
		'pam_main_settings'
	);

	// Auto Geocode Settings
	register_setting( 'pam_settings_group', 'pam_cron_autofill_coords', array(
		'type'              => 'boolean',
		'sanitize_callback' => function( $val ) {
			return (bool) $val;
		},
		'default'           => false,
	) );
	add_settings_field(
		'pam_cron_autofill_coords',
		'Background Coordinate Fill',
		'pam_cron_autofill_checkbox_with_status',
		'public-art-map',
		'pam_main_settings'
	);

}

function pam_map_page_dropdown() {
	$selected = get_option( 'pam_map_page' );
	wp_dropdown_pages(array(
		'name'              => 'pam_map_page',
		'selected'          => $selected,
		'show_option_none'  => '-- Select a page --',
		'option_none_value' => 0
	));
}

function pam_render_settings_page() {
	?>
	<div class="wrap">
		<h1>Public Art Map Settings</h1>
		<form method="post" action="options.php">
			<?php
				settings_fields( 'pam_settings_group' );
				do_settings_sections( 'public-art-map' );
				submit_button();
			?>
		</form>
	</div>
	<?php
}

function pam_mapbox_api_key_input() {
	$value = esc_attr( get_option( 'pam_mapbox_api_key', '' ) );
	echo '<input type="text" name="pam_mapbox_api_key" value="' . $value . '" style="width: 100%;" placeholder="pk.XXXXXXX">';
	echo '<p class="description">Paste your Mapbox public access token (starts with <code>pk.</code>).</p>';
}

/**
 * Auto Geocode Coordinates
 */
function pam_cron_autofill_checkbox_with_status() {
	$checked = checked( get_option( 'pam_cron_autofill_coords', false ), true, false );

	$total = wp_count_posts( 'map_location' )->publish;
	$with_coords = new WP_Query(array(
		'post_type'      => 'map_location',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => 'pam_coordinates',
				'compare' => 'EXISTS'
			)
		),
		'fields' => 'ids'
	));
	$count_with_coords = $with_coords->post_count;
	$count_missing     = $total - $count_with_coords;

	echo "<label><input type='checkbox' name='pam_cron_autofill_coords' value='1' $checked> Automatically populate missing coordinates in the background</label>";
	echo "<p class='description'>";
	echo "Currently: <strong>$count_with_coords</strong> with coordinates, <strong>$count_missing</strong> remaining.";
	echo "</p>";
	if ( get_transient( 'pam_cron_is_running' ) ) {
		echo '<p class="description" style="color:#999;">ℹ️ Background process is currently running…</p>';
	}
	$last_run = get_transient( 'pam_cron_last_run' );
	if ( $last_run ) {
		echo "<p class='description'>Last run: <strong>$last_run</strong></p>";
	}

}

/**
 * Auto Geocode Coordinates
 */
add_action( 'update_option_pam_cron_autofill_coords', 'pam_manage_cron_schedule', 10, 2 );
function pam_manage_cron_schedule( $new_value, $old_value ) {
	error_log("pam_manage_cron_schedule triggered. New: " . var_export($new_value, true));

	if ( $new_value && ! wp_next_scheduled( 'pam_cron_fill_coordinates' ) ) {
		error_log("Scheduling pam_cron_fill_coordinates...");
		pam_run_cron_coordinate_fill();
		wp_schedule_event( time() + 60, 'hourly', 'pam_cron_fill_coordinates' );
		do_action( 'pam_cron_fill_coordinates' );
	} elseif ( ! $new_value ) {
		error_log("Clearing pam_cron_fill_coordinates...");
		wp_clear_scheduled_hook( 'pam_cron_fill_coordinates' );
	}
}

/**
 * Create the Cron Handler
 */
add_action( 'pam_cron_fill_coordinates', 'pam_run_cron_coordinate_fill' );
function pam_run_cron_coordinate_fill() {

	set_transient( 'pam_cron_is_running', true, 10 * MINUTE_IN_SECONDS );

	$posts = get_posts(array(
		'post_type'      => 'map_location',
		'post_status'    => 'publish',
		'posts_per_page' => 5, // keep it light per run
		'meta_query'     => array(
			array(
				'key'     => 'pam_coordinates',
				'compare' => 'NOT EXISTS'
			),
		)
	));

	foreach ( $posts as $post ) {
		$address = trim( get_post_meta( $post->ID, 'pam_address', true ) );
		$city    = trim( get_post_meta( $post->ID, 'pam_city', true ) );
		$state   = trim( get_post_meta( $post->ID, 'pam_state', true ) );
		$zip     = trim( get_post_meta( $post->ID, 'pam_zip', true ) );

		$full_address = implode( ', ', array_filter( [ $address, $city, $state, $zip ] ) );
		if ( ! $full_address ) continue;

		$token = get_option( 'pam_mapbox_api_key' );
		$url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . urlencode( $full_address ) . '.json?access_token=' . $token;

		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) continue;

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['features'][0]['center'] ) ) continue;

		$lng = $data['features'][0]['center'][0];
		$lat = $data['features'][0]['center'][1];
		update_post_meta( $post->ID, 'pam_coordinates', "{$lat},{$lng}" );
	}

	// After the foreach loop...
	$remaining = new WP_Query(array(
		'post_type'      => 'map_location',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => 'pam_coordinates',
				'compare' => 'NOT EXISTS'
			)
		),
		'fields' => 'ids'
	));

	if ( $remaining->post_count === 0 ) {
		// All done — disable cron
		update_option( 'pam_cron_autofill_coords', 0 );
		wp_clear_scheduled_hook( 'pam_cron_fill_coordinates' );
	}

	set_transient( 'pam_cron_last_run', current_time( 'mysql' ), 12 * HOUR_IN_SECONDS );

	delete_transient( 'pam_cron_is_running' ); // clear flag when done

}

