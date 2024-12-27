<?php
/**
 * Plugin admin setup.
 *
 * @author Per Egil Roksvaag
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * Plugin admin setup.
 */
class Admin {
	use Singleton;

	// The plugin settings page slug.
	const SETTINGS_PAGE_SLUG = 'peroks-basic-tools';

	// Section and option ids.
	const SECTION_GITHUB_UPDATER = Plugin::PREFIX . '/github-updater';
	const OPTION_GITHUB_TOKEN    = Plugin::PREFIX . '/github-token';

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'enable_github_updater' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Enable plugin update from GitHub.
	 */
	public function enable_github_updater(): void {
		$token = get_option( self::OPTION_GITHUB_TOKEN, '' );
		Github_Updater::create( Plugin::FILE, $token );
	}

	/**
	 * Creates a plugin settings page.
	 */
	public function admin_menu(): void {
		$plugin = Plugin_Data::create( Plugin::FILE );

		Settings_Page::register_page( self::SETTINGS_PAGE_SLUG, [
			'page_title'      => sprintf( '%s %s', $plugin->Name, __( 'Settings' ) ), // phpcs:ignore
			'menu_title'      => $plugin->Name,
			'plugin_basename' => $plugin->Base,
		] );
	}

	/**
	 * Adds option fields to the settings page.
	 */
	public function admin_init(): void {
		$page = Settings_Page::get_page( self::SETTINGS_PAGE_SLUG );

		if ( empty( $page ) ) {
			return;
		}

		$page->add_section( [
			'section' => self::SECTION_GITHUB_UPDATER,
			'label'   => __( 'Automated plugin update from a GitHub repository', 'peroks-basic-tools' ),
		] );

		$page->add_text( [
			'option'      => self::OPTION_GITHUB_TOKEN,
			'section'     => self::SECTION_GITHUB_UPDATER,
			'label'       => __( 'GitHub access token', 'peroks-basic-tools' ),
			'description' => __( 'Enter a GitHub access token for private repositories.', 'peroks-basic-tools' ),
		] );
	}
}
