/**
 * IAB TCF Banner Tabbed Interface
 *
 * This module handles the tabbed interface for IAB TCF in the preferences modal.
 * It manages tab switching between purposes and vendors views, and handles vendor search.
 * Uses IAB TCF GVL (Global Vendor List) for vendor management.
 *
 * @package WPConsent
 */

import { GVL } from '@iabtechlabtcf/core';

class WPConsentIABTabs {
	constructor() {
		this.vendors = [];
		this.filteredVendors = [];
		this.searchTimeout = null;
		this.selectedPurposes = new Set();
		this.selectedSpecialPurposes = new Set();
		this.selectedSpecialFeatures = new Set();
		this.isFilterDropdownOpen = false;
		this.gvl = null;

		// Listen for the banner initialized event
		window.addEventListener('wpconsent_banner_initialized', () => {
			this.init();
		});
	}

	/**
	 * Initialize the tabbed interface functionality.
	 */
	init() {
		this.bindEvents();
		this.loadVendors();

		// Expose this instance globally for access by other modules
		window.WPConsentIABTabs = this;
	}

	/**
	 * Bind event listeners for tabs and search.
	 */
	bindEvents() {
		// Check if shadow root is available, if not, wait for it
		if (!window.WPConsent || !window.WPConsent.shadowRoot) {
			// Retry binding events after a short delay
			setTimeout(() => this.bindEvents(), 100);
			return;
		}

		// Tab switching - use shadow root for event delegation
		window.WPConsent.shadowRoot.addEventListener('click', (e) => {
			// Check if the clicked element has the tab button class
			if (e.target.classList.contains('wpconsent-tcf-tab-button')) {
				e.preventDefault();
				e.stopPropagation();
				this.switchTab(e.target);
			}
		});

		// Vendor search with debounce - use shadow root
		window.WPConsent.shadowRoot.addEventListener('input', (e) => {
			if (e.target.id === 'wpconsent-vendor-search') {
				const searchTerm = e.target.value;
				clearTimeout(this.searchTimeout);
				this.searchTimeout = setTimeout(() => {
					this.searchVendors(searchTerm);
				}, 300);
			}
		});

		// Filter button and dropdown interactions
		window.WPConsent.shadowRoot.addEventListener('click', (e) => {
			// Filter button toggle
			if (e.target.id === 'wpconsent-vendor-filter-btn' || e.target.closest('#wpconsent-vendor-filter-btn')) {
				e.preventDefault();
				e.stopPropagation();
				this.toggleFilterDropdown();
			}
			// Clear all filters button
			else if (e.target.id === 'wpconsent-filter-clear') {
				e.preventDefault();
				e.stopPropagation();
				this.clearAllFilters();
			}
			// Close dropdown when clicking outside
			else if (!e.target.closest('.wpconsent-vendor-filter-dropdown') &&
					 !e.target.closest('#wpconsent-vendor-filter-btn')) {
				if (this.isFilterDropdownOpen) {
					this.closeFilterDropdown();
				}
			}
		});

		// Handle filter checkbox changes
		window.WPConsent.shadowRoot.addEventListener('change', (e) => {
			if (e.target.classList.contains('wpconsent-filter-purpose-checkbox')) {
				const purposeId = e.target.value;
				if (e.target.checked) {
					this.selectedPurposes.add(purposeId);
				} else {
					this.selectedPurposes.delete(purposeId);
				}
				this.applyFilters();
			} else if (e.target.classList.contains('wpconsent-filter-specialpurpose-checkbox')) {
				const specialPurposeId = e.target.value;
				if (e.target.checked) {
					this.selectedSpecialPurposes.add(specialPurposeId);
				} else {
					this.selectedSpecialPurposes.delete(specialPurposeId);
				}
				this.applyFilters();
			} else if (e.target.classList.contains('wpconsent-filter-specialfeature-checkbox')) {
				const featureId = e.target.value;
				if (e.target.checked) {
					this.selectedSpecialFeatures.add(featureId);
				} else {
					this.selectedSpecialFeatures.delete(featureId);
				}
				this.applyFilters();
			}
		});
	}


	/**
	 * Switch between tabs (purposes/features/vendors).
	 *
	 * @param {HTMLElement} tabButton The clicked tab button.
	 */
	switchTab(tabButton) {
		const targetTab = tabButton.dataset.tab;

		// Remove active class from all tabs and content - use shadow root
		window.WPConsent.shadowRoot.querySelectorAll('.wpconsent-tcf-tab-button').forEach(btn => {
			btn.classList.remove('wpconsent-tcf-tab-active');
		});
		window.WPConsent.shadowRoot.querySelectorAll('.wpconsent-tcf-tab-content').forEach(content => {
			content.classList.remove('wpconsent-tcf-tab-active');
		});

		// Add active class to clicked tab and corresponding content
		tabButton.classList.add('wpconsent-tcf-tab-active');
		const targetContent = window.WPConsent.shadowRoot.querySelector(`.wpconsent-tcf-tab-${targetTab}`);
		if (targetContent) {
			targetContent.classList.add('wpconsent-tcf-tab-active');
		} else {
			console.error('Tab content not found for:', targetTab);
		}

		// Populate purposes tab content (purposes and special purposes).
		if (targetTab === 'purposes') {
			this.populateSpecialPurposes();
		}

		// Populate features tab content (features and special features).
		if (targetTab === 'features') {
			this.populateFeatures();
			this.populateSpecialFeatures();
		}

		// Populate vendors if switching to vendors tab.
		if (targetTab === 'vendors') {
			this.populateFilterPurposes();
		}
	}

	/**
	 * Switch to vendors tab with a special feature filter applied.
	 *
	 * @param {string} specialFeatureId The special feature ID to filter by.
	 */
	switchToVendorsWithSpecialFeature(specialFeatureId) {
		// Clear existing filters
		this.selectedPurposes.clear();
		this.selectedSpecialFeatures.clear();

		// Add the special feature to the filter
		this.selectedSpecialFeatures.add(String(specialFeatureId));

		// Find and click the vendors tab button
		const vendorsTabButton = window.WPConsent.shadowRoot?.querySelector('[data-tab="vendors"]');
		if (vendorsTabButton) {
			this.switchTab(vendorsTabButton);
		}

		// Apply the filter
		setTimeout(() => {
			this.applyFilters();
		}, 100);
	}

	/**
	 * Switch to vendors tab with a purpose filter applied.
	 *
	 * @param {string} purposeId The purpose ID to filter by.
	 */
	switchToVendorsWithPurpose(purposeId) {
		// Clear existing filters
		this.selectedPurposes.clear();
		this.selectedSpecialPurposes.clear();
		this.selectedSpecialFeatures.clear();

		// Add the purpose to the filter
		this.selectedPurposes.add(String(purposeId));

		// Find and click the vendors tab button
		const vendorsTabButton = window.WPConsent.shadowRoot?.querySelector('[data-tab="vendors"]');
		if (vendorsTabButton) {
			this.switchTab(vendorsTabButton);
		}

		// Apply the filter
		setTimeout(() => {
			this.applyFilters();
		}, 100);
	}

	/**
	 * Switch to vendors tab with a special purpose filter applied.
	 *
	 * @param {string} specialPurposeId The special purpose ID to filter by.
	 */
	switchToVendorsWithSpecialPurpose(specialPurposeId) {
		// Clear existing filters
		this.selectedPurposes.clear();
		this.selectedSpecialPurposes.clear();
		this.selectedSpecialFeatures.clear();

		// Add the special purpose to the filter
		this.selectedSpecialPurposes.add(String(specialPurposeId));

		// Find and click the vendors tab button
		const vendorsTabButton = window.WPConsent.shadowRoot?.querySelector('[data-tab="vendors"]');
		if (vendorsTabButton) {
			this.switchTab(vendorsTabButton);
		}

		// Apply the filter
		setTimeout(() => {
			this.applyFilters();
		}, 100);
	}

