<?php
add_action( 'init', 'pam_register_artwork_type_taxonomy' );

function pam_register_artwork_type_taxonomy() {
	$labels = array(
		'name'              => __( 'Artwork Types', PAM_TEXT_DOMAIN ),
		'singular_name'     => __( 'Artwork Type', PAM_TEXT_DOMAIN ),
		'search_items'      => __( 'Search Artwork Types', PAM_TEXT_DOMAIN ),
		'all_items'         => __( 'All Artwork Types', PAM_TEXT_DOMAIN ),
		'parent_item'       => __( 'Parent Artwork Type', PAM_TEXT_DOMAIN ),
		'parent_item_colon' => __( 'Parent Artwork Type:', PAM_TEXT_DOMAIN ),
		'edit_item'         => __( 'Edit Artwork Type', PAM_TEXT_DOMAIN ),
		'update_item'       => __( 'Update Artwork Type', PAM_TEXT_DOMAIN ),
		'add_new_item'      => __( 'Add New Artwork Type', PAM_TEXT_DOMAIN ),
		'new_item_name'     => __( 'New Artwork Type Name', PAM_TEXT_DOMAIN ),
		'menu_name'         => __( 'Artwork Types', PAM_TEXT_DOMAIN ),
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
		<th scope="row"><label for="pam_color"><?php esc_html_e( 'Color', PAM_TEXT_DOMAIN ); ?></label></th>
		<td>
			<input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
			<p class="description"><?php esc_html_e( 'Hex color code for the map marker (for example, #FF0000).', PAM_TEXT_DOMAIN ); ?></p>
			<?php wp_nonce_field( 'pam_edit_artwork_type_meta', 'pam_artwork_type_nonce' ); ?>
		</td>
	</tr>
	<?php
}

// Save color on term edit
add_action( 'edited_artwork_type', 'pam_save_artwork_type_color_field', 10, 2 );
function pam_save_artwork_type_color_field( $term_id, $tt_id ) {
	if ( ! pam_can_save_term_meta( 'artwork_type', 'pam_artwork_type_nonce', 'pam_edit_artwork_type_meta' ) ) {
		return;
	}

	if ( isset( $_POST['pam_color'] ) ) {
		update_term_meta( $term_id, 'pam_color', sanitize_hex_color( wp_unslash( $_POST['pam_color'] ) ) );
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

// Add image upload field to edit form
add_action( 'artwork_type_edit_form_fields', 'pam_edit_term_icon_field', 10, 2 );
function pam_edit_term_icon_field( $term, $taxonomy ) {
	$icon_id = get_term_meta( $term->term_id, 'pam_icon', true );
	$image_url = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
	?>
	<tr class="form-field term-icon-wrap">
		<th scope="row"><label for="pam_icon"><?php esc_html_e( 'Center Icon', PAM_TEXT_DOMAIN ); ?></label></th>
		<td>
			<input type="hidden" name="pam_icon" id="pam_icon" value="<?php echo esc_attr( $icon_id ); ?>">
			<img id="pam_icon_preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width: 50px; height: auto; display: <?php echo $image_url ? 'inline-block' : 'none'; ?>; margin-right: 10px;">
			<button class="button pam-icon-upload"><?php esc_html_e( 'Upload', PAM_TEXT_DOMAIN ); ?></button>
			<p class="description"><?php esc_html_e( 'Optional small PNG icon (ideally 24x24) to show in the center of the pin.', PAM_TEXT_DOMAIN ); ?></p>
		</td>
	</tr>
	<?php
}

// Save the field
add_action( 'edited_artwork_type', 'pam_save_term_icon_field', 10, 2 );
function pam_save_term_icon_field( $term_id, $tt_id ) {
	if ( ! pam_can_save_term_meta( 'artwork_type', 'pam_artwork_type_nonce', 'pam_edit_artwork_type_meta' ) ) {
		return;
	}

	if ( isset( $_POST['pam_icon'] ) ) {
		update_term_meta( $term_id, 'pam_icon', intval( wp_unslash( $_POST['pam_icon'] ) ) );
	}
}

// Enqueue media uploader
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'artwork_type' ) {
		wp_enqueue_media();
		wp_add_inline_script(
			'jquery-core',
			'window.pamArtworkTypeL10n = ' . wp_json_encode(
				array(
					'selectIcon'   => __( 'Select Icon', PAM_TEXT_DOMAIN ),
					'useThisImage' => __( 'Use this image', PAM_TEXT_DOMAIN ),
				)
			) . ';',
			'before'
		);
		wp_add_inline_script( 'jquery-core', <<<JS
			jQuery(document).ready(function($) {
				$('.pam-icon-upload').on('click', function(e) {
					e.preventDefault();
					var input = $(this).siblings('input#pam_icon');
					var preview = $(this).siblings('#pam_icon_preview');

					var frame = wp.media({
						title: pamArtworkTypeL10n.selectIcon,
						button: { text: pamArtworkTypeL10n.useThisImage },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						input.val(attachment.id);
						preview.attr('src', attachment.url).show();
					});
					frame.open();
				});
			});
		JS );
	}
});

/**
 * Validate the current artwork type term-meta save request.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param string $field    Nonce field name.
 * @param string $action   Nonce action.
 * @return bool
 */
function pam_can_save_term_meta( $taxonomy, $field, $action ) {
	if ( ! isset( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
		return false;
	}

	$taxonomy_object = get_taxonomy( $taxonomy );
	if ( ! $taxonomy_object || empty( $taxonomy_object->cap->manage_terms ) ) {
		return false;
	}

	return current_user_can( $taxonomy_object->cap->manage_terms );
}
