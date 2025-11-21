<?php

/**
 * License key fun.
 *
 * @since 1.0.0
 */
class WPConsent_License {

	/**
	 * Store any license error messages.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Store any license success messages.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $success = array();

	/**
	 * The base URL for the license API.
	 *
	 * @var string
	 */
	public static $base_url = 'https://licensing.wpconsent.com/v1/';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Admin notices.
		if ( ! isset( $_GET['page'] ) || 'wpconsent-cookies' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			add_action( 'admin_init', array( $this, 'notices' ) );
		}

		// Periodic background license check.
		if ( $this->get() ) {
			$this->maybe_validate_key();
		}
	}

	/**
	 * Get the URL to check licenses.
	 *
	 * @return string The URL.
	 */
	public static function get_url() {
		if ( defined( 'WPCONSENT_LICENSING_URL' ) ) {
			return WPCONSENT_LICENSING_URL;
		}

		return self::$base_url;
	}

	/**
	 * Retrieve the license key.
	 *
	 * @param bool $multisite True if this is a multisite request.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get( $multisite = null ) {

		if ( is_null( $multisite ) ) {
			$multisite = is_multisite() && is_network_admin();
		}
		// Check for license key.
		$key = $this->get_setting( 'key', '', $multisite );

		// Allow wp-config constant to pass key.
		if ( empty( $key ) && defined( 'WPCONSENT_LICENSING_URL' ) ) {
			$key = WPCONSENT_LICENSING_URL;
		}

		return $key;
	}

	/**
	 * Grab a value from the license setting directly by its key.
	 *
	 * @param string $key The key to grab the value for.
	 * @param mixed  $default_value The default value.
	 * @param bool   $multisite True if this is a multisite request.
	 *
	 * @return array|false|mixed|string
	 */
	public function get_setting( $key, $default_value = false, $multisite = false ) {
		$key     = sanitize_key( $key );
		$options = $this->get_option( $multisite );

		return is_array( $options ) && ! empty( $options[ $key ] ) ? wp_unslash( $options[ $key ] ) : $default_value;
	}

	/**
	 * Check how license key is provided.
	 *
	 * @return string
	 * @since 1.6.3
	 */
	public function get_key_location() {

		if ( defined( 'WPCONSENT_LICENSE_LEY' ) ) {
			return 'constant';
		}

		$key = $this->get_setting( 'key', '' );

		return ! empty( $key ) ? 'option' : 'missing';
	}

	/**
	 * Load the license key level.
	 *
	 * @param bool $multisite True if this is a multisite request.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function type( $multisite = false ) {

		return strtolower( $this->get_setting( 'type', '', $multisite ) );
	}

	/**
	 * Get domains for license requests.
	 *
	 * @return array[]
	 */
	public function get_domains() {
		$path = wp_parse_url( get_home_url(), PHP_URL_PATH );

		$path = $path ? trailingslashit( $path ) : '/';

		return array(
			array(
				'domain' => wpconsent_get_site_domain(),
				'path'   => $path,
			),
		);
	}

	/**
	 * Verify a license key entered by the user.
	 *
	 * @param string $key License key.
	 * @param bool   $ajax True if this is an ajax request.
	 * @param bool   $multisite True if this is a multisite request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function verify_key( $key = '', $ajax = false, $multisite = false ) {

		if ( empty( $key ) ) {
			return false;
		}

		// Perform a request to verify the key.
		$verify = $this->perform_remote_request( 'activate', $key, $this->get_domains(), $multisite );

		// If the verification request returns false, send back a generic error message and return.
		if ( empty( $verify ) ) {
			$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpconsent-premium' );

			if ( $ajax ) {
				wp_send_json_error( $msg );
			} else {
				$this->errors[] = $msg;

				return false;
			}
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $verify->error ) ) {
			$message = empty( $verify->message ) ? $verify->error : $verify->message;
			$message = sprintf(
			/* translators: %s is the error message. */
				__( 'Error message: %s', 'wpconsent-premium' ),
				$message
			);
			if ( $ajax ) {
				wp_send_json_error( $message );
			} else {
				$this->errors[] = $message;

				return false;
			}
		}

		$success = __( 'Congratulations! Your WPConsent license is activated & this site is now receiving automatic updates.', 'wpconsent-premium' );

		// Otherwise, user's license has been verified successfully, update the option and set the success message.
		$option                = $this->get_option( $multisite );
		$option['key']         = $key;
		$option['type']        = isset( $verify->level ) ? $verify->level : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$option['addons']      = isset( $verify->addons ) ? $verify->addons : array();
		$this->success[]       = $success;

		$this->update_option( $option, $multisite );

		$this->clear_cache();

		if ( $ajax ) {
			wp_send_json_success(
				array(
					'type'   => $option['type'],
					'msg'    => $success,
					'reload' => $multisite,
				)
			);
		}
	}

	/**
	 * Clear license cache routine.
	 *
	 * @since 1.6.8
	 */
	private function clear_cache() {
		wp_clean_plugins_cache();
	}

	/**
	 * Update the license option.
	 *
	 * @param array $value The value to update the option with.
	 * @param bool  $multisite True if this is a multisite request.
	 *
	 * @return void
	 */
	public function update_option( $value, $multisite = false ) {
		if ( $multisite ) {
			update_site_option( 'wpconsent_network_license', $value );
		} else {
			update_option( 'wpconsent_license', $value );
		}
	}

	/**
	 * Get the license option.
	 *
	 * @param bool $multisite True if this is a multisite request.
	 *
	 * @return array
	 */
	public function get_option( $multisite = false ) {
		if ( $multisite ) {
			return (array) get_site_option( 'wpconsent_network_license', array() );
		} else {
			return (array) get_option( 'wpconsent_license', array() );
		}
	}

	/**
	 * Maybe validates a license key entered by the user.
	 *
	 * @return void Return early if the transient has not expired yet.
	 * @since 1.0.0
	 */
	public function maybe_validate_key() {

		$key = $this->get();

		if ( ! $key ) {
			return;
		}

		// Perform a request to validate the key once a day.
		$timestamp = get_option( 'wpconsent_license_updates' );

		if ( ! $timestamp ) {
			$timestamp = strtotime( '+24 hours' );
			update_option( 'wpconsent_license_updates', $timestamp );
			$this->validate_key( $key );
		} else {
			$current_timestamp = time();
			if ( $current_timestamp < $timestamp ) {
				return;
			} else {
				update_option( 'wpconsent_license_updates', strtotime( '+24 hours' ) );
				$this->validate_key( $key );
			}
		}
	}

	/**
	 * Validate a license key entered by the user.
	 *
	 * @param string $key Key.
	 * @param bool   $forced Force to set contextual messages (false by default).
	 * @param bool   $ajax AJAX.
	 * @param bool   $return_status Option to return the license status.
	 * @param bool   $multisite If this request is made in a network context.
	 *
	 * @return string|bool
	 * @since 1.0.0
	 */
	public function validate_key( $key = '', $forced = false, $ajax = false, $return_status = false, $multisite = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$validate = $this->perform_remote_request( 'activate', $key, $this->get_domains() );

		// If there was a basic API error in validation, only set the transient for 10 minutes before retrying.
		if ( empty( $validate ) ) {
			// If forced, set contextual success message.
			if ( $forced ) {
				$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpconsent-premium' );
				if ( $ajax ) {
					wp_send_json_error( $msg );
				} else {
					$this->errors[] = $msg;
				}
			}

			return false;
		}

		$option = $this->get_option( $multisite );

		if ( ! empty( $validate->error ) ) {
			if ( 'missing-license' === $validate->error ) {
				$option['is_expired']  = false;
				$option['is_disabled'] = false;
				$option['is_invalid']  = true;
				$this->update_option( $option, $multisite );

				return false;
			}

			if ( 'disabled' === $validate->error ) {
				$option['is_expired']  = false;
				$option['is_disabled'] = true;
				$option['is_invalid']  = false;
				$this->update_option( $option, $multisite );

				return false;
			}

			if ( 'expired' === $validate->error ) {
				$option['is_expired']  = true;
				$option['is_disabled'] = false;
				$option['is_invalid']  = false;
				$this->update_option( $option, $multisite );

				return false;
			}
		}

		// Something bad happened, error unknown.
		if ( empty( $validate->success ) || empty( $validate->level ) ) {
			return false;
		}

		// Otherwise, our check has returned successfully. Set the transient and update our license type and flags.
		$option['type']        = isset( $validate->level ) ? $validate->level : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$option['addons']      = isset( $validate->addons ) ? $validate->addons : array();

		$this->update_option( $option, $multisite );

		return $return_status ? 'valid' : true;
	}

	/**
	 * Deactivate a license key entered by the user.
	 *
	 * @param bool $ajax True if this is an ajax request.
	 * @param bool $force Force deactivate (delete license regardless of the response).
	 * @param bool $multisite True if this is a multisite request.
	 *
	 * @since 1.0.0
	 */
	public function deactivate_key( $ajax = false, $force = false, $multisite = false ) {

		$key = $this->get( $multisite );

		if ( ! $key ) {
			return;
		}

		// Perform a request to deactivate the key.
		$deactivate = $this->perform_remote_request( 'deactivate', $key, $this->get_domains() );

		// If the deactivation request returns false, send back a generic error message and return.
		if ( ! $deactivate && ! $force ) {

			$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpconsent-premium' );

			if ( $ajax ) {
				wp_send_json_error( $msg );
			} else {
				$this->errors[] = $msg;

				return;
			}
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $deactivate->error ) && ! $force ) {
			if ( $ajax ) {
				wp_send_json_error( $deactivate->error );
			} else {
				$this->errors[] = $deactivate->error;

				return;
			}
		}

		// Otherwise, user's license has been deactivated successfully, reset the option and set the success message.
		$success         = esc_html__( 'You have deactivated the key from this site successfully.', 'wpconsent-premium' );
		$this->success[] = $success;

		$this->update_option( '', $multisite );

		$this->clear_cache();

		if ( $ajax ) {
			wp_send_json_success( $success );
		}
	}

	/**
	 * Return possible license key error flag.
	 *
	 * @return bool True if there are license key errors, false otherwise.
	 * @since 1.0.0
	 */
	public function get_errors() {

		$option = $this->get_option();

		return ! empty( $option['is_expired'] ) || ! empty( $option['is_disabled'] ) || ! empty( $option['is_invalid'] );
	}

	/**
	 * Output any notices generated by the class.
	 *
	 * @param bool $below_h2 Whether to display a notice below H2.
	 *
	 * @since 1.0.0
	 */
	public function notices( $below_h2 = false ) {

		$multisite = is_multisite() && is_network_admin();
		if ( $multisite ) {
			return;
		}
		// Grab the option and output any nag dealing with license keys.
		$key    = $this->get( $multisite );
		$option = $this->get_option( $multisite );
		$class  = $below_h2 ? 'below-h2 ' : '';

		$class .= 'wpconsent-license-notice';

		$url = esc_url( add_query_arg( array( 'page' => 'wpconsent-cookies' ), admin_url( 'admin.php' ) ) );
		if ( $multisite ) {
			$url = esc_url( add_query_arg( array( 'page' => 'wpconsent' ), network_admin_url( 'admin.php' ) ) );
		}

		// If there is no license key, output nag about ensuring key is set for automatic updates.
		if ( ! $key ) {
			$notice = sprintf(
				wp_kses( /* translators: %s - plugin settings page URL. */
					__( 'Please <a href="%s">enter and activate</a> your license key for WPConsent to enable automatic updates.', 'wpconsent-premium' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				$url
			);

			WPConsent_Notice::info(
				$notice,
				array( 'class' => $class )
			);
		}

		// If a key has expired, output nag about renewing the key.
		if ( isset( $option['is_expired'] ) && $option['is_expired'] ) :

			$renew_now_url  = add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'Admin Notice',
					'utm_campaign' => 'plugin',
					'utm_content'  => 'Renew Now',
				),
				'https://wpconsent.com/my-account/licenses/'
			);
			$learn_more_url = add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'Admin Notice',
					'utm_campaign' => 'plugin',
					'utm_content'  => 'Learn More',
				),
				'https://wpconsent.com/docs/how-to-renew-your-wpconsent-license/'
			);

			$notice = sprintf(
				'<h3 style="margin: .75em 0 0 0;">
					%1$s %2$s
				</h3>
				<p>%3$s</p>
				<p>
					<a href="%4$s" class="button-primary">%5$s</a> &nbsp
					<a href="%6$s" class="button-secondary">%7$s</a>
				</p>',
				wpconsent_get_icon( 'exclamation' ),
				esc_html__( 'Heads up! Your WPConsent license has expired.', 'wpconsent-premium' ),
				esc_html__( 'An active license is needed to access the premium services automatic configuration. It also provides access to new features & plugin updates (including security improvements), and our world class support!', 'wpconsent-premium' ),
				esc_url( $renew_now_url ),
				esc_html__( 'Renew Now', 'wpconsent-premium' ),
				esc_url( $learn_more_url ),
				esc_html__( 'Learn More', 'wpconsent-premium' )
			);

			WPConsent_Notice::error(
				$notice,
				array(
					'class' => $class,
					'autop' => false,
				)
			);
		endif;

		// If a key has been disabled, output nag about using another key.
		if ( isset( $option['is_disabled'] ) && $option['is_disabled'] ) {
			WPConsent_Notice::error(
				esc_html__( 'Your license key for WPConsent has been disabled. Please use a different key to continue receiving automatic updates.', 'wpconsent-premium' ),
				array( 'class' => $class )
			);
		}

		// If a key is invalid, output nag about using another key.
		if ( isset( $option['is_invalid'] ) && $option['is_invalid'] ) {
			WPConsent_Notice::error(
				esc_html__( 'Your license key for WPConsent is invalid. The key no longer exists or the user associated with the key has been deleted. Please use a different key to continue receiving automatic updates.', 'wpconsent-premium' ),
				array( 'class' => $class )
			);
		}

		// If there are any license errors, output them now.
		if ( ! empty( $this->errors ) ) {
			WPConsent_Notice::error(
				implode( '<br>', $this->errors ),
				array( 'class' => $class )
			);
		}

		// If there are any success messages, output them now.
		if ( ! empty( $this->success ) ) {
			WPConsent_Notice::info(
				implode( '<br>', $this->success ),
				array( 'class' => $class )
			);
		}
	}

	/**
	 * Request the remote URL via wp_remote_get() and return a json decoded response.
	 *
	 * @param string $action The name of the request action var.
	 * @param string $license_key The license key to send with the request.
	 * @param array  $domains Array of domains to check license against.
	 * @param bool   $multisite True if this is a multisite request.
	 *
	 * @return mixed Json decoded response on success, false on failure.
	 * @since 1.7.2 Switch from POST to GET request.
	 *
	 * @since 1.0.0
	 */
	public function perform_remote_request( $action, $license_key, $domains, $multisite = false ) {

		$payload = array(
			'version'     => WPCONSENT_VERSION,
			'license'     => $license_key,
			'domains'     => $domains,
			'php_version' => PHP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'multisite'   => $multisite,
			'sku'         => 'wpconsent',
		);

		return self::api_request( $action, $payload );
	}

	/**
	 * Generic request method for the licensing API.
	 *
	 * @param string $endpoint The api endpoint to request.
	 * @param array  $body The body data to send with the request.
	 * @param array  $headers The headers to use for this request.
	 *
	 * @return false|mixed
	 */
	public static function api_request( $endpoint, $body = array(), $headers = array() ) {
		$body = wp_json_encode( $body );

		// Build the headers of the request.
		$headers = wp_parse_args(
			$headers,
			array(
				'Content-Type' => 'application/json',
			)
		);

		$request_args = array(
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 20,
		);

		$url           = trailingslashit( self::get_url() . $endpoint );
		$response      = wp_remote_post( $url, $request_args );
		$response_body = wp_remote_retrieve_body( $response );

		// Bail out early if there are any errors.
		if ( is_wp_error( $response_body ) || empty( $response_body ) ) {
			return false;
		}

		// Return the json decoded content.
		return json_decode( $response_body );
	}

	/**
	 * Whether the site is using an active license.
	 *
	 * @param bool $multisite True if this is a multisite request.
	 *
	 * @return bool
	 * @since 1.5.0
	 */
	public function is_active( $multisite = false ) {

		$license = $this->get_option( $multisite );

		if (
			empty( $license ) ||
			! empty( $license['is_expired'] ) ||
			! empty( $license['is_disabled'] ) ||
			! empty( $license['is_invalid'] )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the site is using an expired license.
	 *
	 * @return bool
	 * @since 1.7.2
	 */
	public function is_expired() {

		return $this->has_status( 'is_expired' );
	}

	/**
	 * Whether the site is using a disabled license.
	 *
	 * @return bool
	 * @since 1.7.2
	 */
	public function is_disabled() {

		return $this->has_status( 'is_disabled' );
	}

	/**
	 * Whether the site is using an invalid license.
	 *
	 * @return bool
	 * @since 1.7.2
	 */
	public function is_invalid() {

		return $this->has_status( 'is_invalid' );
	}

	/**
	 * Check whether there is a specific license status.
	 *
	 * @param string $status License status.
	 *
	 * @return bool
	 * @since 1.7.2
	 */
	private function has_status( $status ) {

		$license = get_option( 'wpconsent_license', false );

		return ( isset( $license[ $status ] ) && $license[ $status ] );
	}

	/**
	 * Check if the license is allowed to use the addon.
	 *
	 * @param string $sku The SKU of the addon to check.
	 * @param bool   $multisite True if this is a multisite request.
	 *
	 * @return bool
	 */
	public function is_addon_allowed( $sku, $multisite = false ) {
		$addons = $this->get_setting( 'addons', array(), $multisite );

		if ( is_string( $addons ) ) {
			$addons = json_decode( $addons );
		}

		if ( empty( $addons ) ) {
			return false;
		}

		return in_array( $sku, $addons, true );
	}

	/**
	 * Check if the level indicated is allowed by the license.
	 *
	 * @param string $level The level of the license to check.
	 * @param bool   $multisite True if this is a multisite request.
	 *
	 * @return bool
	 */
	public function license_can( $level, $multisite = false ) {
		// If the license is not active, return false.
		if ( ! $this->is_active( $multisite ) ) {
			return false;
		}

		// Now let's see what license level we have.
		$license_level = $this->type( $multisite );
		$levels        = array(
			'basic',
			'plus',
			'pro',
			'elite',
			'bundle',
		);
		// Let's see if the current level matches the level we're checking and if not let's see if it's higher than the level we're checking.
		if ( $license_level === $level || array_search( $license_level, $levels, true ) > array_search( $level, $levels, true ) ) {
			return true;
		}

		return false;
	}
}
