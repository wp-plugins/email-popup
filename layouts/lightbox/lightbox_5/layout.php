<?php

/**
 * @package Optin Cat
 */

$layout = array(

	'name' => __( 'Popup 5' ),

	'editables' => array(

		// Added to the fieldset "Form Background"
		'form' => array(
			'.fca_eoi_lightbox_5' => array(
				'background-color' => array( __( 'Form Background' ), '#f6f6f6' ),
				'border-color' => array( __( 'Border Color' ), '#ccc' ),
			),
		),

		// Added to the fieldset "Headline"
		'headline' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_headline_copy_wrapper' => array(
				'font-size' => array( __('Font Size'), '28px'),
				'color' => array( __('Font Color'), '#1A78D7'),
			),
		),
		'description' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_description_copy_wrapper p' => array(
			),
		),
		'name_field' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_name_field_wrapper, .fca_eoi_lightbox_5 .fca_eoi_lightbox_5_name_field_wrapper input' => array(
				'font-size' => array( __( 'Font Size' ), '18px' ),
				'color' => array( __( 'Font Color' ), '#777' ),
				'background-color' => array( __( 'Background Color' ), '#FFF' ),
			),
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_name_field_wrapper' => array(
				'border-color' => array( __('Border Color'), '#CCC'),
			),
		),
		'email_field' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_email_field_wrapper, .fca_eoi_lightbox_5 .fca_eoi_lightbox_5_email_field_wrapper input' => array(
				'font-size' => array( __( 'Font Size' ), '18px' ),
				'color' => array( __( 'Font Color' ), '#777' ),
				'background-color' => array( __( 'Background Color' ), '#FFF'),
			),
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_email_field_wrapper' => array(
				'border-color' => array( __( 'Border Color' ), '#CCC'),
			),
		),
		'button' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input' => array(
				'font-size' => array( __('Font Size'), '18px' ),
				'color' => array( __( 'Font Color' ), '#FFF' ),
				'background-color' => array( __( 'Button Color' ), '#E67E22' ),
			),
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input:hover' => array(
				'background-color' => array( __( 'Hover Background Color' ), '##D35400' ),
			),
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper' => array(
				'background-color' => array( __( 'Container Background Color' ), '#D35400' ),
				'border-color' => array( __( 'Container Bottom Bolor' ), '#D35400' ),
			),
		),
		'privacy' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_privacy_copy_wrapper' => array(
				'font-size' => array( __('Font Size'), '14px'),
				'color' => array( __('Font Color'), '#8F8F8F'),
			),
		),
		'fatcatapps' => array(
			'.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_fatcatapps_link_wrapper a, .fca_eoi_lightbox_5 .fca_eoi_lightbox_5_fatcatapps_link_wrapper a:hover' => array(
				'color' => array( __('Font Color'), '#8F8F8F'),
			),
		),
	),

	'autocolors' => array(
		array(
			'source' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input][background-color]',
			'destination' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input:hover][background-color]',
			'operations' => array(
				'spin' => '-4.277009381951832',
				'saturate' => '20.32520325203253',
				'darken' => '10.392156862745106',
			),
		),
		array(
			'source' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input][background-color]',
			'destination' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper][background-color]',
			'operations' => array(
				'spin' => '-4.277009381951832',
				'saturate' => '20.32520325203253',
				'darken' => '10.392156862745106',
			),
		),
		array(
			'source' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper input][background-color]',
			'destination' => '[.fca_eoi_lightbox_5 .fca_eoi_lightbox_5_submit_button_wrapper][border-color]',
			'operations' => array(
				'spin' => '-4.277009381951832',
				'saturate' => '20.32520325203253',
				'darken' => '10.392156862745106',
			),
		),

	),

	'texts' => array(
		'headline_copy' => 'Free Email Updates',
		'description_copy' => 'Get the latest content first.',
		'name_placeholder' => 'Name',
		'email_placeholder' => 'Email',
		'button_copy' => 'Join Now',
		'privacy_copy' => "100% Privacy. We don't spam.",
	),
);
