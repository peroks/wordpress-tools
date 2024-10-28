<?php
/**
 * Static helpers and utilities.
 *
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * Static helpers and utilities.
 */
class Utils {
	/**
	 * Converts a string to an array.
	 *
	 * @param mixed  $value A string to be converted.
	 * @param string $separator The string separator, default to comma.
	 *
	 * @return array|mixed The converted array or the unmodified value.
	 */
	public static function string_to_array( mixed $value, string $separator = ',' ): mixed {
		if ( is_string( $value ) ) {
			$value = array_map( 'trim', explode( $separator, $value ) );
			return array_values( array_unique( array_filter( $value ) ) );
		}
		return $value;
	}
}
