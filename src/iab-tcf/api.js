/**
 * IAB TCF CMP API Module
 *
 * This module implements the CMP API for handling consent data based on IAB TCF v2.2 specifications.
 * It manages TCString generation, consent saving, and integration with the banner preferences system.
 * Uses the official IAB CMP API from @iabtechlabtcf/cmpapi.
 *
 * @package WPConsent
 */

import { GVL, TCModel, TCString, PurposeRestriction, RestrictionType } from '@iabtechlabtcf/core';
import { CmpApi } from '@iabtechlabtcf/cmpapi';

class WPConsentCMPAPI {
	constructor() {
		this.tcModel = null;
		this.gvl = null;
		this.cmpApi = null;
		this.isInitialized = false;
		this.consentData = {
			purposes: new Set(),
			vendors: new Set(),
			specialFeatures: new Set(),
			legitimateInterests: new Set()
		};

		// Initialize CMP API immediately to make __tcfapi available.
		this.initializeCMPApiEarly();

		// Listen for banner initialization to proceed with full initialization.
		window.addEventListener('wpconsent_banner_initialized', () => {
			this.init();
		});

		// Listen for preference changes.
		window.addEventListener('wpconsent_consent_saved', (event) => {
			this.handlePreferencesUpdate(event);
		});
	}

	/**
	 * Initialize CMP API immediately to make __tcfapi available as early as possible.
	 * This signals tcloaded with an empty TC string to indicate GDPR applies but no consent data yet.
	 */
	initializeCMPApiEarly() {
		try {
			// Check if basic configuration is available.
			if (!window.wpconsent) {
				console.warn('WPConsent configuration not found, deferring early CMP initialization');
				return;
			}

			// Get basic CMP configuration with fallback defaults.
			const cmpId = parseInt(window.wpconsent.iab_tcf_cmp_id || 999, 10);
			const cmpVersion = parseInt(window.wpconsent.iab_tcf_cmp_version || 1, 10);
			const isServiceSpecific = window.wpconsent.iab_tcf_service_specific || false;

			// Initialize the official IAB CMP API immediately.
			this.cmpApi = new CmpApi(cmpId, cmpVersion, isServiceSpecific);

			// Check for existing TCString in storage.
			const existingTCString = this.getStoredTCString();

			if (existingTCString) {
				// Signal with existing consent string.
				this.cmpApi.update(existingTCString, false);
			} else {
				// Signal tcloaded with empty string to indicate GDPR applies but no consent yet.
				this.cmpApi.update('', false);
			}

			// Expose API globally for early access.
			window.WPConsentCMPAPI = this;

		} catch (error) {
			console.error('Error during early CMP API initialization:', error);
			// Continue execution - the full initialization will be attempted later.
		}
	}

	/**
	 * Initialize the CMP API with GVL and TCModel.
	 */
	async init() {
		try {
			// Check if GVL configuration is available.
			if (!window.wpconsent || !window.wpconsent.iab_tcf_baseurl) {
				console.warn('IAB TCF GVL configuration not found');
				return;
			}

			// Initialize GVL.
			GVL.baseUrl = window.wpconsent.iab_tcf_baseurl;
			this.gvl = new GVL();

			// Wait for GVL to be ready.
			await this.gvl.readyPromise;

			// Narrow vendors to enabled ones.
			const enabledVendorIds = window.wpconsent.iab_tcf_enabled_vendors || [];
			if (enabledVendorIds.length > 0) {
				this.gvl.narrowVendorsTo(enabledVendorIds);
			}

			// Initialize TCModel with GVL.
			this.tcModel = new TCModel(this.gvl);

			// Set basic TCModel properties.
			this.tcModel.cmpId = parseInt(window.wpconsent.iab_tcf_cmp_id || 999, 10);
			this.tcModel.cmpVersion = parseInt(window.wpconsent.iab_tcf_cmp_version || 1, 10);
			this.tcModel.consentScreen = 1;
			this.tcModel.consentLanguage = window.wpconsent.iab_tcf_language || 'EN';
			this.tcModel.isServiceSpecific = window.wpconsent.iab_tcf_service_specific || false;

			// Set vendorsDisclosed to all enabled vendors immediately.
			// This must be set from the start so validators see consistent disclosed vendors.
			if ( enabledVendorIds.length > 0 ) {
				enabledVendorIds.forEach(vendorId => {
					this.tcModel.vendorsDisclosed.set(vendorId);
				});
			} else if ( this.gvl && this.gvl.vendors ) {
				// Fallback: use all vendors from GVL.
				Object.keys(this.gvl.vendors).forEach(vendorId => {
					this.tcModel.vendorsDisclosed.set(parseInt(vendorId, 10));
				});
			}

			// CMP API should already be initialized early - verify it exists.
			if (!this.cmpApi) {
				// Fallback: initialize CMP API if early initialization failed.
				const cmpId = parseInt(window.wpconsent.iab_tcf_cmp_id || 999, 10);
				const cmpVersion = parseInt(window.wpconsent.iab_tcf_cmp_version || 1, 10);
				const isServiceSpecific = window.wpconsent.iab_tcf_service_specific || false;
				this.cmpApi = new CmpApi(cmpId, cmpVersion, isServiceSpecific);
			}

			// Load existing consent data if available.
			this.loadExistingConsent();

			// Initial update to make CMP API fully operational.
			// This ensures __tcfapi is available for validators.
			this.performInitialCMPUpdate();

			this.isInitialized = true;

			// Expose API globally.
			window.WPConsentCMPAPI = this;

		} catch (error) {
			console.error('Error initializing CMP API:', error);
		}
	}

