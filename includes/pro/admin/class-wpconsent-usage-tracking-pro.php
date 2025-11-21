<?php
/**
 * WPConsent Usage Tracking Pro
 *
 * @package WPConsent
 * @since 1.0.0
 */

/**
 * Class WPConsent_Usage_Tracking_Pro
 */
class WPConsent_Usage_Tracking_Pro extends WPConsent_Usage_Tracking {

	/**
	 * Get the type for the request.
	 *
	 * @return string The plugin type.
	 * @since 1.0.0
	 */
	public function get_type() {
		return 'pro';
	}

	/**
	 * Is the usage tracking enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return apply_filters( 'wpconsent_usage_tracking_is_allowed', true );
	}

	/**
	 * Add pro-specific data to the request.
	 *
	 * @return array
	 */
	public function get_data() {
		$data = parent::get_data();

		$activated = get_option( 'wpconsent_activated', array() );

		$data['wpconsent_is_pro']       = true;
		$data['wpconsent_license_type'] = wpconsent()->license->type();
		$data['wpconsent_license_key']  = wpconsent()->license->get();

		if ( ! empty( $activated['wpconsent_pro'] ) ) {
			$data['wpconsent_pro_installed_date'] = $activated['wpconsent_pro'];
		}

		return $data;
	}

	/**
	 * Get languages data for tracking.
	 *
	 * @return array
	 */
	protected function get_languages_data() {
		$settings = wpconsent()->settings->get_options();

		if ( empty( $settings['enabled_languages'] ) || ! is_array( $settings['enabled_languages'] ) ) {
			return array();
		}

		return array(
			'wpconsent_languages' => $settings['enabled_languages'],
		);
	}

	/**
	 * Get geolocation rules data for tracking (Pro version).
	 *
	 * @return array
	 */
	protected function get_geo_rules_data() {
		$settings = wpconsent()->settings->get_options();

		if ( empty( $settings['geolocation_groups'] ) || ! is_array( $settings['geolocation_groups'] ) ) {
			return array();
		}

		$rules = array();
		foreach ( $settings['geolocation_groups'] as $group_id => $group ) {
			$rules[] = array(
				'name'      => isset( $group['name'] ) ? $group['name'] : '',
				'locations' => $this->extract_geo_locations( $group ),
				'settings'  => $this->filter_geo_rule_settings( $group ),
			);
		}

		return array(
			'wpconsent_geo_rules' => $rules,
		);
	}

	/**
	 * Extract location data from a geo rule group.
	 *
	 * @param array $group The geolocation group data.
	 * @return array Array of location codes.
	 */
	protected function extract_geo_locations( $group ) {
		if ( empty( $group['locations'] ) || ! is_array( $group['locations'] ) ) {
			return array();
		}

		// get location codes, if US state prepend the country code 'US', if continent prepend with 'CT'.
		$locations = array_map(
			function ( $location ) {
				if ( 'us_state' === $location['type'] ) {
					return 'US' . $location['code'];
				}
				if ( 'continent' === $location['type'] ) {
					return 'CT' . $location['code'];
				}
				return $location['code'];
			},
			$group['locations']
		);
		return $locations;
	}

	/**
	 * Filter geo rule settings, removing content and keeping only configuration.
	 *
	 * @param array $group The geolocation group data.
	 * @return array Filtered settings for this rule.
	 */
	protected function filter_geo_rule_settings( $group ) {
		// Get ignore list for geo rule settings.
		$ignored_geo_fields = $this->get_ignored_geo_rule_fields();

		// Start with all group settings.
		$filtered = $group;

		// Remove fields we don't want.
		foreach ( $ignored_geo_fields as $field ) {
			unset( $filtered[ $field ] );
		}

		// Remove the data we're handling separately.
		unset( $filtered['locations'] );
		unset( $filtered['name'] ); // Already extracted above.
		unset( $filtered['group_id'] ); // Already extracted above.

		return $filtered;
	}

	/**
	 * Get list of geo rule fields to ignore from tracking.
	 *
	 * @return array
	 */
	protected function get_ignored_geo_rule_fields() {
		$ignored = array(
			// Button content - same as main settings.
			'accept_button_text',
			'cancel_button_text',
			'preferences_button_text',
			'banner_message',
			'button_order',
		);

		return apply_filters( 'wpconsent_usage_tracking_ignored_geo_rule_fields', $ignored );
	}

	/**
	 * Track WPConsent-specific data including Pro features.
	 *
	 * @return array
	 */
	public function get_wpconsent_stats() {
		$wpconsent_data = parent::get_wpconsent_stats();

		// Get custom scripts count (Pro feature).
		$custom_scripts = get_option( 'wpconsent_custom_scripts', array() );
		if ( ! empty( $custom_scripts ) && is_array( $custom_scripts ) ) {
			$wpconsent_data['wpconsent_total_custom_scripts'] = count( $custom_scripts );
		} else {
			// Always include the count, even if zero, to indicate Pro feature exists.
			$wpconsent_data['wpconsent_total_custom_scripts'] = 0;
		}

		return $wpconsent_data;
	}
}
