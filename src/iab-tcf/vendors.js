/**
 * IAB TCF Vendors Dynamic Description Builder
 *
 * This module handles dynamic generation of comprehensive vendor descriptions
 * when vendor details are opened in the preferences modal.
 *
 * @package WPConsent
 */

class WPConsentIABVendors {
	constructor() {
		this.purposesCache = null;
		this.specialPurposesCache = null;
		this.featuresCache = null;
		this.specialFeaturesCache = null;
		this.dataCategoriesCache = null;

		// Listen for vendor events
		document.addEventListener('vendorDetailsOpened', this.handleVendorOpened.bind(this));
		document.addEventListener('vendorDetailsClosed', this.handleVendorClosed.bind(this));
	}

	/**
	 * Handle vendor details opened event.
	 *
	 * @param {CustomEvent} event The custom event with vendor data.
	 */
	handleVendorOpened(event) {
		const { vendorData, content } = event.detail;

		if (!vendorData || !content) {
			return;
		}

		// Build comprehensive description
		this.buildVendorDescription(vendorData, content);
	}

	/**
	 * Handle vendor details closed event.
	 *
	 * @param {CustomEvent} event The custom event with vendor data.
	 */
	handleVendorClosed(event) {
		// Could be used for cleanup if needed
		// For now, we don't need to do anything when vendor details are closed
	}

	/**
	 * Build comprehensive vendor description dynamically.
	 *
	 * @param {Object} vendorData The vendor data from the data attribute.
	 * @param {Element} content The content element to update.
	 */
	async buildVendorDescription(vendorData, content) {
		try {
			// Check if we're dealing with vendors tab structure or purposes tab structure
			let targetElement = content.querySelector('.wpconsent-vendor-details');

			if (!targetElement) {
				// Fallback to the old structure for purposes tab
				targetElement = content.querySelector('p[tabindex="0"]:first-child');
			}

			if (!targetElement) {
				return;
			}

			// Check if description has already been loaded to prevent duplication
			if (targetElement.hasAttribute('data-description-loaded')) {
				return;
			}

			// Load reference data if not cached
			await this.loadReferenceData();

			// For purposes tab, build comprehensive description parts
			const descriptionParts = [];

			// Add existing description (already in the paragraph)
			const existingDescription = targetElement.innerHTML;
			if (existingDescription) {
				descriptionParts.push(existingDescription);
			}

			// Add purposes information
			const purposeInfo = this.buildVendorPurposesInfo(vendorData);
			if (purposeInfo) {
				descriptionParts.push(purposeInfo);
			}

			// Add features information
			const featureInfo = this.buildVendorFeaturesInfo(vendorData);
			if (featureInfo) {
				descriptionParts.push(featureInfo);
			}

			// Add data categories information (IAB TCF Policy C(c)(I) & D(c)(VI))
			const dataCategoriesInfo = this.buildVendorDataCategoriesInfo(vendorData);
			if (dataCategoriesInfo) {
				descriptionParts.push(dataCategoriesInfo);
			}

			// Add lifecycle information (data retention)
			const lifecycleInfo = this.buildVendorLifecycleInfo(vendorData);
			if (lifecycleInfo) {
				descriptionParts.push(lifecycleInfo);
			}

			// Add privacy policy link
			if (vendorData.policyUrl) {
				descriptionParts.push(this.buildPrivacyPolicyLink(vendorData.policyUrl));
			}

			// Add legitimate interest claim link if available
			if (vendorData.legIntClaimUrl) {
				descriptionParts.push(this.buildLegitimateInterestLink(vendorData.legIntClaimUrl));
			}

			// Add device storage disclosure link if available (IAB TCF Policy C(c)(VII))
			if (vendorData.deviceStorageDisclosureUrl) {
				descriptionParts.push(this.buildDeviceStorageLink(vendorData.deviceStorageDisclosureUrl));
			}

			// Add URLs from urls array if available (grab first item)
			if (vendorData.urls && vendorData.urls.length > 0) {
				const firstUrlItem = vendorData.urls[0];

				// Add privacy policy from urls if it exists and is different from policyUrl
				if (firstUrlItem.privacy && firstUrlItem.privacy !== vendorData.policyUrl) {
					descriptionParts.push(this.buildPrivacyPolicyLink(firstUrlItem.privacy));
				}

				// Add legitimate interest claim from urls if it exists and is different from legIntClaimUrl
				if (firstUrlItem.legIntClaim && firstUrlItem.legIntClaim !== vendorData.legIntClaimUrl) {
					descriptionParts.push(this.buildLegitimateInterestLink(firstUrlItem.legIntClaim));
				}
			}

			// Update the description paragraph with comprehensive information (purposes tab only)
			targetElement.innerHTML = descriptionParts.join('<br><br>');

			// Mark the description as loaded to prevent duplication
			targetElement.setAttribute('data-description-loaded', 'true');

		} catch (error) {
			console.error('Error building vendor description:', error);
		}
	}