	/**
	 * Perform CMP API update with fully initialized GVL and TCModel data.
	 * This updates the CMP API with real consent data after GVL loading is complete.
	 */
	performInitialCMPUpdate() {
		if (!this.cmpApi) {
			console.warn('CMP API not available for update');
			return;
		}

		try {
			// Generate current TCString from fully initialized TCModel.
			let tcString = '';
			if (this.tcModel) {
				try {
					tcString = TCString.encode(this.tcModel);
				} catch (encodeError) {
					console.warn('Failed to encode TCString from TCModel:', encodeError);
					// Fall back to stored TCString.
					tcString = this.getStoredTCString() || '';
				}
			} else {
				// Fall back to stored TCString if TCModel not available.
				tcString = this.getStoredTCString() || '';
			}

			// Check if UI is currently visible before updating.
			// We need to preserve the UI visibility state set by notifyUIVisible().
			const isUIVisible = this.isUICurrentlyVisible();

			// Update CMP API with the real TC string (or empty if no consent).
			this.cmpApi.update(tcString, isUIVisible);
		} catch (error) {
			console.warn('Error during CMP API update after full initialization:', error);
			// Fallback to empty string update to maintain functionality.
			try {
				this.cmpApi.update('', false);
			} catch (fallbackError) {
				console.error('Failed to update CMP API with fallback:', fallbackError);
			}
		}
	}

	/**
	 * Load existing consent data from storage.
	 */
	loadExistingConsent() {
		try {
			// Check for existing TCString in localStorage or cookies.
			const existingTCString = this.getStoredTCString();

			if (existingTCString) {
				// Decode existing consent string and update TCModel.
				const decodedModel = TCString.decode(existingTCString, this.tcModel);
				this.tcModel = decodedModel;

				// Re-ensure vendorsDisclosed includes all enabled vendors.
				// Old TCStrings might not have this set correctly.
				const enabledVendorIds = window.wpconsent.iab_tcf_enabled_vendors || [];
				if ( enabledVendorIds.length > 0 ) {
					// Clear and repopulate to ensure accuracy.
					this.tcModel.vendorsDisclosed.empty();
					enabledVendorIds.forEach(vendorId => {
						this.tcModel.vendorsDisclosed.set(vendorId);
					});
				}

				// Update consent data from decoded model.
				this.updateConsentDataFromTCModel();
			}
		} catch (error) {
			console.warn('Error loading existing consent:', error);
			// Continue with fresh consent model.
		}
	}

	/**
	 * Get stored TCString from localStorage or cookies.
	 *
	 * @return {string|null} The stored TCString or null if not found.
	 */
	getStoredTCString() {
		// Try localStorage first.
		try {
			const storedData = localStorage.getItem('wpconsent_tcstring');
			if (storedData) {
				return storedData;
			}
		} catch (error) {
			// localStorage might not be available.
		}

		// Fallback to cookies.
		const cookieName = 'wpconsent_tcstring';
		const cookies = document.cookie.split(';');

		for (let cookie of cookies) {
			const [name, value] = cookie.trim().split('=');
			if (name === cookieName) {
				return decodeURIComponent(value);
			}
		}

		return null;
	}

