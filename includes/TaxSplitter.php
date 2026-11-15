<?php
/**
 * Splits a document into one base per VAT rate, section 6.2 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Pure: plain data in, plain data out.
 */
final class TaxSplitter {

	const TOLERANCE = 0.02;

	/**
	 * Splits a document.
	 *
	 * @param array $items  Each with net and taxes (rate id => amount).
	 * @param array $stored Stored tax by rate id, tax plus shipping tax.
	 * @param array $rates  Rate id => kind, pct, country, name.
	 * @param float $total  Document total.
	 * @return array lineas, iva_extranjero, unmapped, suma, cuadra, a8.
	 */
	public static function split( array $items, array $stored, array $rates, $total ) {
		$groups   = array();
		$unmapped = array();
		foreach ( $items as $item ) {
			$key = self::group_for( $item, $rates, $unmapped );
			if ( ! isset( $groups[ $key['id'] ] ) ) {
				$groups[ $key['id'] ] = $key + array(
					'net'   => 0.0,
					'cuota' => 0.0,
					're'    => 0.0,
				);
			}
			$groups[ $key['id'] ]['net'] += (float) $item['net'];
		}

		$used = array();
		foreach ( $groups as $id => $group ) {
			foreach ( array(
				'iva_rate' => 'cuota',
				're_rate'  => 're',
			) as $rate_key => $field ) {
				$rate = $group[ $rate_key ];
				if ( null !== $rate && ! isset( $used[ $rate ] ) ) {
					$groups[ $id ][ $field ] = round( (float) ( $stored[ $rate ] ?? 0 ), 2 );
					$used[ $rate ]           = true;
				}
			}
			$groups[ $id ]['base'] = round( $group['net'], 2 );
		}

		$suma = 0.0;
		foreach ( $groups as $group ) {
			$suma += $group['base'] + $group['cuota'] + $group['re'];
		}
		$suma   = round( $suma, 2 );
		$diff   = round( (float) $total - $suma, 2 );
		$cuadra = abs( $diff ) <= self::TOLERANCE + 1e-9;
		if ( $cuadra && 0.0 !== $diff && $groups ) {
			$largest = null;
			foreach ( $groups as $id => $group ) {
				if ( null === $largest || abs( $group['base'] ) > abs( $groups[ $largest ]['base'] ) ) {
					$largest = $id;
				}
			}
			$groups[ $largest ]['base'] = round( $groups[ $largest ]['base'] + $diff, 2 );
		}

		$lineas  = array();
		$foreign = array();
		$a8      = false;
		foreach ( $groups as $group ) {
			if ( 'oss' === $group['kind'] ) {
				$foreign[] = array(
					'pais'  => $group['country'],
					'tipo'  => $group['tipo'],
					'base'  => $group['base'],
					'cuota' => $group['cuota'],
				);
				$lineas[]  = self::line( 0.0, $group['base'], 0.0, 0.0, 0.0 );
				continue;
			}
			if ( 'iva' === $group['kind'] && abs( $group['cuota'] - $group['base'] * $group['tipo'] / 100 ) > self::TOLERANCE + 1e-9 ) {
				$a8 = true;
			}
			$lineas[] = self::line( $group['tipo'], $group['base'], $group['cuota'], $group['tipo_re'], $group['re'] );
		}
		usort(
			$lineas,
			function ( $a, $b ) {
				return $b['tipo_iva'] <=> $a['tipo_iva'];
			}
		);

		return array(
			'lineas'         => $lineas,
			'iva_extranjero' => $foreign,
			'unmapped'       => array_values( array_unique( $unmapped ) ),
			'suma'           => $suma,
			'cuadra'         => $cuadra,
			'a8'             => $a8,
		);
	}

	/**
	 * Which group an item goes to. Recargo joins the IVA of the same item.
	 *
	 * @param array $item     The item.
	 * @param array $rates    Rate map.
	 * @param array $unmapped Names of unmapped rates, added to.
	 * @return array
	 */
	private static function group_for( array $item, array $rates, array &$unmapped ) {
		$group = array(
			'id'       => 'zero',
			'kind'     => 'zero',
			'tipo'     => 0.0,
			'tipo_re'  => 0.0,
			'country'  => '',
			'iva_rate' => null,
			're_rate'  => null,
		);
		foreach ( array_keys( $item['taxes'] ?? array() ) as $rate_id ) {
			$rate = $rates[ $rate_id ] ?? null;
			$kind = $rate['kind'] ?? '';
			if ( 'iva' === $kind || 'oss' === $kind ) {
				$group['kind']     = $kind;
				$group['tipo']     = (float) $rate['pct'];
				$group['country']  = $rate['country'] ?? '';
				$group['iva_rate'] = $rate_id;
			} elseif ( 'recargo' === $kind ) {
				$group['tipo_re'] = (float) $rate['pct'];
				$group['re_rate'] = $rate_id;
			} elseif ( 'exento' !== $kind ) {
				$unmapped[] = $rate['name'] ?? (string) $rate_id;
			}
		}
		$group['id'] = implode( '|', array( $group['kind'], $group['tipo'], $group['tipo_re'], $group['country'] ) );
		return $group;
	}

	/**
	 * One line of the document.
	 *
	 * @param float $tipo     IVA percent.
	 * @param float $base     Base.
	 * @param float $cuota    IVA amount.
	 * @param float $tipo_re  Recargo percent.
	 * @param float $cuota_re Recargo amount.
	 * @return array
	 */
	private static function line( $tipo, $base, $cuota, $tipo_re, $cuota_re ) {
		return array(
			'tipo_iva' => $tipo,
			'base'     => $base,
			'cuota'    => $cuota,
			'tipo_re'  => $tipo_re,
			'cuota_re' => $cuota_re,
		);
	}
}
