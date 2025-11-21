<?php
/**
 * Pro-specific install routines.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wpconsent_plugin_activation', 'wpconsent_pro_install_routines' );
add_action( 'wpconsent_before_version_update', 'wpconsent_pro_upgrade_routines' );

/**
 * Called when the plugin is activated to run pro-specific stuff like creating custom DB tables.
 *
 * @return void
 */
function wpconsent_pro_install_routines() {
	// Maybe update the db.
	$db = new WPConsent_DB();
	$db->maybe_update_db();

	$activated = get_option( 'wpconsent_activated', array() );

	if ( empty( $activated['wpconsent_pro'] ) ) {
		$activated['wpconsent_pro'] = time();
		update_option( 'wpconsent_activated', $activated );

		if ( ! isset( wpconsent()->settings ) ) {
			wpconsent()->settings = new WPConsent_Settings();
		}

		wpconsent()->settings->update_option( 'hide_powered_by', 1 );
	}

	// If the license class is not loaded let's bail.
	if ( ! class_exists( 'WPConsent_License' ) ) {
		return;
	}

	// If we have a license key from the connect process, and we don't have a license key set, let's use it.
	$license = get_option( 'wpconsent_connect', false );
	// Let's delete the connect option, if this fails for any reason it shouldn't block the activation process.
	delete_option( 'wpconsent_connect' );
	if ( empty( $license ) ) {
		return;
	}
	// If the license instance is not set let's set it.
	if ( ! isset( wpconsent()->license ) ) {
		wpconsent()->license = new WPConsent_License();
	}
	// If they already set a license key, ignore this.
	$license_key = wpconsent()->license->get();
	if ( empty( $license_key ) ) {
		// Let's set the license key.
		wpconsent()->license->verify_key( $license );
	}
}

/**
 * Run pro-specific upgrade routines.
 *
 * @param array $activated The value of the "wpconsent_activated" option.
 *
 * @return void
 */
function wpconsent_pro_upgrade_routines( $activated ) {
	if ( empty( $activated['version'] ) ) {
		// If no version is set this is the first install so let's skip.
		return;
	}

	if ( version_compare( $activated['version'], WPCONSENT_VERSION, '<' ) ) {
		// Let's run upgrade routines but only for the versions needed.
		if ( version_compare( $activated['version'], '1.0.3', '<' ) ) {
			// Upgrade to 1.0.3.
			wpconsent_pro_update_1_0_3();
		}

		// Migrate geolocation settings to the new format
		// This should only run if you are upgrading from a version before 1.0.9.
		if ( version_compare( $activated['version'], '1.0.9', '<' ) ) {
			wpconsent_pro_migrate_geolocation_settings();
		}
	}
}

/**
 * Upgrade routine for 1.0.3
 *
 * @return void
 */
function wpconsent_pro_update_1_0_3() {
	// Update the db table for country codes.
	$db = new WPConsent_DB();
	$db->maybe_update_db();
}

/**
 * Migrate geolocation settings from old format to new format.
 *
 * @return void
 */
function wpconsent_pro_migrate_geolocation_settings() {
	$geolocation_enabled = wpconsent()->settings->get_option( 'geolocation_enabled', 0 );
	// If geolocation is not configured, no need to migrate.
	if ( ! $geolocation_enabled ) {
		return;
	}

	// Advanced usage with custom slug and global dismissal.
	WPConsent_Notice::register_notice(
		sprintf(
			/* translators: %1$s and %2$s are opening and closing anchor tags respectively */
			__( 'The Geolocation rules for WPConsent have been expanded for more control. You previous rules have been migrated, to ensure the banner is being displayed as intended please %1$sreview the WPConsent Geolocation rules%2$s.', 'wpconsent-premium' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wpconsent-geolocation' ) ) . '">',
			'</a>'
		),
		'info',
		array(
			'dismiss' => WPConsent_Notice::DISMISS_GLOBAL,
			'slug'    => 'wpconsent-geolocation-migration',
			'autop'   => true,
		)
	);

	// Get the current geolocation settings.
	$show_to_eu = wpconsent()->settings->get_option( 'geolocation_eu', 0 );
	$countries  = wpconsent()->settings->get_option( 'geolocation_countries', array() );

	// Get existing geolocation groups.
	$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

	// If groups already exist, don't overwrite them.
	if ( ! empty( $location_groups ) ) {
		return;
	}

	$show_settings_button   = wpconsent()->settings->get_option( 'show_settings_button', 0 );
	$manual_toggle_services = wpconsent()->settings->get_option( 'manual_toggle_services', 0 );

	// Create a new group for the old settings.
	$group = array(
		'id'                     => 'migrated-' . time(),
		'name'                   => __( 'Migrated Geolocation Settings', 'wpconsent-premium' ),
		'locations'              => array(),
		'enable_script_blocking' => true,
		'show_banner'            => true,
		'show_settings_button'   => $show_settings_button,
		'manual_toggle_services' => $manual_toggle_services,
		'consent_mode'           => 'optin',
	);

	// Add EU countries if enabled.
	if ( $show_to_eu ) {
		$continents = WPConsent_Geolocation::get_continents();
		if ( isset( $continents['EU'] ) && isset( $continents['EU']['countries'] ) ) {
			foreach ( $continents['EU']['countries'] as $country_code ) {
				$group['locations'][] = array(
					'type' => 'country',
					'code' => strtoupper( $country_code ),
				);
			}
		}
	}

	// Add individual countries.
	if ( ! empty( $countries ) ) {
		foreach ( $countries as $country_code ) {
			// Skip if already added as part of EU.
			$already_added = false;
			if ( $show_to_eu ) {
				foreach ( $group['locations'] as $location ) {
					if ( isset( $location['code'] ) && strtoupper( $location['code'] ) === strtoupper( $country_code ) ) {
						$already_added = true;
						break;
					}
				}
			}

			if ( $already_added ) {
				continue;
			}

			$group['locations'][] = array(
				'type' => 'country',
				'code' => strtoupper( $country_code ),
			);
		}
	}

	// Save the new group.
	$location_groups[ $group['id'] ] = $group;
	wpconsent()->settings->update_option( 'geolocation_groups', $location_groups );

	// Check if we need to add the geolocation cookie
	wpconsent()->geolocation->maybe_add_geolocation_cookie();
}
