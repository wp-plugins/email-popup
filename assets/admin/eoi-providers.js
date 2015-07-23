var fca_eoi_provider_status_setup;
var fca_eoi_provider_status_codes;
var fca_eoi_provider_status_set;
var fca_eoi_provider_is_value_changed;

(function() {
  var status_descriptors = {};
  var icon_base_class = 'fa fca_eoi_provider_status';
  var $ = jQuery;

  fca_eoi_provider_status_codes = { unknown: 0, loading: 1, ok: 2, error: 3 };

  fca_eoi_provider_status_setup = function( handle, elements ) {
    status_descriptors[ handle ] = {
      status_code: fca_eoi_provider_status_codes.unknown,
      $icons: $( elements ).map( function() {
        return $( '<i/>' ).hide().appendTo( $( this ).parent() )[0];
      } )
    };
  };

  fca_eoi_provider_status_set = function( handle, status_code ) {
    var descriptor = status_descriptors[ handle ];
    if ( ! descriptor ) {
      return;
    }

    var status_class;
    if ( status_code == fca_eoi_provider_status_codes.loading ) {
      status_class = 'fca_eoi_provider_status_loading fa-spinner fa-spin';
    } else if ( status_code == fca_eoi_provider_status_codes.ok ) {
      status_class = 'fca_eoi_provider_status_ok fa-check';
    } else if ( status_code == fca_eoi_provider_status_codes.error ) {
      status_class = 'fca_eoi_provider_status_error fa-times';
    } else {
      descriptor.$icons.hide();
      return;
    }

    descriptor.$icons.attr( 'class', icon_base_class + ' ' + status_class ).show();
  };

  fca_eoi_provider_is_value_changed = function( $element ) {
    var current_value = $element.val();
    var is_changed = current_value != $element.data( 'previous_value' );

    $element.data( 'previous_value', current_value );

    return is_changed;
  };
})();

