<?php
/**
 * A quarter's documents and checks, cached until an order changes.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and caches quarters.
 */
final class Quarter {

	/**
	 * Clears the cache when orders change.
	 */
	public static function hooks() {
		foreach ( array( 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_order_refunded', 'woocommerce_refund_deleted', 'woocommerce_order_status_changed' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
	}

	/**
	 * Drops every cached quarter by moving the version on.
	 */
	public static function flush() {
		update_option( 'gestlisto_cache_version', (int) get_option( 'gestlisto_cache_version', 0 ) + 1, false );
	}

	/**
	 * Today in shop time.
	 *
	 * @return string
	 */
	public static function today() {
		return (string) apply_filters( 'gestlisto_today', wp_date( 'Y-m-d' ) );
	}

	/**
	 * The last finished quarter.
	 *
	 * @return array Year and quarter.
	 */
	public static function last_finished() {
		list( $y, $q ) = Dates::quarter_of( self::today() );
		return Dates::previous( $y, $q );
	}

	/**
	 * Loads a quarter.
	 *
	 * @param int  $year Year.
	 * @param int  $q    Quarter.
	 * @param bool $a7   Add the yearly OSS warning.
	 * @return array docs, notes, totals.
	 */
	public static function load( $year, $q, $a7 = true ) {
		$key  = 'gestlisto_q' . (int) get_option( 'gestlisto_cache_version', 0 ) . '_' . $year . '_' . $q;
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			list( $start, $end )    = Dates::range( $year, $q );
			list( $docs, $missing ) = Reader::read( $start, $end );
			$s                      = Settings::all();
			list( $docs, $gaps )    = Checks::run( $docs, $s );
			$docs                   = apply_filters( 'gestlisto_documents', $docs );

			$data = array(
				'docs'  => $docs,
				'notes' => array_merge( $missing, $gaps ),
			);
			set_transient( $key, $data, DAY_IN_SECONDS );
		}
		if ( $a7 ) {
			$x = 0.0;
			for ( $i = 1; $i <= $q; $i++ ) {
				$x += self::eu_sales( $year, $i, $i === $q ? $data : null );
			}
			if ( $x >= 8000 ) {
				$data['notes'][] = array(
					'code' => 'A7',
					'args' => array( 'x' => $x ),
				);
			}
		}
		$data['totals'] = Totals::of( $data['docs'] );
		return $data;
	}

	/**
	 * A quarter's sales to EU consumers, kept on its own so A7 never reloads earlier quarters whole.
	 *
	 * @param int        $year Year.
	 * @param int        $q    Quarter.
	 * @param array|null $data The quarter, when it's already loaded.
	 * @return float
	 */
	private static function eu_sales( $year, $q, $data = null ) {
		$key = 'gestlisto_x' . (int) get_option( 'gestlisto_cache_version', 0 ) . '_' . $year . '_' . $q;
		$x   = get_transient( $key );
		if ( false === $x ) {
			$x    = 0.0;
			$data = $data ? $data : self::load( $year, $q, false );
			foreach ( $data['docs'] as $doc ) {
				if ( 'ES' !== $doc['cliente']['pais'] && '' === $doc['cliente']['nif'] && in_array( $doc['clase'], array( 'oss', 'interior' ), true ) ) {
					$x += array_sum( array_column( $doc['lineas'], 'base' ) );
				}
			}
			set_transient( $key, $x, DAY_IN_SECONDS );
		}
		return (float) $x;
	}

	/**
	 * Every issue of a quarter, errors first.
	 *
	 * @param array $data From load().
	 * @return array Each with level, text and order_id.
	 */
	public static function issues( array $data ) {
		$out = array();
		foreach ( $data['docs'] as $doc ) {
			foreach ( $doc['checks'] as $check ) {
				$out[] = array(
					'error'    => 'E' === $check['code'][0],
					'text'     => Checks::message( $check, $doc ),
					'order_id' => $doc['order_id'],
				);
			}
		}
		foreach ( $data['notes'] as $check ) {
			$out[] = array(
				'error'    => false,
				'text'     => Checks::message( $check ),
				'order_id' => $check['args']['order_id'] ?? 0,
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return (int) $b['error'] <=> (int) $a['error'];
			}
		);
		return $out;
	}
}
