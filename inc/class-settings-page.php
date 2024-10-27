<?php
/**
 * A helper class for creating and populating a simple settings page.
 *
 * @package finansforbundet
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

/**
 * A helper class for creating and populating a simple settings page.´
 */
class Settings_Page {
	/**
	 * The slug name of the settings page.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * The settings page properties.
	 *
	 * @var array
	 */
	protected array $props;

	/**
	 * An array of registered setting page instances.
	 *
	 * @var static[]
	 */
	protected static array $inst = [];

	/**
	 * Constructor.
	 *
	 * @param string $slug The settings page slug.
	 * @param array  $props The settings page properties.
	 */
	protected function __construct( string $slug, array $props = [] ) {
		$this->slug  = $slug;
		$this->props = wp_parse_args( $props, [
			'page_title'         => $slug,
			'menu_title'         => $slug,
			'parent_slug'        => 'options-general.php',
			'capability'         => 'manage_options',
			'load_page_callback' => null,
			'show_page_callback' => [ $this, 'show_page' ],
			'position'           => null,
			'plugin_basename'    => '',
		] );

		// Adds a top level or a submenu page to the admin menu (or both).
		if ( doing_action( 'admin_menu' ) ) {
			$this->add_sub_menu();
		} else {
			add_action( 'admin_menu', [ $this, 'add_sub_menu' ], 5 );
		}

		// Displays a "Settings" link on the Plugins page.
		if ( $this->props['plugin_basename'] ) {
			$name = $this->props['plugin_basename'];
			add_filter( "plugin_action_links_{$name}", [ $this, 'plugin_action_links' ] );
		}
	}

	/**
	 * Registers a settings page.
	 *
	 * @param string $slug The settings page slug.
	 * @param array  $props The settings page properties.
	 */
	public static function register_page( string $slug, array $props = [] ): static|null {
		if ( empty( $slug ) ) {
			return null;
		}

		if ( empty( array_key_exists( $slug, self::$inst ) ) ) {
			self::$inst[ $slug ] = new static( $slug, $props );
		}

		return self::$inst[ $slug ];
	}

	/**
	 * Gets the settings page instance with the given slug.
	 *
	 * @param string $slug The settings page slug.
	 */
	public static function get_page( string $slug ): static|null {
		return self::$inst[ $slug ] ?? null;
	}

	/**
	 * Checks if a settings page with the given slug has been registered.
	 *
	 * @param string $slug The settings page slug.
	 */
	public static function has_page( string $slug ): bool {
		return static::get_page( $slug ) instanceof self;
	}

	/**
	 * Gets the value of a settings page property.
	 *
	 * @param string $property The property name.
	 */
	public function get_page_property( string $property ): mixed {
		if ( 'page_slug' === $property || 'menu_slug' === $property ) {
			return $this->slug;
		}
		return $this->props[ $property ] ?? null;
	}

	/**
	 * Sets the value of a settings page property.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value The property value.
	 */
	public function set_page_property( string $property, mixed $value ): mixed {
		$this->props[ $property ] = $value;
		return $value;
	}

	/**
	 * Adds a submenu page to the admin menu.
	 */
	public function add_sub_menu(): void {
		$page = add_submenu_page( $this->get_page_property( 'parent_slug' ),
			$this->get_page_property( 'page_title' ),
			$this->get_page_property( 'menu_title' ),
			$this->get_page_property( 'capability' ),
			$this->slug,
			$this->get_page_property( 'show_page_callback' ),
			$this->get_page_property( 'position' ),
		);

		$load_page_callback = $this->get_page_property( 'load_page_callback' );

		if ( $load_page_callback ) {
			add_action( "load-{$page}", $load_page_callback );
		}
	}

	/**
	 * Displays a "Settings" link for this plugin on the Plugins page.
	 *
	 * @param array $actions An array of plugin action links.
	 *
	 * @return array Tme modified action links.
	 */
	public function plugin_action_links( array $actions ): array {
		array_unshift( $actions, vsprintf( '<a href="%s">%s</a>', [
			esc_url( menu_page_url( $this->slug, false ) ),
			esc_html__( 'Settings' ),
		] ) );

		return $actions;
	}

	/**
	 * Displays the settings page with content.
	 */
	public function show_page(): void {
		if ( current_user_can( $this->get_page_property( 'capability' ) ) ) {
			printf( '<div class="wrap">' );
			printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
			printf( '<form method="post" action="options.php">' );

			settings_fields( $this->slug );         // Group name.
			do_settings_sections( $this->slug );    // Menu page slug.
			submit_button();

			printf( '</form>' );
			printf( '</div>' );
		}
	}

