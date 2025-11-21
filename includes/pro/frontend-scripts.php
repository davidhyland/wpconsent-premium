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
	$slugs                  = wpconsent()->cookies->get_preference_slugs();

	wp_enqueue_script( 'wpconsent-frontend-js', WPCONSENT_PLUGIN_URL . 'build/frontend-pro.js', $asset['dependencies'], $asset['version'], true );

	// Determine the correct CSS file based on RTL.
	$css_file = is_rtl() ? 'frontend-pro-rtl.css' : 'frontend-pro.css';

	wp_localize_script(
		'wpconsent-frontend-js',
		'wpconsent',
		apply_filters(
			'wpconsent_frontend_js_data',
			array(
				'consent_duration'           => wpconsent()->settings->get_option( 'consent_duration', 30 ),
				'api_url'                    => rest_url( 'wpconsent/v1' ),
				'nonce'                      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'records_of_consent'         => wpconsent()->settings->get_option( 'records_of_consent', true ),
				'css_url'                    => WPCONSENT_PLUGIN_URL . 'build/' . $css_file,
				'css_version'                => $asset['version'],
				'default_allow'              => $default_allow,
				'consent_type'               => $default_allow ? 'optout' : 'optin',
				'manual_toggle_services'     => $manual_toggle_services,
				'enable_consent_floating'    => boolval( wpconsent()->settings->get_option( 'enable_consent_floating', 0 ) ),
				'slugs'                      => $slugs,
				'geolocation'                => array(
					'enabled'         => wpconsent()->geolocation->enabled(),
					'api_url'         => rest_url( 'wpconsent/v1/geolocation' ),
					'location_groups' => wpconsent()->geolocation->get_groups(),
				),
				'current_language'           => wpconsent()->multilanguage->get_plugin_locale(),
				'enable_script_blocking'     => wpconsent()->settings->get_option( 'enable_script_blocking', 1 ),
				'enable_consent_banner'      => wpconsent()->settings->get_option( 'enable_consent_banner', 1 ),
				'enable_shared_consent'      => boolval( wpconsent()->settings->get_option( 'enable_shared_consent', 0 ) ),
				'cookie_domain'              => wpconsent()->settings->get_option( 'cookie_domain', '' ),
				'accept_button_enabled'      => boolval( wpconsent()->settings->get_option( 'accept_button_enabled', 1 ) ),
				'cancel_button_enabled'      => boolval( wpconsent()->settings->get_option( 'cancel_button_enabled', 1 ) ),
				'preferences_button_enabled' => boolval( wpconsent()->settings->get_option( 'preferences_button_enabled', 1 ) ),
				'respect_gpc'                => boolval( wpconsent()->settings->get_option( 'respect_gpc', 0 ) ),
			)
		)
	);

	$iabtcf_asset_file = WPCONSENT_PLUGIN_PATH . 'build/iab-tcf.asset.php';

	if ( ! file_exists( $iabtcf_asset_file ) ) {
		return;
	}

	// Let's not load anything on the frontend if IAB TCF is not enabled.
	if ( ! wpconsent()->iab_tcf->is_enabled() ) {
		return;
	}

	$iabtcf_asset = require $iabtcf_asset_file;

	wp_enqueue_script( 'wpconsent-iabtcf-js', WPCONSENT_PLUGIN_URL . 'build/iab-tcf.js', $iabtcf_asset['dependencies'], $iabtcf_asset['version'], false );
}

// Add early __tcfapi stub to ensure CMP API is available before any other scripts load
add_action( 'wp_head', 'wpconsent_inject_early_tcfapi_stub', 1 );

/**
 * Inject early __tcfapi stub function in head section.
 * This ensures __tcfapi is available before other scripts load, as required by IAB TCF specifications.
 */
function wpconsent_inject_early_tcfapi_stub() {
	// Only inject if IAB TCF is enabled
	if ( ! wpconsent()->iab_tcf->is_enabled() ) {
		return;
	}

	// Only inject if banner is enabled
	if ( ! wpconsent()->banner->is_enabled() ) {
		return;
	}

	?>
	<script type="text/javascript">
!function(){var i,r,o;i="__tcfapiLocator",r=[],(o=window.frames[i])||(function e(){var t=window.document,a=!!o;if(!a)if(t.body){var n=t.createElement("iframe");n.style.cssText="display:none",n.name=i,t.body.appendChild(n)}else setTimeout(e,50);return!a}(),window.__tcfapi=function(){for(var e,t=[],a=0;a<arguments.length;a++)t[a]=arguments[a];if(!t.length)return r;if("setGdprApplies"===t[0])3<t.length&&2===parseInt(t[1],10)&&"boolean"==typeof t[3]&&(e=t[3],"function"==typeof t[2]&&t[2]("set",!0));else if("ping"===t[0]){var n={gdprApplies:e,cmpLoaded:!1,cmpStatus:"stub"};"function"==typeof t[2]&&t[2](n,!0)}else r.push(t)},window.addEventListener("message",function(n){var i="string"==typeof n.data,e={};try{e=i?JSON.parse(n.data):n.data}catch(e){}var r=e.__tcfapiCall;r&&window.__tcfapi(r.command,r.version,function(e,t){var a={__tcfapiReturn:{returnValue:e,success:t,callId:r.callId}};i&&(a=JSON.stringify(a)),n.source.postMessage(a,"*")},r.parameter)},!1))}();
	</script>
	<?php
}
