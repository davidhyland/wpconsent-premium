/* global ajaxurl, wpconsent */

window.WPConsentAddons = window.WPConsentAddons || (
	function ( document, window, $ ) {
		const app = {
			l18n: window.wpconsent,
			init: function () {
				if ( !app.should_init() ) {
					return;
				}
				app.init_install();
			},
			should_init() {
				app.$install_buttons = $( '.wpconsent-button-install-addon' );
				return app.$install_buttons.length > 0;
			},
			init_install() {
				app.$install_buttons.on(
					'click',
					function ( e ) {
						e.preventDefault();
						const $button = $( this );
						app.install_addon( $button );
					}
				);
			},
			install_addon( $button ) {
				const addon = $button.data( 'addon' );
				if ( !addon ) {
					return;
				}
				app.show_button_spinner( $button );
				$.post(
					ajaxurl,
					{
						action: 'wpconsent_install_addon',
						slug: addon,
						_wpnonce: wpconsent.nonce,
						multisite: app.l18n.multisite,
					},
					function ( response ) {
						if ( response.success ) {
							window.location.reload();
						} else {
							app.hide_button_spinner( $button );
							if ( response.data.message ) {
								let exclamationSign = "<div class='excl-mark'>!</div>";
								$.confirm(
									{
										title: false,
										content: exclamationSign + response.data.message,
										type: 'blue',
										buttons: {
											confirm: {
												text: app.l18n.ok,
												btnClass: 'wpconsent-btn-confirm',
												action: function () {

												}
											}
										}
									}
								);
							}
						}
					}
				);

			},
			show_button_spinner( $button ) {
				window.WPConsentSpinner.show_button_spinner( $button );
			},
			hide_button_spinner( $button ) {
				window.WPConsentSpinner.hide_button_spinner( $button );
			},
		};
		return app;
	}( document, window, jQuery )
);

WPConsentAddons.init();
