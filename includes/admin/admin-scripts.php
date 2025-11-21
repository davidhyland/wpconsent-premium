<?php
/**
 * Load scripts for the admin area.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'wpconsent_admin_scripts' );

/**
 * Load admin scripts here.
 *
 * @return void
 */
function wpconsent_admin_scripts() {

	$current_screen = get_current_screen();

	if ( ! isset( $current_screen->id ) || false === strpos( $current_screen->id, 'wpconsent' ) ) {
		return;
	}

	$admin_asset_file = WPCONSENT_PLUGIN_PATH . 'build/admin.asset.php';

	if ( ! file_exists( $admin_asset_file ) ) {
		return;
	}

	$asset = require $admin_asset_file;

	// Enqueue color picker.
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	wp_enqueue_style( 'wpconsent-admin-css', WPCONSENT_PLUGIN_URL . 'build/admin.css', null, $asset['version'] );

	wp_enqueue_script( 'wpconsent-admin-js', WPCONSENT_PLUGIN_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );

	wp_localize_script(
		'wpconsent-admin-js',
		'wpconsent',
		apply_filters(
			'wpconsent_admin_js_data',
			array(
				'nonce'                     => wp_create_nonce( 'wpconsent_admin' ),
				'configure_cookies_title'   => esc_html__( 'Automatically Configure Cookies?', 'wpconsent-cookies-banner-privacy-suite' ),
				'configure_cookies_content' => esc_html__( 'WPConsent will automatically populate cookie data from the services you have selected.', 'wpconsent-cookies-banner-privacy-suite' ),
				'yes'                       => esc_html__( 'Yes', 'wpconsent-cookies-banner-privacy-suite' ),
				'no'                        => esc_html__( 'No', 'wpconsent-cookies-banner-privacy-suite' ),
				'ok'                        => esc_html__( 'OK', 'wpconsent-cookies-banner-privacy-suite' ),
				'please_wait'               => esc_html__( 'Please wait...', 'wpconsent-cookies-banner-privacy-suite' ),
				'scan_complete'             => esc_html__( 'Scan completed', 'wpconsent-cookies-banner-privacy-suite' ),
				'scan_error'                => esc_html__( 'Scan error', 'wpconsent-cookies-banner-privacy-suite' ),
				'scanning_title'            => esc_html__( 'Scanning website...', 'wpconsent-cookies-banner-privacy-suite' ),
				'lock_icon'                 => wpconsent_get_icon( 'lock', 22, 28 ),
				'purchased_link'            => wpconsent_utm_url( 'https://wpconsent.com/already-purchased/' ),
				'purchased_text'            => esc_html__( 'Already purchased?', 'wpconsent-cookies-banner-privacy-suite' ),
				'discount_note'             => sprintf(
				// Translators: %1$s and %2$s are strong tags, %3$s and %4$s are strong tags with a class.
					esc_html__( '%1$sBonus:%2$s WPConsent Lite users get %3$s50%% off%4$s the regular price, automatically applied at checkout.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<strong>',
					'</strong>',
					'<strong class="wpconsent-green">',
					'</strong>'
				),
				'upgrade_button'            => esc_html__( 'Upgrade to PRO', 'wpconsent-cookies-banner-privacy-suite' ),
				'languages_upsell'          => array(
					'title' => esc_html__( 'Multilanguage is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
					'text'  => esc_html__( 'Upgrade to WPConsent PRO today and unlock the ability to show your cookie banner in multiple languages.', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite/', 'language-switcher', $current_screen->id ),
				),
				'import_warning_title'      => esc_html__( 'Warning: Import Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'import_warning_message'    => esc_html__( 'This action will overwrite all your current settings. This cannot be undone. We recommend exporting your current settings as a backup before proceeding.', 'wpconsent-cookies-banner-privacy-suite' ),
				'import_button'             => esc_html__( 'Import Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				'cancel_button'             => esc_html__( 'Cancel', 'wpconsent-cookies-banner-privacy-suite' ),
				'service_library_upsell'    => array(
					'title' => esc_html__( 'Service Library Import is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
					'text'  => esc_html__( 'Upgrade to WPConsent PRO today and 1-click import cookie data for any service in our library.', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite/', 'service-library', $current_screen->id ),
				),
			)
		)
	);
}
