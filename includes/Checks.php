<?php
/**
 * Checks over a whole quarter, section 5 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Adds checks to documents and writes their text.
 */
final class Checks {

	/**
	 * Runs the checks that look across documents.
	 *
	 * @param array $docs     Documents.
	 * @param array $settings retail (bool).
	 * @return array Documents, with checks.
	 */
	public static function run( array $docs, array $settings = array() ) {
		$limit = ! empty( $settings['retail'] ) || ! isset( $settings['retail'] ) ? 3000 : 400;
		$seen  = array();
		$serie = array();
		foreach ( $docs as $i => $doc ) {
			$num = $doc['numero_completo'];
			if ( 'EUR' !== $doc['moneda'] ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'E3',
					'args' => array( 'moneda' => $doc['moneda'] ),
				);
			}
			if ( isset( $seen[ $num ] ) && empty( $doc['flags']['fallback'] ) ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'E4',
					'args' => array(
						'a' => $docs[ $seen[ $num ] ]['pedido'],
						'b' => $doc['pedido'],
					),
				);
			}
			$seen[ $num ] = $i;
			if ( 'factura' === $doc['tipo'] && '' === $doc['cliente']['nif'] && abs( $doc['total'] ) > $limit ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'A3',
					'args' => array( 'limite' => $limit ),
				);
			}
			if ( ! empty( $doc['flags']['cancelled'] ) ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'A5',
					'args' => array(),
				);
			}
			if ( ! empty( $doc['flags']['refund_no_number'] ) ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'A4',
					'args' => array( 'x' => abs( $doc['total'] ) ),
				);
			}
			if ( '' !== $doc['cliente']['nif'] && ( 'ES' === $doc['cliente']['pais'] ? ! Nif::valid( $doc['cliente']['nif'] ) : ! Nif::valid_eu( $doc['cliente']['nif'], $doc['cliente']['pais'] ) ) ) {
				$docs[ $i ]['checks'][] = array(
					'code' => 'A10',
					'args' => array( 'nif' => $doc['cliente']['nif'] ),
				);
			}
			if ( empty( $doc['flags']['fallback'] ) && ! empty( $doc['flags']['numbered'] ) && '' !== $doc['numero'] && ctype_digit( $doc['numero'] ) ) {
				$serie[ $doc['serie'] ][ (int) $doc['numero'] ] = strlen( $doc['numero'] );
			}
		}
		return array( $docs, self::gaps( $serie ) );
	}

	/**
	 * Numbers missing inside each series.
	 *
	 * @param array $serie Series => number => digits.
	 * @return array Quarter-level checks.
	 */
	private static function gaps( array $serie ) {
		$out = array();
		foreach ( $serie as $name => $numbers ) {
			$digits = max( $numbers );
			$keys   = array_keys( $numbers );
			$last   = max( $keys );
			for ( $n = min( $keys ); $n <= $last; $n++ ) {
				if ( ! isset( $numbers[ $n ] ) ) {
					$number = ( '' === $name ? '' : $name . '-' ) . str_pad( (string) $n, $digits, '0', STR_PAD_LEFT );
					$out[]  = array(
						'code' => 'A2',
						'args' => array(
							'n' => $number,
							's' => $name,
						),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Text of a check for the admin screens, in the admin's language.
	 *
	 * The gestor's resumen.html uses text(), which stays in Spanish.
	 *
	 * @param array $check Check, with code and args.
	 * @param array $doc   Its document, when it has one.
	 * @return string
	 */
	public static function message( array $check, array $doc = array() ) {
		$a     = $check['args'] + array(
			'n' => $doc['numero_completo'] ?? '',
			'p' => $doc['pedido'] ?? '',
		);
		$pais  = $doc['cliente']['pais'] ?? '';
		$money = function ( $key ) use ( $a ) {
			return Format::money( $a[ $key ] ?? 0 );
		};
		switch ( $check['code'] ) {
			case 'E1':
				/* translators: 1: tax name, 2: invoice number. */
				return sprintf( __( 'The tax “%1$s” isn\'t assigned yet. Assign it in Settings and invoice %2$s will go back into the pack.', 'gestor-listo-for-woocommerce' ), $a['nombre'] ?? '', $a['n'] );
			case 'E2':
				/* translators: 1: invoice number, 2: invoice total, 3: sum of its lines. */
				return sprintf( __( 'Invoice %1$s doesn\'t add up: the total is %2$s € and its lines add up to %3$s €.', 'gestor-listo-for-woocommerce' ), $a['n'], Format::money( $doc['total'] ?? 0 ), $money( 's' ) );
			case 'E3':
				/* translators: 1: invoice number, 2: currency code. */
				return sprintf( __( 'Invoice %1$s is in %2$s. For now we only include invoices in euros.', 'gestor-listo-for-woocommerce' ), $a['n'], $a['moneda'] ?? '' );
			case 'E4':
				/* translators: 1: invoice number, 2: first order, 3: second order. */
				return sprintf( __( 'Number %1$s appears on two orders: %2$s and %3$s.', 'gestor-listo-for-woocommerce' ), $a['n'], $a['a'] ?? '', $a['b'] ?? '' );
			case 'E5':
				/* translators: 1: country code, 2: invoice number. */
				return sprintf( __( 'Sale to %1$s with no VAT and no EU VAT number (NIF-IVA) on invoice %2$s. Add the EU VAT number or check the tax.', 'gestor-listo-for-woocommerce' ), $pais, $a['n'] );
			case 'E6':
				/* translators: %s: invoice number. */
				return sprintf( __( 'Invoice %s is a digital service to a business in another EU country. Give it to your gestor separately.', 'gestor-listo-for-woocommerce' ), $a['n'] );
			case 'E7':
				/* translators: %s: invoice number. */
				return sprintf( __( 'Invoice %s has a tax base of 0, and a3 doesn\'t accept that.', 'gestor-listo-for-woocommerce' ), $a['n'] );
			case 'A1':
				/* translators: %s: order number. */
				return sprintf( __( 'Order %s is paid and has no invoice.', 'gestor-listo-for-woocommerce' ), $a['p'] );
			case 'A2':
				/* translators: 1: invoice number, 2: invoice series. */
				return sprintf( __( 'Invoice %1$s is missing from series %2$s.', 'gestor-listo-for-woocommerce' ), $a['n'], $a['s'] ?? '' );
			case 'A3':
				/* translators: 1: invoice number, 2: amount limit. */
				return sprintf( __( 'Invoice %1$s is over %2$s € and has no tax ID (NIF).', 'gestor-listo-for-woocommerce' ), $a['n'], Format::money( $a['limite'] ?? 0, 0 ) );
			case 'A4':
				/* translators: 1: order number, 2: refunded amount. */
				return sprintf( __( 'Order %1$s has a refund of %2$s € with no credit note.', 'gestor-listo-for-woocommerce' ), $a['p'], $money( 'x' ) );
			case 'A5':
				/* translators: 1: order number, 2: invoice number. */
				return sprintf( __( 'Order %1$s is cancelled, but its invoice %2$s still exists.', 'gestor-listo-for-woocommerce' ), $a['p'], $a['n'] );
			case 'A6':
				/* translators: 1: order number, 2: country code. */
				return sprintf( __( 'Order %1$s goes to %2$s with Spanish VAT.', 'gestor-listo-for-woocommerce' ), $a['p'], $a['pais'] ?? $pais );
			case 'A7':
				/* translators: %s: amount sold this year. */
				return sprintf( __( 'Your sales to consumers in other EU countries add up to %s € this year. From 10,000 € the way VAT applies changes. Talk it over with your gestor.', 'gestor-listo-for-woocommerce' ), $money( 'x' ) );
			case 'A8':
				/* translators: 1: invoice number, 2: expected VAT, 3: VAT found. */
				return sprintf( __( 'On invoice %1$s, the VAT doesn\'t match the base: %2$s € was expected and there is %3$s €.', 'gestor-listo-for-woocommerce' ), $a['n'], $money( 'e' ), $money( 'r' ) );
			case 'A9':
				/* translators: %s: invoice number. */
				return sprintf( __( 'Sale in Spain at 0%% on invoice %s.', 'gestor-listo-for-woocommerce' ), $a['n'] );
			case 'A10':
				/* translators: 1: invoice number, 2: the tax ID as written. */
				return sprintf( __( 'The tax ID (NIF) on invoice %1$s doesn\'t look right: %2$s.', 'gestor-listo-for-woocommerce' ), $a['n'], $a['nif'] ?? '' );
			case 'A11':
				/* translators: 1: accounting software, 2: first invoice, 3: second invoice. */
				return sprintf( __( 'Two invoices end up with the same short number in %1$s: %2$s and %3$s.', 'gestor-listo-for-woocommerce' ), $a['programa'] ?? '', $a['a'] ?? '', $a['b'] ?? '' );
		}
		return $check['code'];
	}

	/**
	 * Text of a check, in Spanish, for the gestor's resumen.html.
	 *
	 * @param array $check Check, with code and args.
	 * @param array $doc   Its document, when it has one.
	 * @return string
	 */
	public static function text( array $check, array $doc = array() ) {
		$a     = $check['args'] + array(
			'n' => $doc['numero_completo'] ?? '',
			'p' => $doc['pedido'] ?? '',
		);
		$pais  = $doc['cliente']['pais'] ?? '';
		$money = function ( $key ) use ( $a ) {
			return Format::money( $a[ $key ] ?? 0 );
		};
		$texts = array(
			'E1'  => 'Falta asignar el impuesto «' . ( $a['nombre'] ?? '' ) . '». Hazlo en Ajustes y la factura ' . $a['n'] . ' volverá al paquete.',
			'E2'  => 'La factura ' . $a['n'] . ' no cuadra: el total es ' . Format::money( $doc['total'] ?? 0 ) . ' € y sus líneas suman ' . $money( 's' ) . ' €.',
			'E3'  => 'La factura ' . $a['n'] . ' está en ' . ( $a['moneda'] ?? '' ) . '. Por ahora solo incluimos facturas en euros.',
			'E4'  => 'El número ' . $a['n'] . ' aparece en dos pedidos: ' . ( $a['a'] ?? '' ) . ' y ' . ( $a['b'] ?? '' ) . '.',
			'E5'  => 'Venta a ' . $pais . ' sin IVA y sin NIF-IVA en la factura ' . $a['n'] . '. Añade el NIF-IVA o revisa el impuesto.',
			'E6'  => 'La factura ' . $a['n'] . ' es un servicio digital a una empresa de otro país de la UE. Pásasela a tu gestor aparte.',
			'E7'  => 'La factura ' . $a['n'] . ' tiene base 0 y a3 no la admite.',
			'A1'  => 'El pedido ' . $a['p'] . ' está pagado y no tiene factura.',
			'A2'  => 'Falta la factura ' . $a['n'] . ' en la serie ' . ( $a['s'] ?? '' ) . '.',
			'A3'  => 'La factura ' . $a['n'] . ' supera ' . Format::money( $a['limite'] ?? 0, 0 ) . ' € y no lleva NIF.',
			'A4'  => 'El pedido ' . $a['p'] . ' tiene una devolución de ' . $money( 'x' ) . ' € sin factura rectificativa.',
			'A5'  => 'El pedido ' . $a['p'] . ' está cancelado, pero su factura ' . $a['n'] . ' sigue existiendo.',
			'A6'  => 'El pedido ' . $a['p'] . ' va a ' . ( $a['pais'] ?? $pais ) . ' con IVA español.',
			'A7'  => 'Tus ventas a particulares de otros países de la UE llevan ' . $money( 'x' ) . ' € este año. A partir de 10.000 € cambia cómo se aplica el IVA. Coméntalo con tu gestor.',
			'A8'  => 'En la factura ' . $a['n'] . ', el IVA no coincide con la base: se esperaban ' . $money( 'e' ) . ' € y hay ' . $money( 'r' ) . ' €.',
			'A9'  => 'Venta en España al 0 % en la factura ' . $a['n'] . '.',
			'A10' => 'El NIF de la factura ' . $a['n'] . ' no parece correcto: ' . ( $a['nif'] ?? '' ) . '.',
			'A11' => 'Dos facturas quedan con el mismo número corto en ' . ( $a['programa'] ?? '' ) . ': ' . ( $a['a'] ?? '' ) . ' y ' . ( $a['b'] ?? '' ) . '.',
		);
		return $texts[ $check['code'] ] ?? $check['code'];
	}
}
