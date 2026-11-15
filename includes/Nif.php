<?php
/**
 * Local checks of Spanish tax IDs. No outside call, so Free stays offline.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * NIF, NIE and CIF control characters.
 */
final class Nif {

	/**
	 * Uppercases and removes separators, and a leading ES on Spanish NIFs.
	 *
	 * @param string $nif  Raw value.
	 * @param string $pais ISO country.
	 * @return string
	 */
	public static function clean( $nif, $pais = 'ES' ) {
		$nif = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $nif ) );
		if ( 'ES' === $pais && 0 === strpos( $nif, 'ES' ) && strlen( $nif ) > 9 ) {
			$nif = substr( $nif, 2 );
		}
		return $nif;
	}

	/**
	 * Whether the control character is right.
	 *
	 * @param string $nif Clean value.
	 * @return bool
	 */
	public static function valid( $nif ) {
		$letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
		if ( preg_match( '/^(\d{8})([A-Z])$/', $nif, $m ) ) {
			return $letters[ (int) $m[1] % 23 ] === $m[2];
		}
		if ( preg_match( '/^([XYZ])(\d{7})([A-Z])$/', $nif, $m ) ) {
			return $letters[ (int) ( strpos( 'XYZ', $m[1] ) . $m[2] ) % 23 ] === $m[3];
		}
		if ( preg_match( '/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $nif, $m ) ) {
			$sum = 0;
			for ( $i = 0; $i < 7; $i++ ) {
				$d    = (int) $m[2][ $i ];
				$sum += 0 === $i % 2 ? (int) floor( $d * 2 / 10 ) + ( $d * 2 ) % 10 : $d;
			}
			$control = ( 10 - $sum % 10 ) % 10;
			return (string) $control === $m[3] || 'JABCDEFGHI'[ $control ] === $m[3];
		}
		return false;
	}

	/**
	 * Whether an EU VAT number has its country's shape (VIES FAQ, question 11).
	 * Check digits aren't verified. Unknown countries pass.
	 *
	 * @param string $nif  Clean value, with or without the country prefix.
	 * @param string $pais ISO country.
	 * @return bool
	 */
	public static function valid_eu( $nif, $pais ) {
		$shapes = array(
			'AT' => 'U\\d{8}',
			'BE' => '[01]\\d{9}',
			'BG' => '\\d{9,10}',
			'CY' => '\\d{8}[A-Z]',
			'CZ' => '\\d{8,10}',
			'DE' => '\\d{9}',
			'DK' => '\\d{8}',
			'EE' => '\\d{9}',
			'GR' => '\\d{9}',
			'FI' => '\\d{8}',
			'FR' => '[0-9A-Z]{2}\\d{9}',
			'HR' => '\\d{11}',
			'HU' => '\\d{8}',
			'IE' => '\\d{7}[A-W][A-I]?|\\d[A-Z+*]\\d{5}[A-W]',
			'IT' => '\\d{11}',
			'LT' => '\\d{9}|\\d{12}',
			'LU' => '\\d{8}',
			'LV' => '\\d{11}',
			'MT' => '\\d{8}',
			'NL' => '\\d{9}B\\d{2}',
			'PL' => '\\d{10}',
			'PT' => '\\d{9}',
			'RO' => '\\d{2,10}',
			'SE' => '\\d{12}',
			'SI' => '\\d{8}',
			'SK' => '\\d{10}',
		);
		if ( ! isset( $shapes[ $pais ] ) ) {
			return true;
		}
		$prefix = 'GR' === $pais ? 'EL' : $pais;
		if ( 0 === strpos( $nif, $prefix ) ) {
			$nif = substr( $nif, 2 );
		}
		return 1 === preg_match( '/^(?:' . $shapes[ $pais ] . ')$/', $nif );
	}
}
