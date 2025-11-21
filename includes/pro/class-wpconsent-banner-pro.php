<?php
/**
 * Handles the WPConsent banner in the pro plugin.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Banner_Pro
 */
class WPConsent_Banner_Pro extends WPConsent_Banner {

	/**
	 * Get the banner top buttons.
	 *
	 * @return string
	 */
	public function get_banner_top_buttons() {
		$buttons                   = parent::get_banner_top_buttons();
		$language_switcher_enabled = wpconsent()->settings->get_option( 'show_language_picker' );

		if ( ! $language_switcher_enabled ) {
			return $buttons;
		}
		// Get currently enabled languages.
		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );
		// Use the get_plugin_locale method from WPConsent_Multilanguage to get the correct locale.
		$current_language = wpconsent()->multilanguage->get_plugin_locale();

		// Get the WordPress default language from WPLANG option
		$wp_default_language = get_option( 'WPLANG' );
		if ( empty( $wp_default_language ) ) {
			$wp_default_language = 'en_US';
		}

		// Only show language switcher if we have more than one language enabled.
		if ( count( $enabled_languages ) === 0 ) {
			return $buttons;
		}

		// Make sure the current language is in the enabled languages array
		if ( ! in_array( $current_language, $enabled_languages, true ) ) {
			$enabled_languages = array_merge( array( $current_language ), $enabled_languages );
		}

		// Always add the WordPress default language if it's not already in the enabled languages array
		if ( ! in_array( $wp_default_language, $enabled_languages, true ) ) {
			$enabled_languages = array_merge( array( $wp_default_language ), $enabled_languages );
		}

		// Let's make sure wp_get_available_translations is available.
		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			// Include the file if it doesn't exist.
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}

		// Get all available languages.
		$available_languages = wp_get_available_translations();
		if ( ! $available_languages ) {
			$available_languages = array();
		}

		// Add English (United States) only if it's in the enabled languages array or if it's the WordPress default language
		if ( in_array( 'en_US', $enabled_languages, true ) || $wp_default_language === 'en_US' ) {
			$available_languages['en_US'] = array(
				'language'     => 'en_US',
				'english_name' => 'English (United States)',
				'native_name'  => 'English (United States)',
			);
		}

		// Get current language name.
		$current_language = isset( $_COOKIE['wpconsent_language'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['wpconsent_language'] ) ) : $current_language;

		$switcher = '<div class="wpconsent-language-picker">';

		$switcher .= '<button type="button" class="wpconsent-language-switch-button">' . wpconsent_get_icon( 'globe', 16, 16, '0 -960 960 960' ) . '</button>';
		$switcher .= '<div class="wpconsent-language-dropdown">';
		$switcher .= '<div class="wpconsent-language-list">';

		foreach ( $enabled_languages as $lang ) {
			if ( isset( $available_languages[ $lang ] ) ) {
				$is_active = $lang === $current_language ? 'active' : '';

				$switcher .= sprintf(
					'<button type="button" class="wpconsent-language-item %s" data-language="%s">%s</button>',
					esc_attr( $is_active ),
					esc_attr( $lang ),
					esc_html( $available_languages[ $lang ]['native_name'] )
				);
			}
		}

		$switcher .= '</div>';
		$switcher .= '</div>';
		$switcher .= '</div>';

		return $switcher . $buttons;
	}
}
