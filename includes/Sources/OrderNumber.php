<?php
/**
 * Fallback with no invoice plugin: order numbers, with a warning on the screen.
 *
 * @package GestorListo
 */

namespace GestorListo\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * An order is not an invoice, and the screen says so.
 */
final class OrderNumber extends Source {

	/**
	 * Statuses the owner chose.
	 *
	 * @var array
	 */
	private $statuses;

	/**
	 * Takes the statuses.
	 *
	 * @param array $statuses Without the wc- prefix.
	 */
	public function __construct( array $statuses ) {
		$this->statuses = $statuses;
	}

	/**
	 * Source id.
	 *
	 * @return string
	 */
	public function id() {
		return 'order';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Order numbers', 'gestor-listo-for-woocommerce' );
	}

	/**
	 * Always there.
	 *
	 * @return bool
	 */
	public function detect() {
		return true;
	}

	/**
	 * Orders in the range.
	 *
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return \Generator
	 */
	public function orders( $start, $end ) {
		return self::each(
			array(
				'type'         => 'shop_order',
				'date_created' => $start . '...' . $end,
				'status'       => $this->statuses,
			)
		);
	}

	/**
	 * Date used: paid date, else created date.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public function invoice( $order ) {
		$date = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
		return array(
			'numero' => (string) $order->get_order_number(),
			'fecha'  => $date->date( 'Y-m-d' ),
		);
	}

	/**
	 * No real numbers.
	 *
	 * @return bool
	 */
	public function numbered() {
		return false;
	}
}
