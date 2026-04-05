<?php
namespace PublicArtMap\Frontend;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;

class TemplateLoader implements Service {
	private PluginContext $context;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		add_filter( 'template_include', array( $this, 'loadCustomMapTemplate' ) );
	}

	public function loadCustomMapTemplate( string $template ): string {
		if ( is_admin() ) {
			return $template;
		}

		$map_page_id = get_option( 'pam_map_page' );
		if ( is_page( $map_page_id ) ) {
			return $this->context->templatePath( 'fullscreen-map-template.php' );
		}

		return $template;
	}
}
