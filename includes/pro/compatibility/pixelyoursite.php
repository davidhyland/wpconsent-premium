<?php
/**
 * PixelYourSite compatibility layer.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpconsent_pixelyoursite_bootstrap', 30 );

/**
 * Sets up the integration if PixelYourSite is active.
 *
 * @return void
 */
function wpconsent_pixelyoursite_bootstrap() {
	if ( ! wpconsent_pixelyoursite_is_available() ) {
		return;
	}

	if ( wpconsent()->settings->get_option( 'enable_script_blocking', 1 ) ) {
		add_filter( 'pys_disable_facebook_by_gdpr', '__return_true' );
		add_filter( 'pys_disable_pinterest_by_gdpr', '__return_true' );
		add_filter( 'pys_disable_bing_by_gdpr', '__return_true' );
	}

	// Disable PixelYourSite's Google Consent Mode if WPConsent's consent mode is enabled.
	if ( wpconsent()->cookie_blocking->get_google_consent_mode() ) {
		add_action( 'wp_enqueue_scripts', 'wpconsent_pixelyoursite_disable_consent_mode', 20 );
	}

	add_action( 'wp_enqueue_scripts', 'wpconsent_pixelyoursite_enqueue_inline_script', 20 );
	add_filter( 'wpconsent_scanner_services_needed', 'wpconsent_pixelyoursite_extend_scanner_services', 10, 4 );
	add_filter( 'wpconsent_scanner_formatted_scripts', 'wpconsent_pixelyoursite_extend_scanner_scripts', 10, 4 );
}

/**
 * Determines whether we should hook PixelYourSite.
 *
 * @return bool
 */
function wpconsent_pixelyoursite_is_available() {
	if ( ! function_exists( '\\PixelYourSite\\PYS' ) ) {
		return false;
	}

	return true;
}

/**
 * Disables PixelYourSite's Google Consent Mode by overriding the JS option.
 *
 * @return void
 */
function wpconsent_pixelyoursite_disable_consent_mode() {
	if ( ! wp_script_is( 'pys', 'enqueued' ) ) {
		return;
	}

	$script = 'if ( typeof pysOptions !== "undefined" ) { pysOptions.google_consent_mode = false; }';
	wp_add_inline_script( 'pys', $script, 'after' );
}

/**
 * Provides the services handled in this integration.
 *
 * @return array
 */
function wpconsent_pixelyoursite_get_service_map() {
	$map = array(
		'facebook-pixel'   => array(
			'loader'   => 'Facebook',
			'category' => 'marketing',
		),
		'pinterest'        => array(
			'loader'   => 'Pinterest',
			'category' => 'marketing',
		),
		'bing'             => array(
			'loader'   => 'Bing',
			'category' => 'marketing',
		),
		'google-analytics' => array(
			'loader'   => 'GoogleAnalytics',
			'category' => 'statistics',
		),
	);

	return $map;
}

/**
 * Adds PixelYourSite services to the scanner requirements.
 *
 * @param array             $services Detected services.
 * @param string            $url URL being scanned.
 * @param array             $data Raw scanner response.
 * @param WPConsent_Scanner $scanner Scanner instance.
 *
 * @return array
 */
function wpconsent_pixelyoursite_extend_scanner_services( $services, $url, $data, $scanner ) {
	if ( ! wpconsent_pixelyoursite_is_available() ) {
		return $services;
	}

	$configured = wpconsent_pixelyoursite_get_configured_services();

	if ( empty( $configured ) ) {
		return $services;
	}

	$services = array_merge( $services, array_keys( $configured ) );

	return array_values( array_unique( $services ) );
}

/**
 * Injects PixelYourSite managed services into scanner results.
 *
 * @param array             $scripts_data Formatted scripts grouped by category.
 * @param array             $scripts_by_category Raw scripts detected by the scanner.
 * @param array             $services Loaded service definition data.
 * @param WPConsent_Scanner $scanner Scanner instance.
 *
 * @return array
 */
function wpconsent_pixelyoursite_extend_scanner_scripts( $scripts_data, $scripts_by_category, $services, $scanner ) {
	if ( ! wpconsent_pixelyoursite_is_available() ) {
		return $scripts_data;
	}

	$configured = wpconsent_pixelyoursite_get_configured_services();

	if ( empty( $configured ) ) {
		return $scripts_data;
	}

	$source_note = __( 'Detected via the PixelYourSite integration.', 'wpconsent-premium' );

	foreach ( $configured as $slug => $service_meta ) {
		if ( ! isset( $services[ $slug ] ) ) {
			continue;
		}

		$category        = isset( $service_meta['category'] ) ? $service_meta['category'] : 'marketing';
		$service_details = $services[ $slug ];
		$service_label   = $service_details['label'];

		if ( ! isset( $scripts_data[ $category ] ) ) {
			$scripts_data[ $category ] = array();
		}

		$already_listed = false;

		foreach ( $scripts_data[ $category ] as $entry ) {
			if ( isset( $entry['name'] ) && $entry['name'] === $slug ) {
				$already_listed = true;
				break;
			}
		}

		if ( $already_listed ) {
			continue;
		}

		$scripts_data[ $category ][] = array(
			'name'        => $slug,
			'service'     => $service_label,
			'html'        => $source_note,
			'logo'        => $service_details['logo'],
			'url'         => $service_details['service_url'],
			'description' => $service_details['description'],
			'cookies'     => $service_details['cookies'],
		);
	}

	return $scripts_data;
}

