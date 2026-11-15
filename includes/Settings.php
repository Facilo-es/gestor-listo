<?php
/**
 * Settings and the tax map.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * One option holds everything.
 */
final class Settings {

	/**
	 * Activity code and type for the book, as the Tax Agency lists them.
	 */
	const ACTIVIDADES = array(
		'A03' => 'Empresarial: venta de productos o servicios (lo habitual en una tienda)',
		'A05' => 'Profesional',
		'A04' => 'Profesional artística o deportiva',
		'B06' => 'No sujeta al IAE',
	);

	/**
	 * The same activities, with their names in the admin's language.
	 *
	 * @return array
	 */
	public static function activities() {
		return array(
			'A03' => __( 'Business: selling products or services (the usual for a shop)', 'gestor-listo-for-woocommerce' ),
			'A05' => __( 'Professional', 'gestor-listo-for-woocommerce' ),
			'A04' => __( 'Artistic or sports professional', 'gestor-listo-for-woocommerce' ),
			'B06' => __( 'Not subject to IAE', 'gestor-listo-for-woocommerce' ),
		);
	}

	const NIF_KEYS = array( '_billing_nif', 'billing_nif', '_billing_vat', '_billing_cif', '_billing_dni', '_vat_number' );

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function all() {
		return wp_parse_args(
			get_option( 'gestlisto_settings', array() ),
			array(
				'nombre'              => get_bloginfo( 'name' ),
				'nif'                 => '',
				'tipo'                => 'sociedad',
				'source'              => '',
				'nif_meta'            => '',
				'number_meta'         => '',
				'date_meta'           => '',
				'statuses'            => array( 'completed', 'processing' ),
				'tax_map'             => array(),
				'actividad_codigo'    => 'A',
				'actividad_tipo'      => '03',
				'epigrafe'            => '',
				'retail'              => true,
				'tipo_rectificativa'  => 'R1',
				'delete_on_uninstall' => false,
				'setup_done'          => false,
			)
		);
	}

	/**
	 * Saves some keys.
	 *
	 * @param array $values Values.
	 */
	public static function save( array $values ) {
		update_option( 'gestlisto_settings', array_merge( self::all(), $values ), false );
		Quarter::flush();
	}

	/**
	 * Available sources, in detection order.
	 *
	 * @return Sources\Source[]
	 */
	public static function sources() {
		$s = self::all();
		return apply_filters(
			'gestlisto_document_sources',
			array(
				new Sources\Wpo(),
				new Sources\WebToffee(),
				new Sources\MetaKey( $s['number_meta'], $s['date_meta'] ),
				new Sources\OrderNumber( $s['statuses'] ),
			)
		);
	}

	/**
	 * The source in use.
	 *
	 * @return Sources\Source
	 */
	public static function source() {
		$id      = self::all()['source'];
		$sources = self::sources();
		foreach ( $sources as $source ) {
			if ( $source->id() === $id ) {
				return $source;
			}
		}
		foreach ( $sources as $source ) {
			if ( $source->detect() ) {
				return $source;
			}
		}
		return end( $sources );
	}

	/**
	 * The NIF field filled most often in the last 200 orders.
	 *
	 * @return string
	 */
	public static function detect_nif_field() {
		$counts = array_fill_keys( self::NIF_KEYS, 0 );
		foreach ( wc_get_orders(
			array(
				'limit' => 200,
				'type'  => 'shop_order',
			)
		) as $order ) {
			foreach ( self::NIF_KEYS as $key ) {
				if ( '' !== (string) $order->get_meta( $key ) ) {
					++$counts[ $key ];
				}
			}
		}
		arsort( $counts );
		$best = (string) key( $counts );
		return reset( $counts ) > 0 ? $best : '_billing_nif';
	}

	/**
	 * WooCommerce's tax rates.
	 *
	 * @return array Rate id => country, pct, name.
	 */
	public static function wc_rates() {
		global $wpdb;
		$out = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $wpdb->get_results( "SELECT tax_rate_id, tax_rate_country, tax_rate, tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates" ) as $row ) {
			$out[ (int) $row->tax_rate_id ] = array(
				'country' => $row->tax_rate_country,
				'pct'     => (float) $row->tax_rate,
				'name'    => $row->tax_rate_name . ( $row->tax_rate_country ? ' (' . $row->tax_rate_country . ')' : '' ),
			);
		}
		return $out;
	}

	/**
	 * Suggested kind for a rate.
	 *
	 * @param array $rate Country and pct.
	 * @return string
	 */
	public static function suggest( array $rate ) {
		$spain = in_array( $rate['country'], array( '', 'ES' ), true );
		if ( 0.0 === $rate['pct'] ) {
			return 'exento';
		}
		if ( $spain && in_array( $rate['pct'], array( 21.0, 10.0, 4.0 ), true ) ) {
			return 'iva';
		}
		if ( $spain && in_array( $rate['pct'], array( 5.2, 1.75, 1.4, 0.5 ), true ) ) {
			return 'recargo';
		}
		return ! $spain && in_array( $rate['country'], Classifier::EU, true ) ? 'oss' : '';
	}

	/**
	 * Rates as the pure code wants them, with the owner's map. Unmapped ones stay unmapped until setup is saved.
	 *
	 * @return array
	 */
	public static function rates() {
		$map = self::all()['tax_map'];
		$out = array();
		foreach ( self::wc_rates() as $id => $rate ) {
			$out[ $id ] = $rate + array( 'kind' => $map[ $id ] ?? '' );
		}
		return $out;
	}

	/**
	 * Shop data for the writers.
	 *
	 * @return array
	 */
	public static function shop() {
		$s = self::all();
		return array(
			'nombre'           => $s['nombre'],
			'nif'              => $s['nif'],
			'tipo'             => $s['tipo'],
			'actividad_codigo' => $s['actividad_codigo'],
			'actividad_tipo'   => $s['actividad_tipo'],
			'epigrafe'         => $s['epigrafe'],
		);
	}
}
