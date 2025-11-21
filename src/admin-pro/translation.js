window.WPConsentTranslationConfirm = window.WPConsentTranslationConfirm || (
    function (document, window, $) {
        const app = {
            strings: {
                warning_title: wpconsent.translation_title || 'Start Translation Process',
                warning_message: wpconsent.translation_message || 'This will automatically translate all your consent banner content into the selected language. This process runs in the background and may take several minutes.',
                translation_button: wpconsent.translation_button || 'Continue Translation',
                cancel_button: wpconsent.cancel_button || 'Cancel',
                blocked_title: wpconsent.translation_block_title || 'Translation In Progress',
                blocked_message: wpconsent.translation_block_message || 'A translation is currently running in the background. Please wait for it to complete before starting a new translation.',
                ok_button: 'OK'
            },

            init() {
                this.bindEvents();
                this.initProgressTracking();
            },

            bindEvents() {
                $(document).on('click', '.wpconsent-translate-language', (e) => {
                    e.preventDefault();
                    const $button = $(e.currentTarget);
                    const locale = $button.data('locale');
                    this.showConfirmDialog(locale);
                });

                $(document).on('click', '.wpconsent-reset-translation', (e) => {
                    e.preventDefault();
                    this.showResetConfirmDialog();
                });
            },

            showConfirmDialog(locale) {
                const availableLanguages = this.getAvailableLanguages();
                const languageName = availableLanguages[locale]?.english_name || locale;
                
                // Check license level first - if no license_level data, assume lite
                const licenseCan = window.wpconsent?.license_can || false;
                if (!licenseCan) {
                    this.showUpsellDialog();
                    return;
                }
                
                // Check translation status from initial page load data
                if (window.wpconsent?.translation_active) {
                    this.showTranslationBlockedDialog();
                    return;
                }
                
                $.confirm({
                    title: this.strings.warning_title,
                    content: `
                        <div class="wpconsent-translation-warning">
                            <p><strong>Target Language:</strong> ${languageName} (${locale})</p>
                            <p>${this.strings.warning_message}</p>
                        </div>
                    `,
                    boxWidth: '600px',
                    theme: 'modern',
                    type: 'blue',
                    buttons: {
                        translate: {
                            text: this.strings.translation_button,
                            btnClass: 'btn-confirm',
                            action: () => {
                                this.startTranslation(locale, languageName);
                            }
                        },
                        cancel: {
                            text: this.strings.cancel_button,
                            btnClass: ''
                        }
                    }
                });
            },

            showTranslationBlockedDialog() {
                $.confirm({
                    title: this.strings.blocked_title,
                    content: `
                        <div class="wpconsent-translation-blocked">
                            <p>${this.strings.blocked_message}</p>
                        </div>
                    `,
                    boxWidth: '600px',
                    theme: 'modern',
                    type: 'orange',
                    buttons: {
                        ok: {
                            text: this.strings.ok_button,
                            btnClass: 'btn-confirm'
                        }
                    }
                });
            },

            showResetConfirmDialog() {
                $.confirm({
                    title: window.wpconsent?.translation_cancel_title || 'Cancel Translation?',
                    content: `
                        <div class="wpconsent-translation-reset">
                            <p>${window.wpconsent?.translation_cancel_message || 'Are you sure you want to cancel the current translation process? This will reset the translation status and allow you to start a new translation.'}</p>
                        </div>
                    `,
                    boxWidth: '600px',
                    theme: 'modern',
                    type: 'red',
                    buttons: {
                        reset: {
                            text: window.wpconsent?.translation_cancel_confirm || 'Yes, Cancel Translation',
                            btnClass: 'btn-red',
                            action: () => {
                                this.resetTranslation();
                            }
                        },
                        cancel: {
                            text: window.wpconsent?.translation_cancel_keep || 'No, Keep Running',
                            btnClass: 'btn-default'
                        }
                    }
                });
            },

            showUpsellDialog() {
                // Use the same upsell system as other features
                if (typeof WPConsentAdminNotices !== 'undefined' && window.wpconsent?.translation_upsell) {
                    WPConsentAdminNotices.show_pro_notice(
                        window.wpconsent.translation_upsell.title,
                        window.wpconsent.translation_upsell.text,
                        window.wpconsent.translation_upsell.url,
                        window.wpconsent.translation_upsell.button_text
                    );
                }
            },

            async startTranslation(locale, languageName) {
                const formData = new FormData();
                formData.append('action', 'wpconsent_start_translation');
                formData.append('target_locale', locale);
                formData.append('language_name', languageName);
                formData.append('nonce', window.wpconsent?.nonce || '');

                try {
                    const response = await fetch(window.ajaxurl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.data?.message || 'Failed to start translation process');
                    }

                    // Store translation info for potential failure messages
                    this.lastProgressData = {
                        target_locale: locale,
                        language_name: languageName
                    };

                    // Reload page immediately to show progress notice
                    window.location.reload();
                } catch (error) {
                    console.error('Error starting translation:', error);
                    this.showNotification('Failed to start translation: ' + error.message, 'error');
                }
            },

            async resetTranslation() {
                const formData = new FormData();
                formData.append('action', 'wpconsent_reset_translation');
                formData.append('nonce', window.wpconsent?.nonce || '');

                try {
                    const response = await fetch(window.ajaxurl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.data?.message || 'Failed to reset translation');
                    }

                    // Stop polling
                    this.stopProgressPolling();

                    // Show success message and reload
                    const successMessage = window.wpconsent?.translation_cancelled_success || 'Translation has been cancelled. You can now start a new translation.';
                    this.showNotification(successMessage, 'success');

                    // Reload page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } catch (error) {
                    console.error('Error resetting translation:', error);
                    const errorPrefix = window.wpconsent?.translation_reset_error_prefix || 'Failed to reset translation: ';
                    this.showNotification(errorPrefix + error.message, 'error');
                }
            },

            showNotification(message, type = 'info') {
                const $notice = $(`
                    <div class="notice notice-${type} is-dismissible wpconsent-translation-notice">
                        <p>${message}</p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>
                `);

                $('.wrap h1').after($notice);

                if (type === 'success') {
                    setTimeout(() => {
                        $notice.fadeOut(() => $notice.remove());
                    }, 10000);
                }

                $notice.on('click', '.notice-dismiss', () => {
                    $notice.fadeOut(() => $notice.remove());
                });
            },

            initProgressTracking() {
                // Check if translation progress notice exists on page load
                if ($('.wpconsent-translation-progress-notice').length) {
                    // Make an immediate call to get current progress data including language info
                    this.checkTranslationProgress();
                    this.startProgressPolling();
                }
            },

            startProgressPolling() {
                // Poll every 10 seconds to update progress
                this.progressInterval = setInterval(() => {
                    this.checkTranslationProgress();
                }, 10000);
            },

            stopProgressPolling() {
                if (this.progressInterval) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }
            },

            checkTranslationProgress() {
                $.post(window.ajaxurl, {
                    action: 'wpconsent_check_translation_progress',
                    nonce: window.wpconsent?.nonce || ''
                }, (response) => {
                    if (response.success) {
                        if (!response.data.translation_active) {
                            // Translation finished - check success status
                            if (response.data.progress && response.data.progress.success === true) {
                                this.showCompletionNotice();
                            } else if (response.data.progress && response.data.progress.success === false) {
                                this.showFailureNotice();
                            }
                            this.stopProgressPolling();
                            window.wpconsent.translation_active = false;
                        } else if (response.data.progress) {
                            // Store progress data for completion message
                            this.lastProgressData = response.data.progress;
                            this.updateProgressText(response.data.progress);
                        }
                    }
                }).fail(() => {
                    // Stop polling on error
                    this.stopProgressPolling();
                });
            },

            updateProgressText(progress) {
                const percent = progress.progress_percent || 0;
                
                // Update the status text with just percentage
                $('#wpconsent-translation-status').text(`(${percent}% complete)`);
            },

            showCompletionNotice() {
                // Replace the progress notice with a completion notice
                const $progressNotice = $('.wpconsent-translation-progress-notice');
                if ($progressNotice.length && this.lastProgressData) {
                    // Use the localized completion message template
                    const languageName = this.lastProgressData.language_name || this.lastProgressData.target_locale || 'the target language';
                    let completionMessage = '';

                    if (window.wpconsent && window.wpconsent.translation_complete) {
                        // Use the properly localized message from PHP
                        completionMessage = window.wpconsent.translation_complete.replace('%LANGUAGE_NAME%', languageName);
                    } else {
                        // Fallback message (should not happen in normal operation)
                        completionMessage = `<strong>Translation Complete!</strong> Your WPConsent content has been successfully translated to <strong>${languageName}</strong>. Please <a href="wp-admin/admin.php?page=wpconsent-cookies&view=languages">review the translation</a> for accuracy.`;
                    }

                    $progressNotice.removeClass('notice-info')
                                   .addClass('notice-success')
                                   .html('<p>' + completionMessage + '</p>');
                }
            },

            showFailureNotice() {
                // Replace the progress notice with a failure notice
                const $progressNotice = $('.wpconsent-translation-progress-notice');
                if ($progressNotice.length) {
                    // Use the localized failure message template
                    const languageName = this.lastProgressData?.language_name || this.lastProgressData?.target_locale || 'the target language';
                    let failureMessage = '';

                    if (window.wpconsent && window.wpconsent.translation_failed) {
                        // Use the properly localized message from PHP
                        failureMessage = window.wpconsent.translation_failed.replace('%LANGUAGE_NAME%', languageName);
                    } else {
                        // Fallback message (should not happen in normal operation)
                        failureMessage = `<strong>Translation Failed</strong> - The translation to <strong>${languageName}</strong> could not be completed. You can manually <a href="wp-admin/admin.php?page=wpconsent-cookies&view=languages">manage your languages</a> to add content.`;
                    }

                    $progressNotice.removeClass('notice-info')
                                   .addClass('notice-error')
                                   .html('<p>' + failureMessage + '</p>');
                }
            },

            getAvailableLanguages() {
                const languages = {};
                $('.wpconsent-language-item').each(function() {
                    const $item = $(this);
                    const locale = $item.data('locale') || $item.find('input').val();

                    const $labelText = $item.find('.wpconsent-checkbox-text').clone();
                    $labelText.find('.wpconsent-language-locale, .wpconsent-language-native-name, .wpconsent-language-default-badge').remove();
                    const englishName = ($labelText.text() || '').trim();

                    if (locale) {
                        languages[locale] = {
                            english_name: englishName || locale,
                            enabled: $item.find('input').is(':checked'),
                        };
                    }
                });
                return languages;
            }
        };

        // Initialize when document is ready
        $(document).ready(() => {
            app.init();
        });

        return app;
    }(document, window, jQuery)
);
