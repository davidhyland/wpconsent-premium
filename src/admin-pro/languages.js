jQuery( document ).ready( function ( $ ) {
	var $search = $( '#wpconsent-language-search' );
	var $list = $( '#wpconsent-language-list' );
	var $items = $list.find( '.wpconsent-language-item' );
	var $sections = $list.find( '.wpconsent-language-section' );

	$search.on( 'input', function () {
		var searchTerm = $( this ).val().toLowerCase();
		var hasVisibleItems = false;

		$items.each( function () {
			var $item = $( this );
			var searchText = $item.data( 'search' );

			if ( searchText.indexOf( searchTerm ) !== - 1 ) {
				$item.show();
				hasVisibleItems = true;
			} else {
				$item.hide();
			}
		} );

		// Show/hide section titles based on whether they have visible items
		$sections.each( function () {
			var $section = $( this );
			var hasVisibleItemsInSection = $section.find( '.wpconsent-language-item:visible' ).length > 0;
			$section.find( '.wpconsent-language-section-title' ).toggle( hasVisibleItemsInSection );
		} );
	} );
} );