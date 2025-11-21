<?php
/**
 * Admin paged used for the site scanner.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Scanner_Pro.
 */
class WPConsent_Admin_Page_Scanner_Pro extends WPConsent_Admin_Page_Scanner {

	/**
	 * Services upsell box. No upsell in pro version.
	 *
	 * @param string $slug The slug.
	 *
	 * @return void
	 */
	public function services_upsell_box( $slug = '' ) {}
}
