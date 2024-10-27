<?php
/**
 * Plugin setup.
 *
 * @author Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

/**
 * Plugin setup.
 */
class Setup {
	use Singleton;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'load_translations' ] );

		if ( is_admin() ) {
			add_action( 'init', [ $this, 'init_github_updater' ] );
		} else {
			add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_styles' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		}
	}

	/**
	 * Activate plugin update from GitHun.
	 */
	public function init_github_updater(): void {
		Github_Updater::create( Plugin::FILE );
	}

	/**
	 * Loads the translated strings (if any).
	 */
	public function load_translations(): void {
		$path = dirname( plugin_basename( Plugin::FILE ) ) . '/languages';
		load_plugin_textdomain( 'peroks-basic-tools', false, $path );
	}

	/**
	 * Enqueues frontend styles.
	 */
	public function wp_enqueue_styles() {}

	/**
	 * Enqueues frontend scripts.
	 */
	public function wp_enqueue_scripts() {}
}
