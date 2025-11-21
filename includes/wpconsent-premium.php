<?php
/**
 * WPConsent Premium.
 *
 * @package WPConsent
 */

/**
 * Get the main instance of WPConsent.
 *
 * @return WPConsent_Premium
 */
function wpconsent() {// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WPConsent_Premium::instance();
}
