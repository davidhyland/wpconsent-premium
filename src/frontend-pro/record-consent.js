/**
 * Handle consent logging via REST API
 *
 * @package WPConsent
 */

(function() {
    // Listen for consent saved event
    window.addEventListener('wpconsent_consent_saved', function(event) {
        if ( ! wpconsent.records_of_consent || '0' === wpconsent.records_of_consent ) {
            return;
        }

        // Get the REST API URL from the wp_localize_script data
        const apiUrl = wpconsent.api_url + '/log-consent';

        // Let's use the wp_localize_script data to get the nonce
        const nonce = wpconsent.nonce;
        const headers = {
            'Content-Type': 'application/json',
        };
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }

        fetch(apiUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                consent_data: JSON.stringify(event.detail),
            }),
            credentials: 'same-origin',
            keepalive: true // This ensures the request continues even if the page unloads
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .catch(function(error) {
            console.error('Error logging consent:', error);
        });
    });
})();
