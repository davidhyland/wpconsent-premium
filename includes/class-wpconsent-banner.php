<?php
/**
 * Class used to load and output the cookie banner.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Banner.
 */
class WPConsent_Banner {

	/**
	 * Banner settings.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_footer', array( $this, 'maybe_output_banner' ) );
	}

	/**
	 * Output the banner if enabled.
	 *
	 * @return void
	 */
	public function maybe_output_banner() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Don't load in legacy widget preview in the block editor.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Don't load in the customizer preview.
		if ( is_customize_preview() ) {
			return;
		}

		$this->output_banner();
	}

	/**
	 * Check if the banner is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return wpconsent()->settings->get_option( 'enable_consent_banner' );
	}

	/**
	 * Output the banner with proper escaping.
	 *
	 * @return void
	 */
	public function output_banner() {
		// Allowed tags we start with the ones from wp_kses_post.
		$allowed_tags = wp_kses_allowed_html( 'post' );

		// Add the SVG tags from our icons.
		$allowed_tags = array_merge( $allowed_tags, wpconsent_get_icon_allowed_tags() );
		// Let's allow tabindex attribute.
		$allowed_tags['div']['tabindex'] = true;
		$allowed_tags['div']['part']     = true;
		$allowed_tags['button']['part']  = true;

		// Get colors and create CSS variables.
		$colors   = $this->get_color_settings();
		$css_vars = $this->get_css_variables( $colors );

		// Create the Shadow DOM container with CSS variables.
		echo '<div id="wpconsent-root" style="' . esc_attr( $css_vars ) . '">';
		echo '<div id="wpconsent-container" style="display: none;"></div>';

		// Create a template that contains both styles and HTML.
		echo '<template id="wpconsent-template">';

		// Add the banner HTML to the template.
		echo wp_kses( $this->get_banner(), $allowed_tags );

		// Add the preferences modal to the template.
		echo $this->get_preferences_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Add the floating button to the template.
		$this->floating_consent_button();

		echo '</template>';
		echo '</div>';
	}

	/**
	 * Get CSS variables string from colors array.
	 *
	 * @param array $colors Color settings.
	 *
	 * @return string
	 */
	public function get_css_variables( $colors ) {
		$vars = array(
			'--wpconsent-z-index: 900000;',
			'--wpconsent-background: ' . $colors['background'] . ';',
			'--wpconsent-text: ' . $colors['text'] . ';',
			'--wpconsent-outline-color: ' . $this->hex_to_rgba( $colors['text'], 0.2 ) . ';',
			'--wpconsent-accept-bg: ' . $colors['accept_bg'] . ';',
			'--wpconsent-cancel-bg: ' . $colors['cancel_bg'] . ';',
			'--wpconsent-preferences-bg: ' . $colors['preferences_bg'] . ';',
			'--wpconsent-accept-color: ' . $colors['accept_color'] . ';',
			'--wpconsent-cancel-color: ' . $colors['cancel_color'] . ';',
			'--wpconsent-preferences-color: ' . $colors['preferences_color'] . ';',
			'--wpconsent-font-size: ' . $colors['font_size'] . ';',
		);

		return implode( ';', $vars );
	}

	/**
	 * Convert hex color to rgba.
	 *
	 * @param string $hex Hex color code.
	 * @param float  $opacity Opacity value.
	 *
	 * @return string
	 */
	private function hex_to_rgba( $hex, $opacity ) {
		$hex = str_replace( '#', '', $hex );

		// Convert shorthand hex to full hex.
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		// Convert hex to rgb.
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return "rgba({$r}, {$g}, {$b}, {$opacity})";
	}

	/**
	 * Get the banner markup.
	 *
	 * @return string
	 */
	public function get_banner() {
		$font_size       = wpconsent()->settings->get_option( 'banner_font_size', '16px' );
		$button_size     = wpconsent()->settings->get_option( 'banner_button_size', 'regular' );
		$button_corner   = wpconsent()->settings->get_option( 'banner_button_corner', 'slightly-rounded' );
		$button_type     = wpconsent()->settings->get_option( 'banner_button_type', 'filled' );
		$button_order    = wpconsent()->settings->get_option(
			'button_order',
			array(
				'accept',
				'cancel',
				'preferences',
			)
		);
		$banner_layout   = wpconsent()->settings->get_option( 'banner_layout', 'long' );
		$banner_position = wpconsent()->settings->get_option( 'banner_position', 'top' );
		$position_class  = ! empty( $banner_position ) ? 'wpconsent-banner-' . esc_attr( $banner_layout ) . '-' . esc_attr( $banner_position ) : '';
		$logo            = wpconsent()->settings->get_option( 'banner_logo', '' );
		$banner_classes  = apply_filters(
			'wpconsent_banner_classes',
			array(
				'wpconsent-banner-holder',
				'wpconsent-banner-' . $banner_layout,
				$position_class,
			)
		);

		$html = '<div class="' . esc_attr( implode( ' ', $banner_classes ) ) . '" id="wpconsent-banner-holder" tabindex="-1" aria-labelledby="wpconsent-banner-title" role="dialog" aria-modal="true">';

		$html .= '<div class="wpconsent-banner" part="wpconsent-banner">';
		$html .= $this->get_banner_top_buttons();
		if ( ! empty( $logo ) ) {
			$html .= '<div class="wpconsent-banner-header">';

			$site_name = get_bloginfo( 'name' );

			$html .= '<div class="wpconsent-banner-logo"><img height="30" src="' . esc_url( $logo ) . '" alt="' . esc_html( $site_name ) . '" /></div>';
			$html .= '</div>';
		}

		$text = wpconsent()->settings->get_option( 'banner_message', esc_html__( 'This website uses cookies to ensure you get the best experience on our website.', 'wpconsent-cookies-banner-privacy-suite' ) );

		$html .= '<div class="wpconsent-banner-body" part="wpconsent-banner-body">';
		$html .= '<h2 id="wpconsent-banner-title" class="screen-reader-text">' . esc_html__( 'Cookie Consent', 'wpconsent-cookies-banner-privacy-suite' ) . '</h2>';
		$html .= '<div class="wpconsent-banner-message" tabindex="0">' . wp_kses_post( wpautop( $text ) ) . '</div>';
		$html .= '</div>';

		$html .= '<div class="wpconsent-banner-footer wpconsent-button-size-' . esc_attr( $button_size ) . ' wpconsent-button-corner-' . esc_attr( $button_corner ) . ' wpconsent-button-type-' . esc_attr( $button_type ) . '" part="wpconsent-banner-footer">';

		foreach ( $button_order as $button_id ) {
			$enabled = wpconsent()->settings->get_option( $button_id . '_button_enabled', true );
			if ( ! $enabled ) {
				continue;
			}
			$button_text = wpconsent()->settings->get_option( $button_id . '_button_text', '' );

			$html .= '<button type="button" id="wpconsent-' . esc_attr( $button_id ) . '-all" class="wpconsent-' . esc_attr( $button_id ) . '-cookies wpconsent-banner-button wpconsent-' . esc_attr( $button_id ) . '-all" part="wpconsent-button-' . esc_attr( $button_id ) . '">' . esc_html( $button_text ) . '</button>';
		}

		$html .= '</div>'; // .wpconsent-banner-footer
		$html .= $this->powered_by();
		$html .= '</div>'; // .wpconsent-banner
		$html .= '</div>';// .wpconsent-banner-holder

		return $html;
	}

	/**
	 * Get the top buttons for the banner.
	 *
	 * @return string
	 */
	public function get_banner_top_buttons() {
		// Check if close button is disabled in settings.
		if ( wpconsent()->settings->get_option( 'disable_close_button' ) ) {
			return '';
		}

		$close_text = esc_attr__( 'Close', 'wpconsent-cookies-banner-privacy-suite' );

		return '<button class="wpconsent-banner-close" id="wpconsent-banner-close" aria-label="' . esc_attr( $close_text ) . '">' . wpconsent_get_icon( 'close', 12, 12 ) . '</button>';
	}

	/**
	 * Get the color settings.
	 *
	 * @return array
	 */
	public function get_color_settings() {
		return array(
			'background'        => wpconsent()->settings->get_option( 'banner_background_color', '#FFFFFF' ),
			'text'              => wpconsent()->settings->get_option( 'banner_text_color', '#000000' ),
			'button_text'       => wpconsent()->settings->get_option( 'banner_button_text_color', '#FFFFFF' ),
			'accept_bg'         => wpconsent()->settings->get_option( 'banner_accept_bg', '#0073AA' ),
			'cancel_bg'         => wpconsent()->settings->get_option( 'banner_cancel_bg', '#0073AA' ),
			'preferences_bg'    => wpconsent()->settings->get_option( 'banner_preferences_bg', '#0073AA' ),
			'accept_color'      => wpconsent()->settings->get_option( 'banner_accept_color', '#FFFFFF' ),
			'cancel_color'      => wpconsent()->settings->get_option( 'banner_cancel_color', '#FFFFFF' ),
			'preferences_color' => wpconsent()->settings->get_option( 'banner_preferences_color', '#FFFFFF' ),
			'font_size'         => wpconsent()->settings->get_option( 'banner_font_size', '16px' ),
		);
	}

	/**
	 * Get the preferences modal.
	 *
	 * @return string
	 */
	public function get_preferences_modal() {
		$categories              = wpconsent()->cookies->get_categories();
		$accept_button_text      = wpconsent()->settings->get_option( 'accept_button_text', '' );
		$logo                    = wpconsent()->settings->get_option( 'banner_logo', '' );
		$cookie_policy_title     = wpconsent()->settings->get_option( 'cookie_policy_title', esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) );
		$preferences_panel_title = wpconsent()->settings->get_option( 'preferences_panel_title', esc_html__( 'Cookie Preferences', 'wpconsent-cookies-banner-privacy-suite' ) );

		$html = '<div id="wpconsent-preferences-modal" class="wpconsent-preferences-modal" style="display:none;" tabindex="-1" role="dialog" aria-labelledby="wpconsent-preferences-title" aria-modal="true" part="wpconsent-preferences-modal">';

		$html .= '<div class="wpconsent-preferences-content">';

		// Preferences header div.
		$html .= '<div class="wpconsent-preferences-header">';
		$html .= '<h2 id="wpconsent-preferences-title" tabindex="0">' . esc_html( $preferences_panel_title ) . '</h2>';
		$html .= '<div class="wpconsent-preferences-header-right">';
		if ( ! empty( $logo ) ) {
			$site_name = get_bloginfo( 'name' );

			$html .= '<div class="wpconsent-banner-logo"><img height="30" src="' . esc_url( $logo ) . '" alt="' . esc_html( $site_name ) . '" /></div>';
		}
		$html .= '<button class="wpconsent-preferences-header-close" id="wpconsent-preferences-close" aria-label="' . esc_attr__( 'Close', 'wpconsent-cookies-banner-privacy-suite' ) . '" aria-label="' . esc_attr__( 'Close', 'wpconsent-cookies-banner-privacy-suite' ) . '">&times;</button>';

		$html .= '</div>'; // .wpconsent-preferences-header-right
		$html .= '</div>'; // .wpconsent-preferences-header
		$html .= '<div class="wpconsent_preferences_panel_description">' . wpautop( wp_kses_post( wpconsent()->settings->get_option( 'preferences_panel_description', esc_html__( 'Manage your cookie preferences below:', 'wpconsent-cookies-banner-privacy-suite' ) ) ) ) . '</div>';

		$html .= '<div class="wpconsent-preference-cookies wpconsent-preferences-accordion">';
		foreach ( $categories as $category_slug => $category ) {
			$all_cookies = $this->get_cookies_from_cache();
			$cookies     = isset( $all_cookies[ $category['id'] ] ) ? $all_cookies[ $category['id'] ] : array();

			if ( empty( $cookies['cookies'] ) && empty( $cookies['services'] ) ) {
				continue;
			}

			$html .= '<div class="wpconsent-preferences-accordion-item wpconsent-cookie-category wpconsent-cookie-category-' . esc_attr( $category_slug ) . '">';
			$html .= '<div class="wpconsent-preferences-accordion-header">';
			$html .= '<div class="wpconsent-cookie-category-text">';
			$html .= '<button class="wpconsent-preferences-accordion-toggle">';
			$html .= '<span class="wpconsent-preferences-accordion-arrow"></span>';
			$html .= '</button>';  // .wpconsent-preferences-accordion-toggle
			$html .= '<label>' . esc_html( $category['name'] ) . '</label>';
			$html .= '</div>'; // .wpconsent-cookie-category-text
			$html .= '<div class="wpconsent-cookie-category-checkbox">';
			if ( 'essential' === $category_slug ) {
				$html .= '<label class="wpconsent-preferences-checkbox-toggle wpconsent-preferences-checkbox-toggle-disabled">';
				$html .= '<input type="checkbox" id="cookie-category-' . esc_attr( $category_slug ) . '" checked disabled>';
				$html .= '<span class="wpconsent-preferences-checkbox-toggle-slider"></span>';
				$html .= '</label>';  // .wpconsent-preferences-checkbox-toggle
			} else {
				$html .= '<label class="wpconsent-preferences-checkbox-toggle">';
				$html .= '<input type="checkbox" id="cookie-category-' . esc_attr( $category_slug ) . '" name="wpconsent_cookie[]" value="' . esc_attr( $category_slug ) . '" ' . ( $category['required'] ? 'checked disabled' : '' ) . '>';
				$html .= '<span class="wpconsent-preferences-checkbox-toggle-slider"></span>';
				$html .= '</label>';  // .wpconsent-preferences-checkbox-toggle
			}
			$html .= '</div>'; // .wpconsent-cookie-category-checkbox
			$html .= '</div>'; // .wpconsent-preferences-accordion-header

			$html .= '<div class="wpconsent-preferences-accordion-content">';
			$html .= '<p tabindex="0">' . wp_kses_post( $category['description'] ) . '</p>';
			$html .= $this->get_cookies_content_by_category( $cookies, $category['name'], $category_slug );
			$html .= '</div>'; // .wpconsent-preferences-accordion-content

			$html .= '</div>'; // .wpconsent-cookie-category
		}

		// Cookie policy section, if set.
		$cookie_policy_page_id = wpconsent()->settings->get_option( 'cookie_policy_page', 0 );
		if ( $cookie_policy_page_id ) {
			$cookie_policy_page_url = get_permalink( $cookie_policy_page_id );
			$privacy_policy         = get_privacy_policy_url();

			$html .= '<div class="wpconsent-preferences-accordion-item wpconsent-cookie-category">';
			$html .= '<div class="wpconsent-preferences-accordion-header">';
			$html .= '<div class="wpconsent-cookie-category-text">';
			$html .= '<button class="wpconsent-preferences-accordion-toggle">';
			$html .= '<span class="wpconsent-preferences-accordion-arrow"></span>';
			$html .= '</button>';  // .wpconsent-preferences-accordion-toggle
			$html .= '<label class="wpconsent-cookie-policy-title">' . esc_html( $cookie_policy_title ) . '</label>';
			$html .= '</div>'; // .wpconsent-cookie-category-text
			$html .= '</div>'; // .wpconsent-preferences-accordion-header

			$html .= '<div class="wpconsent-preferences-accordion-content">';
			$html .= '<p tabindex="0" class="wpconsent-cookie-policy-text">';

			if ( $privacy_policy ) {
				$default_cookie_policy_text = sprintf(
				/* translators: 1: Cookie policy URL, 2: Privacy policy URL */
					esc_html__( 'You can find more information about our %1$s and %2$s.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a href="' . esc_url( $cookie_policy_page_url ) . '">' . esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>',
					'<a href="' . esc_url( $privacy_policy ) . '">' . esc_html__( 'Privacy Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>'
				);
			} else {
				$default_cookie_policy_text = sprintf(
				/* translators: %s: Cookie policy URL */
					esc_html__( 'You can find more information in our %s.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a href="' . esc_url( $cookie_policy_page_url ) . '">' . esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>'
				);
			}
			$html .= wp_kses_post( $this->maybe_replace_smart_tags( wpconsent()->settings->get_option( 'cookie_policy_text', $default_cookie_policy_text ) ) );
			$html .= '</p>';
			$html .= '</div>'; // .wpconsent-preferences-accordion-content
			$html .= '</div>'; // .wpconsent-cookie-category
		}
		$html .= '</div>'; // .wpconsent-preference-cookies

		$save_preferences_text = wpconsent()->settings->get_option( 'save_preferences_button_text', esc_html__( 'Save and Close', 'wpconsent-cookies-banner-privacy-suite' ) );
		$close_text            = wpconsent()->settings->get_option( 'close_button_text', esc_html__( 'Close', 'wpconsent-cookies-banner-privacy-suite' ) );
		$button_size           = wpconsent()->settings->get_option( 'banner_button_size', 'regular' );
		$button_corner         = wpconsent()->settings->get_option( 'banner_button_corner', 'slightly-rounded' );
		$button_type           = wpconsent()->settings->get_option( 'banner_button_type', 'filled' );

		$html .= '<div class="wpconsent-preferences-actions">';
		$html .= '<div class="wpconsent-preferences-buttons wpconsent-button-size-' . esc_attr( $button_size ) . ' wpconsent-button-corner-' . esc_attr( $button_corner ) . ' wpconsent-button-type-' . esc_attr( $button_type ) . '">';
		$html .= '<div class="wpconsent-preferences-buttons-left">';
		$html .= '<button class="wpconsent-accept-all wpconsent-banner-button">' . esc_html( $accept_button_text ) . '</button>';
		$html .= '<button class="wpconsent-close-preferences wpconsent-banner-button">' . esc_html( $close_text ) . '</button>';
		$html .= '</div>'; // .wpconsent-preferences-buttons-left
		$html .= '<button class="wpconsent-save-preferences wpconsent-banner-button">' . esc_html( $save_preferences_text ) . '</button>';
		$html .= '</div>'; // .wpconsent-preferences-buttons
		$html .= '</div>'; // .wpconsent-preferences-actions
		// div for Powered by WPConsent.
		if ( ! wpconsent()->settings->get_option( 'hide_powered_by' ) ) {
			$html .= '<div class="wpconsent-preferences-powered-by">';
			$html .= $this->powered_by();
			$html .= '</div>'; // .wpconsent-preferences-powered-by
		}
		$html .= '</div>'; // .wpconsent-preferences-content
		$html .= '</div>'; // #wpconsent-preferences-modal

		return $html;
	}

	/**
	 * Get cookies from cache or database
	 *
	 * @return array
	 */
	private function get_cookies_from_cache() {
		$cache_key = 'wpconsent_preference_cookies';
		$cookies   = get_transient( $cache_key );

		if ( false === $cookies ) {
			$categories = wpconsent()->cookies->get_categories();
			$cookies    = array();

			foreach ( $categories as $category ) {
				$category_id             = $category['id'];
				$cookies[ $category_id ] = array(
					'cookies'  => array(),
					'services' => array(),
				);

				$category_cookies = wpconsent()->cookies->get_cookies_by_category( $category_id );
				$services         = wpconsent()->cookies->get_services_by_category( $category_id );

				if ( ! empty( $category_cookies ) ) {
					foreach ( $category_cookies as $cookie ) {
						// Let's skip here the cookies that are not associated with this category directly and are associated with a service.
						if ( ! in_array( $category_id, $cookie['categories'], true ) ) {
							continue;
						}
						// Add cookie to the category's cookie array.
						$cookies[ $category_id ]['cookies'][] = $cookie;
					}
				}

				// Process services and their cookies.
				if ( ! empty( $services ) ) {
					foreach ( $services as $service ) {
						$service_slug                                         = sanitize_title( $service['name'] );
						$cookies[ $category_id ]['services'][ $service_slug ] = array(
							'name'        => $service['name'],
							'description' => $service['description'],
							'service_url' => $service['service_url'],
							'cookies'     => array(),
						);

						// We already loaded all the cookies for this category so we need to simply add the ones for this service.
						if ( ! empty( $category_cookies ) ) {
							foreach ( $category_cookies as $cookie_for_service ) {
								if ( ! in_array( $service['id'], $cookie_for_service['categories'], true ) ) {
									continue;
								}
								$cookies[ $category_id ]['services'][ $service_slug ]['cookies'][] = $cookie_for_service;
							}
						}
					}
				}
			}
			// Cache for 24 hours.
			set_transient( $cache_key, $cookies, DAY_IN_SECONDS );
		}

		return $cookies;
	}

	/**
	 * Generate the cookies accordioncontent for a category
	 *
	 * @param array  $cookies The cookies array for each category to display.
	 * @param string $category_name The name of the category.
	 * @param string $category_slug The slug of the category.
	 *
	 * @return string
	 */
	private function get_cookies_content_by_category( $cookies, $category_name = '', $category_slug = '' ) {
		if ( empty( $cookies ) ) {
			return '';
		}

		$html = '';

		if ( ! empty( $cookies['cookies'] ) ) {
			$html .= $this->get_cookies_table_by_category( $cookies['cookies'] );
		}

		// First display services and their cookies.
		if ( ! empty( $cookies['services'] ) ) {
			foreach ( $cookies['services'] as $service_slug => $service ) {
				$html .= '<div class="wpconsent-preferences-accordion-item wpconsent-cookie-service">';
				$html .= '<div class="wpconsent-preferences-accordion-header">';
				$html .= '<div class="wpconsent-cookie-category-text">';
				$html .= '<button class="wpconsent-preferences-accordion-toggle">';
				$html .= '<span class="wpconsent-preferences-accordion-arrow"></span>';
				$html .= '</button>';  // .wpconsent-preferences-accordion-toggle
				$html .= '<label>' . esc_html( $service['name'] ) . '</label>';
				$html .= '</div>'; // .wpconsent-cookie-category-text
				$html .= '<div class="wpconsent-cookie-category-checkbox">';

				if ( wpconsent()->settings->get_option( 'manual_toggle_services', false ) ) {
					if ( 'essential' === $category_slug ) {
						$html .= '<label class="wpconsent-preferences-checkbox-toggle wpconsent-preferences-checkbox-toggle-disabled">';
						$html .= '<input type="checkbox" id="cookie-service-' . esc_attr( $service_slug ) . '" name="wpconsent_cookie[]" value="' . esc_attr( $service_slug ) . '" checked disabled>';
						$html .= '<span class="wpconsent-preferences-checkbox-toggle-slider"></span>';
						$html .= '</label>';  // .wpconsent-preferences-checkbox-toggle
					} else {
						$html .= '<label class="wpconsent-preferences-checkbox-toggle">';
						$html .= '<input type="checkbox" id="cookie-service-' . esc_attr( $service_slug ) . '" name="wpconsent_cookie[]" value="' . esc_attr( $service_slug ) . '">';
						$html .= '<span class="wpconsent-preferences-checkbox-toggle-slider"></span>';
						$html .= '</label>';  // .wpconsent-preferences-checkbox-toggle
					}
				}

				$html .= '</div>'; // .wpconsent-cookie-category-checkbox
				$html .= '</div>'; // .wpconsent-preferences-accordion-header

				$html .= '<div class="wpconsent-preferences-accordion-content">';
				if ( ! empty( $service['description'] ) ) {
					$html .= '<p tabindex="0">' . wp_kses_post( $service['description'] ) . '</p>';
				}
				// Add service URL to description if it exists.
				if ( ! empty( $service['service_url'] ) ) {
					$service_url_label = wpconsent()->settings->get_option( 'cookie_table_header_service_url', esc_html__( 'Service URL', 'wpconsent-cookies-banner-privacy-suite' ) );

					$html .= '<p tabindex="0" class="wpconsent-service-url">' . sprintf(
						/* translators: %1$s: Service URL label, %2$s: Service URL */
							esc_html__( '%1$s: %2$s', 'wpconsent-cookies-banner-privacy-suite' ),
							esc_html( $service_url_label ),
							'<a href="' . esc_url( $service['service_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_parse_url( $service['service_url'], PHP_URL_HOST ) ) . '</a>'
						) . '</p>';
				}
				$html .= $this->get_cookies_table_by_category( $service['cookies'] );
				$html .= '</div>'; // .wpconsent-preferences-accordion-content
				$html .= '</div>'; // .wpconsent-cookie-service
			}
		}

		return $html;
	}

	/**
	 * Generate the cookies table for a category
	 *
	 * @param array $cookies The cookies to display.
	 *
	 * @return string
	 */
	private function get_cookies_table_by_category( $cookies ) {
		if ( empty( $cookies ) ) {
			return '';
		}

		$html = '<div class="wpconsent-preferences-cookies-list">';

		$html .= '<div class="wpconsent-preferences-list-header">';
		$html .= '<div class="cookie-name">' . esc_html( wpconsent()->settings->get_option( 'cookie_table_header_name', __( 'Name', 'wpconsent-cookies-banner-privacy-suite' ) ) ) . '</div>';
		$html .= '<div class="cookie-desc">' . esc_html( wpconsent()->settings->get_option( 'cookie_table_header_description', __( 'Description', 'wpconsent-cookies-banner-privacy-suite' ) ) ) . '</div>';
		$html .= '<div class="cookie-duration">' . esc_html( wpconsent()->settings->get_option( 'cookie_table_header_duration', __( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ) ) ) . '</div>';
		$html .= '</div>'; // .wpconsent-preferences-list-header

		foreach ( $cookies as $cookie ) {
			$html .= '<div class="wpconsent-preferences-list-item">';
			$html .= '<div class="cookie-name">' . esc_html( $cookie['name'] ) . '</div>';
			$html .= '<div class="cookie-desc">' . wp_kses_post( $cookie['description'] ) . '</div>';
			$html .= '<div class="cookie-duration">' . esc_html( ! empty( $cookie['duration'] ) ? $cookie['duration'] : '-' ) . '</div>';
			$html .= '</div>'; // .wpconsent-preferences-list-item
		}

		$html .= '</div>'; // .wpconsent-preferences-cookies-list

		return $html;
	}

	/**
	 * Output the powered by WPConsent logo.
	 *
	 * @return string
	 */
	public function powered_by() {
		if ( wpconsent()->settings->get_option( 'hide_powered_by' ) ) {
			return '';
		}
		$url    = wpconsent_utm_url( 'https://wpconsent.com/powered-by/', 'poweredby' );
		$html   = '<div class="wpconsent-powered-by">';
		$colors = $this->get_color_settings();

		$html .= '<a style="color: ' . esc_attr( $colors['text'] ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener noreferrer">';
		$html .= sprintf(
		/* translators: %1$s and %2$s add a tag used for hiding the text on small screens and %3$s is the WPConsent logo svg */
			esc_html__( '%1$sPowered by%2$s %3$s', 'wpconsent-cookies-banner-privacy-suite' ),
			'<span class="wpconsent-powered-by-text">',
			'</span>',
			wpconsent_get_icon( 'logo-mono', 80, 12, '0 0 57 9', $colors['text'] )
		);
		$html .= '</a>';
		$html .= '</div>'; // .wpconsent-powered-by

		return $html;
	}

	/**
	 * Output the floating consent button.
	 *
	 * @return void
	 */
	public function floating_consent_button() {
		if ( is_admin() ) {
			return;
		}
		$colors = $this->get_color_settings();
		$style  = 'background-color: ' . esc_attr( $colors['background'] ) . '; color: ' . esc_attr( $colors['text'] ) . ';';
		echo '<button id="wpconsent-consent-floating" class="wpconsent-consent-floating-button" part="wpconsent-settings-button" style="' . esc_attr( $style ) . '" aria-label="' . esc_attr__( 'Cookie Preferences', 'wpconsent-cookies-banner-privacy-suite' ) . '">';

		$icon_value = wpconsent()->settings->get_option( 'consent_floating_icon', 'preferences' );

		if ( filter_var( $icon_value, FILTER_VALIDATE_URL ) ) {
			echo '<img src="' . esc_url( $icon_value ) . '" alt="' . esc_attr__( 'Cookie Settings', 'wpconsent-cookies-banner-privacy-suite' ) . '">';
		} else {
			echo wp_kses(
				apply_filters(
					'wpconsent_preferences_icon',
					wpconsent_get_icon( $icon_value, 24, 24, '0 -960 960 960', $colors['text'] )
				),
				wpconsent_get_icon_allowed_tags()
			);
		}

		echo '</button>';
	}

	/**
	 * Replace smart tags for the cookie policy text.
	 *
	 * @param string $text The text to replace smart tags in.
	 * @param string $locale The locale to use for the replacement.
	 *
	 * @return string
	 */
	public function maybe_replace_smart_tags( $text, $locale = '' ) {
		$cookie_policy_page_id  = apply_filters( 'wpconsent_get_cookie_policy_id', wpconsent()->settings->get_option( 'cookie_policy_page', 0 ), $locale );
		$privacy_policy_page_id = apply_filters( 'wpconsent_get_privacy_policy_id', get_option( 'wp_page_for_privacy_policy' ), $locale );

		// Get the URLs and titles.
		$cookie_policy_page_url    = $cookie_policy_page_id ? get_permalink( $cookie_policy_page_id ) : '';
		$cookie_policy_page_title  = $cookie_policy_page_id ? get_the_title( $cookie_policy_page_id ) : '';
		$privacy_policy_url        = $privacy_policy_page_id ? get_permalink( $privacy_policy_page_id ) : '';
		$privacy_policy_page_title = $privacy_policy_page_id ? get_the_title( $privacy_policy_page_id ) : '';

		// Replace {cookie_policy} with a link to the cookie policy where the text of the link is the page title.
		if ( $cookie_policy_page_url && $cookie_policy_page_title ) {
			$text = str_replace( '{cookie_policy}', '<a href="' . esc_url( $cookie_policy_page_url ) . '">' . esc_html( $cookie_policy_page_title ) . '</a>', $text );
		} else {
			$text = str_replace( '{cookie_policy}', '', $text );
		}

		// Replace {privacy_policy} with a link to the privacy policy where the text of the link is the page title.
		if ( $privacy_policy_url && $privacy_policy_page_title ) {
			$text = str_replace( '{privacy_policy}', '<a href="' . esc_url( $privacy_policy_url ) . '">' . esc_html( $privacy_policy_page_title ) . '</a>', $text );
		} else {
			// If there is no privacy policy page, remove the {privacy_policy} tag.
			$text = str_replace( '{privacy_policy}', '', $text );
		}

		return $text;
	}
}
