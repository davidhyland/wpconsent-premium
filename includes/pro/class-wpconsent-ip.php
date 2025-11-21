<?php
/**
 * IP address handling
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_IP
 */
class WPConsent_IP {

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	public function get_client_ip() {
		$ip_address = '';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// If multiple IP addresses are provided, the first one is the client IP.
			$ip_address = explode( ',', $ip_address );
			$ip_address = sanitize_text_field( wp_unslash( $ip_address[0] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip_address;
	}

	/**
	 * Anonymize IP address
	 *
	 * @param string $ip_address The IP address.
	 *
	 * @return string
	 */
	public function anonymize_ip( $ip_address ) {
		// Check if ipv4 or ipv6.
		if ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return $this->anonymize_ip_v4( $ip_address );
		} elseif ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $this->anonymize_ip_v6( $ip_address );
		}

		return $ip_address;
	}

	/**
	 * Anonymize IPv4 address
	 *
	 * @param string $ip The IP address.
	 *
	 * @return string
	 */
	private function anonymize_ip_v4( $ip ) {
		$parts = explode( '.', $ip );

		if ( count( $parts ) !== 4 ) {
			return $ip;
		}

		$parts[3] = '000';

		return implode( '.', $parts );
	}

	/**
	 * Anonymize IPv6 address
	 *
	 * @param string $ip The IP address.
	 *
	 * @return string
	 */
	private function anonymize_ip_v6( $ip ) {
		$parts = explode(':', $ip);
		// Replace the last five groups with '0000' or simply use '::' for brevity
		for ($i = 3; $i < 8; $i++) {
			if (isset($parts[$i])) {
				$parts[$i] = '0000';
			}
		}
		// Join the parts back together
		return implode(':', array_slice($parts, 0, 8));
	}
}