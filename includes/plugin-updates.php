<?php
/**
 * GitHub-based plugin update support.
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( file_exists( PAM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once PAM_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	/**
	 * Register plugin updates from the public GitHub repository.
	 *
	 * Releases are preferred, with a ZIP asset used when available.
	 */
	function pam_register_plugin_updates() {
		static $update_checker = null;

		if ( null !== $update_checker ) {
			return $update_checker;
		}

		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/MichaelLauner/public-art-map/',
			PAM_PLUGIN_FILE,
			'public-art-map'
		);

		$update_checker->setBranch( 'main' );
		$update_checker->getVcsApi()->enableReleaseAssets( '/public-art-map(?:-[0-9A-Za-z._-]+)?\\.zip($|[?&#])/i' );

		return $update_checker;
	}

	pam_register_plugin_updates();
}
