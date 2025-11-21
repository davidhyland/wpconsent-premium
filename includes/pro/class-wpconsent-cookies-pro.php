<?php
/**
 * Class used to handle the cookies we use in the plugin - Pro version.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Cookies_Pro.
 */
class WPConsent_Cookies_Pro extends WPConsent_Cookies {

	/**
	 * Add a new script to the WordPress option.
	 *
	 * @param string $script_category The category of the script.
	 * @param string $script_service The service of the script.
	 * @param string $script_type The type of the script (script, iframe).
	 * @param string $script_tag The script tag or content.
	 * @param string $script_blocked_elements The blocked elements (comma-separated string).
	 *
	 * @return array|false The script data if successful, false otherwise.
	 */
	public function add_script( $script_category, $script_service, $script_type, $script_tag, $script_blocked_elements ) {
		$existing_scripts = get_option( 'wpconsent_custom_scripts', array() );

		// Generate a unique ID using WordPress functions.
		$prefix    = $script_category . '-' . $script_service;
		$unique_id = $prefix . '-' . wp_generate_password( 12, false, false );

		$blocked_elements_array = $this->normalize_blocked_elements( $script_blocked_elements );

		$existing_scripts[ $unique_id ] = array(
			'category'         => $script_category,
			'service'          => $script_service,
			'type'             => $script_type,
			'tag'              => $script_tag,
			'blocked_elements' => $blocked_elements_array,
		);

		$updated = update_option( 'wpconsent_custom_scripts', $existing_scripts );

		if ( $updated ) {
			return array(
				'id'               => $unique_id,
				'category'         => $script_category,
				'service'          => $script_service,
				'type'             => $script_type,
				'tag'              => $script_tag,
				'blocked_elements' => $script_blocked_elements,
			);
		}

		return false;
	}

	/**
	 * Modify an existing script in the WordPress option.
	 *
	 * @param string $script_id The unique ID of the script to modify.
	 * @param string $script_category The new category of the script.
	 * @param string $script_service The new service of the script.
	 * @param string $script_type The type of the script (script, iframe).
	 * @param string $script_tag The new script tag or content.
	 * @param string $script_blocked_elements The new blocked elements (comma-separated string).
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function modify_script( $script_id, $script_category, $script_service, $script_type, $script_tag, $script_blocked_elements ) {
		$existing_scripts = get_option( 'wpconsent_custom_scripts', array() );

		if ( ! isset( $existing_scripts[ $script_id ] ) ) {
			return false;
		}

		$blocked_elements_array = $this->normalize_blocked_elements( $script_blocked_elements );

		$existing_scripts[ $script_id ] = array(
			'category'         => $script_category,
			'service'          => $script_service,
			'type'             => $script_type,
			'tag'              => $script_tag,
			'blocked_elements' => $blocked_elements_array,
		);

		return update_option( 'wpconsent_custom_scripts', $existing_scripts );
	}

	/**
	 * Normalize blocked elements from comma-separated string to array.
	 *
	 * @param string $blocked_elements Comma-separated blocked elements string.
	 *
	 * @return array Normalized array of blocked elements.
	 */
	private function normalize_blocked_elements( $blocked_elements ) {
		if ( empty( $blocked_elements ) ) {
			return array();
		}

		if ( is_array( $blocked_elements ) ) {
			return array_filter( array_map( 'trim', $blocked_elements ) );
		}

		$elements = array_map( 'trim', explode( ',', $blocked_elements ) );

		return array_filter( $elements );
	}

	/**
	 * Convert blocked elements array back to comma-separated string.
	 *
	 * @param array $blocked_elements_array Array of blocked elements.
	 *
	 * @return string Comma-separated string.
	 */
	public function blocked_elements_to_string( $blocked_elements_array ) {
		if ( empty( $blocked_elements_array ) || ! is_array( $blocked_elements_array ) ) {
			return '';
		}

		return implode( ', ', array_filter( $blocked_elements_array ) );
	}

	/**
	 * Clear cookies cache and translation strings cache (Pro version).
	 * Extends the base clear_cookies_cache method with pro-specific functionality.
	 *
	 * @return void
	 */
	public function clear_cookies_cache() {
		// Call parent method to clear base caches.
		parent::clear_cookies_cache();

		// Clear translation strings cache (pro-specific).
		delete_transient( 'wpconsent_translation_strings' );
	}

	/**
	 * Reset categories and cookies to their default state (Pro version).
	 * Extends the base reset_to_defaults method with pro-specific functionality.
	 *
	 * @return void
	 */
	public function reset_to_defaults() {
		// Call parent method to reset base categories and cookies.
		parent::reset_to_defaults();

		// Reset translation flags using the translation services class.
		if ( wpconsent()->translation_services ) {
			wpconsent()->translation_services->reset_translation_flags();
		}
	}

	/**
	 * Delete a script from the WordPress option.
	 *
	 * @param string $script_id The unique ID of the script to delete.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function delete_script( $script_id ) {
		$existing_scripts = get_option( 'wpconsent_custom_scripts', array() );

		if ( ! isset( $existing_scripts[ $script_id ] ) ) {
			return false;
		}

		unset( $existing_scripts[ $script_id ] );

		return update_option( 'wpconsent_custom_scripts', $existing_scripts );
	}
}
