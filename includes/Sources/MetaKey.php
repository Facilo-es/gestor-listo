<?php
/**
 * A number and date meta key given by the owner. Covers Verifactu plugins.
 *
 * @package GestorListo
 */

namespace GestorListo\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Custom meta keys.
 */
final class MetaKey extends Source {

	/**
	 * Keys from settings.
	 *
	 * @var array
	 */
	private $keys;

	/**
	 * Takes the keys.
	 *
	 * @param string $number Number key.
	 * @param string $date   Date key.
	 */
	public function __construct( $number, $date ) {
		$this->keys = array( $number, $date );
	}

	/**
	 * Source id.
	 *
	 * @return string
	 */
	public function id() {
		return 'meta';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Other plugin (custom fields)', 'gestor-listo-for-woocommerce' );
	}

	/**
	 * Set up in settings.
	 *
	 * @return bool
	 */
	public function detect() {
		return '' !== $this->keys[0];
	}

	/**
	 * Orders created in the range that have a number. The date meta's format isn't known.
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
				'status'       => array_keys( wc_get_order_statuses() ),
			),
			array( $this, 'invoice' )
		);
	}

	/**
	 * Invoice.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|null
	 */
	public function invoice( $order ) {
		$number = (string) $order->get_meta( $this->keys[0] );
		if ( '' === $number ) {
			return null;
		}
		$date = '' !== $this->keys[1] ? $order->get_meta( $this->keys[1] ) : '';
		return array(
			'numero' => $number,
			'fecha'  => $date ? $this->day( $date ) : $order->get_date_created()->date( 'Y-m-d' ),
		);
	}
}
