<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of eoi-pointer
 *
 * @author thuantp
 */
class EasyOptInsPointer {

    private $settings;

    public function __construct( $settings=null ) {
        
        global $pagenow;
        
        add_action('wp_ajax_tt_eoi_mailing_list', array($this, 'tt_eoi_mailing_list_pointer_ajax'));

        // Show mailing list pointer popup once
        $mailing_list = get_option('tt_eoi_mailing_list');
        if ('easy-opt-ins' == $settings['post_type'] &&
                (( isset($_REQUEST['action']) && 'edit' == $_REQUEST['action']) || 'post-new.php' == $pagenow) &&
                !in_array($mailing_list, array('yes', 'no'))) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        }
        
        $this->settings = $settings;
    }

    function enqueue_assets() {
        wp_enqueue_style('wp-pointer');
        wp_enqueue_script('wp-pointer');
        add_action('admin_print_footer_scripts', array($this, 'tt_eoi_mailing_list_pointer'));
    }

    // Add mailing list subscription
    function tt_eoi_mailing_list_pointer() {
        global $current_user;

        // Get current user info
        get_currentuserinfo();

        // Ajax request template    
        $ajax = '
        jQuery.ajax({
            type: "POST",
            url:  "' . admin_url('admin-ajax.php') . '",
            data: {action: "tt_eoi_mailing_list", email: jQuery("#eoi_email").val(), nonce: "' . wp_create_nonce('tt_eoi_mailing_list') . '", subscribe: "%s" }
        }).done(function( html ) {
                      eval( html  );
         });
    ';

        // Target
        $id = '#wpadminbar';

        // Buttons
        $button_1_title = __('No, thanks');
        $button_1_fn = sprintf($ajax, 'no');
        $button_2_title = __("Let&#39;s do it!");
        $button_2_fn = sprintf($ajax, 'yes');

        // Content
        $content = '<h3>' . __('Easily Grow Your List') . '</h3>';
        $content .= '<p>' . __("Imagine you could get more email optins and make more money. Find out how in our totally free & ridicolously actionable 5-day course on \"How To Triple Your Email List\". No spam.") . '</p>';
        $content .= '<p>' . '<input type="text" name="eoi_email" id="eoi_email" value="' . $current_user->user_email . '" style="width: 100%"/>' . '</p>';

        // Options
        $options = array(
            'content' => $content,
            'position' => array('edge' => 'top', 'align' => 'center')
        );

        $this->tt_eoi_print_script($id, $options, $button_1_title, $button_2_title, $button_1_fn, $button_2_fn);
    }

    function tt_eoi_mailing_list_pointer_ajax() {
        global $current_user;

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tt_eoi_mailing_list') && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            die('No tricky business!');
        }

        // Check status
        $result = ($_POST['subscribe'] == 'yes') ? 'yes' : 'no';
        if ($result == 'no') {
            update_option('tt_eoi_mailing_list', 'no');
            exit();
        }


        // Get current user info
        get_currentuserinfo();
        
        $this->eoi_add_subscriber(
                'https://www.getdrip.com/forms/6746603/submissions',
		
		array(  'fields[name]'  => $current_user->display_name,
			'fields[email]' => $_POST['email'], //$current_user->user_email,,
                        'fields[url]' => get_bloginfo('url')        
		     )
	);      
        
        update_option('tt_eoi_mailing_list', $result);

        // After custommers "sign up", display another pointer to ask them check the confirm email  
        $button_2_title = false;
        $kind_of_email_link = '';
        if ( strpos( $_POST['email'] , '@yahoo' ) !== false ) {
            $button_2_title = 'Go to Yahoo! Mail';
            $kind_of_email_link = 'https://mail.yahoo.com/';
        } elseif ( strpos( $_POST['email'] , '@hotmail' ) !== false )
        {
            $button_2_title = 'Go to Hotmail';
            $kind_of_email_link = 'https://www.hotmail.com/';
        } elseif ( strpos( $_POST['email'] , '@gmail' ) !== false )
        {
            $button_2_title = 'Go to Gmail';
            $kind_of_email_link = 'https://mail.google.com/';
        } elseif ( strpos( $_POST['email'] , '@aol' ) !== false ) 
        {
            $button_2_title = 'Go to AOL Mail';
            $kind_of_email_link = 'https://mail.aol.com/';
        }

        $button_2_func = "window.open('$kind_of_email_link', '_blank');";

        // Target
        $id = '#wpadminbar';

        // Buttons
        $button_1_title = __('Close', PTP_LOC);

        // Content
        $content  = '<h3>' . __('Please confirm your email', PTP_LOC) . '</h3>';
        $content .= '<p>' . __("Thanks! For privacy reasons you'll have to confirm your email. Please check your email inbox.", PTP_LOC) . '</p>';

        // Options
        $options = array(
            'content' => $content,
            'position' => array('edge' => 'top', 'align' => 'center')
        );

        $this->tt_eoi_print_script($id, $options, $button_1_title, $button_2_title , '' , $button_2_func , true);

        exit();
    }
    
    function eoi_add_subscriber($url, $payload = array())
        {
                $data = http_build_query($payload);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ; 
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"); 
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
    }
    
    // Print JS Content
    function tt_eoi_print_script($selector, $options, $button1, $button2 = false, $button1_fn = '', $button2_fn = '', $isCallBackFunc = false ) {

        if( !$isCallBackFunc ) {
     ?>
          <script type="text/javascript">
                    //<![CDATA[
                (function ($) {
     <?php 
              }
     ?>
                var tt_eoi_pointer_options = <?php echo json_encode($options); ?>, setup;
                                     
                tt_eoi_pointer_options = $.extend(tt_eoi_pointer_options, {
                    buttons:function (event, t) {
                        button = jQuery('<a id="pointer-close" style="margin-left:5px" class="button-secondary">' + '<?php echo $button1; ?>' + '</a>');
                        button.bind('click.pointer', function () {
                            t.element.pointer('close');
                        });
                        return button;
                    },
                    close:function () {
                    }
                });
                                     
                setup = function () {
                    $('<?php echo $selector; ?>').pointer(tt_eoi_pointer_options).pointer('open');
                     <?php if ($button2) : ?>
                        jQuery('#pointer-close').after('<a id="pointer-primary" class="button-primary">' + '<?php echo $button2; ?>' + '</a>');
                        jQuery('#pointer-primary').click(function () {
                                <?php echo $button2_fn; ?>
                                $('<?php echo $selector; ?>').pointer('close');
                            });
                        
                        jQuery('#eoi_email').keypress(function ( event ) {
                             if ( event.which == 13 ) {
                                <?php echo $button2_fn; ?>
                                $('<?php echo $selector; ?>').pointer('close');
                             }
                            
                        });
                            jQuery('#pointer-close').click(function () {
                                <?php echo $button1_fn; ?>
                                $('<?php echo $selector; ?>').pointer('close');
                            });
                   <?php endif; ?>
                };
                                 
                $(document).ready(setup);
                
          <?php if( !$isCallBackFunc ) { ?>
          })(jQuery);
        //]]>
	</script>
    <?php
             }
      }

}
?>
