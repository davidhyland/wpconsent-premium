<?php
/**
 * Pro-specific tools admin page.
 *
 * @package WPConsent
 */

/**
 * Pro-specific tools admin page.
 */
class WPConsent_Admin_Page_Tools_Pro extends WPConsent_Admin_Page_Tools {

	/**
	 * Page specific Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		parent::page_hooks();

		// Hook the default DNT logs section rendering.
		add_action( 'wpconsent_tools_dnt_logs_section', array( $this, 'render_dnt_logs_section_default' ), 10 );
	}

	/**
	 * Add the strings for the js data.
	 *
	 * @param array $data The localized data we already have.
	 *
	 * @return array
	 */
	public function add_js_data( $data ) {
		$data['do_not_track_export'] = array(
			'title' => esc_html__( 'Do Not Track Export not available on your plan', 'wpconsent-premium' ),
			'text'  => esc_html__( 'The Do Not Track Export feature is only available on the Plus plan or higher. Upgrade your license today and easily export your do not track logs.', 'wpconsent-premium' ),
			'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite', 'export-do-not-track', 'custom-scripts-export' ),
		);

		return $data;
	}

	/**
	 * Get the export logs content.
	 *
	 * @return string
	 */
	public function get_export_logs_content() {
		ob_start();

		$this->metabox_row(
			esc_html__( 'Consent Logs', 'wpconsent-premium' ),
			$this->get_export_records_of_consent_button(),
			'',
			'',
			'',
			'',
			false,
			'export-records-of-consent'
		);

		$export_do_not_track_class  = 'export-do-not-track-basic';
		$export_do_not_track_button = true;

		if ( wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() ) ) {
			$export_do_not_track_class  = 'export-do-not-track';
			$export_do_not_track_button = false;
		}

		$this->metabox_row(
			esc_html__( 'Do Not Track Logs', 'wpconsent-premium' ),
			$this->get_export_do_not_track_button(),
			'',
			'',
			'',
			'',
			$export_do_not_track_button,
			$export_do_not_track_class
		);

		return ob_get_clean();
	}

	/**
	 * Get the export Records of Consent button.
	 *
	 * @return string
	 */
	public function get_export_records_of_consent_button() {
		ob_start();
		$roc_export_url = add_query_arg(
			array(
				'page' => 'wpconsent-consent-logs',
				'view' => 'export',
			),
			admin_url( 'admin.php' )
		);
		?>
		<button class="wpconsent-button wpconsent-button-primary" type="button" onclick="window.location.href='<?php echo esc_url( $roc_export_url ); ?>'">
			<?php esc_html_e( 'Export Consent Logs', 'wpconsent-premium' ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the export Do Not Track button.
	 *
	 * @return string
	 */
	public function get_export_do_not_track_button() {
		ob_start();
		if ( wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() ) ) {
			$dnt_export_url = add_query_arg(
				array(
					'page' => 'wpconsent-do-not-track',
					'view' => 'export',
				),
				admin_url( 'admin.php' )
			);
			?>
			<button class="wpconsent-button wpconsent-button-primary" type="button" onclick="window.location.href='<?php echo esc_url( $dnt_export_url ); ?>'">
				<?php esc_html_e( 'Export Do Not Track Logs', 'wpconsent-premium' ); ?>
			</button>
			<?php
		} else {
			?>
			<button class="wpconsent-button wpconsent-button-primary" type="button">
				<?php esc_html_e( 'Export Do Not Track Logs', 'wpconsent-premium' ); ?>
			</button>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * Get the input for enabling custom scripts export.
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
	 * Get banner design settings for export.
	 *
	 * @param array $all_options All plugin options.
	 *
	 * @return array
	 */
	protected function get_banner_design_for_export( $all_options ) {
		$banner_data = parent::get_banner_design_for_export( $all_options );

		$banner_data['enabled_languages'] = isset( $all_options['enabled_languages'] ) ? $all_options['enabled_languages'] : array();
		$enabled_languages                = isset( $all_options['enabled_languages'] ) ? $all_options['enabled_languages'] : array();

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
	 * Output the database view.
	 *
	 * @return void
	 */
	public function output_view_database() {
		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post">
			<?php
			wp_nonce_field( 'wpconsent_clear_cache', 'wpconsent_clear_cache_nonce' );
			$this->metabox(
				__( 'Database Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_database_settings_content()
			);
			?>
		</form>
		<?php

		$this->metabox(
			__( 'Clear Consent Logs', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_roc_logs_content()
		);

		/**
		 * Fires to render the Do Not Track logs section.
		 *
		 * This action allows the DNT addon to replace the default upsell
		 * with the actual DNT logs management interface when the addon is active.
		 *
		 * @since 1.0.0
		 *
		 * @param WPConsent_Admin_Page_Tools_Pro $this The tools page instance.
		 */
		do_action( 'wpconsent_tools_dnt_logs_section', $this );
	}

	/**
	 * Render the default DNT logs section with upsell.
	 *
	 * This method displays the DNT logs management interface with appropriate
	 * upsells based on license level. It can be replaced by the DNT addon.
	 *
	 * @return void
	 */
	public function render_dnt_logs_section_default() {
		?>
		<div class="wpconsent-dnt-clear-logs-container">
			<div class="wpconsent-blur-area">
				<?php
				$this->metabox(
					__( 'Clear Do Not Track Logs', 'wpconsent-cookies-banner-privacy-suite' ),
					$this->get_dnt_logs_content()
				);
				?>
			</div>
			<?php
			$addon_name = 'wpconsent-do-not-track';

			// Check if the user has a plus or higher license.
			if ( wpconsent()->license->license_can( 'plus', is_multisite() && is_network_admin() ) ) {
				// Check if the addon is active.
				$is_addon_active = function_exists( 'wpconsent_dnt' );

				// Check if the addon is installed but not active.
				$addon_installed = false;
				if ( ! $is_addon_active ) {
					$plugin_path     = wpconsent()->addons->get_plugin_path( $addon_name );
					$addon_installed = ! empty( $plugin_path );
				}

				if ( ! $is_addon_active ) {
					// Show 1-click install or activate option.
					if ( $addon_installed ) {
						$button_text = __( 'Activate Do Not Track Addon', 'wpconsent-premium' );
						$title       = __( 'The Do Not Track Addon is not active', 'wpconsent-premium' );
					} else {
						$button_text = __( 'Install Do Not Track Addon', 'wpconsent-premium' );
						$title       = __( 'The Do Not Track Addon is not installed', 'wpconsent-premium' );
					}

					echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( $title ),
						'<p>' . esc_html__( 'Install the addon now to allow your users to submit Do Not Track requests directly from your website.', 'wpconsent-premium' ) . '</p>',
						array(
							'text'       => esc_html( $button_text ),
							'tag'        => 'button',
							'class'      => 'wpconsent-button wpconsent-button-large wpconsent-button-install-addon',
							'attributes' => array(
								'data-addon' => esc_attr( $addon_name ),
							),
						)
					);
				}
			} else {
				// Show upgrade message for basic plan.
				echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html__( 'Clear Do Not Track Logs is a premium feature', 'wpconsent-cookies-banner-privacy-suite' ),
					'<p>' . esc_html__( 'Upgrade to WPConsent Plus or higher plans today and easily export your do not track logs. Monitor who requested to be excluded from tracking and when.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
					array(
						'text' => esc_html__( 'Upgrade to PRO and Unlock "Do Not Track"', 'wpconsent-cookies-banner-privacy-suite' ),
						'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'database-page', 'main' ) ),
					),
					array(
						'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
						'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'database-page', 'features' ) ),
					)
				);
			}
			?>
		</div>
		<?php
	}
}
