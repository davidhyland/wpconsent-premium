// Function to load language content
async function loadLanguageContent(locale, languageLinks = null) {
	try {
		const response = await fetch( `/wp-json/wpconsent/v1/language/${locale}` );
		const translations = await response.json();

		// Update banner text with translations
		if ( translations ) {
			// Define mapping of selectors to translation keys
			const translationMap = {
				'.wpconsent-banner-message': 'banner_message',
				'.wpconsent-accept-all': 'accept_button_text',
				'.wpconsent-cancel-all': 'cancel_button_text',
				'.wpconsent-preferences-all': 'preferences_button_text',
				'#wpconsent-preferences-title': 'preferences_panel_title',
				'.wpconsent_preferences_panel_description': 'preferences_panel_description',
				'.wpconsent-cookie-policy-title': 'cookie_policy_title',
				'.wpconsent-cookie-policy-text': 'cookie_policy_text',
				'.wpconsent-save-preferences': 'save_preferences_button_text',
				'.wpconsent-close-preferences': 'close_button_text',
			};

			// Loop through translations['categories'] object (not array) and add the items to translationMap.
			// This is to translate the categories in the preferences panel. The key is the category slug and it has name and description which sould replace .wpconsent-cookie-category-{slug} label and .wpconsent-cookie-category-{slug} p elements.
			Object.entries( translations['categories'] ).forEach( ( [slug, category] ) => {
				translations[ slug + '_name' ] = category.name;
				translations[ slug + '_description' ] = category.description;
				translationMap[`.wpconsent-cookie-category-${slug} .wpconsent-cookie-category-text label`] = slug + '_name';
				translationMap[`.wpconsent-cookie-category-${slug} .wpconsent-category-description`] = slug + '_description';

				// Translate services within each category if they exist.
				if ( category.services && Object.keys( category.services ).length > 0 ) {
					Object.entries( category.services ).forEach( ( [serviceSlug, service] ) => {
						const serviceKey = slug + '_service_' + serviceSlug;
						translations[ serviceKey + '_name' ] = service.name;
						translations[ serviceKey + '_description' ] = service.description;

						// We need to target each service individually by finding it within the specific category.
						// Since services can have the same name across categories, we scope them to their parent category.
						// The selector targets the category first, then finds service descriptions within it.
						const categorySelector = `.wpconsent-cookie-category-${slug}`;

						// For service descriptions, we'll handle them after the main translation map is processed.
						// Store service data for later processing to ensure correct targeting.
						if ( ! translations['__services__'] ) {
							translations['__services__'] = {};
						}
						if ( ! translations['__services__'][slug] ) {
							translations['__services__'][slug] = [];
						}
						translations['__services__'][slug].push({
							slug: serviceSlug,
							name: service.name,
							description: service.description,
							url: service.service_url,
							cookies: service.cookies || {}
						});
					} );
				}

				// Store category cookies for later processing.
				if ( category.cookies && Object.keys( category.cookies ).length > 0 ) {
					if ( ! translations['__category_cookies__'] ) {
						translations['__category_cookies__'] = {};
					}
					translations['__category_cookies__'][slug] = category.cookies;
				}
			} );

			// Update all elements with their translations
			Object.entries( translationMap ).forEach( ( [selector, translationKey] ) => {
				const elements = WPConsent.shadowRoot.querySelectorAll( selector );
				elements.forEach( element => {
					if ( element && translations[translationKey] ) {
						element.innerHTML = translations[translationKey];
					}
				} );
			} );

			// Update service descriptions separately to ensure correct targeting.
			// This handles the nested structure where services are within categories.
			if ( translations['__services__'] ) {
				Object.entries( translations['__services__'] ).forEach( ( [categorySlug, services] ) => {
					const categoryElement = WPConsent.shadowRoot.querySelector( `.wpconsent-cookie-category-${categorySlug}` );
					if ( categoryElement ) {
						// Get all service elements within this category
						const serviceElements = categoryElement.querySelectorAll( '.wpconsent-cookie-service' );

						// Match services by index since they appear in order.
						services.forEach( ( service, index ) => {
							if ( serviceElements[index] ) {
								// Update service description
								const descriptionElement = serviceElements[index].querySelector( '.wpconsent-service-description' );
								if ( descriptionElement && service.description ) {
									descriptionElement.innerHTML = service.description;
								}

								// Update service name (label)
								const labelElement = serviceElements[index].querySelector( '.wpconsent-cookie-category-text label' );
								if ( labelElement && service.name ) {
									labelElement.innerHTML = service.name;
								}

								// Update cookies within this service
								if ( service.cookies && Object.keys( service.cookies ).length > 0 ) {
									Object.entries( service.cookies ).forEach( ( [cookieId, cookie] ) => {
										const cookieRow = serviceElements[index].querySelector( `.wpconsent-preferences-list-item[data-cookie-id="${cookieId}"]` );
										if ( cookieRow ) {
											// Update cookie name
											const nameElement = cookieRow.querySelector( '.cookie-name' );
											if ( nameElement && cookie.name ) {
												nameElement.textContent = cookie.name;
											}

											// Update cookie description
											const descElement = cookieRow.querySelector( '.cookie-desc' );
											if ( descElement && cookie.description ) {
												descElement.innerHTML = cookie.description;
											}

											// Update cookie duration
											const durationElement = cookieRow.querySelector( '.cookie-duration' );
											if ( durationElement && cookie.duration ) {
												durationElement.textContent = cookie.duration;
											}
										}
									} );
								}
							}
						} );
					}
				} );
			}

			// Update category cookies (cookies not associated with services).
			if ( translations['__category_cookies__'] ) {
				Object.entries( translations['__category_cookies__'] ).forEach( ( [categorySlug, cookies] ) => {
					const categoryElement = WPConsent.shadowRoot.querySelector( `.wpconsent-cookie-category-${categorySlug}` );
					if ( categoryElement ) {
						// Update each cookie by its ID
						Object.entries( cookies ).forEach( ( [cookieId, cookie] ) => {
							const cookieRow = categoryElement.querySelector( `.wpconsent-preferences-list-item[data-cookie-id="${cookieId}"]` );
							if ( cookieRow ) {
								// Update cookie name
								const nameElement = cookieRow.querySelector( '.cookie-name' );
								if ( nameElement && cookie.name ) {
									nameElement.textContent = cookie.name;
								}

								// Update cookie description
								const descElement = cookieRow.querySelector( '.cookie-desc' );
								if ( descElement && cookie.description ) {
									descElement.innerHTML = cookie.description;
								}

								// Update cookie duration
								const durationElement = cookieRow.querySelector( '.cookie-duration' );
								if ( durationElement && cookie.duration ) {
									durationElement.textContent = cookie.duration;
								}
							}
						} );
					}
				} );
			}

			// Update active state in language picker if languageLinks is provided
			if (languageLinks) {
				languageLinks.forEach( l => {
					if (l.getAttribute('data-language') === locale) {
						l.classList.add('active');
					} else {
						l.classList.remove('active');
					}
				});
			}
		}
		return true;
	} catch ( error ) {
		console.error( 'Error fetching translations:', error );
		return false;
	}
}

