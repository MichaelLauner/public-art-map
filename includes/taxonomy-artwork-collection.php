<?php
/**
 * Public Art Map – Artwork Collection Taxonomy + Color Picker
 *
 * @package Public Art Map
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Artwork Collection taxonomy
 */
add_action( 'init', 'pam_register_artwork_collection_taxonomy' );
function pam_register_artwork_collection_taxonomy() {
    $labels = array(
        'name'              => 'Artwork Collections',
        'singular_name'     => 'Artwork Collection',
        'search_items'      => 'Search Artwork Collections',
        'all_items'         => 'All Artwork Collections',
        'parent_item'       => 'Parent Collection',
        'parent_item_colon' => 'Parent Collection:',
        'edit_item'         => 'Edit Collection',
        'update_item'       => 'Update Collection',
        'add_new_item'      => 'Add New Collection',
        'new_item_name'     => 'New Collection Name',
        'menu_name'         => 'Artwork Collections',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'artwork-collection' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'artwork_collection', array( 'map_location' ), $args );
}

/**
 * Add Color field to term edit form
 */
add_action( 'artwork_collection_edit_form_fields', 'pam_edit_collection_color_field', 10, 2 );
function pam_edit_collection_color_field( $term, $taxonomy ) {
    $color = get_term_meta( $term->term_id, 'pam_color', true );
    ?>
    <tr class="form-field term-color-wrap">
        <th scope="row"><label for="pam_color">Marker Color</label></th>
        <td>
            <input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
            <p class="description">Hex color code for map marker (e.g. #FF0000)</p>
        </td>
    </tr>
    <?php
}

/**
 * Save Color field on term update
 */
add_action( 'edited_artwork_collection', 'pam_save_collection_color_field', 10, 2 );
function pam_save_collection_color_field( $term_id, $tt_id ) {
    if ( isset( $_POST['pam_color'] ) ) {
        update_term_meta( $term_id, 'pam_color', sanitize_hex_color( $_POST['pam_color'] ) );
    }
}

/**
 * Enqueue color‑picker assets on taxonomy screens
 */
add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
    if ( 'edit-tags.php' === $hook_suffix || 'term.php' === $hook_suffix ) {
        if ( isset( $_GET['taxonomy'] ) && 'artwork_collection' === $_GET['taxonomy'] ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_add_inline_script( 'wp-color-picker', "
                jQuery(function($){
                    $('#pam_color').wpColorPicker();
                });
            " );
        }
    }
});
