<?php
/**
 * Trait for the language picker in the WPConsent admin area.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

trait WPCOnsent_Language_Picker {

	/**
	 * Get the language picker button.
	 *
	 * @return void
	 */
	public function language_picker_button() {
		// Let's make sure we have the translation functions available.
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		// Get currently enabled languages.
		$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );
		$current_language  = get_user_meta( get_current_user_id(), 'wpconsent_admin_language', true );
		// Use the get_plugin_locale method from WPConsent_Multilanguage to get the correct locale.
		$plugin_locale = wpconsent()->multilanguage->get_plugin_locale();

		// Get the WordPress default language from WPLANG option.
		$wp_default_language = get_option( 'WPLANG' );
		if ( empty( $wp_default_language ) ) {
			$wp_default_language = 'en_US';
		}

		if ( empty( $current_language ) ) {
			$current_language = $plugin_locale;
		}

		// Make sure the plugin locale is in the enabled languages array.
		if ( ! in_array( $plugin_locale, $enabled_languages, true ) ) {
			$enabled_languages = array_merge( array( $plugin_locale ), $enabled_languages );
		}

		// Always add the WordPress default language if it's not already in the enabled languages array.
		if ( ! in_array( $wp_default_language, $enabled_languages, true ) ) {
			$enabled_languages = array_merge( array( $wp_default_language ), $enabled_languages );
		}

		// Get all available languages.
		$available_languages = wp_get_available_translations();
		if ( ! $available_languages ) {
			$available_languages = array();
		}

		// Add English (United States) only if it's in the enabled languages array or if it's the WordPress default language.
		if ( in_array( 'en_US', $enabled_languages, true ) || 'en_US' === $wp_default_language ) {
			$available_languages['en_US'] = array(
				'language'     => 'en_US',
				'english_name' => 'English (United States)',
				'native_name'  => 'English (United States)',
			);
		}
		?>
		<div class="wpconsent-language-picker-container">
			<button
					type="button"
					id="wpconsent-languages-button"
					class="wpconsent-button-just-icon wpconsent-languages-button <?php echo $current_language !== $wp_default_language ? 'wpconsent-language-picker-non-default' : ''; ?>">
				<?php wpconsent_icon( 'globe', 16, 16, '0 -960 960 960' ); ?>
				<?php if ( $current_language !== $wp_default_language ) : ?>
					<span class="wpconsent-language-picker-indicator"></span>
				<?php endif; ?>
			</button>
			<div class="wpconsent-language-picker-dropdown">
				<div class="wpconsent-language-picker-list">
					<?php foreach ( $enabled_languages as $lang ) : ?>
						<?php if ( isset( $available_languages[ $lang ] ) ) : ?>
							<button type="button" class="wpconsent-language-picker-item <?php echo $lang === $current_language ? 'active' : ''; ?>" data-language="<?php echo esc_attr( $lang ); ?>">
								<?php echo esc_html( $available_languages[ $lang ]['native_name'] ); ?>
							</button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<div class="wpconsent-language-picker-footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpconsent-cookies&view=languages' ) ); ?>" class="wpconsent-language-picker-manage">
						<?php esc_html_e( 'Manage Languages', 'wpconsent-cookies-banner-privacy-suite' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
