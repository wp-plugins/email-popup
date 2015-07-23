<?php
/**
* @package thuantp
*/

/**
 * This class handles the pointers used in the introduction tour.
 *
 * @todo Add an introductory pointer on the edit post page too.
 */
class EOITourPointer {

	// get all infor of plugin
	private $plugin_data;

	//  the option fca_eoi_take_the_tour
	private $options;

	// the post_type
	private $post_type;

	private $settings;

	public function __construct(  $settings=null ) {

		$this->settings = $settings;
		$this->post_type = $settings['post_type'];
		$this->options = get_option( 'fca_eoi_take_the_tour' );

		if ( ! in_array( $this->options, array( 'closed' ) ) ) {

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			add_filter( 'admin_url', array( $this, 'add_fca_eoi_tour_item_param' ) );
			add_action( 'wp_ajax_fca_eoi_take_the_tour', array( $this, 'fca_eoi_take_the_tour_pointer_ajax' ) );
		}
	}

	/**
	 * Loads CSS and JS elements for pointer popup
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		add_action( 'admin_print_footer_scripts', array( $this, 'intro_tour' ) );
	}

	/*
	 * re-write the url after saving post via tour pointer
	 */
	function add_fca_eoi_tour_item_param ( $link ) {

		$tour_item = K::get_var( 'fca-eoi-tour-item', $_REQUEST );
		if ( ! empty( $tour_item ) ) {
			$link = add_query_arg( array( 'fca-eoi-tour-item' => urlencode( $tour_item ) ), $link );
		}
		return $link;
	}

