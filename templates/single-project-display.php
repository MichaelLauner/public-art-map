<?php
add_filter( 'the_content', 'pam_add_gallery_modal_to_content' );
function pam_add_gallery_modal_to_content( $content ) {

	if ( ! is_singular( 'map_location' ) ) {
		return $content;
	}

	// Featured Image ID
	$featured_image_id = get_post_thumbnail_id( get_the_ID() );

	// Gallery Image IDs
	$gallery_ids = get_post_meta( get_the_ID(), 'pam_gallery_images', true ) ?: array();

	// Ensure the featured image is included in the gallery
	if ( $featured_image_id && ! in_array( $featured_image_id, $gallery_ids ) ) {
		array_unshift( $gallery_ids, $featured_image_id );
	}

	// If no gallery images are set, return the content as is
	if ( empty( $gallery_ids ) ) {
		return $content;
	}

	ob_start();
	?>
	<div class="pam-gallery">
		<?php foreach ( $gallery_ids as $attachment_id ) :
			$thumb = wp_get_attachment_image_src( $attachment_id, 'medium' );
			$full  = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( ! $thumb || ! $full ) continue;
			?>
			<img src="<?php echo esc_url( $thumb[0] ); ?>" 
				 data-full="<?php echo esc_url( $full[0] ); ?>" 
				 alt="" 
				 class="pam-gallery-thumb" />
		<?php endforeach; ?>
	</div>

	<div id="pam-gallery-modal" class="pam-gallery-modal">
		<span class="pam-gallery-close">&times;</span>
		<img class="pam-gallery-full" src="" alt="">
	</div>
	<?php
	$gallery_html = ob_get_clean();
	return $content . $gallery_html;
}
