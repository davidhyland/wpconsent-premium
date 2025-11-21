import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

(function($) {
    'use strict';
    
    const ConsentLogs = {
        init() {
            this.setupDateRangePicker();
        },

        setupDateRangePicker() {
            // Initialize flatpickr as date range picker
            flatpickr('#filter-by-date-range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                allowInput: true,
                disableMobile: false,
                locale: {
                    rangeSeparator: ' to '
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(() => ConsentLogs.init());
})(jQuery);