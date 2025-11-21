jQuery( function ( $ ) {
 $( '#export-do-not-track-basic .wpconsent-button' ).on( 'click', function ( e ) {
 	e.preventDefault();
 	WPConsentAdminNotices.show_pro_notice( wpconsent.do_not_track_export.title, wpconsent.do_not_track_export.text, wpconsent.do_not_track_export.url );
 } );
} );
