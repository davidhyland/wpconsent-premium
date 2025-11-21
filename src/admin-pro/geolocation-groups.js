/**
 * Geolocation Groups JavaScript
 *
 * Handles hierarchical location selection and group management for the geolocation groups feature.
 */

(
	function ( $ ) {
		'use strict';

		// Store selected locations
		const selectedLocations = new Map();


		// Initialize the geolocation groups functionality
		function initGeolocationGroups() {
			initHierarchicalTree();
			initLocationSearch();
			initLocationTools();
			initLocationSelection();
			initGroupActions();
			initPredefinedRules();
			initModal();
		}


		// Initialize hierarchical tree
		function initHierarchicalTree() {
			// Handle tree toggle clicks
			$( '.wpconsent-tree-toggle' ).on( 'click', function () {
				toggleTreeItem( $( this ) );
			} );

			// Handle continent name clicks to expand/collapse
			$( '.wpconsent-tree-continent .wpconsent-location-name' ).on( 'click', function () {
				const $item = $( this ).closest( '.wpconsent-tree-item' );
				const $toggle = $item.find( '> .wpconsent-tree-item-header > .wpconsent-tree-toggle' );
				toggleTreeItem( $toggle );
			} );

			// Handle tree label clicks to expand/collapse
			$( '.wpconsent-tree-label' ).on( 'click', function ( e ) {
				// Don't trigger if clicking on the checkbox or the name (which has its own handler)
				if ( $( e.target ).is( 'input' ) || $( e.target ).is( '.wpconsent-location-name' ) || $( e.target ).is( '.wpconsent-checkbox-toggle' ) || $( e.target ).is( '.wpconsent-checkbox-toggle-slider' ) ) {
					return;
				}

				const $item = $( this ).closest( '.wpconsent-tree-item' );
				const $toggle = $item.find( '> .wpconsent-tree-item-header > .wpconsent-tree-toggle' );
				toggleTreeItem( $toggle );
			} );

		}

		// Helper function to toggle tree item expansion
		function toggleTreeItem( $toggle ) {
			const $item = $toggle.closest( '.wpconsent-tree-item' );
			const $children = $item.children( '.wpconsent-tree-children' );

			if ( $children.is( ':visible' ) ) {
				// Collapse
				$children.slideUp( 200 );
				$toggle.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
			} else {
				// Expand
				$children.slideDown( 200 );
				$toggle.removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
			}
		}

		// Select or deselect all countries in a continent
		function selectAllCountriesInContinent( continentCode, select ) {
			const $countries = $( `.wpconsent-tree-country[data-continent="${continentCode}"]` );

			$countries.each( function () {
				const $country = $( this );
				const $checkbox = $country.find( 'input[type="checkbox"]' );

				if ( !$checkbox.prop( 'disabled' ) ) {
					$checkbox.prop( 'checked', select ).trigger( 'change' );
				}
			} );
		}


		// Initialize location search
		function initLocationSearch() {
			$( '#wpconsent-location-search' ).on( 'input', function () {
				const searchTerm = $( this ).val().toLowerCase();

				if ( searchTerm === '' ) {
					// Show all items when search is empty
					$( '.wpconsent-tree-item' ).show();
					return;
				}

				// Hide all items first
				$( '.wpconsent-tree-item' ).hide();

				// Show items that match the search term and their parents
				$( '.wpconsent-tree-item' ).each( function () {
					const $item = $( this );
					const searchText = $item.data( 'search-text' );

					if ( searchText && searchText.includes( searchTerm ) ) {
						// Show this item
						$item.show();

						// Show all parent items
						let $parent = $item.parent().closest( '.wpconsent-tree-item' );
						while ( $parent.length ) {
							$parent.show();
							// Expand parent
							const $toggle = $parent.find( '> .wpconsent-tree-item-header > .wpconsent-tree-toggle' );
							$parent.find( '> .wpconsent-tree-children' ).show();
							$toggle.removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );

							$parent = $parent.parent().closest( '.wpconsent-tree-item' );
						}
					}
				} );
			} );
		}

		// Initialize location tools
		function initLocationTools() {
			// Expand All button
			$( '.wpconsent-expand-all' ).on( 'click', function () {
				$( '.wpconsent-tree-children' ).slideDown( 200 );
				$( '.wpconsent-tree-toggle' ).removeClass( 'dashicons-arrow-right' ).addClass( 'dashicons-arrow-down' );
			} );

			// Collapse All button
			$( '.wpconsent-collapse-all' ).on( 'click', function () {
				$( '.wpconsent-tree-children' ).slideUp( 200 );
				$( '.wpconsent-tree-toggle' ).removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-right' );
			} );

			// Clear All button
			$( '.wpconsent-clear-all' ).on( 'click', function () {
				// Uncheck all checkboxes that aren't disabled
				$( '.wpconsent-hierarchical-tree input[type="checkbox"]:not(:disabled)' ).prop( 'checked', false ).trigger( 'change' );


				// Clear selected locations
				selectedLocations.clear();
				updateSelectedLocationsDisplay();
			} );
		}

		// Initialize location selection
		function initLocationSelection() {
			// Handle checkbox changes
			$( '#wpconsent-mixed-location-selector' ).on( 'change', 'input[type="checkbox"]', function () {
				const $checkbox = $( this );
				const isChecked = $checkbox.prop( 'checked' );
				const locationType = $checkbox.data( 'type' );
				const locationCode = $checkbox.val();
				const locationName = $checkbox.data( 'name' );
				const locationKey = `${locationType}:${locationCode}`;

				// Handle continent selection (select/deselect all countries)
				if ( locationType === 'continent' && !$checkbox.prop( 'disabled' ) ) {
					selectAllCountriesInContinent( locationCode, isChecked );
				}

				// Handle country selection (select/deselect all states if it's US)
				if ( locationType === 'country' && locationCode === 'US' && !$checkbox.prop( 'disabled' ) ) {
					const $states = $( `.wpconsent-tree-state input[type="checkbox"]:not(:disabled)` );
					$states.prop( 'checked', isChecked ).trigger( 'change' );
				}

				if ( isChecked ) {
					// Add to selected locations
					const locationData = {
						type: locationType,
						code: locationCode
					};

					// Only include name for non-country locations to save space
					if ( locationType !== 'country' ) {
						locationData.name = locationName;
					}

					selectedLocations.set( locationKey, locationData );

				} else {
					// Remove from selected locations
					selectedLocations.delete( locationKey );
				}

				// Update the selected locations display
				updateSelectedLocationsDisplay();
			} );

			// Handle removing selected locations
			$( '#wpconsent-selected-locations-list' ).on( 'click', '.wpconsent-selected-location-remove', function () {
				const $item = $( this ).closest( '.wpconsent-selected-location' );
				const locationKey = $item.data( 'key' );
				const [type, code] = locationKey.split( ':' );

				// Uncheck the corresponding checkbox
				$( `input[data-type="${type}"][value="${code}"]` ).prop( 'checked', false ).trigger( 'change' );

				// Remove from selected locations
				selectedLocations.delete( locationKey );

				// Update the display
				updateSelectedLocationsDisplay();
			} );
		}

		// Update the selected locations display
		function updateSelectedLocationsDisplay() {
			const $container = $( '#wpconsent-selected-locations-list' );

			// Clear current content
			$container.empty();

			if ( selectedLocations.size === 0 ) {
				// Show "no selections" message
				$container.html( '<p class="wpconsent-no-selections">' + wpconsentI18n.noLocationsSelected + '</p>' );
				return;
			}

			// Add each selected location
			selectedLocations.forEach( ( location, key ) => {
				// Get the display name for the location
				let displayName = '';

				if ( location.name ) {
					// Use the stored name if available
					displayName = location.name;
				} else if ( location.type === 'country' ) {
					// For countries, get the name from the checkbox label
					const $checkbox = $( `input[data-type="${location.type}"][value="${location.code}"]` );
					if ( $checkbox.length ) {
						displayName = $checkbox.data( 'name' ) || location.code;
					} else {
						displayName = location.code;
					}
				} else {
					// Fallback to code if name is not available
					displayName = location.code;
				}

				$container.append( `
                <div class="wpconsent-selected-location" data-key="${key}">
                    <span class="wpconsent-selected-location-name">${displayName}</span>
                    <span class="wpconsent-selected-location-remove dashicons dashicons-no-alt"></span>
                </div>
            ` );
			} );
		}


		// Initialize group actions (edit/delete)
		function initGroupActions() {
			// Edit group
			$( '.wpconsent-location-groups-table' ).on( 'click', '.wpconsent-edit-group', function () {
				const $row = $( this ).closest( 'tr.wpconsent-location-group-item' );
				const groupId = $row.data( 'group-id' );
				// Load group data and populate form
				loadGroupData( groupId, $row );
			} );

			// Delete group
			$( '.wpconsent-location-groups-table' ).on( 'click', '.wpconsent-delete-group', function () {
				const $row = $( this ).closest( 'tr.wpconsent-location-group-item' );
				const groupId = $row.data( 'group-id' );
				$.confirm( {
					title: '',
					content: wpconsent.geolocationGroups.strings.confirmDelete,
					type: 'blue',
					icon: 'fa fa-exclamation-circle',
					animateFromElement: false,
					buttons: {
						confirm: {
							text: wpconsent.yes,
							btnClass: 'btn-confirm',
							keys: ['enter'],
							action: function () {
								deleteGroup( groupId, $row );
							}
						},
						cancel: {
							text: wpconsent.no,
							btnClass: 'btn-cancel',
							keys: ['esc'],
						},
					}
				} );
			} );
		}

		// Update loadGroupData to fetch group name and settings from table row
		function loadGroupData( groupId, $row ) {
			// Get group name from the Name column
			const groupName = $row.find( '.column-name strong' ).text();

			// First, open the modal
			const modal = document.getElementById( 'wpconsent-modal-location-group' );
			modal.classList.add( 'active' );

			// Update modal title to indicate editing
			$( '#wpconsent-modal-location-group .wpconsent-modal-header h2' ).text( wpconsentI18n.editLocationGroup );

			// Set form values
			$( '#wpconsent-group-id' ).val( groupId );
			$( '#group_name' ).val( groupName );
			// Consent mode
			const consentMode = $row.find( '.column-mode' ).text().toLowerCase().includes( 'optout' ) ? 'optout' : 'optin';
			$( '#consent_mode' ).val( consentMode );
			// Type of Consent (if you want to set it in the form, add logic here)
			// TODO: If you add a type_of_consent field to the form, set it here from .column-type
			// Settings checkboxes will be set from the AJAX response
			// Load selected locations via AJAX as before
			$.ajax( {
				url: wpconsent.geolocationGroups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpconsent_get_available_locations',
					nonce: wpconsent.geolocationGroups.nonce,
					group_id: groupId
				},
				success: function ( response ) {
					if ( response.success ) {
						// First clear and set the selected locations
						selectedLocations.clear();
						if ( response.data.group_settings.locations && response.data.group_settings.locations.length > 0 ) {
							response.data.group_settings.locations.forEach( location => {
								const locationKey = `${location.type}:${location.code}`;
								const locationData = {
									type: location.type,
									code: location.code
								};

								// Only include name for non-country locations to save space
								if ( location.type !== 'country' && location.name ) {
									locationData.name = location.name;
								}

								selectedLocations.set( locationKey, locationData );
							} );
						}

						// Then update available locations, which will use selectedLocations to determine which to disable
						updateAvailableLocations( response.data.used_locations );

						// Now set the checkboxes for selected locations
						selectedLocations.forEach( ( location, key ) => {
							const $checkbox = $( `input[data-type="${location.type}"][value="${location.code}"]` );
							if ( $checkbox.length ) {
								$checkbox.prop( 'checked', true );
							}
						} );

						// Set settings checkboxes based on group settings from the response
						if ( response.data.group_settings ) {
							$( '#enable_script_blocking' ).prop( 'checked', response.data.group_settings.enable_script_blocking );
							$( '#show_banner' ).prop( 'checked', response.data.group_settings.show_banner );
							$( '#enable_consent_floating' ).prop( 'checked', response.data.group_settings.enable_consent_floating );
							$( '#manual_toggle_services' ).prop( 'checked', response.data.group_settings.manual_toggle_services );
						} else {
							// Fallback to defaults if no settings are available
							$( '#enable_script_blocking' ).prop( 'checked', false );
							$( '#show_banner' ).prop( 'checked', true );
							$( '#enable_consent_floating' ).prop( 'checked', true );
							$( '#manual_toggle_services' ).prop( 'checked', false );
						}

						updateSelectedLocationsDisplay();
						$( '.wpconsent-cancel-edit' ).show();
						$( '.wpconsent-save-group' ).text( wpconsentI18n.updateGroup );
					}
				}
			} );
		}

		// Update available locations based on used locations
		function updateAvailableLocations( usedLocations ) {
			// Reset all checkboxes and remove "used" class
			$( '.wpconsent-location-item, .wpconsent-tree-item' ).removeClass( 'wpconsent-location-used' );
			$( 'input[type="checkbox"][data-type]' ).prop( 'disabled', false );
			$( '.wpconsent-location-used-indicator' ).remove();

			// Mark used locations
			for ( const key in usedLocations ) {
				const [type, code] = key.split( ':' );
				const $checkbox = $( `input[data-type="${type}"][value="${code}"]` );

				// Check if this location is already selected in the current group
				const locationKey = `${type}:${code}`;
				const isSelectedInCurrentGroup = selectedLocations.has( locationKey );

				// Only disable if not selected in current group
				if ( !isSelectedInCurrentGroup ) {
					// Handle both flat list and hierarchical tree
					const $item = $checkbox.closest( '.wpconsent-location-item, .wpconsent-tree-item' );
					if ( $item.length ) {
						$item.addClass( 'wpconsent-location-used' );
						$checkbox.prop( 'disabled', true );

						// Add the used indicator to the appropriate container
						if ( $item.hasClass( 'wpconsent-location-item' ) ) {
							$item.append( '<span class="wpconsent-location-used-indicator">' + wpconsentI18n.used + '</span>' );
						} else {
							// For tree items, add to the label container
							$item.find( '.wpconsent-tree-label' ).append( '<span class="wpconsent-location-used-indicator">' + wpconsentI18n.used + '</span>' );
						}
					}
				}
			}
		}

		// Update deleteGroup to remove the table row
		function deleteGroup( groupId, $row ) {
			$.ajax( {
				url: wpconsent.geolocationGroups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpconsent_delete_location_group',
					nonce: wpconsent.geolocationGroups.nonce,
					group_id: groupId
				},
				beforeSend: function () {
				},
				success: function ( response ) {
					if ( response.success ) {
						$row.fadeOut( 300, function () {
							$( this ).remove();
							if ( $( '.wpconsent-location-group-item' ).length === 0 ) {
								$( '.wpconsent-location-groups-table' ).replaceWith(
									'<p class="wpconsent-empty-state">' + wpconsentI18n.noLocationGroups + '</p>'
								);
							}
						} );
					} else {
						$.alert( {
							title: '',
							content: response.data.message || wpconsent.geolocationGroups.strings.errorDeleting,
							type: 'red',
							icon: 'fa fa-exclamation-circle',
							animateFromElement: false,
							buttons: {
								confirm: {
									text: wpconsent.ok,
									btnClass: 'btn-confirm',
									keys: ['enter'],
								},
							}
						} );
					}
				},
				error: function () {
					$.alert( {
						title: '',
						content: wpconsent.geolocationGroups.strings.errorDeleting,
						type: 'red',
						icon: 'fa fa-exclamation-circle',
						animateFromElement: false,
						buttons: {
							confirm: {
								text: wpconsent.ok,
								btnClass: 'btn-confirm',
								keys: ['enter'],
							},
						}
					} );
				}
			} );
		}

		// Reset the form to its initial state
		function resetForm() {
			// Clear form fields
			$( '#wpconsent-group-id' ).val( '' );
			$( '#group_name' ).val( '' );
			$( '#enable_script_blocking' ).prop( 'checked', false );
			$( '#show_banner' ).prop( 'checked', true );
			$( '#enable_consent_floating' ).prop( 'checked', true );
			$( '#manual_toggle_services' ).prop( 'checked', false );
			$( '#consent_mode' ).val( 'optin' );

			// Clear selected locations
			selectedLocations.clear();
			updateSelectedLocationsDisplay();

			// Reset all checkboxes (both flat list and hierarchical tree)
			$( 'input[type="checkbox"][data-type]' ).prop( 'checked', false );

			// Hide cancel button
			$( '.wpconsent-cancel-edit' ).hide();

			// Reset submit button text
			$( '.wpconsent-save-group' ).text( wpconsentI18n.saveLocationGroup );

			// Reset modal title
			$( '#wpconsent-modal-location-group .wpconsent-modal-header h2' ).text( wpconsentI18n.addNewLocationGroup );

			// Get used locations via AJAX to mark them as disabled
			$.ajax( {
				url: wpconsent.geolocationGroups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpconsent_get_available_locations',
					nonce: wpconsent.geolocationGroups.nonce,
					group_id: '' // Empty group_id for new groups
				},
				success: function ( response ) {
					if ( response.success ) {
						// Update available locations to mark used ones as disabled
						updateAvailableLocations( response.data.used_locations );
					}
				}
			} );
		}

		// Initialize predefined rules
		function initPredefinedRules() {
			// Handle custom rule button click
			$( '.wpconsent-add-custom-rule' ).on( 'click', function ( e ) {
				e.preventDefault();
				// Scroll to the form
				$( 'html, body' ).animate( {
					scrollTop: $( '#wpconsent-location-group-form' ).offset().top - 50
				}, 500 );
			} );

			// Check if predefined rules are already added
			checkPredefinedRuleStatus();

			// Handle predefined rule button click
			$( '.wpconsent-add-predefined-rule' ).on( 'click', function () {
				// Skip if button is disabled
				if ( $( this ).hasClass( 'wpconsent-button-disabled' ) ) {
					return;
				}

				const ruleType = $( this ).data( 'rule' );
				createPredefinedRule( ruleType );
			} );
		}

		// Check if predefined rules are already added and update button state
		function checkPredefinedRuleStatus() {
			// Define rule type to location mapping
			const ruleLocationMap = {
				'gdpr': 'continent:EU',
				'ccpa': 'us_state:CA',
				'lgpd': 'country:BR'
			};

			// Get all location groups from the table
			const usedLocations = {};
			$( '.wpconsent-location-groups-table .column-locations' ).each( function () {
				const locationText = $( this ).text().trim();

				// Check for each predefined rule location
				for ( const [ruleType, locationKey] of Object.entries( ruleLocationMap ) ) {
					const [locationType, locationCode] = locationKey.split( ':' );

					// If the location text contains the location name, mark as used
					// This is a simple check that works for single-location rules
					if ( locationText.includes( getLocationName( locationType, locationCode ) ) ) {
						usedLocations[ruleType] = true;
					}
				}
			} );

			// Update button state for each rule type
			for ( const ruleType in ruleLocationMap ) {
				const $button = $( `.wpconsent-add-predefined-rule[data-rule="${ruleType}"]` );

				if ( usedLocations[ruleType] ) {
					// Rule is already added, disable button and update text
					$button.addClass( 'wpconsent-button-disabled' );
					$button.text( wpconsentI18n.added );
				}
			}
		}

		// Helper function to get location name based on type and code
		function getLocationName( type, code ) {
			// This is a simplified version that works for our predefined rules
			const locationNames = {
				'continent:EU': 'Europe',
				'us_state:CA': 'California',
				'country:BR': 'Brazil'
			};

			return locationNames[`${type}:${code}`] || '';
		}

		// Create a predefined rule via AJAX
		function createPredefinedRule( ruleType ) {
			$.confirm( {
				title: '',
				content: wpconsentI18n.confirmCreateRule,
				type: 'blue',
				icon: 'fa fa-question-circle',
				animateFromElement: false,
				buttons: {
					confirm: {
						text: wpconsent.yes,
						btnClass: 'btn-confirm',
						keys: ['enter'],
						action: function () {
							// Show loading message
							$.alert( {
								title: '',
								content: wpconsentI18n.creatingRule,
								type: 'blue',
								icon: 'fa fa-spinner fa-spin',
								buttons: false,
								closeIcon: false,
								animateFromElement: false,
							} );

							// Make AJAX request
							$.ajax( {
								url: wpconsent.geolocationGroups.ajaxUrl,
								type: 'POST',
								data: {
									action: 'wpconsent_create_predefined_rule',
									nonce: wpconsent.geolocationGroups.nonce,
									rule_type: ruleType
								},
								success: function ( response ) {
									if ( response.success ) {
										// Reload page to show the new group
										window.location.reload();
									} else {
										$.alert( {
											title: '',
											content: response.data.message || wpconsentI18n.errorCreatingRule,
											type: 'red',
											icon: 'fa fa-exclamation-circle',
											animateFromElement: false,
											buttons: {
												confirm: {
													text: wpconsent.ok,
													btnClass: 'btn-confirm',
													keys: ['enter'],
												},
											}
										} );
									}
								},
								error: function () {
									$.alert( {
										title: '',
										content: wpconsentI18n.errorCreatingRule,
										type: 'red',
										icon: 'fa fa-exclamation-circle',
										animateFromElement: false,
										buttons: {
											confirm: {
												text: wpconsent.ok,
												btnClass: 'btn-confirm',
												keys: ['enter'],
											},
										}
									} );
								}
							} );
						}
					},
					cancel: {
						text: wpconsent.no,
						btnClass: 'btn-cancel',
						keys: ['esc'],
					},
				}
			} );
		}

		// Add missing translations
		const wpconsentI18n = {
			noLocationsSelected: 'No locations selected yet.',
			used: '(Used)',
			noLocationGroups: 'No location groups have been created yet. Use the form below to create your first location group.',
			updateGroup: 'Update Location Group',
			saveLocationGroup: 'Save Location Group',
			editLocationGroup: 'Edit Location Group',
			addNewLocationGroup: 'Add New Location Group',
			confirmCreateRule: 'Are you sure you want to create this predefined rule?',
			creatingRule: 'Creating predefined rule...',
			errorCreatingRule: 'Error creating predefined rule. Please try again.',
			added: 'Added'
		};

		// Initialize modal functionality
		function initModal() {
			// Store modal element
			const modal = document.getElementById( 'wpconsent-modal-location-group' );

			// Open modal when clicking the "Add Location Group" button
			$( document ).on( 'click', '.wpconsent-add-location-group', function ( e ) {
				e.preventDefault();
				modal.classList.add( 'active' );

				// Reset form when opening modal
				resetForm();
			} );

			// Close modal when clicking the close button
			$( document ).on( 'click', '#wpconsent-modal-location-group .wpconsent-modal-close', function () {
				modal.classList.remove( 'active' );
			} );

			// Close modal when clicking the cancel button
			$( document ).on( 'click', '.wpconsent-cancel-edit', function () {
				modal.classList.remove( 'active' );
				resetForm();
			} );

			// Modify form submission to close modal on success
			$( '#wpconsent-location-group-form' ).off( 'submit' ).on( 'submit', function ( e ) {
				e.preventDefault();

				// Validate form
				const groupName = $( '#group_name' ).val().trim();
				if ( !groupName ) {
					$.alert( {
						title: '',
						content: wpconsent.geolocationGroups.strings.groupNameRequired,
						type: 'red',
						icon: 'fa fa-exclamation-circle',
						animateFromElement: false,
						buttons: {
							confirm: {
								text: wpconsent.ok,
								btnClass: 'btn-confirm',
								keys: ['enter'],
							},
						}
					} );
					return;
				}

				if ( selectedLocations.size === 0 ) {
					$.alert( {
						title: '',
						content: wpconsent.geolocationGroups.strings.locationsRequired,
						type: 'red',
						icon: 'fa fa-exclamation-circle',
						animateFromElement: false,
						buttons: {
							confirm: {
								text: wpconsent.ok,
								btnClass: 'btn-confirm',
								keys: ['enter'],
							},
						}
					} );
					return;
				}

				// Prepare form data
				const formData = new FormData( this );

				// Add action parameter for AJAX handler
				formData.append( 'action', 'wpconsent_save_location_group' );

				// Add selected locations to form data
				selectedLocations.forEach( ( location, key ) => {
					formData.append( location.type + '[]', location.code );
				} );


				// Show saving message
				const $submitButton = $( '.wpconsent-save-group' );
				const originalText = $submitButton.text();
				$submitButton.text( wpconsent.geolocationGroups.strings.savingGroup );
				$submitButton.prop( 'disabled', true );

				// Submit form via AJAX
				$.ajax( {
					url: wpconsent.geolocationGroups.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function ( response ) {
						if ( response.success ) {
							// Close modal
							modal.classList.remove( 'active' );

							// Reload page to show updated groups
							window.location.reload();
						} else {
							$.alert( {
								title: '',
								content: response.data.message || wpconsent.geolocationGroups.strings.errorSaving,
								type: 'red',
								icon: 'fa fa-exclamation-circle',
								animateFromElement: false,
								buttons: {
									confirm: {
										text: wpconsent.ok,
										btnClass: 'btn-confirm',
										keys: ['enter'],
									},
								}
							} );
							$submitButton.text( originalText );
							$submitButton.prop( 'disabled', false );
						}
					},
					error: function () {
						$.alert( {
							title: '',
							content: wpconsent.geolocationGroups.strings.errorSaving,
							type: 'red',
							icon: 'fa fa-exclamation-circle',
							animateFromElement: false,
							buttons: {
								confirm: {
									text: wpconsent.ok,
									btnClass: 'btn-confirm',
									keys: ['enter'],
								},
							}
						} );
						$submitButton.text( originalText );
						$submitButton.prop( 'disabled', false );
					}
				} );
			} );
		}

		// Initialize when document is ready
		$( document ).ready( function () {
			initGeolocationGroups();
		} );

	}
)( jQuery );
