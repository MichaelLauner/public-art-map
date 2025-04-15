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
