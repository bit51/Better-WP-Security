jQuery( document ).ready( function () {

	jQuery( "#itsec_advanced_tweaks_enabled" ).change( function() {

		if ( jQuery( "#itsec_advanced_tweaks_enabled" ).is( ':checked' ) ) {
			jQuery( "#advanced_tweaks_server, #advanced_tweaks_wordpress, h2.settings-section-header" ).show();

		} else {
			jQuery( "#advanced_tweaks_server, #advanced_tweaks_wordpress, h2.settings-section-header" ).hide();

		}

	} ).change();

} );