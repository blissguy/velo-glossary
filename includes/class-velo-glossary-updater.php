<?php
/**
 * GitHub release update checks for Velo Glossary.
 *
 * @package Velo_Glossary
 */

defined( 'ABSPATH' ) || exit;

class Velo_Glossary_Updater {
	const REPOSITORY_URL = 'https://github.com/blissguy/velo-glossary/';
	const SLUG           = 'velo-glossary';

	/**
	 * Plugin Update Checker instance.
	 *
	 * @var object|null
	 */
	protected $update_checker;

	/**
	 * Register the GitHub update checker.
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$library_file = dirname( __DIR__ ) . '/lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $library_file ) ) {
			return;
		}

		require_once $library_file;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::REPOSITORY_URL,
			$plugin_file,
			self::SLUG
		);

		$this->update_checker->setBranch( 'main' );
		$this->update_checker->getVcsApi()->enableReleaseAssets( '/^velo-glossary-[0-9A-Za-z_.-]+\.zip($|[?&#])/i' );
	}
}
