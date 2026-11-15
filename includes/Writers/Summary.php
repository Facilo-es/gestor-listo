<?php
/**
 * The gestor's first page, resumen.html.
 *
 * @package GestorListo
 */

namespace GestorListo\Writers;

use GestorListo\Checks;
use GestorListo\Document;
use GestorListo\Format;
use GestorListo\Totals;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * One self-contained page, for screen and paper.
 */
final class Summary {

	/**
	 * Escapes.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function e( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The page.
	 *
	 * @param array $docs    Documents, with checks.
	 * @param array $notes   Quarter-level checks.
	 * @param array $shop    Shop: nombre, nif.
	 * @param array $meta    label, generated.
	 * @param array $imports Format name => import lines.
	 * @return string
	 */
	public static function html( array $docs, array $notes, array $shop, array $meta, array $imports = array() ) {
		$t    = Totals::of( $docs );
		$rows = '';
		foreach ( $t['rows'] as $row ) {
			$rows .= '<tr><th>' . self::e( $row['label'] ) . '</th><td>' . Format::money( $row['base'] ) . '</td><td>' . ( $row['iva'] ? Format::money( $row['iva'] ) : '—' ) . '</td><td>' . ( $row['re'] ? Format::money( $row['re'] ) : '—' ) . '</td><td><b>' . Format::money( $row['total'] ) . '</b></td></tr>';
		}
		$out  = '';
		$warn = '';
		foreach ( $docs as $doc ) {
			foreach ( $doc['checks'] as $check ) {
				if ( 'E' === $check['code'][0] ) {
					$out .= '<li>' . self::e( Checks::text( $check, $doc ) ) . '</li>';
				} else {
					$warn .= '<li>' . self::e( Checks::text( $check, $doc ) ) . '</li>';
				}
			}
		}
		foreach ( $notes as $check ) {
			$warn .= '<li>' . self::e( Checks::text( $check ) ) . '</li>';
		}
		$changes = '';
		foreach ( $docs as $doc ) {
			if ( ! empty( $doc['cambio'] ) ) {
				$changes .= '<li>' . self::e( $doc['numero_completo'] . ': ' . $doc['cambio'] ) . '</li>';
			}
		}
		$oss = '';
		foreach ( $t['oss'] as $row ) {
			$oss .= '<tr><th>' . self::e( $row['pais'] ) . ' ' . Format::pct( $row['tipo'] ) . ' %</th><td>' . Format::money( $row['base'] ) . '</td><td>' . Format::money( $row['iva'] ) . '</td></tr>';
		}
		$how = '';
		foreach ( $imports as $name => $lines ) {
			$how .= '<h3>' . self::e( $name ) . '</h3><ol>';
			foreach ( $lines as $line ) {
				$how .= '<li>' . self::e( $line ) . '</li>';
			}
			$how .= '</ol>';
		}
		$card = function ( $label, $value, $extra = '' ) {
			return '<div class="c' . $extra . '"><span>' . $label . '</span><b>' . $value . '</b></div>';
		};
		return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resumen ' . self::e( $meta['label'] ) . '</title><style>'
			. '*{box-sizing:border-box}body{margin:0;background:#F5F4EC;color:#15251F;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-variant-numeric:tabular-nums lining-nums}'
			. 'header{background:#123B30;color:#fff;padding:34px 40px}header h1{margin:0;font-size:30px;letter-spacing:-.02em}header p{margin:6px 0 0;color:#CFE0D6}'
			. 'main{max-width:960px;margin:0 auto;padding:26px 24px 40px}.cs{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}.c{background:#fff;border-radius:16px;padding:14px 16px}.c span{display:block;color:#51625A;font-size:13px;font-weight:600}.c b{font-size:21px;white-space:nowrap}.c.t{background:#123B30;color:#fff}.c.t span{color:#CFE0D6}'
			. 'section{background:#fff;border-radius:18px;padding:18px 22px;margin-bottom:14px}h2{font-size:17px;margin:0 0 8px}h3{font-size:14px;margin:12px 0 2px}table{width:100%;border-collapse:collapse}th,td{padding:7px 0 7px 12px;text-align:right;border-top:1px solid #EEEFE5;white-space:nowrap}thead th{border:0;color:#51625A;font-weight:600}th:first-child{text-align:left;padding-left:0;font-weight:500;white-space:normal}ul,ol{margin:4px 0;padding-left:20px}.foot{color:#51625A;font-size:13px}'
			. '@media (max-width:640px){.cs{grid-template-columns:1fr 1fr}}@media print{body{background:#fff;font-size:12px}header{background:none;color:#000;padding:0 0 10px;border-bottom:2px solid #123B30}header p{color:#333}main{max-width:none;padding:12px 0}.c,.c.t,section{background:#fff;color:#000;border:1px solid #ccc;break-inside:avoid}.c.t span{color:#333}}'
			. '</style></head><body><header><h1>' . self::e( $shop['nombre'] ) . '</h1><p>NIF ' . self::e( $shop['nif'] ) . ' · ' . self::e( ucfirst( $meta['label'] ) ) . ' · Generado el ' . self::e( $meta['generated'] ) . '</p></header><main>'
			. '<div class="cs">' . $card( 'Facturas', $t['facturas'] ) . $card( 'Base imponible', Format::money( $t['base'] ) . ' €' ) . $card( 'IVA', Format::money( $t['iva'] ) . ' €' ) . $card( 'Total', Format::money( $t['total'] ) . ' €', ' t' ) . '</div>'
			. '<section><h2>IVA por tipo</h2><table><thead><tr><th>Tipo</th><th>Base</th><th>IVA</th><th>Recargo</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table></section>'
			. ( $oss ? '<section><h2>IVA de otros países (OSS)</h2><table><thead><tr><th>País y tipo</th><th>Base</th><th>IVA</th></tr></thead><tbody>' . $oss . '</tbody></table></section>' : '' )
			. ( $changes ? '<section><h2>Cambios desde la última entrega</h2><ul>' . $changes . '</ul></section>' : '' )
			. ( $out ? '<section><h2>Documentos que quedan fuera</h2><ul>' . $out . '</ul></section>' : '' )
			. ( $warn ? '<section><h2>Avisos</h2><ul>' . $warn . '</ul></section>' : '' )
			. ( $how ? '<section><h2>Cómo importar</h2>' . $how . '</section>' : '' )
			. '<p class="foot">Este resumen se ha generado con datos de la tienda. Revísalo antes de presentar ningún modelo.</p></main></body></html>';
	}
}
