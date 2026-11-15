<?php
/**
 * Adds a quarter up, for the screen and for resumen.html.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Pure sums over documents.
 */
final class Totals {

	const EXEMPT = array(
		'intra'    => 'Entregas a empresas de la UE',
		'export'   => 'Exportaciones',
		'canarias' => 'Canarias, Ceuta y Melilla',
		'oss'      => 'Ventas OSS a otros países de la UE',
	);

	/**
	 * Quarter totals.
	 *
	 * @param array $docs All documents.
	 * @return array facturas, base, iva, recargo, total, rows, oss.
	 */
	public static function of( array $docs ) {
		$rows = array();
		$oss  = array();
		$out  = array(
			'facturas' => 0,
			'base'     => 0.0,
			'iva'      => 0.0,
			'recargo'  => 0.0,
			'total'    => 0.0,
		);
		foreach ( $docs as $doc ) {
			if ( Document::excluded( $doc ) ) {
				continue;
			}
			++$out['facturas'];
			foreach ( $doc['lineas'] as $l ) {
				$key = 'interior' === $doc['clase'] ? 'iva:' . number_format( $l['tipo_iva'], 2, '.', '' ) : $doc['clase'];
				if ( ! isset( $rows[ $key ] ) ) {
					$rows[ $key ] = array(
						'label' => 'interior' === $doc['clase'] ? 'IVA ' . Format::pct( $l['tipo_iva'] ) . ' %' : self::EXEMPT[ $doc['clase'] ],
						'base'  => 0.0,
						'iva'   => 0.0,
						're'    => 0.0,
						'total' => 0.0,
					);
				}
				$rows[ $key ]['base']  += $l['base'];
				$rows[ $key ]['iva']   += $l['cuota'];
				$rows[ $key ]['re']    += $l['cuota_re'];
				$rows[ $key ]['total'] += Document::row_total( $l );
				$out['base']           += $l['base'];
				$out['iva']            += $l['cuota'];
				$out['recargo']        += $l['cuota_re'];
				$out['total']          += Document::row_total( $l );
			}
			foreach ( $doc['iva_extranjero'] as $f ) {
				$key = $f['pais'] . '|' . $f['tipo'];
				if ( ! isset( $oss[ $key ] ) ) {
					$oss[ $key ] = array(
						'pais' => $f['pais'],
						'tipo' => $f['tipo'],
						'base' => 0.0,
						'iva'  => 0.0,
					);
				}
				$oss[ $key ]['base'] += $f['base'];
				$oss[ $key ]['iva']  += $f['cuota'];
			}
		}
		foreach ( array( 'base', 'iva', 'recargo', 'total' ) as $key ) {
			$out[ $key ] = round( $out[ $key ], 2 );
		}
		foreach ( $rows as $key => $row ) {
			foreach ( array( 'base', 'iva', 're', 'total' ) as $field ) {
				$rows[ $key ][ $field ] = round( $row[ $field ], 2 );
			}
		}
		uksort(
			$rows,
			function ( $a, $b ) {
				$ia = 0 === strpos( $a, 'iva:' );
				$ib = 0 === strpos( $b, 'iva:' );
				if ( $ia && $ib ) {
					return (float) substr( $b, 4 ) <=> (float) substr( $a, 4 );
				}
				if ( $ia !== $ib ) {
					return $ia ? -1 : 1;
				}
				return array_search( $a, array_keys( self::EXEMPT ), true ) <=> array_search( $b, array_keys( self::EXEMPT ), true );
			}
		);
		$out['rows'] = $rows;
		$out['oss']  = array_values( $oss );
		return $out;
	}
}
