<?php
/**
 * Load pro-only compatibility files.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'wpconsent_load_pro_compatibility_files', 20 );

/**
 * Load compatibility files for pro version.
 *
 * @return void
 */
function wpconsent_load_pro_compatibility_files() {
	$compatibility_files = array(
		'pixelyoursite' => 'pixelyoursite.php',
	);

	/**
	 * Filter the list of pro compatibility files to load.
	 *
	 * @param array $compatibility_files Array of compatibility files with slug => filename pairs.
	 */
	$compatibility_files = apply_filters( 'wpconsent_pro_compatibility_files', $compatibility_files );

	foreach ( $compatibility_files as $slug => $file ) {
		$file_path = WPCONSENT_PLUGIN_PATH . 'includes/pro/compatibility/' . $file;

		if ( ! file_exists( $file_path ) ) {
			continue;
		}

		require_once $file_path;
	}
}
