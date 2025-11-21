<?php
/**
 * WPConsent Onboarding Wizard Pro.
 *
 * @package WPConsent
 */

/**
 * Onboarding wizard page class for pro.
 */
class WPConsent_Admin_Page_Onboarding_Pro extends WPConsent_Admin_Page_Onboarding {

	use WPConsent_License_Field;

	/**
	 * Localization data specific to this page.
	 * Extends the parent page.
	 *
	 * @param array $data The data array.
	 *
	 * @return array
	 */
	public function localize_script( $data ) {
		$data = parent::localize_script( $data );

		$data['icons']               = array(
			'checkmark' => wpconsent_get_icon( 'checkmark', 88, 88, '0 0 130.2 130.2' ),
		);
		$data['license_error_title'] = __( 'We encountered an error activating your license key', 'wpconsent-premium' );
		$data['multisite']           = is_network_admin();

		return $data;
	}

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

	/**
	 * Display the license key form in the scanner step.
	 *
	 * @return void
	 */
	public function scan_form() {
		if ( ! wpconsent()->license->get() ) {
			?>
			<div class="wpconsent-onboarding-license-key">
				<label for="wpconsent-setting-license-key"><?php esc_html_e( 'License Key', 'wpconsent-premium' ); ?></label>
				<?php
				echo $this->get_license_key_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<?php
		}
	}

	/**
	 * Services upsell box. No upsell in pro version.
	 *
	 * @param string $slug The slug.
	 *
	 * @return void
	 */
	public function services_upsell_box( $slug = '' ) {}
}