	/**
	 * Adds a new section to an admin page.
	 *
	 * Wrapper for add_settings_section.
	 *
	 * @param array{
	 *     section: string,
	 *     label: string,
	 *     description: string|string[],
	 * } $args An array of arguments.
	 */
	public function add_section( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'section'     => '',
			'label'       => '',
			'description' => '',
		] );

		add_settings_section( $param->section, $param->label, function () use ( $param ) {
			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}
			echo wp_kses_post( $param->description );
		}, $this->slug );
	}

	/**
	 * Adds a checkbox to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 * } $args An array of arguments.
	 */
	public function add_checkbox( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => 0,
			'label'       => '',
			'description' => '',
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'integer',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( $value ) { //phpcs:ignore
				return $value ? 1 : 0;
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			vprintf( '<input type="checkbox" id="%s" name="%s" value="1" %s>', [
				esc_attr( $param->option ),
				esc_attr( $param->option ),
				checked( get_option( $param->option ), 1, false ),
			] );

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<span>%s</span>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Adds multiple checkboxes to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 *     terms: string[],
	 * } $args An array of arguments.
	 */
	public function add_multibox( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => 0,
			'label'       => '',
			'description' => '',
			'terms'       => [],
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'array',
			'default'           => [],
			'sanitize_callback' => $param->sanitize ?? function ( $value ): array { // phpcs:ignore
				return (array) $value;
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
			$value = get_option( $param->option ) ?: [];

			foreach ( $param->terms as $key => $label ) {
				$key   = is_string( $key ) ? $key : $label;
				$input = vsprintf( '<input type="checkbox" name="%s[]" value="%s"%s>', [
					esc_attr( $param->option ),
					esc_attr( $key ),
					in_array( $key, $value, true ) ? ' checked' : '',
				] );
				printf( '<p><label>%s %s</label></p> ', $input, esc_html( $label ) ); // phpcs:ignore
			}
		}, $this->slug, $param->section );
	}

	/**
	 * Adds a dropdown to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 *     terms: string[],
	 * } $args An array of arguments.
	 */
	public function add_dropdown( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => '',
			'label'       => '',
			'description' => '',
			'class'       => 'regular-text',
			'style'       => '',
			'terms'       => [],
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( $value ): string { // phpcs:ignore
				return sanitize_text_field( (string) $value );
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			$whitelist  = array_flip( [ 'maxlength', 'minlength', 'readonly', 'disabled' ] );
			$attributes = array_intersect_key( (array) $param, $whitelist );
			$value      = esc_attr( get_option( $param->option, $param->default ) );

			vprintf( '<select id="%s" class="%s" style="%s" name="%s"%s>', [
				esc_attr( $param->option ),
				esc_attr( $param->class ),
				esc_attr( $param->style ),
				esc_attr( $param->option ),
				$this->array_to_attr( $attributes ), //phpcs:ignore
			] );

			foreach ( $param->terms as $key => $label ) {
				$key = is_string( $key ) ? $key : $label;

				vprintf( '<option value="%s"%s>%s</option>', [
					esc_attr( $key ),
					selected( $key, $value, false ),
					esc_attr( $label ),
				] );
			}

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '</select>' );
			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Adds multiple radio buttons to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: string,
	 *     label: string,
	 *     description: string|string[],
	 *     terms: string[],
	 * } $args An array of arguments.
	 */
	public function add_radio( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => '',
			'label'       => '',
			'description' => '',
			'terms'       => [],
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'default'           => [],
			'sanitize_callback' => $param->sanitize ?? function ( $value ): string { // phpcs:ignore
				return (string) $value;
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
			$value = get_option( $param->option ) ?: '';

			foreach ( $param->terms as $key => $label ) {
				$key   = is_string( $key ) ? $key : $label;
				$input = vsprintf( '<input type="radio" name="%s" value="%s"%s>', [
					esc_attr( $param->option ),
					esc_attr( $key ),
					boolval( $key === $value ) ? ' checked' : '',
				] );
				printf( '<p><label>%s %s</label></p> ', $input, esc_html( $label ) ); // phpcs:ignore
			}
		}, $this->slug, $param->section, [] );
	}

	/**
	 * Adds a numeric input field to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 * } $args An array of arguments.
	 */
	public function add_number( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => 0,
			'label'       => '',
			'description' => '',
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'integer',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( $value ): int { // phpcs:ignore
				return (int) $value;
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			$whitelist  = array_flip( [ 'max', 'min', 'step', 'readonly', 'disabled' ] );
			$attributes = array_intersect_key( (array) $param, $whitelist );

			vprintf( '<input type="number" id="%s" class="small-text" name="%s" value="%d"%s>', [
				esc_attr( $param->option ),
				esc_attr( $param->option ),
				esc_attr( get_option( $param->option, $param->default ) ), //phpcs:ignore
				$this->array_to_attr( $attributes ), //phpcs:ignore
			] );

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( ' <span>%s</span>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Adds a text input field to a section on an admin page.
	 *
	 * Wrapper for register_setting and add_settings_field.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 * } $args An array of arguments.
	 */
	public function add_text( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => '',
			'label'       => '',
			'description' => '',
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( $value ): string { // phpcs:ignore
				return sanitize_text_field( trim( (string) $value ) );
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			$whitelist  = array_flip( [ 'maxlength', 'minlength', 'readonly', 'disabled' ] );
			$attributes = array_intersect_key( (array) $param, $whitelist );

			vprintf( '<input type="text" id="%s" class="regular-text" name="%s" value="%s"%s>', [
				esc_attr( $param->option ),
				esc_attr( $param->option ),
				esc_attr( get_option( $param->option, $param->default ) ), //phpcs:ignore
				$this->array_to_attr( $attributes ), //phpcs:ignore
			] );

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Adds a password input field to a section on an admin page.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: integer,
	 *     label: string,
	 *     description: string|string[],
	 * } $args An array of arguments.
	 */
	public function add_password( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => '',
			'label'       => '',
			'description' => '',
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( $value ): string { // phpcs:ignore
				return sanitize_text_field( trim( (string) $value ) );
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			$whitelist  = array_flip( [ 'maxlength', 'minlength', 'readonly', 'disabled' ] );
			$attributes = array_intersect_key( (array) $param, $whitelist );

			vprintf( '<input type="password" id="%s" class="regular-text" name="%s" value="%s"%s>', [
				esc_attr( $param->option ),
				esc_attr( $param->option ),
				esc_attr( get_option( $param->option, $param->default ) ), //phpcs:ignore
				$this->array_to_attr( $attributes ), //phpcs:ignore
			] );

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Adds a list input field to a section on an admin page.
	 *
	 * @param array{
	 *     option: string,
	 *     section: string,
	 *     default: array,
	 *     label: string,
	 *     description: string|string[],
	 *     rows: integer,
	 * } $args An array of arguments.
	 */
	public function add_list( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'      => '',
			'section'     => 'default',
			'default'     => [],
			'label'       => '',
			'description' => '',
			'rows'        => 10,
		] );

		register_setting( $this->slug, $param->option, [
			'type'              => 'array',
			'default'           => $param->default,
			'sanitize_callback' => $param->sanitize ?? function ( mixed $value ): array { // phpcs:ignore
				$value = is_string( $value ) ? explode( "\n", $value ) : (array) $value;
				$value = array_map( 'sanitize_text_field', $value );
				return array_values( array_unique( array_filter( $value ) ) );
			},
		] );

		add_settings_field( $param->option, $param->label, function () use ( $param ) {
			$whitelist  = array_flip( [ 'maxlength', 'minlength', 'readonly', 'disabled', 'rows' ] );
			$attributes = array_intersect_key( (array) $param, $whitelist );

			vprintf( '<textarea id="%s" class="regular-text" rows="10" name="%s"%s>%s</textarea>', [
				esc_attr( $param->option ),
				esc_attr( $param->option ),
				$this->array_to_attr( $attributes ), //phpcs:ignore
				esc_html( join( "\n", get_option( $param->option, $param->default ) ) ), //phpcs:ignore
			] );

			if ( is_array( $param->description ) ) {
				$param->description = join( ' ', $param->description );
			}

			printf( '<p class="description">%s</p>', wp_kses_post( $param->description ) );
		}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
	}

	/**
	 * Transforms an associative array of key/value pairs to html attributes.
	 *
	 * @param array $attr HTML attributes as key/value pairs.
	 *
	 * @return string Html attributes
	 */
	protected function array_to_attr( array $attr = [] ): string {
		$call = function ( string $key, $value ): string { // phpcs:ignore
			if ( is_bool( $value ) && $value ) {
				return sanitize_key( $key ) . '="' . esc_attr( $key ) . '"';
			}
			if ( ! is_bool( $value ) ) {
				return sanitize_key( $key ) . '="' . esc_attr( $value ) . '"';
			}
			return '';
		};

		if ( $attr ) {
			return ' ' . join( ' ', array_map( $call, array_keys( $attr ), $attr ) );
		}

		return '';
	}
}
