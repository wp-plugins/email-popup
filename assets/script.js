jQuery( document ).ready(function(){

	jQuery( document ).on( 'submit', '.fca_eoi_form', function () {

	
	
		// Attach tooltips
		jQuery( '[name=email], [name=name]' ).each( function(index) {
		
			var $this = jQuery( this );
			var tooltipWidth = $this.width() * .8;

			$this.tooltipster( {
				contentAsHTML: true
				, fixedWidth: tooltipWidth
				, minWidth: tooltipWidth
				, maxWidth: tooltipWidth
				, trigger: 'none'
			} );
		} );

		// Remove tooltip and tick on focus
		jQuery( '[name=email], [name=name]', jQuery( this ) ).focus( function() {
			var $this = jQuery( this );
			var $button = jQuery( '[type=submit]', $this.closest( '.fca_eoi_form' ) );
			var button_initial_val = $button.data( 'fca_eoi_initial_val' );

			// Remove any previous icon
			if( 'undefined' !== typeof( button_initial_val ) ) {
				$button.val( button_initial_val );
			}
			
			// Hide tooltip
			jQuery( this ).tooltipster( 'hide' );
		} );
		
		
		//RN NOTE WHY IS THIS SO OVER COMPLICATED...
		
		// Handle form submissions
		//$( '.fca_eoi_form' ).submit( function( e ) {

			var $this = jQuery( this );
			var $email_field = jQuery( '[name=email]', $this );
			var $name_field = jQuery( '[name=name]', $this );
			var name = $name_field.val();
			var email = $email_field.val();
			var list_id = $this.data( 'fca_eoi_list_id' );
			var url = 'https://api.createsend.com/api/v3.1/subscribers/' + list_id + '.json';
			var $button = jQuery( '[type=submit]', $this );
			var button_initial_val;
			var highlight_interval = false;
			var thank_you_page = $this.data( 'fca_eoi_thank_you_page' );
			var has_error = false;
	                var fca_eoi_form_id = jQuery( '[name=fca_eoi_form_id]', $this ).val();

			// Save or save and get initial button value
			if( $button.data( 'fca_eoi_initial_val' ) ) {
				button_initial_val = $button.data( 'fca_eoi_initial_val' );
			} else {
				button_initial_val = $button.val();
				$button.data( 'fca_eoi_initial_val', button_initial_val );
			}
			
			// Remove any previous icon
			$button.val( button_initial_val );
			
			// Get Error Messages from hidden fields
			var blah = fca_eoi.ajax_url;
			fca_eoi.invalid_email = $this.children('.fca_eoi_error_texts_email').val();
			fca_eoi.field_required = $this.children('.fca_eoi_error_texts_required').val();
			
			// Check email address
			if( ! email || ! is_email( email ) ) {

				$email_field.tooltipster( 'content', email ? fca_eoi.invalid_email : fca_eoi.field_required );
				$email_field.tooltipster( 'show' );
				$button.val( '✗ ' + button_initial_val );
				has_error = true;
			}

			// Check name
			if( $name_field.length && ! name ) {

				$name_field.tooltipster( 'content', fca_eoi.field_required );
				$name_field.tooltipster( 'show' );
				$button.val( '✗ ' + button_initial_val );
				has_error = true;
			}

			// Exit if there is any error
			if( has_error ) {
				return false;
			}

			$( '.fca_eoi_form_button_element', $this ).attr( 'disabled', 'disabled' );

			return true;

			// Start highlighting
			highlight_interval = setInterval(  function() {
				$button.fadeTo( 200, 0.25 ).fadeTo( 200, 1.0 );
			}, 500 );

			$.ajax( {
				url: fca_eoi.ajax_url
				, data: { 'email': email, 'name': name, 'action': 'fca_eoi_subscribe', 'list_id': list_id ,'form_id': fca_eoi_form_id}
				, type: 'POST'
				, datatype: 'text'
			} ).done( function( data ) {
				// Stop highlighting and add an icon for success/failure
				$button.val( data + ' ' + button_initial_val );
				clearInterval( highlight_interval );
				if ( thank_you_page && '✓' === data ) {
					window.location.href = thank_you_page;
				}
			} );
		//} );
	} );
} );

function is_email( email ) {
	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	return regex.test(email);
}