	/**
	 * Update consent data from TCModel vectors.
	 */
	updateConsentDataFromTCModel() {
		if (!this.tcModel) {
			return;
		}

		// Update purposes.
		this.consentData.purposes.clear();
		for (const purposeId of this.tcModel.purposeConsents) {
			this.consentData.purposes.add(purposeId);
		}

		// Update vendors.
		this.consentData.vendors.clear();
		for (const vendorId of this.tcModel.vendorConsents) {
			this.consentData.vendors.add(vendorId);
		}

		// Update special features.
		this.consentData.specialFeatures.clear();
		for (const featureId of this.tcModel.specialFeatureOptins) {
			this.consentData.specialFeatures.add(featureId);
		}

		// Update legitimate interests.
		this.consentData.legitimateInterests.clear();
		for (const purposeId of this.tcModel.purposeLegitimateInterests) {
			this.consentData.legitimateInterests.add(purposeId);
		}
	}

	/**
	 * Handle preference updates from the banner system.
	 *
	 * @param {CustomEvent} event The preference update event.
	 */
	handlePreferencesUpdate(event) {
		if (!this.isInitialized) {
			console.warn('CMP API not initialized yet');
			return;
		}


		const preferences = event.detail || {};
		if (!preferences) {
			return;
		}

		this.updateConsentFromPreferences(preferences);
	}

	/**
	 * Update consent data from preferences panel checkboxes.
	 *
	 * @param {Object} preferences The preferences data from checkboxes.
	 */
	updateConsentFromPreferences(preferences) {
		if (!this.tcModel || !window.WPConsent?.shadowRoot) {
			return;
		}


		// Loop through preferences and look for iab_purpose_1, iab_purpose_2, etc and use the id of the purpose from that to set the checkedPurposes.
		let checkedPurposes = [];
		let checkedPurposeLegints = [];
		for (const key in preferences) {
			if (key.startsWith('iab_purpose_') && preferences[key] === true) {
				const purposeId = parseInt(key.replace('iab_purpose_', ''), 10);
				if (!isNaN(purposeId)) {
					checkedPurposes.push(purposeId);
				}
			}
			// Collect purpose legitimate interests.
			if (key.startsWith('iab_purpose_legint_') && preferences[key] === true) {
				const purposeId = parseInt(key.replace('iab_purpose_legint_', ''), 10);
				if (!isNaN(purposeId)) {
					checkedPurposeLegints.push(purposeId);
				}
			}
		}

		// Check if this is an "Accept All" scenario by checking if all purposes are consented.
		const allPurposesCount = this.gvl?.purposes ? Object.keys(this.gvl.purposes).length : 0;
		const isAcceptAll = allPurposesCount > 0 && checkedPurposes.length === allPurposesCount;

		// Extract vendor data directly from shadowRoot form fields.
		let checkedVendorsConsent = [];
		let checkedVendorsLegint = [];

		// If "Accept All", set all enabled vendors to consented.
		if ( isAcceptAll && window.wpconsent.iab_tcf_enabled_vendors ) {
			checkedVendorsConsent = window.wpconsent.iab_tcf_enabled_vendors.slice();
		} else {
			// Get vendor consent checkboxes from shadowRoot.
			const vendorConsentCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('input[name="wpconsent_tcf_vendor_consent[]"]:checked');
			if (vendorConsentCheckboxes) {
				vendorConsentCheckboxes.forEach(checkbox => {
					const vendorId = parseInt(checkbox.value, 10);
					if (!isNaN(vendorId)) {
						checkedVendorsConsent.push(vendorId);
					}
				});
			}
		}

		// Get vendor legitimate interest checkboxes from shadowRoot.
		const vendorLegintCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('input[name="wpconsent_tcf_vendor_legint[]"]:checked');
		if (vendorLegintCheckboxes) {
			vendorLegintCheckboxes.forEach(checkbox => {
				const vendorId = parseInt(checkbox.value, 10);
				if (!isNaN(vendorId)) {
					checkedVendorsLegint.push(vendorId);
				}
			});
		}

		// Derive purpose legitimate interests from vendor legitimate interests using GVL.
		if (this.gvl && this.gvl.vendors && checkedVendorsLegint.length > 0) {
			const purposeLegintSet = new Set();
			checkedVendorsLegint.forEach(vendorId => {
				const vendor = this.gvl.vendors[vendorId];
				if (vendor && vendor.legIntPurposes) {
					vendor.legIntPurposes.forEach(purposeId => {
						purposeLegintSet.add(purposeId);
					});
				}
			});
			checkedPurposeLegints = Array.from(purposeLegintSet);
		}

		// Collect special features.
		let checkedSpecialFeatures = [];
		const specialFeatureCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('input[name="wpconsent_tcf_special_feature[]"]:checked');
		if (specialFeatureCheckboxes) {
			specialFeatureCheckboxes.forEach(checkbox => {
				const featureId = parseInt(checkbox.value, 10);
				if (!isNaN(featureId)) {
					checkedSpecialFeatures.push(featureId);
				}
			});
		}

		// Update internal consent data.
		this.consentData.vendors = new Set(checkedVendorsConsent);

		// Update TCModel vectors.
		this.updateTCModelVectors(checkedPurposes, checkedVendorsConsent, checkedVendorsLegint, checkedPurposeLegints, checkedSpecialFeatures);
		this.consentData.purposes = new Set(checkedPurposes);

		// Save updated consent.
		// Check if UI is currently visible instead of assuming it's not visible.
		const uiVisible = this.isUICurrentlyVisible();
		this.saveConsent(uiVisible).catch(error => {
			console.error('Error saving consent after preferences update:', error);
		});
	}