	/**
	 * Load vendors data using GVL (Global Vendor List).
	 */
	async loadVendors() {
		try {
			// Check if GVL configuration is available
			if (!window.wpconsent || !window.wpconsent.iab_tcf_baseurl) {
				console.warn('IAB TCF GVL configuration not found');
				this.vendors = [];
				this.filteredVendors = [];
				return;
			}

			// Initialize GVL if not already done
			if (!this.gvl) {
				GVL.baseUrl = window.wpconsent.iab_tcf_baseurl;
				this.gvl = new GVL();
			}

			// Wait for GVL to be ready and then run narrowVendors in the callback
			this.gvl.readyPromise.then(() => {
				// Get enabled vendor IDs from configuration
				const enabledVendorIds = window.wpconsent.iab_tcf_enabled_vendors || [];

				if (enabledVendorIds.length === 0) {
					console.warn('No enabled vendors found in configuration');
					this.vendors = [];
					this.filteredVendors = [];
					return;
				}

				// Filter GVL to only include enabled vendors
				this.gvl.narrowVendorsTo(enabledVendorIds);

				// Convert GVL vendors to array format
				this.vendors = Object.keys(this.gvl.vendors).map(vendorId => {
					const vendor = this.gvl.vendors[vendorId];
					return {
						id: parseInt(vendorId),
						name: vendor.name,
						policyUrl: vendor.policyUrl,
						legIntClaimUrl: vendor.legIntClaimUrl,
						purposes: vendor.purposes,
						legIntPurposes: vendor.legIntPurposes,
						flexiblePurposes: vendor.flexiblePurposes,
						specialPurposes: vendor.specialPurposes,
						features: vendor.features,
						specialFeatures: vendor.specialFeatures,
						cookieMaxAgeSeconds: vendor.cookieMaxAgeSeconds,
						cookieRefresh: vendor.cookieRefresh,
						usesNonCookieAccess: vendor.usesNonCookieAccess,
						deviceStorageDisclosureUrl: vendor.deviceStorageDisclosureUrl,
						urls: vendor.urls
					};
				}).sort((a, b) => {
					// Sort vendors alphabetically by name
					const nameA = (a.name || 'Unknown Vendor').toLowerCase();
					const nameB = (b.name || 'Unknown Vendor').toLowerCase();
					return nameA.localeCompare(nameB);
				});

				this.filteredVendors = [...this.vendors];

				this.populateVendors();
				this.populateSpecialPurposes();
			});

		} catch (error) {
			console.error('Error loading vendors with GVL:', error);
			this.vendors = [];
			this.filteredVendors = [];
		}
	}

	/**
	 * Populate special purposes list in the purposes tab.
	 */
	populateSpecialPurposes() {
		const specialPurposesList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-special-purposes-list');
		if (!specialPurposesList) {
			return;
		}

		// Check if already populated
		if (specialPurposesList.dataset.populated === 'true') {
			return;
		}

		// Check if GVL is available
		if (!this.gvl || !this.gvl.specialPurposes) {
			specialPurposesList.innerHTML = '<p class="wpconsent-no-special-purposes">Special purposes data not available.</p>';
			return;
		}

		const specialPurposes = this.gvl.specialPurposes;

		if (Object.keys(specialPurposes).length === 0) {
			specialPurposesList.innerHTML = '<p class="wpconsent-no-special-purposes">No special purposes available.</p>';
			return;
		}

		// Build HTML for special purposes list with description
		let html = '<div class="wpconsent-tcf-section-separator"></div>';
		html += '<div class="wpconsent-tcf-special-purposes-description">';
		html += '<h4 class="wpconsent-tcf-section-heading">' + this.escapeHtml(this.getTranslation('Special Purposes')) + '</h4>';
		html += '<p class="wpconsent-tab-description">';
		html += this.escapeHtml(this.getTranslation('special_purposes_description'));
		html += '</p>';
		html += '</div>';
		html += '<div class="wpconsent-tcf-special-purposes-accordion">';

		// Sort special purposes by ID for consistent order
		const sortedPurposes = Object.keys(specialPurposes)
			.map(id => ({ id, ...specialPurposes[id] }))
			.sort((a, b) => parseInt(a.id) - parseInt(b.id));

		sortedPurposes.forEach(purpose => {
			const purposeId = purpose.id;
			const purposeName = purpose.name || `Special Purpose ${purposeId}`;
			const purposeDescription = purpose.description || purpose.descriptionLegal || '';
			const illustrations = purpose.illustrations || [];

			// Build illustrations HTML if available (IAB TCF Policy B(b)).
			let illustrationsHtml = '';
			if (illustrations.length > 0) {
				illustrationsHtml = '<div class="wpconsent-iab-illustrations">';
				illustrationsHtml += `<p class="wpconsent-iab-illustrations-label"><strong>${this.escapeHtml(this.getTranslation('Examples:'))}</strong></p>`;
				illustrations.forEach(illustration => {
					illustrationsHtml += `<p class="wpconsent-iab-illustration">${this.escapeHtml(illustration)}</p>`;
				});
				illustrationsHtml += '</div>';
			}

			html += `
				<div class="wpconsent-preferences-accordion-item wpconsent-special-purpose-item" data-purpose-id="${purposeId}">
					<div class="wpconsent-preferences-accordion-header">
						<div class="wpconsent-cookie-category-text">
							<button class="wpconsent-preferences-accordion-toggle">
								<span class="wpconsent-preferences-accordion-arrow"></span>
							</button>
							<label>${this.escapeHtml(purposeName)}</label>
						</div>
					</div>
					<div class="wpconsent-preferences-accordion-content">
						<p tabindex="0">${this.escapeHtml(purposeDescription)}</p>
						${illustrationsHtml}
						<p class="wpconsent-special-purpose-vendor-link">
							<button type="button" class="wpconsent-view-vendors-link wpconsent-view-vendors-special-purpose" data-special-purpose-id="${purposeId}">${this.escapeHtml(this.getTranslation('View vendors using this special purpose'))}</button>
						</p>
					</div>
				</div>
			`;
		});

		html += '</div>'; // Close accordion container

		specialPurposesList.innerHTML = html;

		// Mark as populated
		specialPurposesList.dataset.populated = 'true';

		// Bind accordion functionality for special purpose items
		this.bindSpecialPurposeAccordion();
	}