// Initialize language picker after banner is initialized
function initializeLanguagePicker() {
	// Ensure WPConsent and its shadow root are available
	if (!window.WPConsent || !window.WPConsent.shadowRoot) {
		console.warn('WPConsent or its shadow root is not available yet');
		return;
	}

	const languageContainers = WPConsent.shadowRoot.querySelectorAll( '.wpconsent-language-picker' );

	languageContainers.forEach( container => {
		const button = container.querySelector( '.wpconsent-language-switch-button' );
		const languageLinks = container.querySelectorAll( '.wpconsent-language-item' );

		// Skip if we can't find the button or language links
		if (!button || !languageLinks.length) {
			return;
		}

		// Toggle dropdown on button click
		button.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
			container.classList.toggle( 'active' );
		} );

		// Handle language selection
		languageLinks.forEach( link => {
			link.addEventListener( 'click', async ( e ) => {
				e.preventDefault();
				const locale = link.getAttribute( 'data-language' );

				// Load the language content
				await loadLanguageContent(locale, languageLinks);

				// Close the dropdown
				container.classList.remove( 'active' );
			} );
		} );

		// Close dropdown when clicking outside
		document.addEventListener( 'click', ( e ) => {
			if ( !container.contains( e.target ) ) {
				container.classList.remove( 'active' );
			}
		} );
	} );
}

// Listen for the banner initialized event
window.addEventListener('wpconsent_banner_initialized', initializeLanguagePicker);
