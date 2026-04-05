<?php
add_action( 'admin_menu', 'pam_add_settings_page' );

function pam_add_settings_page() {
	add_options_page(
		__( 'Public Art Map Settings', PAM_TEXT_DOMAIN ),
		__( 'Public Art Map', PAM_TEXT_DOMAIN ),
		'manage_options',
		'public-art-map',
		'pam_render_settings_page'
	);
}

add_action( 'admin_init', 'pam_register_settings' );

function pam_register_settings() {

	register_setting( 'pam_settings_group', 'pam_site_logo', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	) );

	add_settings_field(
		'pam_site_logo',
		__( 'Site Logo', PAM_TEXT_DOMAIN ),
		'pam_site_logo_field',
		'public-art-map',
		'pam_main_settings'
	);

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
		__( 'Mapbox API Key', PAM_TEXT_DOMAIN ),
		'pam_mapbox_api_key_input',
		'public-art-map',
		'pam_main_settings'
	);

	add_settings_section(
		'pam_main_settings',
		__( 'Map Display Settings', PAM_TEXT_DOMAIN ),
		'__return_null',
		'public-art-map'
	);

	add_settings_field(
		'pam_map_page',
		__( 'Map Display Page', PAM_TEXT_DOMAIN ),
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
		__( 'Background Coordinate Fill', PAM_TEXT_DOMAIN ),
		'pam_cron_autofill_checkbox_with_status',
		'public-art-map',
		'pam_main_settings'
	);

	register_setting( 'pam_settings_group', 'pam_export_enabled', array(
		'type'              => 'boolean',
		'sanitize_callback' => function( $value ) {
			return (bool) $value;
		},
		'default'           => false,
	) );

	register_setting( 'pam_settings_group', 'pam_export_token', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );

	add_settings_section(
		'pam_data_sharing_settings',
		__( 'Data Sharing', PAM_TEXT_DOMAIN ),
		'__return_null',
		'public-art-map'
	);

	add_settings_field(
		'pam_export_enabled',
		__( 'Enable Export Feed', PAM_TEXT_DOMAIN ),
		'pam_export_enabled_field',
		'public-art-map',
		'pam_data_sharing_settings'
	);

	add_settings_field(
		'pam_export_token',
		__( 'Export Token', PAM_TEXT_DOMAIN ),
		'pam_export_token_field',
		'public-art-map',
		'pam_data_sharing_settings'
	);

}

function pam_map_page_dropdown() {
	$selected = get_option( 'pam_map_page' );
	wp_dropdown_pages(array(
		'name'              => 'pam_map_page',
		'selected'          => $selected,
		'show_option_none'  => __( '-- Select a page --', PAM_TEXT_DOMAIN ),
		'option_none_value' => 0
	));
}

function pam_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Public Art Map Settings', PAM_TEXT_DOMAIN ); ?></h1>
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
	echo '<p class="description">' . wp_kses_post( __( 'Paste your Mapbox public access token (starts with <code>pk.</code>). Map rendering and geocoding requests depend on this token.', PAM_TEXT_DOMAIN ) ) . '</p>';
}

function pam_export_enabled_field() {
	$enabled    = (bool) get_option( 'pam_export_enabled', false );
	$token      = trim( (string) get_option( 'pam_export_token', '' ) );
	$export_url = rest_url( 'public-art-map/v1/export' );
	?>
	<label>
		<input type="checkbox" name="pam_export_enabled" value="1" <?php checked( $enabled ); ?>>
		<?php esc_html_e( 'Allow another site to fetch map locations, taxonomies, and image URLs from this site.', PAM_TEXT_DOMAIN ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'Export URL:', PAM_TEXT_DOMAIN ); ?>
		<code><?php echo esc_html( $export_url ); ?></code>
	</p>
	<?php if ( $token ) : ?>
		<p class="description">
			<?php echo wp_kses_post( __( 'Use <code>?token=YOUR_TOKEN</code> or an <code>X-PAM-Token</code> header when requesting the feed.', PAM_TEXT_DOMAIN ) ); ?>
		</p>
	<?php else : ?>
		<p class="description">
			<?php esc_html_e( 'Leave the token blank to make the feed public once enabled, or set a token to limit access.', PAM_TEXT_DOMAIN ); ?>
		</p>
	<?php endif;
}

