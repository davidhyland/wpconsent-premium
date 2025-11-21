<?php
/**
 * Automatic scanning logic.
 *
 * @package WPConsent
 */

/**
 * Class WP_Consent_Auto_Scanner
 */
class WP_Consent_Auto_Scanner {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wpconsent_auto_scanner', array( $this, 'maybe_scan_website' ) );
	}

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! wpconsent()->settings->get_option( 'auto_scanner', 0 ) ) {
			return;
		}

		// Check that our daily event is scheduled.
		if ( ! wp_next_scheduled( 'wpconsent_auto_scanner' ) ) {
			wp_schedule_event( time(), 'daily', 'wpconsent_auto_scanner' );
		}
	}

	/**
	 * Determine if we should run an automatics scan.
	 *
	 * @return void
	 */
	public function maybe_scan_website() {
		if ( ! wpconsent()->settings->get_option( 'auto_scanner', 0 ) ) {
			return;
		}
		// Let's check when the auto scanner last ran.
		$last_ran      = wpconsent()->settings->get_option( 'auto_scanner_last_ran', 0 );
		$scan_interval = wpconsent()->settings->get_option( 'auto_scanner_interval', 1 );

		if ( $last_ran + ( $scan_interval * DAY_IN_SECONDS ) < time() ) {
			$this->do_scan();
		}
	}

	/**
	 * Perform the scan.
	 *
	 * @return void
	 */
	public function do_scan() {
		// Get all URLs to scan.
		$urls          = wpconsent()->scanner->get_scan_urls();
		$total_pages   = count( $urls );
		$scanned_pages = 0;

		// Initialize aggregated results.
		$aggregated_results = array(
			'scripts'         => array(),
			'services_needed' => array(),
		);

		// Scan each URL.
		foreach ( $urls as $url ) {
			++$scanned_pages;
			$is_final = ( $scanned_pages === $total_pages );

			// Perform scan for this URL.
			$response = wpconsent()->scanner->perform_scan( $url );

			// If we got an error, log it and continue.
			if ( isset( $response['error'] ) && $response['error'] ) {
				continue;
			}

			// Merge scripts by category.
			foreach ( $response['scripts'] as $category => $scripts ) {
				if ( ! isset( $aggregated_results['scripts'][ $category ] ) ) {
					$aggregated_results['scripts'][ $category ] = array();
				}

				// Merge scripts while avoiding duplicates using script name as key.
				foreach ( $scripts as $script ) {
					$script_key = $script['name'];
					if ( ! isset( $aggregated_results['scripts'][ $category ][ $script_key ] ) ) {
						$aggregated_results['scripts'][ $category ][ $script_key ] = $script;
					}
				}
			}

			// Merge services needed.
			$aggregated_results['services_needed'] = array_unique(
				array_merge( $aggregated_results['services_needed'], $response['services_needed'] )
			);

			// If this is the final scan, save the complete results.
			if ( $is_final ) {
				// Convert aggregated scripts back to sequential array.
				$formatted_scripts = array();
				foreach ( $aggregated_results['scripts'] as $category => $scripts ) {
					$formatted_scripts[ $category ] = array_values( $scripts );
				}

				$final_results = array(
					'error'           => false,
					'scripts'         => $formatted_scripts,
					'categories'      => wpconsent()->cookies->get_categories(),
					'services_needed' => $aggregated_results['services_needed'],
					'message'         => sprintf(
						/* translators: %1$d: number of scanned pages, %2$d: total number of pages */
						__( 'Auto scan complete. (%1$d of %2$d pages scanned)', 'wpconsent-cookies-banner-privacy-suite' ),
						$scanned_pages,
						$total_pages
					),
				);

				wpconsent()->scanner->save_scan_data( $final_results );
			}
		}

		// Update last scan time.
		wpconsent()->settings->update_option( 'auto_scanner_last_ran', time() );
	}
}
