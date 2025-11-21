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

		// Helper function to update button texts in the DOM.
		function updateButtonTexts( data ) {
			// Ensure WPConsent and its shadow root are available.
			if ( !window.WPConsent || !window.WPConsent.shadowRoot ) {
				console.warn( 'WPConsent or its shadow root is not available yet' );
				return;
			}

			// Update accept button text.
			if ( data.hasOwnProperty( 'accept_button_text' ) && data.accept_button_text ) {
				const acceptButton = WPConsent.shadowRoot.querySelector( '#wpconsent-accept-all' );
				if ( acceptButton ) {
					acceptButton.textContent = data.accept_button_text;
				}
			}

			// Update cancel button text.
			if ( data.hasOwnProperty( 'cancel_button_text' ) && data.cancel_button_text ) {
				const cancelButton = WPConsent.shadowRoot.querySelector( '#wpconsent-cancel-all' );
				if ( cancelButton ) {
					cancelButton.textContent = data.cancel_button_text;
				}
			}

			// Update preferences button text.
			if ( data.hasOwnProperty( 'preferences_button_text' ) && data.preferences_button_text ) {
				const preferencesButton = WPConsent.shadowRoot.querySelector( '#wpconsent-preferences-all' );
				if ( preferencesButton ) {
					preferencesButton.textContent = data.preferences_button_text;
				}
			}
		}

		// Helper function to update banner message in the DOM.
		function updateBannerMessage( data ) {
			// Ensure WPConsent and its shadow root are available.
			if ( !window.WPConsent || !window.WPConsent.shadowRoot ) {
				console.warn( 'WPConsent or its shadow root is not available yet' );
				return;
			}

			// Update banner message text.
			if ( data.hasOwnProperty( 'banner_message' ) && data.banner_message ) {
				const bannerMessage = WPConsent.shadowRoot.querySelector( '.wpconsent-banner-message' );
				if ( bannerMessage ) {
					bannerMessage.innerHTML = data.banner_message;
				}
			}
		}

		// Helper function to schedule banner message updates after banner initialization.
		function scheduleBannerMessageUpdates( data ) {
			// If shadow root is already available, update immediately.
			if ( window.WPConsent && window.WPConsent.shadowRoot ) {
				updateBannerMessage( data );
				return;
			}

			// Otherwise, wait for banner initialization event.
			const updateHandler = function () {
				updateBannerMessage( data );
				window.removeEventListener( 'wpconsent_banner_initialized', updateHandler );
			};

			window.addEventListener( 'wpconsent_banner_initialized', updateHandler );
		}

		// Helper function to schedule button text updates after banner initialization.
		function scheduleButtonTextUpdates( data ) {
			// If shadow root is already available, update immediately.
			if ( window.WPConsent && window.WPConsent.shadowRoot ) {
				updateButtonTexts( data );
				return;
			}

			// Otherwise, wait for banner initialization event.
			const updateHandler = function () {
				updateButtonTexts( data );
				window.removeEventListener( 'wpconsent_banner_initialized', updateHandler );
			};

			window.addEventListener( 'wpconsent_banner_initialized', updateHandler );
		}

		// Helper function to update preferences toggles when default_allow applies.
		function updatePreferencesToggles( defaultAllowOverride ) {
			// Ensure WPConsent and its shadow root are available.
			if ( !window.WPConsent || !window.WPConsent.shadowRoot ) {
				console.warn( 'WPConsent or its shadow root is not available yet' );
				return;
			}

			// Use override value if provided, otherwise use global wpconsent.default_allow.
			const defaultAllow = typeof defaultAllowOverride !== 'undefined' ? defaultAllowOverride : wpconsent.default_allow;

			// If no preferences cookie is set and default_allow is true, enable all toggles.
			if ( !WPConsent.getCookie( 'wpconsent_preferences' ) && defaultAllow ) {
				// Enable all category checkboxes.
				const categoryCheckboxes = WPConsent.shadowRoot.querySelectorAll( '#wpconsent-preferences-modal input[type="checkbox"][id^="cookie-category-"]' );
				categoryCheckboxes.forEach( checkbox => {
					if ( !checkbox.disabled ) {
						checkbox.checked = true;
					}
				} );

				// Enable all service checkboxes.
				const serviceCheckboxes = WPConsent.shadowRoot.querySelectorAll( '#wpconsent-preferences-modal input[type="checkbox"][id^="cookie-service-"]' );
				serviceCheckboxes.forEach( checkbox => {
					if ( !checkbox.disabled ) {
						checkbox.checked = true;
					}
				} );
			}
		}

		// Helper function to schedule preferences toggle updates after banner initialization.
		function schedulePreferencesToggleUpdates( defaultAllowOverride ) {
			// If shadow root is already available, update immediately.
			if ( window.WPConsent && window.WPConsent.shadowRoot ) {
				updatePreferencesToggles( defaultAllowOverride );
				return;
			}

			// Otherwise, wait for banner initialization event.
			const updateHandler = function () {
				updatePreferencesToggles( defaultAllowOverride );
				window.removeEventListener( 'wpconsent_banner_initialized', updateHandler );
			};

			window.addEventListener( 'wpconsent_banner_initialized', updateHandler );
		}

		// Helper function to apply geolocation data to settings.
		function applyGeolocationData( data, settings ) {
			if ( data.use_default ) {
				// If we should use the default and we have default allow true we should grant consent mode but only if the preferences cookie is not yet set.
				if ( settings.original_default_allow && !WPConsent.getCookie( 'wpconsent_preferences' ) ) {
					WPConsent.localGtag( 'consent', 'update', {
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
				settings.accept_button_enabled = settings.original_accept_button_enabled;
				settings.cancel_button_enabled = settings.original_cancel_button_enabled;
				settings.preferences_button_enabled = settings.original_preferences_button_enabled;
				settings.banner_message = settings.original_banner_message;

				// Schedule toggle updates for the preferences panel with the correct default_allow value.
				schedulePreferencesToggleUpdates( settings.original_default_allow );

				return;
			}
			// Update settings based on geolocation data.
			if ( data.show_banner === false ) {
				// If the user is in a country where we don't need to show the banner.
				const preferences = {essential: true, statistics: true, marketing: true};
				// Save preferences to a cookie for this session.
				WPConsent.setCookie( 'wpconsent_preferences', JSON.stringify( preferences ), 0 );
				// Unlock scripts based on these preferences
				WPConsent.unlockScripts( preferences );
				WPConsent.unlockIframes( preferences );
			}

			// Store country information if available.
			if ( data.country ) {
				settings.user_country = data.country;
			}

			// Update banner settings based on geolocation.
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
				// Update consent_type based on consent_mode.
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

			// Update button visibility settings based on geolocation.
			if ( data.hasOwnProperty( 'accept_button_enabled' ) ) {
				settings.accept_button_enabled = data.accept_button_enabled;
			}

			if ( data.hasOwnProperty( 'cancel_button_enabled' ) ) {
				settings.cancel_button_enabled = data.cancel_button_enabled;
			}

			if ( data.hasOwnProperty( 'preferences_button_enabled' ) ) {
				settings.preferences_button_enabled = data.preferences_button_enabled;
			}

			// Update button text settings based on geolocation.
			if ( data.hasOwnProperty( 'accept_button_text' ) ) {
				settings.accept_button_text = data.accept_button_text;
			}

			if ( data.hasOwnProperty( 'cancel_button_text' ) ) {
				settings.cancel_button_text = data.cancel_button_text;
			}

			if ( data.hasOwnProperty( 'preferences_button_text' ) ) {
				settings.preferences_button_text = data.preferences_button_text;
			}

			// Update button order based on geolocation.
			if ( data.hasOwnProperty( 'button_order' ) && Array.isArray( data.button_order ) ) {
				settings.button_order = data.button_order;
			}

			// Update banner message based on geolocation.
			if ( data.hasOwnProperty( 'banner_message' ) ) {
				settings.banner_message = data.banner_message;
			}

			// Schedule button text updates after banner is initialized.
			scheduleButtonTextUpdates( data );

			// Schedule banner message updates after banner is initialized.
			scheduleBannerMessageUpdates( data );

			// Schedule toggle updates for the preferences panel.
			schedulePreferencesToggleUpdates();
		}
	}
)();
