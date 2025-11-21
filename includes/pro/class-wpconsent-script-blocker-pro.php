<?php
/**
 * Pro version of script blocker.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Script_Blocker_Pro.
 */
class WPConsent_Script_Blocker_Pro extends WPConsent_Script_Blocker {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'wpconsent_blocked_scripts', array( $this, 'add_custom_scripts' ) );
	}

	/**
	 * Load pro-specific data.
	 */
	public function load_data() {
		parent::load_data();

		$this->categorized_scripts['statistics']['woocommerce-sourcebuster'] = array(
			'label'   => 'WooCommerce',
			'scripts' => array(
				'sourcebuster/sourcebuster.min.js',
				'sourcebuster/sourcebuster.js',
				'woocommerce/assets/js/frontend/order-attribution',
			),
		);
	}

	/**
	 * Add custom scripts to the blocked scripts array.
	 *
	 * @param array $scripts The current scripts array.
	 * @return array Modified scripts array with custom scripts.
	 */
	public function add_custom_scripts( $scripts ) {
		$custom_scripts = get_option( 'wpconsent_custom_scripts' );
		if ( empty( $custom_scripts ) || ! is_array( $custom_scripts ) ) {
			return $scripts;
		}

		foreach ( $custom_scripts as $entry_key => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['category'], $entry['service'], $entry['type'], $entry['tag'] ) ) {
				continue;
			}

			$category_id      = $entry['category'];
			$service_id       = $entry['service'];
			$type             = $entry['type'];
			$tag              = isset( $entry['tag'] ) ? trim( $entry['tag'] ) : '';
			$blocked_elements = isset( $entry['blocked_elements'] ) ? $entry['blocked_elements'] : array();

			$category = wpconsent()->cookies->get_category_by_id( $category_id );
			$service  = wpconsent()->cookies->get_service_by_id( $service_id );
			if ( ! $category || ! $service ) {
				continue;
			}

			$category_slug = $category['slug'];
			$service_slug  = sanitize_title( $service['name'] );

			if ( ! isset( $scripts[ $category_slug ] ) ) {
				$scripts[ $category_slug ] = array();
			}
			if ( ! isset( $scripts[ $category_slug ][ $service_slug ] ) ) {
				$scripts[ $category_slug ][ $service_slug ] = array(
					'label'            => $service['name'],
					'scripts'          => array(),
					'iframes'          => array(),
					'blocked_elements' => array(),
				);
			}

			if ( 'script' === $type && ! empty( $tag ) ) {
				$scripts[ $category_slug ][ $service_slug ]['scripts'][] = $tag;
			} elseif ( 'iframe' === $type && ! empty( $tag ) ) {
				$scripts[ $category_slug ][ $service_slug ]['iframes'][] = $tag;
			}

			if ( ! empty( $blocked_elements ) && is_array( $blocked_elements ) ) {
				if ( 'script' === $type ) {
					$scripts[ $category_slug ][ $service_slug ]['scripts'] = array_merge(
						$scripts[ $category_slug ][ $service_slug ]['scripts'],
						$blocked_elements
					);
				} elseif ( 'iframe' === $type ) {
					$scripts[ $category_slug ][ $service_slug ]['blocked_elements'] = array_merge(
						$scripts[ $category_slug ][ $service_slug ]['blocked_elements'],
						$blocked_elements
					);
				}
			}
		}

		return $scripts;
	}
}
