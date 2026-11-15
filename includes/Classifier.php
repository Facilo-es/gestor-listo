<?php
/**
 * The ten rules of section 6.3 of the spec. First match wins.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Pure classification.
 */
final class Classifier {

	const BOOK = array(
		'interior' => array( '01', 'S1', '' ),
		'intra'    => array( '01', '', 'E5' ),
		'export'   => array( '02', '', 'E2' ),
		'canarias' => array( '02', '', 'E2' ),
		'oss'      => array( '17', 'N2', '' ),
	);

	const EU = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );

	/**
	 * Classifies a document.
	 *
	 * @param array $f pais, cp, nif, all_virtual, unmapped, has_es_iva, has_oss.
	 * @return array clase and raised codes.
	 */
	public static function classify( array $f ) {
		$pais  = $f['pais'];
		$eu    = 'ES' !== $pais && in_array( $pais, self::EU, true );
		$vat   = '' !== $f['nif'];
		$es    = $f['has_es_iva'];
		$zero  = ! $es && ! $f['has_oss'];
		$islas = in_array( substr( (string) $f['cp'], 0, 2 ), array( '35', '38', '51', '52' ), true );

		if ( ! empty( $f['unmapped'] ) ) {
			return array( 'revisar', array( 'E1' ) );
		}
		if ( $f['all_virtual'] && $eu && $vat ) {
			return array( 'revisar', array( 'E6' ) );
		}
		if ( 'ES' === $pais && $islas && ! $es ) {
			return array( 'canarias', array() );
		}
		if ( 'ES' === $pais ) {
			if ( $islas ) {
				return array( 'interior', array( 'A6' ) );
			}
			return array( 'interior', $zero ? array( 'A9' ) : array() );
		}
		if ( $eu && $f['has_oss'] ) {
			return array( 'oss', array() );
		}
		if ( $eu && $zero && $vat ) {
			return array( 'intra', array() );
		}
		if ( $eu && $es ) {
			return array( 'interior', array() );
		}
		if ( $eu ) {
			return array( 'revisar', array( 'E5' ) );
		}
		if ( $zero ) {
			return array( 'export', array() );
		}
		return array( 'interior', array( 'A6' ) );
	}
}
