<?php
add_filter( 'the_content', 'pam_add_gallery_modal_to_content' );
function pam_add_gallery_modal_to_content( $content ) {
	if ( ! is_singular( 'map_location' ) ) {
		return $content;
	}

	$featured_image_id = get_post_thumbnail_id( get_the_ID() );
	$gallery_ids = get_post_meta( get_the_ID(), 'pam_images', true ) ?: array();

	if ( $featured_image_id && ! in_array( $featured_image_id, $gallery_ids ) ) {
		array_unshift( $gallery_ids, $featured_image_id );
	}

	ob_start();

	// Gallery section
	if ( ! empty( $gallery_ids ) ) : ?>
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

		<div class="pam-gallery-modal" id="pamGalleryModal">
			<button class="pam-close" id="pamModalClose">&times;</button>
			<button class="pam-nav prev" id="pamModalPrev">&#8249;</button>
			<img id="pamModalImage" src="" alt="">
			<button class="pam-nav next" id="pamModalNext">&#8250;</button>
		</div>
	<?php endif;

	// Meta fields
	$artist   = get_post_meta( get_the_ID(), 'pam_artist', true );
	$address  = get_post_meta( get_the_ID(), 'pam_address', true );
	$city     = get_post_meta( get_the_ID(), 'pam_city', true );
	$state    = get_post_meta( get_the_ID(), 'pam_state', true );
	$zip      = get_post_meta( get_the_ID(), 'pam_zip', true );

	// Taxonomies
	$types = wp_get_post_terms( get_the_ID(), 'artwork_type', array( 'fields' => 'names' ) );
	$collections = wp_get_post_terms( get_the_ID(), 'artwork_collection', array( 'fields' => 'names' ) );
	?>

	<div class="pam-project-meta">
		<?php if ( $artist ) : ?><p><strong>Artist:</strong> <?php echo esc_html( $artist ); ?></p><?php endif; ?>
		<?php if ( $address || $city || $state || $zip ) : ?>
			<p><strong>Location:</strong>
				<?php
					$parts = array_filter([ $address, $city, $state, $zip ]);
					echo esc_html( implode( ', ', $parts ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $types ) ) : ?>
			<p><strong>Type:</strong> <?php echo esc_html( implode( ', ', $types ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $collections ) ) : ?>
			<p><strong>Collection:</strong> <?php echo esc_html( implode( ', ', $collections ) ); ?></p>
		<?php endif; ?>
	</div>

	<?php
	// Combine all and append WordPress content
	$full_content = ob_get_clean() . $content;

	// Add a "Back to Map" link
	$map_page_id = get_option( 'pam_map_page' );
	if ( $map_page_id ) {
		$map_url = get_permalink( $map_page_id );
		$full_content .= '<p class="pam-back-link"><a href="' . esc_url( $map_url ) . '">&larr; Back to Public Art Map</a></p>';
	}

	return $full_content;
}
