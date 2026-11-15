<?php
/**
 * Stops text cells being read as spreadsheet formulas.
 *
 * @package GestorListo
 */

namespace GestorListo\Writers;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Formula guard for every CSV and spreadsheet text cell.
 */
final class Guard {

	/**
	 * Prefixes dangerous text with an apostrophe.
	 *
	 * @param string $text Cell text.
	 * @return string
	 */
	public static function cell( $text ) {
		$text = (string) $text;
		return '' !== $text && false !== strpos( '=+-@', $text[0] ) ? "'" . $text : $text;
	}
}
