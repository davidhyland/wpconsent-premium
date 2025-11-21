<?php
/**
 * Pro banner admin page.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Banner.
 */
class WPConsent_Admin_Page_Banner_Pro extends WPConsent_Admin_Page_Banner {

	/**
	 * Get banner layouts.
	 *
	 * @return array
	 */
	public function get_banner_layouts() {
		$banner_layouts = parent::get_banner_layouts();

		$banner_layouts['modal']['is_pro'] = false;

		return $banner_layouts;
	}
}
