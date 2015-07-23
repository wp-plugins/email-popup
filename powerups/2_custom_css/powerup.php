<?php

function powerup_custom_css( $settings ) {

	paf_options ( array(
		'eoi_powerup_custom_css' => array(
			'type' => 'checkbox',
			'options' => array(
				'on' => __( 'Enabled' ),
			),
			'page' => 'eoi_powerups',
			'title' => __( 'Custom CSS' ),
			'description' => sprintf( '<p class="description eoi_powerup_description">%s</p>', __( 'Adds a setting that lets you add custom CSS to your opt-in box to the Easy Opt-ins editor.' ) ),
		)
	) );

	if( ! paf( 'eoi_powerup_custom_css' ) ) {
		return;
	}

	new EoiCustomCssBox( $settings );
}

class EoiCustomCssBox {

	var $settings;

	public function __construct( $settings ) {

		$this->settings = $settings;
		
		add_action( 'fca_eoi_powerups',           array( $this, 'show_custom_css_field' ) );  
		add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'enqueue_admin_footer_js' ) );
		add_filter( 'fca_eoi_alter_form',         array( $this, 'append_css_to_form' ) , 10 , 2 );
	}

	public function init() {
	}

	public function append_css_to_form( $content , $fca_eoi_meta ) {

		if( $css = K::get_var( 'custom_css', $fca_eoi_meta ) ) {
			$content .= "<style>$css</style>";
		}

		return $content;
	}

	/*
	 * Add fieldset custom css box
	 */
	public function show_custom_css_field( $fca_eoi_meta ) {
		echo '<div class="eoi-custom-css-form" style="width:40.5em;">';
		K::textarea( 'fca_eoi[custom_css]'
			, array(
				'class' => 'fca_eoi_custom_css_textbox',
				'placeholder' => __( 'Enter your custom CSS here...' ),
			)
			, array(
				'value' => K::get_var( 'custom_css', $fca_eoi_meta, '' ),
				'format' => '<label> Custom CSS</label><br />:textarea',
			)
		);
		echo '</div>';
	}

	public function enqueue_admin_scripts() {
		wp_enqueue_style( 'powerups-css', $this->settings['plugin_url'] . '/assets/powerups/fca_eoi_powerups.css' );

		$protocol = is_ssl() ? 'https' : 'http';
		$screen = get_current_screen();

		// Exit function if not in one of our plugin pages
		if( 'easy-opt-ins' !== $screen->id ){
			return;
		}

		// Enqueue CodeMirror JS and CSS
		$codemirror_prefix = "$protocol://cdnjs.cloudflare.com/ajax/libs/codemirror/4.7.0/";
		$codemirror_js = array(
			'codemirror' => 'codemirror.min.js',
			'codemirror-css' => 'mode/css/css.min.js',
			'codemirror-placeholder' => 'addon/display/placeholder.min.js',
		);
		foreach ( $codemirror_js as $handle => $path) {
			wp_enqueue_script( $handle, $codemirror_prefix . $path );
		}
		wp_enqueue_style('codemirror', $codemirror_prefix . 'codemirror.min.css' );
	}

	public function enqueue_admin_footer_js() {
		?><script type="text/javascript">

			jQuery( document ).ready( function( $ ) {

				// Add CodeMirrors
				$( '.fca_eoi_custom_css_textbox' ).each( function( i, el ) {
					var editor = CodeMirror.fromTextArea( el, {
						mode: "text/css",  
						matchBrackets: true,
						lineNumbers: true,
						lineWrapping: true,
						foldGutter: true,
						gutters: [ "CodeMirror-linenumbers", "CodeMirror-foldgutter" ]
					} );
				} );
			} );
		</script><?php
	}
}
