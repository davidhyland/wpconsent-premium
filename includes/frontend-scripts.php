<?php
/**
 * Load scripts for the frontend.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'wpconsent_frontend_scripts' );
add_action( 'wp_head', 'wpconsent_google_consent_script', 5 );

/**
 * Load frontend scripts here.
 *
 * @return void
 */
function wpconsent_frontend_scripts() {

	$frontend_asset_file = WPCONSENT_PLUGIN_PATH . 'build/frontend.asset.php';

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

	wp_enqueue_script( 'wpconsent-frontend-js', WPCONSENT_PLUGIN_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );

	wp_localize_script(
		'wpconsent-frontend-js',
		'wpconsent',
		apply_filters(
			'wpconsent_frontend_js_data',
			array(
				'consent_duration'        => wpconsent()->settings->get_option( 'consent_duration', 30 ),
				'css_url'                 => WPCONSENT_PLUGIN_URL . 'build/frontend.css',
				'css_version'             => $asset['version'],
				'default_allow'           => $default_allow,
				'consent_type'            => $default_allow ? 'optout' : 'optin',
				'manual_toggle_services'  => $manual_toggle_services,
				'slugs'                   => $slugs,
				'enable_consent_banner'   => wpconsent()->settings->get_option( 'enable_consent_banner', 1 ),
				'enable_script_blocking'  => wpconsent()->settings->get_option( 'enable_script_blocking', 1 ),
				'enable_consent_floating' => boolval( wpconsent()->settings->get_option( 'enable_consent_floating', 0 ) ),
			)
		)
	);
}

/**
 * Outputs the Google consent script for managing user preferences on Google-related services.
 *
 * This function does not execute if the banner display is disabled or if no Google services are enabled
 * in the cookie data. It ensures the Google consent script is loaded early enough for it to take effect correctly.
 *
 * @return void
 */
function wpconsent_google_consent_script() {
	// If the banner display is disabled don't load this.
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	// Let's load this only if they are using one of the Google services in the cookie data.
	if ( ! wpconsent()->cookies->needs_google_consent() ) {
		return;
	}

	$default = intval( wpconsent()->settings->get_option( 'default_allow', 0 ) );

	// We need to load the Google consent script earlier than other tracking scripts for it to take effect correctly.
	echo "<script data-cfasync=\"false\" data-wpfc-render=\"false\">
		(function () {
			window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}
			
			let preferences = {
				marketing: {$default},
				statistics: {$default},
			}
			
			// Get preferences directly from cookie
			const value = `; ` + document.cookie;
			const parts = value.split(`; wpconsent_preferences=`);
			if (parts.length === 2) {
				try {
					preferences = JSON.parse(parts.pop().split(';').shift());
				} catch (e) {
					console.error('Error parsing WPConsent preferences:', e);
				}
			}
			
			gtag('consent', 'default', {
				'ad_storage': preferences.marketing ? 'granted' : 'denied',
				'analytics_storage': preferences.statistics ? 'granted' : 'denied',
				'ad_user_data': preferences.marketing ? 'granted' : 'denied',
				'ad_personalization': preferences.marketing ? 'granted' : 'denied',
				'security_storage': 'granted',
				'functionality_storage': 'granted'
			});
		})();
	</script>"; // phpcs:ignore
}
