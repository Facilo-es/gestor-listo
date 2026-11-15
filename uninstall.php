<?php
/**
 * Pack files always go. Settings and tables only if the owner ticked the box.
 *
 * @package GestorListo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$gestlisto_folder = get_option( 'gestlisto_folder' );
if ( $gestlisto_folder ) {
	$gestlisto_uploads = wp_upload_dir();
	$gestlisto_path    = trailingslashit( $gestlisto_uploads['basedir'] ) . $gestlisto_folder;
	foreach ( (array) glob( $gestlisto_path . '/*' ) as $gestlisto_item ) {
		wp_delete_file( $gestlisto_item );
	}
	global $wp_filesystem;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	if ( $wp_filesystem ) {
		$wp_filesystem->rmdir( $gestlisto_path, true );
	}
}

$gestlisto_settings = get_option( 'gestlisto_settings', array() );
if ( ! empty( $gestlisto_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gestlisto_deliveries, {$wpdb->prefix}gestlisto_delivered_docs, {$wpdb->prefix}gestlisto_accounts" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gestlisto\_%' OR option_name LIKE '\_transient%gestlisto\_%'" );
	// phpcs:enable
}
