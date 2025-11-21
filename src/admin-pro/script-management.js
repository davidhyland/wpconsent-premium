// Make the class available globally
window.WPConsentScriptManagement = class WPConsentScriptManagement {
	constructor() {
		if ( document.getElementById( 'wpconsent-modal-add-script' ) ) {
			this.modal = new WPConsentModalForm( 'wpconsent-modal-add-script' );
			this.bindEvents();
		}
	}

	bindEvents() {
		// Add new script button
		document.addEventListener( 'click', ( e ) => {
			const addButton = e.target.closest( '.wpconsent-add-script' );
			if ( addButton ) {
				this.handleAddScript( e );
			}
		} );

		// Edit script buttons
		document.addEventListener( 'click', ( e ) => {
			const button = e.target.closest( '.wpconsent-edit-script' );
			if ( button ) {
				this.handleEditScript( e );
			}
		} );

		// Delete script buttons
		document.addEventListener( 'click', ( e ) => {
			const deleteButton = e.target.closest( '.wpconsent-delete-script' );
			if ( deleteButton ) {
				this.handleDeleteScript( e );
			}
		} );

		// Listen for category change in the modal to update services
		document.addEventListener('change', (e) => {
			if (e.target && e.target.name === 'script_category') {
				this.updateServicesSelect(e.target.value);
			}
		});
	}

	handleAddScript( e ) {
		const defaultData = {
			script_category: '',
			script_service: '',
			script_type: 'script',
			script_tag: '',
			script_blocked_elements: '',
			action: 'wpconsent_add_script',
			script_id: '',
		};
		this.modal.open({
			title: 'Add New Script or iFrame',
			data: defaultData,
			successCallback: (response) => {
				if (response.success && response.data) {
					this.addScriptToList(response.data);
				}
				this.modal.close();
			},
			errorCallback: (response) => {
				alert('Failed to add script.');
			}
		});
		// Populate services dropdown for default (empty) category
		const serviceSelect = document.getElementById('script_service');
		if (serviceSelect) {
			serviceSelect.disabled = true;
		}
		this.updateServicesSelect('');
	}

	handleEditScript( e ) {
		const button = e.target.closest('.wpconsent-edit-script');
		const scriptItem = button.closest('.wpconsent-cookie-item');
		if (!scriptItem) return;

		// Get all data from data attributes
		const script_id = scriptItem.getAttribute('data-id');
		const script_category = scriptItem.getAttribute('data-category');
		const script_service = scriptItem.getAttribute('data-service');
		const script_type = scriptItem.getAttribute('data-type');
		const script_tag = scriptItem.getAttribute('data-script');
		const script_blocked_elements = scriptItem.getAttribute('data-blocked-elements');

		const data = {
			script_id,
			script_category,
			script_service,
			script_type,
			script_tag,
			script_blocked_elements,
			action: 'wpconsent_edit_script',
			_wpnonce: document.querySelector('#wpconsent_manage_script_nonce').value
		};

		this.modal.open({
			title: 'Edit Script or iFrame',
			data,
			successCallback: (response) => {
				if (response.success && response.data) {
					// Update the existing item
					this.updateScriptInList(response.data, scriptItem);
				}
				this.modal.close();
			},
			errorCallback: (response) => {
				alert('Failed to update script.');
			}
		});

		// Set category select immediately
		const categorySelect = this.modal.form.querySelector('[name="script_category"]');
		if (categorySelect) {
			categorySelect.value = script_category;
		}

		// Set type radio after modal opens
		const typeRadios = this.modal.form.querySelectorAll('input[name="script_type"]');
		if (typeRadios) {
			typeRadios.forEach(radio => {
				radio.checked = (radio.value === script_type);
				// Trigger change event to activate dynamic hide logic
				if (radio.checked) {
					jQuery(radio).trigger('change');
				}
			});
		}

		// Set field values
		this.modal.form.querySelector('[name="script_tag"]').value = script_tag;
		this.modal.form.querySelector('[name="iframe_tag"]').value = script_tag;
		this.modal.form.querySelector('[name="script_keywords"]').value = script_blocked_elements;
		this.modal.form.querySelector('[name="iframe_blocked_elements"]').value = script_blocked_elements;

		// Populate services dropdown for the current category and select the current service
		this.updateServicesSelect(script_category, script_service).then(() => {
			// Set the service value after the dropdown is populated
			const serviceSelect = this.modal.form.querySelector('[name="script_service"]');
			if (serviceSelect) {
				serviceSelect.value = script_service;
			}
		});
	}

	handleDeleteScript( e ) {
		if ( !confirm( 'Are you sure you want to delete this script?' ) ) {
			return;
		}
		const button = e.target.closest('.wpconsent-delete-script');
		const scriptItem = button.closest('.wpconsent-cookie-item');
		if (!scriptItem) return;
		const script_id = scriptItem.getAttribute('data-id');

		const data = {
			action: 'wpconsent_delete_script',
			script_id: script_id,
			wpconsent_manage_script_nonce: document.querySelector('#wpconsent_manage_script_nonce').value
		};

		jQuery.post(ajaxurl, data, (response) => {
			if (response.success) {
				// Remove the DOM element with the script ID
				const toRemove = document.querySelector(`.wpconsent-cookie-item[data-id="${script_id}"]`);
				if (toRemove) toRemove.remove();
			} else {
				alert('Failed to delete script.');
			}
		});
	}

	addScriptToList(data) {
		const categoryItem = document.querySelector(`.wpconsent-accordion-item[data-category="${data.category}"]`);
		if (!categoryItem) return;
		const scriptsList = categoryItem.querySelector('.wpconsent-cookies-list');
		if (!scriptsList) return;

		// Get the service name from the service ID
		const serviceSelect = document.querySelector(`#script_service option[value="${data.service}"]`);
		const serviceName = serviceSelect ? serviceSelect.textContent : data.service;
		const typeLabel = data.type === 'iframe' ? 'iFrame' : 'Script';

		// Use the template for new script row
		const template = document.getElementById('wpconsent-new-script-row').innerHTML;
		let newRowHtml = template
			.replace(/{{id}}/g, data.id)
			.replace(/{{category}}/g, data.category)
			.replace(/{{service_id}}/g, data.service)
			.replace(/{{service_name}}/g, serviceName)
			.replace(/{{type}}/g, data.type)
			.replace(/{{type_label}}/g, typeLabel)
			.replace(/{{tag}}/g, data.tag)
			.replace(/{{blocked_elements}}/g, data.blocked_elements);

		// Insert after the header
		const header = scriptsList.querySelector('.wpconsent-cookie-header');
		if (header) {
			header.insertAdjacentHTML('afterend', newRowHtml);
		} else {
			scriptsList.insertAdjacentHTML('beforeend', newRowHtml);
		}

		// Accordion height fix
		const accordionContent = scriptsList.closest('.wpconsent-accordion-content');
		if (accordionContent) {
			accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
		}
	}

	updateScriptInList(data, scriptItem) {
		if (!scriptItem) return;

		// Get the service name from the service ID
		const serviceSelect = document.querySelector(`#script_service option[value="${data.service}"]`);
		const serviceName = serviceSelect ? serviceSelect.textContent : data.service;

		scriptItem.setAttribute('data-id', data.id);
		scriptItem.setAttribute('data-category', data.category);
		scriptItem.setAttribute('data-service', data.service);
		scriptItem.setAttribute('data-type', data.type);
		scriptItem.setAttribute('data-script', data.tag);
		scriptItem.setAttribute('data-blocked-elements', data.blocked_elements);
		
		const serviceElement = scriptItem.querySelector('.script-service');
		serviceElement.textContent = serviceName;
		serviceElement.setAttribute('data-service-id', data.service);
		scriptItem.querySelector('.script-type').textContent = data.type === 'iframe' ? 'iFrame' : 'Script';
		scriptItem.querySelector('.script-script').textContent = data.tag;
		scriptItem.querySelector('.script-blocked-elements').textContent = data.blocked_elements;
	}

	/**
	 * Fetch and populate the services dropdown in the script modal.
	 * @param {string} categoryId
	 * @param {string} selectedService
	 * @returns {Promise} A promise that resolves when the services are loaded
	 */
	updateServicesSelect(categoryId = '', selectedService = '') {
		const serviceSelect = document.getElementById('script_service');
		if (!serviceSelect) return Promise.resolve();

		if (!categoryId) {
			serviceSelect.innerHTML = '<option value="">Select a service</option>';
			serviceSelect.disabled = true; // Disable if no category
			return Promise.resolve();
		}

		serviceSelect.disabled = false; // Enable if category is chosen

		const data = new FormData();
		data.append('action', 'wpconsent_get_services');
		data.append('category_id', categoryId);
		if (window.wpconsent && window.wpconsent.nonce) {
			data.append('nonce', window.wpconsent.nonce);
		}

		return fetch(ajaxurl, {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		})
			.then(response => response.json())
			.then(response => {
				if (response.success) {
					serviceSelect.innerHTML = response.data;
					if (selectedService) {
						// If the option doesn't exist, add it
						if (!serviceSelect.querySelector(`option[value="${selectedService}"]`)) {
							const opt = document.createElement('option');
							opt.value = selectedService;
							opt.textContent = selectedService;
							serviceSelect.appendChild(opt);
						}
						serviceSelect.value = selectedService;
					}
				}
			})
			.catch(error => {
				console.error('Error updating services select:', error);
			});
	}
};

// Initialize when DOM is ready
document.addEventListener( 'DOMContentLoaded', () => {
	new WPConsentScriptManagement();
} ); 