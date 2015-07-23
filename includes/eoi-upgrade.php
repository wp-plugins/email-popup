<?php

/**
 * Show upgrading notifications for EOI  
 *
 */
class EasyOptInsUpgrade {
    
    private $settings;
    private $fca_maketing_page_left_menu;
    private $fca_maketing_page_top_ad;

    public function __construct( $settings=null ) {
        
        global $pagenow;
        $this->settings = $settings;
        
        $this->fca_maketing_page_left_menu = 'https://fatcatapps.com/optincat?utm_campaign=wp%2Bsubmenu&utm_source=Optin%2BCat%2BFree&utm_medium=plugin';
        $this->fca_maketing_page_top_ad = 'https://fatcatapps.com/optincat?utm_campaign=plugin%2Btop%2Bad&utm_source=Optin%2BCat%2BFree&utm_medium=plugin';

        add_action( 'admin_menu', array( $this, 'fca_eoi_upgrade_to_premium_menu' ));
        add_action( 'admin_footer', array( $this, 'admin_footer' ) );
    }

    function admin_footer() {
        $this->fca_eoi_upgrade_to_premium_menu_js();
        $this->show_upgrade_encouragement();
        $this->show_upgrade_bar();
    }

    function show_upgrade_encouragement() {
        if ( ! $this->is_in_eoi_page() ) {
            return;
        }

        $message =
            '<div>' .
                'Thanks for using Optin Cat. Do you like this plugin? ' .
                '<a href="' . $this->fca_maketing_page_top_ad . '" target="_blank">Optin Cat Premium</a> ' .
                'comes with even more features that help you grow your list. Love it or get your money back. ' .
                '<a href="' . $this->fca_maketing_page_top_ad . '" target="_blank">Learn more here</a>.' .
            '</div>';

        ?>
        <script>
            jQuery( function( $ ) {
                $( '#wpbody-content' ).append( <?php echo json_encode( $message ) ?> );
            } );
        </script>
        <?php
    }

    function show_upgrade_bar() {
        if ( ! $this->is_in_eoi_page() ) {
            return;
        }

        $is_mailchimp = in_array( 'mailchimp', array_keys( $this->settings[ 'providers' ] ) );

        ?>
        <style>
            .fca_eoi_upgrade_bar {
                padding: 12px;
                background-color: #FCF8E3;
            }

            .fca_eoi_upgrade_bar.fca_eoi_upgrade_bar_inner {
                border-top: 1px solid #e5e5e5;
                margin: 0 0 -12px -12px;
            }

            .fca_eoi_upgrade_bar.fca_eoi_upgrade_bar_page {
                border: 1px solid #e5e5e5;
                margin: 12px 12px 12px 0;
            }

            .fca_eoi_upgrade_bar a {
                font-size: 14px;
                font-weight: bold;
            }

            .fca_eoi_upgrade_sidebar {
                float: right;
                margin-right: 22px;
                margin-top: 11px;
                width: 270px;
            }

            .fca_eoi_upgrade_sidebar .fca_eoi_centered {
                text-align: center;
            }

            .fca_eoi_upgrade_sidebar .button-large {
                font-size: 17px;
                line-height: 30px;
                height: 32px;
            }

            .fca_eoi_upgrade_sidebar .last-blurb {
                font-size: 17px;
            }

            #wpbody-content.fca_eoi_upgrade_sidebar_present {
                width: calc( 100% - 300px );
            }
        </style>

        <div class="sidebar-container metabox-holder fca_eoi_upgrade_sidebar" id="fca_eoi_upgrade_sidebar" style="display: none">
            <div class="postbox">
                <h3 class="wp-ui-primary"><span>Wanna Grow Your List?</span></h3>
                <div class="inside">
                    <div class="main">
                        <p class="last-blurb fca_eoi_centered">
                            Optin Cat Premium Boosts Conversions
                        </p>

                        <ul>
                            <li><div class="dashicons dashicons-yes"></div> Mobile-friendly Popups</li>
                            <li><div class="dashicons dashicons-yes"></div> Smart Popup Targeting</li>
                            <li><div class="dashicons dashicons-yes"></div> More Layouts</li>
                            <li><div class="dashicons dashicons-yes"></div> Two-Step Optins</li>
                            <li><div class="dashicons dashicons-yes"></div> Offers Feature</li>
                            <li><div class="dashicons dashicons-yes"></div> Exit Intervention</li>
                            <li><div class="dashicons dashicons-yes"></div> Priority Email Support</li>
                            <?php if ( $is_mailchimp ): ?>
                                <li><div class="dashicons dashicons-yes"></div> Mailchimp Single Optin</li>
                            <?php endif ?>
                            <li><div class="dashicons dashicons-yes"></div> And much more &hellip;</li>
                        </ul>

