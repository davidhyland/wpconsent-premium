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
		$data['license_can']         = wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() );

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
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function export_custom_scripts_input() {
		$this->metabox_row(
				esc_html__( 'Custom Scripts', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
						false,
						'export_custom_scripts',
						esc_html__( 'Export custom scripts and iframes.', 'wpconsent-premium' )
				),
				'export_custom_scripts',
				'',
				'',
				''
		);
	}

	/**
	 * Get custom scripts for export.
	 *
	 * @return array
	 */
	protected function get_custom_scripts_for_export() {
		$custom_scripts = get_option( 'wpconsent_custom_scripts', array() );

		if ( ! is_array( $custom_scripts ) || empty( $custom_scripts ) ) {
			return array();
		}

		$sanitized_scripts = array();

		// Convert IDs to slugs in the custom scripts data.
		foreach ( $custom_scripts as $script_id => $script_data ) {
			if ( ! is_array( $script_data ) ) {
				continue;
			}

			$required_keys = array( 'category', 'service', 'type', 'tag' );
			foreach ( $required_keys as $key ) {
				if ( ! isset( $script_data[ $key ] ) ) {
					continue 2;
				}
			}

			$sanitized_script_id = sanitize_key( $script_id );
			if ( empty( $sanitized_script_id ) ) {
				continue;
			}

			$category_id = absint( $script_data['category'] );
			if ( 0 === $category_id ) {
				continue;
			}

			$category_data = wpconsent()->cookies->get_category_by_id( $category_id );
			if ( ! $category_data || ! isset( $category_data['slug'] ) ) {
				continue;
			}

			$service_id = absint( $script_data['service'] );
			if ( 0 === $service_id ) {
				continue;
			}

			$service_data = wpconsent()->cookies->get_service_by_id( $service_id );
			if ( ! $service_data || ! isset( $service_data['slug'] ) ) {
				continue;
			}

			// Sanitize and build the script data for export.
			$sanitized_scripts[ $sanitized_script_id ] = array(
					'category'         => sanitize_key( $category_data['slug'] ),
					'service'          => sanitize_key( $service_data['slug'] ),
					'type'             => in_array(
							$script_data['type'],
							array(
									'script',
									'iframe',
							),
							true
					) ? $script_data['type'] : 'script',
					'tag'              => wp_kses_post( $script_data['tag'] ),
					'blocked_elements' => isset( $script_data['blocked_elements'] ) && is_array( $script_data['blocked_elements'] ) ?
							wpconsent()->cookies->blocked_elements_to_string( $script_data['blocked_elements'] ) : '',
			);
		}

		return $sanitized_scripts;
	}

	/**
	 * Import custom scripts from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_custom_scripts( $import_data ) {
		if ( ! isset( $import_data['custom_scripts'] ) || ! is_array( $import_data['custom_scripts'] ) ) {
			return;
		}

		$custom_scripts = $import_data['custom_scripts'];

		if ( empty( $custom_scripts ) ) {
			return;
		}

		foreach ( $custom_scripts as $script_id => $script_data ) {
			if ( ! is_array( $script_data ) ) {
				continue;
			}

			$required_keys = array( 'category', 'service', 'type', 'tag' );
			foreach ( $required_keys as $key ) {
				if ( ! isset( $script_data[ $key ] ) ) {
					continue 2;
				}
			}

			$sanitized_script_id = sanitize_key( $script_id );
			if ( empty( $sanitized_script_id ) ) {
				continue;
			}

			$category_slug = sanitize_key( $script_data['category'] );
			if ( empty( $category_slug ) ) {
				continue;
			}

			$category_data = wpconsent()->cookies->get_service_by_slug( $category_slug );
			if ( ! $category_data || ! isset( $category_data['id'] ) ) {
				continue;
			}
			$category_id = absint( $category_data['id'] );
			if ( 0 === $category_id ) {
				continue;
			}

			$service_slug = sanitize_key( $script_data['service'] );
			if ( empty( $service_slug ) ) {
				continue;
			}

			$service_data = wpconsent()->cookies->get_service_by_slug( $service_slug );
			if ( ! $service_data || ! isset( $service_data['id'] ) ) {
				continue;
			}
			$service_id = absint( $service_data['id'] );
			if ( 0 === $service_id ) {
				continue;
			}

			$script_type = sanitize_key( $script_data['type'] );
			if ( ! in_array( $script_type, array( 'script', 'iframe' ), true ) ) {
				$script_type = 'script';
			}

			$script_tag = wp_kses_post( $script_data['tag'] );
			if ( empty( $script_tag ) ) {
				continue;
			}

			$blocked_elements = isset( $script_data['blocked_elements'] ) ? sanitize_text_field( $script_data['blocked_elements'] ) : '';

			wpconsent()->cookies->add_script(
					$category_id,
					$service_id,
					$script_type,
					$script_tag,
					$blocked_elements
			);
		}
	}

	/**
	 * Get banner design settings for export.
	 *
	 * @param array $all_options All plugin options.
	 *
	 * @return array
	 */
	protected function get_banner_design_for_export( $all_options ) {
		$banner_data = parent::get_banner_design_for_export( $all_options );

		$banner_data['enabled_languages'] = $all_options['enabled_languages'];
		$enabled_languages                = $all_options['enabled_languages'];
		foreach ( $enabled_languages as $locale ) {
			if ( isset( $all_options[ $locale ] ) && is_array( $all_options[ $locale ] ) ) {
				$banner_data[ $locale ] = $all_options[ $locale ];

				if ( isset( $banner_data[ $locale ]['content_blocking_placeholder_text'] ) ) {
					unset( $banner_data[ $locale ]['content_blocking_placeholder_text'] );
				}
			}
		}

		if ( isset( $all_options[''] ) && is_array( $all_options[''] ) ) {
			$banner_data[''] = $all_options[''];

			if ( isset( $banner_data['']['content_blocking_placeholder_text'] ) ) {
				unset( $banner_data['']['content_blocking_placeholder_text'] );
			}
		}

		return $banner_data;
	}

	/**
	 * Import banner design from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_banner_design( $import_data ) {
		// First, let the parent class import the main banner design settings.
		parent::import_banner_design( $import_data );

		// Now import the translations for each language.
		if ( isset( $import_data['banner_design'] ) ) {
			$banner_design = $import_data['banner_design'];

			// Check the banner_design enabled languages to see which languages we need to look for.
			$enabled_languages = isset( $banner_design['enabled_languages'] ) ? $banner_design['enabled_languages'] : array();

			foreach ( $enabled_languages as $enabled_language ) {
				if ( empty( $banner_design[ $enabled_language ] ) ) {
					continue;
				}
				$language_texts = $banner_design[ $enabled_language ];

				// Let's go through each option and sanitize it.
				foreach ( $language_texts as $option => $value ) {
					if ( is_array( $value ) ) {
						// If it's an array, we need to sanitize each value.
						foreach ( $value as $key => $val ) {
							$language_texts[ $option ][ $key ] = wp_kses_post( $val );
						}
					} else {
						// Otherwise, just sanitize the value.
						$language_texts[ $option ] = wp_kses_post( $value );
					}
				}

				// Now we can update the option.
				wpconsent()->settings->bulk_update_options( array( $enabled_language => $language_texts ) );
			}
		}

		// Clear translation strings cache when settings change.
		delete_transient( 'wpconsent_translation_strings' );
	}

	/**
	 * Get cookie data for export.
	 *
	 * @return array
	 */
	protected function get_cookie_data_for_export() {
		$export_data = parent::get_cookie_data_for_export();

		// Add translations for each category and its contents.
		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );
		foreach ( $export_data as $category_slug => &$category_data ) {
			$category = get_term_by( 'slug', $category_slug, wpconsent()->cookies->taxonomy );
			if ( ! $category ) {
				continue;
			}

			// Add category translations.
			foreach ( $enabled_languages as $locale ) {
				$translated_name = get_term_meta( $category->term_id, 'wpconsent_category_name_' . $locale, true );
				$translated_desc = get_term_meta( $category->term_id, 'wpconsent_category_description_' . $locale, true );
				if ( $translated_name || $translated_desc ) {
					$category_data['translations'][ $locale ] = array(
							'name'        => $translated_name,
							'description' => $translated_desc,
					);
				}
			}

			// Add cookie translations.
			foreach ( $category_data['cookies'] as &$cookie_data ) {
				foreach ( $enabled_languages as $locale ) {
					$translated_name = get_post_meta( $cookie_data['id'], 'wpconsent_cookie_name_' . $locale, true );
					$translated_desc = get_post_meta( $cookie_data['id'], 'wpconsent_cookie_description_' . $locale, true );
					if ( $translated_name || $translated_desc ) {
						$cookie_data['translations'][ $locale ] = array(
								'name'        => $translated_name,
								'description' => $translated_desc,
						);
					}
				}
			}

			// Add service translations.
			foreach ( $category_data['services'] as &$service_data ) {
				foreach ( $enabled_languages as $locale ) {
					$translated_name = get_post_meta( $service_data['id'], 'wpconsent_service_name_' . $locale, true );
					$translated_desc = get_post_meta( $service_data['id'], 'wpconsent_service_description_' . $locale, true );
					$translated_url  = get_post_meta( $service_data['id'], 'wpconsent_service_url_' . $locale, true );
					if ( $translated_name || $translated_desc || $translated_url ) {
						$service_data['translations'][ $locale ] = array(
								'name'        => $translated_name,
								'description' => $translated_desc,
								'service_url' => $translated_url,
						);
					}
				}

				// Add cookie translations for service cookies.
				foreach ( $service_data['cookies'] as &$cookie_data ) {
					foreach ( $enabled_languages as $locale ) {
						$translated_name = get_post_meta( $cookie_data['id'], 'wpconsent_cookie_name_' . $locale, true );
						$translated_desc = get_post_meta( $cookie_data['id'], 'wpconsent_cookie_description_' . $locale, true );
						if ( $translated_name || $translated_desc ) {
							$cookie_data['translations'][ $locale ] = array(
									'name'        => $translated_name,
									'description' => $translated_desc,
							);
						}
					}
				}
			}
		}

		return $export_data;
	}

	/**
	 * Import additional category data.
	 *
	 * @param int   $category_id The category ID.
	 * @param array $category_data The category data.
	 *
	 * @return void
	 */
	protected function import_category_data( $category_id, $category_data ) {
		// Import category translations.
		if ( isset( $category_data['translations'] ) ) {
			foreach ( $category_data['translations'] as $locale => $translation ) {
				if ( isset( $translation['name'] ) ) {
					update_term_meta( $category_id, 'wpconsent_category_name_' . $locale, sanitize_text_field( $translation['name'] ) );
				}
				if ( isset( $translation['description'] ) ) {
					update_term_meta( $category_id, 'wpconsent_category_description_' . $locale, wp_kses_post( $translation['description'] ) );
				}
			}
		}
	}

	/**
	 * Import additional cookie data.
	 *
	 * @param int   $post_id The cookie post ID.
	 * @param array $cookie_data The cookie data.
	 *
	 * @return void
	 */
	protected function import_cookie_data( $post_id, $cookie_data ) {
		// Import cookie translations.
		if ( ! is_wp_error( $post_id ) && isset( $cookie_data['translations'] ) ) {
			foreach ( $cookie_data['translations'] as $locale => $translation ) {
				if ( isset( $translation['name'] ) ) {
					update_post_meta( $post_id, 'wpconsent_cookie_name_' . $locale, sanitize_text_field( $translation['name'] ) );
				}
				if ( isset( $translation['description'] ) ) {
					update_post_meta( $post_id, 'wpconsent_cookie_description_' . $locale, wp_kses_post( $translation['description'] ) );
				}
			}
		}
	}

	/**
	 * Import additional service data.
	 *
	 * @param int   $service_id The service ID.
	 * @param array $service_data The service data.
	 *
	 * @return void
	 */
	protected function import_service_data( $service_id, $service_data ) {
		// Import service translations.
		if ( isset( $service_data['translations'] ) ) {
			foreach ( $service_data['translations'] as $locale => $translation ) {
				if ( isset( $translation['name'] ) ) {
					update_post_meta( $service_id, 'wpconsent_service_name_' . $locale, sanitize_text_field( $translation['name'] ) );
				}
				if ( isset( $translation['description'] ) ) {
					update_post_meta( $service_id, 'wpconsent_service_description_' . $locale, wp_kses_post( $translation['description'] ) );
				}
				if ( isset( $translation['service_url'] ) ) {
					update_post_meta( $service_id, 'wpconsent_service_url_' . $locale, esc_url( $translation['service_url'] ) );
				}
			}
		}
	}

	/**
	 * Get settings for export, excluding geolocation_groups.
	 *
	 * @param array $all_options All plugin options.
	 *
	 * @return array
	 */
	protected function get_settings_for_export( $all_options ) {
		$settings = parent::get_settings_for_export( $all_options );

		// Remove geolocation_groups as it's handled separately in Pro.
		unset( $settings['geolocation_groups'] );

		return $settings;
	}

	/**
	 * Get geolocation groups for export.
	 *
	 * @param array $all_options All plugin options.
	 *
	 * @return array
	 */
	protected function get_geolocation_groups_for_export( $all_options ) {
		$geolocation_groups = isset( $all_options['geolocation_groups'] ) ? $all_options['geolocation_groups'] : array();

		if ( empty( $geolocation_groups ) || ! is_array( $geolocation_groups ) ) {
			return array();
		}

		$export_groups = array();

		foreach ( $geolocation_groups as $group_id => $group_data ) {
			if ( ! is_array( $group_data ) ) {
				continue;
			}

			$sanitized_group = array();

			// Export basic fields.
			if ( isset( $group_data['name'] ) ) {
				$sanitized_group['name'] = $group_data['name'];
			}

			if ( isset( $group_data['enable_script_blocking'] ) ) {
				$sanitized_group['enable_script_blocking'] = (bool) $group_data['enable_script_blocking'];
			}

			if ( isset( $group_data['show_banner'] ) ) {
				$sanitized_group['show_banner'] = (bool) $group_data['show_banner'];
			}

			if ( isset( $group_data['enable_consent_floating'] ) ) {
				$sanitized_group['enable_consent_floating'] = (bool) $group_data['enable_consent_floating'];
			}

			if ( isset( $group_data['manual_toggle_services'] ) ) {
				$sanitized_group['manual_toggle_services'] = (bool) $group_data['manual_toggle_services'];
			}

			if ( isset( $group_data['consent_mode'] ) ) {
				$sanitized_group['consent_mode'] = $group_data['consent_mode'];
			}

			if ( isset( $group_data['customize_banner_buttons'] ) ) {
				$sanitized_group['customize_banner_buttons'] = (bool) $group_data['customize_banner_buttons'];
			}

			if ( isset( $group_data['customize_banner_message'] ) ) {
				$sanitized_group['customize_banner_message'] = (bool) $group_data['customize_banner_message'];
			}

			// Export locations array.
			if ( isset( $group_data['locations'] ) && is_array( $group_data['locations'] ) ) {
				$sanitized_group['locations'] = array();
				foreach ( $group_data['locations'] as $location ) {
					if ( ! is_array( $location ) ) {
						continue;
					}

					$sanitized_location = array();

					if ( isset( $location['type'] ) ) {
						$sanitized_location['type'] = $location['type'];
					}

					if ( isset( $location['code'] ) ) {
						$sanitized_location['code'] = $location['code'];
					}

					if ( isset( $location['name'] ) ) {
						$sanitized_location['name'] = $location['name'];
					}

					// Only add location if it has both type and code.
					if ( ! empty( $sanitized_location['type'] ) && ! empty( $sanitized_location['code'] ) ) {
						$sanitized_group['locations'][] = $sanitized_location;
					}
				}
			}

			// Export button customization fields.
			if ( isset( $group_data['accept_button_text'] ) ) {
				$sanitized_group['accept_button_text'] = $group_data['accept_button_text'];
			}

			if ( isset( $group_data['cancel_button_text'] ) ) {
				$sanitized_group['cancel_button_text'] = $group_data['cancel_button_text'];
			}

			if ( isset( $group_data['preferences_button_text'] ) ) {
				$sanitized_group['preferences_button_text'] = $group_data['preferences_button_text'];
			}

			if ( isset( $group_data['accept_button_enabled'] ) ) {
				$sanitized_group['accept_button_enabled'] = (bool) $group_data['accept_button_enabled'];
			}

			if ( isset( $group_data['cancel_button_enabled'] ) ) {
				$sanitized_group['cancel_button_enabled'] = (bool) $group_data['cancel_button_enabled'];
			}

			if ( isset( $group_data['preferences_button_enabled'] ) ) {
				$sanitized_group['preferences_button_enabled'] = (bool) $group_data['preferences_button_enabled'];
			}

			if ( isset( $group_data['button_order'] ) && is_array( $group_data['button_order'] ) ) {
				$sanitized_group['button_order'] = $group_data['button_order'];
			}

			// Export banner message.
			if ( isset( $group_data['banner_message'] ) ) {
				$sanitized_group['banner_message'] = $group_data['banner_message'];
			}

			// Only add the group if it has a name and at least one location.
			if ( ! empty( $sanitized_group['name'] ) && ! empty( $sanitized_group['locations'] ) ) {
				$export_groups[ $group_id ] = $sanitized_group;
			}
		}

		return $export_groups;
	}

	/**
	 * Import geolocation groups from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_geolocation_groups( $import_data ) {
		if ( ! isset( $import_data['geolocation_groups'] ) || ! is_array( $import_data['geolocation_groups'] ) ) {
			return;
		}

		$imported_groups = $import_data['geolocation_groups'];

		if ( empty( $imported_groups ) ) {
			// If the import data has an empty geolocation_groups array, clear the existing groups.
			wpconsent()->settings->update_option( 'geolocation_groups', array() );

			return;
		}

		$sanitized_groups = array();

		foreach ( $imported_groups as $group_id => $group_data ) {
			if ( ! is_array( $group_data ) ) {
				continue;
			}

			$sanitized_group = array();

			// Sanitize basic string fields.
			if ( isset( $group_data['name'] ) ) {
				$sanitized_group['name'] = sanitize_text_field( $group_data['name'] );
			}

			if ( isset( $group_data['consent_mode'] ) ) {
				$consent_mode = sanitize_text_field( $group_data['consent_mode'] );
				// Validate consent mode is one of the allowed values.
				if ( in_array( $consent_mode, array( 'optin', 'optout' ), true ) ) {
					$sanitized_group['consent_mode'] = $consent_mode;
				} else {
					$sanitized_group['consent_mode'] = 'optin';
				}
			}

			// Sanitize boolean fields.
			if ( isset( $group_data['enable_script_blocking'] ) ) {
				$sanitized_group['enable_script_blocking'] = (bool) $group_data['enable_script_blocking'];
			}

			if ( isset( $group_data['show_banner'] ) ) {
				$sanitized_group['show_banner'] = (bool) $group_data['show_banner'];
			}

			if ( isset( $group_data['enable_consent_floating'] ) ) {
				$sanitized_group['enable_consent_floating'] = (bool) $group_data['enable_consent_floating'];
			}

			if ( isset( $group_data['manual_toggle_services'] ) ) {
				$sanitized_group['manual_toggle_services'] = (bool) $group_data['manual_toggle_services'];
			}

			if ( isset( $group_data['customize_banner_buttons'] ) ) {
				$sanitized_group['customize_banner_buttons'] = (bool) $group_data['customize_banner_buttons'];
			}

			if ( isset( $group_data['customize_banner_message'] ) ) {
				$sanitized_group['customize_banner_message'] = (bool) $group_data['customize_banner_message'];
			}

			// Sanitize locations array.
			if ( isset( $group_data['locations'] ) && is_array( $group_data['locations'] ) ) {
				$sanitized_group['locations'] = array();
				foreach ( $group_data['locations'] as $location ) {
					if ( ! is_array( $location ) ) {
						continue;
					}

					$sanitized_location = array();

					if ( isset( $location['type'] ) ) {
						$type = sanitize_text_field( $location['type'] );
						// Validate type is one of the allowed values.
						if ( in_array( $type, array( 'continent', 'country', 'us_state' ), true ) ) {
							$sanitized_location['type'] = $type;
						}
					}

					if ( isset( $location['code'] ) ) {
						$sanitized_location['code'] = sanitize_text_field( $location['code'] );
					}

					if ( isset( $location['name'] ) ) {
						$sanitized_location['name'] = sanitize_text_field( $location['name'] );
					}

					// Only add location if it has both type and code.
					if ( ! empty( $sanitized_location['type'] ) && ! empty( $sanitized_location['code'] ) ) {
						$sanitized_group['locations'][] = $sanitized_location;
					}
				}
			}

			// Sanitize button customization fields.
			if ( isset( $group_data['accept_button_text'] ) ) {
				$sanitized_group['accept_button_text'] = sanitize_text_field( $group_data['accept_button_text'] );
			}

			if ( isset( $group_data['cancel_button_text'] ) ) {
				$sanitized_group['cancel_button_text'] = sanitize_text_field( $group_data['cancel_button_text'] );
			}

			if ( isset( $group_data['preferences_button_text'] ) ) {
				$sanitized_group['preferences_button_text'] = sanitize_text_field( $group_data['preferences_button_text'] );
			}

			if ( isset( $group_data['accept_button_enabled'] ) ) {
				$sanitized_group['accept_button_enabled'] = (bool) $group_data['accept_button_enabled'];
			}

			if ( isset( $group_data['cancel_button_enabled'] ) ) {
				$sanitized_group['cancel_button_enabled'] = (bool) $group_data['cancel_button_enabled'];
			}

			if ( isset( $group_data['preferences_button_enabled'] ) ) {
				$sanitized_group['preferences_button_enabled'] = (bool) $group_data['preferences_button_enabled'];
			}

			// Sanitize button order array.
			if ( isset( $group_data['button_order'] ) && is_array( $group_data['button_order'] ) ) {
				$sanitized_group['button_order'] = array();
				foreach ( $group_data['button_order'] as $button_id ) {
					$button_id = sanitize_text_field( $button_id );
					if ( in_array( $button_id, array( 'accept', 'cancel', 'preferences' ), true ) ) {
						$sanitized_group['button_order'][] = $button_id;
					}
				}
				// Ensure we have a valid button order, otherwise use default.
				if ( empty( $sanitized_group['button_order'] ) ) {
					$sanitized_group['button_order'] = array( 'accept', 'cancel', 'preferences' );
				}
			}

			// Sanitize banner message.
			if ( isset( $group_data['banner_message'] ) ) {
				$sanitized_group['banner_message'] = sanitize_textarea_field( $group_data['banner_message'] );
			}

			// Only add the group if it has a name and at least one location.
			if ( ! empty( $sanitized_group['name'] ) && ! empty( $sanitized_group['locations'] ) ) {
				// Sanitize the group ID.
				$sanitized_group_id = sanitize_key( $group_id );
				if ( ! empty( $sanitized_group_id ) ) {
					$sanitized_groups[ $sanitized_group_id ] = $sanitized_group;
				}
			}
		}

		// Save the imported geolocation groups.
		wpconsent()->settings->update_option( 'geolocation_groups', $sanitized_groups );

		// Check if we need to add the geolocation cookie after import.
		if ( ! empty( $sanitized_groups ) && class_exists( 'WPConsent_Geolocation' ) && isset( wpconsent()->geolocation ) ) {
			wpconsent()->geolocation->maybe_add_geolocation_cookie();
		}
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

						// Script-specific fields (shown when script type is selected).
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

						// iFrame-specific fields (shown when iframe type is selected).
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
}
