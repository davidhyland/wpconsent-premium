import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

(function($) {
    'use strict';
    
    const ConsentExport = {
        init() {
            this.button = $('#wpconsent-export');
            this.dateFrom = $('#export-date-from');
            this.dateTo = $('#export-date-to');
            
            this.initializeFlatpickr();
            this.bindEvents();
        },

        initializeFlatpickr() {
            const dateConfig = {
                enableTime: false,
                dateFormat: "Y-m-d",
                allowInput: true
            };

            this.fromCalendar = flatpickr('#export-date-from', {
                ...dateConfig,
                onChange: (selectedDates) => {
                    this.toCalendar.set('minDate', selectedDates[0]);
                }
            });

            this.toCalendar = flatpickr('#export-date-to', {
                ...dateConfig,
                onChange: (selectedDates) => {
                    this.fromCalendar.set('maxDate', selectedDates[0]);
                }
            });
        },

        bindEvents() {
            this.button.on('click', (e) => {
                e.preventDefault();
                if (this.validateForm()) {
                    this.Ajax();
                }
            });
        },
        
        Ajax() {
            this.showProgress();
            const data = {
                action: 'wpconsent_export_start',
                nonce: $('#wpconsent_export_nonce').val(),
                date_from: this.dateFrom.val(),
                date_to: this.dateTo.val(),
            };
        
            // Make the AJAX call
            $.post(ajaxurl, data)
                .done(response => {
                    if (response.success) {
                        this.updateProgress(0, response.data.total_records);
                        this.processBatch(response.data.request_id, 1);
                    } else {
                        this.hideProgress();
                    }
                })
                .fail(error => {
                    this.hideProgress();
                });
        },

        processBatch(requestId, batchNumber, lastId = 0) {
            const data = {
                action: 'wpconsent_export_batch',
                nonce: $('#wpconsent_export_nonce').val(),
                request_id: requestId,
                batch: batchNumber,
                last_id: lastId
            };
        
            return $.post(ajaxurl, data)
                .done(response => {
                    if (!response.success) {
                        const error = new Error(response.data.message || 'Export failed');
                        error.code = response.data.code;
                        throw error;
                    }
        
                    const { total_processed, total_records, last_id, is_last, status } = response.data;
                    this.updateProgress(total_processed, total_records);
        
                    if (status === 'failed') {
                        throw new Error('Export process failed');
                    }
        
                    if (!is_last) {
                        return this.processBatch(requestId, batchNumber + 1, last_id);
                    }
        
                    this.hideProgress();
                    this.downloadFile(requestId);
                })
                .fail(error => {
                    this.hideProgress();
                    alert('Export failed: ' + (error.responseJSON?.data?.message || error.statusText));
                });
        },

        showProgress() {
            this.button.prop('disabled', true);
            $('#wpconsent-export-progress').show();
        },
    
        hideProgress() {
            this.button.prop('disabled', false);
            $('#wpconsent-export-progress').hide();
        },
    
        updateProgress(processed, total) {
            // Ensure we have valid numbers
            processed = parseInt(processed, 10) || 0;
            total = parseInt(total, 10) || 1; // prevent division by zero
            
            const percentage = Math.round((processed / total) * 100);
            
            // Validate percentage is a number
            if (isNaN(percentage)) {
                console.error('Invalid progress values:', { processed, total });
                return;
            }
            
            $('.wpconsent-progress-bar-inner').css('width', percentage + '%');
            $('.wpconsent-progress-percentage').text(percentage + '%');
        },

        downloadFile(requestId) {
            const params = new URLSearchParams({
                action: 'wpconsent_export_download',
                nonce: $('#wpconsent_export_nonce').val(),
                request_id: requestId
            });
        
            window.location.href = `${ajaxurl}?${params.toString()}`;
        },

        validateForm() {
            const dateFrom = this.dateFrom.val();
            const dateTo = this.dateTo.val();

            if (!dateFrom || !dateTo) {
                return false;
            }

            if (new Date(dateFrom) > new Date(dateTo)) {
                return false;
            }

            return true;
        }
    };

    $(document).ready(() => ConsentExport.init());
})(jQuery);