function pam_export_token_field() {
	$value = esc_attr( get_option( 'pam_export_token', '' ) );
	echo '<input type="text" name="pam_export_token" value="' . $value . '" style="width: 100%;" placeholder="' . esc_attr__( 'Optional shared secret', PAM_TEXT_DOMAIN ) . '">';
	echo '<p class="description">' . esc_html__( 'Optional. If set, requests must include this token. This is useful when you want a shareable feed without making it fully public.', PAM_TEXT_DOMAIN ) . '</p>';
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

	printf(
		"<label><input type='checkbox' name='pam_cron_autofill_coords' value='1' %s> %s</label>",
		$checked,
		esc_html__( 'Automatically populate missing coordinates in the background using Mapbox geocoding.', PAM_TEXT_DOMAIN )
	);
	echo "<p class='description'>";
	printf(
		esc_html__( 'Currently: %1$d with coordinates, %2$d remaining.', PAM_TEXT_DOMAIN ),
		(int) $count_with_coords,
		(int) $count_missing
	);
	echo "</p>";
	if ( get_transient( 'pam_cron_is_running' ) ) {
		echo '<p class="description" style="color:#999;">' . esc_html__( 'Background process is currently running.', PAM_TEXT_DOMAIN ) . '</p>';
	}
	$last_run = get_transient( 'pam_cron_last_run' );
	if ( $last_run ) {
		printf(
			"<p class='description'>%s <strong>%s</strong></p>",
			esc_html__( 'Last run:', PAM_TEXT_DOMAIN ),
			esc_html( $last_run )
		);
	}

}

/**
 * Auto Geocode Coordinates
 */
add_action( 'update_option_pam_cron_autofill_coords', 'pam_manage_cron_schedule', 10, 2 );
function pam_manage_cron_schedule( $new_value, $old_value ) {
	if ( $new_value && ! wp_next_scheduled( 'pam_cron_fill_coordinates' ) ) {
		pam_run_cron_coordinate_fill();
		wp_schedule_event( time() + 60, 'hourly', 'pam_cron_fill_coordinates' );
		do_action( 'pam_cron_fill_coordinates' );
	} elseif ( ! $new_value ) {
		wp_clear_scheduled_hook( 'pam_cron_fill_coordinates' );
	}
}

/**
 * Create the Cron Handler
 */
add_action( 'pam_cron_fill_coordinates', 'pam_run_cron_coordinate_fill' );
function pam_run_cron_coordinate_fill() {

	set_transient( 'pam_cron_is_running', true, 10 * MINUTE_IN_SECONDS );
	$token = get_option( 'pam_mapbox_api_key' );

	if ( empty( $token ) ) {
		delete_transient( 'pam_cron_is_running' );
		return;
	}

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

function pam_site_logo_field() {
	$logo_url = esc_url( get_option( 'pam_site_logo', '' ) );
	$media_strings = wp_json_encode(
		array(
			'selectLogo' => __( 'Select or Upload Logo', PAM_TEXT_DOMAIN ),
			'useLogo'    => __( 'Use this logo', PAM_TEXT_DOMAIN ),
		)
	);
	?>
	<div style="max-width: 300px;">
		<img id="pam-site-logo-preview" src="<?php echo $logo_url; ?>" alt="<?php echo esc_attr__( 'Site logo preview', PAM_TEXT_DOMAIN ); ?>" style="max-width:100%;<?php echo $logo_url ? '' : 'display:none;'; ?>" />
	</div>
	<input type="hidden" name="pam_site_logo" id="pam-site-logo" value="<?php echo $logo_url; ?>" />
	<p>
		<button type="button" class="button" id="pam-upload-logo"><?php esc_html_e( 'Select Logo', PAM_TEXT_DOMAIN ); ?></button>
		<button type="button" class="button" id="pam-remove-logo"><?php esc_html_e( 'Remove', PAM_TEXT_DOMAIN ); ?></button>
	</p>
	<script>
		jQuery(document).ready(function($) {
			const pamSiteLogoL10n = <?php echo $media_strings; ?>;
			const frame = wp.media({
				title: pamSiteLogoL10n.selectLogo,
				button: { text: pamSiteLogoL10n.useLogo },
				multiple: false
			});

			$('#pam-upload-logo').on('click', function(e) {
				e.preventDefault();
				frame.open();
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					$('#pam-site-logo').val(attachment.url);
					$('#pam-site-logo-preview').attr('src', attachment.url).show();
				});
			});

			$('#pam-remove-logo').on('click', function(e) {
				e.preventDefault();
				$('#pam-site-logo').val('');
				$('#pam-site-logo-preview').hide();
			});
		});
	</script>
	<?php
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( $hook === 'settings_page_public-art-map' ) {
		wp_enqueue_media();
	}
});
