<?php
namespace PublicArtMap;

use PublicArtMap\Admin\AdminNoticeManager;
use PublicArtMap\Admin\SettingsPage;
use PublicArtMap\Assets\AssetManager;
use PublicArtMap\Cli\PamCommand;
use PublicArtMap\Content\ArtworkCollectionTaxonomy;
use PublicArtMap\Content\ArtworkTypeTaxonomy;
use PublicArtMap\Content\PostTypeRegistrar;
use PublicArtMap\Contracts\Service;
use PublicArtMap\Frontend\SingleProjectContent;
use PublicArtMap\Frontend\TemplateLoader;
use PublicArtMap\Infrastructure\PluginContext;
use PublicArtMap\Meta\MetaManager;
use PublicArtMap\Rest\RestController;
use PublicArtMap\Service\GeocodingService;
use PublicArtMap\Updates\PluginUpdater;

class Plugin {
	private static ?self $instance = null;

	private PluginContext $context;

	/** @var array<string, object> */
	private array $services = array();

	private function __construct( PluginContext $context ) {
		$this->context = $context;
		$this->buildServices();
	}

	public static function boot( PluginContext $context ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $context );
			self::$instance->register();
		}

		return self::$instance;
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	public function getContext(): PluginContext {
		return $this->context;
	}

	public function getService( string $class ) {
		return $this->services[ $class ] ?? null;
	}

	private function buildServices(): void {
		$notice_manager = new AdminNoticeManager();
		$geocoder       = new GeocodingService();

		$this->services = array(
			AdminNoticeManager::class        => $notice_manager,
			GeocodingService::class          => $geocoder,
			PluginUpdater::class             => new PluginUpdater( $this->context ),
			RestController::class            => new RestController( $this->context ),
			PostTypeRegistrar::class         => new PostTypeRegistrar( $this->context ),
			ArtworkTypeTaxonomy::class       => new ArtworkTypeTaxonomy( $this->context ),
			ArtworkCollectionTaxonomy::class => new ArtworkCollectionTaxonomy( $this->context ),
			AssetManager::class              => new AssetManager( $this->context ),
			SettingsPage::class              => new SettingsPage( $this->context, $geocoder ),
			MetaManager::class               => new MetaManager( $this->context, $geocoder, $notice_manager ),
			TemplateLoader::class            => new TemplateLoader( $this->context ),
			SingleProjectContent::class      => new SingleProjectContent( $this->context ),
			PamCommand::class                => new PamCommand( $this->context, $geocoder ),
		);
	}

	private function register(): void {
		add_action( 'plugins_loaded', array( $this, 'loadTextdomain' ) );

		foreach ( $this->services as $service ) {
			if ( $service instanceof Service ) {
				$service->register();
			}
		}
	}

	public function loadTextdomain(): void {
		load_plugin_textdomain(
			$this->context->textDomain(),
			false,
			dirname( $this->context->pluginBasename() ) . '/languages'
		);
	}
}
