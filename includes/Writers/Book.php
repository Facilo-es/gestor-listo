<?php
/**
 * The Tax Agency's book of issued invoices, section 6.5 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo\Writers;

use GestorListo\Classifier;
use GestorListo\Dates;
use GestorListo\Document;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Rows by column letter, then the XLSX.
 */
final class Book {

	const CONSUMER = 'VENTAS A CONSUMIDOR FINAL';

	/**
	 * One row per VAT rate, documents with errors left out.
	 *
	 * @param array $docs Documents.
	 * @param array $shop Shop: tipo, actividad_codigo, actividad_tipo, epigrafe.
	 * @return array
	 */
	public static function rows( array $docs, array $shop ) {
		$rows = array();
		foreach ( $docs as $doc ) {
			if ( Document::excluded( $doc ) ) {
				continue;
			}
			list( $year, $q )      = Dates::quarter_of( $doc['fecha_expedicion'] );
			list( $clave, $s, $e ) = Classifier::BOOK[ $doc['clase'] ];
			$nif                   = $doc['cliente']['nif'];
			$foreign               = 'ES' !== $doc['cliente']['pais'] && '' !== $nif;
			foreach ( $doc['lineas'] as $l ) {
				$row = array(
					'A'  => (string) $year,
					'B'  => $q . 'T',
					'C'  => ( $shop['actividad_codigo'] ?? '' ) ? $shop['actividad_codigo'] : 'A',
					'D'  => ( $shop['actividad_tipo'] ?? '' ) ? $shop['actividad_tipo'] : '03',
					'E'  => $shop['epigrafe'] ?? '',
					'F'  => $doc['tipo_factura'],
					'G'  => 'autonomo' === ( $shop['tipo'] ?? '' ) ? 'I01' : '',
					'H'  => 'autonomo' === ( $shop['tipo'] ?? '' ) ? $l['base'] : '',
					'I'  => Dates::dmy( $doc['fecha_expedicion'] ),
					'J'  => $doc['fecha_operacion'] !== $doc['fecha_expedicion'] ? Dates::dmy( $doc['fecha_operacion'] ) : '',
					'K'  => substr( $doc['serie'], 0, 20 ),
					'L'  => substr( $doc['numero'], -20 ),
					'N'  => $foreign ? ( 'intra' === $doc['clase'] ? '02' : '06' ) : '',
					'O'  => $foreign ? $doc['cliente']['pais'] : '',
					'P'  => $nif,
					'Q'  => '' === $nif ? self::CONSUMER : Guard::cell( mb_substr( $doc['cliente']['nombre'], 0, 40 ) ),
					'R'  => $clave,
					'S'  => $s,
					'T'  => $e,
					'U'  => Document::row_total( $l ),
					'V'  => $l['base'],
					'W'  => 'S1' === $s ? $l['tipo_iva'] : '',
					'X'  => 'S1' === $s ? $l['cuota'] : '',
					'Y'  => $l['tipo_re'] > 0 ? $l['tipo_re'] : '',
					'Z'  => $l['tipo_re'] > 0 ? $l['cuota_re'] : '',
					'AJ' => Guard::cell( $doc['pedido'] ),
				);

				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * File name: exercise, NIF, T, name, uppercase with no accents.
	 *
	 * @param int   $year Year.
	 * @param array $shop Shop.
	 * @return string
	 */
	public static function file_name( $year, array $shop ) {
		$name = strtoupper( strtr( $shop['nombre'], array_combine( preg_split( '//u', 'áéíóúüñÁÉÍÓÚÜÑ', -1, PREG_SPLIT_NO_EMPTY ), str_split( 'aeiouunAEIOUUN' ) ) ) );
		$name = trim( preg_replace( '/[^A-Z0-9 ]/', '', $name ) );
		return $year . $shop['nif'] . 'T' . $name . '.xlsx';
	}

	/**
	 * Writes the XLSX.
	 *
	 * @param string $path Target.
	 * @param array  $docs Documents.
	 * @param array  $shop Shop.
	 * @return bool
	 */
	public static function write( $path, array $docs, array $shop ) {
		$sheets = array();
		foreach ( BookHeaders::SHEETS as $name => $sheet ) {
			$rows = array();
			foreach ( $sheet['cells'] as $ref => $text ) {
				preg_match( '/^([A-Z]+)(\d+)$/', $ref, $m );
				$rows[ (int) $m[2] ][ $m[1] ] = $text;
			}
			$sheets[ $name ] = array(
				'rows'   => $rows,
				'merges' => $sheet['merges'],
				'bold'   => 3,
			);
		}
		$r = 4;
		foreach ( self::rows( $docs, $shop ) as $row ) {
			$sheets['EXPEDIDAS_INGRESOS']['rows'][ $r++ ] = $row;
		}
		return Xlsx::write( $path, $sheets );
	}
}
