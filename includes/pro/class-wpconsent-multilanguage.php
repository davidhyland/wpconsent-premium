<?php
/**
 * Class WPConsent_Multilanguage.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Multilanguage.
 */
class WPConsent_Multilanguage {

	/**
	 * Override locale when needed for local context to this class.
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * Translatable options.
	 *
	 * @var string[]
	 */
	private $translatable_options = array(
		'banner_message',
		'accept_button_text',
		'cancel_button_text',
		'preferences_button_text',
		'preferences_panel_title',
		'preferences_panel_description',
		'cookie_policy_title',
		'cookie_policy_text',
		'save_preferences_button_text',
		'close_button_text',
		'content_blocking_placeholder_text',
		'cookie_table_header_name',
		'cookie_table_header_description',
		'cookie_table_header_duration',
		'cookie_table_header_category',
	);

	/**
	 * Get translatable options.
	 *
	 * @return string[]
	 */
	public function get_translatable_options() {
		return apply_filters( 'wpconsent_translatable_options', $this->translatable_options );
	}

	/**
	 * WPConsent_Multilanguage constructor.
	 */
	public function __construct() {
		add_filter( 'wpconsent_save_options', array( $this, 'maybe_save_translations' ), 10, 2 );
		add_filter( 'wpconsent_get_option', array( $this, 'maybe_get_translated_option' ), 10, 3 );

		add_filter( 'wpconsent_update_cookie_post', array( $this, 'maybe_update_cookie_post' ), 10, 6 );
		add_filter( 'wpconsent_cookie_data', array( $this, 'maybe_get_cookie_data' ), 10, 2 );

		add_filter( 'wpconsent_update_category', array( $this, 'maybe_update_category' ), 10, 4 );
		add_filter( 'wpconsent_category_data', array( $this, 'maybe_get_category_data' ), 10, 2 );

		add_filter( 'wpconsent_update_service', array( $this, 'maybe_update_service' ), 10, 5 );
		add_filter( 'wpconsent_service_data', array( $this, 'maybe_get_service_data' ), 10, 2 );

		// Register REST API endpoint for dynamic language loading.
		add_action( 'rest_api_init', array( $this, 'register_language_endpoint' ) );

		add_action( 'wpconsent_admin_notices', array( $this, 'maybe_add_notice' ), 5 );

		add_filter( 'wpconsent_banner_classes', array( $this, 'add_banner_classes' ) );

		add_filter( 'wpconsent_get_cookie_policy_id', array( $this, 'maybe_get_translated_id' ), 10, 2 );
		add_filter( 'wpconsent_get_privacy_policy_id', array( $this, 'maybe_get_translated_id' ), 10, 2 );
	}

	/**
	 * Maybe save the translations for the given locale.
	 *
	 * @param array $options The options.
	 * @param array $original_options The original options.
	 *
	 * @return array
	 */
	public function maybe_save_translations( $options, $original_options ) {
		if ( ! $this->multilanguage_enabled() ) {
			return $options;
		}

		$default_locale = $this->get_plugin_locale();
		$user_locale    = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );

		if ( $user_locale === $default_locale ) {
			return $options;
		}

		if ( ! isset( $options[ $user_locale ] ) || ! is_array( $options[ $user_locale ] ) ) {
			$options[ $user_locale ] = array();
		}

		// Otherwise let's move the translatable options to an array with the key of the locale.
		// And make sure we don't override previous values in the default locale.
		foreach ( $this->get_translatable_options() as $option ) {
			if ( isset( $options[ $option ] ) ) {
				$options[ $user_locale ][ $option ] = $options[ $option ];
				$options[ $option ]                 = $original_options[ $option ];
			}
		}

