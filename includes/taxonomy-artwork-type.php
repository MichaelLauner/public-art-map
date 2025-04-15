<?php
add_action( 'init', 'pam_register_artwork_type_taxonomy' );

function pam_register_artwork_type_taxonomy() {
	$labels = array(
		'name'              => 'Artwork Types',
		'singular_name'     => 'Artwork Type',
		'search_items'      => 'Search Artwork Types',
		'all_items'         => 'All Artwork Types',
		'parent_item'       => 'Parent Artwork Type',
		'parent_item_colon' => 'Parent Artwork Type:',
		'edit_item'         => 'Edit Artwork Type',
		'update_item'       => 'Update Artwork Type',
		'add_new_item'      => 'Add New Artwork Type',
		'new_item_name'     => 'New Artwork Type Name',
		'menu_name'         => 'Artwork Types',
	);

	$args = array(
		'hierarchical'      => true, // Like categories (set false for tag-like)
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'artwork-type' ),
		'show_in_rest'      => true, // enables block editor and REST support
	);

	register_taxonomy( 'artwork_type', array( 'map_location' ), $args );
}

// Show color input on the term edit form
add_action( 'artwork_type_edit_form_fields', 'pam_edit_artwork_type_color_field', 10, 2 );
function pam_edit_artwork_type_color_field( $term, $taxonomy ) {
	$color = get_term_meta( $term->term_id, 'pam_color', true );
	?>
	<tr class="form-field term-color-wrap">
		<th scope="row"><label for="pam_color">Color</label></th>
		<td>
			<input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
			<p class="description">Hex color code for map marker (e.g. #FF0000)</p>
		</td>
	</tr>
	<?php
}

// Save color on term edit
add_action( 'edited_artwork_type', 'pam_save_artwork_type_color_field', 10, 2 );
function pam_save_artwork_type_color_field( $term_id, $tt_id ) {
	if ( isset( $_POST['pam_color'] ) ) {
		update_term_meta( $term_id, 'pam_color', sanitize_hex_color( $_POST['pam_color'] ) );
	}
}

add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
	if ( $hook_suffix === 'edit-tags.php' || $hook_suffix === 'term.php' ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}
});

add_action( 'admin_footer', function() {
	if ( isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'artwork_type' ) {
		?>
		<script>
			jQuery(document).ready(function($){
				$('.color-picker').wpColorPicker();
			});
		</script>
		<?php
	}
});

