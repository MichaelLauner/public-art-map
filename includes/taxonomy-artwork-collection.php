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
        'name'              => __( 'Collections', PAM_TEXT_DOMAIN ),
        'singular_name'     => __( 'Collection', PAM_TEXT_DOMAIN ),
        'search_items'      => __( 'Search Collections', PAM_TEXT_DOMAIN ),
        'all_items'         => __( 'All Collections', PAM_TEXT_DOMAIN ),
        'parent_item'       => __( 'Parent Collection', PAM_TEXT_DOMAIN ),
        'parent_item_colon' => __( 'Parent Collection:', PAM_TEXT_DOMAIN ),
        'edit_item'         => __( 'Edit Collection', PAM_TEXT_DOMAIN ),
        'update_item'       => __( 'Update Collection', PAM_TEXT_DOMAIN ),
        'add_new_item'      => __( 'Add New Collection', PAM_TEXT_DOMAIN ),
        'new_item_name'     => __( 'New Collection Name', PAM_TEXT_DOMAIN ),
        'menu_name'         => __( 'Collections', PAM_TEXT_DOMAIN ),
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
        <th scope="row"><label for="pam_color"><?php esc_html_e( 'Marker Color', PAM_TEXT_DOMAIN ); ?></label></th>
        <td>
            <input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
            <p class="description"><?php esc_html_e( 'Hex color code for the map marker (for example, #FF0000).', PAM_TEXT_DOMAIN ); ?></p>
            <?php wp_nonce_field( 'pam_edit_artwork_collection_meta', 'pam_artwork_collection_nonce' ); ?>
        </td>
    </tr>
    <?php
}

/**
 * Save Color field on term update
 */
add_action( 'edited_artwork_collection', 'pam_save_collection_color_field', 10, 2 );
function pam_save_collection_color_field( $term_id, $tt_id ) {
    if ( ! pam_can_save_term_meta( 'artwork_collection', 'pam_artwork_collection_nonce', 'pam_edit_artwork_collection_meta' ) ) {
        return;
    }

    if ( isset( $_POST['pam_color'] ) ) {
        update_term_meta( $term_id, 'pam_color', sanitize_hex_color( wp_unslash( $_POST['pam_color'] ) ) );
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
