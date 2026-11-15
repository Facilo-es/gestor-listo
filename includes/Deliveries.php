<?php
/**
 * Delivery history, fingerprints and the 90-day clean-up, F7 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
/**
 * The two tables.
 */
final class Deliveries {

	/**
	 * Daily clean-up.
	 */
	public static function hooks() {
		add_action( 'gestlisto_daily', array( self::class, 'cleanup' ) );
		if ( ! wp_next_scheduled( 'gestlisto_daily' ) ) {
			$nine = strtotime( wp_date( 'Y-m-d' ) . ' 09:00:00 ' . wp_timezone_string() );
			wp_schedule_event( $nine + DAY_IN_SECONDS, 'daily', 'gestlisto_daily' );
		}
	}

	/**
	 * Creates the tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}gestlisto_deliveries (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			quarter varchar(8) NOT NULL,
			created datetime NOT NULL,
			delivered datetime NULL,
			recipient varchar(190) NOT NULL DEFAULT '',
			doc_count int NOT NULL DEFAULT 0,
			total decimal(14,2) NOT NULL DEFAULT 0,
			file_path varchar(190) NOT NULL DEFAULT '',
			token_hash char(64) NOT NULL DEFAULT '',
			expiry datetime NULL,
			PRIMARY KEY  (id),
			KEY quarter (quarter)
			) $c;
			CREATE TABLE {$wpdb->prefix}gestlisto_delivered_docs (
			delivery_id bigint unsigned NOT NULL,
			doc_key varchar(32) NOT NULL,
			fingerprint char(32) NOT NULL,
			KEY delivery (delivery_id)
			) $c;"
		);
	}

	/**
	 * Adds a history row with its fingerprints.
	 *
	 * @param string $quarter For example 2026-3.
	 * @param array  $docs    Documents in the pack.
	 * @param string $file    Stored file name.
	 * @return int Row id.
	 */
	public static function add( $quarter, array $docs, $file ) {
		global $wpdb;
		$kept = array_filter(
			$docs,
			function ( $doc ) {
				return ! Document::excluded( $doc );
			}
		);
		$wpdb->insert(
			"{$wpdb->prefix}gestlisto_deliveries",
			array(
				'quarter'   => $quarter,
				'created'   => current_time( 'mysql' ),
				'doc_count' => count( $kept ),
				'total'     => Totals::of( $docs )['total'],
				'file_path' => $file,
			)
		);
		$id = (int) $wpdb->insert_id;
		foreach ( $kept as $doc ) {
			$wpdb->insert(
				"{$wpdb->prefix}gestlisto_delivered_docs",
				array(
					'delivery_id' => $id,
					'doc_key'     => $doc['key'],
					'fingerprint' => Document::fingerprint( $doc ),
				)
			);
		}
		return $id;
	}

	/**
	 * Marks a row as delivered.
	 *
	 * @param int    $id        Row.
	 * @param string $recipient Who got it.
	 * @param string $when      Date and time, or empty for now.
	 */
	public static function mark( $id, $recipient, $when = '' ) {
		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}gestlisto_deliveries",
			array(
				'delivered' => $when ? $when : Quarter::today() . current_time( ' H:i:s' ),
				'recipient' => $recipient,
			),
			array( 'id' => $id )
		);
	}

	/**
	 * All rows, newest first.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gestlisto_deliveries ORDER BY id DESC" );
	}

	/**
	 * One row.
	 *
	 * @param int $id Row.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gestlisto_deliveries WHERE id = %d", $id ) );
	}

	/**
	 * First delivered row of a quarter, and every delivered fingerprint.
	 *
	 * @param string $quarter Quarter.
	 * @return array Row, and key => last fingerprint delivered.
	 */
	public static function delivered( $quarter ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gestlisto_deliveries WHERE quarter = %s AND delivered IS NOT NULL ORDER BY id ASC", $quarter ) );
		if ( ! $rows ) {
			return array( null, array() );
		}
		$prints = array();
		foreach ( $rows as $row ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT doc_key, fingerprint FROM {$wpdb->prefix}gestlisto_delivered_docs WHERE delivery_id = %d", $row->id ) ) as $doc ) {
				$prints[ $doc->doc_key ] = $doc->fingerprint;
			}
		}
		return array( end( $rows ), $prints );
	}

	/**
	 * What changed since delivery.
	 *
	 * @param string $quarter Quarter.
	 * @param array  $docs    Current documents.
	 * @return array row, nuevas, cambiadas, keys.
	 */
	public static function changes( $quarter, array $docs ) {
		list( $row, $prints ) = self::delivered( $quarter );
		$new                  = array();
		$changed              = array();
		if ( $row ) {
			foreach ( $docs as $doc ) {
				if ( Document::excluded( $doc ) ) {
					continue;
				}
				if ( ! isset( $prints[ $doc['key'] ] ) ) {
					$new[] = $doc['key'];
				} elseif ( Document::fingerprint( $doc ) !== $prints[ $doc['key'] ] ) {
					$changed[] = $doc['key'];
				}
			}
		}
		return array(
			'row'       => $row,
			'nuevas'    => $new,
			'cambiadas' => $changed,
		);
	}

	/**
	 * Deletes files older than 90 days. The history row stays.
	 *
	 * @param int $now Unix time, for tests.
	 */
	public static function cleanup( $now = 0 ) {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', ( $now ? $now : time() ) - 90 * DAY_IN_SECONDS );
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, file_path FROM {$wpdb->prefix}gestlisto_deliveries WHERE file_path <> '' AND created < %s", $before ) ) as $row ) {
			wp_delete_file( Pack::folder() . '/' . basename( $row->file_path ) );
			$wpdb->update( "{$wpdb->prefix}gestlisto_deliveries", array( 'file_path' => '' ), array( 'id' => $row->id ) );
		}
	}
}
// phpcs:enable