	/**
	 * Load reference data for purposes, features, etc.
	 * Uses GVL data if available from IAB tabs instance, falls back to basic hardcoded data.
	 */
	async loadReferenceData() {
		// Try to get GVL data from the IAB tabs instance if available
		if (window.WPConsentIABTabs && window.WPConsentIABTabs.gvl) {
			const gvl = window.WPConsentIABTabs.gvl;

			// Wait for GVL to be ready and then use the data
			await gvl.readyPromise;

			// Use GVL data
			this.purposesCache = gvl.purposes || {};
			this.specialPurposesCache = gvl.specialPurposes || {};
			this.featuresCache = gvl.features || {};
			this.specialFeaturesCache = gvl.specialFeatures || {};
			this.dataCategoriesCache = gvl.dataCategories || {};

			return;
		}

		// Fallback: Initialize with standard IAB TCF purpose names for common IDs
		// This provides basic functionality if GVL data is not available
		this.purposesCache = {
			1: { name: 'Store and/or access information on a device' },
			2: { name: 'Select basic ads' },
			3: { name: 'Create a personalised ads profile' },
			4: { name: 'Select personalised ads' },
			5: { name: 'Create a personalised content profile' },
			6: { name: 'Select personalised content' },
			7: { name: 'Measure ad performance' },
			8: { name: 'Measure content performance' },
			9: { name: 'Apply market research to generate audience insights' },
			10: { name: 'Develop and improve products' },
			11: { name: 'Use limited data to select advertising' },
			12: { name: 'Use profiles to select personalised advertising' },
			13: { name: 'Use profiles to select personalised content' },
			14: { name: 'Actively scan device characteristics for identification' }
		};

		this.specialPurposesCache = {
			1: { name: 'Ensure security, prevent fraud, and debug' },
			2: { name: 'Technically deliver ads or content' }
		};

		this.featuresCache = {
			1: { name: 'Match and combine offline data sources' },
			2: { name: 'Link different devices' },
			3: { name: 'Receive and use automatically-sent device characteristics for identification' }
		};

		this.specialFeaturesCache = {
			1: { name: 'Use precise geolocation data' },
			2: { name: 'Actively scan device characteristics for identification' }
		};

		this.dataCategoriesCache = {
			1: { name: 'IP addresses' },
			2: { name: 'Device characteristics' },
			3: { name: 'Device identifiers' },
			4: { name: 'Probabilistic identifiers' },
			5: { name: 'Authentication-derived identifiers' },
			6: { name: 'Browsing and interaction data' },
			7: { name: 'User-provided data' },
			8: { name: 'Non-precise location data' },
			9: { name: 'Precise location data' },
			10: { name: 'Users\' profiles' },
			11: { name: 'Privacy choices' }
		};
	}

