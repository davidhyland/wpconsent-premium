<?php
/**
 * This class is used to scan websites and
 * suggest a cookies configuration that fits their
 * needs. It leverages the same structure that we use
 * for our script blocking where we block certain scripts
 * automatically for marketing and statistics scripts.
 *
 * @package WPConsent
 */

/**
 * WPConsent Scanner, singleton pattern.
 */
class WPConsent_Scanner {

	/**
	 * The services.
	 *
	 * @var array
	 */
	protected $services;

	/**
	 * The instance of this class.
	 *
	 * @var WPConsent_Scanner
	 */
	private static $instance;

	/**
	 * The services needed for the scanner.
	 *
	 * @var array
	 */
	protected $services_needed = array();

	/**
	 * The scripts by category.
	 *
	 * @var array
	 */
	protected $scripts_by_category = array();

	/**
	 * The essential services.
	 *
	 * @var array
	 */
	protected $essential = array();

	/**
	 * The endpoint for the scan.
	 *
	 * @var string
	 */
	protected $scan_endpoint = 'https://cookies.wpconsent.com/api/v1/scanner';

	/**
	 * Aggregated scripts from all scanned pages.
	 *
	 * @var array
	 */
	protected $aggregated_scripts = array();

	/**
	 * Aggregated services from all scanned pages.
	 *
	 * @var array
	 */
	protected $aggregated_services = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->hooks();
	}

	/**
	 * Gets the instance of the first called class.
	 * This specific class will always run either version exclusively.
	 *
	 * @return WPConsent_Scanner
	 */
	public static function get_instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Adds the hooks for this class.
	 *
	 * @return void
	 */
	protected function hooks() {
		add_action( 'wp_ajax_wpconsent_scan_website', array( $this, 'ajax_scan_website' ) );
		add_action( 'wp_ajax_wpconsent_scan_page', array( $this, 'ajax_scan_page' ) );
	}

	/**
	 * Scans the website and suggests a cookie configuration.
	 * This is the legacy endpoint that scans only the homepage.
	 *
	 * @return void
	 */
	public function ajax_scan_website() {
		check_ajax_referer( 'wpconsent_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Reset aggregated results.
		$this->aggregated_scripts  = array();
		$this->aggregated_services = array();

		// Scan homepage.
		$response = $this->perform_scan( home_url( '/' ) );

		// Check if we have an error from the scanner.
		if ( isset( $response['error'] ) && $response['error'] ) {
			$response['message'] = $response['error_message'];
		} else {
			$response['message'] = $this->get_message( $response );
			$this->save_scan_data( $response );

			if ( ! empty( $email ) ) {
				$this->save_email( $email );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Gets the URLs to scan from the manual_scan_pages.
	 *
	 * @return array
	 */
	public function get_scan_urls() {
		$urls = array();

		// Check if a static page is set as homepage.
		$show_on_front = get_option( 'show_on_front' );
		$page_on_front = get_option( 'page_on_front' );

		// Keep track of this to prevent duplication.
		$homepage_added = false;

		if ( 'page' === $show_on_front && $page_on_front ) {
			// Add the static homepage URL.
			$homepage_url = get_permalink( $page_on_front );
			if ( $homepage_url && ! is_wp_error( $homepage_url ) ) {
				$urls[]         = $homepage_url;
				$homepage_added = true;
			}
		} else {
			// Add the default homepage URL.
			$urls[] = home_url( '/' );
		}

		// Get manually selected page/post IDs from settings.
		$selected_content_ids = wpconsent()->settings->get_option( 'manual_scan_pages', array() );

		if ( ! empty( $selected_content_ids ) && is_array( $selected_content_ids ) ) {
			foreach ( $selected_content_ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id > 0 ) {
					if ( ! $homepage_added || $post_id != $page_on_front ) {
						// Only add if it's not the homepage that has already been added.
						$permalink = get_permalink( $post_id );
						if ( $permalink && ! is_wp_error( $permalink ) ) {
							$urls[] = $permalink;
						}
					}
				}
			}
		}

		// Ensure URLs are unique.
		return array_unique( $urls );
	}

	/**
	 * Scans a single page and returns the results.
	 *
	 * @return void
	 */
	public function ajax_scan_page() {
		check_ajax_referer( 'wpconsent_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$page_id    = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$is_final   = isset( $_POST['is_final'] ) ? (bool) $_POST['is_final'] : false;

		if ( empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No request ID provided for scanning.', 'wpconsent-cookies-banner-privacy-suite' ) ) );
		}

		// Get the URL to scan - either homepage or page permalink
		$url = '';
		if ( $page_id === 0 ) {
			// If page_id is 0, scan the homepage
			$url = home_url( '/' );
		} else {
			// Get the permalink for the page ID
			$url = get_permalink( $page_id );
			if ( ! $url || is_wp_error( $url ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not get permalink for the provided page ID.', 'wpconsent-cookies-banner-privacy-suite' ) ) );
			}
		}

		$current_scan = get_option(
			'wpconsent_scanner_' . $request_id,
			array(
				'scripts'         => array(),
				'services_needed' => array(),
			)
		);

		$this->aggregated_scripts  = $current_scan['scripts'];
		$this->aggregated_services = $current_scan['services_needed'];

		$response = $this->perform_scan( $url );

		// Check if we have an error from the scanner.
		if ( isset( $response['error'] ) && $response['error'] ) {
			$response['message'] = $response['error_message'];
		} else {
			// Aggregate results.
			$this->aggregate_scan_results( $response );

			// Save the aggregated results to temp option.
			update_option(
				'wpconsent_scanner_' . $request_id,
				array(
					'scripts'         => $this->aggregated_scripts,
					'services_needed' => $this->aggregated_services,
				)
			);

			// If this is the final scan, save the aggregated results.
			if ( $is_final ) {
				$final_results       = $this->get_aggregated_results();
				$response['message'] = $this->get_message( $final_results );

				$scanned_pages = isset( $_POST['scanned_pages'] ) ? absint( $_POST['scanned_pages'] ) : 0;
				$total_pages   = isset( $_POST['total_pages'] ) ? absint( $_POST['total_pages'] ) : 0;

				// Add page count information to the message.
				$response['message'] .= ' ' . sprintf(
					/* translators: %1$d: number of scanned pages, %2$d: total number of pages */
					__( '(%1$d of %2$d pages scanned)', 'wpconsent-cookies-banner-privacy-suite' ),
					$scanned_pages,
					$total_pages
				);

				$this->save_scan_data( $final_results );
				// Delete the temp option.
				delete_option( 'wpconsent_scanner_' . $request_id );
			} else {
				$response['message'] = $this->get_message( $response );
			}

			// Save email if provided.
			if ( ! empty( $email ) ) {
				$this->save_email( $email );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Aggregates results from multiple page scans.
	 *
	 * @param array $current_scan Current scan results.
	 * @return void
	 */
	protected function aggregate_scan_results( $current_scan ) {
		// Merge scripts by category.
		foreach ( $current_scan['scripts'] as $category => $scripts ) {
			if ( ! isset( $this->aggregated_scripts[ $category ] ) ) {
				$this->aggregated_scripts[ $category ] = array();
			}

			// Merge scripts while avoiding duplicates.
			foreach ( $scripts as $script ) {
				// Use the service name as the key to avoid duplicates across pages
				$script_key = $script['name'];
				if ( ! isset( $this->aggregated_scripts[ $category ][ $script_key ] ) ) {
					$this->aggregated_scripts[ $category ][ $script_key ] = $script;
				}
			}
		}

		// Merge services needed.
		$this->aggregated_services = array_unique(
			array_merge( $this->aggregated_services, $current_scan['services_needed'] )
		);
	}

	/**
	 * Gets the final aggregated results from all scans.
	 *
	 * @return array
	 */
	public function get_aggregated_results() {
		// Convert aggregated scripts back to sequential array.
		$formatted_scripts = array();
		foreach ( $this->aggregated_scripts as $category => $scripts ) {
			$formatted_scripts[ $category ] = array_values( $scripts );
		}

		return array(
			'error'           => false,
			'scripts'         => $formatted_scripts,
			'categories'      => wpconsent()->cookies->get_categories(),
			'services_needed' => $this->aggregated_services,
		);
	}

	/**
	 * Scans the website and returns the results.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return array
	 */
	public function perform_scan( $url = '' ) {
		$scan_result = $this->remote_scan( $url );

		// If we got an error from the scanner, return early with the error.
		if ( $scan_result['error'] ) {
			return array(
				'error'           => true,
				'error_message'   => $scan_result['error_message'],
				'scripts'         => array(),
				'categories'      => wpconsent()->cookies->get_categories(),
				'services_needed' => array(),
			);
		}

		return array(
			'error'           => false,
			'scripts'         => $this->get_scripts_formatted(),
			'categories'      => wpconsent()->cookies->get_categories(),
			'services_needed' => $this->services_needed,
		);
	}

	/**
	 * Does a remote scan of the website.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return array An array containing error status and message if applicable
	 */
	protected function remote_scan( $url = '' ) {
		$result = array(
			'error'         => false,
			'error_message' => '',
		);

		$request = wp_remote_post(
			$this->scan_endpoint,
			array(
				'body'    => wp_json_encode( $this->scan_request_body( $url ) ),
				'headers' => $this->scan_request_headers(),
				'timeout' => 30, // Increase timeout to handle slower responses.
			)
		);

		if ( is_wp_error( $request ) ) {
			$result['error']         = true;
			$result['error_message'] = sprintf(
			/* translators: %s: error message */
				esc_html__( 'The scanner endpoint could not be reached: %s', 'wpconsent-cookies-banner-privacy-suite' ),
				$request->get_error_message()
			);

			return $result;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		if ( 200 !== $response_code ) {
			$result['error']         = true;
			$result['error_message'] = sprintf(
			/* translators: %d: HTTP response code */
				esc_html__( 'The scanner endpoint returned an error: HTTP %d', 'wpconsent-cookies-banner-privacy-suite' ),
				$response_code
			);

			return $result;
		}

		$body = wp_remote_retrieve_body( $request );

		if ( empty( $body ) ) {
			$result['error']         = true;
			$result['error_message'] = __( 'The scanner endpoint returned an empty response.', 'wpconsent-cookies-banner-privacy-suite' );

			return $result;
		}

		$data = json_decode( $body, true );

		if ( ! isset( $data['services'] ) && ! isset( $data['scripts'] ) ) {
			$result['error']         = true;
			$result['error_message'] = __( 'The scanner endpoint returned an invalid response format.', 'wpconsent-cookies-banner-privacy-suite' );

			return $result;
		}

		$this->scripts_by_category = $data['scripts'];
		$this->services_needed     = $data['services'];

		return $result;
	}

	/**
	 * Headers used for the scanner request.
	 *
	 * @return array
	 */
	protected function scan_request_headers() {
		return array(
			'Content-Type'        => 'application/json',
			'X-WPConsent-Version' => WPCONSENT_VERSION,
		);
	}

	/**
	 * The body of the request to the scanner.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return array
	 */
	protected function scan_request_body( $url = '' ) {
		return array(
			'html'              => $this->get_website_html( $url ),
			'home_url'          => $url,
			'wp_content_url'    => content_url( '/' ),
			'comments'          => $this->has_comments(),
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => phpversion(),
			'plugin_type'       => 'lite',
			'wpconsent_version' => WPCONSENT_VERSION,
		);
	}

	/**
	 * Scan the website markup and find scripts.
	 *
	 * @return array
	 */
	protected function get_scripts_formatted() {

		$scripts_data = array();
		$services     = $this->get_services();

		// Some services add multiple scripts legitimately, but we don't need to show them on the scanner page like we do for multiple Google Analytics scripts, for example.
		$ignore_duplicates = array(
			'optinmonster',
			'youtube',
		);
		$used              = array();

		foreach ( $this->scripts_by_category as $category => $scripts ) {
			foreach ( $scripts as $script ) {
				// Let's see if we have a service for this script.
				if ( ! isset( $services[ $script['name'] ] ) ) {
					continue;
				}
				$service = $services[ $script['name'] ];

				if ( in_array( $script['name'], $ignore_duplicates, true ) ) {
					if ( in_array( $script['name'], $used, true ) ) {
						continue;
					}
					$used[] = $script['name'];
				}

				$scripts_data[ $category ][] = array(
					'name'        => $script['name'],
					'service'     => $service['label'],
					'html'        => esc_html( $script['script'] ),
					'logo'        => $service['logo'],
					'url'         => $service['service_url'],
					'description' => $service['description'],
					'cookies'     => $service['cookies'],
				);
			}
		}

		return $scripts_data;
	}

	/**
	 * Gets the message for the scan using the compiled data.
	 *
	 * @param array $data The data from the scan.
	 *
	 * @return string
	 */
	public function get_message( $data ) {
		$please_review = false;
		$total_scripts = 0;
		$total_cookies = 0;
		// Let's add the count of scripts in each category.
		foreach ( $data['scripts'] as $category => $scripts ) {
			$total_scripts += count( $scripts );
			foreach ( $scripts as $script ) {
				$total_cookies += count( $script['cookies'] );
			}
		}

		if ( 0 === $total_scripts ) {
			$message = __( 'No known scripts were found on your website.', 'wpconsent-cookies-banner-privacy-suite' );
		} else {
			$message = sprintf(
			/* translators: %1$d: number of scripts, %2$d: number of cookies */
				__( 'We found %1$d services on your website that set %2$d cookies.', 'wpconsent-cookies-banner-privacy-suite' ),
				$total_scripts,
				$total_cookies
			);

			$please_review = true;
		}
		if ( $please_review ) {
			$message .= ' ' . __( 'Please review them in the Detailed Report and choose the ones you want to automatically configure cookies for.', 'wpconsent-cookies-banner-privacy-suite' );
		}

		return $message;
	}

	/**
	 * Gets the services from the services class.
	 *
	 * @return array
	 */
	protected function get_services() {
		if ( ! isset( $this->services ) ) {
			$this->services = wpconsent()->services->get_services( $this->services_needed );
		}

		return $this->services;
	}

	/**
	 * Does a request to the website and returns the HTML.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return string
	 */
	public function get_website_html( $url = '' ) {
		$request = $this->self_request( $url );

		if ( is_wp_error( $request ) ) {
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Does a request to the website and returns the request object.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return WP_Error|array
	 */
	protected function self_request( $url = '' ) {
		$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Let's pass forward the basic auth header if present.
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';

		$headers = array(
			'X-Forwarded-For' => $user_ip,
		);

		if ( ! empty( $auth_header ) ) {
			$headers['Authorization'] = $auth_header;
		}

		return wp_remote_get(
			add_query_arg(
				array(
					'wpconsent_debug' => 'true',
				),
				$url
			),
			array(
				'sslverify' => false,
				'headers'   => $headers,
			)
		);
	}

	/**
	 * Saves the scan results to the DB.
	 *
	 * @param array $data The data to save.
	 *
	 * @return void
	 */
	public function save_scan_data( $data ) {
		// Don't save the $data['message'] if set.
		unset( $data['message'] );

		$scanner_data = array(
			'date' => current_time( 'mysql' ),
			'data' => $data,
		);

		update_option( 'wpconsent_scanner_data', $scanner_data );
	}

	/**
	 * Gets the scan data from the DB.
	 *
	 * @return array
	 */
	public function get_scan_data() {
		return get_option( 'wpconsent_scanner_data', array() );
	}

	/**
	 * Tries to determine if the current site has comments enabled.
	 *
	 * @return bool
	 */
	public function has_comments() {
		// Let's check if comments are enabled site-wide.
		if ( get_option( 'default_comment_status' ) === 'open' ) {
			return true;
		}

		return false;
	}

	/**
	 * Marks the scan as configured.
	 *
	 * @return void
	 */
	public function mark_scan_as_configured() {
		$scan_data                    = $this->get_scan_data();
		$scan_data['configured']      = true;
		$scan_data['configured_time'] = current_time( 'mysql' );

		update_option( 'wpconsent_scanner_data', $scan_data );
	}

	/**
	 * Adds services needed for the scan.
	 *
	 * @param array|string $services The services needed.
	 *
	 * @return void
	 */
	public function add_services_needed( $services ) {
		if ( is_string( $services ) ) {
			$services = array( $services );
		}
		$this->services_needed = array_merge( $this->services_needed, $services );
	}

	/**
	 * Subscribes the email for updates on our scan results.
	 *
	 * @param string $email The email to save.
	 *
	 * @return void
	 */
	protected function save_email( $email ) {
		$services_needed = $this->services_needed;

		$body = array(
			'email'    => base64_encode( $email ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'services' => $services_needed,
		);

		wp_remote_post(
			'https://connect.wpconsent.com/subscribe',
			array(
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . '; WPConsent/' . WPCONSENT_VERSION,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( $body ),
			)
		);
	}
}