	/**
	 * Update TCModel vectors with consent data.
	 *
	 * @param {Array} purposes Array of purpose IDs for consent.
	 * @param {Array} vendorsConsent Array of vendor IDs for consent.
	 * @param {Array} vendorsLegint Array of vendor IDs for legitimate interest.
	 * @param {Array} purposeLegints Array of purpose IDs for legitimate interest.
	 * @param {Array} specialFeatures Array of special feature IDs.
	 */
	updateTCModelVectors( purposes, vendorsConsent = [], vendorsLegint = [], purposeLegints = [], specialFeatures = []) {
		if (!this.tcModel) {
			return;
		}

		// Clear existing vectors.
		this.tcModel.vendorConsents.empty();
		this.tcModel.vendorLegitimateInterests.empty();
		this.tcModel.purposeConsents.empty();
		this.tcModel.purposeLegitimateInterests.empty();
		this.tcModel.specialFeatureOptins.empty();
		this.tcModel.vendorsDisclosed.empty();
		this.tcModel.publisherConsents.empty();
		this.tcModel.publisherLegitimateInterests.empty();

		// Apply publisher restrictions before setting consent vectors.
		// This ensures that restricted purposes/vendors are filtered out.
		const restrictedData = this.applyPublisherRestrictions({
			purposes,
			vendorsConsent,
			vendorsLegint,
			purposeLegints,
			specialFeatures
		});

		// Set purpose consents (after restrictions applied).
		restrictedData.purposes.forEach(purposeId => {
			this.tcModel.purposeConsents.set(purposeId);
		});

		// Set purpose legitimate interests (after restrictions applied).
		restrictedData.purposeLegints.forEach(purposeId => {
			this.tcModel.purposeLegitimateInterests.set(purposeId);
		});

		// Set vendor consents (after restrictions applied).
		restrictedData.vendorsConsent.forEach(vendorId => {
			this.tcModel.vendorConsents.set(vendorId);
		});

		// Set vendor legitimate interests (after restrictions applied).
		restrictedData.vendorsLegint.forEach(vendorId => {
			this.tcModel.vendorLegitimateInterests.set(vendorId);
		});

		// Set special features (after restrictions applied).
		restrictedData.specialFeatures.forEach(featureId => {
			this.tcModel.specialFeatureOptins.set(featureId);
		});

		// Apply publisher restrictions to TCModel.
		this.setPublisherRestrictions();

		// Set publisher declarations (publisher's own purposes and features).
		this.setPublisherDeclarations();

		// Set vendorsDisclosed to include all vendors that were shown to user.
		// This must be ALL enabled vendors from configuration, not just consented ones.
		const enabledVendors = window.wpconsent.iab_tcf_enabled_vendors || [];
		if ( enabledVendors.length > 0 ) {
			enabledVendors.forEach(vendorId => {
				this.tcModel.vendorsDisclosed.set(vendorId);
			});
		} else {
			// Fallback: if no enabled vendors list, use all vendors from GVL.
			if ( this.gvl && this.gvl.vendors ) {
				Object.keys(this.gvl.vendors).forEach(vendorId => {
					this.tcModel.vendorsDisclosed.set(parseInt(vendorId, 10));
				});
			}
		}

		// Update timestamps - Per IAB TCF spec, Created and LastUpdated should have the same value.
		// Timestamps should have hours, minutes, and seconds zeroed out (set to midnight).
		const now = new Date();
		now.setHours( 0, 0, 0, 0 );
		this.tcModel.lastUpdated = now;
		this.tcModel.created = now;
	}

