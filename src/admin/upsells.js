jQuery( function ( $ ) {
	jQuery( '#wpconsent-records-of-consent-lite' ).on( 'change', function () {
		jQuery( this ).prop( 'checked', false );

		WPConsentAdminNotices.show_pro_notice( wpconsent.records_of_consent.title, wpconsent.records_of_consent.text, wpconsent.records_of_consent.url );
	} );

	jQuery( '#wpconsent-auto-scanner-lite' ).on( 'change', function () {
		jQuery( this ).prop( 'checked', false );

		wpconsent_show_scanner_notice();
	} );

	jQuery( '[for="wpconsent-auto-scanner-lite"], [for="wpconsent-auto-scanner-interval-lite"], #wpconsent-auto-scanner-interval-lite' ).on( 'click', function ( e ) {
		e.preventDefault();
		wpconsent_show_scanner_notice();
	} );

	function wpconsent_show_scanner_notice() {
		WPConsentAdminNotices.show_pro_notice( wpconsent.scanner.title, wpconsent.scanner.text, wpconsent.scanner.url );
	}

	jQuery( '#wpconsent-export-custom-scripts-lite' ).on( 'change', function () {
		jQuery( this ).prop( 'checked', false );

		WPConsentAdminNotices.show_pro_notice( wpconsent.custom_scripts_export.title, wpconsent.custom_scripts_export.text, wpconsent.custom_scripts_export.url );
	} );

 $( '.wpconsent-languages-button-lite' ).on( 'click', function () {
 	WPConsentAdminNotices.show_pro_notice( wpconsent.languages_upsell.title, wpconsent.languages_upsell.text, wpconsent.languages_upsell.url );
 } );

 $( '.wpconsent-add-service-from-library-lite' ).on( 'click', function () {
 	WPConsentAdminNotices.show_pro_notice( wpconsent.service_library_upsell.title, wpconsent.service_library_upsell.text, wpconsent.service_library_upsell.url );
 } );
} );