	/*
	 * Update option fca_eoi_take_the_tour
	 */
	function fca_eoi_take_the_tour_pointer_ajax () {

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'fca_eoi_take_the_tour' ) ) {
			die( __( 'An error occurred, please try again.' ) );
		}

		// Check status
		update_option( 'fca_eoi_take_the_tour', 'closed' );
		exit;
	}

	/**
	 * Load the introduction tour
	 */
	function intro_tour() {

		global  $pagenow;

		$this->plugin_data = get_plugin_data( $this->settings[ 'plugin_dir' ] . 'easy-opt-ins.php' );

		// Ajax request template

		$ajax = '
			jQuery.ajax( {
				type: "POST",
				url: "' . admin_url( 'admin-ajax.php' ) . '",
				data: {
					action: "fca_eoi_take_the_tour",
					nonce: "' . wp_create_nonce( 'fca_eoi_take_the_tour' ) . '"
				}
			} );
		';

		// if there is more than 1 layout the number will be 6 , otherwise 5
		$init_pointer_number = 5;
		if ( count( glob( $this->settings[ 'plugin_dir' ] . 'layouts/*', GLOB_ONLYDIR ) ) > 1 ) {
			$init_pointer_number += 1;
		};

		$page_items = array(
			'fca_eoi_form_integration' => array(
				'target'           => '#fca_eoi_fieldset_form_integration',
				'options'          => array(
					'content'      => sprintf( '<h3>%s</h3><p>%s</p>'
						, __( 'Mailing List Integration' )
						, __( 'First, integrate '. $this->plugin_data[ 'Name' ] .' with your mailing list.' )
					),
				   'position'      => array(
				   		'edge'  => 'bottom',
				   		'align' => 'middle'
			   		),
					'pointerClass' => 'fca_eoi_tour_pointer_style',
				),
				'button1'          => __( 'Close' ),
				'button2'          => __( 'Next' ),
				'button3'          => __( 'Previous' ),
				'button1_function' => $ajax

			),
			'fca_eoi_fieldset_thank_you_page' => array(
				'target'           => '#fca_eoi_fieldset_thank_you_page',
				'options'          => array(
					'content'      => sprintf( '<h3>%s</h3><p>%s</p>'
						, __( 'Thank you page' )
						, __( 'Next, select a thank you page <em>(selecting a thank you page is required)</em>.' )
					),
					'position'     => array(
						'edge'  => 'bottom',
						'align' => 'center'
					),
					'pointerClass' => 'fca_eoi_tour_pointer_style'
				),
				'button1'          => __( 'Close' ),
				'button2'          => __( 'Next' ),
				'button3'          => __( 'Previous' ),
				'button1_function' => $ajax
			),
			'fca_eoi_design_and_content_headline' => array(
				'target' => '#fca_eoi_design_and_content_headline',
				'options' => array(
					'content'  => '<h3>' . __( 'Design & Content' ) . '</h3>' . '<p>' . __( 'You can customize your opt-in form in this area. You can click anywhere on your form to change the text, colors, etc...' ) . '</p>'
					,
					'position' => array( 'edge' => 'bottom', 'align' => 'center' ),
					'pointerClass' => 'fca_eoi_tour_pointer_style'
				),
				'button1' => __( 'Close' ),
				'button2' => __( 'Next' ),
				'button3' => __( 'Previous' ),
				'button1_function' => $ajax,
			),
			'fca_eoi_save' => array(
				'target' => '#publish',
				'options' => array(
				  	'content'  => '<h3>' . __( 'Save' ) . '</h3>'. '<p>' . __( 'Make sure to hit ‘Save Form’ now that you’ve set up your form.' ) . '</p>'
				  	,
					'position' => array( 'edge' => 'bottom', 'align' => 'left' ),
					'pointerClass' => 'fca_eoi_tour_pointer_style',
				),
				'button1'  => __( 'Close' ),
				'button2'  => __( 'Save Now' ),
				'button3'  => __( 'Previous' ),
				'button1_function' =>  $ajax,
				'button2_function' => "$( '#publish' ).click()",
				'extra_function' =>  "var form = $(pointer.target).closest( 'form' );$(pointer.target).after( '<input type=\"hidden\" name=\"fca-eoi-tour-item\" value=\"$init_pointer_number\"/>' );"
			),
		);

		if( $init_pointer_number == 6 ) {

			$fca_eoi_choose_layout = array(
				'target'   => 'a[href="#fca_eoi_meta_box_setup"]',
				'options' => array(
					'content'  => '<h3>' . __( 'Choose Layout' ) . '</h3>' . '<p>' . __( 'Choose a Layout to get started.' ) . '</p>',
					'position' => array( 'edge' => 'bottom', 'align' => 'left' ),
					'pointerClass' => 'fca_eoi_tour_pointer_style'
				),
				'button1' => __( 'Close' ),
				'button2' => __( 'Next' ),
				'button3' => __( 'Previous' ),
				'button1_function' => $ajax,
				'extra_function' => "$( '[href=\"#fca_eoi_meta_box_setup\"]' ).click();",
			);
			// push this array to the beginning of $page_items array
			array_unshift( $page_items, $fca_eoi_choose_layout );
		}

		$fca_eoi_dashboard_tour_item =  array(
			'target' => '#menu-posts-easy-opt-ins',
			'options' => array(
				'content'  => '<h3>' . __( 'Getting started' ) . '</h3>' . '<p>' . __( 'Thanks for installing '. $this->plugin_data['Name'] .'. Click “Start Tour” to view a quick introduction of how to use this plugin.' ) . '</p>'
				,
				'position' => array( 'edge' => 'bottom', 'align' => 'center' ),
				'pointerClass' => 'fca_eoi_tour_dashboard'
			),
			'button1' => __( 'Close' ),
			'button2' => __( 'Start tour' ),
			'button1_function' => $ajax,
			'button2_function' => ''
		);

		$fca_eoi_index_tour_item = 1;

		if ( ! isset( $_GET[ 'fca-eoi-tour-item' ] ) ) {
			if ( FALSE
				|| 'easy-opt-ins' !== $this->post_type 
				|| ( $pagenow == 'edit.php' && 'easy-opt-ins' == $this->post_type )
			) {
				$fca_eoi_dashboard_tour_item =  array(
					'target' => '#menu-posts-easy-opt-ins',
					'options' => array(
						'content'  => '<h3>' . __( 'Getting started' ) . '</h3>' . '<p>' . __( 'Thanks for installing '. $this->plugin_data['Name'] .'. Click “Start Tour” to view a quick introduction of how to use this plugin.' ) . '</p>',
						'position' => array( 'edge' => 'bottom', 'align' => 'center' ),
						'pointerClass' => 'fca_eoi_tour_dashboard'
					),
					'button1' => __( 'Close' ),
					'button2' => __( 'Start tour' ),
					'button2_function' => "document.location='" . admin_url( 'post-new.php?post_type=easy-opt-ins&fca-eoi-tour-item=1' )."'",
				);
			}

			$fca_eoi_index_tour_item = 0;
		} else {
			if ( K::get_var( 'fca-eoi-tour-item', $_GET ) == $init_pointer_number ) {

				$fca_eoi_index_tour_item = $init_pointer_number;
				$fca_eoi_publish_tour_item =  array(
					'target'   => '#menu-appearance',
					'options' => array(
						'content' => '<h3> Publish </h3>' . '<p>' . __( 'You can publish your opt-in widget by going to “Appearance -> Widgets” and choosing the “Optin Cat Sidebar Widget”.' ) . '</p>'
						,
						'position' => array( 'edge' => 'top', 'align' => 'center' ),
						'pointerClass' => 'fca_eoi_tour_pointer_style'
					),
					'button1' => __( 'Close' ),
					'button3' => __( 'Previous' ),
					'button1_function' => $ajax,
				);
				$page_items['fca_eoi_publish'] = $fca_eoi_publish_tour_item;
			}
		}
		// push this array to the beginning of $page_items array
		array_unshift( $page_items, $fca_eoi_dashboard_tour_item );

		$this->print_scripts( $fca_eoi_index_tour_item ,$page_items );
	}

	/**
	 * Prints the pointer script
	 *
 	 * @param string      $selector         The CSS selector the pointer is attached to. 
	 * @param array       $options          The options for the pointer. 
	 * @param string      $button1          Text for button 1 
	 * @param string|bool $button2          Text for button 2 (or false to not show it, defaults to false) 
	 * @param string      $button2_function The JavaScript function to attach to button 2 
	 * @param string      $button1_function The JavaScript function to attach to button 1 	 
	 */
	function print_scripts( $fca_eoi_tour_index, $options) {
		?><script>
			jQuery( document ).ready( function( $ ) {
				var fca_eoi_tour_pointer_options_json = <?php echo json_encode( $options ); ?>;
				var fca_eoi_tour_pointer_options = [];
				for ( elem in fca_eoi_tour_pointer_options_json ) {
					fca_eoi_tour_pointer_options.push( fca_eoi_tour_pointer_options_json[ elem ] );
				}

				$( window ).load( function( $ ) {
					setTimeout( function() {
						fca_eoi_open_pointer( <?php echo $fca_eoi_tour_index ?> );
					}, 500 );
				} );

				function fca_eoi_open_pointer( i ) {

					$( '[href="#fca_eoi_meta_box_build"]' ).click();
						var pointer = fca_eoi_tour_pointer_options[ i ];
						var button2_function_text = pointer.button2_function;
						var button1_function_text = pointer.button1_function;
						var extra_function_text = pointer.extra_function;

						// excute the extra function
						if ( extra_function_text ) {
							eval( extra_function_text );
						}

						options = $.extend( pointer.options, {
							buttons:function ( event, t ) {
								button = jQuery( '<a id="fca-eoi-tour-pointer-close-'+ i +'" style="margin-left:5px" class="button-secondary">' + pointer.button1 + '</a>' );
								button.bind( 'click.pointer', function () {
									t.element.pointer( 'close' );
									if( pointer.button1_function ) {
										eval( button1_function_text );
									}
								} );
								return button;
							},
							close: function() {}
						});
						$( pointer.target +':not(.expanded) legend' ).click();
						$( pointer.target ).pointer( options ).pointer( 'open' );

						if ( pointer.button2 ) {

							jQuery( '#fca-eoi-tour-pointer-close-'+i ).after( '<a id="fca-eoi-tour-pointer-primary-'+ i +'" class="button-primary">' + pointer.button2 + '</a>' );
							jQuery( '#fca-eoi-tour-pointer-primary-' + i ).click( function ( e ) {
								e.preventDefault();
								$( pointer.target ).pointer( 'close' );
								eval( button2_function_text );
								fca_eoi_open_pointer( i + 1 );
							} );
						}

						if ( pointer.button3 ) {
							jQuery( '#fca-eoi-tour-pointer-close-'+i ).after( '<a id="fca-eoi-tour-pointer-previous-'+ i +'" class="button-secondary">' + pointer.button3 + '</a>' );
							jQuery( '#fca-eoi-tour-pointer-previous-'+i ).click( function () {
								$( pointer.target ).pointer( 'close' );
								fca_eoi_open_pointer( i - 1 );
							} );
						}
					}
				} );
		</script><?php
	}
}