	/**
	 * Apply publisher restrictions to consent data before setting TCModel vectors.
	 *
	 * This method filters out purposes, vendors, and features based on publisher restrictions:
	 * - Global legitimate interest restrictions
	 * - Per-vendor purpose restrictions
	 * - Per-vendor special purpose restrictions
	 *
	 * @param {Object} data The consent data object containing purposes, vendors, etc.
	 * @return {Object} Filtered consent data with restrictions applied.
	 */
	applyPublisherRestrictions(data) {
		const restrictions = window.wpconsent?.iab_tcf_publisher_restrictions;

		// If no restrictions configured, return data unchanged.
		if (!restrictions || (!restrictions.global && !restrictions.vendors)) {
			return data;
		}

		const filtered = {
			purposes: [...data.purposes],
			vendorsConsent: [...data.vendorsConsent],
			vendorsLegint: [...data.vendorsLegint],
			purposeLegints: [...data.purposeLegints],
			specialFeatures: [...data.specialFeatures]
		};

		// Apply global legitimate interest restrictions.
		if (restrictions.global && restrictions.global.disallow_li_purposes) {
			const disallowedPurposes = restrictions.global.disallow_li_purposes;

			if (disallowedPurposes.includes('all')) {
				// Disallow ALL legitimate interest purposes globally.
				filtered.purposeLegints = [];
				filtered.vendorsLegint = [];
			} else if (Array.isArray(disallowedPurposes) && disallowedPurposes.length > 0) {
				// Disallow specific legitimate interest purposes.
				filtered.purposeLegints = filtered.purposeLegints.filter(
					purposeId => !disallowedPurposes.includes(purposeId)
				);

				// Filter out vendors that only had legitimate interest for disallowed purposes.
				if (this.gvl && this.gvl.vendors) {
					filtered.vendorsLegint = filtered.vendorsLegint.filter(vendorId => {
						const vendor = this.gvl.vendors[vendorId];
						if (!vendor || !vendor.legIntPurposes) {
							return false;
						}

						// Check if vendor has any allowed legitimate interest purposes left.
						const allowedPurposes = vendor.legIntPurposes.filter(
							purposeId => !disallowedPurposes.includes(purposeId)
						);

						return allowedPurposes.length > 0;
					});
				}
			}
		}

		// Apply per-vendor restrictions.
		if (restrictions.vendors && Object.keys(restrictions.vendors).length > 0) {
			Object.entries(restrictions.vendors).forEach(([vendorIdStr, vendorRestrictions]) => {
				const vendorId = parseInt(vendorIdStr, 10);

				// Handle disallowed_purposes: remove vendor consent for these purposes.
				if (vendorRestrictions.disallowed_purposes && vendorRestrictions.disallowed_purposes.length > 0) {
					// If any purposes are disallowed, we need to ensure the vendor can't process them.
					// Since we can't selectively remove purposes per vendor in consent vectors,
					// we rely on publisherRestrictions to communicate this to vendors.
					// The filtering here prevents full consent if all purposes are disallowed.
					if (this.gvl && this.gvl.vendors && this.gvl.vendors[vendorId]) {
						const vendor = this.gvl.vendors[vendorId];
						const allowedConsentPurposes = vendor.purposes?.filter(
							purposeId => !vendorRestrictions.disallowed_purposes.includes(purposeId)
						) || [];

						// If no consent purposes are allowed, remove vendor from consent.
						if (allowedConsentPurposes.length === 0 && vendor.purposes && vendor.purposes.length > 0) {
							filtered.vendorsConsent = filtered.vendorsConsent.filter(id => id !== vendorId);
						}
					}
				}

				// Handle require_consent_for_li: convert legitimate interest to consent-only.
				if (vendorRestrictions.require_consent_for_li && vendorRestrictions.require_consent_for_li.length > 0) {
					// Remove vendor from legitimate interest if they only have restricted purposes.
					if (this.gvl && this.gvl.vendors && this.gvl.vendors[vendorId]) {
						const vendor = this.gvl.vendors[vendorId];
						const allowedLegIntPurposes = vendor.legIntPurposes?.filter(
							purposeId => !vendorRestrictions.require_consent_for_li.includes(purposeId)
						) || [];

						// If all legitimate interest purposes require consent, remove from legitimate interest.
						if (allowedLegIntPurposes.length === 0 && vendor.legIntPurposes && vendor.legIntPurposes.length > 0) {
							filtered.vendorsLegint = filtered.vendorsLegint.filter(id => id !== vendorId);
						}

						// Remove restricted purposes from purpose legitimate interests.
						filtered.purposeLegints = filtered.purposeLegints.filter(
							purposeId => !vendorRestrictions.require_consent_for_li.includes(purposeId)
						);
					}
				}

				// Handle disallowed_special_purposes: filter special features.
				// Note: Special purposes are not directly controlled by consent, but we can track this.
			});
		}

		return filtered;
	}

