<?php
/**
 * WebToffee's PDF invoices plugin.
 *
 * @package GestorListo
 */

namespace GestorListo\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Its meta keys were read in version 5.0.4. Its PDF code can generate as it goes, so PDFs stay out.
 */
final class WebToffee extends Source {

	/**
	 * Source id.
	 *
	 * @return string
	 */
	public function id() {
		return 'webtoffee';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function label() {
		return 'WebToffee PDF Invoices';
	}

	/**
	 * Installed.
	 *
	 * @return bool
	 */
	public function detect() {
		return class_exists( 'Wf_Woocommerce_Packing_List' );
	}

	/**
	 * Orders in the range.
	 *
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return \Generator
	 */
	public function orders( $start, $end ) {
		return $this->by_time_meta( '_wf_invoice_date', $start, $end );
	}

	/**
	 * Invoice.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|null
	 */
	public function invoice( $order ) {
		$number = (string) $order->get_meta( 'wf_invoice_number' );
		if ( '' === $number ) {
			return null;
		}
		return array(
			'numero' => $number,
			'fecha'  => $this->day( $order->get_meta( '_wf_invoice_date' ) ),
		);
	}
}
