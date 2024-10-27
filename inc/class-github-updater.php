<?php
/**
 * Enables automated plugin updates from a GitHub repository.
 * This class was inspired by the article "How To Deploy WordPress Plugins With GitHub Using Transients"
 * by Matthew Ray.
 *
 * @see https://www.smashingmagazine.com/2015/08/deploy-wordpress-plugins-with-github-using-transients/
 * @see http://www.matthewray.com/
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

use WP_Error;
use WP_Upgrader;

/**
 * Enables automated plugin updates from a GitHub repository.
 */
class Github_Updater {
	/**
	 * @var object|false|null The latest release from the GitHub repository.
	 */
	protected object|false|null $release;

	protected string $plugin_file;
	protected string $repository_url;

	protected string $repository_token;

	/**
	 * Constructor.
	 */
	public function __construct( string $plugin_file, string $repository_token ) {
		$this->plugin_file      = $plugin_file;
		$this->repository_token = $repository_token;

		$this->init();;
	}

	/**
	 * Activates automated plugin update.
	 */
	public function init(): bool {
		$this->repository_url = static::get_repository_url( $this->plugin_file );

		if ( $this->repository_url ) {
			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'update_plugins' ] );
			add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
			add_filter( 'upgrader_pre_download', [ $this, 'upgrader_pre_download' ], 10, 4 );
			add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );
			return true;
		}
		return false;
	}

	/* -------------------------------------------------------------------------
	 * WordPress callbacks
	 * ---------------------------------------------------------------------- */

	/**
	 * Checks if a newer version of this plugin is avaialble on GitHub.
	 *
	 * @param object $transient Contains plugin update states.
	 *
	 * @return object the modified object.
	 */
	public function update_plugins( object $transient ) {

		//	Did WordPress check for updates?
		if ( property_exists( $transient, 'checked' ) && $transient->checked ) {
			$release = $this->get_latest_release();

			//	Do we have a valid release object?
			if ( empty( is_wp_error( $release ) ) && is_object( $release ) ) {
				$base = plugin_basename( $this->plugin_file );

				$plugin_data     = (object) static::get_plugin_data( $this->plugin_file );
				$release_version = trim( $release->tag_name, 'v' );

				// Is a newer version available on GitHub?
				if ( version_compare( $release_version, $plugin_data->Version, '>' ) ) {
					$transient->response[ $base ] = (object) [
						'url'          => $this->repository_url,
						'slug'         => current( explode( '/', $base ) ),
						'plugin'       => $base,
						'package'      => $release->zipball_url,
						'new_version'  => $release_version,
						'requires_php' => $plugin_data->RequiresPHP,
					];
				}
			}
		}
		return $transient;
	}

	/**
	 * Displays plugin version details.
	 *
	 * @param array|false|object $result The result object or array
	 * @param string $action The type of information being requested from the Plugin Installation API.
	 * @param object $args Plugin API arguments.
	 *
	 * @return false|object Plugin information
	 */
	public function plugins_api( $result, $action, $args ) {
		$base = plugin_basename( Plugin::FILE );
		$slug = current( explode( '/', $base ) );

		if ( property_exists( $args, 'slug' ) && $args->slug == $slug ) {
			$plugin  = (object) get_plugin_data( Plugin::FILE );
			$release = $this->get_latest_release();

			return (object) [
				'name'              => $plugin->Name,
				'slug'              => $slug,
				'plugin'            => $base,
				'version'           => trim( $release->tag_name, 'v' ),
				'author'            => $plugin->AuthorName,
				'author_profile'    => $plugin->AuthorURI,
				'last_updated'      => $release->published_at,
				'homepage'          => $plugin->PluginURI,
				'short_description' => $plugin->Description,
				'sections'          => [
					'Description' => $plugin->Description,
					'Updates'     => $release->body,
				],
				'download_link'     => $release->zipball_url,
			];
		}
		return $result;
	}

	/**
	 * Adds an authorisation header for private GitHub repositories.
	 * You can create a "Personal access token" in GitHub.
	 *
	 * @param bool $reply Whether to bail without returning the package. Default false.
	 * @param string $package The package file name.
	 * @param WP_Upgrader $upgrader The WP_Upgrader instance.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return bool The modified reply.
	 */
	public function upgrader_pre_download( $reply, $package, $upgrader, $hook_extra ) {
		$plugin = $hook_extra['plugin'] ?? null;
		$base   = plugin_basename( Plugin::FILE );

		if ( $base === $plugin && $token = get_option( self::OPTION_REPOSITORY_TOKEN ) ) {
			add_filter( 'http_request_args', function( $args, $url ) use ( $package, $token ) {
				if ( isset( $args['filename'] ) && $url === $package ) {
					$args['headers']['Authorization'] = "token {$token}";
				}
				return $args;
			}, 10, 2 );
		}

		return $reply;
	}

	/**
	 * Moves the source file location for the upgrade package.
	 *
	 * @param string $source File source location.
	 * @param string $remote_source Remote file source location.
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return string The modified source file location.
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		$plugin = $hook_extra['plugin'] ?? null;
		$base   = plugin_basename( Plugin::FILE );

		//	Set plugin slug and move source accordingly
		if ( $base === $plugin ) {
			$slug   = current( explode( '/', $base ) );
			$target = trailingslashit( dirname( $source ) ) . $slug;
			$wp_filesystem->move( $source, $target );

			return trailingslashit( $target );
		}
		return $source;
	}

	/* -------------------------------------------------------------------------
	 * Utils
	 * ---------------------------------------------------------------------- */

	public static function get_plugin_data( string $plugin_file ): array {
		$base = plugin_basename( $plugin_file );
		$data = wp_cache_get( 'plugin_data', $base );

		if ( $data && is_array( $data ) ) {
			return $data;
		}

		if ( empty( function_exists( 'get_plugin_data' ) ) ) {
			if ( empty( defined( 'ABSPATH' ) && ABSPATH ) ) {
				return [];
			}

			if ( empty( is_readable( ABSPATH . '/wp-admin/includes/plugin.php' ) ) ) {
				return [];
			}

			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $plugin_file, false, false );
		wp_cache_set( 'plugin_data', $data, $base );
		return $data;
	}

	public static function get_repository_url( string $plugin_file ): string {
		$plugin_data = static::get_plugin_data( $plugin_file );
		return $plugin_data['UpdateURI'] ?? '';
	}

	public static function get_current_version( string $plugin_file ): string {
		$plugin_data = static::get_plugin_data( $plugin_file );
		return $plugin_data['Version'] ?? '';
	}

	/**
	 * Gets the latest release of this plugin on GitHub.
	 *
	 * @return bool|object|WP_Error The latest release of this plugin on GitHub.
	 */
	public function get_latest_release() {
		if ( is_null( $this->release ) ) {
			$this->release = false;

			$repo = parse_url( $this->repository_url );
			$host = trim( $repo['host'] ?? null );
			$path = trim( $repo['path'] ?? null, '/' );
			$args = [];

			if ( 'github.com' == $host && strpos( $path, '/' ) ) {
				if ( $token = $this->repository_token ) {
					$args['headers']['Authorization'] = "token {$token}";
				}

				$request  = "https://api.github.com/repos/{$path}/releases";
				$response = wp_remote_get( $request, $args );
				$status   = wp_remote_retrieve_response_code( $response );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				if ( $status && $status < 400 ) {
					$releases = json_decode( wp_remote_retrieve_body( $response ) );
					$releases = array_filter( (array) $releases, function( $release ) {
						return isset( $release->draft ) && false === $release->draft;
					} );

					$this->release = current( $releases );
				}
			}
		}
		return $this->release;
	}
}