	/**
	 * Set publisher restrictions in the TCModel.
	 *
	 * Publisher restrictions communicate to vendors which purposes they cannot use
	 * or must use consent for instead of legitimate interest.
	 *
	 * Uses PurposeRestriction with restriction types:
	 * - 0 (NOT_ALLOWED): Purpose is not allowed for this vendor
	 * - 1 (REQUIRE_CONSENT): Purpose requires consent (no legitimate interest)
	 */
	setPublisherRestrictions() {
		if (!this.tcModel || !this.tcModel.publisherRestrictions) {
			return;
		}

		const restrictions = window.wpconsent?.iab_tcf_publisher_restrictions;
		if (!restrictions || !restrictions.vendors) {
			return;
		}

		// Apply per-vendor restrictions.
		Object.entries(restrictions.vendors).forEach(([vendorIdStr, vendorRestrictions]) => {
			const vendorId = parseInt(vendorIdStr, 10);

			// Set NOT_ALLOWED restrictions for disallowed purposes.
			if (vendorRestrictions.disallowed_purposes && vendorRestrictions.disallowed_purposes.length > 0) {
				vendorRestrictions.disallowed_purposes.forEach(purposeId => {
					const restriction = new PurposeRestriction(purposeId, RestrictionType.NOT_ALLOWED);
					this.tcModel.publisherRestrictions.add(vendorId, restriction);
				});
			}

			// Set REQUIRE_CONSENT restrictions for legitimate interest purposes that require consent.
			if (vendorRestrictions.require_consent_for_li && vendorRestrictions.require_consent_for_li.length > 0) {
				vendorRestrictions.require_consent_for_li.forEach(purposeId => {
					const restriction = new PurposeRestriction(purposeId, RestrictionType.REQUIRE_CONSENT);
					this.tcModel.publisherRestrictions.add(vendorId, restriction);
				});
			}
		});
	}

	/**
	 * Set publisher declarations in the TCModel.
	 *
	 * Publisher declarations communicate what TCF purposes and features
	 * the website itself (as a first party) uses for its own data processing.
	 *
	 * This populates the TCData.publisher object which is separate from vendor consents.
	 */
	setPublisherDeclarations() {
		if (!this.tcModel) {
			return;
		}

		const declarations = window.wpconsent?.iab_tcf_publisher_declarations;
		if (!declarations) {
			return;
		}

		// Set publisher purpose consents.
		if (declarations.purposes_consent && Array.isArray(declarations.purposes_consent)) {
			declarations.purposes_consent.forEach(purposeId => {
				this.tcModel.publisherConsents.set(purposeId);
			});
		}

		// Set publisher purpose legitimate interests.
		if (declarations.purposes_li_transparency && Array.isArray(declarations.purposes_li_transparency)) {
			declarations.purposes_li_transparency.forEach(purposeId => {
				this.tcModel.publisherLegitimateInterests.set(purposeId);
			});
		}

		// Set publisher custom purposes (if any - not used in standard TCF but field exists).
		// The TCModel has publisherCustomConsents and publisherCustomLegitimateInterests
		// but we don't use them in this implementation.

		// Note: Special purposes, features, and special features don't have publisher-specific
		// vectors in the TCModel. They are implicitly declared through the GVL and
		// are not user-controlled consent items.
	}