	/**
	 * Build vendor purposes information.
	 *
	 * @param {Object} vendorData The vendor data.
	 * @return {string} The formatted purposes information.
	 */
	buildVendorPurposesInfo(vendorData) {
		const purposeParts = [];
		const dataRetention = vendorData.dataRetention || {};
		const purposesRetention = dataRetention.purposes || {};
		const specialPurposesRetention = dataRetention.specialPurposes || {};
		const stdRetention = dataRetention.stdRetention || null;

		// Regular purposes (with retention per purpose - IAB TCF Policy C(c)(I) & D(c)(VI))
		if (vendorData.purposes && vendorData.purposes.length > 0) {
			const purposeItems = [];
			vendorData.purposes.forEach(purposeId => {
				if (this.purposesCache[purposeId] && this.purposesCache[purposeId].name) {
					let purposeText = this.escapeHtml(this.purposesCache[purposeId].name);
					// Add retention period if available
					const retentionDays = purposesRetention[purposeId] || stdRetention;
					if (retentionDays) {
						purposeText += ` (${this.getTranslation('retention:')} ${retentionDays} ${this.getTranslation('days')})`;
					}
					purposeItems.push(purposeText);
				}
			});
			if (purposeItems.length > 0) {
				purposeParts.push(`<strong>${this.getTranslation('Purposes:')} </strong>${purposeItems.join(', ')}`);
			}
		}

		// Legitimate interest purposes (with retention - IAB TCF Policy C(c)(I) & D(c)(VI))
		if (vendorData.legIntPurposes && vendorData.legIntPurposes.length > 0) {
			const legIntItems = [];
			vendorData.legIntPurposes.forEach(purposeId => {
				if (this.purposesCache[purposeId] && this.purposesCache[purposeId].name) {
					let purposeText = this.escapeHtml(this.purposesCache[purposeId].name);
					// Add retention period if available
					const retentionDays = purposesRetention[purposeId] || stdRetention;
					if (retentionDays) {
						purposeText += ` (${this.getTranslation('retention:')} ${retentionDays} ${this.getTranslation('days')})`;
					}
					legIntItems.push(purposeText);
				}
			});
			if (legIntItems.length > 0) {
				let legIntSection = `<strong>${this.getTranslation('Legitimate Interest Purposes:')} </strong>${legIntItems.join(', ')}`;

				// Add data type disclosure (Check #22 compliance)
				legIntSection += `<br><em>${this.getTranslation('Processes:')} ${this.getTranslation('unique identifiers, browsing data, device information')}</em>`;

				// Add scope information (Check #22 compliance)
				legIntSection += `<br><em>${this.getTranslation('Scope:')} ${this.getTranslation('Service-specific processing')}</em>`;

				purposeParts.push(legIntSection);
			}
		}

		// Special purposes (with retention - IAB TCF Policy C(c)(I) & D(c)(VI))
		if (vendorData.specialPurposes && vendorData.specialPurposes.length > 0) {
			const specialItems = [];
			vendorData.specialPurposes.forEach(purposeId => {
				if (this.specialPurposesCache[purposeId] && this.specialPurposesCache[purposeId].name) {
					let purposeText = this.escapeHtml(this.specialPurposesCache[purposeId].name);
					// Add retention period if available
					const retentionDays = specialPurposesRetention[purposeId] || stdRetention;
					if (retentionDays) {
						purposeText += ` (${this.getTranslation('retention:')} ${retentionDays} ${this.getTranslation('days')})`;
					}
					specialItems.push(purposeText);
				}
			});
			if (specialItems.length > 0) {
				purposeParts.push(`<strong>${this.getTranslation('Special Purposes:')} </strong>${specialItems.join(', ')}`);
			}
		}

		return purposeParts.join('<br>');
	}

