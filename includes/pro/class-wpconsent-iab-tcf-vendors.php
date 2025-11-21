<?php
/**
 * IAB TCF Vendors List handler for WPConsent Pro.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_IAB_TCF_Vendors.
 */
class WPConsent_IAB_TCF_Vendors {

	/**
	 * Holds the instance of the class.
	 *
	 * @var WPConsent_IAB_TCF_Vendors
	 */
	private static $instance;

	/**
	 * Key for storing vendors data in the cache.
	 *
	 * @var string
	 */
	protected $cache_key = 'vendor-list';

	/**
	 * The default URL for the IAB TCF vendors list.
	 *
	 * @var string
	 */
	protected $vendors_url = 'https://vendor-list.consensu.org/v3/vendor-list.json';

	/**
	 * The vendors data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The default time to live for cached data (24 hours).
	 *
	 * @var int
	 */
	protected $default_ttl = DAY_IN_SECONDS;

	/**
	 * Get the singleton instance.
	 *
	 * @return WPConsent_IAB_TCF_Vendors
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private constructor for singleton pattern.
		$this->init();
	}

	/**
	 * Initialize the class and hook into WordPress filters.
	 *
	 * @return void
	 */
	private function init() {
		// Initialization logic for the vendors class.
	}

	/**
	 * Get the vendors list data.
	 *
	 * @return array
	 */
	public function get_data() {
		if ( ! isset( $this->data ) ) {
			$this->data = $this->load_data();
		}

		return $this->data;
	}

	/**
	 * Load the vendors data either from cache or from the server.
	 *
	 * @return array
	 */
	public function load_data() {
		// Get cached data from file cache using TTL and expired callback.
		$cache_result = wpconsent()->file_cache->get( $this->cache_key, $this->default_ttl, true );

		if ( false !== $cache_result && is_array( $cache_result ) ) {
			// We have cached data, check if it's expired.
			if ( ! $cache_result['expired'] && ! isset( $cache_result['data']['error'] ) ) {
				// Data is still valid and not an error state.
				return $cache_result['data'];
			} elseif ( $cache_result['expired'] && ! isset( $cache_result['data']['error'] ) ) {
				// Data is expired but valid, try to fetch new data but keep old as fallback.
				$new_data = $this->get_from_server( $cache_result['data'] );
				if ( ! empty( $new_data ) && ! isset( $new_data['error'] ) ) {
					return $new_data;
				} else {
					// Failed to fetch new data, return expired data as fallback.
					return $cache_result['data'];
				}
			}
		}

		// No cached data or error state, fetch from server.
		$this->data = $this->get_from_server();

		return $this->data;
	}

	/**
	 * Get data from the IAB TCF vendors server.
	 *
	 * @param array $fallback_data Optional. Existing data to return if server request fails.
	 *
	 * @return array
	 */
	protected function get_from_server( $fallback_data = null ) {
		/**
		 * Filter the IAB TCF vendors list URL.
		 *
		 * @param string $url The vendors list URL.
		 *
		 * @return string
		 */
		$url = apply_filters( 'wpconsent_iab_tcf_vendors_url', $this->vendors_url );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'WPConsent/' . WPCONSENT_VERSION . '; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->handle_server_failure( $fallback_data );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return $this->handle_server_failure( $fallback_data );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $this->handle_server_failure( $fallback_data );
		}

		$data = json_decode( $body, true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $this->handle_server_failure( $fallback_data );
		}

		// Get cache duration from response headers and update default TTL.
		$ttl = $this->get_cache_ttl_from_headers( $response );
		$this->default_ttl = $ttl;

		// Cache the successful response in original format.
		wpconsent()->file_cache->set( $this->cache_key, $data );

