<?php
/**
 * Three-step setup, F1 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * All three steps sit on one page, and each set of fields is shared with Ajustes.
 */
final class Setup {

	/**
	 * Nothing to hook yet.
	 */
	public static function hooks() {
	}

	/**
	 * Company fields.
	 *
	 * @param array $s Settings.
	 */
	public static function company_fields( array $s ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['nif'] ) ) {
			echo '<p class="aviso">' . esc_html__( 'This tax ID (NIF) doesn\'t look right. Please check it.', 'gestor-listo-for-woocommerce' ) . '</p>';
		}
		echo '<label>' . esc_html__( 'Legal name', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="nombre" value="' . esc_attr( $s['nombre'] ) . '" required></label>';
		echo '<label>' . esc_html__( 'Tax ID (NIF)', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="nif" value="' . esc_attr( $s['nif'] ) . '" required></label>';
		echo '<label>' . esc_html__( 'Type of business', 'gestor-listo-for-woocommerce' ) . ' <select name="tipo"><option value="sociedad"' . selected( $s['tipo'], 'sociedad', false ) . '>' . esc_html__( 'Company', 'gestor-listo-for-woocommerce' ) . '</option><option value="autonomo"' . selected( $s['tipo'], 'autonomo', false ) . '>' . esc_html__( 'Self-employed', 'gestor-listo-for-woocommerce' ) . '</option></select></label><p></p>';
	}

	/**
	 * Invoice source fields.
	 *
	 * @param array $s Settings.
	 */
	public static function invoice_fields( array $s ) {
		$found = Settings::source();
		/* translators: %s: name of the invoice plugin. */
		$text = $found->numbered() ? sprintf( __( 'We found %s. We\'ll read your invoices from there.', 'gestor-listo-for-woocommerce' ), $found->label() ) : Admin::no_invoice_plugin();
		echo '<p>' . esc_html( $text ) . '</p>';
		echo '<label>' . esc_html__( 'Invoice plugin', 'gestor-listo-for-woocommerce' ) . ' <select name="source">';
		foreach ( Settings::sources() as $source ) {
			if ( $source->detect() || 'meta' === $source->id() ) {
				echo '<option value="' . esc_attr( $source->id() ) . '"' . selected( $found->id(), $source->id(), false ) . '>' . esc_html( $source->label() ) . '</option>';
			}
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Tax ID (NIF) field on the order', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="nif_meta" value="' . esc_attr( '' !== (string) $s['nif_meta'] ? $s['nif_meta'] : Settings::detect_nif_field() ) . '"></label>';
		echo '<label>' . esc_html__( 'Other plugin: number field', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="number_meta" value="' . esc_attr( $s['number_meta'] ) . '"></label>';
		echo '<label>' . esc_html__( 'Other plugin: date field', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="date_meta" value="' . esc_attr( $s['date_meta'] ) . '"></label><p></p>';
	}

	/**
	 * Tax map fields.
	 *
	 * @param array $s Settings.
	 */
	public static function tax_fields( array $s ) {
		$rates = Settings::wc_rates();
		if ( ! $rates ) {
			echo '<p>' . esc_html__( 'Your shop has no taxes yet.', 'gestor-listo-for-woocommerce' ) . '</p>';
		}
		echo '<table class="izq"><tbody>';
		foreach ( $rates as $id => $rate ) {
			$kind = $s['tax_map'][ $id ] ?? Settings::suggest( $rate );
			echo '<tr><th scope="row">' . esc_html( $rate['name'] . ' · ' . Format::pct( $rate['pct'] ) . ' %' ) . '</th><td><select name="tax[' . (int) $id . ']"><option value="">' . esc_html__( 'Not assigned', 'gestor-listo-for-woocommerce' ) . '</option>';
			foreach ( array(
				'iva'     => __( 'VAT', 'gestor-listo-for-woocommerce' ),
				'recargo' => __( 'Surcharge', 'gestor-listo-for-woocommerce' ),
				'oss'     => 'OSS',
				'exento'  => __( 'Exempt', 'gestor-listo-for-woocommerce' ),
			) as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $kind, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The setup page.
	 */
	public static function render() {
		$s = Settings::all();
		echo '<div class="gl"><div class="alto gl-pad-bottom"><div class="top gl-flush"><h1>' . esc_html__( 'Get your quarter ready in three steps', 'gestor-listo-for-woocommerce' ) . '</h1></div></div>';
		echo '<form class="f pasos" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gestlisto_save' );
		echo '<input type="hidden" name="action" value="gestlisto_save"><input type="hidden" name="back" value="setup">';
		echo '<section class="bloque"><h3>' . esc_html__( '1. Your business', 'gestor-listo-for-woocommerce' ) . '</h3><p>' . esc_html__( 'These details appear in the files your gestor receives.', 'gestor-listo-for-woocommerce' ) . '</p>';
		self::company_fields( $s );
		echo '</section><section class="bloque"><h3>' . esc_html__( '2. Your invoices', 'gestor-listo-for-woocommerce' ) . '</h3>';
		self::invoice_fields( $s );
		echo '</section><section class="bloque"><h3>' . esc_html__( '3. Your taxes', 'gestor-listo-for-woocommerce' ) . '</h3><p>' . esc_html__( 'We\'ve sorted your taxes. If one doesn\'t fit, change it here.', 'gestor-listo-for-woocommerce' ) . '</p>';
		self::tax_fields( $s );
		echo '<p></p></section><p><button class="btn">' . esc_html__( 'See my quarter', 'gestor-listo-for-woocommerce' ) . '</button></p><p class="tranq">' . esc_html__( 'Gestor Listo only reads your data. It never creates or changes invoices or orders.', 'gestor-listo-for-woocommerce' ) . '</p></form></div>';
	}
}