	/**
	 * Generate and save consent string based on current preferences.
	 *
	 * @param {boolean} uiVisible - Whether the consent UI is currently visible.
	 * @return {Promise<string>} The generated TCString.
	 */
	async saveConsent(uiVisible = false) {
		if (!this.isInitialized || !this.tcModel || !this.cmpApi) {
			throw new Error('CMP API not properly initialized');
		}

		try {
			// Generate TCString from current TCModel.
			const tcString = TCString.encode(this.tcModel);

			// Update the official CMP API with the new TC string.
			// This triggers events to all registered listeners with eventStatus set appropriately.
			// - If uiVisible=true: eventStatus will be 'cmpuishown'.
			// - If uiVisible=false after consent change: eventStatus will be 'useractioncomplete'.
			this.cmpApi.update(tcString, uiVisible);

			// Still save to our own storage for compatibility.
			this.storeTCString(tcString);

			// Trigger consent saved event.
//			this.triggerConsentSavedEvent(tcString);

			return tcString;

		} catch (error) {
			console.error('Error saving consent:', error);
			throw error;
		}
	}

	/**
	 * Store TCString in localStorage and cookies.
	 *
	 * @param {string} tcString The TCString to store.
	 */
	storeTCString(tcString) {
		const cookieName = 'wpconsent_tcstring';
		const expirationDays = 365; // 1 year expiration.

		// Store in localStorage.
		try {
			localStorage.setItem('wpconsent_tcstring', tcString);
		} catch (error) {
			console.warn('Could not store in localStorage:', error);
		}

		// Store in cookie as fallback and for server-side access.
		const expirationDate = new Date();
		expirationDate.setTime(expirationDate.getTime() + (expirationDays * 24 * 60 * 60 * 1000));

		const cookieValue = `${cookieName}=${encodeURIComponent(tcString)}; expires=${expirationDate.toUTCString()}; path=/; SameSite=Lax`;
		document.cookie = cookieValue;
	}

	/**
	 * Trigger consent saved event for other parts of the system.
	 *
	 * @param {string} tcString The saved TCString.
	 */
	triggerConsentSavedEvent(tcString) {
		const event = new CustomEvent('wpconsent_consent_saved', {
			detail: {
				tcString: tcString,
				consentData: {
					vendors: Array.from(this.consentData.vendors),
					purposes: Array.from(this.consentData.purposes),
					specialFeatures: Array.from(this.consentData.specialFeatures),
					legitimateInterests: Array.from(this.consentData.legitimateInterests)
				},
				timestamp: new Date().toISOString()
			}
		});

		window.dispatchEvent(event);
	}

	/**
	 * Get current consent data.
	 *
	 * @return {Object} Current consent data.
	 */
	getConsentData() {
		return {
			tcString: this.tcModel ? TCString.encode(this.tcModel) : null,
			vendors: Array.from(this.consentData.vendors),
			purposes: Array.from(this.consentData.purposes),
			specialFeatures: Array.from(this.consentData.specialFeatures),
			legitimateInterests: Array.from(this.consentData.legitimateInterests),
			isInitialized: this.isInitialized,
			lastUpdated: this.tcModel?.lastUpdated?.toISOString() || null,
			created: this.tcModel?.created?.toISOString() || null
		};
	}

	/**
	 * Check if vendor has consent.
	 *
	 * @param {number} vendorId The vendor ID to check.
	 * @return {boolean} True if vendor has consent.
	 */
	hasVendorConsent(vendorId) {
		return this.consentData.vendors.has(vendorId);
	}

	/**
	 * Check if purpose has consent.
	 *
	 * @param {number} purposeId The purpose ID to check.
	 * @return {boolean} True if purpose has consent.
	 */
	hasPurposeConsent(purposeId) {
		return this.consentData.purposes.has(purposeId);
	}

