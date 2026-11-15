<?php
/**
 * A small XLSX writer on ZipArchive and plain XML, with no library.
 *
 * @package GestorListo
 */

namespace GestorListo\Writers;

defined( 'GESTLISTO_PURE' ) || defined( 'ABSPATH' ) || exit;

/**
 * Sheets are name => array( 'rows' => row number => column => value, 'merges' => refs, 'bold' => last header row ).
 * Numbers become numeric cells and strings become text.
 */
final class Xlsx {

	/**
	 * Writes a workbook.
	 *
	 * @param string $path   Target file.
	 * @param array  $sheets Sheets.
	 * @return bool
	 */
	public static function write( $path, array $sheets ) {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$strings = array();
		$names   = array_keys( $sheets );
		$over    = '';
		$list    = '';
		$rels    = '';
		foreach ( $names as $i => $name ) {
			$n = $i + 1;
			$zip->addFromString( "xl/worksheets/sheet$n.xml", self::sheet( $sheets[ $name ], $strings ) );
			$over .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$list .= '<sheet name="' . self::x( $name ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		}
		$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$next = count( $names ) + 1;
		$zip->addFromString(
			'[Content_Types].xml',
			$head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $over . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>'
		);
		$zip->addFromString( '_rels/.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $list . '</sheets></workbook>' );
		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			$head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . $next . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId' . ( $next + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>'
		);
		$zip->addFromString(
			'xl/styles.xml',
			$head . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf></cellXfs></styleSheet>'
		);
		$items = '';
		foreach ( array_keys( $strings ) as $text ) {
			$items .= '<si><t xml:space="preserve">' . self::x( (string) $text ) . '</t></si>';
		}
		$zip->addFromString( 'xl/sharedStrings.xml', $head . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">' . $items . '</sst>' );
		return $zip->close();
	}

	/**
	 * Sheet XML.
	 *
	 * @param array $sheet   Sheet.
	 * @param array $strings Shared strings, added to.
	 * @return string
	 */
	private static function sheet( array $sheet, array &$strings ) {
		$bold = $sheet['bold'] ?? 0;
		$out  = '';
		ksort( $sheet['rows'] );
		foreach ( $sheet['rows'] as $r => $cells ) {
			uksort(
				$cells,
				function ( $a, $b ) {
					return self::index( $a ) <=> self::index( $b );
				}
			);
			$out .= '<row r="' . $r . '">';
			foreach ( $cells as $col => $value ) {
				if ( '' === $value || null === $value ) {
					continue;
				}
				$style = $r <= $bold ? ' s="2"' : '';
				if ( is_float( $value ) ) {
					$out .= '<c r="' . $col . $r . '" s="1"><v>' . number_format( $value, 2, '.', '' ) . '</v></c>';
				} elseif ( is_int( $value ) ) {
					$out .= '<c r="' . $col . $r . '"' . $style . '><v>' . $value . '</v></c>';
				} else {
					if ( ! isset( $strings[ $value ] ) ) {
						$strings[ $value ] = count( $strings );
					}
					$out .= '<c r="' . $col . $r . '"' . $style . ' t="s"><v>' . $strings[ $value ] . '</v></c>';
				}
			}
			$out .= '</row>';
		}
		$merges = '';
		if ( ! empty( $sheet['merges'] ) ) {
			$merges = '<mergeCells count="' . count( $sheet['merges'] ) . '">';
			foreach ( $sheet['merges'] as $ref ) {
				$merges .= '<mergeCell ref="' . $ref . '"/>';
			}
			$merges .= '</mergeCells>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $out . '</sheetData>' . $merges . '</worksheet>';
	}

	/**
	 * Column letters as a number.
	 *
	 * @param string $col Column.
	 * @return int
	 */
	private static function index( $col ) {
		$n = 0;
		foreach ( str_split( $col ) as $c ) {
			$n = $n * 26 + ord( $c ) - 64;
		}
		return $n;
	}

	/**
	 * XML escape, dropping characters XML can't hold.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function x( $text ) {
		return htmlspecialchars( preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
