<?php
namespace PublicArtMap\Updates;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class PluginUpdater implements Service {
	private PluginContext $context;

	private $update_checker;

	public function __construct( PluginContext $context ) {
		$this->context = $context;
	}

	public function register(): void {
		$this->registerPluginUpdates();
	}

	public function registerPluginUpdates() {
		if ( null !== $this->update_checker ) {
			return $this->update_checker;
		}

		$plugin_update_checker = $this->context->path( 'vendor/plugin-update-checker/plugin-update-checker.php' );
		if ( ! file_exists( $plugin_update_checker ) ) {
			return null;
		}

		require_once $plugin_update_checker;

		$this->update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/MichaelLauner/public-art-map/',
			$this->context->pluginFile(),
			'public-art-map'
		);

		$this->update_checker->setBranch( 'main' );
		$this->update_checker->getVcsApi()->enableReleaseAssets( '/public-art-map(?:-[0-9A-Za-z._-]+)?\\.zip($|[?&#])/i' );

		return $this->update_checker;
	}
}