	/**
	 * Populate features list in the features tab.
	 */
	populateFeatures() {
		const featuresList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-features-list');
		if (!featuresList) {
			return;
		}

		// Check if already populated
		if (featuresList.dataset.populated === 'true') {
			return;
		}

		// Check if GVL is available
		if (!this.gvl || !this.gvl.features) {
			featuresList.innerHTML = '<p class="wpconsent-no-features">Features data not available.</p>';
			return;
		}

		const features = this.gvl.features;

		if (Object.keys(features).length === 0) {
			featuresList.innerHTML = '<p class="wpconsent-no-features">No features available.</p>';
			return;
		}

		// Build HTML for features list with description
		let html = '<div class="wpconsent-tcf-features-description">';
		html += '<h4 class="wpconsent-tcf-section-heading">' + this.escapeHtml(this.getTranslation('Features')) + '</h4>';
		html += '<p class="wpconsent-tab-description">';
		html += this.escapeHtml(this.getTranslation('features_description'));
		html += '</p>';
		html += '</div>';
		html += '<div class="wpconsent-tcf-features-accordion">';

		// Sort features by ID for consistent order
		const sortedFeatures = Object.keys(features)
			.map(id => ({ id, ...features[id] }))
			.sort((a, b) => parseInt(a.id) - parseInt(b.id));

		sortedFeatures.forEach(feature => {
			const featureId = feature.id;
			const featureName = feature.name || `Feature ${featureId}`;
			const featureDescription = feature.description || feature.descriptionLegal || '';
			const illustrations = feature.illustrations || [];

			// Build illustrations HTML if available (IAB TCF Policy B(b)).
			let illustrationsHtml = '';
			if (illustrations.length > 0) {
				illustrationsHtml = '<div class="wpconsent-iab-illustrations">';
				illustrationsHtml += `<p class="wpconsent-iab-illustrations-label"><strong>${this.escapeHtml(this.getTranslation('Examples:'))}</strong></p>`;
				illustrations.forEach(illustration => {
					illustrationsHtml += `<p class="wpconsent-iab-illustration">${this.escapeHtml(illustration)}</p>`;
				});
				illustrationsHtml += '</div>';
			}

			html += `
				<div class="wpconsent-preferences-accordion-item wpconsent-feature-item" data-feature-id="${featureId}">
					<div class="wpconsent-preferences-accordion-header">
						<div class="wpconsent-cookie-category-text">
							<button class="wpconsent-preferences-accordion-toggle">
								<span class="wpconsent-preferences-accordion-arrow"></span>
							</button>
							<label>${this.escapeHtml(featureName)}</label>
						</div>
					</div>
					<div class="wpconsent-preferences-accordion-content">
						<p tabindex="0">${this.escapeHtml(featureDescription)}</p>
						${illustrationsHtml}
					</div>
				</div>
			`;
		});

		html += '</div>'; // Close accordion container

		featuresList.innerHTML = html;

		// Mark as populated
		featuresList.dataset.populated = 'true';

		// Bind accordion functionality for feature items
		this.bindFeatureAccordion();
	}

	/**
	 * Populate special features list in the features tab.
	 */
	populateSpecialFeatures() {
		const specialFeaturesList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-special-features-list');
		if (!specialFeaturesList) {
			return;
		}

		// Check if already populated
		if (specialFeaturesList.dataset.populated === 'true') {
			return;
		}

		// Check if GVL is available
		if (!this.gvl || !this.gvl.specialFeatures) {
			specialFeaturesList.innerHTML = '<p class="wpconsent-no-special-features">Special features data not available.</p>';
			return;
		}

		const specialFeatures = this.gvl.specialFeatures;

		if (Object.keys(specialFeatures).length === 0) {
			specialFeaturesList.innerHTML = '<p class="wpconsent-no-special-features">No special features available.</p>';
			return;
		}

		// Build HTML for special features list with description
		let html = '<div class="wpconsent-tcf-section-separator"></div>';
		html += '<div class="wpconsent-tcf-special-features-description">';
		html += '<h4 class="wpconsent-tcf-section-heading">' + this.escapeHtml(this.getTranslation('Special Features')) + '</h4>';
		html += '<p class="wpconsent-tab-description">';
		html += this.escapeHtml(this.getTranslation('special_features_description'));
		html += '</p>';
		html += '</div>';
		html += '<div class="wpconsent-tcf-special-features-accordion">';

		// Sort special features by ID for consistent order
		const sortedFeatures = Object.keys(specialFeatures)
			.map(id => ({ id, ...specialFeatures[id] }))
			.sort((a, b) => parseInt(a.id) - parseInt(b.id));

		sortedFeatures.forEach(feature => {
			const featureId = feature.id;
			const featureName = feature.name || `Special Feature ${featureId}`;
			const featureDescription = feature.description || feature.descriptionLegal || '';
			const illustrations = feature.illustrations || [];

			// Build illustrations HTML if available (IAB TCF Policy B(b)).
			let illustrationsHtml = '';
			if (illustrations.length > 0) {
				illustrationsHtml = '<div class="wpconsent-iab-illustrations">';
				illustrationsHtml += `<p class="wpconsent-iab-illustrations-label"><strong>${this.escapeHtml(this.getTranslation('Examples:'))}</strong></p>`;
				illustrations.forEach(illustration => {
					illustrationsHtml += `<p class="wpconsent-iab-illustration">${this.escapeHtml(illustration)}</p>`;
				});
				illustrationsHtml += '</div>';
			}

			html += `
				<div class="wpconsent-preferences-accordion-item wpconsent-special-feature-item" data-feature-id="${featureId}">
					<div class="wpconsent-preferences-accordion-header">
						<div class="wpconsent-cookie-category-text">
							<button class="wpconsent-preferences-accordion-toggle">
								<span class="wpconsent-preferences-accordion-arrow"></span>
							</button>
							<label>${this.escapeHtml(featureName)}</label>
						</div>
						<div class="wpconsent-cookie-category-checkbox">
							<label class="wpconsent-preferences-checkbox-toggle">
								<input type="checkbox" id="tcf-special-feature-${featureId}" name="wpconsent_tcf_special_feature[]" value="${featureId}" class="wpconsent-special-feature-checkbox">
								<span class="wpconsent-preferences-checkbox-toggle-slider"></span>
							</label>
						</div>
					</div>
					<div class="wpconsent-preferences-accordion-content">
						<p tabindex="0">${this.escapeHtml(featureDescription)}</p>
						${illustrationsHtml}
						<p class="wpconsent-special-feature-vendor-link">
							<button type="button" class="wpconsent-view-vendors-link" data-feature-id="${featureId}">View vendors using this special feature</button>
						</p>
					</div>
				</div>
			`;
		});

		html += '</div>'; // Close accordion container

		specialFeaturesList.innerHTML = html;

		// Mark as populated
		specialFeaturesList.dataset.populated = 'true';

		// Bind accordion functionality for special feature items
		this.bindSpecialFeatureAccordion();
	}