/**
 * Returns the PixelYourSite services configured on the site.
 *
 * @return array
 */
function wpconsent_pixelyoursite_get_configured_services() {
	static $configured_services = null;

	if ( null !== $configured_services ) {
		return $configured_services;
	}

	$configured_services = array();

	if ( ! wpconsent_pixelyoursite_is_available() ) {
		return $configured_services;
	}

	$service_map = wpconsent_pixelyoursite_get_service_map();

	foreach ( $service_map as $slug => $service_meta ) {
		if ( wpconsent_pixelyoursite_is_service_configured( $slug ) ) {
			$configured_services[ $slug ] = $service_meta;
		}
	}

	return $configured_services;
}

/**
 * Determines whether a given PixelYourSite service is configured.
 *
 * @param string $service Service slug.
 *
 * @return bool
 */
function wpconsent_pixelyoursite_is_service_configured( $service ) {
	switch ( $service ) {
		case 'facebook-pixel':
			$configured = wpconsent_pixelyoursite_pixel_is_configured( '\\PixelYourSite\\Facebook' );
			break;
		case 'pinterest':
			$configured = wpconsent_pixelyoursite_pixel_is_configured( '\\PixelYourSite\\Pinterest' );
			break;
		case 'bing':
			$configured = wpconsent_pixelyoursite_pixel_is_configured( '\\PixelYourSite\\Bing' );
			break;
		case 'google-analytics':
			$configured = wpconsent_pixelyoursite_pixel_is_configured( '\\PixelYourSite\\GA' );
			break;
		default:
			$configured = false;
			break;
	}

	return (bool) $configured;
}

/**
 * Checks if a PixelYourSite pixel exposes a configured() method and reports it as enabled.
 *
 * @param string $callback Fully-qualified function name returning the pixel instance.
 *
 * @return bool
 */
function wpconsent_pixelyoursite_pixel_is_configured( $callback ) {
	if ( ! function_exists( $callback ) ) {
		return false;
	}

	$instance = call_user_func( $callback );

	if ( ! is_object( $instance ) || ! method_exists( $instance, 'configured' ) ) {
		return false;
	}

	return (bool) $instance->configured();
}

/**
 * Adds the JS bridge that informs PixelYourSite about consent events.
 *
 * @return void
 */
function wpconsent_pixelyoursite_enqueue_inline_script() {
	if ( ! wpconsent_pixelyoursite_is_available() ) {
		return;
	}

	if ( ! wp_script_is( 'pys', 'enqueued' ) ) {
		return;
	}

	$services = wpconsent_pixelyoursite_get_service_map();

	// Google Analytics listens to Consent Mode and should not be toggled manually via this bridge.
	unset( $services['google-analytics'] );

	$script = 'window.wpconsentPixelYourSiteConfig = ' . wp_json_encode( $services ) . ';';
	$script .= '
( function ( w, config ) {
	if ( ! w || ! config ) {
		return;
	}

	var loaded = {};
	Object.keys( config ).forEach( function ( key ) {
		loaded[ key ] = false;
	} );

	function requestLoad( service, attempt ) {
		if ( loaded[ service ] ) {
			return;
		}

		var loader = config[ service ].loader;
		var target = loader && w.pys && w.pys[ loader ] && typeof w.pys[ loader ].loadPixel === "function" ? w.pys[ loader ] : null;

		if ( target ) {
			target.loadPixel();
			loaded[ service ] = true;
			return;
		}

		if ( ( attempt || 0 ) > 20 ) {
			return;
		}

		w.setTimeout( function () {
			requestLoad( service, ( attempt || 0 ) + 1 );
		}, 250 );
	}

	function isManualToggle() {
		return !! ( w.wpconsent && w.wpconsent.manual_toggle_services );
	}

	function serviceAllowed( preferences, service ) {
		if ( ! preferences ) {
			return false;
		}

		var entry = config[ service ] || {};

		if ( isManualToggle() ) {
			if ( Object.prototype.hasOwnProperty.call( preferences, service ) ) {
				return !! preferences[ service ];
			}

			if ( entry.category && Object.prototype.hasOwnProperty.call( preferences, entry.category ) ) {
				return !! preferences[ entry.category ];
			}

			return false;
		}

		var category = entry.category || "marketing";
		if ( Object.prototype.hasOwnProperty.call( preferences, category ) ) {
			return !! preferences[ category ];
		}

		return false;
	}

	function handleEvent( event ) {
		var preferences = event && event.detail;

		if ( ! preferences ) {
			return;
		}

		Object.keys( config ).forEach( function ( service ) {
			if ( serviceAllowed( preferences, service ) ) {
				requestLoad( service );
			}
		} );
	}

	document.addEventListener( "wpconsent_consent_processed", handleEvent );
} )( window, window.wpconsentPixelYourSiteConfig );';

	wp_add_inline_script( 'pys', $script, 'after' );
}
