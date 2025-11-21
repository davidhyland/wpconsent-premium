/**
 * Service Library functionality for WPConsent.
 */

(function($) {
    'use strict';

    const ServiceLibrary = {
        /**
         * Initialize the service library functionality.
         */
        init: function() {
            this.modal = document.getElementById('wpconsent-modal-add-service-from-library');
            this.servicesLoaded = false;
            this.servicesData = null;
            this.categories = null;
            this.bindEvents();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Open modal when clicking the "Add Service From Library" button
            $(document).on('click', '.wpconsent-add-service-from-library', this.openModal.bind(this));

            // Handle search input
            $(document).on('input', '#wpconsent-service-library-search', this.handleSearch.bind(this));

            // Handle service selection
            $(document).on('click', '.wpconsent-service-library-item', this.handleServiceSelection.bind(this));

            // Handle modal close button
            $(document).on('click', '#wpconsent-modal-add-service-from-library .wpconsent-modal-close', this.closeModal.bind(this));
            $(document).on('click', '#wpconsent-modal-add-service-from-library .wpconsent-button-secondary', this.closeModal.bind(this));
        },

        /**
         * Open the service library modal.
         *
         * @param {Event} e The click event.
         */
        openModal: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const categoryId = $button.data('category-id');
            const categoryName = $button.data('category-name');

            this.currentCategory = {
                id: categoryId,
                name: categoryName
            };

            $('#wpconsent-modal-add-service-from-library input[name="category_id"]').val(categoryId);

            this.modal.classList.add('active');

            // Only load services if not already loaded
            if (!this.servicesLoaded) {
                this.loadServices();
            } else {
                this.renderServices(this.servicesData);
            }
        },

        /**
         * Update category names in the service library items.
         */
        updateCategoryNames: function() {
            if (!this.categories) {
                return;
            }

            $('.category-slug').each((index, element) => {
                const $element = $(element);
                const slug = $element.data('category-slug');

                if (slug && this.categories[slug]) {
                    $element.text(this.categories[slug].name);
                }
            });
        },

        /**
         * Close the modal.
         */
        closeModal: function() {
            this.modal.classList.remove('active');
        },

        /**
         * Load services from the library.
         */
        loadServices: function() {
            const $container = $('.wpconsent-service-library-items');
            const $loading = $('.wpconsent-service-library-loading');

            // Show loading spinner
            WPConsentConfirm.show_please_wait();

            $loading.show();
            $container.empty();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpconsent_get_services_library',
                    nonce: wpconsent.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        this.servicesLoaded = true;

                        // Handle the new response format with services and categories
                        if (response.data.services) {
                            this.servicesData = response.data.services;
                            this.renderServices(response.data.services);
                        } else {
                            // Fallback for backward compatibility
                            this.servicesData = response.data;
                            this.renderServices(response.data);
                        }

                        // Set categories if available
                        if (response.data.categories) {
                            this.categories = response.data.categories;
                            this.updateCategoryNames();
                        }
                    } else {
                        this.showError('Failed to load services');
                    }
                }.bind(this),
                error: function() {
                    this.showError('Failed to load services');
                }.bind(this),
                complete: function() {
                    $loading.hide();
                    WPConsentConfirm.close();
                }
            });
        },

        /**
         * Render the services in the modal.
         *
         * @param {Array} services The services to render.
         */
        renderServices: function(services) {
            const $container = $('.wpconsent-service-library-items');
            $container.empty();

            // Convert object to array of { id, ...service }
            const serviceArray = Object.entries(services).map(([id, data]) => ({
                id,
                name: data.label,
                description: data.description || '',
                added: data.added || false,
                term_id: data.term_id || 0,
                logo: data.logo || '',
                category: data.category || ''
            }));

            if (!serviceArray.length) {
                $container.html('<div class="wpconsent-service-library-empty">No services found</div>');
                return;
            }

            serviceArray.forEach(function(service) {
                const $item = $('<div>', {
                    class: 'wpconsent-service-library-item' + (service.added ? ' already-added' : ''),
                    'data-service-id': service.id,
                    'data-term-id': service.term_id
                });

                // Construct logo URL, use fallback.png if logo is empty
                const logoUrl = 'https://cdn.wpconsent.com/services/' + (service.logo ? service.logo : 'fallback.png');

                $item.html(`
                    <div class="service-logo">
                        <img src="${logoUrl}" alt="${service.name} logo" />
                    </div>
                    <div class="service-info">
                        <div class="service-name">
                            ${service.name}
                            ${service.added ? '<span class="service-added-indicator" title="Already added to your website">✓</span>' : ''}
                        </div>
                        <div class="service-description">${service.description}</div>
                        ${service.category ? `<div class="service-suggested-category">Suggested category: <span class="category-slug" data-category-slug="${service.category}">${service.category}</span></div>` : ''}
                    </div>
                `);

                $container.append($item);
            });

            // Update category names if categories are loaded
            if (this.categories) {
                this.updateCategoryNames();
            }
        },

        /**
         * Handle search input.
         *
         * @param {Event} e The input event.
         */
        handleSearch: function(e) {
            const searchTerm = $(e.currentTarget).val().toLowerCase();
            const $items = $('.wpconsent-service-library-item');

            $items.each(function() {
                const $item = $(this);
                const name = $item.find('.service-info .service-name').text().toLowerCase();
                const description = $item.find('.service-info .service-description').text().toLowerCase();

                if (name.includes(searchTerm) || description.includes(searchTerm)) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        },

        /**
         * Handle service selection.
         *
         * @param {Event} e The click event.
         */
        handleServiceSelection: function(e) {
            const $item = $(e.currentTarget);
            const serviceId = $item.data('service-id');
            const categoryId = $('#wpconsent-modal-add-service-from-library input[name="category_id"]').val();

            // Show loading state
            $item.addClass('loading');

            // Show loading spinner
            WPConsentConfirm.show_please_wait();

            // AJAX call to import the service
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpconsent_import_service_from_library',
                    nonce: wpconsent.nonce,
                    service_key: serviceId,
                    category_id: categoryId
                },
                success: function(response) {
                    if (response.success) {
                        this.closeModal();
                        // Close loading spinner
                        WPConsentConfirm.close();
                        // Dispatch a custom event to update the UI
                        const serviceData = {
                            name: response.data.name,
                            description: response.data.description || '',
                            cookie_id: response.data.id,
                            id: response.data.id, // Add id property to match what's expected in service-management.js
                            service_url: response.data.service_url || '',
                            category_id: $('#wpconsent-modal-add-service-from-library input[name="category_id"]').val(),
                            cookies: response.data.cookies || []
                        };
                        document.dispatchEvent(new CustomEvent('wpconsent:service-added', { detail: serviceData }));
                    } else {
                        $item.removeClass('loading');
                        // Close loading spinner
                        WPConsentConfirm.close();
                        alert(response.data && response.data.message ? response.data.message : 'Failed to import service.');
                    }
                }.bind(this),
                error: function() {
                    $item.removeClass('loading');
                    // Close loading spinner
                    WPConsentConfirm.close();
                    alert('Failed to import service.');
                }
            });
        },

        /**
         * Show an error message.
         *
         * @param {string} message The error message to show.
         */
        showError: function(message) {
            const $container = $('.wpconsent-service-library-items');
            $container.html(`<div class="wpconsent-service-library-error">${message}</div>`);
        }
    };

    // Initialize when document is ready
    $(function() {
        ServiceLibrary.init();
    });

})(jQuery);
