<?php
/**
 * Pro-specific admin page loader.
 * Replaces the classes used for generic pages with pro-specific ones.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Loader_Pro.
 */
class WPConsent_Admin_Page_Loader_Pro extends WPConsent_Admin_Page_Loader {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		parent::hooks();
		add_filter( 'plugin_action_links_' . WPCONSENT_PLUGIN_BASENAME, array( $this, 'pro_action_links' ) );
	}

	/**
	 * Require pro-specific files.
	 *
	 * @return void
	 */
	public function require_files() {
		parent::require_files();
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/trait-wpconsent-license-field.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-banner-pro.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-consent-logs.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-cookies-pro.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-onboarding-pro.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-geolocation-pro.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-scanner-pro.php';
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-admin-page-do-not-track-pro.php';
	}

	/**
	 * Override pro-specific pages.
	 *
	 * @return void
	 */
	public function prepare_pages() {
		parent::prepare_pages();

		$this->pages['banner']       = 'WPConsent_Admin_Page_Banner_Pro';
		$this->pages['cookies']      = 'WPConsent_Admin_Page_Cookies_Pro';
		$this->pages['geolocation']  = 'WPConsent_Admin_Page_Geolocation_Pro';
		$this->pages['consent_logs'] = 'WPConsent_Admin_Page_Consent_Logs_Pro';
		$this->pages['onboarding']   = 'WPConsent_Admin_Page_Onboarding_Pro';
		$this->pages['scanner']      = 'WPConsent_Admin_Page_Scanner_Pro';
		$this->pages['do_not_track'] = 'WPConsent_Admin_Page_Do_Not_Track_Pro';
	}

	/**
	 * Add pro-specific links.
	 *
	 * @param array $links The links array.
	 *
	 * @return array
	 */
	public function pro_action_links( $links ) {
		if ( isset( $links['wpconsentpro'] ) ) {
			unset( $links['wpconsentpro'] );
		}
		$custom = array();

		if ( isset( $links['settings'] ) ) {
			$custom['settings'] = $links['settings'];

			unset( $links['settings'] );
		}

		$custom['support'] = sprintf(
			'<a href="%1$s" aria-label="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			wpconsent_utm_url(
				'https://wpconsent.com/account/support/',
				'all-plugins',
				'plugin-action-links',
				'support'
			),
			esc_attr__( 'Go to WPConsent.com Support page', 'wpconsent-premium' ),
			esc_html__( 'Support', 'wpconsent-premium' )
		);

		return array_merge( $custom, (array) $links );
	}
}
