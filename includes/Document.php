<?php
/**
 * The normalised document every writer works on, section 6.1 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Builds Documento arrays from the facts a source reads.
 */
final class Document {

	/**
	 * Splits a full number into series and number.
	 *
	 * @param string $full Full number.
	 * @return array
	 */
	public static function split_number( $full ) {
		if ( preg_match( '/^(.*?)(\d+)$/', $full, $m ) ) {
			return array( rtrim( $m[1], '-/ ' ), $m[2] );
		}
		return array( '', $full );
	}

	/**
	 * Builds a document.
	 *
	 * @param array $f        Facts: key, order_id, refund_id, source, tipo, numero_completo, fecha_expedicion,
	 *                        fecha_operacion, cliente, items, stored, total, moneda, rectifica, pedido, flags.
	 * @param array $rates    Rate map.
	 * @param array $settings Settings: tipo_rectificativa.
	 * @return array
	 */
	public static function build( array $f, array $rates, array $settings = array() ) {
		list( $serie, $numero ) = self::split_number( $f['numero_completo'] );

		$split = TaxSplitter::split( $f['items'], $f['stored'], $rates, $f['total'] );
		$nif   = $f['cliente']['nif'];
		$es    = false;
		foreach ( $split['lineas'] as $linea ) {
			$es = $es || $linea['cuota'] > 0 || $linea['cuota'] < 0 || $linea['tipo_iva'] > 0;
		}
		list( $clase, $codes ) = Classifier::classify(
			array(
				'pais'        => $f['cliente']['pais'],
				'cp'          => $f['cliente']['cp'],
				'nif'         => $nif,
				'all_virtual' => ! empty( $f['all_virtual'] ),
				'unmapped'    => $split['unmapped'],
				'has_es_iva'  => $es,
				'has_oss'     => (bool) $split['iva_extranjero'],
			)
		);

		$checks = array();
		foreach ( $codes as $code ) {
			$checks[] = array(
				'code' => $code,
				'args' => array( 'nombre' => implode( ', ', $split['unmapped'] ) ),
			);
		}
		if ( ! $split['cuadra'] && ! $split['unmapped'] ) {
			$checks[] = array(
				'code' => 'E2',
				'args' => array( 's' => $split['suma'] ),
			);
		}
		if ( $split['a8'] ) {
			$checks[] = array(
				'code' => 'A8',
				'args' => self::a8_args( $split['lineas'] ),
			);
		}

		$rect = 'rectificativa' === $f['tipo'];
		$tipo = $nif ? ( $rect ? ( $settings['tipo_rectificativa'] ?? 'R1' ) : 'F1' ) : ( $rect ? 'R5' : 'F2' );

		return array(
			'key'              => $f['key'],
			'order_id'         => $f['order_id'],
			'refund_id'        => $f['refund_id'] ?? 0,
			'source'           => $f['source'] ?? '',
			'tipo'             => $f['tipo'],
			'tipo_factura'     => $tipo,
			'numero_completo'  => $f['numero_completo'],
			'serie'            => $serie,
			'numero'           => $numero,
			'fecha_expedicion' => $f['fecha_expedicion'],
			'fecha_operacion'  => $f['fecha_operacion'] ?? $f['fecha_expedicion'],
			'cliente'          => $f['cliente'],
			'lineas'           => $split['lineas'],
			'iva_extranjero'   => $split['iva_extranjero'],
			'clase'            => $clase,
			'total'            => (float) $f['total'],
			'moneda'           => $f['moneda'] ?? 'EUR',
			'rectifica'        => $f['rectifica'] ?? array(
				'numero' => '',
				'fecha'  => '',
			),
			'pedido'           => $f['pedido'] ?? (string) $f['order_id'],
			'flags'            => $f['flags'] ?? array(),
			'checks'           => $checks,
		);
	}

	/**
	 * Values for the A8 text.
	 *
	 * @param array $lineas Lines.
	 * @return array
	 */
	private static function a8_args( array $lineas ) {
		foreach ( $lineas as $linea ) {
			$e = round( $linea['base'] * $linea['tipo_iva'] / 100, 2 );
			if ( abs( $e - $linea['cuota'] ) > TaxSplitter::TOLERANCE ) {
				return array(
					'e' => $e,
					'r' => $linea['cuota'],
				);
			}
		}
		return array();
	}

	/**
	 * Whether a check of error level left it out.
	 *
	 * @param array $doc Document.
	 * @return bool
	 */
	public static function excluded( array $doc ) {
		foreach ( $doc['checks'] as $check ) {
			if ( 'E' === $check['code'][0] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fingerprint for the delivery history.
	 *
	 * @param array $doc Document.
	 * @return string
	 */
	public static function fingerprint( array $doc ) {
		return md5( (string) json_encode( array( $doc['numero_completo'], $doc['fecha_expedicion'], $doc['total'], $doc['cliente']['nif'], $doc['cliente']['nombre'], $doc['lineas'], $doc['clase'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure code that also runs outside WordPress.
	}

	/**
	 * Row total for a line.
	 *
	 * @param array $linea Line.
	 * @return float
	 */
	public static function row_total( array $linea ) {
		return round( $linea['base'] + $linea['cuota'] + $linea['cuota_re'], 2 );
	}
}
