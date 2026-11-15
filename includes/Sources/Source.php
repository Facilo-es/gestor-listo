<?php
/**
 * What a document source can do.
 *
 * @package GestorListo
 */

namespace GestorListo\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Sources only read. None of them may create a number, a document or a meta row.
 */
abstract class Source {

	/**
	 * Short id, stored in settings.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Name shown to the owner.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Whether it's installed here.
	 *
	 * @return bool
	 */
	abstract public function detect();

	/**
	 * Orders with an invoice dated in the range.
	 *
	 * @param string $start yyyy-mm-dd.
	 * @param string $end   yyyy-mm-dd.
	 * @return iterable \WC_Order one at a time.
	 */
	abstract public function orders( $start, $end );

	/**
	 * Number and date of the order's invoice, or null.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|null
	 */
	abstract public function invoice( $order );

	/**
	 * Number and date of a refund's rectificativa, or null.
	 *
	 * @param \WC_Order_Refund $refund Refund.
	 * @return array|null
	 */
	public function credit_note( $refund ) {
		unset( $refund );
		return null;
	}

	/**
	 * PDF bytes of the invoice, without creating it, or null.
	 *
	 * @param \WC_Order $order Order.
	 * @return string|null
	 */
	public function pdf( $order ) {
		unset( $order );
		return null;
	}

	/**
	 * Whether this source can hand over PDFs.
	 *
	 * @return bool
	 */
	public function has_pdf() {
		return false;
	}

	/**
	 * Whether it reads real invoice numbers, not order numbers.
	 *
	 * @return bool
	 */
	public function numbered() {
		return true;
	}

	/**
	 * Orders by a date meta key, as a unix time.
	 *
	 * @param string $key   Meta key.
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return \Generator
	 */
	protected function by_time_meta( $key, $start, $end ) {
		$from = strtotime( $start . ' 00:00:00' );
		$to   = strtotime( $end . ' 23:59:59' );
		// An invoice never comes before its order. The dates are checked here because the
		// posts data store doesn't apply a meta query, while the HPOS one does.
		return self::each(
			array(
				'type'         => 'shop_order',
				'status'       => array_keys( wc_get_order_statuses() ),
				'date_created' => '<=' . $to,
			),
			function ( $order ) use ( $key, $from, $to ) {
				$time = (int) $order->get_meta( $key );
				return $time >= $from && $time <= $to;
			}
		);
	}

	/**
	 * Orders one at a time, so a big quarter never sits in memory all at once.
	 *
	 * @param array         $args wc_get_orders() arguments.
	 * @param callable|null $keep Which orders to keep.
	 * @return \Generator
	 */
	public static function each( array $args, $keep = null ) {
		$ids = wc_get_orders(
			array_merge(
				$args,
				array(
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);
		foreach ( $ids as $i => $id ) {
			// WooCommerce keeps every order it loads in the in-memory cache. Drop it now and then.
			if ( $i && 0 === $i % 200 && wp_cache_supports( 'flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
			$order = wc_get_order( $id );
			if ( $order && ( ! $keep || $keep( $order ) ) ) {
				yield $order;
			}
		}
	}

	/**
	 * Date meta values to yyyy-mm-dd.
	 *
	 * @param mixed $value Unix time or a date.
	 * @return string
	 */
	protected function day( $value ) {
		if ( is_numeric( $value ) ) {
			return wp_date( 'Y-m-d', (int) $value );
		}
		return substr( (string) $value, 0, 10 );
	}
}
