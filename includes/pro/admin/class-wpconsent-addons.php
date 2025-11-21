<?php
/**
 * This class handles WPConsent addons and licensing functionality.
 *
 * @package WPConsent
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains helper methods specific to the addons.
 *
 * @since 2.0.7
 */
class WPConsent_Addons {
	/**
	 * Holds our list of loaded addons.
	 *
	 * @since 2.0.7
	 *
	 * @var array
	 */
	protected $loadedAddons = [];

	/**
	 * The addons URL.
	 *
	 * @since 2.0.7
	 *
	 * @var string
	 */
	protected $addons_url = 'https://licensing.wpconsent.com/keys/lite/wpconsent-premium.json';

	/**
	 * Returns our addons.
	 *
	 * @param boolean $fluch_cache Whether or not to flush the cache.
	 *
	 * @return array               An array of addon data.
	 * @since 2.0.7
	 *
	 */
	public function get_addons( $fluch_cache = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$addons = wpconsent()->file_cache->get( 'addons', DAY_IN_SECONDS );
		if ( false === $addons || $fluch_cache ) {
			$response = wp_remote_get( $this->get_addons_url() );
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$addons = json_decode( wp_remote_retrieve_body( $response ) );
			}

			if ( ! $addons || ! empty( $addons->error ) ) {
				$addons = $this->get_default_addons();
			}

			wpconsent()->file_cache->set( 'addons', $addons );
		} else {
			$addons = json_decode( wp_json_encode( $addons ) );
		}

		if ( is_null( $addons ) ) {
			$addons = $this->get_default_addons();
		}

		$installedPlugins = array_keys( get_plugins() );
		foreach ( $addons as $key => $addon ) {
			$addons[ $key ]->basename          = $this->get_addon_basename( $addon->sku );
			$addons[ $key ]->installed         = in_array( $this->get_addon_basename( $addon->sku ), $installedPlugins, true );
			$addons[ $key ]->isActive          = is_plugin_active( $addons[ $key ]->basename );
			$addons[ $key ]->canInstall        = $this->can_install();
			$addons[ $key ]->canActivate       = $this->can_activate();
			$addons[ $key ]->canUpdate         = $this->can_update();
			$addons[ $key ]->capability        = $this->get_manage_capability( $addon->sku );
			$addons[ $key ]->minimumVersion    = '0.0.0';
			$addons[ $key ]->hasMinimumVersion = false;
		}

