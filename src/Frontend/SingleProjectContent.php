<?php
namespace PublicArtMap\Frontend;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class SingleProjectContent implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_filter( 'the_content', array( $this, 'filterContent' ) );
	}

	public function filterContent( string $content ): string {
		if ( ! is_singular( 'map_location' ) ) {
			return $content;
		}

		$featured_image_id = get_post_thumbnail_id( get_the_ID() );
		$gallery_ids       = get_post_meta( get_the_ID(), 'pam_images', true ) ?: array();

		if ( $featured_image_id && ! in_array( $featured_image_id, $gallery_ids, true ) ) {
			array_unshift( $gallery_ids, $featured_image_id );
		}

		ob_start();

		if ( ! empty( $gallery_ids ) ) :
			?>
			<div class="pam-gallery">
				<?php
				foreach ( $gallery_ids as $attachment_id ) :
					$thumb = wp_get_attachment_image_src( $attachment_id, 'medium' );
					$full  = wp_get_attachment_image_src( $attachment_id, 'large' );
					if ( ! $thumb || ! $full ) {
						continue;
					}
					?>
					<img src="<?php echo esc_url( $thumb[0] ); ?>" data-full="<?php echo esc_url( $full[0] ); ?>" alt="" class="pam-gallery-thumb" />
				<?php endforeach; ?>
			</div>

			<div class="pam-gallery-modal" id="pamGalleryModal">
				<button class="pam-close" id="pamModalClose">&times;</button>
				<button class="pam-nav prev" id="pamModalPrev">&#8249;</button>
				<img id="pamModalImage" src="" alt="">
				<button class="pam-nav next" id="pamModalNext">&#8250;</button>
			</div>
			<?php
		endif;

		$artist      = get_post_meta( get_the_ID(), 'pam_artist', true );
		$address     = get_post_meta( get_the_ID(), 'pam_address', true );
		$city        = get_post_meta( get_the_ID(), 'pam_city', true );
		$state       = get_post_meta( get_the_ID(), 'pam_state', true );
		$zip         = get_post_meta( get_the_ID(), 'pam_zip', true );
		$types       = wp_get_post_terms( get_the_ID(), 'artwork_type', array( 'fields' => 'names' ) );
		$collections = wp_get_post_terms( get_the_ID(), 'artwork_collection', array( 'fields' => 'names' ) );
		?>

		<div class="pam-project-meta">
			<?php if ( $artist ) : ?><p><strong><?php esc_html_e( 'Artist:', $this->context->textDomain() ); ?></strong> <?php echo esc_html( $artist ); ?></p><?php endif; ?>
			<?php if ( $address || $city || $state || $zip ) : ?>
				<p><strong><?php esc_html_e( 'Location:', $this->context->textDomain() ); ?></strong>
					<?php
					$parts = array_filter( array( $address, $city, $state, $zip ) );
					echo esc_html( implode( ', ', $parts ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $types ) ) : ?>
				<p><strong><?php esc_html_e( 'Type:', $this->context->textDomain() ); ?></strong> <?php echo esc_html( implode( ', ', $types ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $collections ) ) : ?>
				<p><strong><?php esc_html_e( 'Collection:', $this->context->textDomain() ); ?></strong> <?php echo esc_html( implode( ', ', $collections ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php
		$full_content = ob_get_clean() . $content;
		$map_page_id  = get_option( 'pam_map_page' );
		if ( $map_page_id ) {
			$map_url        = get_permalink( $map_page_id );
			$full_content .= '<p class="pam-back-link"><a href="' . esc_url( $map_url ) . '">&larr; ' . esc_html__( 'Back to Public Art Map', $this->context->textDomain() ) . '</a></p>';
		}

		return $full_content;
	}
}
