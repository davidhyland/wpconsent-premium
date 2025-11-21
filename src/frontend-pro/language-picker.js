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
				'.wpconsent_preferences_panel_description p': 'preferences_panel_description',
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
				translationMap[`.wpconsent-cookie-category-${slug} .wpconsent-preferences-accordion-content > p`] = slug + '_description';
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