		return $data;
	}

	/**
	 * Determine cache TTL from HTTP response headers.
	 *
	 * @param array $response The HTTP response.
	 *
	 * @return int Cache TTL in seconds.
	 */
	protected function get_cache_ttl_from_headers( $response ) {
		$headers = wp_remote_retrieve_headers( $response );

		if ( ! is_array( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}

		// Check for Cache-Control header.
		if ( isset( $headers['cache-control'] ) ) {
			$cache_control = $headers['cache-control'];
			if ( preg_match( '/max-age=(\d+)/', $cache_control, $matches ) ) {
				return (int) $matches[1];
			}
		}

		// Check for Expires header.
		if ( isset( $headers['expires'] ) ) {
			$expires_timestamp = strtotime( $headers['expires'] );
			if ( $expires_timestamp > time() ) {
				return $expires_timestamp - time();
			}
		}

		// Fallback to default TTL.
		return $this->default_ttl;
	}

	/**
	 * Handle server failure by preserving existing data or saving error state.
	 *
	 * @param array $fallback_data Optional. Existing data to return if available.
	 *
	 * @return array
	 */
	protected function handle_server_failure( $fallback_data = null ) {
		// If we have fallback data, return it without overwriting the cache.
		if ( ! empty( $fallback_data ) && is_array( $fallback_data ) && ! isset( $fallback_data['error'] ) ) {
			return $fallback_data;
		}

		// No fallback data available, save temporary error state.
		return $this->save_temporary_response_fail();
	}

	/**
	 * Handle failed server response by saving a temporary error state.
	 * This prevents repeated failed requests for a short period.
	 * Only called when there's no existing valid data to preserve.
	 *
	 * @return array
	 */
	protected function save_temporary_response_fail() {
		$error_data = array(
			'error' => true,
			'time'  => time(),
		);

		// Cache the error for a shorter period (1 hour) in original format.
		// Update default TTL for this error state.
		$this->default_ttl = HOUR_IN_SECONDS;
		wpconsent()->file_cache->set( $this->cache_key, $error_data );

		return $this->get_empty_array();
	}

	/**
	 * Get an empty array for consistent response structure.
	 *
	 * @return array
	 */
	protected function get_empty_array() {
		return array(
			'vendors'         => array(),
			'purposes'        => array(),
			'specialPurposes' => array(),
			'features'        => array(),
			'specialFeatures' => array(),
			'stacks'          => array(),
		);
	}

	/**
	 * Get a specific vendor by ID.
	 *
	 * @param int $vendor_id The vendor ID.
	 *
	 * @return array|null Vendor data or null if not found.
	 */
	public function get_vendor( $vendor_id ) {
		$data = $this->get_data();

		if ( isset( $data['vendors'] ) && isset( $data['vendors'][ $vendor_id ] ) ) {
			return $data['vendors'][ $vendor_id ];
		}

		return null;
	}

	/**
	 * Get all vendors.
	 *
	 * @return array
	 */
	public function get_vendors() {
		$data = $this->get_data();

		return isset( $data['vendors'] ) ? $data['vendors'] : array();
	}

	/**
	 * Get all purposes.
	 *
	 * @return array
	 */
	public function get_purposes() {
		$data = $this->get_data();

		return isset( $data['purposes'] ) ? $data['purposes'] : array();
	}

	/**
	 * Get all special purposes.
	 *
	 * @return array
	 */
	public function get_special_purposes() {
		$data = $this->get_data();

		return isset( $data['specialPurposes'] ) ? $data['specialPurposes'] : array();
	}

	/**
	 * Get all features.
	 *
	 * @return array
	 */
	public function get_features() {
		$data = $this->get_data();

		return isset( $data['features'] ) ? $data['features'] : array();
	}

	/**
	 * Get all special features.
	 *
	 * @return array
	 */
	public function get_special_features() {
		$data = $this->get_data();

		return isset( $data['specialFeatures'] ) ? $data['specialFeatures'] : array();
	}


	/**
	 * Force refresh the vendors data from the server.
	 *
	 * @return array
	 */
	public function refresh_data() {
		$this->delete_cache();
		$this->data = $this->get_from_server();

		return $this->data;
	}

	/**
	 * Delete the cached vendors data.
	 *
	 * @return void
	 */
	public function delete_cache() {
		wpconsent()->file_cache->delete( $this->cache_key );
		if ( isset( $this->data ) ) {
			unset( $this->data );
		}
	}

	/**
	 * Check if the vendors list is available and not in error state.
	 *
	 * @return bool
	 */
	public function is_available() {
		$data = $this->get_data();

		return ! empty( $data ) && ! isset( $data['error'] );
	}
}
