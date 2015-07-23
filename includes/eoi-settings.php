<?php

class EasyOptInsSettings
{
  var $settings;
  var $sanitize_done = false;

  function __construct($settings)
  {
    $this->settings = $settings;

    add_action('admin_menu', array($this, 'settings_menu'));
    add_action('admin_init', array($this, 'settings_init'));
  }

  function settings_content()
  {
    $this->plugin_options = get_option('easy_opt_in_settings');
    ?>

      <div class="dh_easy_opt_in_settings">
        <?php echo get_screen_icon(); ?> <h2>Settings</h2>

        <?php settings_errors('easy_opt_in_settings_group'); ?>

        <form method="post" action="options.php">
          <?php
            settings_fields('easy_opt_in_settings_group');
            do_settings_sections(basename(__FILE__));

            submit_button();
          ?>
        </form>
      </div>
    <?php
  }

  function settings_menu()
    {
        add_submenu_page('edit.php?post_type=easy-opt-ins', 'Settings', 'Settings', 'manage_options', basename(__FILE__), array($this, 'settings_content'));
    }

  function settings_init()
  {

    $provider_id = $this->settings[ 'provider' ];
    $provider_settings = $this->settings[ 'providers' ][  $provider_id ][ 'settings' ];

    register_setting('easy_opt_in_settings_group', 'easy_opt_in_settings', array($this, 'sanitize'));

    add_settings_section('setting_api_settings', 'MailChimp API Settings', false, basename(__FILE__));

    foreach ( $provider_settings as $setting => $params ) {
      $params[ 'provider_id' ] = $provider_id;
      $params[ 'setting' ] = $setting;
      add_settings_field(
        "easy_opt_in_settings[{$provider_id}_{$setting}]"
        , $params[ 'title' ]
        , array( $this, 'provider_setting' )
        , basename( __FILE__ )
        , 'setting_api_settings'
        , $params
      );
    }
  }

  public function provider_setting( $args ) {

    // !dd( $args, $this->settings );

    echo str_replace(
      array( '{{setting_name}}' )
      , array( "easy_opt_in_settings[{$args['provider_id']}_{$args['setting']}]" )
      , $args[ 'html' ]
    );
  }

  public function callback_api_key()
  {
    printf(
      '<input type="text" id="api_key" name="easy_opt_in_settings[api_key]" class="large-text" value="%s" />'.
      '<p class="description"><a tabindex="-1" href="http://admin.mailchimp.com/account/api" target="_blank">Where can I find my Campaign Monitor API key?</a></p>',
      esc_attr($this->plugin_options['api_key'])
    );
  }

  public function callback_double_opt_in()
  {

    $plugin_options = $this->plugin_options;

    K::input( 'easy_opt_in_settings[double_opt_in]'
      , array(
        'type' => 'radio',
        'value' => 'true',
        'checked' => K::get_var( 'double_opt_in', $plugin_options ) !== 'false' ? 'checked' : null,
      )
      , array( 'format' => '<div><label>:input Yes</label></div>' )
    );
    K::input( 'easy_opt_in_settings[double_opt_in]'
      , array(
        'type' => 'radio',
        'value' => 'false',
        'checked' => K::get_var( 'double_opt_in', $plugin_options ) === 'false' ? 'checked' : null,
      )
      , array( 'format' => '<div><label>:input No</label></div>' )
    );
  }

  public function sanitize($input)
  {
    // Update notice
    if( ! $this->sanitize_done ) {
      add_settings_error('easy_opt_in_settings_group', '200', 'Settings saved.', 'updated');
      $this->sanitize_done = true;
    }
    return $input;
  }
}


?>
