<?php
/**
 * Quarter dates and their Spanish labels.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Quarters as year and number. Strings are yyyy-mm-dd.
 */
final class Dates {

	const MESES = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	/**
	 * Quarter of a date.
	 *
	 * @param string $date yyyy-mm-dd.
	 * @return array Year and quarter.
	 */
	public static function quarter_of( $date ) {
		return array( (int) substr( $date, 0, 4 ), (int) ceil( (int) substr( $date, 5, 2 ) / 3 ) );
	}

	/**
	 * The quarter before.
	 *
	 * @param int $year Year.
	 * @param int $q    Quarter.
	 * @return array
	 */
	public static function previous( $year, $q ) {
		return 1 === $q ? array( $year - 1, 4 ) : array( $year, $q - 1 );
	}

	/**
	 * First and last day.
	 *
	 * @param int $year Year.
	 * @param int $q    Quarter.
	 * @return array
	 */
	public static function range( $year, $q ) {
		$first = ( $q - 1 ) * 3 + 1;
		$last  = $first + 2;
		$days  = (int) gmdate( 't', gmmktime( 0, 0, 0, $last, 1, $year ) );
		return array( sprintf( '%04d-%02d-01', $year, $first ), sprintf( '%04d-%02d-%02d', $year, $last, $days ) );
	}

	/**
	 * For example 3.er trimestre de 2026.
	 *
	 * @param int $year Year.
	 * @param int $q    Quarter.
	 * @return string
	 */
	public static function label( $year, $q ) {
		return array(
			1 => '1.er',
			'2.º',
			'3.er',
			'4.º',
		)[ $q ] . ' trimestre de ' . $year;
	}

	/**
	 * The general deadline, article 71.4 of the VAT regulation.
	 *
	 * @param int $year Year.
	 * @param int $q    Quarter.
	 * @return string
	 */
	public static function deadline( $year, $q ) {
		return array(
			1 => "$year-04-20",
			"$year-07-20",
			"$year-10-20",
			( $year + 1 ) . '-01-30',
		)[ $q ];
	}

	/**
	 * For example 20 de octubre.
	 *
	 * @param string $date yyyy-mm-dd.
	 * @param bool   $year Add the year.
	 * @return string
	 */
	public static function long( $date, $year = false ) {
		$text = (int) substr( $date, 8, 2 ) . ' de ' . self::MESES[ (int) substr( $date, 5, 2 ) - 1 ];
		return $year ? $text . ' de ' . substr( $date, 0, 4 ) : $text;
	}

	/**
	 * A date for the admin screens, in the admin's language. Files for the gestor use long().
	 *
	 * @param string $date yyyy-mm-dd.
	 * @param bool   $year Add the year.
	 * @return string
	 */
	public static function shown( $date, $year = false ) {
		/* translators: Date format for a day and month, see https://www.php.net/manual/datetime.format.php */
		$format = __( 'F j', 'gestor-listo-for-woocommerce' );
		if ( $year ) {
			/* translators: Date format for a full date, see https://www.php.net/manual/datetime.format.php */
			$format = __( 'F j, Y', 'gestor-listo-for-woocommerce' );
		}
		return wp_date( $format, strtotime( $date . ' 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * A date as dd/mm/yyyy.
	 *
	 * @param string $date yyyy-mm-dd.
	 * @return string
	 */
	public static function dmy( $date ) {
		return substr( $date, 8, 2 ) . '/' . substr( $date, 5, 2 ) . '/' . substr( $date, 0, 4 );
	}

	/**
	 * Adds days.
	 *
	 * @param string $date yyyy-mm-dd.
	 * @param int    $days Days.
	 * @return string
	 */
	public static function add( $date, $days ) {
		return gmdate( 'Y-m-d', strtotime( $date . ' 12:00:00 UTC' ) + $days * 86400 );
	}
}
