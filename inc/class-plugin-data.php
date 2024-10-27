<?php
/**
 * Utility class for accessing WordPress plugin data.
 *
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

/**
 * Utility class for accessing WordPress plugin data.
 *
 * @property-read string $File The plugin file.
 * @property-read string $Base The plugin base name.
 * @property-read string $Name The plugin name.
 * @property-read string $PluginURI The plugin URI.
 * @property-read string $Version The plugin version.
 * @property-read string $Description The plugin description.
 * @property-read string $Author The plugin author.
 * @property-read string $AuthorURI The plugin author uri.
 * @property-read string $TextDomain The plugin text domain
 * @property-read string $DomainPath The local path to the plugin text domain.
 * @property-read bool $Network
 * @property-read string $RequiresWP The required PHP version for this plugin.
 * @property-read string $RequiresPHP The required WordPress version for this plugin.
 * @property-read string|bool $UpdateURI The plugin update uri.
 * @property-read string[] $RequiresPlugins The plugins required by this plugin to run.
 * @property-read string $Title Alias for Name
 * @property-read string $AuthorName Alias for Author
 */
class Plugin_Data {
	/**
	 * The plugin file.
	 *
	 * @var string
	 */
	protected string $plugin_file;

	/**
	 * The plugin base name.
	 *
	 * @var string
	 */
	protected string $plugin_base;

	/**
	 * Plugin data cache.
	 *
	 * @var array[]
	 */
	protected static array $cache = [];

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file The plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_base = plugin_basename( $plugin_file );
	}

	/**
	 * Object access to plugin properties.
	 *
	 * @param string $property The plugin property to get the value for.
	 */
	public function __get( string $property ): mixed {
		return $this->get_plugin_property( $property );
	}

	/**
	 * Wrapper to safely call the WordPress get_plugin_data() function.
	 *
	 * @return array The plugin data.
	 */
	public function get_plugin_data(): array {
		if ( empty( array_key_exists( $this->plugin_base, static::$cache ) ) ) {
			if ( empty( function_exists( 'get_plugin_data' ) ) ) {
				if ( empty( is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) ) {
					return [];
				}

				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			static::$cache[ $this->plugin_base ] = get_plugin_data( $this->plugin_file, false, false );
		}

		return static::$cache[ $this->plugin_base ];
	}

	/**
	 * Gets the value of a plugin property.
	 *
	 * @param string $property The plugin property to get the value for.
	 */
	public function get_plugin_property( string $property ): mixed {
		if ( 'File' === $property ) {
			return $this->plugin_file;
		}

		if ( 'Base' === $property ) {
			return $this->plugin_base;
		}

		$data  = $this->get_plugin_data();
		$value = $data[ $property ] ?? '';

		return match ( $property ) {
			'RequiresPlugins' => Utils::string_to_array( $value ),
			default           => $value,
		};
	}
}
