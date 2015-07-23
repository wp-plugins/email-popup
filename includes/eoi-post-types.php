<?php

class EasyOptInsPostTypes {

	public $settings;

	private $activity_day_interval = array(
		'form_list' => null,
		'dashboard_widget' => 30
	);

	private $targeting_cat_path = 'assets/vendor/targeting-cat/TargetingCat-OptinCat-1.1.min.js';

	public function __construct( $settings ) {

		$this->settings = $settings;

		$providers_available = array_keys( $this->settings[ 'providers' ] );

		// Register custom post type
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_filter( 'manage_easy-opt-ins_posts_columns', array( $this, 'add_new_columns' ) );
		add_action( 'manage_easy-opt-ins_posts_custom_column', array( $this, 'set_column_data' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		// Reset action
		add_action( 'admin_post_fca_eoi_reset_stats', array( $this, 'reset_stats' ) );

		// Dashboard widget
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ) );

		// Add provider object and post settings to settings
		add_action( 'init', array( $this, 'more_settings' ) );

		// Handle AJAX submission (unused)
		add_action( 'init', array( $this, 'handle_submission' ) );

		// Initiate action hooks
		add_action( 'save_post', array( $this, 'save_meta_box_content' ), 1, 2 );

		// Live preview
		add_filter( 'the_content', array( $this, 'live_preview' ) );

		// Scripts and styles
		add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_filter( 'admin_enqueue_scripts', array( $this, 'disable_autosave' ) );

		add_action( 'admin_head', array( $this, 'hide_minor_publishing' ) );

		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );

		add_filter( 'wp_insert_post_data', array( $this, 'force_published' ) );

		// add_action( 'admin_footer', array( $this, 'disable_metabox_toggle' ) );

		add_action( 'wp_ajax_fca_eoi_subscribe', array( $this, 'ajax_subscribe' ) );

		add_filter( 'get_user_option_screen_layout_easy-opt-ins', array( $this, 'force_one_column' ) );

		add_filter( 'get_user_option_meta-box-order_easy-opt-ins', array( $this, 'order_columns' ) );

		add_filter( 'gettext', array( $this, 'override_text' ) );

		add_filter( 'bulk_actions-edit-easy-opt-ins', array( $this, 'disable_bulk_edit' ) );

