<?php
/**
 * Implements the singleton pattern.
 *
 * @author Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace peroks\wp\plugin\tools;

/**
 * Implements the singleton pattern.
 */
trait Singleton {
	/**
	 * An array of class singletons.
	 *
	 * @var static[]
	 */
	protected static array $inst;

	/**
	 * Gets a new or existing class instance.
	 *
	 * @return static The class singleton.
	 */
	public static function instance(): static {
		$class = apply_filters( Plugin::FILTER_CLASS_NAME, static::class );

		if ( empty( static::$inst[ $class ] ) ) {
			static::$inst[ $class ] = apply_filters( Plugin::FILTER_CLASS_INSTANCE, new $class(), static::class );
			do_action( Plugin::ACTION_CLASS_LOADED, static::$inst[ $class ], $class, static::class );
		}
		return static::$inst[ $class ];
	}

	/**
	 * Protect constructor.
	 */
	protected function __construct() {}
}