	/**
	 * Clear all consent data.
	 */
	clearConsent() {
		// Clear internal data.
		this.consentData.vendors.clear();
		this.consentData.purposes.clear();
		this.consentData.specialFeatures.clear();
		this.consentData.legitimateInterests.clear();

		// Clear TCModel vectors.
		if (this.tcModel) {
			this.tcModel.vendorConsents.empty();
			this.tcModel.purposeConsents.empty();
			this.tcModel.specialFeatureOptins.empty();
			this.tcModel.purposeLegitimateInterests.empty();
			this.tcModel.vendorsDisclosed.empty();
			this.tcModel.lastUpdated = new Date();
		}

		// Clear storage.
		try {
			localStorage.removeItem('wpconsent_tcstring');
		} catch (error) {
			// localStorage might not be available.
		}

		// Clear cookie.
		document.cookie = 'wpconsent_tcstring=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';

		// Trigger event.
		window.dispatchEvent(new CustomEvent('wpconsent_consent_cleared'));
	}

	/**
	 * Check if the consent UI (banner or preferences modal) is currently visible.
	 *
	 * @return {boolean} True if UI is visible, false otherwise.
	 */
	isUICurrentlyVisible() {
		try {
			const container = document.querySelector('#wpconsent-container');
			if (!container || !container.shadowRoot) {
				return false;
			}

			// Check if banner is visible.
			const banner = container.shadowRoot.querySelector('#wpconsent-banner-holder');
			if (banner && banner.classList.contains('wpconsent-banner-visible')) {
				return true;
			}

			// Check if preferences modal is visible.
			const modal = container.shadowRoot.querySelector('.wpconsent-preferences-modal, #wpconsent-preferences, .wpconsent-tcf-preferences');
			if (modal) {
				const styles = window.getComputedStyle(modal);
				if (styles.display !== 'none' && styles.visibility !== 'hidden' && styles.opacity !== '0') {
					return true;
				}
			}

			return false;
		} catch (error) {
			console.warn('Error checking UI visibility:', error);
			return false;
		}
	}

	/**
	 * Notify CMP API that UI is visible (for proper eventStatus handling).
	 * This should be called when the preferences modal or banner is shown to the user.
	 * CmpApi will fire events to all registered listeners with eventStatus='cmpuishown'.
	 */
	notifyUIVisible() {
		// Only require cmpApi - it's available from early initialization.
		if (!this.cmpApi) {
			console.warn('CMP API not available, cannot notify UI visible');
			return;
		}

		try {
			// Try to use TCModel if available, otherwise use stored TC string or empty string.
			let tcString = '';

			if (this.tcModel) {
				try {
					tcString = TCString.encode(this.tcModel);
				} catch (encodeError) {
					console.warn('Failed to encode TCString from TCModel:', encodeError);
					tcString = this.getStoredTCString() || '';
				}
			} else {
				// Fall back to stored TCString if TCModel not yet initialized.
				tcString = this.getStoredTCString() || '';
			}

			// Update CMP API with UI visible flag.
			this.cmpApi.update(tcString, true);
		} catch (error) {
			console.warn('Error notifying CMP API about UI visibility:', error);
		}
	}

	/**
	 * Notify CMP API that UI is hidden (for proper eventStatus handling).
	 * This should be called when the preferences modal or banner is closed without saving.
	 * CmpApi will fire events to all registered listeners with eventStatus='tcloaded'.
	 */
	notifyUIHidden() {
		// Only require cmpApi - it's available from early initialization.
		if (!this.cmpApi) {
			console.warn('CMP API not available, cannot notify UI hidden');
			return;
		}

		try {
			// Try to use TCModel if available, otherwise use stored TC string or empty string.
			let tcString = '';

			if (this.tcModel) {
				try {
					tcString = TCString.encode(this.tcModel);
				} catch (encodeError) {
					console.warn('Failed to encode TCString from TCModel:', encodeError);
					tcString = this.getStoredTCString() || '';
				}
			} else {
				// Fall back to stored TCString if TCModel not yet initialized.
				tcString = this.getStoredTCString() || '';
			}

			// Update CMP API with UI not visible flag.
			this.cmpApi.update(tcString, false);
		} catch (error) {
			console.warn('Error notifying CMP API about UI hidden:', error);
		}
	}
}

// Initialize the CMP API.
const wpConsentCMPAPI = new WPConsentCMPAPI();

export default wpConsentCMPAPI;
