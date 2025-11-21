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
add_filter( 'wpconsent_admin_js_data', 'wpconsent_pro_admin_js_data' );

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

/**
 * Add pro-specific translatable strings to the admin JS data.
 *
 * @param array $data Existing localized data.
 *
 * @return array Modified data with pro strings added.
 */
function wpconsent_pro_admin_js_data( $data ) {
	$data['translation_cancel_title']       = esc_html__( 'Cancel Translation?', 'wpconsent-premium' );
	$data['translation_cancel_message']     = esc_html__( 'Are you sure you want to cancel the current translation process? This will reset the translation status and allow you to start a new translation.', 'wpconsent-premium' );
	$data['translation_cancel_confirm']     = esc_html__( 'Yes, Cancel Translation', 'wpconsent-premium' );
	$data['translation_cancel_keep']        = esc_html__( 'No, Keep Running', 'wpconsent-premium' );
	$data['translation_cancelled_success']  = esc_html__( 'Translation has been cancelled. You can now start a new translation.', 'wpconsent-premium' );
	$data['translation_reset_error_prefix'] = esc_html__( 'Failed to reset translation: ', 'wpconsent-premium' );

	return $data;
}
