<?php
/**
 * The generic CSV files, section 6.6 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo\Writers;

use GestorListo\Document;
use GestorListo\Format;
use GestorListo\Totals;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * UTF-8 with BOM, semicolons, decimal comma.
 */
final class Csv {

	/**
	 * Numbers stay numbers.
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private static function n( $value ) {
		return number_format( (float) $value, 2, ',', '' );
	}

	/**
	 * Joins a row, quoting where needed.
	 *
	 * @param array $cells Cells.
	 * @return string
	 */
	private static function row( array $cells ) {
		$out = array();
		foreach ( $cells as $cell ) {
			$out[] = preg_match( '/[;"\r\n]/', $cell ) ? '"' . str_replace( '"', '""', $cell ) . '"' : $cell;
		}
		return implode( ';', $out ) . "\r\n";
	}

	/**
	 * Text of ventas.csv.
	 *
	 * @param array $docs Documents.
	 * @return string
	 */
	public static function ventas( array $docs ) {
		$out = "\xEF\xBB\xBF" . self::row( array( 'fecha', 'serie', 'numero', 'tipo', 'cliente', 'nif', 'pais', 'clase', 'base', 'tipo_iva', 'iva', 'tipo_recargo', 'recargo', 'total', 'pedido' ) );
		foreach ( $docs as $doc ) {
			if ( Document::excluded( $doc ) ) {
				continue;
			}
			foreach ( $doc['lineas'] as $l ) {
				$out .= self::row(
					array(
						substr( $doc['fecha_expedicion'], 8, 2 ) . '/' . substr( $doc['fecha_expedicion'], 5, 2 ) . '/' . substr( $doc['fecha_expedicion'], 0, 4 ),
						Guard::cell( $doc['serie'] ),
						Guard::cell( $doc['numero'] ),
						$doc['tipo_factura'],
						Guard::cell( '' === $doc['cliente']['nif'] ? Book::CONSUMER : $doc['cliente']['nombre'] ),
						Guard::cell( $doc['cliente']['nif'] ),
						$doc['cliente']['pais'],
						$doc['clase'],
						self::n( $l['base'] ),
						self::n( $l['tipo_iva'] ),
						self::n( $l['cuota'] ),
						self::n( $l['tipo_re'] ),
						self::n( $l['cuota_re'] ),
						self::n( Document::row_total( $l ) ),
						Guard::cell( $doc['pedido'] ),
					)
				);
			}
		}
		return $out;
	}

	/**
	 * Text of oss-por-pais.csv, or empty with no OSS sales.
	 *
	 * @param array $docs Documents.
	 * @return string
	 */
	public static function oss( array $docs ) {
		$rows = Totals::of( $docs )['oss'];
		if ( ! $rows ) {
			return '';
		}
		$out = "\xEF\xBB\xBF" . self::row( array( 'pais', 'tipo', 'base', 'iva' ) );
		foreach ( $rows as $row ) {
			$out .= self::row( array( $row['pais'], Format::pct( $row['tipo'] ), self::n( $row['base'] ), self::n( $row['iva'] ) ) );
		}
		return $out;
	}
}
