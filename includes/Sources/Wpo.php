<?php
/**
 * PDF Invoices & Packing Slips, by WP Overnight.
 *
 * @package GestorListo
 */

namespace GestorListo\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Reads its order meta. Credit notes come from its paid add-on, in the same pattern.
 */
final class Wpo extends Source {

	/**
	 * Source id.
	 *
	 * @return string
	 */
	public function id() {
		return 'wpo';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function label() {
		return 'PDF Invoices & Packing Slips';
	}

	/**
	 * Installed.
	 *
	 * @return bool
	 */
	public function detect() {
		return function_exists( 'wcpdf_get_document' );
	}

	/**
	 * Orders in the range.
	 *
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return \Generator
	 */
	public function orders( $start, $end ) {
		return $this->by_time_meta( '_wcpdf_invoice_date', $start, $end );
	}

	/**
	 * Number and date from a document slug.
	 *
	 * @param \WC_Abstract_Order $order Order or refund.
	 * @param string             $slug  invoice or credit-note.
	 * @return array|null
	 */
	private function read( $order, $slug ) {
		$data   = $order->get_meta( "_wcpdf_{$slug}_number_data" );
		$number = is_array( $data ) ? ( $data['formatted_number'] ?? '' ) : '';
		if ( '' === $number ) {
			$number = (string) $order->get_meta( "_wcpdf_formatted_{$slug}_number" );
			if ( '' === $number ) {
				$number = (string) $order->get_meta( "_wcpdf_{$slug}_number" );
			}
		}
		if ( '' === $number ) {
			return null;
		}
		return array(
			'numero' => $number,
			'fecha'  => $this->day( $order->get_meta( "_wcpdf_{$slug}_date" ) ),
		);
	}

	/**
	 * Invoice.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|null
	 */
	public function invoice( $order ) {
		return $this->read( $order, 'invoice' );
	}

	/**
	 * Rectificativa.
	 *
	 * @param \WC_Order_Refund $refund Refund.
	 * @return array|null
	 */
	public function credit_note( $refund ) {
		return $this->read( $refund, 'credit-note' );
	}

	/**
	 * Has PDFs.
	 *
	 * @return bool
	 */
	public function has_pdf() {
		return true;
	}

	/**
	 * PDF of an invoice that already exists. The third argument false means it's never created here.
	 *
	 * @param \WC_Order $order Order.
	 * @return string|null
	 */
	public function pdf( $order ) {
		$document = wcpdf_get_document( 'invoice', $order, false );
		if ( ! $document || ! $document->exists() ) {
			return null;
		}
		return $document->get_pdf();
	}
}
