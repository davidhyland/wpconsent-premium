(
	function () {
		const geolocation_enabled = wpconsent.geolocation?.enabled;

		if ( !geolocation_enabled ) {
			return;
		}
		if ( typeof wpconsentPreferences !== 'undefined' ) {
			return;
		}

		// Register a settings hook with WPConsent to update settings based on geolocation
		WPConsent.registerSettingsHook( function ( settings ) {
			return new Promise( ( resolve, reject ) => {
				// Get the user's geolocation from our API
				const api_url = wpconsent.geolocation?.api_url;

				if ( !api_url ) {
					console.error( 'Geolocation API URL not found' );
					resolve();
					return;
				}

				// Check if we have cached geolocation data
				const cachedGeoData = WPConsent.getCookie( 'wpconsent_geolocation' );

				if ( cachedGeoData ) {
					try {
						// Parse the cached data
						const data = JSON.parse( cachedGeoData );

						// Apply the cached geolocation data to settings
						applyGeolocationData( data, settings );

						// Resolve the promise to continue banner initialization
						resolve();
						return;
					} catch ( error ) {
						console.error( 'Error parsing cached geolocation data:', error );
						// Continue with the fetch request if there's an error parsing the cached data
					}
				}

				// Make the request if no cached data exists or if there was an error parsing it
				fetch( api_url )
					.then( response => response.json() )
					.then( data => {
						// Cache the geolocation data in a cookie (30 days expiration)
						WPConsent.setCookie( 'wpconsent_geolocation', JSON.stringify( data ), 30 );

						// Apply the geolocation data to settings
						applyGeolocationData( data, settings );

						// Resolve the promise to continue banner initialization
						resolve();
					} )
					.catch( error => {
						console.error( 'Geolocation settings hook failed:', error );
						// If there's an error, resolve anyway to continue with default settings
						resolve();
					} );
			} );
		} );

		// Helper function to apply geolocation data to settings
		function applyGeolocationData( data, settings ) {
			if ( data.use_default ) {
				// If we should use the default and we have default allow true we should update the consent mode if gtag is defined.
				if ( settings.original_default_allow && typeof gtag === 'function' ) {
					gtag( 'consent', 'update', {
						'ad_storage': 'granted',
						'analytics_storage': 'granted',
						'ad_user_data': 'granted',
						'ad_personalization': 'granted',
						'security_storage': 'granted',
						'functionality_storage': 'granted'
					} );
				}

				settings.default_allow = settings.original_default_allow;
				settings.enable_consent_banner = settings.original_enable_consent_banner;
				settings.enable_script_blocking = settings.original_enable_script_blocking;

				return;
			}
			// Update settings based on geolocation data
			if ( data.show_banner === false ) {
				// If the user is in a country where we don't need to show the banner
				const preferences = {essential: true, statistics: true, marketing: true};
				// Save preferences to a cookie for this session
				WPConsent.setCookie( 'wpconsent_preferences', JSON.stringify( preferences ), 0 );
				// Unlock scripts based on these preferences
				WPConsent.unlockScripts( preferences );
				WPConsent.unlockIframes( preferences );
			}

			// Store country information if available
			if ( data.country ) {
				settings.user_country = data.country;
			}

			// Update banner settings based on geolocation
			if ( data.hasOwnProperty( 'enable_script_blocking' ) ) {
				settings.enable_script_blocking = data.enable_script_blocking;
			}

			if ( data.hasOwnProperty( 'enable_consent_floating' ) ) {
				settings.enable_consent_floating = data.enable_consent_floating;
			}

			if ( data.hasOwnProperty( 'manual_toggle_services' ) ) {
				settings.manual_toggle_services = data.manual_toggle_services;
			}

			if ( data.hasOwnProperty( 'consent_mode' ) ) {
				settings.consent_mode = data.consent_mode;
				// Update consent_type based on consent_mode
				if ( data.consent_mode === 'optin' ) {
					settings.consent_type = 'optin';
					settings.default_allow = false;
				} else if ( data.consent_mode === 'optout' ) {
					settings.consent_type = 'optout';
					settings.default_allow = true;
				}
			}

			if ( data.hasOwnProperty( 'show_banner' ) ) {
				settings.show_banner = data.show_banner;
			}
		}
	}
)();
