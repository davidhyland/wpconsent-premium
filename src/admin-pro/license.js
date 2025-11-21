(
	function ( $ ) {

		jconfirm.defaults = {
			closeIcon: false,
			backgroundDismiss: true,
			escapeKey: true,
			animationBounce: 1,
			useBootstrap: false,
			theme: 'modern',
			boxWidth: '560px',
			type: 'blue',
			animateFromElement: false,
			scrollToPreviousElement: false,
		};

		'use strict';

		const app = {
			l18n: window.wpconsent,
			init() {
				app.addEvents();
			},
			addEvents() {
				// Verify license key.
				$( document ).on(
					'click',
					'#wpconsent-setting-license-key-verify',
					function ( event ) {

						event.preventDefault();

						app.licenseVerify( $( this ) );
					}
				);

				// Deactivate license key.
				$( document ).on(
					'click',
					'#wpconsent-setting-license-key-deactivate',
					function ( event ) {

						event.preventDefault();

						app.licenseDeactivate( $( this ) );
					}
				);

				// Deactivate license key.
				$( document ).on(
					'click',
					'#wpconsent-setting-license-key-deactivate-force',
					function ( event ) {

						event.preventDefault();

						app.licenseDeactivate( $( this ), true );
					}
				);
			},
			licenseVerify( $el ) {
				const $row = $el.closest( '.wpconsent-license-key-container' );
				const $keyField = $( '#wpconsent-setting-license-key' );
				const data = {
					action: 'wpconsent_verify_license',
					_wpnonce: app.l18n.nonce,
					license: $keyField.val(),
					multisite: app.l18n.multisite,
				};

				WPConsentSpinner.show_button_spinner( $el );
				const svgIcon = app.l18n.icons.checkmark;
				$.post(
					ajaxurl,
					data,
					function ( res ) {

						let msg = '';
						let reload = false;
						WPConsentSpinner.hide_button_spinner( $el );

						if ( res.success ) {
							msg = res.data.msg;
							$row.find( '.type, .desc, #wpconsent-setting-license-key-deactivate' ).show();
							$row.find( '#wpconsent-setting-license-key-verify' ).hide();
							$row.find( '.type strong' ).text( res.data.type );
							reload = res.data.reload;
							// Event we can hook into.
							const event = new CustomEvent( 'wpconsent_license_verified', {detail: res} );
							window.dispatchEvent( event );
						} else {
							msg = res.data;
						}
						let exclamationSign = '<div class=\'excl-mark\'>!</div>';
						$.confirm(
							{
								title: res.success ? false : exclamationSign + app.l18n.license_error_title,
								content: res.success ? svgIcon + msg : msg,
								type: 'blue',
								buttons: {
									confirm: {
										text: app.l18n.ok,
										btnClass: 'wpconsent-btn-confirm',
										action: function () {
											if ( reload ) {
												$.confirm(
													{
														title: app.l18n.please_wait,
														content: function () {
															var self = this;
															return new Promise(
																function ( resolve ) {
																	setTimeout(
																		function () {
																			location.reload();
																			resolve();
																		},
																		2000
																	);
																}
															);
														}
													}
												);
											}
										}
									}
								}
							}
						);
					}
				).fail(
					function ( xhr ) {
						WPConsentSpinner.hide_button_spinner( $el );
						console.log( xhr.responseText );
					}
				);
			},
			licenseDeactivate: function ( el, force = false ) {

				var $this = $( el ),
					$row = $this.closest( '.wpconsent-license-key-container' ),
					data = {
						action: 'wpconsent_deactivate_license',
						_wpnonce: app.l18n.nonce,
						force,
						multisite: app.l18n.multisite,
					};

				WPConsentSpinner.show_button_spinner( $this );
				const svgIcon = app.l18n.icons.checkmark;
				$.post(
					ajaxurl,
					data,
					function ( res ) {
						let msg = res.data;
						let icon = '';
						WPConsentSpinner.hide_button_spinner( $this );

						if ( res.success ) {
							$row.find( '#wpconsent-setting-license-key' ).val( '' ).prop( 'disabled', false );
							$row.find( '.type, .desc, #wpconsent-setting-license-key-deactivate' ).hide();
							$row.find( '.type, .desc, #wpconsent-setting-license-key-deactivate-force' ).hide();
							$row.find( '#wpconsent-setting-license-key-verify' ).show();
							icon = 'success';
						} else {
							icon = 'warning';
							$row.find( '.type, .desc, #wpconsent-setting-license-key-deactivate' ).hide();
							$row.find( '.type, .desc, #wpconsent-setting-license-key-deactivate-force' ).show();
						}

						$.alert(
							{
								title: false, // Setting no title
								content: svgIcon + msg, // The message content
								type: 'blue', // The type (icon in SweetAlert is mapped to 'type' in jquery-confirm)
								buttons: {
									ok: {
										text: app.l18n.ok, // Custom button text
										btnClass: 'wpconsent-btn-confirm', // Custom button class
										action: function () {
											// You can add any actions here if needed
										}
									}
								}
							}
						);

					}
				).fail(
					function ( xhr ) {
						WPConsentSpinner.hide_button_spinner( $this );
						console.log( xhr.responseText );
					}
				);
			},
		};

		app.init();
	}
)( jQuery );
