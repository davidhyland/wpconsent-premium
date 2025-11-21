<?php
/**
 * Trait WPConsent_License_Field
 *
 * @package WPConsent
 */

/**
 * Trait WPConsent_License_Field.
 */
trait WPConsent_License_Field {

	/**
	 * License key field for the Pro settings page.
	 *
	 * @return false|string
	 */
	public function get_license_key_field() {
		$license      = wpconsent()->license->get_option( is_network_admin() );
		$key          = ! empty( $license['key'] ) ? $license['key'] : '';
		$type         = ! empty( $license['type'] ) ? $license['type'] : '';
		$is_valid_key = ! empty( $key ) &&
		                ( isset( $license['is_expired'] ) && $license['is_expired'] === false ) &&
		                ( isset( $license['is_disabled'] ) && $license['is_disabled'] === false ) &&
		                ( isset( $license['is_invalid'] ) && $license['is_invalid'] === false );

		$hide        = $is_valid_key ? '' : 'wpconsent-hide';
		$account_url = wpconsent_utm_url(
			'https://wpconsent.com/my-account/licenses/',
			'settings-page',
			'license-key',
			'account'
		);

		ob_start();
		?>
		<div class="wpconsent-license-key-container">
			<span class="wpconsent-setting-license-wrapper">
				<input type="password" id="wpconsent-setting-license-key" value="<?php echo esc_attr( $key ); ?>" class="wpconsent-input-text" <?php disabled( $is_valid_key ); ?>>
			</span>
			<button type="button" id="wpconsent-setting-license-key-verify" class="wpconsent-button <?php echo $is_valid_key ? 'wpconsent-hide' : ''; ?>"><?php esc_html_e( 'Verify Key', 'wpconsent-premium' ); ?></button>
			<button type="button" id="wpconsent-setting-license-key-deactivate" class="wpconsent-button <?php echo esc_attr( $hide ); ?>"><?php esc_html_e( 'Deactivate Key', 'wpconsent-premium' ); ?></button>
			<button type="button" id="wpconsent-setting-license-key-deactivate-force" class="wpconsent-button wpconsent-hide"><?php esc_html_e( 'Force Deactivate Key', 'wpconsent-premium' ); ?></button>
			<p class="type <?php echo esc_attr( $hide ); ?>">
				<?php
				printf(
				/* translators: %s: the license type */
					esc_html__( 'Your license key level is %s.', 'wpconsent-premium' ),
					'<strong>' . esc_html( $type ) . '</strong>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag */
					esc_html__( 'You can find your license key in your %1$sWPConsent account%2$s.', 'wpconsent-premium' ),
					'<a href="' . esc_url( $account_url ) . '" target="_blank">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php

		return ob_get_clean();
	}
}