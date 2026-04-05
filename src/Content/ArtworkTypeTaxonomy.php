<?php
namespace PublicArtMap\Content;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class ArtworkTypeTaxonomy implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'registerTaxonomy' ) );
		add_action( 'artwork_type_edit_form_fields', array( $this, 'renderColorField' ), 10, 2 );
		add_action( 'artwork_type_edit_form_fields', array( $this, 'renderIconField' ), 10, 2 );
		add_action( 'edited_artwork_type', array( $this, 'saveColorField' ), 10, 2 );
		add_action( 'edited_artwork_type', array( $this, 'saveIconField' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
		add_action( 'admin_footer', array( $this, 'renderColorPickerScript' ) );
	}

	public function registerTaxonomy(): void {
		$text_domain = $this->context->textDomain();

		$labels = array(
			'name'              => __( 'Artwork Types', $text_domain ),
			'singular_name'     => __( 'Artwork Type', $text_domain ),
			'search_items'      => __( 'Search Artwork Types', $text_domain ),
			'all_items'         => __( 'All Artwork Types', $text_domain ),
			'parent_item'       => __( 'Parent Artwork Type', $text_domain ),
			'parent_item_colon' => __( 'Parent Artwork Type:', $text_domain ),
			'edit_item'         => __( 'Edit Artwork Type', $text_domain ),
			'update_item'       => __( 'Update Artwork Type', $text_domain ),
			'add_new_item'      => __( 'Add New Artwork Type', $text_domain ),
			'new_item_name'     => __( 'New Artwork Type Name', $text_domain ),
			'menu_name'         => __( 'Artwork Types', $text_domain ),
		);

		register_taxonomy(
			'artwork_type',
			array( 'map_location' ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'artwork-type' ),
				'show_in_rest'      => true,
			)
		);
	}

	public function renderColorField( $term, $taxonomy ): void {
		$color = get_term_meta( $term->term_id, 'pam_color', true );
		?>
		<tr class="form-field term-color-wrap">
			<th scope="row"><label for="pam_color"><?php esc_html_e( 'Color', $this->context->textDomain() ); ?></label></th>
			<td>
				<input name="pam_color" id="pam_color" type="text" value="<?php echo esc_attr( $color ); ?>" class="color-picker" />
				<p class="description"><?php esc_html_e( 'Hex color code for the map marker (for example, #FF0000).', $this->context->textDomain() ); ?></p>
				<?php wp_nonce_field( 'pam_edit_artwork_type_meta', 'pam_artwork_type_nonce' ); ?>
			</td>
		</tr>
		<?php
	}

	public function renderIconField( $term, $taxonomy ): void {
		$icon_id   = get_term_meta( $term->term_id, 'pam_icon', true );
		$image_url = $icon_id ? wp_get_attachment_url( $icon_id ) : '';
		?>
		<tr class="form-field term-icon-wrap">
			<th scope="row"><label for="pam_icon"><?php esc_html_e( 'Center Icon', $this->context->textDomain() ); ?></label></th>
			<td>
				<input type="hidden" name="pam_icon" id="pam_icon" value="<?php echo esc_attr( $icon_id ); ?>">
				<img id="pam_icon_preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width: 50px; height: auto; display: <?php echo $image_url ? 'inline-block' : 'none'; ?>; margin-right: 10px;">
				<button class="button pam-icon-upload"><?php esc_html_e( 'Upload', $this->context->textDomain() ); ?></button>
				<p class="description"><?php esc_html_e( 'Optional small PNG icon (ideally 24x24) to show in the center of the pin.', $this->context->textDomain() ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function saveColorField( $term_id, $tt_id ): void {
		if ( ! $this->canSaveTermMeta( 'artwork_type', 'pam_artwork_type_nonce', 'pam_edit_artwork_type_meta' ) ) {
			return;
		}

		if ( isset( $_POST['pam_color'] ) ) {
			update_term_meta( $term_id, 'pam_color', sanitize_hex_color( wp_unslash( $_POST['pam_color'] ) ) );
		}
	}

	public function saveIconField( $term_id, $tt_id ): void {
		if ( ! $this->canSaveTermMeta( 'artwork_type', 'pam_artwork_type_nonce', 'pam_edit_artwork_type_meta' ) ) {
			return;
		}

		if ( isset( $_POST['pam_icon'] ) ) {
			update_term_meta( $term_id, 'pam_icon', intval( wp_unslash( $_POST['pam_icon'] ) ) );
		}
	}

	public function enqueueAdminAssets( string $hook ): void {
		if ( ! isset( $_GET['taxonomy'] ) || 'artwork_type' !== sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) ) {
			return;
		}

		if ( 'edit-tags.php' === $hook || 'term.php' === $hook ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_media();
			wp_add_inline_script(
				'jquery-core',
				'window.pamArtworkTypeL10n = ' . wp_json_encode(
					array(
						'selectIcon'   => __( 'Select Icon', $this->context->textDomain() ),
						'useThisImage' => __( 'Use this image', $this->context->textDomain() ),
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
JS
			);
		}
	}

	public function renderColorPickerScript(): void {
		if ( ! isset( $_GET['taxonomy'] ) || 'artwork_type' !== sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) ) {
			return;
		}
		?>
		<script>
			jQuery(document).ready(function($){
				$('.color-picker').wpColorPicker();
			});
		</script>
		<?php
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
