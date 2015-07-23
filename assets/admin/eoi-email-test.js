jQuery( function( $ ) {
  var $container = $( '#fca_eoi_email_test' );

  var $recommendation =
    $( '<div class="fca_eoi_email_test_recommendation"/>' )
      .html( fca_eoi_email_test_recommendation );

  var $invalid_address_error =
    $( '<span class="fca_eoi_email_test_error"/>')
      .text( 'Invalid email address.' );

  if ( $container.length == 0 ) {
    return;
  }

  var $text = $( '<span>Would you like to send a test email?</span>' );
  var $buttons = $( '<div class="fca_eoi_email_test_buttons"/>' );

  var remove_container = function() {
    $container.remove();
  };

  var disable_and_hide_prompt = function() {
    post( 'fca_eoi_email_test_disable', {}, remove_container );
  };

  var disable_prompt = function() {
    post( 'fca_eoi_email_test_disable' );
  };

  var highlight_recommendation = function() {
    $text.remove();
    $buttons.remove();
    $recommendation.addClass('highlighted').show();
  };

  var hide_invalid_address_error = function() {
    $invalid_address_error.fadeOut( function() {
      $( this ).remove();
    } );
  };

  var show_invalid_address_error = function() {
    $invalid_address_error.finish().remove();
    $buttons.append( $invalid_address_error.show() );
  };

  var post = function( action, data, completion_handler ) {
    $buttons.html( '<i class="fa fa-spinner fa-spin"></i>' );

    data = data || {};
    data['action'] = action;

    $.post( ajaxurl, data, completion_handler );
  };

  var $button_disable_test = $( '<span class="button">No</span>' ).click( disable_and_hide_prompt );

  var $button_send_test = $( '<span class="button">Yes, send test email</span>' ).click( function() {
    var $input_email = $( '<input type="email" placeholder="Email address"/>' )
      .keyup( hide_invalid_address_error )
      .mouseup( hide_invalid_address_error )
      .on( 'paste', hide_invalid_address_error )
      .on( 'contextmenu', hide_invalid_address_error )
      .keypress( function( event ) {
        if ( event.which == 13 ) {
          send_email();
          event.preventDefault();
          event.stopPropagation();
        }
      } );

    var send_email = function() {
      var email = $.trim( $input_email.val() );

      if ( ! email || ! validateEmail( email ) ) {
        show_invalid_address_error();
        return;
      }

      $text.text( 'Sending email...' );

      post( 'fca_eoi_email_test_send', {
        sender_name: $( 'input[name="fca_eoi[optin_bait_fields][sender_name]"]' ).val() || "WordPress",
        sender_email: $( 'input[name="fca_eoi[optin_bait_fields][sender_email]"]' ).val() || 'wordpress@' + window.location.host,
        email: email,
        subject: $( 'input[name="fca_eoi[optin_bait_fields][subject]"]' ).val() || 'Test',
        message: $( 'textarea[name="fca_eoi[optin_bait_fields][message]"]' ).val() || 'Test message.'
      }, function( result ) {
        if ( JSON.parse( result ) ) {
          $text.text( 'Email sent. Dit it arrive?' );

          $buttons.html( '' ).append(
            $( '<span class="button">Yes</span>' ).click( function() {
              if ( fca_eoi_email_test_has_active ) {
                disable_and_hide_prompt();
              } else {
                disable_prompt();
                highlight_recommendation();
              }
            } ),
            $( '<span class="button">No</span>' ).click( function() {
              if ( fca_eoi_email_test_has_active ) {
                $text.html( fca_eoi_email_test_failure_message );
                $buttons.remove();
                $recommendation.remove();
              } else {
                highlight_recommendation();
              }
            } )
          );
        } else {
          $text.html( 'Could not send email. ' + fca_eoi_email_test_failure_message );
          $buttons.remove();
          $recommendation.remove();
        }
      } );
    };

    var $button_send = $( '<span class="button">Send test email</span>' ).click( send_email );

    $buttons.html( '' ).append( $input_email, $button_send );
  } );

  $container.html( '' ).append(
    $text,
    $buttons.append( $button_disable_test, $button_send_test )
  );

  if ( ! fca_eoi_email_test_has_active ) {
    $container.append( $recommendation );
  }

  function validateEmail( email ) {
    var re = /^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i;
    return re.test( email );
  }
} );