<?php
/**
 * Pro scanner class.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Scanner_Pro.
 */
class WPConsent_Scanner_Pro extends WPConsent_Scanner {

	/**
	 * Headers from the site scan request.
	 *
	 * @var array
	 */
	protected $headers = array();

	/**
	 * Extend the request body with headers.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return array
	 */
	protected function scan_request_body( $url = '' ) {
		$body = parent::scan_request_body( $url );

		// Send the list of plugins.
		$body['plugins']     = $this->get_active_plugins();
		$body['plugin_type'] = 'pro';

		if ( ! empty( $this->headers ) ) {
			$body['request_headers'] = $this->headers;
		}

		return $body;
	}

	/**
	 * Headers used for the scanner request.
	 *
	 * @return array
	 */
	protected function scan_request_headers() {
		$headers = parent::scan_request_headers();

		// Add header for license key.
		$headers['X-WPConsent-License-Key'] = wpconsent()->license->get();

		return $headers;
	}

	/**
	 * Does a request to the website and returns the HTML.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return string
	 */
	public function get_website_html( $url = '' ) {
		$request = $this->self_request( $url );

		if ( is_wp_error( $request ) ) {
			return '';
		}

		// Let's convert headers to a simple array.
		$headers = wp_remote_retrieve_headers( $request );
		if ( ! is_array( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		// Save headers.
		$this->headers = $headers;

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Returns an array of active plugins.
	 *
	 * @return array
	 */
	public function get_active_plugins() {
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins = array();

		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

			$plugins[] = array(
				'slug'    => $plugin,
				'version' => $plugin_data['Version'],
			);
		}

		return $plugins;
	}
}