		return $options;
	}

	/**
	 * Maybe get the translated option for the given locale.
	 *
	 * @param mixed  $value The option value.
	 * @param string $option The option name.
	 * @param array  $options The options.
	 *
	 * @return mixed
	 */
	public function maybe_get_translated_option( $value, $option, $options ) {
		if ( ! in_array( $option, $this->get_translatable_options(), true ) || ! $this->multilanguage_enabled() ) {
			return $value;
		}

		$locale = $this->get_plugin_locale();
		// Let's get the locale from the wp options.
		$wplang = get_option( 'WPLANG' );

		if ( is_admin() ) {
			$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
		}

		if ( $locale === $wplang || 'en_US' === $locale && empty( $wplang ) ) {
			return $value;
		}

		// Let's see if we have a translation in the options based on the locale.
		if ( isset( $options[ $locale ][ $option ] ) ) {
			return $options[ $locale ][ $option ];
		}

		return $value;
	}

	/**
	 * Maybe update the cookie post with the given locale.
	 *
	 * @param bool   $updated Whether the default update should be prevented.
	 * @param int    $post_id The post ID.
	 * @param string $cookie_name The cookie name.
	 * @param string $cookie_description The cookie description.
	 * @param string $cookie_category The cookie category.
	 * @param int    $duration The cookie duration.
	 *
	 * @return bool
	 */
	public function maybe_update_cookie_post( $updated, $post_id, $cookie_name, $cookie_description, $cookie_category, $duration ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $updated;
		}

		$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );

		if ( $this->get_plugin_locale() === $locale ) {
			return false;
		}

		// Let's store these values in the post meta for the given locale.
		update_post_meta( $post_id, 'wpconsent_cookie_name_' . $locale, $cookie_name );
		update_post_meta( $post_id, 'wpconsent_cookie_description_' . $locale, $cookie_description );
		update_post_meta( $post_id, 'wpconsent_cookie_category_' . $locale, $cookie_category );
		update_post_meta( $post_id, 'wpconsent_cookie_duration_' . $locale, $duration );

		// Return true to prevent the default update.
		return true;
	}

	/**
	 * Maybe update the category with the given locale.
	 *
	 * @param bool   $updated Whether the default update should be prevented.
	 * @param int    $category_id The category ID.
	 * @param string $category_name The category name.
	 * @param string $category_description The category description.
	 *
	 * @return bool
	 */
	public function maybe_update_category( $updated, $category_id, $category_name, $category_description ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $updated;
		}

		$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );

		if ( $this->get_plugin_locale() === $locale ) {
			return false;
		}

		// Let's store these values in the post meta for the given locale.
		update_term_meta( $category_id, 'wpconsent_category_name_' . $locale, $category_name );
		update_term_meta( $category_id, 'wpconsent_category_description_' . $locale, $category_description );

		// Return true to prevent the default update.
		return true;
	}

	/**
	 * Maybe update the service with the given locale.
	 *
	 * @param bool   $updated Whether the default update should be prevented.
	 * @param int    $service_id The service ID.
	 * @param string $service_name The service name.
	 * @param string $service_description The service description.
	 * @param string $service_url The service URL.
	 *
	 * @return bool
	 */
	public function maybe_update_service( $updated, $service_id, $service_name, $service_description, $service_url ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $updated;
		}

		$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );

		if ( $this->get_plugin_locale() === $locale ) {
			return $updated;
		}

		// Let's store these values in the post meta for the given locale.
		update_post_meta( $service_id, 'wpconsent_service_name_' . $locale, $service_name );
		update_post_meta( $service_id, 'wpconsent_service_description_' . $locale, $service_description );
		update_post_meta( $service_id, 'wpconsent_service_url_' . $locale, $service_url );

		// Return true to prevent the default update.
		return true;
	}

	/**
	 * Maybe get the cookie data for the given cookie ID.
	 *
	 * @param array $cookie_data The cookie data.
	 * @param int   $post_id The cookie ID.
	 *
	 * @return array
	 */
	public function maybe_get_cookie_data( $cookie_data, $post_id ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $cookie_data;
		}

		$locale = $this->get_plugin_locale();
		if ( is_admin() ) {
			$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
			if ( $this->get_plugin_locale() === $locale ) {
				return $cookie_data;
			}
		}

		$fields = array(
			'name'        => 'wpconsent_cookie_name_',
			'description' => 'wpconsent_cookie_description_',
			'category'    => 'wpconsent_cookie_category_',
			'duration'    => 'wpconsent_cookie_duration_',
		);

		foreach ( $fields as $field => $meta_key ) {
			$translated_value = get_post_meta( $post_id, $meta_key . $locale, true );
			if ( ! empty( $translated_value ) ) {
				$cookie_data[ $field ] = $translated_value;
			}
		}

		return $cookie_data;
	}

	/**
	 * Maybe get the category data for the given category ID.
	 *
	 * @param array $category_data The category data.
	 * @param int   $category_id The category ID.
	 *
	 * @return array
	 */
	public function maybe_get_category_data( $category_data, $category_id ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $category_data;
		}

		$locale = $this->get_plugin_locale();
		if ( isset( $this->locale ) ) {
			$locale = $this->locale;
		}
		if ( is_admin() ) {
			$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
			if ( $this->get_plugin_locale() === $locale ) {
				return $category_data;
			}
		}

		$fields = array(
			'name'        => 'wpconsent_category_name_',
			'description' => 'wpconsent_category_description_',
		);

		foreach ( $fields as $field => $meta_key ) {
			$translated_value = get_term_meta( $category_id, $meta_key . $locale, true );
			if ( ! empty( $translated_value ) ) {
				$category_data[ $field ] = $translated_value;
			}
		}

		return $category_data;
	}

	/**
	 * Maybe get the service data for the given service ID.
	 *
	 * @param array $service_data The service data.
	 * @param int   $service_id The service ID.
	 *
	 * @return array
	 */
	public function maybe_get_service_data( $service_data, $service_id ) {

		if ( ! $this->multilanguage_enabled() ) {
			return $service_data;
		}

		$locale = $this->get_plugin_locale();
		if ( is_admin() ) {
			$locale = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
			if ( $this->get_plugin_locale() === $locale ) {
				return $service_data;
			}
		}

		$fields = array(
			'name'        => 'wpconsent_service_name_',
			'description' => 'wpconsent_service_description_',
			'url'         => 'wpconsent_service_url_',
		);

		foreach ( $fields as $field => $meta_key ) {
			$translated_value = get_post_meta( $service_id, $meta_key . $locale, true );
			if ( ! empty( $translated_value ) ) {
				$service_data[ $field ] = $translated_value;
			}
		}

		return $service_data;
	}

	/**
	 * Register the REST API endpoint for dynamic language loading
	 */
	public function register_language_endpoint() {
		// If disabling the language picker, don't register the endpoint.
		$language_switcher_enabled = wpconsent()->settings->get_option( 'show_language_picker' );
		if ( ! $language_switcher_enabled ) {
			return;
		}

		register_rest_route(
			'wpconsent/v1',
			'/language/(?P<locale>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_language_texts' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'locale' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) <= 10;
						},
					),
				),
			)
		);
	}

	/**
	 * Get language texts for the specified locale
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_language_texts( $request ) {
		$request_locale = sanitize_text_field( wp_unslash( $request['locale'] ) );
		$texts          = array();
		$options        = wpconsent()->settings->get_options();

		// Update the locale if it's not the default one.
		$current_locale = get_locale();
		if ( $current_locale !== $request_locale ) {
			// Update the wp locale to the requested one.
			switch_to_locale( $request_locale );
		}

		// Get all translatable options for the specified locale.
		foreach ( $this->get_translatable_options() as $option ) {
			if ( ! isset( $texts[ $option ] ) ) {
				$texts[ $option ] = '';
			}
			if ( isset( $options[ $request_locale ][ $option ] ) ) {
				$texts[ $option ] = $options[ $request_locale ][ $option ];
			} elseif ( ! empty( $options[ $option ] ) ) {
				$texts[ $option ] = $options[ $option ];
			}
			if ( 'cookie_policy_text' === $option && isset( $texts[ $option ] ) ) {
				$texts[ $option ] = wpconsent()->banner->maybe_replace_smart_tags( $texts[ $option ], $request_locale );
			}
			// Translate the text with other plugins.
			$texts[ $option ] = $this->maybe_translate( $texts[ $option ], $request_locale );
		}

		$this->locale = $request_locale;

		// Get categories with the new locale.
		$categories = wpconsent()->cookies->get_categories();

		// Add translated categories to response.
		$texts['categories'] = $categories;

		return rest_ensure_response( $texts );
	}

	/**
	 * If current user is viewing admin in a different language, show a notice.
	 *
	 * @return void
	 */
	public function maybe_add_notice() {
		$locale         = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
		$default_locale = $this->get_plugin_locale();

		if ( empty( $locale ) || $default_locale === $locale ) {
			return;
		}

		$languages     = wp_get_available_translations();
		$language_name = isset( $languages[ $locale ] ) ? $languages[ $locale ]['native_name'] : $locale;

		$notice = sprintf(
		/* translators: %s: language name */
			__( 'You are currently editing WPConsent content in %1$s. You can switch back to your site\'s default language by %2$sclicking here%3$s.', 'wpconsent-cookies-banner-privacy-suite' ),
			$language_name,
			'<button class="wpconsent-button wpconsent-button-text wpconsent-language-picker-item" data-language="' . esc_attr( $default_locale ) . '">',
			'</button>'
		);

		wpconsent()->notice->info(
			$notice,
			array(
				'slug' => 'language_notice',
			)
		);
	}

	/**
	 * Add classes to the banner based on the language switcher.
	 *
	 * @param string[] $classes The banner classes.
	 *
	 * @return string[]
	 */
	public function add_banner_classes( $classes ) {
		$language_switcher_enabled = wpconsent()->settings->get_option( 'show_language_picker' );
		if ( ! $language_switcher_enabled ) {
			return $classes;
		}

		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );

		if ( count( $enabled_languages ) > 0 ) {
			$classes[] = 'wpconsent-has-language-switcher';
		}

		return $classes;
	}

	/**
	 * Check if we have more than one language enabled.
	 *
	 * @return bool
	 */
	public function multilanguage_enabled() {
		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );

		if ( count( $enabled_languages ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the correct locale from multilanguage plugins or WordPress.
	 *
	 * This function checks for PolyLang and WPML plugins first, then falls back to WordPress's get_locale().
	 *
	 * @return string The locale.
	 */
	public function get_plugin_locale() {
		// Check for PolyLang.
		if ( function_exists( 'pll_current_language' ) ) {
			$pll_lang = pll_current_language( 'locale' );
			if ( $pll_lang ) {
				return $pll_lang;
			}
		}

		// Check for WPML.
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'wpml_get_current_language' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				// Try to get the locale from WPML.
				$wpml_lang = $sitepress->get_current_language();
				if ( $wpml_lang && method_exists( $sitepress, 'get_locale' ) ) {
					return $sitepress->get_locale( $wpml_lang );
				}

				// Alternative method for WPML.
				if ( function_exists( 'icl_get_languages_locales' ) ) {
					$locales = icl_get_languages_locales();
					if ( isset( $locales[ $wpml_lang ] ) ) {
						return $locales[ $wpml_lang ];
					}
				}
			}
		}

		// Check for TranslatePress.
		if ( function_exists( 'trp_get_locale' ) ) {
			$trp_locale = trp_get_locale();
			if ( $trp_locale ) {
				return $trp_locale;
			}
		}

		// Fall back to WordPress's get_locale.
		return get_locale();
	}

	/**
	 * Get the translated page ID for the given locale.
	 *
	 * @param int    $id The original page ID.
	 * @param string $locale The locale to translate to.
	 *
	 * @return int The translated page ID.
	 */
	public function maybe_get_translated_id( $id, $locale ) {
		// If a locale is specified, get the translated page IDs.
		// Check if WPML or Polylang is active (both support the wpml_object_id filter).
		if ( empty( $locale ) ) {
			$locale = $this->get_plugin_locale();
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			// For WPML, convert locale to language code if needed.
			$language_code = $locale;

			// If this is a locale like 'en_US', extract just the language part for WPML.
			if ( strpos( $locale, '_' ) !== false && defined( 'ICL_SITEPRESS_VERSION' ) ) {
				global $sitepress;
				if ( $sitepress && method_exists( $sitepress, 'get_language_code_from_locale' ) ) {
					$language_code = $sitepress->get_language_code_from_locale( $locale );
				} else {
					// Simple fallback - just take the part before the underscore.
					$language_code = substr( $locale, 0, strpos( $locale, '_' ) );
				}
			}

			// Get translated cookie policy page ID.
			if ( $id ) {
				$id = apply_filters( 'wpml_object_id', $id, 'page', true, $language_code );
			}
		}

		return $id;
	}

	/**
	 * Translate the text using the appropriate translation function, if it exists.
	 *
	 * @param string $text The text to translate.
	 * @param string $locale The locale to translate to.
	 *
	 * @return string The translated text.
	 */
	public function maybe_translate( $text, $locale ) {
		if ( function_exists( 'trp_translate' ) ) {
			return trp_translate( $text, $locale );
		}

		return $text;
	}
}
