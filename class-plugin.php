<?php
/**
 * Plugin Name:       Basic Plugin Tools
 * Plugin URI:        https://github.com/peroks/peroks-basic-tools
 * Update URI:        https://github.com/peroks/peroks-basic-tools
 * Description:       Basic tools and classes for use in other WordPress plugins.
 *
 * Text Domain:       peroks-basic-tools
 * Domain Path:       /languages
 *
 * Author:            Per Egil Roksvaag
 * Author URI:        https://github.com/peroks
 *
 * Version:           0.1.0
 * Stable tag:        0.1.0
 * Requires at least: 6.6
 * Tested up to:      6.6
 * Requires PHP:      8.2
 */

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

require_once 'inc/trait-singleton.php';

/**
 * The plugin main class.
 */
class Plugin {
	use Singleton;

	/**
	 * The plugin version, should match the "Version" field in the plugin header.
	 *
	 * @var string The plugin version.
	 */
	const VERSION = '0.1.0';

	/**
	 * The plugin prefix, Use lowercase and underscores as word separator.
	 *
	 * @var string The plugin prefix (underscore).
	 */
	const PREFIX = 'peroks_basic_tools';

	/**
	 * The full path to this file.
	 *
	 * @var string The plugin file.
	 */
	const FILE = __FILE__;

	/**
	 * The plugin global filter hooks.
	 */
	const FILTER_CLASS_NAME     = self::PREFIX . '/class_name';
	const FILTER_CLASS_INSTANCE = self::PREFIX . '/class_instance';
	const FILTER_CLASS_PATHS    = self::PREFIX . '/class_paths';
	const FILTER_PLUGIN_PATH    = self::PREFIX . '/plugin_path';
	const FILTER_PLUGIN_URL     = self::PREFIX . '/plugin_url';

	/**
	 * The plugin global action hooks.
	 */
	const ACTION_CLASS_CREATED = self::PREFIX . '/class_created';

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->autoload();
		$this->run();
	}

	/**
	 * Registers autoloading.
	 */
	protected function autoload(): void {
		$classes = apply_filters( self::FILTER_CLASS_PATHS, [
			__NAMESPACE__ . '\\Setup' => static::path( 'inc/class-setup.php' ),
		] );

		spl_autoload_register( function( $name ) use ( $classes ) {
			if ( array_key_exists( $name, $classes ) ) {
				require $classes[ $name ];
			}
		} );
	}

	/**
	 * Loads and runs the plugin classes.
	 * You must register your classes for autoloading (above) before you can run them here.
	 */
	protected function run(): void {
		Setup::instance();
	}

	/**
	 * Gets a full filesystem path from a local path.
	 *
	 * @param string $path The local path relative to this plugin's root directory.
	 *
	 * @return string The full filesystem path.
	 */
	public static function path( string $path = '' ): string {
		$path = ltrim( trim( $path ), '/' );
		$full = plugin_dir_path( self::FILE ) . $path;
		return apply_filters( self::FILTER_PLUGIN_PATH, $full, $path );
	}

	/**
	 * Gets the URL to the given local path.
	 *
	 * @param string $path The local path relative to this plugin's root directory.
	 *
	 * @return string The URL.
	 */
	public static function url( string $path = '' ): string {
		$path = ltrim( trim( $path ), '/' );
		$url  = plugins_url( $path, self::FILE );
		return apply_filters( self::FILTER_PLUGIN_URL, $url, $path );
	}
}

// Registers and runs the main plugin class.
if ( defined( 'ABSPATH' ) ) {
	add_action( 'plugins_loaded', [ Plugin::class, 'instance' ] );
}
