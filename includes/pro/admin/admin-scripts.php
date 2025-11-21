<?php
/**
 * Load pro-specific scripts for the admin area.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'wpconsent_pro_admin_scripts' );

/**
 * Load admin scripts here.
 *
 * @return void
 */
function wpconsent_pro_admin_scripts() {

	$current_screen = get_current_screen();

	if ( ! isset( $current_screen->id ) || false === strpos( $current_screen->id, 'wpconsent' ) ) {
		return;
	}

	$admin_asset_file = WPCONSENT_PLUGIN_PATH . 'build/admin-pro.asset.php';

	if ( ! file_exists( $admin_asset_file ) ) {
		return;
	}

	$asset = require $admin_asset_file;

	wp_enqueue_style( 'wpconsent-admin-pro-css', WPCONSENT_PLUGIN_URL . 'build/admin-pro.css', null, $asset['version'] );

	wp_enqueue_script( 'wpconsent-admin-pro-js', WPCONSENT_PLUGIN_URL . 'build/admin-pro.js', $asset['dependencies'], $asset['version'], true );
}
