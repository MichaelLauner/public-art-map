<?php
/**
 * Transitional compatibility wrappers for the OOP refactor.
 */

defined( 'ABSPATH' ) || exit;

use PublicArtMap\Assets\AssetManager;
use PublicArtMap\Frontend\TemplateLoader;
use PublicArtMap\Plugin;
use PublicArtMap\Updates\PluginUpdater;

function pam_public_art_map(): ?Plugin {
	return Plugin::instance();
}

function pam_get_mapbox_asset_url( string $asset ): string {
	$plugin = pam_public_art_map();
	if ( ! $plugin ) {
		return '';
	}

	$asset_manager = $plugin->getService( AssetManager::class );
	return $asset_manager instanceof AssetManager ? $asset_manager->getMapboxAssetUrl( $asset ) : '';
}

function pam_register_plugin_updates() {
	$plugin = pam_public_art_map();
	if ( ! $plugin ) {
		return null;
	}

	$updater = $plugin->getService( PluginUpdater::class );
	return $updater instanceof PluginUpdater ? $updater->registerPluginUpdates() : null;
}

function pam_load_custom_map_template( string $template ): string {
	$plugin = pam_public_art_map();
	if ( ! $plugin ) {
		return $template;
	}

	$template_loader = $plugin->getService( TemplateLoader::class );
	return $template_loader instanceof TemplateLoader ? $template_loader->loadCustomMapTemplate( $template ) : $template;
}
