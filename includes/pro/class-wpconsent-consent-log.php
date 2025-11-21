<?php
/**
 * Handles consent logging functionality
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Consent_Log
 */
class WPConsent_Consent_Log {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register REST API endpoints
	 */
	public function register_endpoints() {
		// Let's only add the endpoint if the option is enabled.
		if ( ! wpconsent()->settings->get_option( 'records_of_consent' ) ) {
			return;
		}

		register_rest_route(
			'wpconsent/v1',
			'/log-consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_consent' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'consent_data' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Check permission
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	public function check_permission( $request ) {
		return true;
	}

	/**
	 * Handle consent logging
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function log_consent( $request ) {

		$consent_data = json_decode( $request['consent_data'] );
		$user_id      = get_current_user_id();
		$ip_address   = wpconsent()->ip->anonymize_ip( wpconsent()->ip->get_client_ip() );

		// Get country code for the IP address.
		$country_code = $this->get_country_code( $ip_address );

		$result = $this->add_log_entry( $user_id, $ip_address, $consent_data, $country_code );

		if ( false === $result ) {
			return new WP_REST_Response(
				array( 'success' => false ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'success' => true ),
			200
		);
	}

	/**
	 * Get country code for an IP address
	 *
	 * @param string $ip_address The IP address.
	 *
	 * @return string Country code or 'XX' on failure
	 */
	public function get_country_code( $ip_address ) {
		$country_code = wpconsent()->geolocation->get_location_for_ip( $ip_address );

		// Return 'XX' if no valid country code was found.
		return ( null === $country_code ) ? 'XX' : $country_code;
	}

	/**
	 * Add a log entry
	 *
	 * @param int    $user_id The user ID.
	 * @param string $ip_address The IP address.
	 * @param string $consent_data The consent data.
	 * @param string $country_code The country code.
	 *
	 * @return false|int
	 */
	public function add_log_entry( $user_id, $ip_address, $consent_data, $country_code = 'XX' ) {
		global $wpdb;

		$result = $wpdb->insert(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpconsent_consent_logs',
			array(
				'user_id'      => $user_id,
				'ip_address'   => $ip_address,
				'country_code' => $country_code,
				'consent_data' => wp_json_encode( $consent_data ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return $result;
	}
}
