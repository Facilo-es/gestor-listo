<?php
/**
 * Plugin Name:       Gestor Listo for WooCommerce
 * Description:       Hand your gestor the quarter's sales in one click: invoice book, VAT summary and a ZIP with every invoice.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Facilo
 * License:           GPLv2 or later
 * Text Domain:       gestor-listo-for-woocommerce
 * WC requires at least: 9.0
 *
 * @package GestorListo
 */

defined( 'ABSPATH' ) || exit;

define( 'GESTLISTO_VERSION', '1.0.0' );
define( 'GESTLISTO_FILE', __FILE__ );
define( 'GESTLISTO_DIR', plugin_dir_path( __FILE__ ) );

foreach ( array( 'Nif', 'Dates', 'Format', 'TaxSplitter', 'Classifier', 'Document', 'Checks', 'Totals', 'Writers/Guard', 'Writers/Xlsx', 'Writers/BookHeaders', 'Writers/Book', 'Writers/Csv', 'Writers/Summary', 'Sources/Source', 'Sources/Wpo', 'Sources/WebToffee', 'Sources/MetaKey', 'Sources/OrderNumber', 'Settings', 'Reader', 'Quarter', 'Deliveries', 'Pack', 'Admin', 'Setup' ) as $gestlisto_file ) {
	require GESTLISTO_DIR . 'includes/' . $gestlisto_file . '.php';
}

register_activation_hook( __FILE__, array( 'GestorListo\Deliveries', 'install' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GESTLISTO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GESTLISTO_FILE, true );
		}
	}
);

add_action(
	'init',
	function () {
		// The wordpress.org copy ships no translations and gets language packs. The GitHub copy brings
		// its own in languages/, used only until WordPress has a pack for this locale.
		$domain = 'gestor-listo-for-woocommerce';
		$locale = determine_locale();
		$ours   = GESTLISTO_DIR . 'languages/' . $domain . '-' . $locale . '.mo';
		$theirs = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
		if ( ! is_textdomain_loaded( $domain ) && is_readable( $ours ) && ! is_readable( $theirs . '.mo' ) && ! is_readable( $theirs . '.l10n.php' ) ) {
			load_textdomain( $domain, $ours, $locale );
		}
	},
	0
);

add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			GestorListo\Quarter::hooks();
			GestorListo\Pack::hooks();
			GestorListo\Deliveries::hooks();
			GestorListo\Admin::hooks();
			GestorListo\Setup::hooks();
		}
	}
);
