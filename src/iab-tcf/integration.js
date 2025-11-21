/**
 * IAB TCF Integration with Core Banner
 *
 * This module bridges IAB TCF functionality with the core WPConsent banner
 * using the hook system to maintain clean separation of concerns.
 *
 * @package WPConsent
 */

( function () {
	'use strict';

	// Only initialize if IAB TCF is enabled.
	if ( !window.wpconsent || !window.wpconsent.iab_tcf_enabled ) {
		return;
	}

	// Wait for WPConsent to be ready.
	const initIABTCFIntegration = function () {
		if ( !window.WPConsent || !window.WPConsent.addHook ) {
			// Try again after a short delay.
			setTimeout( initIABTCFIntegration, 100 );
			return;
		}

		// Register hook for when preferences modal is shown.
		window.WPConsent.addHook( 'afterShowPreferences', function () {
			// Notify CMP API that UI is visible.
			if ( window.WPConsentCMPAPI && typeof window.WPConsentCMPAPI.notifyUIVisible === 'function' ) {
				window.WPConsentCMPAPI.notifyUIVisible();
			}
		} );

		// Register hook for when banner is shown.
		window.WPConsent.addHook( 'afterShowBanner', function () {
			// Notify CMP API that UI is visible.
			if ( window.WPConsentCMPAPI && typeof window.WPConsentCMPAPI.notifyUIVisible === 'function' ) {
				window.WPConsentCMPAPI.notifyUIVisible();
			}
		} );

		// Register hook for when banner is hidden.
		window.WPConsent.addHook( 'afterHideBanner', function () {
			// Notify CMP API that UI is hidden.
			if ( window.WPConsentCMPAPI && typeof window.WPConsentCMPAPI.notifyUIHidden === 'function' ) {
				window.WPConsentCMPAPI.notifyUIHidden();
			}
		} );

		// Register hook for accordion toggle events.
		window.WPConsent.addHook( 'accordionToggled', function ( data ) {
			const { accordion, content, isActive, isService } = data;

			// Only handle IAB vendor accordions.
			if ( !accordion.hasAttribute( 'data-iab-vendor' ) ) {
				return;
			}

			// Fire vendor events for IAB vendors.
			const eventType = isActive ? 'vendorDetailsOpened' : 'vendorDetailsClosed';
			fireVendorEvent( eventType, accordion );
		} );
	};

	// Fire vendor event for IAB TCF vendors.
	const fireVendorEvent = function ( eventType, accordion ) {
		const vendorData = accordion.getAttribute( 'data-vendor-data' );
		const vendorId = accordion.getAttribute( 'data-vendor-id' );

		if ( vendorData && vendorId ) {
			try {
				const parsedVendorData = JSON.parse( vendorData );
				const customEvent = new CustomEvent( eventType, {
					detail: {
						vendorId: vendorId,
						vendorData: parsedVendorData,
						accordion: accordion,
						content: accordion.querySelector( '.wpconsent-preferences-accordion-content' )
					}
				} );

				// Dispatch the event on the document.
				document.dispatchEvent( customEvent );
			} catch ( error ) {
				console.error( 'Error parsing vendor data for event:', error );
			}
		}
	};

	// Initialize the integration.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initIABTCFIntegration );
	} else {
		// DOM is already loaded.
		initIABTCFIntegration();
	}
} )();