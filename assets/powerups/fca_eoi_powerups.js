jQuery( document ).ready(function( $ ){
    
    $( '.header_power_up_eoi_items' ).click( function() {
	
		var $this = $( this );
		$( 'input[name="eoi_power_ups_settings['+ $this.attr( 'id' ) +']"]' ).click();
	} );
    
    $( 'input[name*="send_test_mail"]' ).hide();
})