<?php
/**
 * Load scripts for the frontend.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

remove_action( 'wp_enqueue_scripts', 'wpconsent_frontend_scripts' );
add_action( 'wp_enqueue_scripts', 'wpconsent_pro_frontend_scripts' );

/**
 * Load frontend scripts here.
 *
 * @return void
 */
function wpconsent_pro_frontend_scripts() {

	$frontend_asset_file = WPCONSENT_PLUGIN_PATH . 'build/frontend-pro.asset.php';

	if ( ! file_exists( $frontend_asset_file ) ) {
		return;
	}

	$asset = require $frontend_asset_file;

	// Let's not load anything on the frontend if the banner is disabled.
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	$default_allow          = boolval( wpconsent()->settings->get_option( 'default_allow', 0 ) );
	$manual_toggle_services = boolval( wpconsent()->settings->get_option( 'manual_toggle_services', 0 ) );
	$slugs                  = $manual_toggle_services ? wpconsent()->cookies->get_preference_slugs() : array(
		'essential',
		'statistics',
		'marketing',
	);

	wp_enqueue_script( 'wpconsent-frontend-js', WPCONSENT_PLUGIN_URL . 'build/frontend-pro.js', $asset['dependencies'], $asset['version'], true );

	wp_localize_script(
		'wpconsent-frontend-js',
		'wpconsent',
		apply_filters(
			'wpconsent_frontend_js_data',
			array(
				'consent_duration'        => wpconsent()->settings->get_option( 'consent_duration', 30 ),
				'api_url'                 => rest_url( 'wpconsent/v1' ),
				'nonce'                   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'records_of_consent'      => wpconsent()->settings->get_option( 'records_of_consent', true ),
				'css_url'                 => WPCONSENT_PLUGIN_URL . 'build/frontend-pro.css',
				'css_version'             => $asset['version'],
				'default_allow'           => $default_allow,
				'consent_type'            => $default_allow ? 'optout' : 'optin',
				'manual_toggle_services'  => $manual_toggle_services,
				'enable_consent_floating' => boolval( wpconsent()->settings->get_option( 'enable_consent_floating', 0 ) ),
				'slugs'                   => $slugs,
				'geolocation'             => array(
					'enabled'         => wpconsent()->geolocation->enabled(),
					'api_url'         => rest_url( 'wpconsent/v1/geolocation' ),
					'location_groups' => wpconsent()->geolocation->get_groups(),
				),
				'current_language'        => wpconsent()->multilanguage->get_plugin_locale(),
				'enable_consent_banner'   => wpconsent()->settings->get_option( 'enable_consent_banner', 1 ),
			)
		)
	);
}
