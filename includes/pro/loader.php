<?php
/**
 * File used for importing pro-only files.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( is_admin() || defined( 'DOING_CRON' ) && DOING_CRON ) {
	// Pro-specific admin page loader.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-admin-page-loader-pro.php';
	// Admin scripts for pro version.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/admin-scripts.php';
	// Pro scanner.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-scanner-pro.php';
	// License.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-license.php';
	// Pro ajax handlers.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/admin-ajax-handlers.php';
	// Export handler.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-export-handler.php';
	// Pro-specific language picker trait.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/trait-wpconsent-language-picker.php';
	// Review request.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/admin/class-wpconsent-review.php';
	// Services library.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-services-library.php';
	// Translation services.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-translation-services.php';
	// Addons manager.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-addons.php';
	// Addons Pro manager.
	require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/class-wpconsent-addons-pro.php';
}

// Pro install routine.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/install.php';
// Load the db class.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-db.php';
// Load the consent log class.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-consent-log.php';
// Frontend scripts.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/frontend-scripts.php';
// Pro script blocker.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-script-blocker-pro.php';
// Updates.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-updates.php';
// IP helpers.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-ip.php';
// Geolocation.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-geolocation.php';
// Automatic scanner.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-auto-scanner.php';
// Multilanguage.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-multilanguage.php';
// Pro banner.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-banner-pro.php';
// Pro cookies.
require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/class-wpconsent-cookies-pro.php';

add_action( 'plugins_loaded', 'wpconsent_load_pro_updates', 15 );


/**
 * Load the updates class.
 *
 * @return void
 */
function wpconsent_load_pro_updates() {
	// Only load this in the admin.
	if ( ! is_admin() || ! isset( wpconsent()->license ) ) {
		return;
	}
	$is_multisite_and_network_admin = is_multisite() && is_network_admin();
	$key                            = wpconsent()->license->get( $is_multisite_and_network_admin );

	if ( empty( $key ) && $is_multisite_and_network_admin ) {
		$key = wpconsent()->license->get( false );
	}
	new WPConsent_Updates(
		array(
			'plugin_slug' => 'wpconsent',
			'plugin_path' => WPCONSENT_PLUGIN_BASENAME,
			'version'     => WPCONSENT_VERSION,
			'key'         => $key,
		)
	);
}
