<?php
namespace PublicArtMap\Content;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class ArtworkCollectionTaxonomy implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'registerTaxonomy' ) );
		add_action( 'artwork_collection_edit_form_fields', array( $this, 'renderColorField' ), 10, 2 );
		add_action( 'edited_artwork_collection', array( $this, 'saveColorField' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
	}

	public function registerTaxonomy(): void {
		$text_domain = $this->context->textDomain();

		$labels = array(
			'name'              => __( 'Collections', $text_domain ),
			'singular_name'     => __( 'Collection', $text_domain ),
			'search_items'      => __( 'Search Collections', $text_domain ),
			'all_items'         => __( 'All Collections', $text_domain ),
			'parent_item'       => __( 'Parent Collection', $text_domain ),
			'parent_item_colon' => __( 'Parent Collection:', $text_domain ),
			'edit_item'         => __( 'Edit Collection', $text_domain ),
			'update_item'       => __( 'Update Collection', $text_domain ),
			'add_new_item'      => __( 'Add New Collection', $text_domain ),
			'new_item_name'     => __( 'New Collection Name', $text_domain ),
			'menu_name'         => __( 'Collections', $text_domain ),
		);

		register_taxonomy(
			'artwork_collection',
			array( 'map_location' ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'artwork-collection' ),
				'show_in_rest'      => true,
			)
		);
	}

	public function renderColorField( $term, $taxonomy ): void {
		$color = get_term_meta( $term->term_id, 'pam_color', true );
		?>
		<tr class="form-field term-color-wrap">
			<th scope="row"><label for="pam_color"><?php esc_html_e( 'Marker Color', $this->context->textDomain() ); ?></label></th>
			<td>
				<input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
				<p class="description"><?php esc_html_e( 'Hex color code for the map marker (for example, #FF0000).', $this->context->textDomain() ); ?></p>
				<?php wp_nonce_field( 'pam_edit_artwork_collection_meta', 'pam_artwork_collection_nonce' ); ?>
			</td>
		</tr>
		<?php
	}

	public function saveColorField( $term_id, $tt_id ): void {
		if ( ! $this->canSaveTermMeta( 'artwork_collection', 'pam_artwork_collection_nonce', 'pam_edit_artwork_collection_meta' ) ) {
			return;
		}

		if ( isset( $_POST['pam_color'] ) ) {
			update_term_meta( $term_id, 'pam_color', sanitize_hex_color( wp_unslash( $_POST['pam_color'] ) ) );
		}
	}

	public function enqueueAdminAssets( string $hook ): void {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['taxonomy'] ) || 'artwork_collection' !== sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			"jQuery(function($){ $('#pam_color').wpColorPicker(); });"
		);
	}

	private function canSaveTermMeta( string $taxonomy, string $field, string $action ): bool {
		if ( ! isset( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || empty( $taxonomy_object->cap->manage_terms ) ) {
			return false;
		}

		return current_user_can( $taxonomy_object->cap->manage_terms );
	}
}
