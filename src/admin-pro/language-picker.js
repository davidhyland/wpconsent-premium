(function($) {
    'use strict';

    const WPConsentLanguagePicker = {
        init() {
            this.$languageButton = $('#wpconsent-languages-button');
            this.$languageDropdown = $('.wpconsent-language-picker-dropdown');
            this.$languageList = $('.wpconsent-language-picker-list');

            this.bindEvents();
        },

        bindEvents() {
            // Toggle dropdown when clicking the language button
            this.$languageButton.on('click', (e) => {
                e.preventDefault();
                this.$languageDropdown.toggleClass('active');
            });

            // Close dropdown when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.wpconsent-language-picker-container').length) {
                    this.$languageDropdown.removeClass('active');
                }
            });

            // Handle language selection
            $(document).on('click', '.wpconsent-language-picker-item', (e) => {
                e.preventDefault();
                const $item = $(e.currentTarget);
                const language = $item.data('language');

                this.switchLanguage(language);
            });
        },

        switchLanguage(language, options = {}) {
            // Default options
            const defaults = {
                $button: this.$languageButton,
                redirectUrl: null,
                target: null
            };
            const settings = Object.assign({}, defaults, options);

            // Show loading state
            if (settings.$button) {
                settings.$button.prop('disabled', true);
            }

            // Show a loading modal until the request is completed.
            WPConsentConfirm.show_please_wait();

            // Send AJAX request to switch language
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpconsent_switch_language',
                    nonce: wpconsent.nonce,
                    language: language
                },
                success: (response) => {
                    if (response.success) {
                        // Handle different redirect behaviors
                        if (settings.redirectUrl) {
                            if (settings.target === '_blank') {
                                window.open(settings.redirectUrl, '_blank');
                                WPConsentConfirm.close();
                            } else {
                                window.location.href = settings.redirectUrl;
                            }
                        } else {
                            // Default behavior: reload page
                            window.location.reload();
                        }
                    } else {
                        WPConsentConfirm.close();
                        // Show error message
                        alert(response.data.message || 'Failed to switch language');
                    }
                },
                error: () => {
                    WPConsentConfirm.close();
                    alert('Failed to switch language. Please try again.');
                },
                complete: () => {
                    if (settings.$button) {
                        settings.$button.prop('disabled', false);
                    }
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(() => WPConsentLanguagePicker.init());

    // Expose globally for reuse (following WPConsent patterns)
    window.WPConsentLanguagePicker = WPConsentLanguagePicker;
})(jQuery);