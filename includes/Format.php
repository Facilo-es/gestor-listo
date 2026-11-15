<?php
/**
 * Spanish number format.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Formatting helpers with no WordPress in them.
 */
final class Format {

	/**
	 * For example 1.234,50.
	 *
	 * @param float $value    Amount.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function money( $value, $decimals = 2 ) {
		return number_format( (float) $value, $decimals, ',', '.' );
	}

	/**
	 * Percent without trailing zeros, for example 5,2.
	 *
	 * @param float $value Percent.
	 * @return string
	 */
	public static function pct( $value ) {
		return str_replace( '.', ',', rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' ) );
	}
}
