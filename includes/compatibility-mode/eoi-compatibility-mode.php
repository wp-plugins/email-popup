<?php

class EasyOptInsCompatibilityMode {
    
    var $settings;
    var $css_frontend;
    var $css_backend;

    public function __construct( $settings = array() ) {

		$this->settings = $settings;
                // import  compatibility-lib file
                include_once plugin_dir_path( __FILE__ ) . 'compatibility-lib.php';
                
                 $this->css_frontend = '';
                 $this->css_backend = '';
                 
                 $active_plugins_arr = get_option('active_plugins');
                 foreach ($compatibility_items as $item ) {
                     
                     if( in_array( $item['plugin_dir'] , $active_plugins_arr ) ) {
                         
                         if( $item['is_front_end'] ) {
                             $this->css_frontend .= sanitize_text_field( $item['css'] ) ;
                         } else {
                             $this->css_backend .= sanitize_text_field( $item['css'] ) ;
                         }
                         
                     }                     
                 }
                 
                 if( $this->css_backend !== '' ) {
                        add_action( 'admin_footer', array( $this, 'append_compatible_css_backend' ) , 15 );
                     }
                 if( $this->css_frontend !== '' ) { 
                         add_filter( 'fca_eoi_alter_form', array($this, 'append_compatible_css_frontend') , 10, 2);
                     }
                
                
	}
   
   /**
    * add compatible css frontend
    * 
    */     
   public function append_compatible_css_frontend( $content , $fca_eoi_meta ) {
                $scss = new scssc();
	        $scss->setFormatter("scss_formatter_compressed");
                $content .= "<style>".$scss->compile( $this->css_frontend )."</style>";
		return $content;
   }
   
   /**
    * add compatible css backend
    * 
    */
   public function append_compatible_css_backend( ) {
        global  $pagenow;
        
        if ( 'easy-opt-ins' == $this->settings['post_type'] &&
                (( isset($_REQUEST['action']) && 'edit' == $_REQUEST['action']) || 'post-new.php' == $pagenow) ) { 
              $scss = new scssc();
	      $scss->setFormatter("scss_formatter_compressed");
              echo '<style>'.$scss->compile( $this->css_backend ).'</style>';            
        }
       
   }
   
}