	/**
	 * Bind accordion functionality for special purpose items.
	 */
	bindSpecialPurposeAccordion() {
		// Bind accordion toggle buttons
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-special-purpose-item .wpconsent-preferences-accordion-toggle').forEach(toggle => {
			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				const item = toggle.closest('.wpconsent-preferences-accordion-item');

				// Toggle accordion using CSS classes (works with CSS transitions)
				item.classList.toggle('active');
			});
		});

		// Bind "View vendors" links for special purposes
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-view-vendors-special-purpose').forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const specialPurposeId = link.dataset.specialPurposeId;
				if (specialPurposeId) {
					this.switchToVendorsWithSpecialPurpose(specialPurposeId);
				}
			});
		});

		// Bind "View vendors" links for regular purposes (from PHP template)
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-view-vendors-purpose').forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const purposeId = link.dataset.purposeId;
				if (purposeId) {
					this.switchToVendorsWithPurpose(purposeId);
				}
			});
		});
	}

	/**
	 * Bind accordion functionality for feature items.
	 */
	bindFeatureAccordion() {
		// Bind accordion toggle buttons
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-feature-item .wpconsent-preferences-accordion-toggle').forEach(toggle => {
			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				const item = toggle.closest('.wpconsent-preferences-accordion-item');

				// Toggle accordion using CSS classes (works with CSS transitions)
				item.classList.toggle('active');
			});
		});
	}

	/**
	 * Bind accordion functionality for special feature items.
	 */
	bindSpecialFeatureAccordion() {
		// Bind accordion toggle buttons
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-special-feature-item .wpconsent-preferences-accordion-toggle').forEach(toggle => {
			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				const item = toggle.closest('.wpconsent-preferences-accordion-item');

				// Toggle accordion using CSS classes (works with CSS transitions)
				item.classList.toggle('active');
			});
		});

		// Bind "View vendors" links
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-view-vendors-link').forEach(link => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const featureId = link.dataset.featureId;
				if (featureId) {
					this.switchToVendorsWithSpecialFeature(featureId);
				}
			});
		});
	}

	/**
	 * Search vendors by name.
	 *
	 * @param {string} searchTerm The search term.
	 */
	searchVendors(searchTerm) {
		const vendorItems = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-tcf-vendor-item');
		if (!vendorItems) {
			return;
		}

		// Handle undefined/null searchTerm
		if (!searchTerm) {
			searchTerm = '';
		}

		const term = searchTerm.toLowerCase().trim();
		let visibleCount = 0;

		vendorItems.forEach(item => {
			const vendorLabel = item.querySelector('.wpconsent-cookie-category-text label');
			const vendorName = vendorLabel ? vendorLabel.textContent.toLowerCase() : '';

			if (!term || vendorName.includes(term)) {
				item.style.display = '';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		});

		// Update no vendors message
		const vendorsList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-vendors-list');
		if (vendorsList) {
			let noVendorsMsg = vendorsList.querySelector('.wpconsent-no-vendors');

			if (visibleCount === 0) {
				if (!noVendorsMsg) {
					const accordion = vendorsList.querySelector('.wpconsent-tcf-vendors-accordion');
					if (accordion) {
						noVendorsMsg = document.createElement('p');
						noVendorsMsg.className = 'wpconsent-no-vendors';
						vendorsList.insertBefore(noVendorsMsg, accordion);
					}
				}
				if (noVendorsMsg) {
					noVendorsMsg.textContent = this.vendors.length === 0 ? 'No vendors available.' : 'No vendors found matching your search.';
					noVendorsMsg.style.display = '';
				}
			} else {
				if (noVendorsMsg) {
					noVendorsMsg.style.display = 'none';
				}
			}
		}
	}

	/**
	 * Populate vendors list in the vendors tab.
	 */
	populateVendors() {
		const vendorsList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-vendors-list');
		if (!vendorsList) {
			return;
		}

		// Load vendors if not already loaded
		if (this.vendors.length === 0) {
			this.loadVendors();
		}

		this.renderVendorsList();
	}

	/**
	 * Render the vendors list HTML.
	 */
	renderVendorsList() {
		const vendorsList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-vendors-list');
		if (!vendorsList) {
			return;
		}

		if (this.vendors.length === 0) {
			vendorsList.innerHTML = '<p class="wpconsent-no-vendors">No vendors available.</p>';
			return;
		}

		// Check if legitimate interest is globally disabled.
		const isLegitimateInterestDisabled = this.isLegitimateInterestGloballyDisabled();

		// Start with table-like structure
		let html = '<div class="wpconsent-tcf-vendors-table">';

		// Add header row with master toggles
		html += `
			<div class="wpconsent-tcf-vendors-header">
				<div class="wpconsent-vendor-name-header">
					<!-- Empty space for vendor names -->
				</div>
				<div class="wpconsent-vendor-toggles-header">
					<div class="wpconsent-master-toggle-group">
		`;

		// Only show legitimate interest column if it's not globally disabled.
		if (!isLegitimateInterestDisabled) {
			html += `
						<div class="wpconsent-master-toggle-column">
							<span class="wpconsent-master-toggle-label">Legitimate Interest</span>
							<label class="wpconsent-preferences-checkbox-toggle wpconsent-master-toggle">
								<input type="checkbox" id="tcf-master-legint-toggle" class="wpconsent-master-legint-toggle">
								<span class="wpconsent-preferences-checkbox-toggle-slider"></span>
							</label>
						</div>
			`;
		}

		html += `
						<div class="wpconsent-master-toggle-column">
							<span class="wpconsent-master-toggle-label">Consent</span>
							<label class="wpconsent-preferences-checkbox-toggle wpconsent-master-toggle">
								<input type="checkbox" id="tcf-master-consent-toggle" class="wpconsent-master-consent-toggle">
								<span class="wpconsent-preferences-checkbox-toggle-slider"></span>
							</label>
						</div>
					</div>
				</div>
			</div>
		`;

		// Add accordion container for vendors
		html += '<div class="wpconsent-tcf-vendors-accordion">';

		// Render all vendors (not just filtered ones, since search now uses show/hide)
		this.vendors.forEach((vendor, index) => {
			const vendorId = vendor.id || index;
			const vendorName = vendor.name || 'Unknown Vendor';
			const hasLegitimateInterest = vendor.legIntPurposes && vendor.legIntPurposes.length > 0;
			const hasPurposes = vendor.purposes && vendor.purposes.length > 0;

			// Build checkbox HTML for table-like alignment
			let checkboxesHtml = `
				<div class="wpconsent-vendor-table-checkboxes">
			`;

			// Only show legitimate interest column if it's not globally disabled.
			if (!isLegitimateInterestDisabled) {
				checkboxesHtml += `
					<div class="wpconsent-vendor-checkbox-column wpconsent-legint-column">
				`;

				// Add legitimate interest checkbox if needed
				if (hasLegitimateInterest) {
					checkboxesHtml += `
						<label class="wpconsent-preferences-checkbox-toggle">
							<input type="checkbox" id="tcf-vendor-legint-${vendorId}" name="wpconsent_tcf_vendor_legint[]" value="${vendorId}" class="wpconsent-vendor-legint-checkbox" checked>
							<span class="wpconsent-preferences-checkbox-toggle-slider"></span>
						</label>
					`;
				}

				checkboxesHtml += `
					</div>
				`;
			}

			checkboxesHtml += `
					<div class="wpconsent-vendor-checkbox-column wpconsent-consent-column">
			`;

			// Add consent checkbox if needed
			if (hasPurposes) {
				checkboxesHtml += `
					<label class="wpconsent-preferences-checkbox-toggle">
						<input type="checkbox" id="tcf-vendor-consent-${vendorId}" name="wpconsent_tcf_vendor_consent[]" value="${vendorId}" class="wpconsent-vendor-consent-checkbox">
						<span class="wpconsent-preferences-checkbox-toggle-slider"></span>
					</label>
				`;
			}

			checkboxesHtml += `
					</div>
				</div>
			`;

			html += `
				<div class="wpconsent-preferences-accordion-item wpconsent-cookie-category wpconsent-tcf-vendor-item" data-vendor-id="${vendorId}" data-purposes="${this.escapeHtml( JSON.stringify( vendor.purposes || [] ) )}" data-special-purposes="${this.escapeHtml( JSON.stringify( vendor.specialPurposes || [] ) )}" data-special-features="${this.escapeHtml( JSON.stringify( vendor.specialFeatures || [] ) )}" data-content-loaded="false">
					<div class="wpconsent-preferences-accordion-header wpconsent-vendor-table-row">
						<div class="wpconsent-cookie-category-text wpconsent-vendor-name-cell">
							<button class="wpconsent-preferences-accordion-toggle">
								<span class="wpconsent-preferences-accordion-arrow"></span>
							</button>
							<label>${this.escapeHtml( vendorName )}</label>
						</div>
						${checkboxesHtml}
					</div>
					<div class="wpconsent-preferences-accordion-content">
						<!-- Content will be loaded on demand -->
					</div>
				</div>
			`;
		});

		html += '</div>'; // Close accordion container
		html += '</div>'; // Close table container
		vendorsList.innerHTML = html;

		// Bind accordion functionality for vendor items
		this.bindVendorAccordion();

		// Bind master toggle functionality
		this.bindMasterToggles();
	}

	/**
	 * Generate detailed content for a vendor accordion item.
	 *
	 * @param {Object} vendor The vendor object with all properties.
	 * @return {string} HTML content for the vendor.
	 */
	generateVendorContent(vendor) {

		return '<div class="wpconsent-vendor-details"></div>';
	}

	/**
	 * Bind accordion functionality for vendor items.
	 */
	bindVendorAccordion() {
		window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-tcf-vendor-item .wpconsent-preferences-accordion-toggle').forEach(toggle => {
			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				const item = toggle.closest('.wpconsent-preferences-accordion-item');
				const contentContainer = item.querySelector('.wpconsent-preferences-accordion-content');
				const vendorId = item.dataset.vendorId;
				const isContentLoaded = item.dataset.contentLoaded === 'true';
				const isOpening = !item.classList.contains('active');

				// If opening accordion and content hasn't been loaded yet, generate it
				if (isOpening && !isContentLoaded) {
					// Find the vendor data from our vendors array
					const vendor = this.vendors.find(v => v.id == vendorId);
					if (vendor) {
						// Generate content on-demand
						const vendorContent = this.generateVendorContent(vendor);
						contentContainer.innerHTML = vendorContent;
						// Mark content as loaded to avoid regenerating
						item.dataset.contentLoaded = 'true';
					}
				}

				// Toggle accordion using CSS classes (works with CSS transitions)
				item.classList.toggle('active');

				// Fire vendor events using GVL data instead of data attributes
				this.fireVendorTabEvent(item, vendorId);
			});
		});
	}

	/**
	 * Bind master toggle functionality for consent and legitimate interest.
	 */
	bindMasterToggles() {
		const shadowRoot = window.WPConsent.shadowRoot;
		if (!shadowRoot) {
			return;
		}

		// Bind master consent toggle
		const masterConsentToggle = shadowRoot.querySelector('.wpconsent-master-consent-toggle');
		if (masterConsentToggle) {
			masterConsentToggle.addEventListener('change', (e) => {
				const isChecked = e.target.checked;
				const vendorConsentCheckboxes = shadowRoot.querySelectorAll('.wpconsent-vendor-consent-checkbox');

				vendorConsentCheckboxes.forEach(checkbox => {
					checkbox.checked = isChecked;
					// Trigger change event for each checkbox to ensure proper handling
					checkbox.dispatchEvent(new Event('change', { bubbles: true }));
				});
			});
		}

		// Bind master legitimate interest toggle
		const masterLegIntToggle = shadowRoot.querySelector('.wpconsent-master-legint-toggle');
		if (masterLegIntToggle) {
			masterLegIntToggle.addEventListener('change', (e) => {
				const isChecked = e.target.checked;
				const vendorLegIntCheckboxes = shadowRoot.querySelectorAll('.wpconsent-vendor-legint-checkbox');

				vendorLegIntCheckboxes.forEach(checkbox => {
					checkbox.checked = isChecked;
					// Trigger change event for each checkbox to ensure proper handling
					checkbox.dispatchEvent(new Event('change', { bubbles: true }));
				});
			});
		}

		// Update master toggles state when individual checkboxes change
		this.bindIndividualCheckboxUpdates();
	}

	/**
	 * Bind individual checkbox updates to update master toggle states.
	 */
	bindIndividualCheckboxUpdates() {
		const shadowRoot = window.WPConsent.shadowRoot;
		if (!shadowRoot) {
			return;
		}

		// Update master consent toggle state based on individual checkboxes
		const updateMasterConsentState = () => {
			const masterConsentToggle = shadowRoot.querySelector('.wpconsent-master-consent-toggle');
			const vendorConsentCheckboxes = shadowRoot.querySelectorAll('.wpconsent-vendor-consent-checkbox');

			if (masterConsentToggle && vendorConsentCheckboxes.length > 0) {
				const checkedCount = Array.from(vendorConsentCheckboxes).filter(cb => cb.checked).length;
				const totalCount = vendorConsentCheckboxes.length;

				if (checkedCount === 0) {
					masterConsentToggle.checked = false;
					masterConsentToggle.indeterminate = false;
				} else if (checkedCount === totalCount) {
					masterConsentToggle.checked = true;
					masterConsentToggle.indeterminate = false;
				} else {
					masterConsentToggle.checked = false;
					masterConsentToggle.indeterminate = true;
				}
			}
		};

		// Update master legitimate interest toggle state based on individual checkboxes
		const updateMasterLegIntState = () => {
			const masterLegIntToggle = shadowRoot.querySelector('.wpconsent-master-legint-toggle');
			const vendorLegIntCheckboxes = shadowRoot.querySelectorAll('.wpconsent-vendor-legint-checkbox');

			if (masterLegIntToggle && vendorLegIntCheckboxes.length > 0) {
				const checkedCount = Array.from(vendorLegIntCheckboxes).filter(cb => cb.checked).length;
				const totalCount = vendorLegIntCheckboxes.length;

				if (checkedCount === 0) {
					masterLegIntToggle.checked = false;
					masterLegIntToggle.indeterminate = false;
				} else if (checkedCount === totalCount) {
					masterLegIntToggle.checked = true;
					masterLegIntToggle.indeterminate = false;
				} else {
					masterLegIntToggle.checked = false;
					masterLegIntToggle.indeterminate = true;
				}
			}
		};

		// Bind change events to individual checkboxes
		shadowRoot.addEventListener('change', (e) => {
			if (e.target.classList.contains('wpconsent-vendor-consent-checkbox')) {
				updateMasterConsentState();
			} else if (e.target.classList.contains('wpconsent-vendor-legint-checkbox')) {
				updateMasterLegIntState();
			}
		});

		// Initialize master toggle states
		updateMasterConsentState();
		updateMasterLegIntState();
	}

	/**
	 * Fire vendor events for vendors tab using GVL data instead of data attributes.
	 *
	 * @param {Element} item The vendor accordion item element.
	 * @param {string} vendorId The vendor ID.
	 */
	fireVendorTabEvent(item, vendorId) {
		// Only fire events if GVL is available and vendor ID is provided
		if (!this.gvl || !this.gvl.vendors || !vendorId) {
			return;
		}

		// Get vendor data directly from GVL using vendor ID
		const gvlVendor = this.gvl.vendors[vendorId];
		if (!gvlVendor) {
			console.warn(`Vendor with ID ${vendorId} not found in GVL`);
			return;
		}

		// Determine event type based on accordion state
		const isActive = item.classList.contains('active');
		const eventType = isActive ? 'vendorDetailsOpened' : 'vendorDetailsClosed';

		// Build comprehensive vendor data from GVL
		const vendorData = {
			vendor_id: vendorId,
			name: gvlVendor.name || 'Unknown Vendor',
			purposes: gvlVendor.purposes || [],
			legIntPurposes: gvlVendor.legIntPurposes || [],
			flexiblePurposes: gvlVendor.flexiblePurposes || [],
			specialPurposes: gvlVendor.specialPurposes || [],
			features: gvlVendor.features || [],
			specialFeatures: gvlVendor.specialFeatures || [],
			policyUrl: gvlVendor.policyUrl || '',
			legIntClaimUrl: gvlVendor.legIntClaimUrl || '',
			cookieMaxAgeSeconds: gvlVendor.cookieMaxAgeSeconds,
			cookieRefresh: gvlVendor.cookieRefresh,
			usesNonCookieAccess: gvlVendor.usesNonCookieAccess || false,
			deviceStorageDisclosureUrl: gvlVendor.deviceStorageDisclosureUrl || '',
			urls: gvlVendor.urls || [],
			dataRetention: gvlVendor.dataRetention || null,
			dataDeclaration: gvlVendor.dataDeclaration || []
		};

		// Create and dispatch the vendor event
		try {
			const customEvent = new CustomEvent(eventType, {
				detail: {
					vendorId: vendorId,
					vendorData: vendorData,
					accordion: item,
					content: item.querySelector('.wpconsent-preferences-accordion-content'),
					source: 'vendorsTab' // Identify this as coming from vendors tab
				}
			});

			// Dispatch the event on the document
			document.dispatchEvent(customEvent);
		} catch (error) {
			console.error('Error firing vendor tab event:', error);
		}
	}

	/**
	 * Toggle the filter dropdown visibility.
	 */
	toggleFilterDropdown() {
		const dropdown = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-filter-dropdown');
		const button = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-filter-btn');

		if (!dropdown || !button) {
			return;
		}

		if (this.isFilterDropdownOpen) {
			this.closeFilterDropdown();
		} else {
			dropdown.style.display = 'block';
			button.classList.add('active');
			this.isFilterDropdownOpen = true;
		}
	}

	/**
	 * Close the filter dropdown.
	 */
	closeFilterDropdown() {
		const dropdown = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-filter-dropdown');
		const button = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-filter-btn');

		if (dropdown) {
			dropdown.style.display = 'none';
		}
		if (button) {
			button.classList.remove('active');
		}
		this.isFilterDropdownOpen = false;
	}

	/**
	 * Clear all selected filters.
	 */
	clearAllFilters() {
		this.selectedPurposes.clear();
		this.selectedSpecialPurposes.clear();
		this.selectedSpecialFeatures.clear();

		// Uncheck all purpose checkboxes
		const purposeCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-filter-purpose-checkbox');
		if (purposeCheckboxes) {
			purposeCheckboxes.forEach(checkbox => {
				checkbox.checked = false;
			});
		}

		// Uncheck all special purpose checkboxes
		const specialPurposeCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-filter-specialpurpose-checkbox');
		if (specialPurposeCheckboxes) {
			specialPurposeCheckboxes.forEach(checkbox => {
				checkbox.checked = false;
			});
		}

		// Uncheck all special feature checkboxes
		const specialFeatureCheckboxes = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-filter-specialfeature-checkbox');
		if (specialFeatureCheckboxes) {
			specialFeatureCheckboxes.forEach(checkbox => {
				checkbox.checked = false;
			});
		}

		this.applyFilters();
	}

	/**
	 * Apply the current filters to vendors.
	 */
	applyFilters() {
		const vendorItems = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-tcf-vendor-item');
		if (!vendorItems) {
			return;
		}

		let visibleCount = 0;

		vendorItems.forEach(item => {
			let shouldShow = true;

			// Check purpose filters
			if (this.selectedPurposes.size > 0) {
				const vendorPurposesData = item.dataset.purposes;
				let vendorPurposes = [];

				if (vendorPurposesData) {
					try {
						vendorPurposes = JSON.parse(vendorPurposesData);
					} catch (e) {
						console.error('Error parsing vendor purposes:', e);
						vendorPurposes = [];
					}
				}

				// Check if vendor has any of the selected purposes
				const hasMatchingPurpose = vendorPurposes.some(purposeId =>
					this.selectedPurposes.has(String(purposeId))
				);

				if (!hasMatchingPurpose) {
					shouldShow = false;
				}
			}

			// Check special purpose filters
			if (this.selectedSpecialPurposes.size > 0 && shouldShow) {
				const vendorSpecialPurposesData = item.dataset.specialPurposes;
				let vendorSpecialPurposes = [];

				if (vendorSpecialPurposesData) {
					try {
						vendorSpecialPurposes = JSON.parse(vendorSpecialPurposesData);
					} catch (e) {
						console.error('Error parsing vendor special purposes:', e);
						vendorSpecialPurposes = [];
					}
				}

				// Check if vendor has any of the selected special purposes
				const hasMatchingSpecialPurpose = vendorSpecialPurposes.some(purposeId =>
					this.selectedSpecialPurposes.has(String(purposeId))
				);

				if (!hasMatchingSpecialPurpose) {
					shouldShow = false;
				}
			}

			// Check special feature filters
			if (this.selectedSpecialFeatures.size > 0 && shouldShow) {
				const vendorSpecialFeaturesData = item.dataset.specialFeatures;
				let vendorSpecialFeatures = [];

				if (vendorSpecialFeaturesData) {
					try {
						vendorSpecialFeatures = JSON.parse(vendorSpecialFeaturesData);
					} catch (e) {
						console.error('Error parsing vendor special features:', e);
						vendorSpecialFeatures = [];
					}
				}

				// Check if vendor has any of the selected special features
				const hasMatchingSpecialFeature = vendorSpecialFeatures.some(featureId =>
					this.selectedSpecialFeatures.has(String(featureId))
				);

				if (!hasMatchingSpecialFeature) {
					shouldShow = false;
				}
			}

			// Also check search term if it exists
			const searchInput = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-search');
			if (searchInput && searchInput.value.trim() && shouldShow) {
				const searchTerm = searchInput.value.toLowerCase().trim();
				const vendorLabel = item.querySelector('.wpconsent-cookie-category-text label');
				const vendorName = vendorLabel ? vendorLabel.textContent.toLowerCase() : '';

				if (!vendorName.includes(searchTerm)) {
					shouldShow = false;
				}
			}

			if (shouldShow) {
				item.style.display = '';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		});

		// Update no vendors message
		this.updateNoVendorsMessage(visibleCount);

		// Update active filters display
		this.updateActiveFiltersDisplay();
	}

	/**
	 * Update the no vendors message based on visible count.
	 *
	 * @param {number} visibleCount Number of visible vendors.
	 */
	updateNoVendorsMessage(visibleCount) {
		const vendorsList = window.WPConsent.shadowRoot?.getElementById('wpconsent-tcf-vendors-list');
		if (!vendorsList) {
			return;
		}

		let noVendorsMsg = vendorsList.querySelector('.wpconsent-no-vendors');

		if (visibleCount === 0) {
			if (!noVendorsMsg) {
				const accordion = vendorsList.querySelector('.wpconsent-tcf-vendors-accordion');
				if (accordion) {
					noVendorsMsg = document.createElement('p');
					noVendorsMsg.className = 'wpconsent-no-vendors';
					vendorsList.insertBefore(noVendorsMsg, accordion);
				}
			}
			if (noVendorsMsg) {
				const hasSearch = window.WPConsent.shadowRoot?.getElementById('wpconsent-vendor-search')?.value.trim();
				const hasFilters = this.selectedPurposes.size > 0 || this.selectedSpecialPurposes.size > 0 || this.selectedSpecialFeatures.size > 0;

				let message;
				if (this.vendors.length === 0) {
					message = 'No vendors available.';
				} else if (hasSearch && hasFilters) {
					message = 'No vendors found matching your search and filter criteria.';
				} else if (hasSearch) {
					message = 'No vendors found matching your search.';
				} else if (hasFilters) {
					message = 'No vendors found matching your filter criteria.';
				} else {
					message = 'No vendors available.';
				}

				noVendorsMsg.textContent = message;
				noVendorsMsg.style.display = '';
			}
		} else {
			if (noVendorsMsg) {
				noVendorsMsg.style.display = 'none';
			}
		}
	}

	/**
	 * Update the active filters display to show currently selected filters.
	 */
	updateActiveFiltersDisplay() {
		const activeFiltersContainer = window.WPConsent.shadowRoot?.getElementById('wpconsent-active-filters');
		if (!activeFiltersContainer) {
			return;
		}

		// Check if any filters are active
		const hasActiveFilters = this.selectedPurposes.size > 0 || this.selectedSpecialPurposes.size > 0 || this.selectedSpecialFeatures.size > 0;

		if (!hasActiveFilters) {
			activeFiltersContainer.innerHTML = '';
			activeFiltersContainer.style.display = 'none';
			return;
		}

		// Build the active filters HTML
		let html = '<div class="wpconsent-active-filters-list">';
		html += `<span class="wpconsent-active-filters-label">${this.escapeHtml(this.getTranslation('Active filters:'))}</span>`;

		// Add purpose filter chips
		if (this.gvl && this.gvl.purposes) {
			this.selectedPurposes.forEach(purposeId => {
				const purpose = this.gvl.purposes[purposeId];
				if (purpose) {
					const purposeName = this.escapeHtml(purpose.name || `Purpose ${purposeId}`);
					html += `<span class="wpconsent-filter-chip wpconsent-purpose-chip" data-purpose-id="${purposeId}">
						${purposeName}
						<button type="button" class="wpconsent-filter-chip-remove" data-filter-type="purpose" data-filter-id="${purposeId}" aria-label="Remove filter">×</button>
					</span>`;
				}
			});
		}

		// Add special purpose filter chips
		if (this.gvl && this.gvl.specialPurposes) {
			this.selectedSpecialPurposes.forEach(purposeId => {
				const purpose = this.gvl.specialPurposes[purposeId];
				if (purpose) {
					const purposeName = this.escapeHtml(purpose.name || `Special Purpose ${purposeId}`);
					html += `<span class="wpconsent-filter-chip wpconsent-specialpurpose-chip" data-purpose-id="${purposeId}">
						${purposeName}
						<button type="button" class="wpconsent-filter-chip-remove" data-filter-type="specialpurpose" data-filter-id="${purposeId}" aria-label="Remove filter">×</button>
					</span>`;
				}
			});
		}

		// Add special feature filter chips
		if (this.gvl && this.gvl.specialFeatures) {
			this.selectedSpecialFeatures.forEach(featureId => {
				const feature = this.gvl.specialFeatures[featureId];
				if (feature) {
					const featureName = this.escapeHtml(feature.name || `Special Feature ${featureId}`);
					html += `<span class="wpconsent-filter-chip wpconsent-specialfeature-chip" data-feature-id="${featureId}">
						${featureName}
						<button type="button" class="wpconsent-filter-chip-remove" data-filter-type="specialfeature" data-filter-id="${featureId}" aria-label="Remove filter">×</button>
					</span>`;
				}
			});
		}

		html += '</div>';

		activeFiltersContainer.innerHTML = html;
		activeFiltersContainer.style.display = 'block';

		// Bind remove handlers for filter chips
		this.bindActiveFilterRemoveHandlers();
	}

	/**
	 * Bind click handlers for removing individual filters from active filters display.
	 */
	bindActiveFilterRemoveHandlers() {
		const removeButtons = window.WPConsent.shadowRoot?.querySelectorAll('.wpconsent-filter-chip-remove');
		if (!removeButtons) {
			return;
		}

		removeButtons.forEach(button => {
			button.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();

				const filterType = button.dataset.filterType;
				const filterId = button.dataset.filterId;

				if (filterType === 'purpose') {
					this.selectedPurposes.delete(filterId);
					// Uncheck the corresponding checkbox in the dropdown
					const checkbox = window.WPConsent.shadowRoot?.querySelector(`.wpconsent-filter-purpose-checkbox[value="${filterId}"]`);
					if (checkbox) {
						checkbox.checked = false;
					}
				} else if (filterType === 'specialpurpose') {
					this.selectedSpecialPurposes.delete(filterId);
					// Uncheck the corresponding checkbox in the dropdown
					const checkbox = window.WPConsent.shadowRoot?.querySelector(`.wpconsent-filter-specialpurpose-checkbox[value="${filterId}"]`);
					if (checkbox) {
						checkbox.checked = false;
					}
				} else if (filterType === 'specialfeature') {
					this.selectedSpecialFeatures.delete(filterId);
					// Uncheck the corresponding checkbox in the dropdown
					const checkbox = window.WPConsent.shadowRoot?.querySelector(`.wpconsent-filter-specialfeature-checkbox[value="${filterId}"]`);
					if (checkbox) {
						checkbox.checked = false;
					}
				}

				// Reapply filters
				this.applyFilters();
			});
		});
	}

	/**
	 * Populate the filter dropdown with purpose and special feature checkboxes.
	 * Includes vendor counts per purpose as required by TCF Policy C(c)(II) and D(c)(IV).
	 */
	populateFilterPurposes() {
		const purposesContainer = window.WPConsent.shadowRoot?.getElementById('wpconsent-filter-purposes');
		if (!purposesContainer) {
			return;
		}

		// Check if GVL is available and ready
		if (!this.gvl || !this.gvl.purposes) {
			console.warn('GVL purposes data not available');
			return;
		}

		const purposes = this.gvl.purposes;
		const specialFeatures = this.gvl.specialFeatures || {};
		let html = '';

		// Count vendors for each purpose (consent and legitimate interest).
		const purposeVendorCounts = {};
		if (this.vendors && this.vendors.length > 0) {
			this.vendors.forEach(vendor => {
				// Count vendors seeking consent for purposes.
				if (vendor.purposes && Array.isArray(vendor.purposes)) {
					vendor.purposes.forEach(purposeId => {
						if (!purposeVendorCounts[purposeId]) {
							purposeVendorCounts[purposeId] = { consent: 0, legInt: 0 };
						}
						purposeVendorCounts[purposeId].consent++;
					});
				}

				// Count vendors using legitimate interest for purposes.
				if (vendor.legIntPurposes && Array.isArray(vendor.legIntPurposes)) {
					vendor.legIntPurposes.forEach(purposeId => {
						if (!purposeVendorCounts[purposeId]) {
							purposeVendorCounts[purposeId] = { consent: 0, legInt: 0 };
						}
						purposeVendorCounts[purposeId].legInt++;
					});
				}
			});
		}

		// Add purposes section
		html += '<div class="wpconsent-filter-section wpconsent-filter-purposes-section">';
		html += '<h5 class="wpconsent-filter-section-title" style="font-weight: bold; color: #333; margin: 12px 0 8px 0; padding: 8px 0 4px 0; font-size: 13px; border-bottom: 1px solid #ddd;">Purposes</h5>';

		// Sort purposes by ID for consistent order
		const sortedPurposes = Object.keys(purposes)
			.map(id => ({ id, ...purposes[id] }))
			.sort((a, b) => parseInt(a.id) - parseInt(b.id));

		sortedPurposes.forEach(purpose => {
			const purposeId = purpose.id;
			const purposeName = purpose.name || `Purpose ${purposeId}`;
			const isChecked = this.selectedPurposes.has(String(purposeId));

			// Get vendor count for this purpose
			let vendorCountText = '';
			if (purposeVendorCounts[purposeId]) {
				const counts = purposeVendorCounts[purposeId];
				const parts = [];
				if (counts.consent > 0) {
					parts.push(`${counts.consent} seeking consent`);
				}
				if (counts.legInt > 0) {
					parts.push(`${counts.legInt} using legitimate interest`);
				}
				if (parts.length > 0) {
					vendorCountText = ` (${parts.join(', ')})`;
				}
			}

			html += `
				<div class="wpconsent-filter-purpose-item">
					<input type="checkbox"
						   class="wpconsent-filter-purpose-checkbox"
						   id="purpose-filter-${purposeId}"
						   value="${purposeId}"
						   ${isChecked ? 'checked' : ''}>
					<label class="wpconsent-filter-purpose-label" for="purpose-filter-${purposeId}">
						${this.escapeHtml(purposeName)}${this.escapeHtml(vendorCountText)}
					</label>
				</div>
			`;
		});

		html += '</div>'; // Close purposes section

		// Count vendors for each special purpose.
		const specialPurposeVendorCounts = {};
		const specialPurposes = this.gvl.specialPurposes || {};
		if (this.vendors && this.vendors.length > 0) {
			this.vendors.forEach(vendor => {
				if (vendor.specialPurposes && Array.isArray(vendor.specialPurposes)) {
					vendor.specialPurposes.forEach(purposeId => {
						if (!specialPurposeVendorCounts[purposeId]) {
							specialPurposeVendorCounts[purposeId] = 0;
						}
						specialPurposeVendorCounts[purposeId]++;
					});
				}
			});
		}

		// Add special purposes section if there are any
		if (Object.keys(specialPurposes).length > 0) {
			html += '<div class="wpconsent-filter-section wpconsent-filter-specialpurposes-section">';
			html += '<h5 class="wpconsent-filter-section-title" style="font-weight: bold; color: #333; margin: 12px 0 8px 0; padding: 8px 0 4px 0; font-size: 13px; border-bottom: 1px solid #ddd;">Special Purposes</h5>';

			// Sort special purposes by ID for consistent order
			const sortedSpecialPurposes = Object.keys(specialPurposes)
				.map(id => ({ id, ...specialPurposes[id] }))
				.sort((a, b) => parseInt(a.id) - parseInt(b.id));

			sortedSpecialPurposes.forEach(purpose => {
				const purposeId = purpose.id;
				const purposeName = purpose.name || `Special Purpose ${purposeId}`;
				const isChecked = this.selectedSpecialPurposes.has(String(purposeId));

				// Get vendor count for this special purpose
				let vendorCountText = '';
				if (specialPurposeVendorCounts[purposeId]) {
					vendorCountText = ` (${specialPurposeVendorCounts[purposeId]} vendor${specialPurposeVendorCounts[purposeId] !== 1 ? 's' : ''})`;
				}

				html += `
					<div class="wpconsent-filter-purpose-item wpconsent-filter-specialpurpose-item">
						<input type="checkbox"
							   class="wpconsent-filter-specialpurpose-checkbox"
							   id="specialpurpose-filter-${purposeId}"
							   value="${purposeId}"
							   ${isChecked ? 'checked' : ''}>
						<label class="wpconsent-filter-purpose-label wpconsent-filter-specialpurpose-label" for="specialpurpose-filter-${purposeId}">
							${this.escapeHtml(purposeName)}${this.escapeHtml(vendorCountText)}
						</label>
					</div>
				`;
			});

			html += '</div>'; // Close special purposes section
		}

		// Count vendors for each special feature.
		const specialFeatureVendorCounts = {};
		if (this.vendors && this.vendors.length > 0) {
			this.vendors.forEach(vendor => {
				if (vendor.specialFeatures && Array.isArray(vendor.specialFeatures)) {
					vendor.specialFeatures.forEach(featureId => {
						if (!specialFeatureVendorCounts[featureId]) {
							specialFeatureVendorCounts[featureId] = 0;
						}
						specialFeatureVendorCounts[featureId]++;
					});
				}
			});
		}

		// Add special features section if there are any
		if (Object.keys(specialFeatures).length > 0) {
			html += '<div class="wpconsent-filter-section wpconsent-filter-specialfeatures-section">';
			html += '<h5 class="wpconsent-filter-section-title" style="font-weight: bold; color: #333; margin: 12px 0 8px 0; padding: 8px 0 4px 0; font-size: 13px; border-bottom: 1px solid #ddd;">Special Features</h5>';

			// Sort special features by ID for consistent order
			const sortedFeatures = Object.keys(specialFeatures)
				.map(id => ({ id, ...specialFeatures[id] }))
				.sort((a, b) => parseInt(a.id) - parseInt(b.id));

			sortedFeatures.forEach(feature => {
				const featureId = feature.id;
				const featureName = feature.name || `Special Feature ${featureId}`;
				const isChecked = this.selectedSpecialFeatures.has(String(featureId));

				// Get vendor count for this special feature
				let vendorCountText = '';
				if (specialFeatureVendorCounts[featureId]) {
					vendorCountText = ` (${specialFeatureVendorCounts[featureId]} vendor${specialFeatureVendorCounts[featureId] !== 1 ? 's' : ''})`;
				}

				html += `
					<div class="wpconsent-filter-purpose-item wpconsent-filter-specialfeature-item">
						<input type="checkbox"
							   class="wpconsent-filter-specialfeature-checkbox"
							   id="specialfeature-filter-${featureId}"
							   value="${featureId}"
							   ${isChecked ? 'checked' : ''}>
						<label class="wpconsent-filter-purpose-label wpconsent-filter-specialfeature-label" for="specialfeature-filter-${featureId}">
							${this.escapeHtml(featureName)}${this.escapeHtml(vendorCountText)}
						</label>
					</div>
				`;
			});

			html += '</div>'; // Close special features section
		}

		purposesContainer.innerHTML = html;
	}

	/**
	 * Override the searchVendors method to work with filters.
	 *
	 * @param {string} searchTerm The search term.
	 */
	searchVendors(searchTerm) {
		// Use the combined filtering approach
		this.applyFilters();
	}

	/**
	 * Get translation for a text string.
	 * Uses translations from backend if available, falls back to the text itself.
	 *
	 * @param {string} text The text to translate.
	 * @return {string} The translated text.
	 */
	getTranslation(text) {
		// Use backend translations if available from localized script data.
		if (window.wpconsent && window.wpconsent.iab_tcf_translations) {
			const backendTranslations = window.wpconsent.iab_tcf_translations;
			if (backendTranslations[text]) {
				return backendTranslations[text];
			}
		}

		// Fallback: Basic translations for when backend data is not available.
		const fallbackTranslations = {
			'Special Purposes': 'Special Purposes',
			'special_purposes_description': 'Special purposes are processing activities that do not require your consent. They are essential for the functioning of the service, such as security, fraud prevention, and technical delivery.',
			'View vendors using this special purpose': 'View vendors using this special purpose',
			'Features': 'Features',
			'features_description': 'Features are technical capabilities that vendors use to support their data processing purposes. These features do not require separate consent.',
			'Special Features': 'Special Features',
			'special_features_description': 'Special features are data processing practices that require your explicit consent. You can object to their use by the vendors listed here.',
			'Active filters:': 'Active filters:',
			'Examples:': 'Examples:'
		};

		return fallbackTranslations[text] || text;
	}

	/**
	 * Check if legitimate interest is globally disabled in admin settings.
	 *
	 * @return {boolean} True if legitimate interest is globally disabled, false otherwise.
	 */
	isLegitimateInterestGloballyDisabled() {
		// Check if publisher restrictions are available.
		if (!window.wpconsent || !window.wpconsent.iab_tcf_publisher_restrictions) {
			return false;
		}

		const restrictions = window.wpconsent.iab_tcf_publisher_restrictions;

		// Check if global disallow_li_purposes is set to 'all'.
		if (restrictions.global &&
			restrictions.global.disallow_li_purposes &&
			Array.isArray(restrictions.global.disallow_li_purposes) &&
			restrictions.global.disallow_li_purposes.includes('all')) {
			return true;
		}

		return false;
	}

	/**
	 * Escape HTML to prevent XSS.
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

// Initialize the tabbed interface
new WPConsentIABTabs();