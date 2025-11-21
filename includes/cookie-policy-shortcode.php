<?php
/**
 * Shortcode for the cookie policy page.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'wpconsent_cookie_policy', 'wpconsent_cookie_policy_shortcode' );

/**
 * Outputs a list of cookies with settings to be used on the cookie policy page.
 *
 * @param array $atts The shortcode attributes.
 *
 * @return string
 */
function wpconsent_cookie_policy_shortcode( $atts = array() ) {
	// Parse shortcode attributes with defaults.
	$atts = shortcode_atts(
		array(
			'category_heading' => 'h2',
			'service_heading'  => 'h3',
		),
		$atts
	);

	$allowed_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' );

	// Let's make sure the heading tags are valid.
	$atts['category_heading'] = in_array( $atts['category_heading'], $allowed_tags, true ) ? $atts['category_heading'] : 'h2';
	$atts['service_heading']  = in_array( $atts['service_heading'], $allowed_tags, true ) ? $atts['service_heading'] : 'h3';

	$categories = wpconsent()->cookies->get_categories();
	$output     = '<div class="wpconsent-cookie-policy">';

	foreach ( $categories as $category ) {
		$cookies = wpconsent()->cookies->get_cookies_by_category( $category['id'] );
		if ( empty( $cookies ) ) {
			continue;
		}
		$output .= '<' . esc_attr( $atts['category_heading'] ) . ' class="wpconsent-cookie-category-name">' . esc_html( $category['name'] ) . '</' . esc_attr( $atts['category_heading'] ) . '>';
		$output .= '<p class="wpconsent-cookie-category-description">' . esc_html( $category['description'] ) . '</p>';

		$category_table = '<table class="wpconsent-cookie-policy-table">';

		$category_table .= '<tr><th>' . esc_html__( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ) . '</th><th>' . esc_html__( 'Purpose', 'wpconsent-cookies-banner-privacy-suite' ) . '</th><th>' . esc_html__( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ) . '</th></tr>';

		$has_cookies = false;
		foreach ( $cookies as $cookie ) {
			if ( ! in_array( $category['id'], $cookie['categories'], true ) ) {
				continue;
			}
			$has_cookies = true;

			$category_table .= '<tr><td>' . esc_html( $cookie['cookie_id'] ) . '</td><td>' . esc_html( $cookie['description'] ) . '</td><td>' . esc_html( $cookie['duration'] ) . '</td></tr>';
		}
		$category_table .= '</table>';
		if ( $has_cookies ) {
			$output .= $category_table;
		}

		$services = wpconsent()->cookies->get_services_by_category( $category['id'] );
		foreach ( $services as $service ) {
			$output .= '<' . esc_attr( $atts['service_heading'] ) . ' class="wpconsent-cookie-service-name">' . esc_html( $service['name'] ) . '</' . esc_attr( $atts['service_heading'] ) . '>';
			$output .= '<p class="wpconsent-cookie-service-description">' . esc_html( $service['description'] ) . '</p>';
			if ( ! empty( $service['service_url'] ) ) {
				$output .= '<a href="' . esc_url( $service['service_url'] ) . '">' . esc_html__( 'Learn more', 'wpconsent-cookies-banner-privacy-suite' ) . '</a>';
			}

			$cookies = wpconsent()->cookies->get_cookies_by_service( $service['id'] );

			$output .= '<table class="wpconsent-cookie-policy-table">';
			$output .= '<tr><th>' . esc_html__( 'Cookie ID', 'wpconsent-cookies-banner-privacy-suite' ) . '</th><th>' . esc_html__( 'Purpose', 'wpconsent-cookies-banner-privacy-suite' ) . '</th><th>' . esc_html__( 'Duration', 'wpconsent-cookies-banner-privacy-suite' ) . '</th></tr>';
			foreach ( $cookies as $cookie ) {
				$output .= '<tr><td>' . esc_html( $cookie['cookie_id'] ) . '</td><td>' . esc_html( $cookie['description'] ) . '</td><td>' . esc_html( $cookie['duration'] ) . '</td></tr>';
			}
			$output .= '</table>';
		}
	}

	$output .= '</div>';

	return $output;
}