		return $addons;
	}

	/**
	 * Returns the required capability to manage the addon.
	 * For now, we're using the same capability for all addons.
	 *
	 * @param string $sku The addon sku.
	 *
	 * @return string      The required capability.
	 * @since 2.0.7
	 *
	 */
	protected function get_manage_capability( $sku ) {
		return 'install_plugins'; // This can be changed to a more specific capability if needed.
	}

	/**
	 * Check to see if there are unlicensed addons installed and activated.
	 *
	 * @return array True if there are unlicensed addons, false if not.
	 * @since 2.0.7
	 *
	 */
	public function unlicensed_addons() {
		$unlicensed = [
			'addons'  => [],
			// Translators: 1 - Opening bold tag, 2 - Plugin short name ("WPConsent"), 3 - "Pro", 4 - Closing bold tag.
			'message' => sprintf(
			// Translators: 1 - Opening HTML strong tag, 2 - The short plugin name ("WPConsent"), 3 - "Pro", 4 - Closing HTML strong tag.
				__( 'The following addons cannot be used, because they require %1$s%2$s %3$s%4$s to work:', 'wpconsent-premium' ),
				'<strong>',
				'WPConsent',
				'Pro',
				'</strong>'
			)
		];

		$addons = $this->get_addons();
		foreach ( $addons as $addon ) {
			if ( $addon->isActive ) {
				$unlicensed['addons'][] = $addon;
			}
		}

		return $unlicensed;
	}

	/**
	 * Get the data for a specific addon.
	 *
	 * We need this function to refresh the data of a given addon because installation links expire after one hour.
	 *
	 * @param string $sku The addon sku.
	 * @param bool   $flushCache Whether or not to flush the cache.
	 *
	 * @return null|object             The addon.
	 * @since 2.0.7
	 *
	 */
	public function get_addon( $sku, $flushCache = false ) {
		$addon     = null;
		$allAddons = $this->get_addons( $flushCache );
		foreach ( $allAddons as $a ) {
			if ( $sku === $a->sku ) {
				$addon = $a;
			}
		}

		if ( ! $addon || ! empty( $addon->error ) ) {
			$addon = $this->get_default_addon( $sku );
		}

		return $addon;
	}

	/**
	 * Checks if the specified addon is activated.
	 *
	 * @param string $sku The sku to check.
	 *
	 * @return string      The addon basename.
	 * @since 4.0.0
	 *
	 */
	public function get_addon_basename( $sku ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		$keys = array_keys( $plugins );
		foreach ( $keys as $key ) {
			if ( preg_match( '|^' . $sku . '|', $key ) ) {
				return $key;
			}
		}

		return $sku;
	}

	/**
	 * Returns an array of levels connected to an addon.
	 *
	 * @param string $addon_name The addon name.
	 *
	 * @return array             The array of levels.
	 * @since 4.0.0
	 *
	 */
	public function get_addon_levels( $addon_name ) {
		$addons = $this->get_addons();
		foreach ( $addons as $addon ) {
			if ( $addon_name !== $addon->sku ) {
				continue;
			}

			if ( ! isset( $addon->levels ) ) {
				return [];
			}

			return $addon->levels;
		}

		return [];
	}

	/**
	 * Get the URL to get addons.
	 *
	 * @return string The URL.
	 * @since 4.1.8
	 *
	 */
	protected function get_addons_url() {
		$url = $this->addons_url;
		if ( defined( 'WPCONSENT_ADDONS_URL' ) ) {
			$url = WPCONSENT_ADDONS_URL;
		}

		return $url;
	}


	/**
	 * Installs and activates a given addon or plugin.
	 *
	 * @param string $name The addon name/sku.
	 * @param bool   $network Whether or not we are in a network environment.
	 *
	 * @return bool            Whether or not the installation was succesful.
	 * @since 4.0.0
	 *
	 */
	public function install_addon( $name, $network = false ) {
		if ( ! $this->can_install() ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		// Set the current screen to avoid undefined notices.
		set_current_screen( 'toplevel_page_wpconsent' );

		// Prepare variables.
		$url = esc_url_raw(
			add_query_arg(
				array(
					'page' => 'wpconsent',
				),
				admin_url( 'admin.php' )
			)
		);

		// Do not allow WordPress to search/download translations, as this will break JS output.
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

		wpconsent_require_upgrader();

		// Create the plugin upgrader with our custom skin.
		$installer = new Plugin_Upgrader( new WPConsent_Skin() );

		$addon = $this->get_addon( $name );
		if ( ! empty( $addon->installed ) && isset( $addon->basename ) ) {
			$activated = activate_plugin( $addon->basename, '', $network );

			if ( ! is_wp_error( $activated ) ) {
				return $name;
			}
		}

		$downloadUrl = wpconsent()->addons->get_download_url( $name, $network );
		if ( empty( $downloadUrl ) ) {
			return false;
		}
		$installLink = $downloadUrl;

		$installer->install( $installLink );

		// Flush the cache and return the newly installed plugin basename.
		wp_cache_flush();

		$pluginBasename = $installer->plugin_info();
		if ( ! $pluginBasename ) {
			return false;
		}

		// Activate the plugin silently.
		$activated = activate_plugin( $pluginBasename, '', $network );

		if ( is_wp_error( $activated ) ) {
			return false;
		}

		return $pluginBasename;
	}

	/**
	 * Determine if addons/plugins can be installed.
	 *
	 * @return bool True if yes, false if not.
	 * @since 4.0.0
	 *
	 */
	public function can_install() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		// Determine whether file modifications are allowed.
		if ( ! wp_is_file_mod_allowed( 'wpconsent_can_install' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine if addons/plugins can be updated.
	 *
	 * @return bool True if yes, false if not.
	 * @since 4.1.6
	 *
	 */
	public function can_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return false;
		}

		// Determine whether file modifications are allowed.
		if ( ! wp_is_file_mod_allowed( 'wpconsent_can_update' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine if addons/plugins can be activated.
	 *
	 * @return bool True if yes, false if not.
	 * @since 4.1.3
	 *
	 */
	public function can_activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Load an addon into wpconsent
	 *
	 * @param string $slug
	 * @param object $addon Addon class instance
	 *
	 * @return void
	 * @since 2.0.7
	 *
	 */
	public function load_addon( $slug, $addon ) {
		$this->{$slug}        = $addon;
		$this->loadedAddons[] = $slug;
	}

	/**
	 * Return a loaded addon
	 *
	 * @param string $slug
	 *
	 * @return object|null
	 * @since 2.0.7
	 *
	 */
	public function get_loaded_addon( $slug ) {
		return isset( $this->{$slug} ) ? $this->{$slug} : null;
	}

	/**
	 * Returns loaded addons
	 *
	 * @return array
	 * @since 2.0.7
	 *
	 */
	public function get_loaded_addons() {
		$loadedAddonsList = [];
		if ( ! empty( $this->loadedAddons ) ) {
			foreach ( $this->loadedAddons as $addonSlug ) {
				$loadedAddonsList[ $addonSlug ] = $this->{$addonSlug};
			}
		}

		return $loadedAddonsList;
	}

	/**
	 * Run a function through all addons that support it.
	 *
	 * @param string $class The class name.
	 * @param string $function The function name.
	 * @param array  $args The args for the function.
	 *
	 * @return array            The response from each addon.
	 * @since 4.2.3
	 *
	 */
	public function doFunction( $class, $function, $args = [] ) {
		$addonResponses = [];

		foreach ( $this->get_loaded_addons() as $addonSlug => $addon ) {
			if ( isset( $addon->$class ) && method_exists( $addon->$class, $function ) ) {
				$addonResponses[ $addonSlug ] = call_user_func_array( [ $addon->$class, $function ], $args );
			}
		}

		return $addonResponses;
	}

	/**
	 * Retrieves a default addon with whatever information is needed if the API cannot be reached.
	 *
	 * @param string $sku The sku of the addon.
	 *
	 * @return array       An array of addon data.
	 * @since 4.0.0
	 *
	 */
	public function get_default_addon( $sku ) {
		$addons = $this->get_default_addons();
		$addon  = [];
		foreach ( $addons as $a ) {
			if ( $a->sku === $sku ) {
				$addon = $a;
			}
		}

		return $addon;
	}

	/**
	 * Retrieves a default list of addons if the API cannot be reached.
	 *
	 * @return array An array of addons.
	 * @since 4.0.0
	 *
	 */
	protected function get_default_addons() {
		return json_decode( wp_json_encode( [
			[
				'sku'                => 'wpconsent-do-not-track',
				'name'               => 'Do Not Track Addon',
				'version'            => '1.0.0',
				'image'              => null,
				'icon'               => 'code',
				'levels'             => [
					'agency',
					'elite',
					'pro',
					'plus'
				],
				'currentLevels'      => [
					'pro',
					'elite'
				],
				'requiresUpgrade'    => true,
				'description'        => '<p>Our Do Not Track Addon makes it easy to handle Do Not Track requests from your visitors.</p>',
				// phpcs:ignore Generic.Files.LineLength.MaxExceeded
				'descriptionVersion' => 0,
				'productUrl'         => 'https://wpconsent.com/features/do-not-track/',
				'learnMoreUrl'       => 'https://wpconsent.com/features/do-not-track/',
				'manageUrl'          => '?page=wpconsent-do-not-track',
				'basename'           => 'wpconsent-do-not-track/wpconsent-do-not-track.php',
				'installed'          => false,
				'isActive'           => false,
				'canInstall'         => false,
				'canActivate'        => false,
				'canUpdate'          => false,
				'capability'         => $this->get_manage_capability( 'wpconsent-do-not-track' ),
				'minimumVersion'     => '0.0.0',
				'hasMinimumVersion'  => false
			],
		] ) );
	}

	/**
	 * Get the plugin path for an addon.
	 *
	 * @param string $addon_name The addon name.
	 *
	 * @return string|false The plugin path or false if not found.
	 */
	public function get_plugin_path( $addon_name ) {
		$plugins = get_plugins();
		foreach ( $plugins as $plugin_path => $plugin_data ) {
			if ( strpos( $plugin_path, $addon_name ) === 0 ) {
				return $plugin_path;
			}
		}

		return false;
	}

	/**
	 * Check for updates for all addons.
	 *
	 * @return void
	 * @since 4.2.4
	 *
	 */
	public function register_update_check() {
	}

	/**
	 * Get the download URL for an addon.
	 *
	 * @param string $sku The addon name.
	 *
	 * @return string The download URL.
	 */
	public function get_download_url( $sku ) {
		return '';
	}
}

new WPConsent_Addons();
