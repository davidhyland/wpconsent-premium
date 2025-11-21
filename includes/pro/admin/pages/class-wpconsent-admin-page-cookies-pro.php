<?php
/**
 * Pro-specific settings admin page.
 *
 * @package WPConsent
 */

/**
 * Pro-specific settings admin page.
 */
class WPConsent_Admin_Page_Cookies_Pro extends WPConsent_Admin_Page_Cookies {

	use WPConsent_License_Field;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		parent::page_hooks();

		add_filter( 'wpconsent_admin_js_data', array( $this, 'add_js_data' ), 15 );

		add_action( 'admin_init', array( $this, 'handle_language_settings_submit' ) );
	}

	/**
	 * Add license-specific body classes.
	 *
	 * @param string $body_class The body class to append.
	 *
	 * @return string
	 */
	public function page_specific_body_class( $body_class ) {
		// Call parent method first.
		$body_class = parent::page_specific_body_class( $body_class );

		// Add license level class.
		$license_type = wpconsent()->license->type();
		if ( ! empty( $license_type ) ) {
			$body_class .= ' wpconsent-license-' . sanitize_html_class( $license_type );
		} else {
			$body_class .= ' wpconsent-license-none';
		}

		// Check if license is active and can use translation (Plus, Pro, Elite).
		$can_translate = wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() );
		if ( ! $can_translate ) {
			$body_class .= ' wpconsent-translation-restricted';
		}

		return $body_class;
	}

	/**
	 * Add license strings to the JS object for the Pro settings page.
	 *
	 * @param string[] $data The translation strings.
	 *
	 * @return string[]
	 */
	public function add_js_data( $data ) {
		$data['license_error_title'] = __( 'We encountered an error activating your license key', 'wpconsent-premium' );
		$data['multisite']           = is_network_admin();
		// Translation is available for Plus, Pro, and Elite licenses.
		$data['license_can'] = wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() );

		// Add translation status to initial page load.
		$data['translation_active'] = false;
		if ( isset( wpconsent()->translation_services ) ) {
			$data['translation_active'] = wpconsent()->translation_services->is_translation_active();
		}

		// Add translation upsell data.
		$data['translation_upsell'] = array(
				'title'       => esc_html__( 'Automatic Translations is not available on your plan', 'wpconsent-premium' ),
				'text'        => esc_html__( 'Instead of manually translating every banner setting, categories, services, cookies info, let us do the heavy lifting. Upgrade to WPConsent Plus or higher to use high-quality, AI-powered automatic translation and have your site ready for a new audience today', 'wpconsent-premium' ),
				'url'         => wpconsent_utm_url( 'https://wpconsent.com/my-account/', 'translation', 'languages' ),
				'button_text' => esc_html__( 'Upgrade Now', 'wpconsent-premium' ),
		);

		$data['discount_note'] = false;

		return $data;
	}

	/**
	 * Handle the form submission.
	 *
	 * @return void
	 */
	public function handle_submit() {
		// Handle IAB TCF vendor selection (AJAX or regular form submission).
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_iab_tcf_vendors' ) {
			$this->handle_iab_tcf_vendor_selection();

			return;
		}
		// Check the nonce for settings view.
		if ( ! isset( $_POST['wpconsent_save_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpconsent_save_settings_nonce'] ), 'wpconsent_save_settings' ) ) {
			return;
		}

		// Only process pro settings when we're in the settings view.
		if ( 'settings' === $this->view ) {
			$settings = array(
					'records_of_consent'    => isset( $_POST['records_of_consent'] ) ? 1 : 0,
					'auto_scanner'          => isset( $_POST['auto_scanner'] ) ? 1 : 0,
					'auto_scanner_interval' => isset( $_POST['auto_scanner_interval'] ) ? intval( $_POST['auto_scanner_interval'] ) : 1,
			);

			if ( 0 === $settings['auto_scanner'] ) {
				wp_clear_scheduled_hook( 'wpconsent_auto_scanner' );
			}

			wpconsent()->settings->bulk_update_options( $settings );
		}

		// Let the parent class save things too.
		parent::handle_submit();
	}


	/**
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function records_of_consent_input() {
		$this->metabox_row(
				esc_html__( 'Consent Logs', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
						wpconsent()->settings->get_option( 'records_of_consent', false ),
						'records_of_consent',
						esc_html__( 'Enable keeping records of consent for all visitors that give consent.', 'wpconsent-premium' )
				),
				'records_of_consent',
				'',
				'',
				''
		);
	}

	/**
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function automatic_scanning_input() {
		$this->metabox_row(
				esc_html__( 'Auto Scanning', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
						wpconsent()->settings->get_option( 'auto_scanner', false ),
						'auto_scanner',
						esc_html__( 'Enable automatic scanning of consent compliance in the background.', 'wpconsent-premium' )
				),
				'auto_scanner'
		);
		$this->metabox_row(
				esc_html__( 'Scan Interval', 'wpconsent-premium' ),
				$this->select(
						'auto_scanner_interval',
						array(
								1  => esc_html__( 'Daily', 'wpconsent-premium' ),
								7  => esc_html__( 'Weekly', 'wpconsent-premium' ),
								30 => esc_html__( 'Monthly', 'wpconsent-premium' ),
						),
						intval( wpconsent()->settings->get_option( 'auto_scanner_interval', 1 ) )
				),
				'auto_scanner_interval',
				'',
				'',
				esc_html__( 'Choose how often to automatically scan your website for compliance.', 'wpconsent-premium' )
		);
	}

	/**
	 * Output an interface where users can configure the languages they want to have in the banner.
	 *
	 * @return void
	 */
	public function output_view_languages() {
		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post">
			<?php
			$this->metabox(
					esc_html__( 'Language Settings', 'wpconsent-premium' ),
					$this->get_language_settings_content()
			);

			wp_nonce_field(
					'wpconsent_save_language_settings',
					'wpconsent_save_language_settings_nonce'
			);
			?>
			<div class="wpconsent-submit">
				<button type="submit" name="save_language_settings" class="wpconsent-button wpconsent-button-primary">
					<?php esc_html_e( 'Save Changes', 'wpconsent-premium' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Get the language settings content.
	 *
	 * @return string
	 */
	public function get_language_settings_content() {
		ob_start();

		// Get currently enabled languages.
		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );

		// Get all available languages.
		$available_languages = wp_get_available_translations();
		if ( ! $available_languages ) {
			$available_languages = array();
		}

		// Get current language from plugin locale.
		// Use the get_plugin_locale method from WPConsent_Multilanguage to get the correct locale.
		$plugin_locale = wpconsent()->multilanguage->get_plugin_locale();

		// Get the WordPress default language from WPLANG option.
		$wp_default_language = get_option( 'WPLANG' );
		if ( empty( $wp_default_language ) ) {
			$wp_default_language = 'en_US';
		}

		// Add English (United States) only if it's in the enabled languages array or if it's the WordPress default language.
		if ( in_array( 'en_US', $enabled_languages, true ) || 'en_US' === $wp_default_language ) {
			$available_languages['en_US'] = array(
					'language'     => 'en_US',
					'english_name' => 'English (United States)',
					'native_name'  => 'English (United States)',
			);
		}

		// If en_US is not already in the available languages, add en_US to the available languages.
		if ( ! isset( $available_languages['en_US'] ) ) {
			$available_languages['en_US'] = array(
					'language'     => 'en_US',
					'english_name' => 'English (United States)',
					'native_name'  => 'English (United States)',
			);
		}

		// Ensure the plugin locale is always enabled.
		if ( ! in_array( $plugin_locale, $enabled_languages, true ) ) {
			$enabled_languages[] = $plugin_locale;
		}

		// Always add the WordPress default language if it's not already in the enabled languages array.
		if ( ! in_array( $wp_default_language, $enabled_languages, true ) ) {
			$enabled_languages[] = $wp_default_language;
		}

		// Sort languages into selected and unselected.
		$selected_languages   = array();
		$unselected_languages = array();

		foreach ( $available_languages as $locale => $language ) {
			if ( in_array( $locale, $enabled_languages, true ) ) {
				$selected_languages[ $locale ] = $language;
			} else {
				$unselected_languages[ $locale ] = $language;
			}
		}

		// Sort both arrays alphabetically by English name.
		uasort( $selected_languages, function ( $a, $b ) {
			return strcmp( $a['english_name'], $b['english_name'] );
		} );
		uasort( $unselected_languages, function ( $a, $b ) {
			return strcmp( $a['english_name'], $b['english_name'] );
		} );
		?>
		<div class="wpconsent-language-settings">
			<div class="wpconsent-input-area-description">
				<p>
					<?php
					printf(
					// Translators: %s is the current WordPress language name.
							esc_html__( 'Select the languages you want to make available for your content. The default language (%s) will be used for the current settings until you configure translations.', 'wpconsent-premium' ),
							esc_html( isset( $available_languages[ $wp_default_language ]['english_name'] ) ? $available_languages[ $wp_default_language ]['english_name'] : 'English (United States)' )
					);
					?>
				</p>
				<p>
					<?php
					printf(
					// Translators: %s is the icon for the language switcher.
							esc_html__(
									'Easily switch between languages using the globe icon (%s) in the header of any WPConsent admin page.',
									'wpconsent-premium'
							),
							wp_kses(
									wpconsent_get_icon( 'globe', 16, 16, '0 -960 960 960' ),
									wpconsent_get_icon_allowed_tags()
							)
					);
					?>
				</p>
				<p>
					<?php
					esc_html_e( 'The "Translate" button appears for languages that are supported by our translation service. Click the button to start the automatic translation process for your consent banner content. Translation happens asynchronously in the background, and you will be notified when the process is complete.', 'wpconsent-premium' );
					?>
				</p>
			</div>
			<div class="wpconsent-language-selector">
				<div class="wpconsent-language-search">
					<input type="text"
					       class="wpconsent-input-text"
					       id="wpconsent-language-search"
					       placeholder="<?php esc_attr_e( 'Search languages...', 'wpconsent-premium' ); ?>"
					>
				</div>
				<div class="wpconsent-language-setting-list" id="wpconsent-language-list">
					<?php
					// Output selected languages first.
					if ( ! empty( $selected_languages ) ) :
						?>
						<div class="wpconsent-language-section">
							<div class="wpconsent-language-section-title">
								<?php esc_html_e( 'Selected Languages', 'wpconsent-premium' ); ?>
							</div>
							<?php
							foreach ( $selected_languages as $locale => $language ) :
								$is_default = $locale === $wp_default_language;
								$this->output_language_item( $locale, $language, $is_default, true, $wp_default_language );
							endforeach;
							?>
						</div>
					<?php
					endif;

					// Output unselected languages.
					if ( ! empty( $unselected_languages ) ) :
						?>
						<div class="wpconsent-language-section">
							<div class="wpconsent-language-section-title">
								<?php esc_html_e( 'Available Languages', 'wpconsent-premium' ); ?>
							</div>
							<?php
							foreach ( $unselected_languages as $locale => $language ) :
								$is_default = $locale === $wp_default_language;
								$this->output_language_item( $locale, $language, $is_default, false, $wp_default_language );
							endforeach;
							?>
						</div>
					<?php
					endif;
					?>
				</div>
			</div>
		</div>
		<?php
		$this->metabox_row(
				esc_html__( 'Language Picker', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
						wpconsent()->settings->get_option( 'show_language_picker', 0 ),
						'show_language_picker',
						esc_html__( 'Show a language picker in the consent banner', 'wpconsent-premium' )
				),
				'show_language_picker',
				'',
				'',
				esc_html__( 'This will show a globe icon in the header of the consent banner, allowing users to switch between languages just for the banner/preferences panel even if you do not use a translation plugin. If you are using a translation plugin the banner should automatically display the content in the selected language, if available.', 'wpconsent-premium' )
		);

		return ob_get_clean();
	}

	/**
	 * Override the output_language_item method to add translate buttons for enabled languages.
	 *
	 * @param string $locale The language locale.
	 * @param array  $language The language data.
	 * @param bool   $is_default Whether this is the default language.
	 * @param bool   $is_enabled Whether this language is enabled.
	 * @param string $wp_default_language The WordPress default language.
	 *
	 * @return void
	 */
	protected function output_language_item( $locale, $language, $is_default, $is_enabled, $wp_default_language = 'en_US' ) {
		$classes = array( 'wpconsent-language-item' );
		if ( $is_default ) {
			$classes[] = 'wpconsent-language-default';
		}
		if ( $is_enabled ) {
			$classes[] = 'wpconsent-language-enabled';
		}

		// Check if this language is supported by the translation service.
		$translation_supported = false;
		if ( isset( wpconsent()->translation_services ) ) {
			$translation_supported = wpconsent()->translation_services->is_language_supported( $locale );
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-locale="<?php echo esc_attr( $locale ); ?>" data-search="<?php echo esc_attr( strtolower( $language['english_name'] . ' ' . $language['native_name'] . ' ' . $locale ) ); ?>">
			<label class="wpconsent-checkbox-label">
				<input type="checkbox" name="enabled_languages[]" value="<?php echo esc_attr( $locale ); ?>"
						<?php checked( $is_enabled ); ?>
						<?php disabled( $is_default ); ?>>
				<span class="wpconsent-checkbox-text">
					<?php echo esc_html( $language['english_name'] ); ?>
					<span class="wpconsent-language-locale">(<?php echo esc_html( $locale ); ?>)</span>
					<?php if ( $language['native_name'] !== $language['english_name'] ) : ?>
						<span class="wpconsent-language-native-name">
							(<?php echo esc_html( $language['native_name'] ); ?>)
						</span>
					<?php endif; ?>
					<?php if ( $is_default ) : ?>
						<span class="wpconsent-language-default-badge">
							<?php esc_html_e( 'Default', 'wpconsent-premium' ); ?>
						</span>
					<?php endif; ?>
				</span>
			</label>
			<?php if ( $is_enabled ) : ?>
				<div class="wpconsent-language-actions">
					<button type="button"
					        class="wpconsent-button wpconsent-button-secondary wpconsent-review-banner-content"
					        data-locale="<?php echo esc_attr( $locale ); ?>"
					        data-redirect-url="<?php echo esc_url( admin_url( 'admin.php?page=wpconsent-banner&view=content' ) ); ?>"
					        data-target="_blank">
						<?php esc_html_e( 'Review Banner Content', 'wpconsent-premium' ); ?>
					</button>
					<button type="button"
					        class="wpconsent-button wpconsent-button-secondary wpconsent-review-cookie-content"
					        data-locale="<?php echo esc_attr( $locale ); ?>"
					        data-redirect-url="<?php echo esc_url( admin_url( 'admin.php?page=wpconsent-cookies&view=cookies' ) ); ?>"
					        data-target="_blank">
						<?php esc_html_e( 'Review Cookie Content', 'wpconsent-premium' ); ?>
					</button>
					<?php
					$is_english_variant           = str_starts_with( $locale, 'en_' );
					$is_default_language_english  = str_starts_with( $wp_default_language, 'en_' );
					$should_show_translate_button = $translation_supported && ( ! $is_english_variant || ( $is_english_variant && ! $is_default_language_english ) );
					if ( $should_show_translate_button ) :
						?>
						<button type="button"
						        class="wpconsent-button wpconsent-button-primary wpconsent-translate-language"
						        data-locale="<?php echo esc_attr( $locale ); ?>">
							<?php esc_html_e( 'Auto-Translate', 'wpconsent-premium' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle language settings submission.
	 *
	 * @return void
	 */
	public function handle_language_settings_submit() {
		// Check the nonce for language settings.
		if ( ! isset( $_POST['wpconsent_save_language_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpconsent_save_language_settings_nonce'] ), 'wpconsent_save_language_settings' ) ) {
			return;
		}

		// Get enabled languages from POST data.
		$enabled_languages    = isset( $_POST['enabled_languages'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_languages'] ) ) : array();
		$show_language_picker = isset( $_POST['show_language_picker'] ) ? 1 : 0;

		// Save enabled languages.
		wpconsent()->settings->bulk_update_options(
				array(
						'enabled_languages'    => $enabled_languages,
						'show_language_picker' => $show_language_picker,
				)
		);

		wp_safe_redirect( $this->get_page_action_url() );
		exit;
	}

	/**
	 * Get the service library button HTML.
	 *
	 * @param array $category The category data.
	 *
	 * @return string The button HTML.
	 */
	public function get_service_library_button( $category ) {
		ob_start();
		?>
		<button class="wpconsent-button wpconsent-button-secondary wpconsent-add-service-from-library wpconsent-button-icon" type="button" data-category-id="<?php echo esc_attr( $category['id'] ); ?>" data-category-name="<?php echo esc_attr( $category['name'] ); ?>">
			<?php wpconsent_icon( 'library', 14, 14, '0 -960 960 960' ); ?>
			<?php esc_html_e( 'Add Service From Library', 'wpconsent-premium' ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output the footer for the cookies view.
	 *
	 * @return void
	 */
	public function output_footer_cookies() {
		parent::output_footer_cookies();

		?>
		<div class="wpconsent-modal" id="wpconsent-modal-add-service-from-library">
			<div class="wpconsent-modal-inner">
				<div class="wpconsent-modal-header">
					<h2><?php echo esc_html__( 'Add Service From Library', 'wpconsent-premium' ); ?></h2>
					<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="wpconsent-modal-content">
					<div class="wpconsent-service-library-search">
						<input type="text"
						       class="wpconsent-input-text"
						       id="wpconsent-service-library-search"
						       placeholder="<?php esc_attr_e( 'Search services...', 'wpconsent-premium' ); ?>"
						>
					</div>
					<div class="wpconsent-service-library-list">
						<div class="wpconsent-service-library-loading">
							<?php esc_html_e( 'Loading services...', 'wpconsent-premium' ); ?>
						</div>
						<div class="wpconsent-service-library-items">
							<!-- Services will be loaded here via JavaScript -->
						</div>
					</div>
					<div class="wpconsent-modal-buttons">
						<button class="wpconsent-button wpconsent-button-secondary" type="button">
							<?php echo esc_html__( 'Cancel', 'wpconsent-premium' ); ?>
						</button>
					</div>
				</div>
				<input type="hidden" name="action" value="wpconsent_add_service_from_library">
				<input type="hidden" name="category_id" value="">
				<?php wp_nonce_field( 'wpconsent_add_service_from_library', 'wpconsent_add_service_from_library_nonce' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the footer for the advanced view.
	 *
	 * @return void
	 */
	public function output_footer_advanced() {
		?>
		<div class="wpconsent-modal" id="wpconsent-modal-add-script">
			<div class="wpconsent-modal-inner">
				<form action="" id="wpconsent-modal-form">
					<div class="wpconsent-modal-header">
						<h2><?php echo esc_html__( 'Add New Script or iFrame', 'wpconsent-premium' ); ?></h2>
						<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="wpconsent-modal-content">
						<div class="wpconsent-input-area-description">
							<?php
							printf(
							// Translators: %1$s is a link to the documentation, %2$s is the closing tag for the link.
									esc_html__(
											'For instructions on how to add custom scripts, please refer to our %1$sdocumentation%2$s.',
											'wpconsent-premium'
									),
									'<a href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs/how-to-block-custom-scripts-and-iframes/', 'settings', 'custom-scripts' ) ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
							);
							?>
						</div>
						<?php
						// Category dropdown: statistics, marketing.
						$categories        = wpconsent()->cookies->get_categories();
						$script_categories = array();
						if ( isset( $categories['statistics'] ) ) {
							$script_categories[ $categories['statistics']['id'] ] = esc_html( $categories['statistics']['name'] );
						}
						if ( isset( $categories['marketing'] ) ) {
							$script_categories[ $categories['marketing']['id'] ] = esc_html( $categories['marketing']['name'] );
						}
						$this->metabox_row(
								esc_html__( 'Category', 'wpconsent-premium' ),
								$this->select( 'script_category', $script_categories )
						);

						// Service dropdown: will be dynamically populated in JS based on selected category.
						$this->metabox_row(
								esc_html__( 'Service', 'wpconsent-premium' ),
								$this->select( 'script_service', $this->get_services_options() )
						);

						// Script or iFrame radio buttons.
						$this->metabox_row(
								esc_html__( 'Type', 'wpconsent-premium' ),
								'<label><input class="wpconsent-input-radio" type="radio" name="script_type" value="script" checked> ' . esc_html__( 'Script', 'wpconsent-premium' ) . '</label> '
								. '<label style="margin-left: 1em;"><input class="wpconsent-input-radio" type="radio" name="script_type" value="iframe"> ' . esc_html__( 'iFrame', 'wpconsent-premium' ) . '</label>',
								'script_type'
						);

						// Script-specific fields (shown when script type is selected)
						$this->metabox_row(
								esc_html__( 'Script Tag', 'wpconsent-premium' ),
								$this->get_input_textarea(
										'script_tag',
										'',
										esc_html__( 'Enter a unique string that identifies the script to block. Example: "connect.facebook.net/en_US/fbevents.js"', 'wpconsent-premium' )
								),
								'script_tag',
								'[name="script_type"]',
								'script'
						);

						$this->metabox_row(
								esc_html__( 'Script Keywords', 'wpconsent-premium' ),
								$this->get_input_text(
										'script_keywords',
										'',
										esc_html__( 'JavaScript function names to block that depend on the main script (comma separated). Example: "fbq, fbq.push"', 'wpconsent-premium' )
								),
								'script_keywords',
								'[name="script_type"]',
								'script'
						);

						// iFrame-specific fields (shown when iframe type is selected)
						$this->metabox_row(
								esc_html__( 'iFrame Tag', 'wpconsent-premium' ),
								$this->get_input_textarea(
										'iframe_tag',
										'',
										esc_html__( 'Enter a unique string that identifies the iframe to block. Example: "youtube.com/embed"', 'wpconsent-premium' )
								),
								'script_tag',
								'[name="script_type"]',
								'iframe'
						);

						$this->metabox_row(
								esc_html__( 'Blocked Elements', 'wpconsent-premium' ),
								$this->get_input_text(
										'iframe_blocked_elements',
										'',
										esc_html__( 'CSS selectors for elements to block and add a placeholder for until consent is given (comma separated). Example: "#my-chat-widget, #my-chat-widget-2"', 'wpconsent-premium' )
								),
								'iframe_blocked_elements',
								'[name="script_type"]',
								'iframe'
						);
						?>
						<div class="wpconsent-modal-buttons">
							<button class="wpconsent-button wpconsent-button-primary" type="submit">
								<?php echo esc_html__( 'Save', 'wpconsent-premium' ); ?>
							</button>
							<button class="wpconsent-button wpconsent-button-secondary" type="button">
								<?php echo esc_html__( 'Cancel', 'wpconsent-premium' ); ?>
							</button>
						</div>
					</div>
					<input type="hidden" name="action" value="wpconsent_manage_script">
					<input type="hidden" name="script_id" value="">
					<?php wp_nonce_field( 'wpconsent_manage_script', 'wpconsent_manage_script_nonce' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the advanced settings view.
	 *
	 * @return void
	 */
	public function output_view_advanced() {
		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post">
			<?php
			wp_nonce_field( 'wpconsent_save_settings', 'wpconsent_save_settings_nonce' );

			$this->metabox(
					__( 'Custom Iframes/Scripts', 'wpconsent-premium' ),
					$this->get_custom_scripts_content()
			);

			$this->metabox(
					__( 'Advanced Settings', 'wpconsent-premium' ),
					$this->get_advanced_settings_content()
			);

			?>
			<div class="wpconsent-submit">
				<button type="submit" name="save_changes" class="wpconsent-button wpconsent-button-primary">
					<?php esc_html_e( 'Save Changes', 'wpconsent-premium' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Get the content for the usage tracking input.
	 *
	 * @return void
	 */
	public function usage_tracking_input() {
		// Do nothing.
	}

	/**
	 * Get the content for the custom scripts meta box.
	 *
	 * @return string
	 */
	public function get_custom_scripts_content() {
		ob_start();
		?>
		<div class="wpconsent-input-area-description">
			<p><?php esc_html_e( 'Add custom iframes or scripts that should be blocked until consent is given.', 'wpconsent-premium' ); ?>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs', 'advanced', 'learn-more' ) ); ?>">
					<?php esc_html_e( 'Learn more', 'wpconsent-premium' ); ?>
				</a>
			</p>
		</div>

		<div class="wpconsent-custom-scripts-manager wpconsent-cookies-manager wpconsent-accordion">
			<?php
			// Get existing scripts.
			$custom_scripts = get_option( 'wpconsent_custom_scripts', array() );

			// Fetch categories from the database.
			$all_categories = wpconsent()->cookies->get_categories();
			$categories     = array();
			if ( isset( $all_categories['statistics'] ) ) {
				$categories[ $all_categories['statistics']['id'] ] = array(
						'name'        => esc_html( $all_categories['statistics']['name'] ) . ' ' . esc_html__( 'Scripts', 'wpconsent-premium' ),
						'description' => esc_html__( 'Add scripts for analytics and statistics tracking.', 'wpconsent-premium' ),
				);
			}
			if ( isset( $all_categories['marketing'] ) ) {
				$categories[ $all_categories['marketing']['id'] ] = array(
						'name'        => esc_html( $all_categories['marketing']['name'] ) . ' ' . esc_html__( 'Scripts', 'wpconsent-premium' ),
						'description' => esc_html__( 'Add scripts for marketing and advertising purposes.', 'wpconsent-premium' ),
				);
			}

			foreach ( $categories as $category_id => $category ) {
				?>
				<div class="wpconsent-accordion-item" data-category="<?php echo esc_attr( $category_id ); ?>">
					<div class="wpconsent-accordion-header">
						<h3><?php echo esc_html( $category['name'] ); ?></h3>
						<button class="wpconsent-accordion-toggle">
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
					</div>
					<div class="wpconsent-accordion-content">
						<div class="wpconsent-cookie-category-description">
							<?php echo esc_html( $category['description'] ); ?>
						</div>
						<div class="wpconsent-cookies-list">
							<div class="wpconsent-cookie-header">
								<div class="script-service"><?php esc_html_e( 'Service', 'wpconsent-premium' ); ?></div>
								<div class="script-type"><?php esc_html_e( 'Type', 'wpconsent-premium' ); ?></div>
								<div class="script-script"><?php esc_html_e( 'Script', 'wpconsent-premium' ); ?></div>
								<div class="script-blocked-elements"><?php esc_html_e( 'Blocked Elements', 'wpconsent-premium' ); ?></div>
								<div class="script-actions"><?php esc_html_e( 'Actions', 'wpconsent-premium' ); ?></div>
							</div>
							<?php
							// Group scripts by category.
							$category_scripts = array();
							foreach ( $custom_scripts as $script_id => $script_data ) {
								if ( $script_data['category'] == $category_id ) {
									$service_data                = wpconsent()->cookies->get_service_by_id( $script_data['service'] );
									$script_data['service_id']   = $script_data['service'];
									$script_data['service_name'] = $service_data ? $service_data['name'] : $script_data['service'];
									if ( isset( $script_data['blocked_elements'] ) && is_array( $script_data['blocked_elements'] ) ) {
										$script_data['blocked_elements'] = wpconsent()->cookies->blocked_elements_to_string( $script_data['blocked_elements'] );
									}
									$category_scripts[] = array_merge( array( 'id' => $script_id ), $script_data );
								}
							}

							usort(
									$category_scripts,
									function ( $a, $b ) {
										return strcmp( $a['service_name'], $b['service_name'] );
									}
							);

							foreach ( $category_scripts as $script ) {
								?>
								<div class="wpconsent-cookie-item"
								     data-id="<?php echo esc_attr( $script['id'] ); ?>"
								     data-category="<?php echo esc_attr( $script['category'] ); ?>"
								     data-service="<?php echo esc_attr( $script['service_id'] ); ?>"
								     data-type="<?php echo esc_attr( $script['type'] ); ?>"
								     data-script="<?php echo esc_attr( $script['tag'] ); ?>"
								     data-blocked-elements="<?php echo esc_attr( $script['blocked_elements'] ); ?>">
									<div class="script-service" data-service-id="<?php echo esc_attr( $script['service_id'] ); ?>"><?php echo esc_html( $script['service_name'] ); ?></div>
									<div class="script-type"><?php echo esc_html( 'iframe' === $script['type'] ? 'iFrame' : 'Script' ); ?></div>
									<div class="script-script"><?php echo esc_html( $script['tag'] ); ?></div>
									<div class="script-blocked-elements"><?php echo esc_html( $script['blocked_elements'] ); ?></div>
									<div class="cookie-actions">
										<button class="wpconsent-button-icon wpconsent-edit-script" type="button" data-script-id="<?php echo esc_attr( $script['id'] ); ?>">
											<?php wpconsent_icon( 'edit', 15, 16 ); ?>
										</button>
										<button class="wpconsent-button-icon wpconsent-delete-script" type="button" data-script-id="<?php echo esc_attr( $script['id'] ); ?>">
											<?php wpconsent_icon( 'delete', 14, 16 ); ?>
										</button>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
				</div>
				<?php
			}
			?>
		</div>

		<div class="wpconsent-metabox-form-row">
			<button class="wpconsent-button wpconsent-button-primary wpconsent-add-script wpconsent-button-icon" type="button">
				<?php esc_html_e( 'Add Custom iFrame/Script', 'wpconsent-premium' ); ?>
			</button>
		</div>

		<!-- Template for new script row -->
		<script type="text/template" id="wpconsent-new-script-row">
			<div class="wpconsent-cookie-item"
			     data-id="{{id}}"
			     data-category="{{category}}"
			     data-service="{{service}}"
			     data-type="{{type}}"
			     data-script="{{tag}}"
			     data-blocked-elements="{{blocked_elements}}">
				<div class="script-service" data-service-id="{{service}}">{{service_name}}</div>
				<div class="script-type">{{type_label}}</div>
				<div class="script-script">{{tag}}</div>
				<div class="script-blocked-elements">{{blocked_elements}}</div>
				<div class="cookie-actions">
					<button class="wpconsent-button-icon wpconsent-edit-script" type="button" data-script-id="{{id}}">
						<?php wpconsent_icon( 'edit', 15, 16 ); ?>
					</button>
					<button class="wpconsent-button-icon wpconsent-delete-script" type="button" data-script-id="{{id}}">
						<?php wpconsent_icon( 'delete', 14, 16 ); ?>
					</button>
				</div>
			</div>
		</script>

		<?php
		return ob_get_clean();
	}

	/**
	 * Process publisher restrictions from form data.
	 *
	 * @return array The processed publisher restrictions.
	 */
	private function process_publisher_restrictions() {
		$restrictions = array(
				'global'  => array(),
				'vendors' => array(),
		);

		// Process global restrictions.
		if ( isset( $_POST['global_disallow_li'] ) ) {
			$global_disallow_li = sanitize_text_field( wp_unslash( $_POST['global_disallow_li'] ) );

			if ( 'disallow_all' === $global_disallow_li ) {
				$restrictions['global']['disallow_li_purposes'] = array( 'all' );
			} elseif ( 'disallow_specific' === $global_disallow_li && isset( $_POST['global_disallow_li_purposes'] ) && is_array( $_POST['global_disallow_li_purposes'] ) ) {
				$disallow_li_purposes = array();
				foreach ( $_POST['global_disallow_li_purposes'] as $purpose_id ) {
					$purpose_id = intval( $purpose_id );
					if ( $purpose_id > 0 ) {
						$disallow_li_purposes[] = $purpose_id;
					}
				}
				$restrictions['global']['disallow_li_purposes'] = $disallow_li_purposes;
			}
		}

		// Process per-vendor restrictions.
		if ( isset( $_POST['vendor_restrictions'] ) && is_array( $_POST['vendor_restrictions'] ) ) {
			foreach ( $_POST['vendor_restrictions'] as $vendor_id => $vendor_data ) {
				$vendor_id = intval( $vendor_id );
				if ( $vendor_id <= 0 ) {
					continue;
				}

				$vendor_restrictions = array();

				// Process disallowed purposes (consent purposes).
				if ( isset( $vendor_data['disallowed_purposes'] ) && is_array( $vendor_data['disallowed_purposes'] ) ) {
					$disallowed_purposes = array();
					foreach ( $vendor_data['disallowed_purposes'] as $purpose_id ) {
						$purpose_id = intval( $purpose_id );
						if ( $purpose_id > 0 ) {
							$disallowed_purposes[] = $purpose_id;
						}
					}
					if ( ! empty( $disallowed_purposes ) ) {
						$vendor_restrictions['disallowed_purposes'] = $disallowed_purposes;
					}
				}

				// Process legitimate interest purposes.
				if ( isset( $vendor_data['li_purposes'] ) && is_array( $vendor_data['li_purposes'] ) ) {
					$require_consent_for_li = array();
					$disallowed_li_purposes = array();

					foreach ( $vendor_data['li_purposes'] as $purpose_id => $action ) {
						$purpose_id = intval( $purpose_id );
						$action     = sanitize_text_field( wp_unslash( $action ) );

						if ( $purpose_id <= 0 ) {
							continue;
						}

						if ( 'require_consent' === $action ) {
							$require_consent_for_li[] = $purpose_id;
						} elseif ( 'disallow' === $action ) {
							$disallowed_li_purposes[] = $purpose_id;
						}
					}

					if ( ! empty( $require_consent_for_li ) ) {
						$vendor_restrictions['require_consent_for_li'] = $require_consent_for_li;
					}

					// Merge disallowed LI purposes with general disallowed purposes.
					if ( ! empty( $disallowed_li_purposes ) ) {
						if ( isset( $vendor_restrictions['disallowed_purposes'] ) ) {
							$vendor_restrictions['disallowed_purposes'] = array_unique( array_merge(
									$vendor_restrictions['disallowed_purposes'],
									$disallowed_li_purposes
							) );
						} else {
							$vendor_restrictions['disallowed_purposes'] = $disallowed_li_purposes;
						}
					}
				}

				// Process disallowed special purposes.
				if ( isset( $vendor_data['disallowed_special_purposes'] ) && is_array( $vendor_data['disallowed_special_purposes'] ) ) {
					$disallowed_special_purposes = array();
					foreach ( $vendor_data['disallowed_special_purposes'] as $special_purpose_id ) {
						$special_purpose_id = intval( $special_purpose_id );
						if ( $special_purpose_id > 0 ) {
							$disallowed_special_purposes[] = $special_purpose_id;
						}
					}
					if ( ! empty( $disallowed_special_purposes ) ) {
						$vendor_restrictions['disallowed_special_purposes'] = $disallowed_special_purposes;
					}
				}

				// Only add vendor restrictions if there are any.
				if ( ! empty( $vendor_restrictions ) ) {
					$restrictions['vendors'][ $vendor_id ] = $vendor_restrictions;
				}
			}
		}

		return $restrictions;
	}

	/**
	 * Process publisher declarations from form submission.
	 *
	 * Processes the publisher's own TCF purpose and feature declarations.
	 *
	 * @return array Structured publisher declarations data.
	 */
	private function process_publisher_declarations() {
		$declarations = array(
				'purposes_consent'         => array(),
				'purposes_li_transparency' => array(),
		);

		// Process publisher purposes (consent).
		if ( isset( $_POST['publisher_purposes_consent'] ) && is_array( $_POST['publisher_purposes_consent'] ) ) {
			foreach ( $_POST['publisher_purposes_consent'] as $purpose_id ) {
				$purpose_id = intval( $purpose_id );
				if ( $purpose_id > 0 ) {
					$declarations['purposes_consent'][] = $purpose_id;
				}
			}
		}

		// Process publisher purposes (legitimate interest).
		if ( isset( $_POST['publisher_purposes_li'] ) && is_array( $_POST['publisher_purposes_li'] ) ) {
			$li_allowed_purposes = array( 2, 7, 9, 10 ); // Only these purposes allow LI per TCF policy.

			foreach ( $_POST['publisher_purposes_li'] as $purpose_id ) {
				$purpose_id = intval( $purpose_id );
				// Validate that purpose is in the allowed list.
				if ( $purpose_id > 0 && in_array( $purpose_id, $li_allowed_purposes, true ) ) {
					$declarations['purposes_li_transparency'][] = $purpose_id;
				}
			}
		}

		return $declarations;
	}

	/**
	 * Handle IAB TCF vendor selection form submission.
	 *
	 * @return void
	 */
	private function handle_iab_tcf_vendor_selection() {
		// Verify nonce for security.
		if ( ! isset( $_POST['iab_tcf_vendors_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['iab_tcf_vendors_nonce'] ), 'save_iab_tcf_vendors' ) ) {
			if ( wp_doing_ajax() ) {
				wp_die( 'Security check failed', 'Error', array( 'response' => 403 ) );
			}
			wp_die( esc_html__( 'Security check failed.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpconsent-cookies-banner-privacy-suite' ) );
		}

		// Get and sanitize selected vendors.
		$selected_vendors = array();
		if ( isset( $_POST['selected_vendors'] ) && is_array( $_POST['selected_vendors'] ) ) {
			foreach ( $_POST['selected_vendors'] as $vendor_id ) {
				$vendor_id = intval( $vendor_id );
				if ( $vendor_id > 0 ) {
					$selected_vendors[] = $vendor_id;
				}
			}
		}

		// Validate vendors exist in the IAB TCF list.
		if ( class_exists( 'WPConsent_IAB_TCF_Vendors' ) ) {
			$vendors_instance = WPConsent_IAB_TCF_Vendors::get_instance();
			if ( $vendors_instance->is_available() ) {
				$all_vendors      = $vendors_instance->get_vendors();
				$selected_vendors = array_filter( $selected_vendors, function ( $vendor_id ) use ( $all_vendors ) {
					return isset( $all_vendors[ $vendor_id ] );
				} );
			}
		}

		// Save selected vendors to options.
		wpconsent()->settings->update_option( 'iab_tcf_selected_vendors', $selected_vendors );

		// Save TCF frontend enabled setting.
		$tcf_frontend_enabled = isset( $_POST['iab_tcf_frontend_enabled'] );
		wpconsent()->settings->update_option( 'iab_tcf_frontend_enabled', $tcf_frontend_enabled );

		// Process and save publisher restrictions.
		$publisher_restrictions = $this->process_publisher_restrictions();
		wpconsent()->settings->update_option( 'iab_tcf_publisher_restrictions', $publisher_restrictions );

		// Process and save publisher declarations.
		$publisher_declarations = $this->process_publisher_declarations();
		wpconsent()->settings->update_option( 'iab_tcf_publisher_declarations', $publisher_declarations );

		// Clear preference slugs transient when IAB TCF setting is toggled.
		delete_transient( 'wpconsent_preference_slugs' );

		// Handle AJAX response.
		if ( wp_doing_ajax() ) {
			wp_send_json_success( array(
					'message'        => esc_html__( 'IAB TCF settings saved successfully.', 'wpconsent-cookies-banner-privacy-suite' ),
					'selected_count' => count( $selected_vendors ),
			) );
		}

		// For regular form submission, redirect back.
		$this->set_success_message( esc_html__( 'IAB TCF settings have been saved.', 'wpconsent-cookies-banner-privacy-suite' ) );
		wp_safe_redirect( $this->get_page_action_url() . '&view=iabtcf' );
		exit;
	}

	/**
	 * Output the IAB TCF view (Pro version with real functionality).
	 *
	 * @return void
	 */
	public function output_view_iabtcf() {
		// Check if IAB TCF vendors class is available.
		if ( ! class_exists( 'WPConsent_IAB_TCF_Vendors' ) ) {
			echo '<div class="wpconsent-notice wpconsent-notice-error">';
			echo '<p>' . esc_html__( 'IAB TCF Vendors functionality is not available. Please ensure the Pro version is active.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>';
			echo '</div>';

			return;
		}

		$vendors_instance = WPConsent_IAB_TCF_Vendors::get_instance();

		if ( ! $vendors_instance->is_available() ) {
			echo '<div class="wpconsent-notice wpconsent-notice-error">';
			echo '<p>' . esc_html__( 'IAB TCF Vendors data is not available. Please check your internet connection and try refreshing the data.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>';
			echo '<button class="wpconsent-button wpconsent-button-primary" onclick="location.reload();">' . esc_html__( 'Refresh Data', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
			echo '</div>';

			return;
		}

		$vendors          = $vendors_instance->get_vendors();
		$purposes         = $vendors_instance->get_purposes();
		$special_purposes = $vendors_instance->get_special_purposes();
		$features         = $vendors_instance->get_features();
		$special_features = $vendors_instance->get_special_features();

		// Get selected vendors from options.
		$selected_vendors       = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		$vendors_initialized    = wpconsent()->settings->get_option( 'iab_tcf_vendors_initialized', false );
		$tcf_frontend_enabled   = wpconsent()->settings->get_option( 'iab_tcf_frontend_enabled', false );
		$publisher_restrictions = wpconsent()->settings->get_option( 'iab_tcf_publisher_restrictions', array() );

		// If no vendors have been selected yet and this is the first time, select all vendors by default.
		if ( empty( $selected_vendors ) && ! $vendors_initialized ) {
			$selected_vendors = array_keys( $vendors );
			wpconsent()->settings->update_option( 'iab_tcf_selected_vendors', $selected_vendors );
			wpconsent()->settings->update_option( 'iab_tcf_vendors_initialized', true );
		}

		// Sort vendors by name (default sorting for initial load).
		$sorted_vendors = $this->sort_vendors( $vendors, 'name_asc' );

		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post">
			<?php wp_nonce_field( 'save_iab_tcf_vendors', 'iab_tcf_vendors_nonce' ); ?>
			<input type="hidden" name="action" value="save_iab_tcf_vendors">

			<?php
			// TCF Activation metabox.
			ob_start();
			$toggle_html = '<label class="wpconsent-toggle">';
			$toggle_html .= $this->get_checkbox_toggle( $tcf_frontend_enabled, 'iab_tcf_frontend_enabled' );
			$toggle_html .= '<span class="wpconsent-toggle-slider"></span>';
			$toggle_html .= '</label>';

			$this->metabox_row(
					__( 'Enable TCF', 'wpconsent-cookies-banner-privacy-suite' ),
					$toggle_html,
					'iab_tcf_frontend_enabled',
					'',
					'',
					__( 'Enable this setting to load the IAB TCF (Transparency and Consent Framework) on the frontend of your website.', 'wpconsent-cookies-banner-privacy-suite' )
			);
			?>
			<div class="wpconsent-input-area-description" style="margin-top: 10px;">
				<p><strong><?php esc_html_e( 'Please Note:', 'wpconsent-cookies-banner-privacy-suite' ); ?></strong> <?php esc_html_e( 'Enabling this setting will force certain banner settings, styles, and behavior to comply with IAB TCF v2.2 standards to ensure proper consent management.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
			</div>
			<?php
			$frontend_content = ob_get_clean();

			$this->metabox(
					__( 'TCF Activation', 'wpconsent-cookies-banner-privacy-suite' ),
					$frontend_content
			);
			?>

			<?php $this->output_global_vendor_restrictions( $purposes, $publisher_restrictions ); ?>

			<?php $this->output_publisher_declarations( $purposes ); ?>

			<div class="wpconsent-iab-tcf-vendors" data-per-page="50">
				<?php $this->output_vendor_controls( count( $sorted_vendors ) ); ?>
				<?php $this->output_vendor_list( $sorted_vendors, $selected_vendors, $purposes, $special_purposes, $publisher_restrictions ); ?>
				<?php $this->output_vendor_pagination(); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Output the vendor list.
	 *
	 * @param array $vendors The vendors to display.
	 * @param array $selected_vendors The selected vendors.
	 * @param array $purposes The purposes data.
	 * @param array $special_purposes The special purposes data.
	 * @param array $publisher_restrictions The publisher restrictions data.
	 *
	 * @return void
	 */
	protected function output_vendor_list( $vendors, $selected_vendors, $purposes, $special_purposes, $publisher_restrictions ) {
		?>
		<div class="wpconsent-vendor-list">
			<?php if ( empty( $vendors ) ) : ?>
				<div class="wpconsent-no-vendors">
					<p><?php esc_html_e( 'No vendors found matching your criteria.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $vendors as $vendor_id => $vendor ) : ?>
					<?php $this->output_vendor_item( $vendor_id, $vendor, $selected_vendors, $purposes, $special_purposes, $publisher_restrictions ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output vendor pagination placeholder (handled by JavaScript).
	 *
	 * @return void
	 */
	protected function output_vendor_pagination() {
		?>
		<div class="wpconsent-vendor-pagination" style="display: none;">
			<button type="button" class="wpconsent-button wpconsent-button-secondary" id="vendor-prev-page" disabled>
				<?php esc_html_e( '← Previous', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>

			<span class="wpconsent-pagination-info">
				<?php esc_html_e( 'Page 1 of 1', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</span>

			<button type="button" class="wpconsent-button wpconsent-button-secondary" id="vendor-next-page" disabled>
				<?php esc_html_e( 'Next →', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Output a single vendor item.
	 *
	 * @param int   $vendor_id The vendor ID.
	 * @param array $vendor The vendor data.
	 * @param array $selected_vendors The selected vendors.
	 * @param array $purposes The purposes data.
	 * @param array $special_purposes The special purposes data.
	 * @param array $publisher_restrictions The publisher restrictions data.
	 *
	 * @return void
	 */
	protected function output_vendor_item( $vendor_id, $vendor, $selected_vendors, $purposes, $special_purposes, $publisher_restrictions ) {
		$is_selected             = in_array( (int) $vendor_id, array_map( 'intval', $selected_vendors ), true );
		$vendor_purposes         = isset( $vendor['purposes'] ) ? $vendor['purposes'] : array();
		$vendor_legint_purposes  = isset( $vendor['legIntPurposes'] ) ? $vendor['legIntPurposes'] : array();
		$vendor_special_purposes = isset( $vendor['specialPurposes'] ) ? $vendor['specialPurposes'] : array();
		$vendor_urls             = $this->get_vendor_urls_for_language( $vendor );

		// Get vendor-specific restrictions.
		$vendor_restrictions         = isset( $publisher_restrictions['vendors'][ $vendor_id ] ) ? $publisher_restrictions['vendors'][ $vendor_id ] : array();
		$disallowed_purposes         = isset( $vendor_restrictions['disallowed_purposes'] ) ? $vendor_restrictions['disallowed_purposes'] : array();
		$require_consent_for_li      = isset( $vendor_restrictions['require_consent_for_li'] ) ? $vendor_restrictions['require_consent_for_li'] : array();
		$disallowed_special_purposes = isset( $vendor_restrictions['disallowed_special_purposes'] ) ? $vendor_restrictions['disallowed_special_purposes'] : array();
		?>
		<div class="wpconsent-vendor-item <?php echo $is_selected ? 'selected' : ''; ?>" data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>">
			<div class="wpconsent-vendor-header">
				<div class="wpconsent-vendor-selection">
					<input type="checkbox"
					       id="vendor-<?php echo esc_attr( $vendor_id ); ?>"
					       name="selected_vendors[]"
					       value="<?php echo esc_attr( $vendor_id ); ?>"
							<?php checked( $is_selected ); ?>
                           class="wpconsent-vendor-checkbox">
				</div>
				<div class="wpconsent-vendor-info">
					<h3 class="wpconsent-vendor-name">
						<label for="vendor-<?php echo esc_attr( $vendor_id ); ?>">
							<?php echo esc_html( $vendor['name'] ); ?>
							<span class="wpconsent-vendor-id">(ID: <?php echo esc_html( $vendor_id ); ?>)</span>
						</label>
					</h3>
					<?php if ( ! empty( $vendor_urls['privacy'] ) || ! empty( $vendor_urls['legIntClaim'] ) ) : ?>
						<div class="wpconsent-vendor-policy">
							<?php if ( ! empty( $vendor_urls['privacy'] ) ) : ?>
								<a href="<?php echo esc_url( $vendor_urls['privacy'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Privacy Policy', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $vendor_urls['legIntClaim'] ) ) : ?>
								<?php if ( ! empty( $vendor_urls['privacy'] ) ) : ?>
									<span class="wpconsent-vendor-policy-separator"> | </span>
								<?php endif; ?>
								<a href="<?php echo esc_url( $vendor_urls['legIntClaim'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Legitimate Interest', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="wpconsent-vendor-toggle">
					<button type="button" class="wpconsent-vendor-details-toggle" aria-expanded="false">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
				</div>
			</div>
			<div class="wpconsent-vendor-details" style="display: none;">
				<?php if ( ! empty( $vendor_purposes ) || ! empty( $vendor_legint_purposes ) ) : ?>
					<div class="wpconsent-vendor-purposes">
						<h4><?php esc_html_e( 'Declared Purposes', 'wpconsent-cookies-banner-privacy-suite' ); ?></h4>

						<?php if ( ! empty( $vendor_purposes ) ) : ?>
							<div class="wpconsent-purposes-section">
								<h5><?php esc_html_e( 'Consent Purposes', 'wpconsent-cookies-banner-privacy-suite' ); ?></h5>
								<ul class="wpconsent-purposes-list">
									<?php foreach ( $vendor_purposes as $purpose_id ) : ?>
										<li>
											<strong><?php echo esc_html( $purpose_id ); ?>:</strong>
											<?php if ( isset( $purposes[ $purpose_id ] ) ) : ?>
												<?php echo esc_html( $purposes[ $purpose_id ]['name'] ); ?>
											<?php else : ?>
												<?php esc_html_e( 'Unknown Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $vendor_legint_purposes ) ) : ?>
							<div class="wpconsent-purposes-section">
								<h5><?php esc_html_e( 'Legitimate Interest Purposes', 'wpconsent-cookies-banner-privacy-suite' ); ?></h5>
								<ul class="wpconsent-purposes-list">
									<?php foreach ( $vendor_legint_purposes as $purpose_id ) : ?>
										<li>
											<strong><?php echo esc_html( $purpose_id ); ?>:</strong>
											<?php if ( isset( $purposes[ $purpose_id ] ) ) : ?>
												<?php echo esc_html( $purposes[ $purpose_id ]['name'] ); ?>
											<?php else : ?>
												<?php esc_html_e( 'Unknown Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $vendor_special_purposes ) ) : ?>
					<div class="wpconsent-vendor-special-purposes">
						<h4><?php esc_html_e( 'Special Purposes', 'wpconsent-cookies-banner-privacy-suite' ); ?></h4>
						<ul class="wpconsent-purposes-list">
							<?php foreach ( $vendor_special_purposes as $special_purpose_id ) : ?>
								<li>
									<strong><?php echo esc_html( $special_purpose_id ); ?>:</strong>
									<?php if ( isset( $special_purposes[ $special_purpose_id ] ) ) : ?>
										<?php echo esc_html( $special_purposes[ $special_purpose_id ]['name'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Unknown Special Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php $this->output_vendor_restrictions( $vendor_id, $vendor_purposes, $vendor_legint_purposes, $vendor_special_purposes, $purposes, $special_purposes, $disallowed_purposes, $require_consent_for_li, $disallowed_special_purposes ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get vendor URLs for the current language.
	 *
	 * @param array $vendor The vendor data.
	 *
	 * @return array Array with 'privacy' and 'legIntClaim' URLs.
	 */
	protected function get_vendor_urls_for_language( $vendor ) {
		$urls = array(
				'privacy'     => '',
				'legIntClaim' => '',
		);

		// Fallback to legacy policyUrl if urls array is not available.
		if ( ! isset( $vendor['urls'] ) || ! is_array( $vendor['urls'] ) ) {
			if ( isset( $vendor['policyUrl'] ) ) {
				$urls['privacy'] = $vendor['policyUrl'];
			}

			return $urls;
		}

		// Get current language code (e.g., 'en' from 'en_US').
		$current_locale = get_locale();
		$current_lang   = substr( $current_locale, 0, 2 );

		// First, try to find exact match for current language.
		foreach ( $vendor['urls'] as $url_data ) {
			if ( isset( $url_data['langId'] ) && $url_data['langId'] === $current_lang ) {
				if ( isset( $url_data['privacy'] ) ) {
					$urls['privacy'] = $url_data['privacy'];
				}
				if ( isset( $url_data['legIntClaim'] ) ) {
					$urls['legIntClaim'] = $url_data['legIntClaim'];
				}

				return $urls;
			}
		}

		// If no match found, try to find English as fallback.
		foreach ( $vendor['urls'] as $url_data ) {
			if ( isset( $url_data['langId'] ) && $url_data['langId'] === 'en' ) {
				if ( isset( $url_data['privacy'] ) ) {
					$urls['privacy'] = $url_data['privacy'];
				}
				if ( isset( $url_data['legIntClaim'] ) ) {
					$urls['legIntClaim'] = $url_data['legIntClaim'];
				}

				return $urls;
			}
		}

		// If no English found, use the first available URL.
		if ( ! empty( $vendor['urls'] ) && is_array( $vendor['urls'] ) ) {
			$first_url = reset( $vendor['urls'] );
			if ( isset( $first_url['privacy'] ) ) {
				$urls['privacy'] = $first_url['privacy'];
			}
			if ( isset( $first_url['legIntClaim'] ) ) {
				$urls['legIntClaim'] = $first_url['legIntClaim'];
			}
		}

		return $urls;
	}

	/**
	 * Output per-vendor restriction controls.
	 *
	 * @param int   $vendor_id The vendor ID.
	 * @param array $vendor_purposes The vendor's consent purposes.
	 * @param array $vendor_legint_purposes The vendor's legitimate interest purposes.
	 * @param array $vendor_special_purposes The vendor's special purposes.
	 * @param array $purposes The purposes data.
	 * @param array $special_purposes The special purposes data.
	 * @param array $disallowed_purposes Current disallowed purposes for this vendor.
	 * @param array $require_consent_for_li Current purposes requiring consent for LI.
	 * @param array $disallowed_special_purposes Current disallowed special purposes.
	 *
	 * @return void
	 */
	protected function output_vendor_restrictions( $vendor_id, $vendor_purposes, $vendor_legint_purposes, $vendor_special_purposes, $purposes, $special_purposes, $disallowed_purposes, $require_consent_for_li, $disallowed_special_purposes ) {
		// Only show restrictions if vendor has any purposes.
		if ( empty( $vendor_purposes ) && empty( $vendor_legint_purposes ) && empty( $vendor_special_purposes ) ) {
			return;
		}
		?>
		<div class="wpconsent-vendor-restrictions">
			<h4><?php esc_html_e( 'Publisher Restrictions', 'wpconsent-cookies-banner-privacy-suite' ); ?></h4>
			<p class="wpconsent-restrictions-description">
				<?php esc_html_e( 'Override the default legal bases for this vendor. These restrictions allow you to enforce stricter data policies for individual vendors.', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</p>

			<?php if ( ! empty( $vendor_purposes ) ) : ?>
				<div class="wpconsent-restrictions-section">
					<h5><?php esc_html_e( 'Consent Purpose Restrictions', 'wpconsent-cookies-banner-privacy-suite' ); ?></h5>
					<div class="wpconsent-restrictions-list">
						<?php foreach ( $vendor_purposes as $purpose_id ) : ?>
							<div class="wpconsent-restriction-item">
								<label class="wpconsent-restriction-label">
									<strong><?php echo esc_html( $purpose_id ); ?>:</strong>
									<?php if ( isset( $purposes[ $purpose_id ] ) ) : ?>
										<?php echo esc_html( $purposes[ $purpose_id ]['name'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Unknown Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<?php endif; ?>
								</label>
								<div class="wpconsent-restriction-control">
									<label class="wpconsent-checkbox-label">
										<input type="checkbox"
										       name="vendor_restrictions[<?php echo esc_attr( $vendor_id ); ?>][disallowed_purposes][]"
										       value="<?php echo esc_attr( $purpose_id ); ?>"
												<?php checked( in_array( $purpose_id, $disallowed_purposes, true ), true ); ?>>
										<span><?php esc_html_e( 'Disallow', 'wpconsent-cookies-banner-privacy-suite' ); ?></span>
									</label>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $vendor_legint_purposes ) ) : ?>
				<div class="wpconsent-restrictions-section">
					<h5><?php esc_html_e( 'Legitimate Interest Purpose Restrictions', 'wpconsent-cookies-banner-privacy-suite' ); ?></h5>
					<div class="wpconsent-restrictions-list">
						<?php foreach ( $vendor_legint_purposes as $purpose_id ) : ?>
							<div class="wpconsent-restriction-item">
								<label class="wpconsent-restriction-label">
									<strong><?php echo esc_html( $purpose_id ); ?>:</strong>
									<?php if ( isset( $purposes[ $purpose_id ] ) ) : ?>
										<?php echo esc_html( $purposes[ $purpose_id ]['name'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Unknown Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<?php endif; ?>
								</label>
								<div class="wpconsent-restriction-control">
									<select name="vendor_restrictions[<?php echo esc_attr( $vendor_id ); ?>][li_purposes][<?php echo esc_attr( $purpose_id ); ?>]" class="wpconsent-select-small">
										<option value="allow" <?php selected( ! in_array( $purpose_id, $disallowed_purposes, true ) && ! in_array( $purpose_id, $require_consent_for_li, true ), true ); ?>>
											<?php esc_html_e( 'Allow LI', 'wpconsent-cookies-banner-privacy-suite' ); ?>
										</option>
										<option value="disallow" <?php selected( in_array( $purpose_id, $disallowed_purposes, true ), true ); ?>>
											<?php esc_html_e( 'Disallow', 'wpconsent-cookies-banner-privacy-suite' ); ?>
										</option>
										<option value="require_consent" <?php selected( in_array( $purpose_id, $require_consent_for_li, true ), true ); ?>>
											<?php esc_html_e( 'Require Consent', 'wpconsent-cookies-banner-privacy-suite' ); ?>
										</option>
									</select>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $vendor_special_purposes ) ) : ?>
				<div class="wpconsent-restrictions-section">
					<h5><?php esc_html_e( 'Special Purpose Restrictions', 'wpconsent-cookies-banner-privacy-suite' ); ?></h5>
					<div class="wpconsent-restrictions-list">
						<?php foreach ( $vendor_special_purposes as $special_purpose_id ) : ?>
							<div class="wpconsent-restriction-item">
								<label class="wpconsent-restriction-label">
									<strong><?php echo esc_html( $special_purpose_id ); ?>:</strong>
									<?php if ( isset( $special_purposes[ $special_purpose_id ] ) ) : ?>
										<?php echo esc_html( $special_purposes[ $special_purpose_id ]['name'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Unknown Special Purpose', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									<?php endif; ?>
								</label>
								<div class="wpconsent-restriction-control">
									<label class="wpconsent-checkbox-label">
										<input type="checkbox"
										       name="vendor_restrictions[<?php echo esc_attr( $vendor_id ); ?>][disallowed_special_purposes][]"
										       value="<?php echo esc_attr( $special_purpose_id ); ?>"
												<?php checked( in_array( $special_purpose_id, $disallowed_special_purposes, true ), true ); ?>>
										<span><?php esc_html_e( 'Disallow', 'wpconsent-cookies-banner-privacy-suite' ); ?></span>
									</label>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}


	/**
	 * Filter vendors based on search term and status.
	 *
	 * @param array  $vendors The vendors array.
	 * @param string $search_term The search term.
	 * @param string $status_filter The status filter.
	 * @param array  $selected_vendors The selected vendors.
	 *
	 * @return array Filtered vendors.
	 */
	protected function filter_vendors( $vendors, $search_term, $status_filter, $selected_vendors ) {
		$filtered = array();

		foreach ( $vendors as $vendor_id => $vendor ) {
			// Search filter.
			if ( ! empty( $search_term ) ) {
				$search_lower      = strtolower( $search_term );
				$vendor_name_lower = strtolower( $vendor['name'] );
				$vendor_id_str     = strval( $vendor_id );

				if ( strpos( $vendor_name_lower, $search_lower ) === false &&
				     strpos( $vendor_id_str, $search_lower ) === false ) {
					continue;
				}
			}

			// Status filter.
			if ( ! empty( $status_filter ) ) {
				$is_selected = in_array( (int) $vendor_id, array_map( 'intval', $selected_vendors ), true );
				if ( ( $status_filter === 'selected' && ! $is_selected ) ||
				     ( $status_filter === 'not_selected' && $is_selected ) ) {
					continue;
				}
			}

			$filtered[ $vendor_id ] = $vendor;
		}

		return $filtered;
	}

	/**
	 * Sort vendors based on sort order.
	 *
	 * @param array  $vendors The vendors array.
	 * @param string $sort_order The sort order.
	 *
	 * @return array Sorted vendors.
	 */
	protected function sort_vendors( $vendors, $sort_order ) {
		switch ( $sort_order ) {
			case 'name_desc':
				uasort( $vendors, function ( $a, $b ) {
					return strcasecmp( $b['name'], $a['name'] );
				} );
				break;
			case 'id_asc':
				uksort( $vendors, function ( $a, $b ) {
					return $a - $b;
				} );
				break;
			case 'id_desc':
				uksort( $vendors, function ( $a, $b ) {
					return $b - $a;
				} );
				break;
			case 'name_asc':
			default:
				uasort( $vendors, function ( $a, $b ) {
					return strcasecmp( $a['name'], $b['name'] );
				} );
				break;
		}

		return $vendors;
	}

	/**
	 * Output vendor controls (search, filters, sorting).
	 *
	 * @param int $total_vendors Total number of vendors.
	 *
	 * @return void
	 */
	protected function output_vendor_controls( $total_vendors ) {
		?>
		<div class="wpconsent-vendor-controls">
			<div class="wpconsent-vendor-controls-row">
				<div class="wpconsent-vendor-search">
					<input type="text"
					       id="vendor-search"
					       placeholder="<?php esc_attr_e( 'Search vendors by name or ID...', 'wpconsent-cookies-banner-privacy-suite' ); ?>"
					       class="wpconsent-input-text">
					<button type="button" class="wpconsent-button wpconsent-button-secondary" id="vendor-search-btn">
						<?php esc_html_e( 'Search', 'wpconsent-cookies-banner-privacy-suite' ); ?>
					</button>
					<button type="button" class="wpconsent-button wpconsent-button-secondary" id="vendor-clear-search" style="display: none;">
						<?php esc_html_e( 'Clear', 'wpconsent-cookies-banner-privacy-suite' ); ?>
					</button>
				</div>
				<div class="wpconsent-vendor-filters">
					<select id="vendor-status-filter" class="wpconsent-select">
						<option value=""><?php esc_html_e( 'All Vendors', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="selected"><?php esc_html_e( 'Selected', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="not_selected"><?php esc_html_e( 'Not Selected', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
					</select>
					<select id="vendor-sort-order" class="wpconsent-select">
						<option value="name_asc"><?php esc_html_e( 'Name A-Z', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="name_desc"><?php esc_html_e( 'Name Z-A', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="id_asc"><?php esc_html_e( 'ID Low-High', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
						<option value="id_desc"><?php esc_html_e( 'ID High-Low', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
					</select>
				</div>
			</div>
			<div class="wpconsent-vendor-results-info">
				<span><?php printf( esc_html__( 'Showing %d vendors', 'wpconsent-cookies-banner-privacy-suite' ), $total_vendors ); ?></span>
			</div>
			<div class="wpconsent-vendor-save-section">
				<button type="submit" class="wpconsent-button wpconsent-button-primary" id="wpconsent-save-vendors">
					<?php esc_html_e( 'Save Changes', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
