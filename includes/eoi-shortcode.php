<?php

class EasyOptInsShortcodes {

	var $settings;
	var $prerequisites = array();
	var $assets_enqueued = false;

	public function __construct( $settings = array() ) {
		global $pagenow, $typenow;

		$this->settings = $settings;

		// Add shortcode
		add_shortcode( $this->settings[ 'shortcode' ], array( $this, 'shortcode_content' ) );

		// Add shortcode aliases
		foreach ( $settings[ 'shortcode_aliases' ] as $shortcode) {
			add_shortcode( $shortcode, array( $this, 'shortcode_content' ) );
		}

		// Add shortcode generator button
		if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) && $typenow != 'download' ) {
			add_action( 'admin_head', array( $this, 'button_head' ) );
			add_action( 'media_buttons', array( $this, 'button' ), 1000 );
			add_action( 'admin_footer', array( $this, 'button_footer' ) );
		}

		if ( ! is_admin() ) {
			add_action( 'wp', array( $this, 'parse_shortcodes' ), 11 );
		}
	}

	public function wp_head() {
		foreach ( $this->prerequisites as $form_id => $head ) {
			echo $head;
			$this->prerequisites[ $form_id ] = '';
		}
	}

	public function parse_shortcodes() {
		global $post;

		if ( empty( $post ) ) {
			return;
		}

		if ( preg_match_all( '/' . get_shortcode_regex() . '/s', $post->post_content, $matches )
		     && array_key_exists( 2, $matches )
		     && array_key_exists( 3, $matches )
		     && in_array( $this->settings['shortcode'], $matches[2] )
		) {
			foreach ( $matches[3] as $params ) {
				$params = shortcode_parse_atts( $params );
				if ( ! empty( $params['id'] ) ) {
					$this->add_prerequisites_for_form( (int) $params['id'] );
				}
			}
		}

		if ( ! empty( $this->prerequisites ) ) {
			$this->enqueue_assets();
			add_action( 'wp_head', array( $this, 'wp_head' ) );
		}
	}

	/**
	 * @param int $form_id
	 *
	 * @return string
	 */
	private function add_prerequisites_for_form( $form_id ) {
		if ( array_key_exists( $form_id, $this->prerequisites ) ) {
			return $this->prerequisites[ $form_id ];
		}

		$head = '';

		$fca_eoi_meta = get_post_meta( $form_id, 'fca_eoi', true );
		if ( ! $fca_eoi_meta ) {
			return $head;
		}

		$layout_id = $fca_eoi_meta['layout'];
		$layout    = new EasyOptInsLayout( $layout_id );
		$scss_path = $layout->path_to_resource( 'layout', 'scss' );

		$scss = $layout->new_scss_compiler();

		if ( file_exists( $scss_path ) ) {
			$head .=
				'<style>' .
				'.fca_eoi_form p { width: auto; }' . $scss->compile(
					sprintf( '$ltr: %s;', is_rtl() ? 'false' : 'true' ) .
					'#fca_eoi_form_' . $form_id . '{' .
						'input{ max-width: 9999px; }' .
						file_get_contents( $scss_path ) .
					'}'
				) .
				'</style>';
		}

		// Add per form CSS
		if ( ! EasyOptInsLayout::uses_new_css() ) {
			$head .= '<style>.fca_eoi_form{ margin: auto; }</style>';
		}

		$css_for_scss = '';
		if ( ! empty( $fca_eoi_meta[ $layout_id ] ) ) {
			$head .= '<style>';
			$css_for_scss .= "#fca_eoi_form_$form_id {";
			foreach ( $fca_eoi_meta[ $layout_id ] as $selector => $declarations ) {
				$css_for_scss .= "$selector{";
				foreach ( $declarations as $property => $value ) {
					if ( strlen( $value ) ) {
						$css_for_scss .= "$property:$value !important;";
					}
				}
				$css_for_scss .= '}';
			}
			$css_for_scss .= '}';
			$head .= $scss->compile( $css_for_scss ) . '</style>';
		}

		if ( $layout->layout_type != 'lightbox' ) {
			$head .= '<script>' . EasyOptInsActivity::get_instance()->get_tracking_code( $form_id ) . '</script>';
		}

		$this->prerequisites[ $form_id ] = $head;
		return $head;
	}

	/**
	 * @param int $form_id
	 *
	 * @return string
	 */
	private function get_prerequisites_for_form( $form_id ) {
		$prerequisites = $this->add_prerequisites_for_form( $form_id );
		$this->prerequisites[ $form_id ] = '';

		if ( ! empty( $prerequisites ) ) {
			$this->enqueue_assets();
			return $prerequisites;
		}

		return '';
	}

	public function button_head() {
		?>

		<style>
			#fca-eoi-media-button {
				background: url(<?php echo $this->settings['plugin_url'] . '/icon.png' ?>) 0 -1px no-repeat;
				background-size: 16px 16px;
			}
		</style>

		<?php
	}

	public function button() {
		$button_title = __( 'Optin Cat' );

		if ( version_compare( $GLOBALS['wp_version'], '3.5', '<' ) ) {
			echo '<a href="#TB_inline?width=640&inlineId=fca-eoi-shortcode-thickbox" class="thickbox" title="' . $button_title . '">' . $button_title . '</a>';
		} else {
			$img = '<span class="wp-media-buttons-icon" id="fca-eoi-media-button"></span>';
			echo '<a href="#TB_inline?width=640&inlineId=fca-eoi-shortcode-thickbox" class="thickbox button" title="' . $button_title . '" style="padding-left: .4em;">' . $img . $button_title . '</a>';
		}
	}

	public function button_footer() {
		$options = array();

		foreach ( get_posts( array( 'post_type' => 'easy-opt-ins', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $post ) {
			$form_id = $post->ID;
			$layout = get_post_meta( $form_id, 'fca_eoi_layout', true );

			if ( ! empty( $layout ) && strpos( $layout, 'postbox_' ) === 0 ) {
				$options[ $form_id ] = empty( $post->post_title ) ? '(no title)' : $post->post_title;
			}
		}

		?>

		<script type="text/javascript">
			jQuery( function( $ ) {
				$( '#fca-eoi-shortcode-insert' ).on( 'click', function() {
					var id = $( '#fca-eoi-shortcode' ).val();

					if ( '' === id ) {
						alert( <?php echo json_encode( __( 'You must choose a form' ) ) ?> );
						return;
					}

					window.send_to_editor( '[<?php echo $this->settings[ 'shortcode' ] ?> id="' + id + '"]' );
				} );
			} );
		</script>
		<div id="fca-eoi-shortcode-thickbox" style="display: none;">
			<div class="wrap" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
				<p><?php _e('Use the form below to insert an Optin Cat shortcode .' ) ?></p>
				<div>
					<select id="fca-eoi-shortcode">
						<option value=""><?php _e( 'Please select...' ) ?></option>
						<?php foreach ( $options as $form_id => $title ) { ?>
							<option value="<?php echo (int) $form_id ?>"><?php echo esc_html( $title ) ?></option>
						<?php } ?>
					</select>
				</div>
				<p class="submit">
					<input type="button" id="fca-eoi-shortcode-insert" class="button-primary" value="<?php _e( 'Insert' ) ?>">
					<a id="fca-eoi-shortcode-cancel" class="button-secondary" onclick="tb_remove();" title="<?php _e( 'Cancel' ) ?>"><?php _e( 'Cancel' ) ?></a>
				</p>
			</div>
		</div>

		<?php
	}

	public function enqueue_assets() {

		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;

		$protocol = is_ssl() ? 'https' : 'http';

		// Get lightboxes
		$lightboxes = get_posts( array(
			'post_type' => 'easy-opt-ins',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'meta_key' => 'fca_eoi_layout',
			'meta_value' => 'lightbox_',
			'meta_compare' => 'like',
		) );

		// Get postboxes
		$postboxes = get_posts( array(
			'post_type' => 'easy-opt-ins',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'meta_key' => 'fca_eoi_layout',
			'meta_value' => 'postbox_',
			'meta_compare' => 'like',
		) );

		// Get postboxes
		$widgets = get_posts( array(
			'post_type' => 'easy-opt-ins',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'meta_key' => 'fca_eoi_layout',
			'meta_value' => 'layout_',
			'meta_compare' => 'like',
		) );

		// Exit function if not optin form exist
		if ( ! $lightboxes && ! $postboxes && ! $widgets ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		wp_enqueue_style( 'fca_eoi', $this->settings[ 'plugin_url' ].'/assets/style' . ( EasyOptInsLayout::uses_new_css() ? '-new' : '' ) . '.css' );
		wp_enqueue_script( 'fca_eoi', $this->settings[ 'plugin_url' ].'/assets/script.js' );

		wp_enqueue_style( 'fontawesome', $protocol . '://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.1.0/css/font-awesome.min.css' );

		wp_enqueue_script( 'tooltipster', $this->settings[ 'plugin_url' ] . '/assets/vendor/tooltipster/jquery.tooltipster.min.js' );
		wp_enqueue_style( 'tooltipster', $this->settings[ 'plugin_url' ] . '/assets/vendor/tooltipster/tooltipster.min.css' );
		wp_localize_script(
			'fca_eoi'
			, 'fca_eoi'
			, array_merge( $this->settings['error_text'], array(
				'ajax_url' => admin_url( 'admin-ajax.php' )
			) )
		);
	}

	public function shortcode_content( $atts ) {

		$form_id = $atts['id'];

		/**
		 * Check that we have a valid post ID
		 */
		if( empty ( $form_id ) ) {
			return 'Missing form ID';
		}
		if( ! $post = get_post( $form_id ) ) {
			return 'Wrong form ID';
		}

		$fca_eoi_meta = get_post_meta( $form_id, 'fca_eoi', true );
		if( ! $fca_eoi_meta ) {
			return 'Form doesn\'t exist';
		}

		// Get template
		$layout_id = $fca_eoi_meta[ 'layout' ];

		$layout         = new EasyOptInsLayout( $layout_id );
		$layout_type    = $layout->layout_type;
		$html_path      = $layout->path_to_resource( 'layout', 'html' );
		$html_wrap_path = $layout->path_to_html_wrapper();

		if ( ! file_exists( $html_path ) ) {
			return '';
		}

		if ( EasyOptInsLayout::uses_new_css() ) {
			$template = str_replace(
				'{{{layout}}}',
				file_get_contents( $html_path ),
				file_get_contents( $html_wrap_path )
			);
		} else {
			$template = file_get_contents( $html_path );
		}

		$form_wrapper = '';
		$form_wrapper_end = '';

		if ( EasyOptInsLayout::uses_new_css() && $layout->layout_type != 'lightbox' ) {
			$form_wrapper =
				'<div class="' .
					'fca_eoi_form_wrapper ' .
					$layout->layout_class . '_wrapper ' .
					'fca_eoi_layout_' . $layout->layout_number . '_wrapper' .
				'">';
			$form_wrapper_end = '</div>';
		}

		// Fill template with our formatting stuff
		$template = str_replace(
			array(
				'<form>',
				'{{{description_copy}}}',
				'{{{headline_copy}}}',
				'{{{name_field}}}',
				'{{{email_field}}}',
				'{{{submit_button}}}',
				'{{{privacy_copy}}}',
				'{{{fatcatapps_link}}}',
				'</form>',
			),
			array(
				sprintf(
					$form_wrapper .
					'<div id="fca_eoi_form_%s" class="fca_eoi_form_content">' .
						'<form method="post" action="" class="fca_eoi_form %s %s" ' .
							'data-fca_eoi_list_id="%s" data-fca_eoi_thank_you_page="%s" novalidate' .
						'>' .
							'<input type="hidden" name="fca_eoi_form_id" value="%s" />'
					, $form_id
					, EasyOptInsLayout::uses_new_css()
						? 'fca_eoi_layout_' . $layout->layout_number
						: 'fca_eoi_' . $layout_type
					, EasyOptInsLayout::uses_new_css()
						? $layout->layout_class
						: 'fca_eoi_' . $layout_id
					, K::get_var( 'list_id', $fca_eoi_meta )
					, get_permalink( K::get_var( 'thank_you_page', $fca_eoi_meta ) )
						? get_permalink( K::get_var( 'thank_you_page', $fca_eoi_meta ) )
						: ''
					, $post->ID
				),
				'<div>{{{description_copy}}}</div>',
				EasyOptInsLayout::uses_new_css()
					? '<div>{{{headline_copy}}}</div>'
					: '<span>{{{headline_copy}}}</span>',
				EasyOptInsLayout::uses_new_css()
					? '<input class="fca_eoi_form_input_element" type="text" name="name" placeholder="{{{name_placeholder}}}">'
					: '<input type="text" name="name" placeholder="{{{name_placeholder}}}" />',
				EasyOptInsLayout::uses_new_css()
					? '<input class="fca_eoi_form_input_element" type="email" name="email" placeholder="{{{email_placeholder}}}">'
					: '<input type="email" name="email" placeholder="{{{email_placeholder}}}" 	/>',
				EasyOptInsLayout::uses_new_css()
					? '<input class="fca_eoi_form_button_element" type="submit" value="{{{button_copy}}}">'
					: '<input type="submit" value="{{{button_copy}}}" />',
				EasyOptInsLayout::uses_new_css()
					? '<div>{{{privacy_copy}}}</div>'
					: '<span >{{{privacy_copy}}}</span>',
				EasyOptInsLayout::uses_new_css()
					? '{{#show_fatcatapps_link}}<div class="fca_eoi_layout_fatcatapps_link_wrapper fca_eoi_form_text_element"><a href="http://fatcatapps.com/eoi" target="_blank">Powered by Optin Cat</a></div>{{/show_fatcatapps_link}}'
					: '{{#show_fatcatapps_link}}<p class="fca_eoi_' . $layout_id . '_fatcatapps_link_wrapper"><a href="http://fatcatapps.com/eoi" target="_blank">Powered by Optin Cat</a></p>{{/show_fatcatapps_link}}',
				'<input type="hidden" name="id" value="' . $form_id . '"><input type="hidden" name="fca_eoi" value="1"></form></div>' . $form_wrapper_end,
			),
			$template
		);

		$mustache = new Mustache_Engine;
		$output = $mustache->render(
			$template,
			array(
				'headline_copy' => $fca_eoi_meta[ 'headline_copy' ],
				'description_copy' => $fca_eoi_meta[ 'description_copy' ],
				'privacy_copy' => $fca_eoi_meta[ 'privacy_copy' ],
				'name_placeholder' => $fca_eoi_meta[ 'name_placeholder' ],
				'email_placeholder' => $fca_eoi_meta[ 'email_placeholder' ],
				'button_copy' => $fca_eoi_meta[ 'button_copy' ],
				'show_name_field' => K::get_var( 'show_name_field', $fca_eoi_meta ),
				'show_fatcatapps_link' => K::get_var( 'show_fatcatapps_link', $fca_eoi_meta ),
			)
		);

		// add the fca_eoi_alter_form             
		$output = apply_filters( 'fca_eoi_alter_form'
			, $output
			, $fca_eoi_meta
		);

		$error_text = '';

		foreach ( $fca_eoi_meta as $key => $value ) {
			if ( strpos( $key, 'error_text_' ) === 0 ) {
				$text = substr( $key, 11 );
				$error_text .= 'fca_eoi[' . json_encode($text) . '] = ' . json_encode($value) . ';';
			}
		}

		if ( ! empty( $error_text ) ) {
			$output .=
				'<script>' .
					'if ( typeof fca_eoi === "undefined" ) {' .
						'fca_eoi = {};' .
					'}' .
					$error_text .
				'</script>';
		}

		// Return form with debugging information if applicable
		return $this->get_prerequisites_for_form( $form_id ) . $output . ( FCA_EOI_DEBUG ? @d( $fca_eoi_meta, $template ) : '' );
	}
}