	/**
	 * Build vendor features information.
	 *
	 * @param {Object} vendorData The vendor data.
	 * @return {string} The formatted features information.
	 */
	buildVendorFeaturesInfo(vendorData) {
		const featureParts = [];

		// Regular features
		if (vendorData.features && vendorData.features.length > 0) {
			const featureNames = this.getPurposeNames(vendorData.features, this.featuresCache);
			if (featureNames.length > 0) {
				featureParts.push(`<strong>${this.getTranslation('Features:')} </strong>${featureNames.join(', ')}`);
			}
		}

		// Special features
		if (vendorData.specialFeatures && vendorData.specialFeatures.length > 0) {
			const specialFeatureNames = this.getPurposeNames(vendorData.specialFeatures, this.specialFeaturesCache);
			if (specialFeatureNames.length > 0) {
				featureParts.push(`<strong>${this.getTranslation('Special Features:')} </strong>${specialFeatureNames.join(', ')}`);
			}
		}

		return featureParts.join('<br>');
	}

	/**
	 * Build vendor data categories information.
	 *
	 * @param {Object} vendorData The vendor data.
	 * @return {string} The formatted data categories information.
	 */
	buildVendorDataCategoriesInfo(vendorData) {
		// Get data declaration (categories of data collected/processed)
		if (!vendorData.dataDeclaration || vendorData.dataDeclaration.length === 0) {
			return '';
		}

		const categoryNames = [];
		vendorData.dataDeclaration.forEach(categoryId => {
			if (this.dataCategoriesCache[categoryId] && this.dataCategoriesCache[categoryId].name) {
				categoryNames.push(this.escapeHtml(this.dataCategoriesCache[categoryId].name));
			}
		});

		if (categoryNames.length === 0) {
			return '';
		}

		return `<strong>${this.getTranslation('Data Categories:')} </strong>${categoryNames.join(', ')}`;
	}

	/**
	 * Build vendor lifecycle/data retention information.
	 *
	 * @param {Object} vendorData The vendor data.
	 * @return {string} The formatted lifecycle information.
	 */
	buildVendorLifecycleInfo(vendorData) {
		const lifecycleParts = [];

		// Cookie storage information (IAB TCF Policy C(c)(VII))
		if (vendorData.cookieMaxAgeSeconds) {
			const days = Math.ceil(vendorData.cookieMaxAgeSeconds / 86400);
			let storageText = `${this.getTranslation('Up to')} ${days} ${this.getTranslation('days')}`;

			// Add refresh information if applicable (Policy C(c)(VII))
			if (vendorData.cookieRefresh === true) {
				storageText += ` (${this.getTranslation('duration refreshes from your last interaction with the property')})`;
			}

			lifecycleParts.push(`<strong>${this.getTranslation('Cookie Storage:')} </strong>${storageText}`);
		}

		// Non-cookie access
		if (vendorData.usesNonCookieAccess) {
			lifecycleParts.push(`<strong>${this.getTranslation('Data Processing:')} </strong>${this.getTranslation('May use non-cookie methods for data collection and processing')}`);
		}

		// Data retention information
		if (vendorData.dataRetention && Object.keys(vendorData.dataRetention).length > 0) {
			const retentionInfo = this.buildDataRetentionInfo(vendorData.dataRetention);
			if (retentionInfo) {
				lifecycleParts.push(`<strong>${this.getTranslation('Data Retention:')} </strong>${retentionInfo}`);
			}
		}

		return lifecycleParts.join('<br>');
	}

	/**
	 * Build data retention information.
	 *
	 * @param {Object} dataRetention The data retention object.
	 * @return {string} The formatted data retention information.
	 */
	buildDataRetentionInfo(dataRetention) {
		const retentionParts = [];

		// Note: stdRetention is already in days, not seconds
		if (dataRetention.stdRetention) {
			const days = dataRetention.stdRetention;
			retentionParts.push(`${this.getTranslation('Standard')}: ${days} ${this.getTranslation('days')}`);
		}

		return retentionParts.join(', ');
	}