                        <div class="fca_eoi_centered">
                            <a href="<?php echo $this->fca_maketing_page_top_ad ?>" class="button-primary button-large" target="_blank">
                                Upgrade to Premium
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php

        $template = '<div class="__class__"><a href="' . $this->fca_maketing_page_top_ad . '" target="_blank">__text__ &gt;&gt;</a></div>';

        $script = basename( $_SERVER['SCRIPT_NAME'] );
        if ( $script == 'post.php' || $script == 'post-new.php' ) { ?>
            <script>
                jQuery( function( $ ) {
                    var layouts_message = <?php echo json_encode( str_replace(
                        array( '__class__', '__text__' ),
                        array( 'fca_eoi_upgrade_bar fca_eoi_upgrade_bar_inner', 'Upgrade to Premium for more layouts & design options' ),
                        $template
                    ) ) ?>;

                    $( '#fca_eoi_layout_select' ).before( layouts_message );
                    $( '#fca_eoi_settings' ).after( '<br class="clear"/>' + layouts_message );

                    $( '#fca_eoi_publish_lightbox_mode_two_step_optin' ).after( <?php echo json_encode( str_replace(
                        array( '__class__', '__text__' ),
                        array( 'fca_eoi_upgrade_bar fca_eoi_upgrade_bar_inner', 'Want smart popup targeting, mobile popups, exit intervention or two-step optins? Upgrade to Premium' ),
                        $template
                    ) ) ?> );

                    <?php if ( in_array( 'mailchimp', array_keys( $this->settings['providers'] ) ) ): ?>
                        $( '#fca_eoi_fieldset_form_mailchimp_integration' ).append( <?php echo json_encode( str_replace(
                            array( '__class__', '__text__' ),
                            array( 'fca_eoi_upgrade_bar fca_eoi_upgrade_bar_inner', 'Want to set up MailChimp Single Optin? Upgrade to Premium' ),
                            $template
                        ) ) ?> );
                    <?php endif ?>

                    var $power_ups_span = $( '.hndle span:contains("Power Ups")' );
                    if ( $power_ups_span && $power_ups_span.length > 0 ) {
                        $power_ups_span.parent().next( '.inside' ).append( <?php echo json_encode( '<br/>' . str_replace(
                            array( '__class__', '__text__' ),
                            array( 'fca_eoi_upgrade_bar fca_eoi_upgrade_bar_inner', 'Upgrade to Premium and get access to the Offers Powerup' ),
                            $template
                        ) ) ?> );
                    }
                } );
            </script>
        <?php }

        elseif ( $script == 'edit.php' && ( empty( $_REQUEST['page'] ) || $_REQUEST['page'] == 'eoi_powerups' ) ) { ?>
            <script>
                jQuery( function( $ ) {
                    $( '#wpbody-content' ).addClass( 'fca_eoi_upgrade_sidebar_present' ).before( $( '#fca_eoi_upgrade_sidebar' ).show() );
                } );
            </script>
        <?php }
    }

    function fca_eoi_upgrade_to_premium_menu() {
        
        $page_hook = add_submenu_page( 'edit.php?post_type=easy-opt-ins', __( 'Upgrade to Premium'), __( 'Upgrade to Premium' ), 'manage_options', 'eoi_premium_upgrade', array( $this, 'fca_eoi_upgrade_to_premium' ));
        add_action( 'load-' . $page_hook , array( $this, 'fca_eoi_upgrade_to_premium_redirect' ));
     }
   
    function fca_eoi_upgrade_to_premium_redirect() {
        
        wp_redirect( $this->fca_maketing_page_left_menu, 301 );
        exit();
      }
      
    function fca_eoi_upgrade_to_premium_menu_js()
     {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('a[href="edit.php?post_type=easy-opt-ins&page=eoi_premium_upgrade"]').on('click', function () {
                            $(this).attr('target', '_blank');
                });
            });
        </script>
        <style>
            a[href="edit.php?post_type=easy-opt-ins&page=eoi_premium_upgrade"] {
                color: #ee6800 !important;
            }
            a[href="edit.php?post_type=easy-opt-ins&page=eoi_premium_upgrade"]:hover {
                color: #C65700 !important;
            }
            .eoi-changelogs {
                background: #f1f1f1;
            }
            .eoi-changelogs-content {
                margin: 20px 10px;
                background: #fff;
            }
            
        </style>
    <?php 
    }
    
    function is_in_eoi_page() {
        return ( ! empty( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == 'easy-opt-ins' ) ||
               ( ! empty( $GLOBALS['post'] ) && $GLOBALS['post']->post_type == 'easy-opt-ins' );
    }
}