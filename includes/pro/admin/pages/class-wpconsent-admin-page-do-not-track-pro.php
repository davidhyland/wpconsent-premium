<?php
/**
 * Pro Do Not Track admin page.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Do_Not_Track_Pro.
 */
class WPConsent_Admin_Page_Do_Not_Track_Pro extends WPConsent_Admin_Page_Do_Not_Track {

	/**
	 * Override the output method to show different upsell banners based on license type.
	 *
	 * @return void
	 */
	public function output() {
		$this->output_header();
		?>
		<div class="wpconsent-content">
			<div class="wpconsent-blur-area">
				<?php
				$this->output_content();
				do_action( "wpconsent_admin_page_content_{$this->page_slug}", $this );
				?>
			</div>
			<?php
			$addon_name = 'wpconsent-do-not-track';

			// Check if the user has a plus or higher license.
			if ( $this->license_can( 'plus' ) ) {
				// Check if the addon is active.
				$is_addon_active = function_exists( 'wpconsent_dnt' );

				// Check if the addon is installed but not active.
				$addon_installed = false;
				if ( ! $is_addon_active ) {
					$plugin_path     = $this->get_plugin_path( $addon_name );
					$addon_installed = ! empty( $plugin_path );
				}

				if ( ! $is_addon_active ) {
					// Show 1-click install or activate option.
					if ( $addon_installed ) {
						$button_text = __( 'Activate Do Not Track Addon', 'wpconsent-premium' );
						$title       = __( 'The Do Not Track Addon is not active', 'wpconsent-premium' );
					} else {
						$button_text = __( 'Install Do Not Track Addon', 'wpconsent-premium' );
						$title       = __( 'The Do Not Track Addon is not installed', 'wpconsent-premium' );
					}

					echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( $title ),
						'<p>' . esc_html__( 'Install the addon now to allow your users to submit Do Not Track requests directly from your website.', 'wpconsent-premium' ) . '</p>',
						array(
							'text'       => esc_html( $button_text ),
							'tag'        => 'button',
							'class'      => 'wpconsent-button wpconsent-button-large wpconsent-button-install-addon',
							'attributes' => array(
								'data-addon' => esc_attr( $addon_name ),
							),
						)
					);
				}
			} else {
				// Show upgrade message for basic plan.
				echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html__( 'Do Not Track Addon is not available on your plan', 'wpconsent-premium' ),
					'<p>' . esc_html__( 'The Do Not Track addon is only available on the Plus plan or higher. Upgrade your license to enable this feature and allow your users to submit Do Not Track requests directly from your website.', 'wpconsent-premium' ) . '</p>',
					array(
						'text' => esc_html__( 'Upgrade Now', 'wpconsent-premium' ),
						'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/my-account/licenses/', 'do-not-track-page', 'upgrade-to-plus' ) ),
					),
					array(),
					array(
						esc_html__( 'Allow users to submit Do Not Track requests', 'wpconsent-premium' ),
						esc_html__( 'Manage and export Do Not Track requests', 'wpconsent-premium' ),
						esc_html__( 'Customizable request form', 'wpconsent-premium' ),
						esc_html__( 'GDPR and CCPA compliance', 'wpconsent-premium' ),
					)
				);
			}
			?>
		</div>
		<?php
	}

	/**
	 * Check if the license can use a specific feature.
	 *
	 * @param string $level The level to check.
	 *
	 * @return bool
	 */
	private function license_can( $level ) {
		if ( ! method_exists( wpconsent()->license, 'license_can' ) ) {
			return false;
		}

		return wpconsent()->license->license_can( $level );
	}

	/**
	 * Get the plugin path for an addon.
	 *
	 * @param string $addon_name The addon name.
	 *
	 * @return string|false The plugin path or false if not found.
	 */
	private function get_plugin_path( $addon_name ) {
		// Make sure the get_plugins function is available.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		foreach ( $plugins as $plugin_path => $plugin_data ) {
			if ( strpos( $plugin_path, $addon_name ) === 0 ) {
				return $plugin_path;
			}
		}

		return false;
	}
}