	/**
	 * Build privacy policy link.
	 *
	 * @param {string} policyUrl The privacy policy URL.
	 * @return {string} The formatted privacy policy link.
	 */
	buildPrivacyPolicyLink(policyUrl) {
		return `${this.getTranslation('For more information, see their')} <a href="${this.escapeHtml(policyUrl)}" target="_blank" rel="noopener noreferrer">${this.getTranslation('privacy policy')}</a>.`;
	}

	/**
	 * Build legitimate interest claim link.
	 *
	 * @param {string} legIntClaimUrl The legitimate interest claim URL.
	 * @return {string} The formatted legitimate interest claim link.
	 */
	buildLegitimateInterestLink(legIntClaimUrl) {
		return `${this.getTranslation('View their')} <a href="${this.escapeHtml(legIntClaimUrl)}" target="_blank" rel="noopener noreferrer">${this.getTranslation('legitimate interest disclosure')}</a>.`;
	}

	/**
	 * Build device storage disclosure link.
	 *
	 * @param {string} disclosureUrl The device storage disclosure URL.
	 * @return {string} The formatted disclosure link.
	 */
	buildDeviceStorageLink(disclosureUrl) {
		return `${this.getTranslation('View their')} <a href="${this.escapeHtml(disclosureUrl)}" target="_blank" rel="noopener noreferrer">${this.getTranslation('device storage disclosure')}</a> ${this.getTranslation('for details about data storage')}.`;
	}

	/**
	 * Get purpose names from IDs.
	 *
	 * @param {Array} purposeIds Array of purpose IDs.
	 * @param {Object} purposeCache The purpose cache object.
	 * @return {Array} Array of purpose names.
	 */
	getPurposeNames(purposeIds, purposeCache) {
		if (!purposeIds || !purposeCache) {
			return [];
		}

		const names = [];
		purposeIds.forEach(id => {
			if (purposeCache[id] && purposeCache[id].name) {
				names.push(this.escapeHtml(purposeCache[id].name));
			}
		});

		return names;
	}

	/**
	 * Get translation for a text string.
	 * Uses translations from backend if available, falls back to the text itself.
	 *
	 * @param {string} text The text to translate.
	 * @return {string} The translated text.
	 */
	getTranslation(text) {
		// Use backend translations if available from localized script data
		if (window.wpconsent && window.wpconsent.iab_tcf_translations) {
			const backendTranslations = window.wpconsent.iab_tcf_translations;
			if (backendTranslations[text]) {
				return backendTranslations[text];
			}
		}

		// Fallback: Basic translations for when backend data is not available
		const fallbackTranslations = {
			'Purposes:': 'Purposes:',
			'Legitimate Interest Purposes:': 'Legitimate Interest Purposes:',
			'Special Purposes:': 'Special Purposes:',
			'Features:': 'Features:',
			'Special Features:': 'Special Features:',
			'Data Categories:': 'Data Categories:',
			'Cookie Storage:': 'Cookie Storage:',
			'Data Processing:': 'Data Processing:',
			'Data Retention:': 'Data Retention:',
			'Up to': 'Up to',
			'days': 'days',
			'retention:': 'retention:',
			'Standard': 'Standard',
			'duration refreshes from your last interaction with the property': 'duration refreshes from your last interaction with the property',
			'May use non-cookie methods for data collection and processing': 'May use non-cookie methods for data collection and processing',
			'For more information, see their': 'For more information, see their',
			'privacy policy': 'privacy policy',
			'View their': 'View their',
			'legitimate interest disclosure': 'legitimate interest disclosure',
			'device storage disclosure': 'device storage disclosure',
			'for details about data storage': 'for details about data storage'
		};

		return fallbackTranslations[text] || text;
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} The escaped text.
	 */
	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
}

// Initialize the IAB TCF Vendors handler when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => {
		new WPConsentIABVendors();
	});
} else {
	new WPConsentIABVendors();
}