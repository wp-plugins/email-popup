<?php

/**
 * @package    Optin Cat
 */

require_once dirname( __FILE__ ) . '/../../common/layout_1/layout.php';

$layout = fca_eoi_layout_descriptor_1( 'Popup 1', 'lightbox_1', array(
	'headline_copy' => 'Free Email Updates',
	'description_copy' => 'Get the latest content first.',
	'name_placeholder' => 'Name',
	'email_placeholder' => 'Email',
	'button_copy' => 'Join Now',
	'privacy_copy' => "100% Privacy. We don't spam.",
) );
