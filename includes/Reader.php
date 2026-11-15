<?php
/**
 * Turns WooCommerce orders and refunds into facts for Document::build. It only reads.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only reader.
 */
final class Reader {

	/**
	 * Documents of a date range.
	 *
	 * @param string $start yyyy-mm-dd.
	 * @param string $end   yyyy-mm-dd.
	 * @return array Documents and paid orders with no invoice.
	 */
	public static function read( $start, $end ) {
		$s      = Settings::all();
		$source = Settings::source();
		$rates  = Settings::rates();
		$docs   = array();
		$seen   = array();
		foreach ( $source->orders( $start, $end ) as $order ) {
			$invoice = $source->invoice( $order );
			if ( ! $invoice ) {
				continue;
			}
			$seen[ $order->get_id() ] = true;

			$docs[] = Document::build( self::order_facts( $order, $invoice, $source, $s ), $rates, $s );
		}
		foreach ( Sources\Source::each(
			array(
				'type'         => 'shop_order_refund',
				'date_created' => $start . '...' . $end,
			)
		) as $refund ) {
			$parent = wc_get_order( $refund->get_parent_id() );
			if ( $parent ) {
				$facts = self::refund_facts( $refund, $parent, $source, $s );
				if ( $facts ) {
					$docs[] = Document::build( $facts, $rates, $s );
				}
			}
		}
		usort(
			$docs,
			function ( $a, $b ) {
				return array( $a['fecha_expedicion'], $a['numero_completo'] ) <=> array( $b['fecha_expedicion'], $b['numero_completo'] );
			}
		);

		$missing = array();
		if ( $source->numbered() ) {
			foreach ( Sources\Source::each(
				array(
					'type'      => 'shop_order',
					'status'    => array( 'completed', 'processing' ),
					'date_paid' => $start . '...' . $end,
				)
			) as $order ) {
				if ( ! isset( $seen[ $order->get_id() ] ) && ! $source->invoice( $order ) ) {
					$missing[] = array(
						'code' => 'A1',
						'args' => array(
							'p'        => $order->get_order_number(),
							'order_id' => $order->get_id(),
						),
					);
				}
			}
		}
		return array( $docs, $missing );
	}

	/**
	 * Client data from an order.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $s     Settings.
	 * @return array
	 */
	private static function client( $order, array $s ) {
		$ship = $order->has_shipping_address() && $order->get_shipping_country();
		$pais = $ship ? $order->get_shipping_country() : $order->get_billing_country();
		$raw  = $s['nif_meta'] ? (string) $order->get_meta( $s['nif_meta'] ) : '';
		return array(
			'nombre' => '' !== $order->get_billing_company() ? $order->get_billing_company() : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'nif'    => Nif::clean( $raw, (string) $pais ),
			'pais'   => (string) $pais,
			'cp'     => (string) ( $ship ? $order->get_shipping_postcode() : $order->get_billing_postcode() ),
		);
	}

	/**
	 * Items and stored taxes of an order or refund.
	 *
	 * @param \WC_Abstract_Order $order Order or refund.
	 * @return array items, stored, all_virtual.
	 */
	private static function money( $order ) {
		$items   = array();
		$virtual = true;
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item ) {
			$taxes = array_filter(
				(array) ( $item->get_taxes()['total'] ?? array() ),
				function ( $amount ) {
					return '' !== $amount;
				}
			);

			$items[] = array(
				'net'   => (float) $item->get_total(),
				'taxes' => $taxes,
			);
			if ( 'line_item' === $item->get_type() ) {
				$product = $item->get_product();
				$virtual = $virtual && $product && $product->is_virtual();
			}
			if ( 'shipping' === $item->get_type() ) {
				$virtual = false;
			}
		}
		$stored = array();
		foreach ( $order->get_items( 'tax' ) as $tax ) {
			$id            = $tax->get_rate_id();
			$stored[ $id ] = ( $stored[ $id ] ?? 0 ) + (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();
		}
		return compact( 'items', 'stored', 'virtual' );
	}

	/**
	 * Facts of an invoice.
	 *
	 * @param \WC_Order      $order   Order.
	 * @param array          $invoice Number and date.
	 * @param Sources\Source $source  Source.
	 * @param array          $s       Settings.
	 * @return array
	 */
	private static function order_facts( $order, array $invoice, $source, array $s ) {
		$money = self::money( $order );
		return array(
			'key'              => 'o' . $order->get_id(),
			'order_id'         => $order->get_id(),
			'source'           => $source->id(),
			'tipo'             => 'factura',
			'numero_completo'  => $invoice['numero'],
			'fecha_expedicion' => $invoice['fecha'],
			'cliente'          => self::client( $order, $s ),
			'items'            => $money['items'],
			'stored'           => $money['stored'],
			'all_virtual'      => $money['virtual'],
			'total'            => (float) $order->get_total(),
			'moneda'           => $order->get_currency(),
			'pedido'           => (string) $order->get_order_number(),
			'flags'            => array(
				'numbered'  => $source->numbered(),
				'fallback'  => ! $source->numbered(),
				'cancelled' => $order->has_status( 'cancelled' ),
			),
		);
	}

	/**
	 * Facts of a refund. Amount-only refunds take their split from the order, in proportion.
	 *
	 * @param \WC_Order_Refund $refund Refund.
	 * @param \WC_Order        $parent_order Order.
	 * @param Sources\Source   $source Source.
	 * @param array            $s      Settings.
	 * @return array|null
	 */
	private static function refund_facts( $refund, $parent_order, $source, array $s ) {
		$invoice = $source->invoice( $parent_order );
		if ( ! $invoice ) {
			return null;
		}
		$note  = $source->credit_note( $refund );
		$money = self::money( $refund );
		$total = (float) $refund->get_total();
		if ( ! $money['items'] && (float) $parent_order->get_total() > 0 ) {
			$ratio = $total / (float) $parent_order->get_total();
			$money = self::money( $parent_order );
			foreach ( $money['items'] as $i => $item ) {
				$money['items'][ $i ]['net'] = $item['net'] * $ratio;
			}
			foreach ( $money['stored'] as $id => $amount ) {
				$money['stored'][ $id ] = $amount * $ratio;
			}
		}
		return array(
			'key'              => 'r' . $refund->get_id(),
			'order_id'         => $parent_order->get_id(),
			'refund_id'        => $refund->get_id(),
			'source'           => $source->id(),
			'tipo'             => 'rectificativa',
			'numero_completo'  => $note ? $note['numero'] : 'R' . $refund->get_id(),
			'fecha_expedicion' => $note ? $note['fecha'] : $refund->get_date_created()->date( 'Y-m-d' ),
			'cliente'          => self::client( $parent_order, $s ),
			'items'            => $money['items'],
			'stored'           => $money['stored'],
			'all_virtual'      => $money['virtual'],
			'total'            => $total,
			'moneda'           => $parent_order->get_currency(),
			'rectifica'        => array(
				'numero' => $invoice['numero'],
				'fecha'  => $invoice['fecha'],
			),
			'pedido'           => (string) $parent_order->get_order_number(),
			'flags'            => array(
				'numbered'         => (bool) $note,
				'refund_no_number' => ! $note,
			),
		);
	}
}
