<?php
/**
 * Load services from the cookielibrary.org service library.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Services_Library.
 */
class WPConsent_Services_Library {

	/**
	 * Key for storing services in the cache.
	 *
	 * @var string
	 */
	protected $cache_key = 'services-library';

	/**
	 * The URL for the all services JSON file.
	 *
	 * @var string
	 */
	protected $all_services_url = 'https://cookielibrary.org/wp-content/all-services.json';

	/**
	 * The data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The default time to live for library items that are cached.
	 *
	 * @var int
	 */
	protected $ttl = DAY_IN_SECONDS;

	/**
	 * Get all the available services from the library.
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
	 * Load the library data either from the server or from cache.
	 *
	 * @return array
	 */
	public function load_data() {
		$this->data = wpconsent()->file_cache->get( $this->cache_key, $this->ttl );

		if ( false === $this->data ) {
			$this->data = $this->get_from_server();
		}

		return $this->data;
	}

	/**
	 * Get data from the server.
	 *
	 * @return array
	 */
	protected function get_from_server() {
		$response = wp_remote_get( $this->all_services_url );

		if ( is_wp_error( $response ) ) {
			return $this->save_temporary_response_fail();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return $this->save_temporary_response_fail();
		}

		$data = json_decode( $body, true );
		if ( empty( $data ) ) {
			return $this->save_temporary_response_fail();
		}

		wpconsent()->file_cache->set( $this->cache_key, $data );

		return $data;
	}

	/**
	 * When we can't fetch from the server we save a temporary error => true file to avoid
	 * subsequent requests for a while. Returns a properly formatted array for frontend output.
	 *
	 * @return array
	 */
	public function save_temporary_response_fail() {
		$data = array(
			'error' => true,
			'time'  => time(),
		);
		wpconsent()->file_cache->set( $this->cache_key, $data );

		return $this->get_empty_array();
	}

	/**
	 * Get an empty array for a consistent response.
	 *
	 * @return array
	 */
	public function get_empty_array() {
		return array();
	}

	/**
	 * Delete the file cache for the services library.
	 *
	 * @return void
	 */
	public function delete_cache() {
		wpconsent()->file_cache->delete( $this->cache_key );
		if ( isset( $this->data ) ) {
			unset( $this->data );
		}
	}
}
