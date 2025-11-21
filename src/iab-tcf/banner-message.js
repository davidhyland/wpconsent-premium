/**
 * IAB TCF Banner Message Handler
 *
 * This module handles the dynamic replacement of vendor count placeholder
 * in the IAB TCF compliant banner message.
 *
 * @package WPConsent
 */

import { GVL } from '@iabtechlabtcf/core';

class WPConsentIABBannerMessage {
	constructor() {
		this.gvl = null;
		this.vendorCount = 0;

		// Listen for the banner initialized event.
		window.addEventListener( 'wpconsent_banner_initialized', () => {
			this.init();
		} );
	}

	/**
	 * Initialize the banner message handler.
	 */
	async init() {
		try {
			// Check if IAB TCF is enabled.
			if ( ! window.wpconsent || ! window.wpconsent.iab_tcf_enabled_vendors ) {
				return;
			}

			// Check if GVL configuration is available.
			if ( ! window.wpconsent.iab_tcf_baseurl ) {
				console.warn( 'IAB TCF GVL configuration not found for banner message' );
				return;
			}

			// Initialize GVL if not already done.
			GVL.baseUrl = window.wpconsent.iab_tcf_baseurl;
			this.gvl = new GVL();

			// Wait for GVL to be ready.
			await this.gvl.readyPromise;

			// Get enabled vendor count from configuration.
			const enabledVendorIds = window.wpconsent.iab_tcf_enabled_vendors || [];
			this.vendorCount = enabledVendorIds.length;

			// Replace the placeholder with actual vendor count.
			this.replacePlaceholder();

			// Setup click handler for vendors link.
			this.setupVendorsLink();

		} catch ( error ) {
			console.error( 'Error initializing IAB TCF banner message handler:', error );
		}
	}

	/**
	 * Replace the [number_of_vendors] placeholder with actual count.
	 */
	replacePlaceholder() {
		// Check if shadow root is available.
		if ( ! window.WPConsent || ! window.WPConsent.shadowRoot ) {
			console.warn( 'Shadow root not available for banner message replacement' );
			return;
		}

		// Find the banner message element within the shadow root.
		const bannerMessage = window.WPConsent.shadowRoot.querySelector( '.wpconsent-banner-message' );
		if ( ! bannerMessage ) {
			console.warn( 'Banner message element not found' );
			return;
		}

		// Get the current message text.
		let messageHTML = bannerMessage.innerHTML;

		// Replace the placeholder with the actual vendor count.
		if ( messageHTML.includes( '[number_of_vendors]' ) ) {
			messageHTML = messageHTML.replace( '[number_of_vendors]', this.vendorCount.toString() );
			bannerMessage.innerHTML = messageHTML;
		}
	}

	/**
	 * Setup click handler for the vendors link in the banner.
	 */
	setupVendorsLink() {
		// Check if shadow root is available.
		if ( ! window.WPConsent || ! window.WPConsent.shadowRoot ) {
			console.warn( 'Shadow root not available for vendors link setup' );
			return;
		}

		// Find the vendors link within the shadow root.
		const vendorsLink = window.WPConsent.shadowRoot.querySelector( '#wpconsent-view-vendors-link' );
		if ( ! vendorsLink ) {
			return;
		}

		// Add click event listener.
		vendorsLink.addEventListener( 'click', ( e ) => {
			e.preventDefault();

			// Open the preferences modal.
			if ( window.WPConsent && typeof window.WPConsent.showPreferences === 'function' ) {
				window.WPConsent.showPreferences();

				// Wait for modal to be visible, then switch to vendors tab.
				setTimeout( () => {
					if ( window.WPConsentIABTabs && typeof window.WPConsentIABTabs.switchTab === 'function' ) {
						// Find the vendors tab button.
						const vendorsTabButton = window.WPConsent.shadowRoot?.querySelector( '.wpconsent-tcf-tab-button[data-tab="vendors"]' );
						if ( vendorsTabButton ) {
							window.WPConsentIABTabs.switchTab( vendorsTabButton );
						}
					}
				}, 100 );
			}
		} );
	}
}

// Initialize the banner message handler.
new WPConsentIABBannerMessage();