		add_filter( 'post_row_actions', array( $this, 'remove_quick_edit' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_filter( 'enter_title_here', array( $this, 'change_default_title' ) );

		add_filter( 'init', array( $this, 'bind_content_filter' ), 10 );

		add_filter( 'init', array( $this, 'parse_tc_condition_request' ), 1 );

		add_filter( 'wp_footer', array( $this, 'show_lightbox' ) );

		foreach ( $providers_available as $provider ) {
			add_action( 'wp_ajax_fca_eoi_' . $provider . '_get_lists', $provider . '_ajax_get_lists' );
		}

		// Hook provder callback functions
		foreach ( $providers_available as $provider ) {
			add_filter( 'fca_eoi_alter_admin_notices', $provider . '_admin_notices', 10, 1 );
		} 

		// Handle licensing
		if( count( $providers_available ) > 1 ) {
			require $this->settings[ 'plugin_dir' ] . 'includes/licensing.php';
			new  EasyOptInsLicense( $this->settings );
		}
	}

	public function more_settings() {

		$providers_available = array_keys( $this->settings[ 'providers' ] );

		// Get the post id 
		if( is_admin() ) {
			$form_id = K::get_var( 'post', $_GET );
		} else {
			$protocol = is_ssl() ? 'https' : 'http';
			$form_id = K::get_var( 'fca_eoi_form_id', $_POST );
		}

		// Save form meta into object settings
		$this->settings[ 'eoi_form_meta' ] = empty ( $form_id )
			? false
			: get_post_meta( $form_id, 'fca_eoi', true )
		;

		// Add last 3 posts and there meta
		// We need to prepare the previous posts
		$fca_eoi_last_3_forms = array();
		foreach (query_posts( 'posts_per_page=3&post_type=easy-opt-ins' ) as $i => $f ) {
			$fca_eoi_last_3_forms[ $i ][ 'post' ] = $f;
			$fca_eoi_last_3_forms[ $i ][ 'fca_eoi' ] = get_post_meta( $f->ID, 'fca_eoi', true );
		}
		// reset query after the loop
		wp_reset_query();
		$this->settings[ 'fca_eoi_last_3_forms' ] = $fca_eoi_last_3_forms;
	}

	public function register_custom_post_type() {

		$labels = array(
			'name' => __('Optin Forms') ,
			'singular_name' => __('Optin Form') ,
			'add_new' => __('Add New') ,
			'add_new_item' => __('Add New Optin Form') ,
			'edit_item' => __('Edit Optin Form') ,
			'new_item' => __('New Optin Form') ,
			'all_items' => __('All Optin Forms') ,
			'view_item' => __('View Optin Form') ,
			'search_items' => __('Search Optin Form') ,
			'not_found' => __('No Optin Form Found') ,
			'not_found_in_trash' => __('No Optin Form Found in Trash') ,
			'parent_item_colon' => '',
			'menu_name' => __('Optin Cat')
		);
		$args = array(
			'menu_icon' => "{$this->settings['plugin_url']}/icon.png",
			'labels' => $labels,
			'public' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'rewrite' => array(
				'slug' => 'easy-opt-ins',
			) ,
			'capability_type' => 'post',
			'has_archive' => false,
			'hierarchical' => false,
			'menu_position' => 105,
			'supports' => array(
				'title',
			) ,
			'register_meta_box_cb' => array(
				$this,
				'add_meta_boxes'
			)
		);
		register_post_type('easy-opt-ins', $args);
	}

	private function enqueue_activity_style() {
		wp_enqueue_style( 'admin-cpt-easy-opt-ins-activity', $this->settings['plugin_url'] . '/assets/admin/cpt-easy-opt-ins-activity.css' );
	}

	public function add_new_columns( $columns ) {
		$new_columns = array();

		if ( ! empty( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		if ( ! empty( $columns['title'] ) ) {
			$new_columns['title'] = $columns['title'];
		}

		$this->enqueue_activity_style();
		$activity = EasyOptInsActivity::get_instance();

		$period =
			'<span class="fca_eoi_activity_period">(' .
				$activity->get_text( 'period', null, array( $this->activity_day_interval['form_list'] ) ) .
			')</span>';

		foreach ( array( 'impressions', 'conversions', 'conversion_rate' ) as $activity_type ) {
			$new_columns[ $activity_type ] = esc_html( $activity->get_text( $activity_type, 'form' ) ) . '<br>' . $period;
		}

		return $new_columns;
	}

	public function set_column_data( $column_name, $form_id ) {
		$activity = EasyOptInsActivity::get_instance();

		$stats = $activity->get_form_stats( $this->activity_day_interval['form_list'] );
		$value = 0;

		if ( ! empty( $stats[ $column_name ][ $form_id ] ) ) {
			$value = $stats[ $column_name ][ $form_id ];
		}

		echo $activity->format_column_text( $column_name, $value );
	}

	public function post_row_actions( $actions, $post ) {
		if ( $post->post_type == 'easy-opt-ins' ) {
			$action = 'fca_eoi_reset_stats';
			$title  = __( 'Reset stats for this item' );
			$label  = __( 'Reset Stats' );

			$url = add_query_arg( 'action', $action, admin_url( 'admin-post.php?post=' . $post->ID ) );
			$url = wp_nonce_url( $url );

			$actions[$action] = $this->confirm_tag(
				'<a href="' . $url . '" title="' . $title . '">' . $label . '</a>',
				__( 'Are you sure?' ),
				__( 'Do you really want to reset this Optin Form\'s stats? This action cannot be undone.' )
			);
		}
		return $actions;
	}

	public function reset_stats() {
		if ( wp_verify_nonce( $_REQUEST['_wpnonce'] ) ) {
			EasyOptInsActivity::get_instance()->reset_stats( (int) $_REQUEST['post'] );
			wp_redirect( wp_get_referer() );
		}
	}

	private function confirm_tag( $tag, $title, $message ) {
		return preg_replace(
			'/>/',
			' onclick="return confirm(' .
				esc_html( '"' . $title . '\n\n' . $message . '"' ) .
			')">',
			$tag, 1 );
	}

	public function dashboard_setup() {
		add_meta_box(
			'fca_eoi_dashboard_widget',
			'Optin Cat Summary',
			array( $this, 'add_dashboard_widget' ),
			'dashboard',
			'normal',
			'high'
		);
	}

	public function add_dashboard_widget() {
		wp_enqueue_script( 'd3_js', $this->settings['plugin_url'] . '/assets/vendor/nvd3/d3.min.js' );
		wp_enqueue_script( 'nvd3_js', $this->settings['plugin_url'] . '/assets/vendor/nvd3/nv.d3.min.js' );
		wp_enqueue_style( 'nvd3_css', $this->settings['plugin_url'] . '/assets/vendor/nvd3/nv.d3.min.css' );
		$this->enqueue_activity_style();

		$day_interval = $this->activity_day_interval['dashboard_widget'];
		$activity = EasyOptInsActivity::get_instance();
		$stats = $activity->get_daily_stats( $day_interval );

		$date_labels = array();
		foreach ( array_keys( $stats['impressions'] ) as $date ) {
			$date_labels[] = strftime( '%e %b', strtotime( $date ) );
		}

		$colors = array(
			'impressions' => '#5b90bf',
			'conversions' => '#bf616a'
		);

		?>
		<div class="fca_eoi_activity_chart_title_container">
			<div class="fca_eoi_activity_chart_legend">
				<?php foreach ( array( 'impressions', 'conversions' ) as $activity_type ): ?>
					<div class="fca_eoi_activity_chart_legend_item">
						<div class="fca_eoi_activity_chart_legend_sample" style="background-color: <?php echo $colors[ $activity_type ] ?>;"></div>
						<div class="fca_eoi_activity_chart_legend_text">
							<?php echo esc_html( $activity->get_text( $activity_type, 'total' ) ) ?>
						</div>
					</div>
				<?php endforeach ?>
			</div>
			<div class="fca_eoi_activity_chart_period">
				<?php echo esc_html( $activity->get_text( 'period', null, array( $day_interval ) ) ) ?>
				-
				<a href="<?php echo admin_url( 'edit.php?post_type=easy-opt-ins' ) ?>"><?php echo __( 'View All Data' ) ?></a>
			</div>
		</div>
		<div class="fca_eoi_activity_chart" id="fca_eoi_activity_chart"></div>
		<div class="fca_eoi_activity_chart_stat">
			<?php foreach ( array( 'impressions', 'conversions', 'conversion_rate' ) as $activity_type ): ?>
				<div class="fca_eoi_activity_chart_stat_item">
					<div class="fca_eoi_activity_chart_stat_value">
						<?php echo $activity->format_column_text( $activity_type, $stats['totals'][ $activity_type ] ) ?>
					</div>
					<div class="fca_eoi_activity_chart_stat_title">
						<?php echo esc_html( $activity->get_text( $activity_type, 'total' ) ) ?>
					</div>
				</div>
			<?php endforeach ?>
		</div>
		<script>
			jQuery( function() {
				var impressions = <?php echo json_encode( array_values( $stats['impressions'] ) ) ?>;
				var conversions = <?php echo json_encode( array_values( $stats['conversions'] ) ) ?>;
				var dates = <?php echo json_encode( $date_labels ) ?>;

				var chart = nv.models.lineChart().options({
					duration: 0,
					transitionDuration: 0,
					useInteractiveGuideline: true,
					isArea: true,
					showLegend: false,
					margin: { top: 10, right: 20, bottom: 30, left: 40 }
				} );

				chart.xAxis.tickFormat( function( index ) { return dates[ index ]; } );
				chart.yAxis.tickFormat( d3.format( 'd' ) );
				chart.forceY( [ 0, d3.max(impressions) || 1 ] );

				var valuesToPoint = function( value, index ) {
					return { x: index, y: value };
				};

				d3.select( '#fca_eoi_activity_chart' ).append( 'svg' ).datum( [
					{ color: '<?php echo $colors['impressions'] ?>', key: 'Impressions', values: impressions.map(valuesToPoint) },
					{ color: '<?php echo $colors['conversions'] ?>', key: 'Conversions', values: conversions.map(valuesToPoint) }
				] ).call( chart );

				nv.utils.windowResize( chart.update );
			} );
		</script>
	<?php
	}

	public function add_meta_boxes() {
		add_meta_box(
			'fca_eoi_meta_box_nav',
			__( 'Navigation' ),
			array( &$this, 'meta_box_content_nav' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		add_meta_box(
			'fca_eoi_meta_box_setup',
			__( 'Setup' ),
			array( &$this, 'meta_box_content_setup' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		add_meta_box(
			'fca_eoi_meta_box_build',
			__( 'Form Builder' ),
			array( &$this, 'meta_box_content_build' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		add_meta_box(
			'fca_eoi_meta_box_provider',
			__( 'Email Marketing Provider Integration' ),
			array( &$this, 'meta_box_content_provider' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		add_meta_box(
			'fca_eoi_meta_box_publish',
			__( 'Publication' ),
			array( &$this, 'meta_box_content_publish' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		add_meta_box(
			'fca_eoi_meta_box_thanks',
			__( 'Thank You Page' ),
			array( &$this, 'meta_box_content_thanks' ),
			'easy-opt-ins',
			'side',
			'high'
		);
		if ( has_action( 'fca_eoi_powerups' ) ) {
			add_meta_box(
				'fca_eoi_meta_box_powerups',
				__( 'Power Ups' ),
				array( &$this, 'meta_box_content_powerups' ),
				'easy-opt-ins',
				'side',
				'high'
			);
		}
		if ( FCA_EOI_DEBUG ) {
			add_meta_box(
				'fca_eoi_meta_box_debug',
				'Debug', 
				array( &$this, 'meta_box_content_debug' ),
				'easy-opt-ins',
				'side',
				'high'
			);
		}
	}

	public function meta_box_content_nav() {
		?>
		<h2 class="nav-tab-wrapper" style="padding: 0 10px">
			<a href="#fca_eoi_meta_box_setup" class="nav-tab nav-tab-active">Choose</a>
			<a href="#fca_eoi_meta_box_build" class="nav-tab ">Build</a>
			<?php if( FCA_EOI_DEBUG ) : ?>
				<a href="#fca_eoi_meta_box_debug" class="nav-tab ">Debug</a>
			<?php endif; ?>
		</h2>
		<?php
	}

	public function meta_box_content_setup() {

		global $post;

		$layouts_types_labels = array(
			'widget' => 'Sidebar Widgets',
			'postbox' => 'Post Boxes',
			'lightbox' => 'Popups',
		);

		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );

		echo '<h2>' . __( 'Layouts' ) . '</h2>';
		echo '<script id="fca_eoi_texts" type="application/json">{ "headline_copy": "Headline Copy", "description_copy": "Description Copy", "name_placeholder": "Name Placeholder", "email_placeholder": "Email Placeholder", "button_copy": "Button Copy", "privacy_copy": "Privacy Copy" }</script>';
			
		// Build the layouts array
		$layouts = $layouts_types = $layouts_types_found = array();
		foreach ( glob( $this->settings[ 'plugin_dir' ] . 'layouts/*', GLOB_ONLYDIR ) as $v) {
			$layouts_types_found[] = basename( $v );
		}

		$layouts_types_accepted = array_keys( $layouts_types_labels );
		foreach ( $layouts_types_accepted as $layout_type ) {
			if ( in_array( $layout_type, $layouts_types_found ) ) {
				$layouts_types[] = $layout_type;
			}
		}

		// Layouts types mini-tabs
		echo '<ul class="category-tabs" id="layouts_types_tabs">';
		foreach ( $layouts_types as $layout_type ) {
			K::wrap(
				$layouts_types_labels[ $layout_type ]
				, array(
					'href' => '#'. 'layouts_type_' . $layout_type,
				)
				, array(
					'html_before' => '<li' . ( 'widget' === $layout_type ? ' class="tabs"' : '' ) . ' >',
					'html_after' => '</li> ',
					'in' => 'a',
				)
			);
		}
		echo '</ul>';

		// Layout types
		foreach ( $layouts_types as $layout_type ) {
			echo '<div id="layouts_type_' . $layout_type . '">';
			foreach ( glob( $this->settings[ 'plugin_dir' ] . "layouts/$layout_type/*", GLOB_ONLYDIR ) as $layout_path ) {
				// Grab layout details
				$layout_id = basename( $layout_path );

				$layout_helper   = new EasyOptInsLayout( $layout_id );
				$layout_type     = $layout_helper->layout_type;
				$php_path        = $layout_helper->path_to_resource( 'layout', 'php' );
				$html_path       = $layout_helper->path_to_resource( 'layout', 'html' );
				$html_wrap_path  = $layout_helper->path_to_html_wrapper();
				$scss_path       = $layout_helper->path_to_resource( 'layout', 'scss' );
				$screenshot_path = $layout_helper->path_to_resource( 'screenshot', 'png' );
				$screenshot_url  = $layout_helper->url_to_resource( 'screenshot', 'png' );

				include $php_path;
				if ( EasyOptInsLayout::uses_new_css() ) {
					$layout[ 'css' ] = file_exists( $scss_path )
						? '#fca_eoi_preview_form_container {' .
						    file_get_contents( $scss_path ) .
						    '.fca_eoi_layout_popup_close {' .
						      'display: none;' .
						    '}' .
						  '}'
						: ''
					;
				} else {
					$layout[ 'css' ] = file_exists( $scss_path )
						? file_get_contents( $scss_path)
						: ''
					;
				}

				// Add $ltr SASS variable
				$layout[ 'css' ] = sprintf( '$ltr: %s;', is_rtl() ? 'false' : 'true' )
					. $layout[ 'css' ]
				;
				$scss = $layout_helper->new_scss_compiler();
				$layout[ 'css' ] = $scss->compile( $layout[ 'css' ] );
				if ( EasyOptInsLayout::uses_new_css() ) {
					$layout[ 'template' ] = str_replace(
						'{{{layout}}}',
						file_get_contents( $html_path ),
						file_get_contents( $html_wrap_path )
					);
				} else {
					$layout[ 'template' ] = file_get_contents( $html_path );
				}
				$layout[ 'template' ] = str_replace(
					array(
						'<form',
						'/form',
						'{{{description_copy}}}',
						'{{{headline_copy}}}',
						'{{{name_field}}}',
						'{{{email_field}}}',
						'{{{submit_button}}}',
						'{{{privacy_copy}}}',
						'{{{fatcatapps_link}}}'
					)
					, array(
						EasyOptInsLayout::uses_new_css()
							? '<div id="fca_eoi_preview_form_container">' .
							    '<div id="fca_eoi_preview_form_wrapper">' .
							      '<div id="fca_eoi_preview_form" class="' .
							        'fca_eoi_layout_' . $layout_helper->layout_number . ' ' .
							        $layout_helper->layout_class .
							      '"'
							: '<div id="fca_eoi_preview_form" class="fca_eoi_' . $layout_id . '"',
						EasyOptInsLayout::uses_new_css()
							? '/div></div></div'
							: '/div',
						'<div data-fca-eoi-fieldset-id ="description">{{{description_copy}}}</div>',
						EasyOptInsLayout::uses_new_css()
							? '<div data-fca-eoi-fieldset-id ="headline" id="fca_eoi_preview_headline_copy">{{{headline_copy}}}</div>'
							: '<span data-fca-eoi-fieldset-id ="headline" id="fca_eoi_preview_headline_copy">{{{headline_copy}}}</span>',
						EasyOptInsLayout::uses_new_css()
							? '<input class="fca_eoi_form_input_element" type="text" placeholder="{{{name_placeholder}}}">'
							: '<input data-fca-eoi-fieldset-id ="name_field" type="text" placeholder="{{{name_placeholder}}}" />',
						EasyOptInsLayout::uses_new_css()
							? '<input class="fca_eoi_form_input_element" type="email" placeholder="{{{email_placeholder}}}">'
							: '<input data-fca-eoi-fieldset-id ="email_field" type="email" placeholder="{{{email_placeholder}}}" />',
						EasyOptInsLayout::uses_new_css()
							? '<input class="fca_eoi_form_button_element" data-fca-eoi-fieldset-id ="button" type="submit" value="{{{button_copy}}}">'
							: '<input data-fca-eoi-fieldset-id ="button" type="submit" value="{{{button_copy}}}" />',
						EasyOptInsLayout::uses_new_css()
							? '<div data-fca-eoi-fieldset-id="privacy">{{{privacy_copy}}}</div>'
							: '<span data-fca-eoi-fieldset-id ="privacy">{{{privacy_copy}}}</span>',
						EasyOptInsLayout::uses_new_css()
							? '{{#show_fatcatapps_link}}<div class="fca_eoi_layout_fatcatapps_link_wrapper fca_eoi_form_text_element"><span data-fca-eoi-fieldset-id ="fatcatapps"><a href="#" onclick="javascript:return false">Powered by Optin Cat</a></span></div>{{/show_fatcatapps_link}}'
							: '{{#show_fatcatapps_link}}<p class="fca_eoi_' . $layout_id . '_fatcatapps_link_wrapper"><span data-fca-eoi-fieldset-id ="fatcatapps"><a href="#" onclick="javascript:return false">Powered by Optin Cat</a></span></p>{{/show_fatcatapps_link}}',
					)
					, $layout[ 'template' ]
				);
				$layout[ 'template' ] .= sprintf( '<style>%s</style>', $layout[ 'css' ] );
				$layouts[ $layout_id ] = $layout;
				// Output the layout image and hidden template
				$layout_output_tpl = '
					<div
						class="fca_eoi_layout has-tip"
						data-layout-id=":id" data-layout-type=":type"				
					>
						<img src=":src" />
						<h3>:name</h3>
						<script id="fca_eoi_tpl_:id" type="x-tmpl-mustache">:template</script>
						<script id="fca_eoi_editables_:id" type="application/json">:editables</script>
						<script id="fca_eoi_texts_:id" type="application/json">:texts</script>
					</div>
				';
				$layout_output = str_replace(
					array(
						':id',
						':type',
						':name',
						':src',
						':template',
						':editables',
						':texts',
					),
					array(
						$layout_id,
						$layout_type,
						$layout[ 'name' ],
						file_exists( $screenshot_path )
							? $screenshot_url
							: $this->settings[ 'plugin_url' ] . '/layouts/no-image.jpg'
						,
						$layout[ 'template' ],
						! empty( $layout[ 'editables' ] )
							? json_encode( $layout[ 'editables' ] )
							: 'null'
						,
						! empty( $layout[ 'texts' ] )
							? json_encode( $layout[ 'texts' ] )
							: 'null'
						,
					),
					$layout_output_tpl
				);

				// Add autocolors
				if( ! empty( $layout[ 'autocolors' ] ) ) {
					foreach ($layout[ 'autocolors' ] as $autocolor ) {
						$layout_output .= '
							<script>
								jQuery( document ).ready( function( $ ) { 
									
									var source = "[name*=\'' . $autocolor[ 'source' ] . '\']";
									var destination = "[name*=\'' . $autocolor[ 'destination' ] . '\']";

									$( destination ).closest( "p" ).hide();

									$( document ).on( "change", source, function() {
										var v = $(this).val();
										if( ! v ) {
											$( destination ).val( "" );
											return ;
										}

										var c = tinycolor( v );'
						;
						foreach ( $autocolor[ 'operations'] as $op => $val ) {
							$layout_output .= '
										c = c.' . $op. '( ' . 1*$val . ' );
										';
						}
						$layout_output .= '
										$( destination ).val( c.toString() );
										$( destination ).blur();
									} );
								} );
							</script>'
						;
					}
				}

				echo $layout_output;
			}
			echo '</div>';
		}
		echo '<br clear="all"/>';

		// Prepare layouts array (id->name) for K
		$layouts_array = array();
		foreach ( $layouts as $layout_id => $layout ) {
			$layouts_array[ $layout_id ] = $layout[ 'name' ];
		}

		// Print hidden select box, it will be controlled by images (UI)
		K::select(
			'fca_eoi[layout]',
			array(
				'class' => 'hidden',
				'id' => 'fca_eoi_layout_select',
			),
			array(
				'options' => $layouts_array,
				'selected' => K::get_var( 'layout', $fca_eoi ),
			)
		);

		// Prepare the properties fields templates
		$property_templates[ 'color' ] = K::input( '{{property_name}}',
			array(
				'class' => sprintf(
					"color { hash: true, caps: false, required: false, pickerPosition: '%s' }"
					, is_rtl() ? 'right' : 'left'
				),
				'value' => '{{property_value}}',
			),
			array(
				'format' => '<p class="clear"><label><span class="control-title">{{property_label}}</span><br />:input <a href="#transparent">None</a></label></p>',
				'nocolorpicker' => true,
				'return' => true,
			)
		);
		$property_templates[ 'icon' ] = K::input( '{{property_name}}',
			array(
				'data-value-unchecked' => '{{property_value_unchecked}}',
				'data-is-checked' => '{{property_is_checked}}',
				'type' => 'checkbox',
				'value' => '{{property_value}}',
			),
			array(
				'format' => '<p class="control-property-icon"><label class="fca_eoi_toggle">{{{icon}}} :input</label></p>',
				'return' => true,
			)
		);
		$property_templates[ 'font-size' ] = K::select( '{{property_name}}',
			array(
				'data-selected' => '{{selected}}',
			),
			array(
				'format' => '<p class="clear"><label><span class="control-title">{{property_label}}</span><br />:select</label></p>',
				'options' => array(
					'none' => '',
					'7px' => '7px',
					'8px' => '8px',
					'9px' => '9px',
					'10px' => '10px',
					'11px' => '11px',
					'12px' => '12px',
					'13px' => '13px',
					'14px' => '14px',
					'15px' => '15px',
					'16px' => '16px',
					'17px' => '17px',
					'18px' => '18px',
					'19px' => '19px',
					'20px' => '20px',
					'21px' => '21px',
					'22px' => '22px',
					'23px' => '23px',
					'24px' => '24px',
					'25px' => '25px',
					'26px' => '26px',
					'27px' => '27px',
					'28px' => '28px',
					'29px' => '29px',
					'30px' => '30px',
					'31px' => '31px',
					'32px' => '32px',
					'33px' => '33px',
					'34px' => '34px',
					'35px' => '35px',
					'36px' => '36px',
				),
				'selected' => 'none',
				'return' => true,
			)
		);

		// Print the proprties fields templates
		foreach ( $property_templates as $prop => $property_template) {
			echo '<script id="fca_eoi_property_' . $prop . '" type="x-tmpl-mustache">' . $property_template . '</script>';
		}

		// Print a copy of the current settings
		echo
			'<script id="fca_eoi_post_meta" type="application/json">'
			. ( $fca_eoi ? json_encode( $fca_eoi ) : '{}' )
			. '</script>'
		;
	}

	public function meta_box_content_provider() {

		global $post;
		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );
		$providers_available = array_keys( $this->settings[ 'providers' ] );
		$providers_options = array();
		$screen = get_current_screen();
		$fca_eoi_last_3_forms = $this->settings[ 'fca_eoi_last_3_forms' ];

		// @todo: remove
		// Hack for mailchimp upgrade
		$fca_eoi[ 'mailchimp_list_id' ] = K::get_var(
			'mailchimp_list_id'
			, $fca_eoi
			, K::get_var( 'list_id' , $fca_eoi )
		);
		if( K::get_var( 'list_id' , $fca_eoi ) ) {
			$fca_eoi[ 'provider' ] = 'mailchimp';
		}
		// End of hack
		// Hack for campaignmonitor upgrade
		$fca_eoi[ 'campaignmonitor_list_id' ] = K::get_var(
			'campaignmonitor_list_id'
			, $fca_eoi
			, K::get_var( 'list_id' , $fca_eoi )
		);
		if( strlen( K::get_var( 'campaignmonitor_list_id' , $fca_eoi ) ) == 32){
			$fca_eoi[ 'provider' ] = 'campaignmonitor';
		}
		// End of hack
        // Hack for getresponse upgrade
        $fca_eoi[ 'getresponse_list_id' ] = K::get_var(
            'getresponse_list_id'
            , $fca_eoi
            , K::get_var( 'list_id' , $fca_eoi )
        );
        if( K::get_var( 'list_id' , $fca_eoi ) ) {
            $fca_eoi[ 'provider' ] = 'getresponse';
        }
        // End of hack

        // Prepare providers options
		foreach ($this->settings[ 'providers' ] as $provider_id => $provider ) {
			$providers_options[ $provider_id ] = $provider[ 'info' ][ 'name' ];
		}

		// Provider choice if there are many providers
		if ( 1 < count( $providers_available) ) {

			$last_date = 0;
			$provider = 'mailchimp'; // use mailchimp by default
			foreach ( $fca_eoi_last_3_forms as $form ) {
				if ( ! empty( $form['fca_eoi'] ) ) {
					$post_date = strtotime( $form['post']->post_date );

					foreach ( array_keys( $form['fca_eoi'] ) as $key ) {
						$pos = strpos( $key, '_list_id' );
						if ( $pos !== false && $post_date > $last_date ) {
							$provider = substr( $key, 0, $pos );
							$last_date = $post_date;
						}
					}
				}
			}

			K::select( 'fca_eoi[provider]'
				, array( 
					'class' => 'select2',
					'style' => 'width: 27em;',
				)
				, array( 
					'format' => '<p><label>:select</label></p>',
					'options' => array( '' => 'Not set' ) + $providers_options,
					'selected' => K::get_var( 'provider', $fca_eoi, $provider ),
				)
			);
		}

		$providers_available = array_keys( $this->settings[ 'providers' ] );
		foreach ( $providers_available as $provider ) {
			call_user_func( $provider . '_integration', $this->settings );
		}
	}

	public function meta_box_content_publish() {

		global $post;
		$screen = get_current_screen();

		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );

		// Widgets
		K::wrap(
			sprintf(
				__( 'You can publish this optin box by going to <a href="%s" target="_blank">Appearance › Widgets</a>')
				, admin_url( 'widgets.php')
			)
			, array( 'id' => 'fca_eoi_publish_widget' )
			, array( 'in' => 'p' )
		);

		// Post boxes
		echo '<div id ="fca_eoi_publish_postbox">';
		K::wrap( __( 'Shortcode')
			, array( 'style' => 'padding-left: 0px; padding-right: 0px; ' )
			, array( 'in' => 'h3' )
		);
		K::wrap( __( "Copy and paste beneath shortcode anywhere on your site where you'd like this opt-in form to appear." )
			, null
			, array( 'in' => 'p' )
		);
		K::input( ''
			, array(
				'class' => 'regular-text autoselect',
				'readonly' => 'readonly',
				'value' => sprintf( '[%s id=%d]', $this->settings[ 'shortcode' ], $post->ID ),
			)
			, array( 'format' => '<p>:input</p>', )
		);
		K::wrap( __( 'Append to post or page')
			, array( 'style' => 'padding-left: 0px; padding-right: 0px; ' )
			, array( 'in' => 'h3' )
		);
		K::wrap( __( 'Automatically append this optin to the following posts, categories and/or pages.' )
			, null
			, array( 'in' => 'p' )
		);
		k_selector( 'fca_eoi[publish_postbox]', K::get_var( 'publish_postbox', $fca_eoi, array() ) );
		echo '</div>';

		// Lightboxes

		switch ( $this->settings['distribution'] ) {
		case 'free':
			$conditions_options = array(
				'time_on_page' => 'Time on page is at least (second)',
			);
			break;
		case 'premium':
			$conditions_options = array(
				'pageviews' => 'Number of pageviews during this visit at least',
				'scrolled_percent' => 'Scrolled down on current page at least (%)',
				'time_on_page' => 'Time on page is at least (second)',
				'include' => 'Only show popup on the following posts/pages',
				'exclude' => 'Never show popup on the following posts/pages',
				'exit_intervention' => 'User is about to close the page (Exit Intervention)'
			);
			break;
		}

		$fca_eoi[ 'publish_lightbox' ] = K::get_var( 'publish_lightbox', $fca_eoi, array() );
		echo '<div id ="fca_eoi_publish_lightbox">';

		if ( 'premium' === $this->settings[ 'distribution' ] ) {
			K::input( 'fca_eoi[publish_lightbox_mode]'
				, array(
					'type' => 'radio',
					'value' => 'two_step_optin',
					'checked' => 'two_step_optin' === K::get_var( 'publish_lightbox_mode', $fca_eoi ),
				)
				, array(
					'format' => '<p><label>:input Two-Step Optin (Trigger popup only when the visitor clicks on a call to action link)</label></p>',
				)
			);
		}

		K::input( 'fca_eoi[publish_lightbox_mode]'
			, array(
				'type' => 'radio',
				'value' => 'traditional_popup',
				'checked' => 'traditional_popup' === K::get_var( 'publish_lightbox_mode', $fca_eoi ),
			)
			, array(
				'format' => '<p><label>:input Traditional Popup (Trigger popup when the visitor is browsing your site)</label></p>',
			)
		);
		?>
		
		<hr />

		<?php // Condition always present ?>
		<div id="fca_eoi_publish_lightbox_mode_traditional_popup" class="hidden">
			<fieldset class="fca_lightbox_condition fca_lightbox_condition_permanent">
				<span class="fca_lightbox_condition_text"><?php _e('Show popup'); ?></span><?php
				K::select(
					'fca_eoi[publish_lightbox][show_every]'
					, array()
					, array(
						'options' => array(
							'never' => __( 'Never' ),
							'always' => __( 'On every pageview' ),
							'session' => __( 'Once per visit' ),
							'day' => __( 'Once per day' ),
							'month' => __( 'Once per month' ),
							'once' => __( 'Only once' ),
						),
						'selected' => K::get_var( 'show_every', $fca_eoi[ 'publish_lightbox' ] ),
					)
				);
				?>
				<p class="fca_eoi_help"><?php _e( 'Set how frequently to show the popup. (Never means the popup is deactivated.)' ); ?></p>
			</fieldset>
			<?php
			// Saved conditions
			$saved_conditions = array();
			$saved_conditions = K::get_var( 'conditions', $fca_eoi[ 'publish_lightbox' ], array() );

			// Add default if creating a new form
			if ( 'add' === $screen->action ) {
				$saved_conditions = array(
					'_'.time().'001' => array(
						'parameter' => 'pageviews',
						'value' => '2',
					),
					'_'.time().'002' => array(
						'parameter' => 'scrolled_percent',
						'value' => '30',
					),
					'_'.time().'003' => array(
						'parameter' => 'time_on_page',
						'value' => '30',
					),
				);
			}

			foreach ( $saved_conditions as $condition_id => $condition ) {
				// Skip conditions that are not available in version
				if ( ! array_key_exists( $condition[ 'parameter' ], $conditions_options ) ) {
					continue;
				}

				$select = K::select( "fca_eoi[publish_lightbox][conditions][$condition_id][parameter]"
					, array()
					, array(
						'selected' => $condition['parameter'],
						'options'  => $conditions_options,
						'return'   => 'true',
					)
				);

				if ( in_array( $condition['parameter'], array( 'include', 'exclude' ) ) ) {
					$input = k_selector( "fca_eoi[publish_lightbox][conditions][$condition_id][value]"
						, K::get_var( 'value', $condition, array() )
						, true
					);
				} else if ( $condition['parameter'] == 'exit_intervention' ) {
					$input = K::input( "fca_eoi[publish_lightbox][conditions][$condition_id][value]"
						, array(
							'type' => 'hidden',
							'value' => ''
						)
						, array(
							'return' => true
						)
					);
				} else {
					$input = K::input( "fca_eoi[publish_lightbox][conditions][$condition_id][value]"
						, array(
							'class' => 'medium-text',
							'type'  => 'text',
							'value' => $condition['value'],
						)
						, array(
							'return' => true,
						)
					);
				}

				$condition_HTML_inner = ''
					. $select
					. $input
					. ' '
					. '<span class="button fca_remove_lightbox_condition"><span style="vertical-align:text-bottom" class="dashicons dashicons-no"></span> Remove Rule</span>'
					. '<p class="fca_eoi_help"></p>'
				;
				echo '<div class="fca_eoi_and">- And -</div>';
				K::wrap( $condition_HTML_inner
					, array(
						'id' => 'fca_lightbox_condition' . $condition_id,
						'class' => 'fca_lightbox_condition',
						'style' => '
							padding: .5%;
							margin:.5% 0;
							box-shadow: 0 0 1px rgba(0, 0, 0, .2);
							width: 97%;
							background:#F7F7F7;
						',
					)
					, array(
						'in' => 'fieldset',
					)
				);
			}
			?>
		
			<?php /* Button to add conditions */ ?>		
			<p><span class="button" id="fca_add_lightbox_condition"><span style="vertical-align:text-bottom" class="dashicons dashicons-plus"></span> Add Rule</span></p>
		</div>

		<?php if ( 'premium' === $this->settings[ 'distribution' ] ) { ?>
			<div id="fca_eoi_publish_lightbox_mode_two_step_optin" class="hidden">
				<?php 
					K::input( 'fca_eoi[lightbox_cta_text]'
						, array(
							'value' => K::get_var( 'lightbox_cta_text', $fca_eoi )
								? K::get_var( 'lightbox_cta_text', $fca_eoi )
								: __( 'Free Download' )
							,
							'class' => 'regular-text',
						)
						, array(
							'format' => '<p><label>Call to action text :input</label></p>',
						)
					);
					K::input( 'fca_eoi[lightbox_cta_link]'
						, array(
							'readonly' => 'readonly',
							'value' => htmlspecialchars( sprintf( '<a href="#" data-optin-cat="%d">%s</a>'
								, $post->ID
								, __( 'Free Download' )
							) ),
							'class' => 'regular-text autoselect',
						)
						, array(
							'format' => '<p><label>Call to action link :input</label></p>',
						)
					);

					K::wrap( __( 'Post above link into the text editor window of your post/page/widget.' )
						, array( 'class' => 'description' )
						, array( 'in' => 'p' )
					);

				?>
			</div>
		<?php } ?>

		<script>
		jQuery( document ).ready( function( $ ) {

			<?php
				$lightbox_help = array(
					'scrolled_percent' => __( 'Only show this popup to visitors who have scrolled down at least X% on the current page. Someone who scrolls down is engaging with your content and therefore more likely to convert. (Keep in mind that the entire page length, including comments, is included in this calculation.)' ),
					'pageviews' => __( 'Only show this popup to visitors have viewed at least X pages in this visit. Someone who views multiple pages during a visit is very engaged and therefore more likely to convert.' ),
					'include' => __( 'Makes sure your popup will only be shown on the posts, pages or categories you select. This is great if you want to set up offers for specific categories or blog posts.' ),
					'exclude' => __( 'Makes sure your popup will never be shown on the posts, pages or categories you select. This is great if you want to avoid showing your popups on landing pages, checkout pages, order confirmation pages, etc.' ),
					'exit_intervention' => __( 'Only show this popup to visitors who are about to close the current page.' ),
					'time_on_page' => __( 'Only show this popup to visitors who have spent at least X seconds on the current page. The longer someone spends on your page, the more engaged he is.' ),
				);
			?>

			var lightbox_help = <?php echo json_encode( $lightbox_help ) ?>;

			// Condition template
			<?php if ( 'free' === $this->settings[ 'distribution' ] ) { ?>
				var f = ( '<fieldset id="fca_lightbox_condition_x_" class="fca_lightbox_condition"><select name="fca_eoi[publish_lightbox][conditions][_x_][parameter]"><option value="time_on_page" data-default="30">Time on page is at least (second)</option></select><input class="medium-text" type="text" value="" name="fca_eoi[publish_lightbox][conditions][_x_][value]"/> <span class="button fca_remove_lightbox_condition"><span class="dashicons dashicons-no"></span> Remove Rule</span><p class="fca_eoi_help"></p></fieldset>' );
			<?php } else if ( 'premium' === $this->settings[ 'distribution' ] ) { ?>
				var f = ( '<fieldset id="fca_lightbox_condition_x_" class="fca_lightbox_condition"><select name="fca_eoi[publish_lightbox][conditions][_x_][parameter]"><option value="pageviews" data-default="2">Number of pageviews during this visit at least</option><option value="scrolled_percent" data-default="30">Scrolled down on current page at least (%)</option><option value="time_on_page" data-default="30">Time on page is at least (second)</option><option value="include">Only show popup on the following posts/pages</option><option value="exclude">Never show popup on the following posts/pages</option><option value="exit_intervention">User is about to close the page (Exit Intervention)</option></select><input class="medium-text" type="text" value="" name="fca_eoi[publish_lightbox][conditions][_x_][value]"/> <span class="button fca_remove_lightbox_condition"><span class="dashicons dashicons-no"></span> Remove Rule</span><p class="fca_eoi_help"></p></fieldset>' );
			<?php } ?>

			<?php 
				$s = str_replace( array( "\n", '"' )
					, array( '', '\"' )
					, k_selector( 'fca_eoi[publish_lightbox][conditions][_x_][value]', array(), true )
				);
			?>

			var s = "<?php echo $s; ?>";

			var t = '<input  class="medium-text" type="text" name="fca_eoi[publish_lightbox][conditions][_x_][value]"/>';
			
			// Disables all but last condition, and use the right input field 
			$( document )
				.on( 'change'
					, "select[name^='fca_eoi[publish_lightbox][conditions]['][name$='][parameter]']"
					, function() {
						fix_conditions();
					}
				)
			;
			$("select[name^='fca_eoi[publish_lightbox][conditions]['][name$='][parameter]']:first").change()
			;
			function fix_conditions() {
				var $button = $( '#fca_add_lightbox_condition' );
				var $conditions;

				$conditions = $( 'select' )
					.filter( "[name^='fca_eoi[publish_lightbox][conditions][']" )
					.filter( "[name$='][parameter]']" )
				;
				// Prevent changing other conditions
				$conditions
					.not(':last')
					.prop( 'disabled', true )
				;
				// Allow changing last
				$conditions
					.last()
					.prop( 'disabled', false )
				;
				// Show hide plus button
				if ( $conditions.length == $(f).find('select:first option').length ) {
					$button.hide();
				} else {
					$button.show();
				}

				// Update conditions variable
				$conditions = $( 'select' )
					.filter( "[name^='fca_eoi[publish_lightbox][conditions][']" )
					.filter( "[name$='][parameter]']" )
				;

				/**
				 * Show posts selector VS input depending on option.
				 * Remove previously selected options.
				 * Update help text
				 * Use default when applicable (i.e when data-default is here)
				 * Remove overflow (condition fieldsets without possible options)
				 */
				$conditions.each( function( i ) {
					var $this = $( this );
					var $fieldset = $this.parent();
					var $parameter = $( '[name$="[parameter]"]', $fieldset );
					var $value = $( '[name$="[value]"],[name$="[value][]"]', $fieldset );
					var $input = $value;
					var name = $value.attr( 'name' ).replace(/\[\]/g, '');
					var is_textfield = $value.is( 'input' );
					var is_hidden = ( 'exit_intervention' === $parameter.val() );
					var use_textfield = ( 'include' !== $parameter.val() && 'exclude' !== $parameter.val() );
					var cpt, condition, _default;

					// Decide what element to use : input VS select
					if ( use_textfield && ! is_textfield ) {
						$( '.select2', $fieldset ).remove();
						$input = $( t ).attr( 'name', name );
						$parameter.after( $input );
					} else if ( ! use_textfield && is_textfield ) {
						$input = $( s ).attr( 'name', name + '[]' );
						$value.replaceWith( $input );
					}

					// Hide or show the input
					$input.attr( 'type', is_hidden ? 'hidden' : 'text' );

					// Remove previously selected condition parameters
					$conditions.not($this).find('option[value='+$this.val()+']').remove();

					// Update help text
					$( '.fca_eoi_help', $fieldset ).text( lightbox_help[ $parameter.val() ] );

					// Use default
					if ( _default = $( ':selected', $parameter).data( 'default' ) ) {
						$value.val( _default );
						$( 'option', $parameter ).data( 'default', '' );
					}

					// Remove overflow
					if ( ! $( 'option', $parameter ).length ) {
						$( '.fca_remove_lightbox_condition', $fieldset ).click();
					} 
				} );
				$('select.select2').select2();
			}

			$( document ).on( 'click', '.fca_remove_lightbox_condition', function( i ) {

				var $this = $( this );
				var $opt = $( 'select:first option:selected', $this.parent() )
					.clone()
					.removeAttr( 'selected' )
				;

				$this.parent().remove(); // remove condition row

				$('.fca_eoi_and').remove(); // removes "AND"
				$('.fca_lightbox_condition:gt(0)').before( '<div class="fca_eoi_and">- And -</div>' );

				$('select')
					.filter( "[name^='fca_eoi[publish_lightbox][conditions][']" )
					.filter( "[name$='][parameter]']" )
					.append( $opt )
				;

				fix_conditions();
			} );


			$( document ).on( 'click', '#fca_add_lightbox_condition', function() {

				var $this = $( this );
				var ts = Date.now();
				var $condition = $( f.replace( /_x_/g, '_' + ts ) );

				// Remove already used conditions
				$( 'option', $condition ).each( function() {
					var $this = $( this );
					var val = $this.val();
					var remove = $('select')
						.filter("[name^='fca_eoi[publish_lightbox][conditions][']")
						.filter("[name$='][parameter]']")
						.find('option:selected[value=' + val + ']')
						.length
					;
					
					if ( remove ) {
						$( this ).remove();
					}
				} );
				
				$this.parent().before( '<div class="fca_eoi_and">- And -</div>' );
				$this.parent().before( $condition );
				
				// Bind removing to minus sign
				$( '.fca_remove_lightbox_condition', '#fca_lightbox_condition_' + ts ).click( function() {
					$( this ).parent().remove();
				} );

				// Prevent changing previous conditions
				fix_conditions();
			} );

			// Enable disabled conditions fields to allow passing their value to the form action
			$( document ).on( 'submit', '#post', function() {
				$( 'select' )
					.filter( "[name^='fca_eoi[publish_lightbox][conditions][']" )
					.filter( "[name$='][parameter]']" )
					.prop( 'disabled', false )
				;
			} );
		} );

		</script>
		<?php

		echo '</div>'; // #fca_eoi_publish_lightbox
	}

	private function meta_box_field( $id, $title, $controls = array() ) {
		$content = '';

		foreach ( $controls as $control ) {
			$control[2] = empty( $control[2] ) ? array() : $control[2];
			$control[3] = empty( $control[3] ) ? array() : $control[3];

			$control[3][ 'return' ] = true;

			$content .= call_user_func_array( 'K::' . $control[0], array_slice( $control, 1 ) );
		}

		$content = trim( $content );
		$class_name = 'accordion-section-primary-' . ( empty( $content ) ? 'empty' : 'full' );
		$content = '<div class="' . $class_name . '">' . $content . '</div>';

		K::wrap(
			K::wrap(
				$title,
				array( 'class' => 'accordion-section-title' ),
				array( 'return' => true )
			) .
			K::wrap(
				$content,
				array( 'class' => 'accordion-section-content' ),
				array( 'return' => true )
			),
			array(
				'class' => 'accordion-section',
				'id' => $id
			)
		);
	}

	public function meta_box_content_build() {

		wp_enqueue_script( 'accordion' );

		global $post;
		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );
		$providers_available = array_keys( $this->settings[ 'providers' ] );
		$providers_options = array();
		$screen = get_current_screen();
		$fca_eoi_last_3_forms = $this->settings[ 'fca_eoi_last_3_forms' ];

		// Prepare providers options
		foreach ($this->settings[ 'providers' ] as $provider_id => $provider ) {
			$providers_options[ $provider_id ] = $provider[ 'info' ][ 'name' ];
		}

		K::wrap( '', array( 'id' => 'fca_eoi_preview' ) );

		echo '<div id="fca_eoi_settings" class="accordion-container">';

		$this->meta_box_field( 'fca_eoi_fieldset_form', 'Form' );

		$this->meta_box_field( 'fca_eoi_fieldset_headline', 'Headline', array(
			array( 'input', 'fca_eoi[headline_copy]',
				array( 'value' => K::get_var( 'headline_copy', $fca_eoi ) ),
				array( 'format' => '<p><label><span class="control-title">Headline Copy</span><br />:input</label></p>' )
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_description', 'Description', array(
			array( 'textarea', 'fca_eoi[description_copy]', array(), array(
				'format' => ':textarea',
				'editor' => true,
				'value' => K::get_var( 'description_copy', $fca_eoi ),
			) )
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_name_field', 'Name Field', array(
			array( 'input', 'fca_eoi[show_name_field]',
				array(
					'type' => 'checkbox',
					'checked' => K::get_var( 'show_name_field', $fca_eoi ),
				),
				array(
					'format' => '<p><label>:input Show Name Field</label></p>'
				),
			),
			array( 'input', 'fca_eoi[name_placeholder]',
				array( 'value' => K::get_var( 'name_placeholder', $fca_eoi, 'Your Name' ) ),
				array( 'format' => '<p><label><span class="control-title">Placeholder Text</span><br />:input</label></p>' )
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_email_field', 'Email Field', array(
			array( 'input', 'fca_eoi[email_placeholder]',
				array( 'value' => K::get_var( 'email_placeholder', $fca_eoi, 'Your Email' ) ),
				array( 'format' => '<p><label><span class="control-title">Placeholder Text</span><br />:input</label></p>' )
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_button', 'Button', array(
			array( 'input', 'fca_eoi[button_copy]',
				array( 'value' => K::get_var( 'button_copy', $fca_eoi, 'Subscribe Now' ) ),
				array( 'format' => '<p><label><span class="control-title">Button Copy</span><br />:input</label></p>' )
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_privacy', 'Privacy Policy', array(
			array( 'textarea', 'fca_eoi[privacy_copy]',
				array(
					'class' => 'large-text',
				),
				array(
					'format' => '<p><label><span class="control-title">Privacy Policy Copy</span><br />:textarea</label></p>',
					'value' => K::get_var( 'privacy_copy', $fca_eoi ),
				)
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_fatcatapps', 'Branding', array(
			array( 'input', 'fca_eoi[show_fatcatapps_link]',
				array(
					'type' => 'checkbox',
					'checked' => K::get_var( 'show_fatcatapps_link', $fca_eoi ),
				),
				array(
					'format' => '<p><label>:input Show <a href="http://fatcatapps.com/" target="_blank">Easy Opt-ins</a> Branding</label></p>'
				)
			)
		) );

		$this->meta_box_field( 'fca_eoi_fieldset_error_text', 'Error Text', array(
			array( 'textarea', 'fca_eoi[error_text_field_required]',
				array(
					'class' => 'large-text'
				),
				array(
					'format' => '<p><label><span class="control-title">Field Required</span><br />:textarea</label></p>',
					'value' => K::get_var( 'error_text_field_required', $fca_eoi, esc_html( $this->settings['error_text']['field_required'] ) )
				)
			),
			array( 'textarea', 'fca_eoi[error_text_invalid_email]',
				array(
					'class' => 'large-text'
				),
				array(
					'format' => '<p><label><span class="control-title">Invalid Email</span><br />:textarea</label></p>',
					'value' => K::get_var( 'error_text_invalid_email', $fca_eoi, esc_html( $this->settings['error_text']['invalid_email'] ) )
				)
			)
		) );

		echo '</div>';
	}

	public function meta_box_content_thanks() {
		global $post;
		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );
		$screen = get_current_screen();
		$fca_eoi_last_3_forms = $this->settings[ 'fca_eoi_last_3_forms' ];

		// Get the previous thank you page if this is a new post
		$thank_you_page_suggestion = false;
		if ( 'add' === $screen->action ) {
			foreach ( $fca_eoi_last_3_forms as $fca_eoi_previous_form ) {
				try {
					if(
						K::get_var( 'fca_eoi', $fca_eoi_previous_form )
						&& K::get_var( 'thank_you_page', $fca_eoi_previous_form[ 'fca_eoi' ] )
					) {
						$thank_you_page_suggestion = $fca_eoi_previous_form[ 'fca_eoi' ][ 'thank_you_page' ];
						break;
					}
				} catch ( Exception $e ) {}
			}
		}

		// Prepare options
		$pages = array( '~' => __( 'Front page' ) );
		$pages_objects = get_pages();
		foreach ( $pages_objects as $page_obj ) {
			$pages[ $page_obj->ID ] = $page_obj->post_title;
		}

		K::wrap( 'Redirect user to the following page after submitting the form:'
			, null
			, array( 'in' => 'p' )
		);
		K::select( 'fca_eoi[thank_you_page]'
			, array( 
				'class' => 'select2',
				'style' => 'width: 27em;',
			)
			, array( 
				'format' => '<p><label>:select</label></p>',
				'options' => $pages,
				'selected' => 'add' === $screen->action
					? $thank_you_page_suggestion
					: K::get_var( 'thank_you_page', $fca_eoi, '~' )
				,
			)
		);
		K::wrap( __( 'Create a new "Thank You Page" &rsaquo;' )
			, array(
				'href' => admin_url( 'post-new.php?post_type=page' ),
				'target' => '_blank',
			)
			, array(
				'in' => 'a',
				'html_before' => '<p>',
				'html_after' => '</p>',
			)
		);
	}

	public function meta_box_content_powerups() {

		global $post;
		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );

		do_action('fca_eoi_powerups', $fca_eoi ); 
	}

	public function meta_box_content_debug() {

		global $post;

		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );

		!d( $fca_eoi );
	}

	/**
	 * Save the Metabox Data
	 */
	public function save_meta_box_content( $post_id, $post ) {

		// Save meta data
		delete_post_meta( $post->ID, 'fca_eoi' );
		if( $meta = K::get_var( 'fca_eoi', $_POST ) ) {
			// Add provider if missing (happens on free distros where there is only one provider)
			if( ! K::get_var( 'provider', $meta ) ) {
				$meta[ 'provider' ] = $this->settings[ 'provider' ];
			}

			// Keep only the current providers settings, Remove all [provider]_[setting] not belonging to the current provider
			$provider = K::get_var( 'provider', $meta );
			if( $provider ) {
				$providers = array_keys( $this->settings[ 'providers' ] );
				$other_providers = array_values( array_diff( $providers, array( $provider ) ) );
				foreach ( $meta as $k => $v ) {
					$p = explode( '_', $k );
					$k_1 = array_shift( $p );
					if( in_array( $k_1, $other_providers ) ) {
						unset( $meta[ $k ] );
					}
				}

				foreach ( $_POST as $k => $v ) {
					if ( strpos( $k, 'fca_eoi_' . $provider . '_' ) === 0 ) {
						delete_post_meta( $post->ID, $k );
						add_post_meta( $post->ID, $k, $v );
						$meta[ substr($k, 8) ] = $v;
					}
				}
			}

			// Keep only the current layout inforamtion
			foreach ( $meta as $k => $v ) {
				if( preg_match( '|^[a-z]+_[0-9]+$|', $k ) && $k !== $meta[ 'layout' ] ) {
					unset( $meta[ $k ] );
				}
			}

			// Make sure emtpy value for publish_postbox or publish_lightbox are saved as array(-1)
			if( ! K::get_var( 'publish_postbox' , $meta, array() ) ) {
				$meta[ 'publish_postbox' ] = array(-1);
			}
			if( ! K::get_var( 'publish_lightbox' , $meta, array() ) ) {
				$meta[ 'publish_lightbox' ] = array(-1);
			}


			delete_post_meta( $post->ID, 'fca_eoi_layout' );
			delete_post_meta( $post->ID, 'fca_eoi_provider' );

			$on_save_function = $provider . '_on_save';
			if ( function_exists( $on_save_function ) ) {
				$meta = $on_save_function( $meta );
			}

			add_post_meta( $post->ID, 'fca_eoi', $meta );
			add_post_meta( $post->ID, 'fca_eoi_layout', $meta[ 'layout' ] );
			add_post_meta( $post->ID, 'fca_eoi_provider', $meta[ 'provider' ] );
		}
	}

	public function live_preview( $content ) {
		global $post;
		if (get_post_type() == 'easy-opt-ins' && is_main_query()) {
			$shortcode = sprintf( '[%s id=%d]', $this->settings[ 'shortcode' ], $post->ID );
			return do_shortcode($shortcode);
		} else {
			return $content;
		}
	}

	public function admin_enqueue() {

		$protocol = is_ssl() ? 'https' : 'http';
		$provider = $this->settings[ 'provider' ];
		$providers_available = array_keys( $this->settings[ 'providers' ] );

		if ( ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] == 'eoi_powerups' ) || has_action( 'fca_eoi_powerups' ) ) {
			wp_enqueue_style( 'fca_eoi_powerups', $this->settings['plugin_url'] . '/assets/powerups/fca_eoi_powerups.css' );
		}

		$screen = get_current_screen();
		if( 'easy-opt-ins' === $screen->id ){
			wp_enqueue_script( 'tooltipster', $this->settings[ 'plugin_url' ] . '/assets/vendor/tooltipster/jquery.tooltipster.min.js' );
			wp_enqueue_style( 'tooltipster', $this->settings[ 'plugin_url' ] . '/assets/vendor/tooltipster/tooltipster.css' );
			wp_enqueue_script( 'mustache', $protocol . '://cdnjs.cloudflare.com/ajax/libs/mustache.js/0.8.1/mustache.min.js' );
			wp_enqueue_script( 'select2', $protocol . '://cdnjs.cloudflare.com/ajax/libs/select2/3.5.0/select2.js' );
			wp_enqueue_script( 'tinycolor', $protocol . '://cdnjs.cloudflare.com/ajax/libs/tinycolor/1.0.0/tinycolor.min.js' );
			wp_enqueue_script( 'jscolor', $this->settings['plugin_url'] . '/assets/vendor/jscolor/jscolor.js' );
			wp_enqueue_script( 'admin-cpt-easy-opt-ins-providers', $this->settings['plugin_url'] . '/assets/admin/eoi-providers.js' );
			wp_enqueue_style( 'admin-cpt-easy-opt-ins-providers', $this->settings['plugin_url'] . '/assets/admin/eoi-providers.css' );
			wp_enqueue_script( 'admin-cpt-easy-opt-ins', $this->settings['plugin_url'] . '/assets/admin/cpt-easy-opt-ins.js' );
			wp_enqueue_style( 'fca_eoi', $this->settings[ 'plugin_url' ].'/assets/style' . ( EasyOptInsLayout::uses_new_css() ? '-new' : '' ) . '.css' );
			foreach ( $providers_available as $provider ) {
				wp_enqueue_script( 'admin-cpt-easy-opt-ins-' . $provider, $this->settings['plugin_url'] . '/providers/' . $provider . '/cpt-easy-opt-ins.js' );

				$css_path = '/providers/' . $provider . '/cpt-easy-opt-ins.css';
				if ( is_readable( $this->settings['plugin_dir'] . $css_path ) ) {
					wp_enqueue_style( 'admin-cpt-easy-opt-ins-' . $provider, $this->settings['plugin_url'] . $css_path );
				}
			}
			wp_enqueue_style( 'admin-cpt-easy-opt-ins', $this->settings['plugin_url'] . '/assets/admin/cpt-easy-opt-ins.css' );
			if ( EasyOptInsLayout::uses_new_css() ) {
				wp_enqueue_style( 'admin-cpt-easy-opt-ins-new', $this->settings['plugin_url'] . '/assets/admin/cpt-easy-opt-ins-new.css' );
			} else {
				wp_enqueue_style( 'admin-cpt-easy-opt-ins-old', $this->settings['plugin_url'] . '/assets/admin/cpt-easy-opt-ins-old.css' );
			}
			wp_enqueue_style( 'font-awesome', $protocol . '://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.1.0/css/font-awesome.min.css' );
			wp_enqueue_style( 'select2', $protocol . '://cdnjs.cloudflare.com/ajax/libs/select2/3.5.0/select2.min.css' );
			wp_enqueue_script('bootstrap-js', $protocol . '://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js');
			if ( has_action( 'fca_eoi_powerups' ) ) {
				wp_enqueue_script('fca_eoi_powerups', $this->settings['plugin_url'] . '/assets/powerups/fca_eoi_powerups.js');
			}
		}
		if( 'widgets' === $screen->id ){
			wp_enqueue_script( 'select2', $protocol . '://cdnjs.cloudflare.com/ajax/libs/select2/3.5.0/select2.js' );
			wp_enqueue_style( 'select2', $protocol . '://cdnjs.cloudflare.com/ajax/libs/select2/3.5.0/select2.min.css' );
		}
	}

	/**
	 * Disable autosaving optin forms since it causes data loss
	 */
	function disable_autosave() {
		if ( 'easy-opt-ins' == get_post_type() ) {
			wp_dequeue_script( 'autosave' );
		}
	}	

	/**
	 * Hides minor publising form items (status, visibility and publication date)
	 *
	 * This function shoud be used along with force_published to prevent
	 * saving posts as drafts
	 */
	public function hide_minor_publishing() {
		$screen = get_current_screen();
		if( in_array( $screen->id, array( 'easy-opt-ins' ) ) ) {
			echo '<style>#minor-publishing { display: none; }</style>';
		}
	}

	/**
	 * Disables meta box toggling (collapse/expand) for specified post types
	 */
	public function disable_metabox_toggle() {
		
		$current_screen = get_current_screen();

		// Array of post types where we want to remove metabox toggling
		$post_types = array(
			'easy-opt-ins',
		);

		if( in_array( $current_screen->id, $post_types ) ) {
			?>
			<script type="text/javascript">
				jQuery( document ).ready( function($) {
					$( '.postbox' ).removeClass( 'closed' );
					$( '.postbox .hndle' ).css( 'cursor', 'default' );
					$( document ).delegate( '.postbox h3, .postbox .handlediv', 'click', function() {
						$( this )
							.unbind( 'click.postboxes' )
							.parent().removeClass( 'closed' );
					} );
				} );
			</script>
			<?php		
		}
	}

	/**
	 * Forces one column
	 */
	public function force_one_column() {
		
		return 1;
	}

	/**
	 * Sort metaboxes
	 */
	public function order_columns( $order ) {
		return array(
			'normal' => join( ",", array(
				'submitdiv',
				'fca_eoi_meta_box_nav',
				'fca_eoi_meta_box_setup',
				'fca_eoi_meta_box_build',
				'fca_eoi_meta_box_provider',
				'fca_eoi_meta_box_thanks',
				'fca_eoi_meta_box_publish',
				'fca_eoi_meta_box_powerups',
				'fca_eoi_meta_box_debug',
			) ),
			'side' => '',
			'advanced' => '',
		);
	}

	/**
	 * replacing the default "Enter title here" placeholder text in the title input box to 
	 * 
	 */
	public function change_default_title($title) {

		$screen = get_current_screen();

		if ( 'easy-opt-ins' == $screen->post_type ) {
			$title = 'Enter name here';
		}

		return $title;
	}

	/**
	 * Override some strings to match our likings
	 */
	public function override_text( $text ) {

		if( $post = K::get_var( 'post', $GLOBALS ) ) {
			if ( 'easy-opt-ins' === $post->post_type ) {
				switch ( $text ) {
				case 'Post published. <a href="%s">View post</a>':
				case 'Post updated. <a href="%s">View post</a>':
					$text = __( 'Opt-In Form saved.' );
					break;
				case 'Publish':
					$text = __( 'Save' );
					break;
				case 'Update':
					$text = __( 'Save' );
					break;
				}
			}
		}
		return $text;
	}
	
	public function force_published( $post ) {

		if( ! in_array( $post[ 'post_status' ], array( 'auto-draft', 'trash') ) ) {
			if( in_array( $post[ 'post_type' ], array( 'easy-opt-ins' ) ) ) {
				$post['post_status'] = 'publish';
			}
		}
		return $post;
	}

	/**
	 * Disables bulk editing
	 */
	public function disable_bulk_edit( $actions ){
		unset( $actions[ 'edit' ] );
		return $actions;
	}

	/**
	 * Removes quick edit
	 */
	public function remove_quick_edit( $actions ) {
		global $post;
		if( 'easy-opt-ins' === $post->post_type ) {
			unset($actions['inline hide-if-no-js']);
		}
		return $actions;
	}

	/**
	 * Add the desired body classes (backend)
	 */
	public function add_body_class( $classes ) {
		
		return "$classes fca_eoi";
	}

	/**
	 * Handle Adding a subscriber with Ajax
	 */
	public function ajax_subscribe() {

		// Check a list_id is provided
		$list_id = K::get_var( 'list_id' , $_POST );
		if( ! $list_id ) {
			echo '✗';
			exit;
		}

		// Subscribe user
		$status = call_user_func( $this->settings[ 'provider' ] . '_add_user' , $this->settings , $_POST , $list_id );
                // Output ✓ or ✗
		if( $status ) {
                        $fca_eoi = get_post_meta( K::get_var( 'form_id' , $_POST ), 'fca_eoi', true );
                        $double_opt_in = K::get_var( 'mailchimp_double_opt_in', $fca_eoi, '' );
						do_action('fca_eoi_after_submission'
							, $fca_eoi
							, $_POST
						);
			echo '✓';
		} else {
			echo '✗';
		}
		exit;
	}

	/**
	 * Handle form submissions
	 */
	public function handle_submission() {

		// Check that we have a form submission
		if ( ! K::get_var( 'fca_eoi', $_POST ) ) {
			return;
		}

		// Check that we have a valid form
		$id = K::get_var( 'id', $_POST );
		$post = get_post( $id );
		if ( $post && 'easy-opt-ins' === $post->post_type ) {
			// Nothing, we're good
		} else {
			return;
		}

		// Get meta
		$fca_eoi = get_post_meta( $id, 'fca_eoi', true );
		$provider = K::get_var( 'provider' , $fca_eoi );

		// Check a list_id is provided
		$list_id = K::get_var( $provider . '_list_id' , $fca_eoi );

		// @todo: remove
		// Hack for mailchimp upgrade
		if( empty( $fca_eoi[ 'provider' ] ) ) {
			$list_id = K::get_var(
				'mailchimp_list_id'
				, $fca_eoi
				, K::get_var( 'list_id' , $fca_eoi )
			);
			$provider = 'mailchimp';
		}
		// End of Hack

		// Hack for campaignmonitor upgrade
		if( strlen( K::get_var( 'list_id' , $fca_eoi ) ) == 32){
			$list_id = K::get_var(
				'campaignmonitor_list_id'
				, $fca_eoi
				, K::get_var( 'list_id' , $fca_eoi )
			);
			$provider = 'campaignmonitor';
		}
		// End of Hack

        // Hack for mailchimp upgrade
        if( empty( $fca_eoi[ 'provider' ] ) ) {
            $list_id = K::get_var(
                'getresponse_list_id'
                , $fca_eoi
                , K::get_var( 'list_id' , $fca_eoi )
            );
            $provider = 'getresponse';
        }
        // End of Hack

        // Hack for custom form upgrade
        if( empty( $fca_eoi[ 'provider' ] ) ) {
            $list_id = K::get_var(
                'customform_list_id'
                , $fca_eoi
                , K::get_var( 'list_id' , $fca_eoi )
            );
            $provider = 'customform';
        }
        // End of Hack

        // Subscribe user
		if( $list_id ) {
			$status = call_user_func( $provider . '_add_user' , $this->settings , $_POST , $list_id );
			$double_opt_in = K::get_var( 'mailchimp_double_opt_in', $fca_eoi, '' );
			do_action('fca_eoi_after_submission'
				, $fca_eoi
				, $_POST
			);
		}

		// Go to thank you page if any
		$thank_you_page = K::get_var( 'thank_you_page', $fca_eoi );
		$thank_you_page_url = ( $thank_you_page && '~' !== $thank_you_page )
	        ? get_permalink( $thank_you_page )
	        : get_home_url()
		;
		EasyOptInsActivity::get_instance()->add_conversion( K::get_var( 'fca_eoi_form_id', $_POST ) );
		wp_redirect( $thank_you_page_url );
		exit;
	}

	public function admin_notices() {

		$current_screen = get_current_screen();

		// Exit function if we are not on the opt-in editing page
		if ( ! (
				'easy-opt-ins' === $current_screen->id 
				&& 'post' === $current_screen->base
				&& 'edit' === $current_screen->parent_base
				&& '' === $current_screen->action
			) ) {
			return;
		}

		global $post;
		$fca_eoi = get_post_meta( $post->ID, 'fca_eoi', true );
		$provider = K::get_var( 'provider', $fca_eoi);
		$errors = array();

		// Add error for missing thank you page
		$confirmation_page_set = ( bool ) K::get_var( 'thank_you_page', $fca_eoi);
		if( ! $confirmation_page_set ) {
			$errors[] = __( 'No "Thank you" page selected. You will not be able to use this form.' );
		}

		// Add error for missing list setting for the current provider
		$list_set = ( bool ) K::get_var( $provider . '_list_id', $fca_eoi);

		// @todo: remove
		// Hack for mailchimp upgrade
		if( empty( $fca_eoi[ 'provider' ] ) ) {
			$fca_eoi[ 'mailchimp_list_id' ] = K::get_var(
				'mailchimp_list_id'
				, $fca_eoi
				, K::get_var( 'list_id' , $fca_eoi )
			);
			$list_set = ( bool ) K::get_var( 'mailchimp_list_id', $fca_eoi);
		}
		// End of Hack


		if( ! $list_set ) {
			$errors[] = __( 'No List selected. You will not be able to use this form.' );
		}

		$errors = apply_filters( 'fca_eoi_alter_admin_notices', $errors );

		foreach ( $errors as $error ) {
			echo '<div class="error"><p>' . $error . '</p></div>';
		}
	}

	public function bind_content_filter() {

		// Do nothing in backend
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp', array( $this, 'content' ), 10 );
	}

	public function content() {

		global $post;

		if ( empty( $post ) ) {
			return;
		}

		// Do nothing if viewing an opt-in
		if ( 'easy-opt-ins' === $post->post_type ) {
			return;
		}

		// Work only when viewing a post of any type
		if ( empty( $post ) ) {
			return;
		}

		$priorities = array();

		// Post details
		$post_ID = $post->ID;
		$post_type = get_post_type( $post_ID );

		// Build the array for testing
		$post_cond = array(
			'*',
			$post_type,
			'#' . $post_ID,
		);
		if ( is_front_page() ) {
			$post_cond[] = '~';
		}

		$priorities[] = '#' . $post_ID;

		$taxonomies = get_taxonomies('','names');
		$post_taxonomies = wp_get_object_terms( $post->ID,$taxonomies);
		foreach ( $post_taxonomies as $t ) {
			$condition = $post_type . ':' . $t->term_id;

			$post_cond[] = $condition;
			$priorities[] = $condition;
		}

		$priorities[] = $post_type;
		$priorities[] = '*';

		$fca_eoi_last_99_forms = array();
		foreach (get_posts( 'posts_per_page=99&post_type=easy-opt-ins' ) as $i => $f ) {
			$fca_eoi_last_99_forms[ $i ][ 'post' ] = $f;
			$fca_eoi_last_99_forms[ $i ][ 'fca_eoi' ] = get_post_meta( $f->ID, 'fca_eoi', true );
		}
		// wp_reset_query();

		$postboxes = array();

		// Append postcode shortcode when the conditions match
		foreach( $fca_eoi_last_99_forms as $f) {
		
			// Exclude other layout types
			if ( empty ( $f[ 'fca_eoi' ][ 'layout' ] ) ) {
				continue;
			}
			if ( strpos( $f[ 'fca_eoi' ][ 'layout' ], 'postbox_' ) !== 0 ) {
				continue;
			}
		
			// Get conditions
			$eoi_form_cond = K::get_var( 'publish_postbox', $f[ 'fca_eoi' ], array() );
		
			// Append
			if ( array_intersect( $eoi_form_cond, $post_cond ) ) {
				foreach ( $eoi_form_cond as $cond ) {
					if ( empty( $postboxes[ $cond ] ) ) {
						$postboxes[ $cond ] = sprintf( '[%s id=%d]', $this->settings['shortcode'], $f['post']->ID );
					}
				}
			}
		}

		if ( ! empty( $postboxes ) ) {
			foreach ( $priorities as $cond ) {
				if ( ! empty( $postboxes[ $cond ] ) ) {
					$post->post_content .= $postboxes[ $cond ];
					return;
				}
			}

			$post->post_content .= reset( $postboxes );
			return;
		}
	}

	private function enqueue_featherlight() {
		wp_enqueue_script( 'featherlight'
			, $this->settings['plugin_url'] . '/assets/vendor/featherlight/release/featherlight.min.js'
			, array( 'jquery' )
		);

		wp_enqueue_style( 'featherlight'
			, $this->settings['plugin_url'] . '/assets/vendor/featherlight/release/featherlight.min.css'
		);
	}

	private function get_tc_token( $form_id ) {
		return "$form_id";
	}

	private function to_call_tc_condition( $name, $arguments ) {
		return array_merge( array( $name ), $arguments );
	}

	private function to_value_tc_condition( $form_id, $name, $eoi_condition ) {
		$value  = intval( $eoi_condition['value'] );
		$params = array( $value );

		if ( $name === 'page_views' ) {
			$params[] = $this->get_tc_token( $form_id );
		} else if ( $name === 'time_on_page' ) {
			$params[0] *= 1000;
		}

		return $this->to_call_tc_condition( $name, $params );
	}

	private function to_server_pass_tc_condition( $post_id, $parameter, $eoi_condition ) {
		$url = add_query_arg( array(
			'fca_eoi_tc_condition_pass' => urlencode( $parameter ),
			'fca_eoi_tc_condition_value' => urlencode( implode( ',', $eoi_condition['value'] ) ),
			'fca_eoi_tc_condition_post_id' => $post_id
		) );

		return $this->to_call_tc_condition( 'server_pass', array( $url, 'true' ) );
	}

	private function to_tc_condition( $form_id, $post_id, $eoi_condition ) {
		$eoi_to_tc_value_map = array(
			'pageviews' => 'page_views',
			'scrolled_percent' => 'scroll_percent',
			'time_on_page' => 'time_on_page'
		);

		$eoi_server_pass_conditions = array( 'include', 'exclude' );

		$parameter = $eoi_condition['parameter'];
		if ( array_key_exists( $parameter, $eoi_to_tc_value_map ) ) {
			return $this->to_value_tc_condition( $form_id, $eoi_to_tc_value_map[ $parameter ], $eoi_condition );
		} else if ( in_array( $parameter, $eoi_server_pass_conditions ) ) {
			return $this->to_server_pass_tc_condition( $post_id, $parameter, $eoi_condition );
		}

		return null;
	}

	private function to_show_every_tc_condition( $form_id, $condition ) {
		if ( in_array( $condition, array( 'day', 'month', 'once', 'session' ) ) ) {
			return array( $condition, $this->get_tc_token( $form_id ) );
		} elseif ( $condition === 'always' ) {
			return 'true';
		} elseif ( $condition === 'never' ) {
			return 'false';
		}

		return $condition;
	}

	private function get_tc_conditions( $form_id, $post_id, $conditions ) {
		$tc_conditions       = array();
		$sequence_conditions = array();

		if ( ! empty( $conditions['show_every'] ) ) {
			$condition = $this->to_show_every_tc_condition( $form_id, $conditions['show_every'] );

			if ( $condition === 'false' ) {
				return array( 'false' );
			} else {
				$sequence_conditions[] = $condition;
			}
		}

		if ( ! empty( $conditions['conditions'] ) ) {
			foreach ( $conditions['conditions'] as $condition ) {
				if ( $condition['parameter'] === 'exit_intervention' ) {
					$sequence_conditions[] = 'exit';
					continue;
				}

				$tc_condition = $this->to_tc_condition( $form_id, $post_id, $condition );
				if ( ! empty( $tc_condition ) ) {
					$tc_conditions[] = $tc_condition;
				}
			}
		}

		$has_conditions = ! empty ( $tc_conditions );
		if ( $has_conditions ) {
			$tc_conditions = array( 'and' => $tc_conditions );
		}

		if ( $sequence_conditions ) {
			if ( $has_conditions ) {
				$tc_conditions = array( 'sequence' => array( $tc_conditions ) );
			} else {
				$tc_conditions = array( 'sequence' => array() );
			}
			foreach ( $sequence_conditions as $condition ) {
				$tc_conditions['sequence'][] = $condition;
			}
		}

		return $tc_conditions;
	}

	private function echo_tc_conditions_for_form( $form_id, $post_id ) {
		$fca_eoi = get_post_meta( $form_id , 'fca_eoi', true );
		$publish_lightbox = K::get_var( 'publish_lightbox', $fca_eoi, array() );

		header( 'Content-Type: application/json' );
		exit( json_encode( $this->get_tc_conditions( $form_id, $post_id, $publish_lightbox ) ) );
	}

	private function echo_tc_condition_pass( $post_id, $type, $values ) {
		$post_cond = array( '*' );

		if ( is_front_page() ) {
			$post_cond[] = '~';
		}

		if ( $post_id ) {
			$post_type = get_post_type( $post_id );

			$post_cond[] = $post_type;
			$post_cond[] = '#' . $post_id;

			$taxonomies = get_taxonomies( '', 'names' );
			$post_taxonomies = wp_get_object_terms( $post_id, $taxonomies );

			foreach ( $post_taxonomies as $t ) {
				$post_cond[] = $post_type . ':' . $t->term_id;
			}
		}

		$intersect = array_intersect( $values, $post_cond );

		if ( $type === 'include' ) {
			echo $intersect ? 'true' : 'false';
		} else if ( $type === 'exclude' ) {
			echo $intersect ? 'false' : 'true';
		}

		exit;
	}

	public function parse_tc_condition_request() {
		$post_id = empty( $_REQUEST['fca_eoi_tc_condition_post_id'] ) ? 0 : (int) $_REQUEST['fca_eoi_tc_condition_post_id'];

		if ( ! empty( $_REQUEST['fca_eoi_tc_conditions_for'] ) ) {
			$form_id = (int) $_REQUEST['fca_eoi_tc_conditions_for'];
			$this->echo_tc_conditions_for_form( $form_id, $post_id );
		}

		if ( ! empty( $_REQUEST['fca_eoi_tc_condition_pass'] ) ) {
			$this->echo_tc_condition_pass(
				$post_id,
				$_REQUEST['fca_eoi_tc_condition_pass'],
				explode( ',', $_REQUEST['fca_eoi_tc_condition_value'] )
			);
		}
	}

	private function get_cookie_configuration() {
		$cookie_configuration = array();

		if ( defined( 'COOKIEPATH' ) ) {
			$path = COOKIEPATH;
			if ( ! empty( $path ) ) {
				$cookie_configuration['path'] = $path;
			}
		}

		if ( defined( 'COOKIE_DOMAIN' ) ) {
			$domain = COOKIE_DOMAIN;
			if ( ! empty( $domain ) ) {
				$cookie_configuration['domain'] = $domain;
			}
		}

		return $cookie_configuration;
	}

	private function prepare_targeting_cat() {
		static $prepared = false;

		if ( $prepared ) {
			return;
		} else {
			$prepared = true;
		}

		wp_enqueue_script( 'TargetingCat', $this->settings['plugin_url'] . '/' . $this->targeting_cat_path );

		?>
		<script>
			var fca_eoi_tc_configured = false;

			function fca_eoi_on_conditions_pass( form_id, callback ) {
				jQuery.post( window.location.href, {
					'fca_eoi_tc_condition_post_id': <?php echo $GLOBALS['post']->ID ?>,
					'fca_eoi_tc_conditions_for': form_id
				}, function( descriptors ) {
					if ( ! fca_eoi_tc_configured ) {
						TargetingCat_OptinCat.StorageManagerSessionPermanent.get_instance().default_configuration = <?php echo json_encode( $this->get_cookie_configuration() ) ?>;
						fca_eoi_tc_configured = true;
					}

					if ( typeof descriptors === 'string' ) {
						descriptors = JSON.parse( descriptors );
					}

					var evaluable = TargetingCat_OptinCat.ConditionManager.get_instance().parse_descriptors( descriptors );
					if ( evaluable ) {
						evaluable.set_pass_callback( callback );
						evaluable.evaluate();
					}
				} );
			}
		</script>
		<?php
	}

	public function show_lightbox() {
		// Do not show lightbox on mobile if this is the free distribution
		if ( $this->settings[ 'distribution' ] === 'free' ) {
			$mobile_detect = new Mobile_Detect;
			if ( $mobile_detect->isMobile() ) {
				return;
			}
		}

		$has_two_step_optin = false;

		// Get lightboxes
		$lightboxes = get_posts( array(
			'post_type' => 'easy-opt-ins',
			'posts_per_page' => -1,
			'orderby' => 'ID',
			'meta_key' => 'fca_eoi_layout',
			'meta_value' => 'lightbox_',
			'meta_compare' => 'like',
		) );

		// Exit function if no lightbox found
		if( ! $lightboxes ) {
			return;
		}

		$has_lightboxes = false;

		if ( EasyOptInsLayout::uses_new_css() ) {
			$is_iphone = preg_match('/(?:iPhone|iPod);/i', $_SERVER['HTTP_USER_AGENT']);
			$is_ios = preg_match( '/(?:iPhone|iPad|iPod).* OS ([\d])_.* Safari/', $_SERVER['HTTP_USER_AGENT'], $matches );

			if ( $is_ios ) {
				$ios_specific_options = ',
					beforeOpen: function() {
						var viewport = jQuery( "meta[name=viewport]" );
						if ( viewport.length == 0 ) {
							viewport = jQuery( "<meta name=\"viewport\" content=\"\"/>" );
							jQuery( "head" ).append( viewport );
						}

						var viewport_content = viewport.attr( "content" );
						this.fca_eoi_viewport_content = viewport_content;
						viewport.attr( "content", "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" );

						this.fca_eoi_orientation_change_listener = function() {
							window.scrollTo( 0, 0 );
							jQuery( ".fca_eoi_form input" ).each( function() {
								this.blur();
							} );
						};
						window.addEventListener( "orientationchange", this.fca_eoi_orientation_change_listener, false );

						this.fca_eoi_touch_move_listener = function( event ) {
							event.preventDefault();
						};
						window.addEventListener( "touchmove", this.fca_eoi_touch_move_listener, false );
					},
					afterOpen: function() {
						jQuery( "body" ).append( "<div class=\"fca_eoi_featherlight_pad\" style=\"display: block; height: 1000px;\"/>" );
					},
					beforeClose: function() {
						jQuery( ".fca_eoi_featherlight_pad" ).remove();
					},
					afterClose: function() {
						if ( this.hasOwnProperty( "fca_eoi_viewport_content" ) ) {
							jQuery( "meta[name=viewport]" ).attr( "content", this.fca_eoi_viewport_content );
						}

						if ( this.hasOwnProperty( "fca_eoi_orientation_change_listener" ) ) {
							window.removeEventListener( "orientationchange", this.fca_eoi_orientation_change_listener );
						}

						if ( this.hasOwnProperty( "fca_eoi_touch_move_listener" ) ) {
							window.removeEventListener( "touchmove", this.fca_eoi_touch_move_listener );
						}
					}';
			} else {
				$ios_specific_options = '';
			}

			$featherlight_options = '{
					namespace: "fca_eoi_featherlight",
					otherClose: ".fca_eoi_layout_popup_close"' . $ios_specific_options . ',
					variant: ' . ( $is_iphone ? '"fca_eoi_device_iphone"' : 'null' ) . '
				}';
		} else {
			$featherlight_options = 'undefined';
		}

		foreach ( $lightboxes as $lightbox ) {

			// Get conditions
			$lightbox->fca_eoi = get_post_meta( $lightbox->ID , 'fca_eoi', true );
			$publish_lightbox_mode = K::get_var( 'publish_lightbox_mode', $lightbox->fca_eoi, array() );

			// If on a free distribution, force traditional popup mode
			if ( 'free' === $this->settings['distribution'] ) {
				$publish_lightbox_mode = 'traditional_popup';
			}

			if ( 'two_step_optin' === $publish_lightbox_mode ) {
				$has_two_step_optin = true;
			}

			$has_lightboxes = true;

			// Output
			printf( '
				<style>.%s-content { background: transparent !important; }</style>
				<div style="display:none"><div id="fca_eoi_lightbox_%s">%s</div></div>'
				, EasyOptInsLayout::uses_new_css() ? 'fca_eoi_featherlight' : 'featherlight'
				, $lightbox->ID
				, do_shortcode( "[easy-opt-in id=$lightbox->ID]" )
			);

			if ( ! $has_two_step_optin ) {
				$this->prepare_targeting_cat();
				?>
				<script>
					jQuery( function() {
						fca_eoi_on_conditions_pass( <?php echo $lightbox->ID ?>, function() {
							jQuery.featherlight( jQuery( '#fca_eoi_lightbox_<?php echo $lightbox->ID; ?>' ), <?php echo $featherlight_options ?> );
							<?php echo EasyOptInsActivity::get_instance()->get_tracking_code( $lightbox->ID ) ?>
						} );
					} );
				</script>
				<?php
			}
		}

		if ( $has_two_step_optin ) {
			$has_lightboxes = true;

			?>
			<script>
				jQuery( document ).ready( function( $ ) {

					var $ = jQuery;
					$( '[data-optin-cat]' ).click( function( e ) {

						var $this = $( this );
						var lightbox_ID = $this.data( 'optin-cat' );
						<?php echo EasyOptInsActivity::get_instance()->get_tracking_code( 'lightbox_ID', false ) ?>
						$.featherlight( $( '#fca_eoi_lightbox_' + lightbox_ID ), <?php echo $featherlight_options ?> );
						e.preventDefault();
					} );
				} );
			</script>
			<?php
		}

		if ( $has_lightboxes ) {
			$this->enqueue_featherlight();

			?>
			<script>
				jQuery( function( $ ) {
					$( '.fca_eoi_featherlight' ).live( 'click', function( event ) {
						var target = $( event.target );

						if ( target.hasClass( 'fca_eoi_featherlight-inner' ) ||
							target.hasClass( 'fca_eoi_form_content' ) ) {
							$( '.fca_eoi_layout_popup_close' ).click();
						}
					} );
				} );
			</script>
			<?php
		}
	}
}

function k_selector( $name, $selected_options = array(), $return = false ) {

	global $post;
	// Dirty fix to restore the global $post
	$post_bak = $post;

	// Get all post types except media
	$post_types = get_post_types( array( 'public' => true ) );
	unset( $post_types[ 'attachment' ] );

	$ret = ob_start();

	// Start ouput
	echo '<select data-placeholder="' . __( 'Type to search for posts, categories or pages.' ) . '" name = "' . $name . '[]" class="select2" multiple="multiple" style="width: 27em;">';

	// Front page
	K::wrap( __( 'Front page' )
		, array(
			'value' => '~',
			'selected' => in_array( '~', $selected_options ),
		)
		, array( 'in' => 'option' )
	);

	// All posts
	// K::wrap( __( 'All' )
	// 	, array(
	// 		'value' => '*',
	// 		'selected' => in_array( '*', $selected_options ),
	// 	)
	// 	, array( 'in' => 'option' )
	// );

	foreach ($post_types as $post_type => $post_type_args ) {

		$post_type_obj = get_post_type_object( $post_type );
		$post_type_name = $post_type_obj->labels->singular_name;

		$options = array();

		// Add taxonomy/terms options
		$taxonomies = get_object_taxonomies( $post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_obj = get_taxonomy( $taxonomy );
			$taxonomy_name = $taxonomy_obj->labels->singular_name;
			$terms = get_categories("taxonomy=$taxonomy&type=$post_type"); 
			foreach ($terms as $term) {
				$options[ 'taxonomies' ][ "$post_type:$term->term_id" ] =
					$post_type_name
					. " › $taxonomy_name"
					. " › $term->name"
				;
			}
		}

		// Add posts options
		$the_query = new WP_Query( "post_type=$post_type&posts_per_page=-1" );
		if ( $the_query->have_posts() ) {

			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$options[ 'posts' ][ '#' . get_the_ID() ] = $post_type_name
					. ' ' . __( '›' ) . ' '
					. '#' . get_the_ID() . ' &ndash; '
					. ( get_the_title() ? get_the_title() : __('[Untitled]') )
				;
			}
		}

		// Dirty fix to restore the global $post
		$post = $post_bak;

		// Posts > All
		echo '<optgroup label="' . $post_type_name . '">';
		printf(
			'<option value="%s" %s >%s</option>'
			, $post_type
			, ( in_array( $post_type, $selected_options ) ? 'selected' : '' )
			, $post_type_name . ' ' . ( '›' ) . ' ' . __( 'All' )
		);
		echo '</optgroup>';

		// Posts > Taoxonomies
		if ( ! empty( $options[ 'taxonomies' ] ) ) {
			printf(
				'<optgroup label="%s">'
				, $post_type_name . ' ' . __( '›' ) . ' ' . __( 'Taxonomies' )
			);
			foreach ( $options[ 'taxonomies' ] as $k => $v ) {
				$selected = ( in_array( $k, $selected_options ) ) ? 'selected="selected"' : '';
				printf( '<option value="%s" %s >%s</option>', $k, $selected, $v );
			}
			echo '</optgroup>';
		}

		// Posts > content
		if ( ! empty( $options[ 'posts' ] ) ) {
			printf( '<optgroup label="%s">'
				, $post_type_name . ' ' . __( '›' ) . ' ' . __( 'Content' )
			);
			foreach ( $options[ 'posts' ] as $k => $v ) {
				$selected = ( in_array( $k, $selected_options ) ) ? 'selected="selected"' : '';
				printf( '<option value="%s" %s >%s</option>'
					, $k
					, $selected
					, $v
				);
			}
			echo '</optgroup>';
		}
	}
	echo '</select>';

	$ret = ob_get_clean();

	if ( $return ) {
		return $ret;
	} else {
		echo $ret;
	}
}

function fca_eoi_comp( $a, $b, $op, $negate = false ) {
	switch ( $op ) {
		case 'eq': return $negate ? ( ! ( $a == $b ) ) : ( $a == $b );
		case 'gt': return $negate ? ( ! ( $a > $b ) ) : ( $a > $b );
		case 'gte': return $negate ? ( ! ( $a >= $b ) ) : ( $a >= $b );
		case 'lt': return $negate ? ( ! ( $a < $b ) ) : ( $a < $b );
		case 'lte': return $negate ? ( ! ( $a <= $b ) ) : ( $a <= $b );
	}
}
