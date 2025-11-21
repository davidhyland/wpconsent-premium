<?php
/**
 * Admin paged used to configure the cookies loaded in the banner
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Cookies.
 */
class WPConsent_Admin_Page_Cookies extends WPConsent_Admin_Page {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent-cookies';

	/**
	 * Default view.
	 *
	 * @var string
	 */
	public $view = 'settings';

	use WPConsent_Input_Select;

	/**
	 * Current action.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Call this just to set the page title translatable.
	 */
	public function __construct() {
		$this->page_title = __( 'Cookies Configuration', 'wpconsent-cookies-banner-privacy-suite' );
		$this->menu_title = __( 'Settings', 'wpconsent-cookies-banner-privacy-suite' );
		parent::__construct();
	}

	/**
	 * Page specific Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		$this->process_message();
		add_action( 'admin_init', array( $this, 'handle_submit' ) );
		add_filter( 'wpconsent_admin_js_data', array( $this, 'add_connect_strings' ) );

		$this->views = array(
			'settings'      => __( 'Settings', 'wpconsent-cookies-banner-privacy-suite' ),
			'cookies'       => __( 'Cookies', 'wpconsent-cookies-banner-privacy-suite' ),
			'languages'     => __( 'Languages', 'wpconsent-cookies-banner-privacy-suite' ),
			'import_export' => __( 'Import/Export', 'wpconsent-cookies-banner-privacy-suite' ),
			'advanced'      => __( 'Advanced', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}

	/**
	 * Add the strings for the connect page to the JS object.
	 *
	 * @param array $data The localized data we already have.
	 *
	 * @return array
	 */
	public function add_connect_strings( $data ) {
		$data['oops']                  = esc_html__( 'Oops!', 'wpconsent-cookies-banner-privacy-suite' );
		$data['ok']                    = esc_html__( 'OK', 'wpconsent-cookies-banner-privacy-suite' );
		$data['almost_done']           = esc_html__( 'Almost Done', 'wpconsent-cookies-banner-privacy-suite' );
		$data['plugin_activate_btn']   = esc_html__( 'Activate', 'wpconsent-cookies-banner-privacy-suite' );
		$data['server_error']          = esc_html__( 'Unfortunately there was a server connection error.', 'wpconsent-cookies-banner-privacy-suite' );
		$data['icons']                 = array(
			'checkmark' => wpconsent_get_icon( 'checkmark', 88, 88, '0 0 130.2 130.2' ),
		);
		$data['records_of_consent']    = array(
			'title' => esc_html__( 'Records of Consent is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
			'text'  => esc_html__( 'Upgrade to PRO today and start keeping logs for all visitors that give consent. 100% self-hosted on your WordPress site.', 'wpconsent-cookies-banner-privacy-suite' ),
			'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite', 'settings', 'consent-logs' ),
		);
		$data['scanner']               = array(
			'title' => esc_html__( 'Automatic Scanning is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
			'text'  => esc_html__( 'Upgrade to PRO today and schedule automatic website scanning to stay up to date with your website\'s consent needs.', 'wpconsent-cookies-banner-privacy-suite' ),
			'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite', 'settings', 'consent-logs' ),
		);
		$data['custom_scripts_export'] = array(
			'title' => esc_html__( 'Custom Scripts Export is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
			'text'  => esc_html__( 'Upgrade to WPConsent PRO today and easily manage custom scripts and iframes. Take full control and block any scripts and iframes from loading until users give consent.', 'wpconsent-cookies-banner-privacy-suite' ),
			'url'   => wpconsent_utm_url( 'https://wpconsent.com/lite', 'import-export', 'custom-scripts-export' ),
		);

		return $data;
	}

	/**
	 * The page output based on the view.
	 *
	 * @return void
	 */
	public function output_content() {
		if ( method_exists( $this, 'output_view_' . $this->view ) ) {
			call_user_func( array( $this, 'output_view_' . $this->view ) );
		}
	}

	/**
	 * For this page we output a menu.
	 *
	 * @return void
	 */
	public function output_header_bottom() {
		?>
		<ul class="wpconsent-admin-tabs">
			<?php
			foreach ( $this->views as $slug => $label ) {
				$class = $this->view === $slug ? 'active' : '';
				?>
				<li>
					<a href="<?php echo esc_url( $this->get_view_link( $slug ) ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				</li>
			<?php } ?>
		</ul>
		<?php
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

		// Handle import.
		if ( isset( $_POST['wpconsent_import'] ) ) {
			$this->handle_import();
			return;
		}

		// Handle export.
		if ( isset( $_POST['wpconsent_export'] ) ) {
			$this->handle_export();
			return;
		}

		// Save the settings based on current view.
		if ( $this->view === 'advanced' ) {
			$settings = array(
				'uninstall_data' => isset( $_POST['uninstall_data'] ) ? 1 : 0,
			);
		} else {
			// Update main settings (for settings view)
			$settings = array(
				'enable_consent_banner'             => isset( $_POST['enable_consent_banner'] ) ? 1 : 0,
				'cookie_policy_page'                => isset( $_POST['cookie_policy_page'] ) ? intval( $_POST['cookie_policy_page'] ) : 0,
				'enable_script_blocking'            => ( isset( $_POST['enable_script_blocking'] ) && isset( $_POST['enable_consent_banner'] ) ) ? 1 : 0,
				'google_consent_mode'               => ( isset( $_POST['google_consent_mode'] ) && isset( $_POST['google_consent_mode'] ) ) ? 1 : 0,
				'enable_consent_floating'           => isset( $_POST['enable_consent_floating'] ) ? 1 : 0,
				'default_allow'                     => isset( $_POST['default_allow'] ) ? 1 : 0,
				'manual_toggle_services'            => isset( $_POST['manual_toggle_services'] ) ? 1 : 0,
				'consent_duration'                  => isset( $_POST['consent_duration'] ) ? intval( $_POST['consent_duration'] ) : 30,
				'enable_content_blocking'           => isset( $_POST['enable_content_blocking'] ) ? 1 : 0,
				'content_blocking_services'         => isset( $_POST['content_blocking_services'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['content_blocking_services'] ) ) : array(),
				'content_blocking_placeholder_text' => isset( $_POST['content_blocking_placeholder_text'] ) ? sanitize_text_field( wp_unslash( $_POST['content_blocking_placeholder_text'] ) ) : 'Click here to accept {category} cookies and load this content',
			);
		}

		wpconsent()->settings->bulk_update_options( $settings );

		wp_safe_redirect( $this->get_page_action_url() );
		exit;
	}

	/**
	 * Handle the export of settings.
	 *
	 * @return void
	 */
	protected function handle_export() {
		$export_all_settings   = isset( $_POST['export_all_settings'] ) ? true : false;  // phpcs:ignore WordPress.Security.NonceVerification
		$export_banner_design  = isset( $_POST['export_banner_design'] ) ? true : false;  // phpcs:ignore WordPress.Security.NonceVerification
		$export_cookie_data    = isset( $_POST['export_cookie_data'] ) ? true : false;  // phpcs:ignore WordPress.Security.NonceVerification
		$export_custom_scripts = isset( $_POST['export_custom_scripts'] ) ? true : false;  // phpcs:ignore WordPress.Security.NonceVerification

		$export_data = array();

		if ( $export_all_settings || $export_banner_design ) {
			$all_options = wpconsent()->settings->get_options();
		}

		if ( $export_all_settings ) {
			$export_data['settings'] = $this->get_settings_for_export( $all_options );
		}

		if ( $export_banner_design ) {
			$export_data['banner_design'] = $this->get_banner_design_for_export( $all_options );
		}

		if ( $export_cookie_data ) {
			$export_data['cookies'] = $this->get_cookie_data_for_export();
		}

		if ( $export_custom_scripts ) {
			$export_data['custom_scripts'] = $this->get_custom_scripts_for_export();
		}

		$this->output_export_file( $export_data );
	}

	/**
	 * Get settings for export.
	 *
	 * @param array $all_options All plugin options.
	 * @return array
	 */
	protected function get_settings_for_export( $all_options ) {
		$settings = $all_options;

		unset( $settings['manual_scan_pages'] );
		unset( $settings['onboarding_completed'] );
		unset( $settings['has_auto_populated_pages'] );
		unset( $settings['banner_logo'] );

		// Remove banner settings as they are handled separately.
		foreach ( $this->get_banner_settings_keys() as $setting ) {
			unset( $settings[ $setting ] );
		}

		return $settings;
	}

	/**
	 * Get banner design settings for export.
	 *
	 * @param array $all_options All plugin options.
	 * @return array
	 */
	protected function get_banner_design_for_export( $all_options ) {
		$banner_data = array();

		foreach ( $this->get_banner_settings_keys() as $setting ) {
			if ( isset( $all_options[ $setting ] ) ) {
				// If this is the consent_floating_icon setting and it's a URL (custom image)
				if ( $setting === 'consent_floating_icon' && filter_var( $all_options[ $setting ], FILTER_VALIDATE_URL ) ) {
					$banner_data[ $setting ] = 'preferences';
				} else {
					$banner_data[ $setting ] = $all_options[ $setting ];
				}
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
		$export_data = array();

		$categories = wpconsent()->cookies->get_categories();
		foreach ( $categories as $category_slug => $category ) {
			$category_id = $category['id'];

			$cookies  = wpconsent()->cookies->get_cookies_by_category( $category_id );
			$services = wpconsent()->cookies->get_services_by_category( $category_id );

			$category_data = array(
				'name'        => $category['name'],
				'description' => $category['description'],
				'required'    => $category['required'],
				'cookies'     => array(),
				'services'    => array(),
			);

			foreach ( $cookies as $cookie ) {
				if ( count( $cookie['categories'] ) === 1 && $cookie['categories'][0] === $category_id ) {
					$category_data['cookies'][] = $this->get_cookie_export_data( $cookie );
				}
			}

			foreach ( $services as $service ) {
				$service_data = array(
					'id'          => $service['id'],
					'name'        => $service['name'],
					'description' => $service['description'],
					'service_url' => $service['service_url'],
					'cookies'     => array(),
				);

				foreach ( $cookies as $cookie ) {
					if ( in_array( $service['id'], $cookie['categories'] ) ) {
						$service_data['cookies'][] = $this->get_cookie_export_data( $cookie );
					}
				}

				$category_data['services'][] = $service_data;
			}
			$export_data[ $category_slug ] = $category_data;
		}

		return $export_data;
	}

	/**
	 * Get custom scripts for export.
	 *
	 * @return void
	 */
	protected function get_custom_scripts_for_export() {
		// This method is meant to be overridden by child classes.
	}

	/**
	 * Get cookie export data.
	 *
	 * @param array $cookie Cookie data.
	 * @return array
	 */
	protected function get_cookie_export_data( $cookie ) {
		return array(
			'id'          => $cookie['id'],
			'name'        => $cookie['name'],
			'cookie_id'   => $cookie['cookie_id'],
			'description' => $cookie['description'],
			'duration'    => $cookie['duration'],
			'auto_added'  => $cookie['auto_added'],
		);
	}

	/**
	 * Get banner settings keys.
	 *
	 * @return array
	 */
	protected function get_banner_settings_keys() {
		return array(
			'banner_layout',
			'banner_position',
			'banner_background_color',
			'banner_text_color',
			'banner_font_size',
			'banner_button_size',
			'banner_button_corner',
			'banner_button_type',
			'banner_accept_bg',
			'banner_accept_color',
			'banner_cancel_bg',
			'banner_cancel_color',
			'banner_preferences_bg',
			'banner_preferences_color',
			'hide_powered_by',
			'banner_message',
			'accept_button_text',
			'accept_button_enabled',
			'button_order',
			'preferences_button_text',
			'cancel_button_text',
			'cancel_button_enabled',
			'preferences_button_enabled',
			'preferences_panel_title',
			'preferences_panel_description',
			'cookie_policy_title',
			'cookie_policy_text',
			'save_preferences_button_text',
			'close_button_text',
			'disable_close_button',
			'cookie_table_header_name',
			'cookie_table_header_description',
			'cookie_table_header_duration',
			'cookie_table_header_category',
			'consent_floating_icon',
		);
	}

	/**
	 * Output export file.
	 *
	 * @param array $export_data Data to export.
	 *
	 * @return void
	 */
	protected function output_export_file( $export_data ) {
		$filename = 'wpconsent-export-' . gmdate( 'Y-m-d-H-i' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Expires: 0' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle the import of settings.
	 *
	 * @return void
	 */
	protected function handle_import() {
		// Check if file was uploaded.
		if ( ! isset( $_FILES['import_file'] ) || ! isset( $_FILES['import_file']['tmp_name'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification
			wp_die(
				esc_html__( 'No file was uploaded.', 'wpconsent-cookies-banner-privacy-suite' ),
				esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
				array(
					'response' => 400,
				)
			);
		}

		// Validate file extension.
		$ext = '';
		if ( isset( $_FILES['import_file']['name'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification
			$ext = strtolower( pathinfo( sanitize_text_field( $_FILES['import_file']['name'] ), PATHINFO_EXTENSION ) );  // phpcs:ignore WordPress.Security.NonceVerification
		}

		if ( 'json' !== $ext ) {
			wp_die(
				esc_html__( 'Please upload a valid .json export file.', 'wpconsent-cookies-banner-privacy-suite' ),
				esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
				array(
					'response' => 400,
				)
			);
		}

		// Validate MIME type.
		$tmp_name  = isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['import_file']['tmp_name'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() breaks upload on Windows.
		$file_name = isset( $_FILES['import_file']['name'] ) ? sanitize_text_field( $_FILES['import_file']['name'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification

		// Define valid MIME types for JSON files.
		$valid_mime_types = array(
			'application/json',
			'text/plain', // Some servers may identify JSON files as text/plain.
			'text/json',
		);

		// Check MIME type using WordPress functions first.
		$wp_filetype = wp_check_filetype(
			$file_name,
			array(
				'json' => 'application/json',
				'txt'  => 'text/plain',
			)
		);

		if ( empty( $wp_filetype['type'] ) || ! in_array( $wp_filetype['type'], $valid_mime_types, true ) ) {
			wp_die(
				esc_html__( 'The uploaded file is not a valid JSON file.', 'wpconsent-cookies-banner-privacy-suite' ),
				esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
				array(
					'response' => 400,
				)
			);
		}

		// Additional check using finfo if available for more security.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $tmp_name );
			finfo_close( $finfo );

			if ( ! in_array( $mime, $valid_mime_types, true ) ) {
				wp_die(
					esc_html__( 'The uploaded file is not a valid JSON file.', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
					array(
						'response' => 400,
					)
				);
			}
		}

		// Initialize WordPress Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// Get file contents.
		$file_contents = $wp_filesystem->get_contents( $tmp_name );

		// Validate JSON syntax.
		$import_data = json_decode( $file_contents, true );
		$json_error  = json_last_error();

		if ( JSON_ERROR_NONE !== $json_error ) {
			wp_die(
				sprintf(
				/* translators: %s: JSON error message */
					esc_html__( 'Invalid JSON syntax: %s', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html( json_last_error_msg() )
				),
				esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
				array(
					'response' => 400,
				)
			);
		}

		// Validate expected data structure.
		if ( empty( $import_data ) || ! is_array( $import_data ) ) {
			wp_die(
				esc_html__( 'Import data cannot be processed.', 'wpconsent-cookies-banner-privacy-suite' ),
				esc_html__( 'Error', 'wpconsent-cookies-banner-privacy-suite' ),
				array(
					'response' => 400,
				)
			);
		}

		$this->import_settings( $import_data );
		$this->import_banner_design( $import_data );
		$this->import_cookies( $import_data );
		$this->import_custom_scripts( $import_data );

		wp_safe_redirect( add_query_arg( 'message', 'import_success', $this->get_page_action_url() ) );
		exit;
	}

	/**
	 * Import settings from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_settings( $import_data ) {
		if ( isset( $import_data['settings'] ) ) {
			$settings = $import_data['settings'];
			// Let's go through the settings and sanitize all the values.
			foreach ( $settings as $key => $value ) {
				if ( is_array( $value ) ) {
					$settings[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					$settings[ $key ] = sanitize_text_field( $value );
				}
			}
			wpconsent()->settings->bulk_update_options( $settings );

			// Handle cookie policy page after settings are updated.
			if ( isset( $import_data['settings']['cookie_policy_page'] ) && 0 !== $import_data['settings']['cookie_policy_page'] ) {
				// Translators: This is a default text for the cookie policy generated by WPConsent. %1$s is the opening anchor tag, %2$s is the closing anchor tag.
				$default_cookie_policy_text = esc_html__( 'This page provides comprehensive information about how we use cookies on our website to enhance your browsing experience, improve website performance, and deliver personalized content. Cookies are small text files that are stored on your device when you visit our site. They help us understand how visitors interact with our website, allowing us to offer a smoother and more efficient user experience. In the table below, you will find detailed information about each type of cookie we use, their purpose, and how long they remain on your device. We are committed to respecting your privacy and providing transparency about the data we collect through cookies. For more information on how we handle your personal data, please see our %1$sPrivacy Policy.%2$s', 'wpconsent-cookies-banner-privacy-suite' );
				$privacy_policy             = get_privacy_policy_url();
				$default_cookie_policy_text = sprintf( $default_cookie_policy_text, '<a href="' . esc_url( $privacy_policy ) . '">', '</a>' );

				// Add shortcode.
				$shortcode    = '<br>[wpconsent_cookie_policy]';
				$page_content = '<p>' . $default_cookie_policy_text . '</p>';

				// Let's check if the site is using the block editor for pages.
				if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( 'page' ) ) {
					// Let's wrap the page content in a paragraph block.
					$page_content = '<!-- wp:paragraph -->' . $page_content . '<!-- /wp:paragraph -->';
					// Let's add the shortcode block.
					$page_content .= '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->';
				} else {
					// Let's add the shortcode.
					$page_content .= $shortcode;
				}

				// Create new cookie policy page.
				$page_id = wp_insert_post(
					array(
						'post_title'   => esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ),
						'post_content' => $page_content,
						'post_status'  => 'publish',
						'post_type'    => 'page',
					)
				);

				if ( $page_id ) {
					// Update the cookie policy page ID in settings.
					wpconsent()->settings->bulk_update_options(
						array(
							'cookie_policy_page' => $page_id,
						)
					);
				} else {
					wpconsent()->settings->bulk_update_options(
						array(
							'cookie_policy_page' => 0,
						)
					);
				}
			}
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
		if ( isset( $import_data['banner_design'] ) ) {
			// Let's go through the banner design settings and sanitize all the values.
			$banner_design = $import_data['banner_design'];
			foreach ( $banner_design as $key => $value ) {
				if ( is_array( $value ) ) {
					$banner_design[ $key ] = array_map( 'wp_kses_post', $value );
				} else {
					$banner_design[ $key ] = wp_kses_post( $value );
				}
			}

			wpconsent()->settings->bulk_update_options( $banner_design );
		}
	}

	/**
	 * Import cookies from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_cookies( $import_data ) {
		if ( isset( $import_data['cookies'] ) ) {
			foreach ( $import_data['cookies'] as $category_slug => $category_data ) {
				// Check if category exists, if not create it.
				$category = get_term_by( 'slug', $category_slug, wpconsent()->cookies->taxonomy );
				if ( ! $category ) {
					$category_id = wpconsent()->cookies->add_category(
						sanitize_text_field( $category_data['name'] ),
						wp_kses_post( $category_data['description'] )
					);
					if ( is_wp_error( $category_id ) ) {
						continue;
					}
				} else {
					$category_id = $category->term_id;

					// Update category name and description if it already exists
					wpconsent()->cookies->update_category(
						$category_id,
						sanitize_text_field( $category_data['name'] ),
						wp_kses_post( $category_data['description'] )
					);
				}

				// Update category meta.
				update_term_meta( $category_id, 'wpconsent_required', intval( $category_data['required'] ) );

				// Allow child classes to import additional category data.
				$this->import_category_data( $category_id, $category_data );

				// Import cookies directly attached to category.
				if ( isset( $category_data['cookies'] ) && is_array( $category_data['cookies'] ) ) {
					foreach ( $category_data['cookies'] as $cookie_data ) {
						$post_id = wpconsent()->cookies->add_cookie(
							sanitize_text_field( $cookie_data['cookie_id'] ),
							sanitize_text_field( $cookie_data['name'] ),
							wp_kses_post( $cookie_data['description'] ),
							absint( $category_id ),
							isset( $cookie_data['duration'] ) ? sanitize_text_field( $cookie_data['duration'] ) : ''
						);

						// Allow child classes to import additional cookie data.
						$this->import_cookie_data( $post_id, $cookie_data );
					}
				}

				// Import services and their cookies.
				if ( isset( $category_data['services'] ) && is_array( $category_data['services'] ) ) {
					foreach ( $category_data['services'] as $service_data ) {
						// Add new service under the category.
						$service_id = wpconsent()->cookies->add_service(
							sanitize_text_field( $service_data['name'] ),
							absint( $category_id ),
							isset( $service_data['description'] ) ? wp_kses_post( $service_data['description'] ) : '',
							isset( $service_data['service_url'] ) ? esc_url( $service_data['service_url'] ) : ''
						);

						if ( ! $service_id ) {
							continue;
						}

						// Allow child classes to import additional service data.
						$this->import_service_data( $service_id, $service_data );

						// Import cookies for this service.
						if ( isset( $service_data['cookies'] ) && is_array( $service_data['cookies'] ) ) {
							foreach ( $service_data['cookies'] as $cookie_data ) {
								$post_id = wpconsent()->cookies->add_cookie(
									sanitize_text_field( $cookie_data['cookie_id'] ),
									sanitize_text_field( $cookie_data['name'] ),
									wp_kses_post( $cookie_data['description'] ),
									absint( $service_id ),
									isset( $cookie_data['duration'] ) ? sanitize_text_field( $cookie_data['duration'] ) : ''
								);

								// Allow child classes to import additional cookie data.
								$this->import_cookie_data( $post_id, $cookie_data );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Import additional category data. This is a hook for child classes to extend.
	 *
	 * @param int   $category_id The category ID.
	 * @param array $category_data The category data.
	 *
	 * @return void
	 */
	protected function import_category_data( $category_id, $category_data ) {
		// This method is meant to be overridden by child classes.
	}

	/**
	 * Import additional cookie data. This is a hook for child classes to extend.
	 *
	 * @param int   $post_id The cookie post ID.
	 * @param array $cookie_data The cookie data.
	 *
	 * @return void
	 */
	protected function import_cookie_data( $post_id, $cookie_data ) {
		// This method is meant to be overridden by child classes.
	}

	/**
	 * Import additional service data. This is a hook for child classes to extend.
	 *
	 * @param int   $service_id The service ID.
	 * @param array $service_data The service data.
	 *
	 * @return void
	 */
	protected function import_service_data( $service_id, $service_data ) {
		// This method is meant to be overridden by child classes.
	}

	/**
	 * Import custom scripts from import data.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return void
	 */
	protected function import_custom_scripts( $import_data ) {
		// This method is meant to be overridden by child classes.
	}

	/**
	 * Output the settings view.
	 *
	 * @return void
	 */
	public function output_view_settings() {
		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post">
			<?php
			$this->metabox(
				esc_html__( 'License', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_license_key_content()
			);
			$this->metabox(
				__( 'Cookies Configuration', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_settings_metabox()
			);
			wp_nonce_field(
				'wpconsent_save_settings',
				'wpconsent_save_settings_nonce'
			);
			?>
			<div class="wpconsent-submit">
				<button type="submit" name="save_changes" class="wpconsent-button wpconsent-button-primary">
					<?php esc_html_e( 'Save Changes', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</button>
			</div>
		</form>
		<?php
	}


	/**
	 * Get the license key input.
	 *
	 * @return string
	 */
	public function get_license_key_field() {
		ob_start();
		?>
		<div class="wpconsent-metabox-form wpconsent-license-key-container">
			<p><?php esc_html_e( 'You\'re using WPConsent Lite - no license needed. Enjoy!', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				🙂
			</p>
			<p>
				<?php
				printf(
				// Translators: %1$s - Opening anchor tag, do not translate. %2$s - Closing anchor tag, do not translate.
					esc_html__( 'To unlock more features consider %1$supgrading to PRO%2$s.', 'wpconsent-cookies-banner-privacy-suite' ),
					'<strong><a href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'settings-license', 'upgrading-to-pro' ) ) . '" target="_blank" rel="noopener noreferrer">',
					'</a></strong>'
				)
				?>
			</p>
			<hr>
			<p><?php esc_html_e( 'Already purchased? Simply enter your license key below to enable WPConsent PRO!', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
			<p>
				<input type="password" class="wpconsent-input-text" id="wpconsent-settings-upgrade-license-key" placeholder="<?php esc_attr_e( 'Paste license key here', 'wpconsent-cookies-banner-privacy-suite' ); ?>" value="">
				<button type="button" class="wpconsent-button" id="wpconsent-settings-connect-btn">
					<?php esc_html_e( 'Verify Key', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</button>
			</p>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the license key content.
	 *
	 * @return string
	 */
	public function get_license_key_content() {
		ob_start();

		$this->metabox_row(
			esc_html__( 'License Key', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_license_key_field(),
			'wpconsent-setting-license-key'
		);

		return ob_get_clean();
	}

	/**
	 * Get the settings metabox.
	 *
	 * @return string
	 */
	public function get_settings_metabox() {

		ob_start();

		$this->metabox_row(
			esc_html__( 'Consent Banner', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'enable_consent_banner' ),
				'enable_consent_banner',
				esc_html__( 'Enable displaying the consent banner on your website.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'enable_consent_banner'
		);

		$this->metabox_row(
			esc_html__( 'Script Blocking', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'enable_script_blocking' ),
				'enable_script_blocking',
				sprintf(
				// translators: %1$s is an opening link tag, %2$s is a closing link tag.
					esc_html__( 'Prevent known scripts from adding cookies before consent is given. %1$sLearn More%2$s', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a target="_blank" rel="noopener noreferrer" href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs/automatic-script-blocking', 'settings', 'script-blocking' ) ) . '">',
					'</a>'
				)
			) . $this->help_icon( __( 'Script blocking is not available without displaying the banner', 'wpconsent-cookies-banner-privacy-suite' ), false ),
			'enable_script_blocking'
		);

		$this->metabox_row(
			esc_html__( 'Google Consent Mode', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'google_consent_mode', true ),
				'google_consent_mode',
				sprintf(
				// translators: %1$s is an opening link tag, %2$s is a closing link tag.
					esc_html__( 'Use services like Google Analytics and Google ads without cookies until consent is given. %1$sLearn More%2$s', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a target="_blank" rel="noopener noreferrer" href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs/google-consent-mode', 'settings', 'google-consent-mode' ) ) . '">',
					'</a>'
				)
			) . $this->help_icon( __( 'Google Consent Mode will not be loaded if the banner is disabled.', 'wpconsent-cookies-banner-privacy-suite' ), false ),
			'google_consent_mode'
		);

		$this->metabox_row(
			esc_html__( 'Settings Button', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'enable_consent_floating' ),
				'enable_consent_floating',
				esc_html__( 'Show a floating button to manage consent after the banner is dismissed.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'enable_consent_floating'
		);

		$this->metabox_row(
			esc_html__( 'Default Allow', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'default_allow' ),
				'default_allow',
				sprintf(
				// translators: %1$s is an opening link tag, %2$s is a closing link tag.
					esc_html__( 'Enable this to only block scripts/cookies if the user rejects them. %1$sLearn More%2$s', 'wpconsent-cookies-banner-privacy-suite' ),
					'<a target="_blank" rel="noopener noreferrer" href="' . esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs/default-allow', 'settings', 'default-allow' ) ) . '">',
					'</a>'
				)
			),
			'default_allow'
		);

		$this->metabox_row(
			esc_html__( 'Toggle Services', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'manual_toggle_services' ),
				'manual_toggle_services',
				esc_html__( 'Allow site visitors to toggle individual services from the preferences panel.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'manual_toggle_services'
		);

		$this->metabox_row_separator();
		$this->metabox_row(
			esc_html__( 'Cookie Categories', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_cookie_categories_input()
		);
		$this->metabox_row_separator();
		$this->metabox_row(
			esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_cookie_policy_input(),
			'',
			'',
			'',
			'',
			false,
			'cookie-policy-input'
		);
		$this->metabox_row_separator();
		$this->metabox_row(
			esc_html__( 'Content Blocking', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'enable_content_blocking' ),
				'enable_content_blocking',
				esc_html__( 'Block 3rd party services that use iframes from being loaded before consent is given.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'enable_content_blocking'
		);
		$this->metabox_row(
			esc_html__( 'Content to Block', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_content_blocking_input(),
			'',
			'#enable_content_blocking',
			'1',
			esc_html__( 'Choose which content providers to automatically block.', 'wpconsent-cookies-banner-privacy-suite' ),
			false,
			'content_blocking'
		);
		$this->metabox_row(
			esc_html__( 'Placeholder Button', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_input_text(
				'content_blocking_placeholder_text',
				wpconsent()->settings->get_option( 'content_blocking_placeholder_text', 'Click here to accept {category} cookies and load this content' )
			),
			'content_blocking_placeholder_text',
			'#enable_content_blocking',
			'1',
			// Translators: %s is the {category} tag wrapped in a code tag.
			sprintf(
				esc_html__( 'Customize the text shown on the placeholder button. Use %s to insert the cookie category name.', 'wpconsent-cookies-banner-privacy-suite' ),
				'<code>{category}</code>'
			)
		);
		$this->metabox_row_separator();
		$this->metabox_row(
			esc_html__( 'Consent Duration', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_input_number( 'consent_duration', wpconsent()->settings->get_option( 'consent_duration', 30 ) ),
			'consent_duration',
			'',
			'',
			esc_html__( 'The duration of the consent given by the user (in days).', 'wpconsent-cookies-banner-privacy-suite' )
		);
		$this->metabox_row_separator();
		$this->records_of_consent_input();
		$this->metabox_row_separator();
		$this->automatic_scanning_input();

		return ob_get_clean();
	}

	/**
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function records_of_consent_input() {
		$this->metabox_row(
			esc_html__( 'Consent Logs', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				false,
				'wpconsent-records-of-consent-lite',
				esc_html__( 'Enable keeping records of consent for all visitors that give consent.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'wpconsent-records-of-consent-lite',
			'',
			'',
			'',
			true
		);
	}

	/**
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function automatic_scanning_input() {
		$this->metabox_row(
			esc_html__( 'Auto Scanning', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				false,
				'wpconsent-auto-scanner-lite',
				esc_html__( 'Enable automatic scanning of consent compliance in the background.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'wpconsent-auto-scanner-lite',
			'',
			'',
			'',
			true
		);
		$this->metabox_row(
			esc_html__( 'Scan Interval', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->select(
				'wpconsent-auto-scanner-interval-lite',
				array(
					'1'  => esc_html__( 'Daily', 'wpconsent-cookies-banner-privacy-suite' ),
					'7'  => esc_html__( 'Weekly', 'wpconsent-cookies-banner-privacy-suite' ),
					'30' => esc_html__( 'Monthly', 'wpconsent-cookies-banner-privacy-suite' ),
				)
			),
			'wpconsent-auto-scanner-interval-lite',
			'',
			'',
			esc_html__( 'Choose how often to automatically scan your website for compliance.', 'wpconsent-cookies-banner-privacy-suite' ),
			true
		);
	}

	/**
	 * Manage cookie categories.
	 *
	 * @return string
	 */
	public function get_cookie_categories_input() {
		ob_start();
		$categories = wpconsent()->cookies->get_categories();
		?>
		<div class="wpconsent-buttons-config-input wpconsent-manage-cookie-categories">
			<div class="wpconsent-button-row wpconsent-buttons-list-header">
				<div class="wpconsent-button-label-column wpconsent-button-label-header">
					<?php echo esc_html__( 'Title', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</div>
				<div class="wpconsent-button-text-column wpconsent-button-text-header">
					<?php echo esc_html__( 'Description', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</div>
				<div class="wpconsent-button-enabled-column wpconsent-button-enabled-header">
					<?php echo esc_html__( 'Action', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</div>
			</div>
			<?php
			$default_categories = wpconsent()->cookies->get_default_categories();
			foreach ( $categories as $slug => $category ) {
				?>
				<div class="wpconsent-button-row" data-button-id="<?php echo esc_attr( $category['id'] ); ?>">
					<div class="wpconsent-button-label-column">
						<?php echo esc_html( $category['name'] ); ?>
					</div>
					<div class="wpconsent-button-text-column">
						<?php echo esc_html( $category['description'] ); ?>
					</div>
					<div class="wpconsent-button-enabled-column">
						<textarea class="wpconsent-hidden wpconsent-category-description" readonly><?php echo esc_textarea( $category['description'] ); ?></textarea>
						<button class="wpconsent-button wpconsent-button-just-icon wpconsent-edit-category" type="button">
							<?php wpconsent_icon( 'edit', 15, 16 ); ?>
						</button>
						<?php if ( ! array_key_exists( $slug, $default_categories ) ) : ?>
							<button class="wpconsent-button wpconsent-button-just-icon wpconsent-delete-category" data-button-id="<?php echo esc_attr( $category['id'] ); ?>" type="button">
								<?php wpconsent_icon( 'delete', 14, 16 ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<?php
			}
			?>
			<div class="wpconsent-actions-row">
				<button class="wpconsent-button wpconsent-button-text" type="button" id="wpconsent-add-category">
					<?php echo esc_html__( '+ Add New Category', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</button>
			</div>
		</div>
		<div class="wpconsent-input-area-description">
			<?php esc_html_e( 'Customize the information for cookie categories.', 'wpconsent-cookies-banner-privacy-suite' ); ?>
		</div>
		<script type="text/template" id="wpconsent-new-category-row">
			<div class="wpconsent-button-row" data-button-id="{{id}}">
				<div class="wpconsent-button-label-column">
					{{name}}
				</div>
				<div class="wpconsent-button-text-column">
					{{required}}
				</div>
				<div class="wpconsent-button-enabled-column">
					<textarea class="wpconsent-hidden wpconsent-category-description" readonly>{{description}}</textarea>
					<button class="wpconsent-button wpconsent-button-just-icon wpconsent-edit-category" type="button">
						<?php wpconsent_icon( 'edit', 15, 16 ); ?>
					</button>
					<button class="wpconsent-button wpconsent-button-just-icon wpconsent-delete-category" data-button-id="{{id}}" type="button">
						<?php wpconsent_icon( 'delete', 14, 16 ); ?>
					</button>
				</div>
			</div>
		</script>
		<?php

		return ob_get_clean();
	}

	/**
	 * The cookies input accordion.
	 *
	 * @return string
	 */
	public function get_cookies_input() {
		ob_start();
		$categories = wpconsent()->cookies->get_categories();
		?>
		<div class="wpconsent-cookies-manager wpconsent-accordion">
			<?php
			foreach ( $categories as $category ) {
				$cookies = wpconsent()->cookies->get_cookies_by_category( $category['id'] );
				?>
				<div class="wpconsent-accordion-item">
					<div class="wpconsent-accordion-header">
						<h3><?php printf( '%s (%d)', esc_html( $category['name'] ), count( $cookies ) ); ?></h3>
						<button class="wpconsent-accordion-toggle">
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
					</div>
					<div class="wpconsent-accordion-content">
						<div class="wpconsent-cookie-category-description">
							<?php echo wp_kses_post( $category['description'] ); ?>
						</div>
						<div class="wpconsent-cookies-list">
							<div class="wpconsent-cookie-header">
								<div class="cookie-name"><?php esc_html_e( 'Cookie Name', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="cookie-id"><?php esc_html_e( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="cookie-desc"><?php esc_html_e( 'Description', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="cookie-duration"><?php esc_html_e( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="cookie-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
							</div>
							<?php
							if ( ! empty( $cookies ) ) {
								foreach ( $cookies as $cookie ) {
									// Let's skip here the cookies that are not associated with this category directly and are associated with a service.
									if ( ! in_array( $category['id'], $cookie['categories'], true ) ) {
										continue;
									}
									?>
									<div class="wpconsent-cookie-item">
										<div class="cookie-name"><?php echo esc_html( $cookie['name'] ); ?></div>
										<div class="cookie-id"><?php echo esc_html( $cookie['cookie_id'] ); ?></div>
										<div class="cookie-desc"><?php echo esc_html( $cookie['description'] ); ?></div>
										<div class="cookie-duration"><?php echo esc_html( $cookie['duration'] ); ?></div>
										<div class="cookie-actions">
											<button class="wpconsent-button-icon wpconsent-edit-cookie" type="button" data-cookie-id="<?php echo esc_attr( $cookie['id'] ); ?>">
												<?php wpconsent_icon( 'edit', 15, 16 ); ?>
											</button>
											<button class="wpconsent-button-icon wpconsent-delete-cookie" type="button" data-cookie-id="<?php echo esc_attr( $cookie['id'] ); ?>">
												<?php wpconsent_icon( 'delete', 14, 16 ); ?>
											</button>
										</div>
										<input type="hidden" class="wpconsent-cookie-id" value="<?php echo esc_attr( $cookie['cookie_id'] ); ?>">
									</div>
									<?php
								}
							}
							$services = wpconsent()->cookies->get_services_by_category( $category['id'] );
							foreach ( $services as $service ) {
								?>
								<div class="wpconsent-service-item" data-service-id="<?php echo absint( $service['id'] ); ?>">
									<div class="wpconsent-service-header">
										<div class="wpconsent-service-text">
											<div class="service-name"><?php echo esc_html( $service['name'] ); ?></div>
											<div class="service-desc"><?php echo esc_html( $service['description'] ); ?></div>
										</div>
										<div class="service-actions">
											<button class="wpconsent-button-icon wpconsent-edit-service" type="button" data-service-id="<?php echo absint( $service['id'] ); ?>">
												<?php wpconsent_icon( 'edit', 15, 16 ); ?>
											</button>
											<button class="wpconsent-button-icon wpconsent-delete-service" type="button" data-service-id="<?php echo absint( $service['id'] ); ?>">
												<?php wpconsent_icon( 'delete', 14, 16 ); ?>
											</button>
											<input type="hidden" class="wpconsent-service-id" value="<?php echo absint( $service['id'] ); ?>">
											<input type="hidden" class="wpconsent-service-url" value="<?php echo esc_url( $service['service_url'] ); ?>">
										</div>
									</div>
									<div class="wpconsent-cookies-list">
										<div class="wpconsent-cookie-header">
											<div class="cookie-name"><?php esc_html_e( 'Cookie Name', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
											<div class="cookie-id"><?php esc_html_e( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
											<div class="cookie-desc"><?php esc_html_e( 'Description', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
											<div class="cookie-duration"><?php esc_html_e( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
											<div class="cookie-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
										</div>
										<?php
										// We already loaded all the cookies for this category so we need to simply show here just the ones for this service.
										if ( ! empty( $cookies ) ) :
											foreach ( $cookies as $cookie_for_service ) {
												if ( ! in_array( $service['id'], $cookie_for_service['categories'], true ) ) {
													continue;
												}
												?>
												<div class="wpconsent-cookie-item">
													<div class="cookie-name"><?php echo esc_html( $cookie_for_service['name'] ); ?></div>
													<div class="cookie-id"><?php echo esc_html( $cookie_for_service['cookie_id'] ); ?></div>
													<div class="cookie-desc"><?php echo esc_html( $cookie_for_service['description'] ); ?></div>
													<div class="cookie-duration"><?php echo esc_html( $cookie_for_service['duration'] ); ?></div>
													<div class="cookie-actions">
														<button class="wpconsent-button-icon wpconsent-edit-cookie" type="button" data-cookie-id="<?php echo esc_attr( $cookie_for_service['id'] ); ?>">
															<?php wpconsent_icon( 'edit', 15, 16 ); ?>
														</button>
														<button class="wpconsent-button-icon wpconsent-delete-cookie" type="button" data-cookie-id="<?php echo esc_attr( $cookie_for_service['id'] ); ?>">
															<?php wpconsent_icon( 'delete', 14, 16 ); ?>
														</button>
													</div>
													<input type="hidden" class="wpconsent-cookie-id" value="<?php echo esc_attr( $cookie_for_service['cookie_id'] ); ?>">
													<input type="hidden" class="wpconsent-cookie-service" value="<?php echo esc_attr( $service['id'] ); ?>">
												</div>
												<?php
											}
										endif;
										?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
						<div class="wpconsent-actions-row">
							<button class="wpconsent-button wpconsent-button-primary wpconsent-add-cookie wpconsent-button-icon" type="button" data-category-id="<?php echo esc_attr( $category['id'] ); ?>" data-category-name="<?php echo esc_attr( $category['name'] ); ?>">
								<?php wpconsent_icon( 'cookie', 14, 14 ); ?>
								<?php esc_html_e( 'Add A Cookie', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
							<button class="wpconsent-button wpconsent-button-secondary wpconsent-add-service wpconsent-button-icon" type="button" data-category-id="<?php echo esc_attr( $category['id'] ); ?>" data-category-name="<?php echo esc_attr( $category['name'] ); ?>">
								<?php wpconsent_icon( 'plus', 14, 14, '0 -960 960 960' ); ?>
								<?php esc_html_e( 'Add A Service', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
							<?php echo $this->get_service_library_button( $category ); ?>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
		<script type="text/template" id="wpconsent-new-cookie-row">
			<div class="wpconsent-cookie-item">
				<div class="cookie-name">{{name}}</div>
				<div class="cookie-id">{{cookie_id}}</div>
				<div class="cookie-desc">{{description}}</div>
				<div class="cookie-duration">{{duration}}</div>
				<div class="cookie-actions">
					<button class="wpconsent-button-icon wpconsent-edit-cookie" type="button" data-cookie-id="{{id}}">
						<?php wpconsent_icon( 'edit', 15, 16 ); ?>
					</button>
					<button class="wpconsent-button-icon wpconsent-delete-cookie" type="button" data-cookie-id="{{id}}">
						<?php wpconsent_icon( 'delete', 14, 16 ); ?>
					</button>
				</div>
				<input type="hidden" class="wpconsent-cookie-id" value="{{cookie_id}}">
			</div>
		</script>
		<script type="text/template" id="wpconsent-new-service-row">
			<div class="wpconsent-service-item" data-service-id="{{id}}">
				<div class="wpconsent-service-header">
					<div class="wpconsent-service-text">
						<div class="service-name">{{name}}</div>
						<div class="service-desc">{{description}}</div>
					</div>
					<div class="service-actions">
						<button class="wpconsent-button-icon wpconsent-edit-service" type="button" data-service-id="{{id}}">
							<?php wpconsent_icon( 'edit', 15, 16 ); ?>
						</button>
						<button class="wpconsent-button-icon wpconsent-delete-service" type="button" data-service-id="{{id}}">
							<?php wpconsent_icon( 'delete', 14, 16 ); ?>
						</button>
						<input type="hidden" class="wpconsent-service-id" value="{{service_id}}">
						<input type="hidden" class="wpconsent-service-url" value="{{service_url}}">
					</div>
				</div>
				<div class="wpconsent-cookies-list">
					<div class="wpconsent-cookie-header">
						<div class="cookie-name"><?php esc_html_e( 'Cookie Name', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
						<div class="cookie-id"><?php esc_html_e( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
						<div class="cookie-desc"><?php esc_html_e( 'Description', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
						<div class="cookie-duration"><?php esc_html_e( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
						<div class="cookie-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
					</div>
				</div>
			</div>
		</script>
		<?php
		return ob_get_clean();
	}


	/**
	 * Get the service library button HTML.
	 *
	 * @param array $category The category data.
	 * @return string The button HTML.
	 */
	public function get_service_library_button( $category ) {
		ob_start();
		?>
		<button class="wpconsent-button wpconsent-button-secondary wpconsent-add-service-from-library-lite wpconsent-button-icon" type="button" data-category-id="<?php echo esc_attr( $category['id'] ); ?>" data-category-name="<?php echo esc_attr( $category['name'] ); ?>">
			<?php wpconsent_icon( 'library', 14, 14, '0 -960 960 960' ); ?>
			<?php esc_html_e( 'Add Service From Library', 'wpconsent-cookies-banner-privacy-suite' ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output the cookies view.
	 *
	 * @return void
	 */
	public function output_view_cookies() {
		$this->metabox(
			__( 'Cookies Configuration', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_cookies_input()
		);
	}

	/**
	 * Output the footer.
	 *
	 * @return void
	 */
	public function output_footer() {
		parent::output_footer();
		$footer_method = 'output_footer_' . $this->view;
		if ( method_exists( $this, $footer_method ) ) {
			call_user_func( array( $this, $footer_method ) );
		}
	}

	/**
	 * Output the footer for the settings view.
	 *
	 * @return void
	 */
	public function output_footer_settings() {
		?>
		<div class="wpconsent-modal" id="wpconsent-modal-add-category">
			<div class="wpconsent-modal-inner">
				<form action="" id="wpconsent-modal-form">
					<div class="wpconsent-modal-header">
						<h2><?php echo esc_html__( 'Add New Category', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
						<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="wpconsent-modal-content">
						<?php
						$this->metabox_row(
							esc_html__( 'Category Name', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'category_name' )
						);
						$this->metabox_row(
							esc_html__( 'Description', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_textarea( 'category_description' )
						);
						?>
						<div class="wpconsent-modal-buttons">
							<button class="wpconsent-button wpconsent-button-primary" type="submit">
								<?php echo esc_html__( 'Save', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
							<button class="wpconsent-button wpconsent-button-secondary" type="button">
								<?php echo esc_html__( 'Cancel', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
						</div>
					</div>
					<input type="hidden" name="action" value="wpconsent_add_category">
					<input type="hidden" name="category_id" value="">
					<?php wp_nonce_field( 'wpconsent_add_category', 'wpconsent_add_category_nonce' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the footer for the cookies view.
	 *
	 * @return void
	 */
	public function output_footer_cookies() {
		?>
		<div class="wpconsent-modal" id="wpconsent-modal-add-cookie">
			<div class="wpconsent-modal-inner">
				<form action="" id="wpconsent-modal-form">
					<div class="wpconsent-modal-header">
						<h2><?php echo esc_html__( 'Add New Cookie', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
						<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="wpconsent-modal-content">
						<?php
						$this->metabox_row(
							esc_html__( 'Service', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->select( 'cookie_service', $this->get_services_options() )
						);
						$this->metabox_row(
							esc_html__( 'Cookie Name', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'cookie_name' )
						);
						$this->metabox_row(
							esc_html__( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'cookie_id' )
						);
						$this->metabox_row(
							esc_html__( 'Description', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_textarea( 'cookie_description' )
						);
						$this->metabox_row(
							esc_html__( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'cookie_duration' )
						);
						?>
						<div class="wpconsent-modal-buttons">
							<button class="wpconsent-button wpconsent-button-primary" type="submit">
								<?php echo esc_html__( 'Save', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
							<button class="wpconsent-button wpconsent-button-secondary" type="button">
								<?php echo esc_html__( 'Cancel', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
						</div>
					</div>
					<input type="hidden" name="action" value="wpconsent_manage_cookie">
					<input type="hidden" name="post_id" value="">
					<input type="hidden" id="cookie_category" name="cookie_category" value="">
					<?php wp_nonce_field( 'wpconsent_manage_cookie', 'wpconsent_manage_cookie_nonce' ); ?>
				</form>
			</div>
		</div>
		<div class="wpconsent-modal" id="wpconsent-modal-add-service">
			<div class="wpconsent-modal-inner">
				<form action="">
					<div class="wpconsent-modal-header">
						<h2><?php echo esc_html__( 'Add New Service', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
						<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="wpconsent-modal-content">
						<?php
						$categories     = wpconsent()->cookies->get_categories();
						$select_options = array();
						foreach ( $categories as $category ) {
							$select_options[ $category['id'] ] = $category['name'];
						}

						$this->metabox_row(
							esc_html__( 'Category', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->select( 'service_category', $select_options )
						);
						$this->metabox_row(
							esc_html__( 'Service Name', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'service_name' )
						);
						$this->metabox_row(
							esc_html__( 'Description', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_textarea( 'service_description' )
						);
						$this->metabox_row(
							esc_html__( 'Privacy Policy URL', 'wpconsent-cookies-banner-privacy-suite' ),
							$this->get_input_text( 'service_url' )
						);
						?>
						<div class="wpconsent-modal-buttons">
							<button class="wpconsent-button wpconsent-button-primary" type="submit">
								<?php echo esc_html__( 'Save', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
							<button class="wpconsent-button wpconsent-button-secondary" type="button">
								<?php echo esc_html__( 'Cancel', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</button>
						</div>
					</div>
					<input type="hidden" name="action" value="wpconsent_manage_service">
					<input type="hidden" name="post_id" value="">
					<?php wp_nonce_field( 'wpconsent_manage_service', 'wpconsent_manage_service_nonce' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Grab an array of available services for the cookies.
	 *
	 * @return array
	 */
	public function get_services_options() {
		return array(
			'0' => esc_html__( 'No service', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}

	/**
	 * Get the cookie policy input.
	 *
	 * @return string
	 */
	public function get_cookie_policy_input() {
		ob_start();
		$selected_page_id = wpconsent()->settings->get_option( 'cookie_policy_page' );
		$selected_page    = $selected_page_id ? get_post( $selected_page_id ) : null;
		$pages_args       = array(
			'number'  => 20,
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		if ( ! empty( $selected_page_id ) ) {
			$pages_args['exclude'] = array( $selected_page_id );
		}
		// Let's pre-load 20 pages.
		$pages = get_pages( $pages_args );
		?>
		<div class="wpconsent-inline-select-group">
			<select id="cookie-policy-page" name="cookie_policy_page" class="wpconsent-choices wpconsent-page-search" data-placeholder="<?php esc_attr_e( 'Search for a page...', 'wpconsent-cookies-banner-privacy-suite' ); ?>" data-search="true" data-ajax-action="wpconsent_search_pages" data-ajax="true">
				<?php if ( $selected_page ) : ?>
					<option value="<?php echo esc_attr( $selected_page->ID ); ?>" selected>
						<?php echo esc_html( $selected_page->post_title ); ?>
					</option>
				<?php else : ?>
					<option value="0"><?php esc_html_e( 'Choose Page', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
				<?php endif; ?>
				<?php
				foreach ( $pages as $page ) {
					?>
					<option value="<?php echo esc_attr( $page->ID ); ?>">
						<?php echo esc_html( $page->post_title ); ?>
					</option>
					<?php
				}
				?>
			</select>
			<?php if ( $selected_page_id && $selected_page ) : ?>
				<a href="<?php echo esc_url( get_permalink( $selected_page_id ) ); ?>" class="wpconsent-button wpconsent-button-text" target="_blank">
					<?php
					esc_html_e( 'View Page', 'wpconsent-cookies-banner-privacy-suite' );
					?>
				</a>
			<?php endif; ?>
		</div>
		<div data-show-if-id="#cookie-policy-page" data-show-if-value="0" class="wpconsent-input-area-description">
			<button class="wpconsent-button wpconsent-button-secondary wpconsent-button-icon" id="wpconsent-create-cookie-policy-page" type="button">
				<?php
				wpconsent_icon( 'generate' );
				esc_html_e( 'Generate Cookie Policy Page', 'wpconsent-cookies-banner-privacy-suite' );
				?>
			</button>
		</div>
		<div class="wpconsent-input-area-description">
			<?php
			printf(
			// Translators: %s is the wpconsent_cookie_policy shortcode wrapped in code tags.
				esc_html__( 'Please select the page that serves as your cookie policy. Ensure that this page includes the %s shortcode. This shortcode is essential for automatically listing all the cookies configured in WPConsent.', 'wpconsent-cookies-banner-privacy-suite' ),
				'<code>[wpconsent_cookie_policy]</code>'
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output an interface where users can configure the languages they want to have in the banner.
	 *
	 * @return void
	 */
	public function output_view_languages() {

		?>
		<div class="wpconsent-blur-area">
			<?php
			$this->metabox(
				esc_html__( 'Language Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_language_settings_content()
			);
			wp_nonce_field(
				'wpconsent_save_language_settings',
				'wpconsent_save_language_settings_nonce'
			);
			?>
		</div>
		<?php
		echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Multilanguage is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
			'<p>' . esc_html__( 'Upgrade to WPConsent PRO today and easily manage content in multiple languages. Easily switch languages for the banner or directly integrate with popular translation plugins.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
			array(
				'text' => esc_html__( 'Upgrade to PRO and Unlock Languages', 'wpconsent-cookies-banner-privacy-suite' ),
				'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'languages-page', 'main' ) ),
			),
			array(
				'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
				'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'languages-page', 'features' ) ),
			)
		);
		?>
		<?php
	}

	/**
	 * Output a single language item in the language selector.
	 *
	 * @param string $locale The locale code.
	 * @param array  $language The language data array.
	 * @param bool   $is_default Whether this is the default language.
	 * @param bool   $is_enabled Whether this language is enabled.
	 *
	 * @return void
	 */
	protected function output_language_item( $locale, $language, $is_default, $is_enabled ) {
		$classes = array( 'wpconsent-language-item' );
		if ( $is_default ) {
			$classes[] = 'wpconsent-language-default';
		}
		if ( $is_enabled ) {
			$classes[] = 'wpconsent-language-enabled';
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
							<?php esc_html_e( 'Default', 'wpconsent-cookies-banner-privacy-suite' ); ?>
						</span>
					<?php endif; ?>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Get the language settings content.
	 *
	 * @return string
	 */
	public function get_language_settings_content() {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		ob_start();
		// Get all available languages.
		$available_languages = wp_get_available_translations();
		if ( ! $available_languages ) {
			$available_languages = array();
		}

		// Add English as it's not in the translations list.
		$available_languages['en_US'] = array(
			'language'     => 'en_US',
			'english_name' => 'English (United States)',
			'native_name'  => 'English (United States)',
		);

		// Get WordPress default language.
		$default_language  = get_locale();
		$enabled_languages = array( $default_language );

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
						esc_html__( 'Select the languages you want to make available for your content. The default language (%s) will be used for the current settings until you configure translations.', 'wpconsent-cookies-banner-privacy-suite' ),
						esc_html( isset( $available_languages[ $default_language ]['english_name'] ) ? $available_languages[ $default_language ]['english_name'] : 'English (United States)' )
					);
					?>
				</p>
				<p>
					<?php
					printf(
					// Translators: %s is the icon for the language switcher.
						esc_html__(
							'Easily switch between languages using the globe icon (%s) in the header of any WPConsent admin page.',
							'wpconsent-cookies-banner-privacy-suite'
						),
						wp_kses(
							wpconsent_get_icon( 'globe', 16, 16, '0 -960 960 960' ),
							wpconsent_get_icon_allowed_tags()
						)
					);
					?>
				</p>
			</div>
			<div class="wpconsent-language-selector">
				<div class="wpconsent-language-search">
					<input type="text"
					       class="wpconsent-input-text"
					       id="wpconsent-language-search"
					       placeholder="<?php esc_attr_e( 'Search languages...', 'wpconsent-cookies-banner-privacy-suite' ); ?>"
					>
				</div>
				<div class="wpconsent-language-setting-list" id="wpconsent-language-list">
					<?php
					// Output selected languages first.
					if ( ! empty( $selected_languages ) ) : ?>
						<div class="wpconsent-language-section">
							<div class="wpconsent-language-section-title">
								<?php esc_html_e( 'Selected Languages', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</div>
							<?php foreach ( $selected_languages as $locale => $language ) :
								$is_default = $locale === $default_language;
								$this->output_language_item( $locale, $language, $is_default, true );
							endforeach; ?>
						</div>
					<?php endif;

					// Output unselected languages.
					if ( ! empty( $unselected_languages ) ) : ?>
						<div class="wpconsent-language-section">
							<div class="wpconsent-language-section-title">
								<?php esc_html_e( 'Available Languages', 'wpconsent-cookies-banner-privacy-suite' ); ?>
							</div>
							<?php foreach ( $unselected_languages as $locale => $language ) :
								$is_default = $locale === $default_language;
								$this->output_language_item( $locale, $language, $is_default, false );
							endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		$this->metabox_row(
			esc_html__( 'Language Picker', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				true,
				'show_language_picker',
				esc_html__( 'Show a language picker in the consent banner', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'show_language_picker',
			'',
			'',
			esc_html__( 'This will show a globe icon in the header of the consent banner, allowing users to switch between languages just for the banner/preferences panel even if you do not use a translation plugin. If you are using a translation plugin the banner should automatically display the content in the selected language, if available.', 'wpconsent-cookies-banner-privacy-suite' )
		);

		return ob_get_clean();
	}

	/**
	 * The input to choose which content to be blocked.
	 *
	 * @return string
	 */
	public function get_content_blocking_input() {
		$content_blocking_services = wpconsent()->script_blocker->get_content_blocking_providers();
		$currently_blocking        = wpconsent()->settings->get_option( 'content_blocking_services', array() );

		ob_start();
		?>
		<div class="wpconsent-content-blocking-list">
			<?php
			foreach ( $content_blocking_services as $service_id => $service ) {
				$is_enabled = empty( $currently_blocking ) || in_array( $service_id, $currently_blocking, true );
				$this->metabox_row(
					esc_html( $service ),
					$this->get_checkbox_toggle(
						$is_enabled,
						'content_blocking_services[]',
						'',
						$service_id
					),
					'content_blocking_services_' . $service_id
				);
			}
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Output the import/export view.
	 *
	 * @return void
	 */
	public function output_view_import_export() {
		?>
		<form action="<?php echo esc_url( $this->get_page_action_url() ); ?>" method="post" enctype="multipart/form-data">
			<?php
			wp_nonce_field( 'wpconsent_save_settings', 'wpconsent_save_settings_nonce' );
			$this->metabox(
				__( 'Import Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_import_settings_content()
			);

			$this->metabox(
				__( 'Export Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_export_settings_content()
			);
			?>
		</form>
		<?php
	}

	/**
	 * Get the import settings content.
	 *
	 * @return string
	 */
	public function get_import_settings_content() {
		ob_start();
		?>
		<div class="wpconsent-input-area-description">
			<p><?php esc_html_e( 'Import your WPConsent settings from a JSON file. This will overwrite your current settings.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
		</div>

		<div class="wpconsent-metabox-form-row">
			<div class="wpconsent-file-upload">
				<input type="file" name="import_file" id="wpconsent-import-file" class="inputfile" data-multiple-caption="{count} files selected" accept=".json">
				<label for="wpconsent-import-file">
					<span class="wpconsent-file-field"><span class="placeholder"><?php esc_html_e( 'No file chosen', 'wpconsent-cookies-banner-privacy-suite' ); ?></span></span>
					<strong class="wpconsent-button wpconsent-button-secondary wpconsent-button-icon">
						<?php esc_html_e( 'Choose a file&hellip;', 'wpconsent-cookies-banner-privacy-suite' ); ?>
					</strong>
				</label>
			</div>
		</div>

		<div class="wpconsent-metabox-form-row">
			<button type="submit" name="wpconsent_import" class="wpconsent-button wpconsent-button-primary">
				<?php esc_html_e( 'Import Settings', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the export settings content.
	 *
	 * @return string
	 */
	public function get_export_settings_content() {
		ob_start();
		?>
		<div class="wpconsent-input-area-description">
			<p><?php esc_html_e( 'Export your WPConsent settings to a JSON file. You can use this file to import your settings on another site.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
		</div>

		<div class="wpconsent-export-options">
			<?php
			$this->metabox_row(
				esc_html__( 'All Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_checkbox_toggle(
					false,
					'export_all_settings',
					esc_html__( 'Export WPConsent settings including geolocation, multilanguage, and general settings.', 'wpconsent-cookies-banner-privacy-suite' )
				)
			);

			$this->metabox_row(
				esc_html__( 'Banner Design', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_checkbox_toggle(
					false,
					'export_banner_design',
					esc_html__( 'Export only the banner design settings including layout, colors, and text.', 'wpconsent-cookies-banner-privacy-suite' )
				)
			);

			$this->metabox_row(
				esc_html__( 'Cookie Data', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_checkbox_toggle(
					false,
					'export_cookie_data',
					esc_html__( 'Export cookie categories, services, and individual cookie information.', 'wpconsent-cookies-banner-privacy-suite' )
				)
			);

			$this->export_custom_scripts_input();
			?>
		</div>

		<div class="wpconsent-metabox-form-row">
			<button type="submit" name="wpconsent_export" class="wpconsent-button wpconsent-button-primary">
				<?php esc_html_e( 'Export Settings', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the input for enabling records of consent.
	 *
	 * @return void
	 */
	public function export_custom_scripts_input() {
		$this->metabox_row(
			esc_html__( 'Custom Scripts', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				false,
				'wpconsent-export-custom-scripts-lite',
				esc_html__( 'Export custom scripts and iframes.', 'wpconsent-cookies-banner-privacy-suite' )
			),
			'wpconsent-export-custom-scripts-lite',
			'',
			'',
			'',
			true
		);
	}

	/**
	 * Process messages for this page (e.g., after import).
	 */
	public function process_message() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['message'] ) && 'import_success' === $_GET['message'] ) {
			$this->set_success_message(
				__( 'Settings imported successfully.', 'wpconsent-cookies-banner-privacy-suite' )
			);
		}
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
			?>
			<div style="position: relative">
				<div class="wpconsent-blur-area">
					<?php
					$this->metabox(
						__( 'Custom Iframe/Scripts', 'wpconsent-cookies-banner-privacy-suite' ),
						$this->get_custom_scripts_content()
					);
					?>
				</div>
				<?php
				echo self::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html__( 'Custom Scripts/iFrames is a PRO feature', 'wpconsent-cookies-banner-privacy-suite' ),
					'<p>' . esc_html__( 'Upgrade to WPConsent PRO today and easily manage custom scripts and iframes. Take full control and block any scripts and iframes from loading until users give consent.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
					array(
						'text' => esc_html__( 'Upgrade to PRO and Unlock Custom Scripts', 'wpconsent-cookies-banner-privacy-suite' ),
						'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'advanced-page', 'main' ) ),
					),
					array(
						'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
						'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'advanced-page', 'features' ) ),
					)
				);
				?>
			</div>
			<?php
			$this->metabox(
				__( 'Advanced Settings', 'wpconsent-cookies-banner-privacy-suite' ),
				$this->get_advanced_settings_content()
			);
			?>
			<div class="wpconsent-submit">
				<button type="submit" name="save_changes" class="wpconsent-button wpconsent-button-primary">
					<?php esc_html_e( 'Save Changes', 'wpconsent-cookies-banner-privacy-suite' ); ?>
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
			<p><?php esc_html_e( 'Add custom iframes or scripts that should be blocked until consent is given.', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				<a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wpconsent_utm_url( 'https://wpconsent.com/docs', 'advanced', 'learn-more' ) ); ?>">
					<?php esc_html_e( 'Learn more', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</a>
			</p>
		</div>

		<div class="wpconsent-custom-scripts-manager wpconsent-cookies-manager wpconsent-accordion">
			<?php
			$display_scripts = array(
				array(
					'id'               => 'custom_1',
					'service'          => 'Some Service',
					'type'             => 'script',
					'tag'              => '1234',
					'blocked_elements' => 'script[src*="yourservice.com"]',
					'category'         => 'statistics',
				),
				array(
					'id'               => 'custom_2',
					'service'          => 'Other Pixel',
					'type'             => 'script',
					'tag'              => 'abc',
					'blocked_elements' => 'script[src*="otherservice.com"]',
					'category'         => 'statistics',
				),
			);

			// Fetch categories from the database.
			$all_categories = wpconsent()->cookies->get_categories();
			$categories     = array();
			if ( isset( $all_categories['statistics'] ) ) {
				$categories[ $all_categories['statistics']['id'] ] = array(
					'name'        => esc_html( $all_categories['statistics']['name'] ) . ' ' . esc_html__( 'Scripts', 'wpconsent-cookies-banner-privacy-suite' ),
					'description' => esc_html__( 'Add scripts for analytics and statistics tracking.', 'wpconsent-cookies-banner-privacy-suite' ),
				);
			}
			if ( isset( $all_categories['marketing'] ) ) {
				$categories[ $all_categories['marketing']['id'] ] = array(
					'name'        => esc_html( $all_categories['marketing']['name'] ) . ' ' . esc_html__( 'Scripts', 'wpconsent-cookies-banner-privacy-suite' ),
					'description' => esc_html__( 'Add scripts for marketing and advertising purposes.', 'wpconsent-cookies-banner-privacy-suite' ),
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
								<div class="script-service"><?php esc_html_e( 'Service', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="script-type"><?php esc_html_e( 'Type', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="script-script"><?php esc_html_e( 'Script', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="script-blocked-elements"><?php esc_html_e( 'Blocked Elements', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
								<div class="script-actions"><?php esc_html_e( 'Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></div>
							</div>
							<?php
							$category_scripts = array();
							if ( isset( $all_categories['statistics'] ) && $category_id == $all_categories['statistics']['id'] ) {
								$category_scripts = $display_scripts;
							}

							foreach ( $category_scripts as $script ) {
								?>
								<div class="wpconsent-cookie-item">
									<div class="script-service"><?php echo esc_html( $script['service'] ); ?></div>
									<div class="script-type"><?php echo esc_html( 'iframe' === $script['type'] ? 'iFrame' : 'Script' ); ?></div>
									<div class="script-script"><?php echo esc_html( $script['tag'] ); ?></div>
									<div class="script-blocked-elements"><?php echo esc_html( $script['blocked_elements'] ); ?></div>
									<div class="cookie-actions">
										<button class="wpconsent-button-icon wpconsent-edit-script" type="button">
											<?php wpconsent_icon( 'edit', 15, 16 ); ?>
										</button>
										<button class="wpconsent-button-icon wpconsent-delete-script" type="button">
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
				<?php esc_html_e( 'Add Custom iFrame/Script', 'wpconsent-cookies-banner-privacy-suite' ); ?>
			</button>
		</div>
		
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the content for the advanced settings meta box.
	 *
	 * @return string
	 */
	public function get_advanced_settings_content() {
		ob_start();

		$this->metabox_row(
			esc_html__( 'Remove all data', 'wpconsent-cookies-banner-privacy-suite' ),
			$this->get_checkbox_toggle(
				wpconsent()->settings->get_option( 'uninstall_data', false ),
				'uninstall_data',
				esc_html__( 'Remove all data when uninstalling the plugin.', 'wpconsent-cookies-banner-privacy-suite' )
			) . $this->help_icon( __( 'All cookie data and configuration will be unrecoverable.', 'wpconsent-cookies-banner-privacy-suite' ), false ),
			'uninstall_data'
		);

		return ob_get_clean();
	}
}
