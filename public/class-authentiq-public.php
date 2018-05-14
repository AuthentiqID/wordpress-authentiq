<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/public
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Public
{
  /**
   * The ID of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $plugin_name The ID of this plugin.
   */
  private $plugin_name;
  /**
   * The version of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $version The current version of this plugin.
   */
  private $version;
  protected $options;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   *
   * @param      string $plugin_name The name of the plugin.
   * @param      string $version     The version of this plugin.
   */
  public function __construct($plugin_name, $version, $options = null) {

    $this->plugin_name = $plugin_name;
    $this->version = $version;

    if ($options instanceof Authentiq_Options) {
      $this->options = $options;
    } else {
      $this->options = Authentiq_Options::Instance();
    }
  }

  /**
   * Initialize WP hooks, filters or anything else needed
   *
   * @since    1.0.0
   */
  public function init() {

    // Adds CSS classes for the login form
    add_action('login_body_class', array($this, 'add_login_form_classes'));

    // Add Authentiq code on the login page
    add_action('login_form', array($this, 'render_form'));
    add_filter('login_message', array($this, 'show_login_messages'));
    add_action('register_form', array($this, 'render_register_form'));

    // Allow the Authentiq button to be rendered anywhere
    add_action('authentiq_render_login_button', array($this, 'render_login_button'), 10, 4);
    add_shortcode('authentiq_login_button', array($this, 'shortcode_render_login_button'));

    // Append stylesheet for the login page
    add_action('login_enqueue_scripts', array($this, 'enqueue_login_form_styles'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
  }

  /**
   * Register the stylesheets for the login page of WordPress
   *
   * @since    1.0.0
   */
  function enqueue_login_form_styles() {
    $layout_form_mode = $this->options->get('layout_signin_form');
    
    $is_registration_form = Authentiq_Helpers::query_vars('action') === 'register';
    if ($is_registration_form) {
      $layout_form_mode = $this->options->get('layout_registration_form');
    }
    
    // admin doesn't want Authentiq to handle default WP login form
    if ($layout_form_mode == 3) {
      return;
    }

    wp_enqueue_style($this->plugin_name . '-form', AUTHENTIQ_PLUGIN_URL . 'public/css/authentiq-login-form.min.css', array(), $this->version, 'all');
    wp_enqueue_style($this->plugin_name, AUTHENTIQ_PLUGIN_URL . 'public/css/authentiq-login.min.css', array(), $this->version, 'all');
  }

  /**
   * Register the stylesheets for the public-facing side of the site.
   *
   * @since    1.0.0
   */
  function enqueue_styles() {
    wp_enqueue_style($this->plugin_name, AUTHENTIQ_PLUGIN_URL . 'public/css/authentiq-login.min.css', array(), $this->version, 'all');
  }

  function add_login_form_classes($classes) {
    $layout_form_mode = $this->options->get('layout_signin_form');
    
    $is_registration_form = Authentiq_Helpers::query_vars('action') === 'register';
    if ($is_registration_form) {
      $layout_form_mode = $this->options->get('layout_registration_form');
    }

    // 1 & 2: WP login form will not be replaced, AQ link will be added inline
    // 3: admin doesn't want Authentiq to handle default WP login form
    if (in_array($layout_form_mode, array(1, 2, 3))) {
      return $classes;
    }

    if (!$this->options->is_configured()) {
      return $classes;
    }

    array_push($classes, 'authentiq-login');

    $show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);
    if (!$this->options->allow_classic_wp_login() || !$show_wp_password) {
      array_push($classes, 'wp-passwords-hidden');
    }

    return $classes;
  }

  function show_login_messages($messages) {
    $layout_form_mode = $this->options->get('layout_signin_form');
    
    $is_registration_form = Authentiq_Helpers::query_vars('action') === 'register';
    if ($is_registration_form) {
      $layout_form_mode = $this->options->get('layout_registration_form');
    }
    
    // 1 & 2: WP login form will not be replaced, AQ link will be added inline
    // 3: admin doesn't want Authentiq to handle default WP login form
    if (in_array($layout_form_mode, array(1, 2, 3))) {
      return $messages;
    }

    if (!$this->options->is_configured()) {
      $msg = __('Please visit the Authentiq plugin settings and configure the client.', AUTHENTIQ_LANG);
      $messages .= '<p class="message">' . $msg . '</p>';

      return $messages;
    }

    $show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);
    $is_lost_password_form = Authentiq_Helpers::query_vars('action') === 'lostpassword';
    if ($show_wp_password || $is_lost_password_form) {
      $back_to_authentiq_button = Authentiq_Helpers::render_template('public/partials/render-back-to-authentiq.php', array(
        'is_registration' => $is_registration_form,
      ));

      return $messages . $back_to_authentiq_button;
    }
  }

  function render_form() {
    $layout_signin_form_mode = $this->options->get('layout_signin_form');
    
    // admin doesn't want Authentiq to handle default WP login form
    if ($layout_signin_form_mode == 3) {
      return;
    }

    $is_configured = $this->options->is_configured();
    $show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);

    if (is_user_logged_in() || !$is_configured || $show_wp_password) {
      return;
    }

    $button_text = __('Sign in or register', AUTHENTIQ_LANG);
    if (!$this->options->is_wp_registration_enabled()) {
      $button_text = __('Sign in', AUTHENTIQ_LANG);
    }

    $allow_classic_wp_login = $this->options->allow_classic_wp_login();
    $authorize_url = Authentiq_Provider::get_authorize_url();

    $template_vars = array(
      'authorize_url' => $authorize_url,
      'allow_classic_wp_login' => $allow_classic_wp_login,
      'button_text' => $button_text,
      'button_color_scheme' => $this->options->get('button_color_scheme'),
    );

    // replace WP login form with Authentiq
    if ($layout_signin_form_mode == 0) {
      $template_vars['is_registration'] = false;

      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);

    // add Authentiq button in the WP login form
    } else if ($layout_signin_form_mode == 1) {
      $template_vars['allow_classic_wp_login'] = false;

      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);

    // add Authentiq text link in the WP login form
    } else if ($layout_signin_form_mode == 2) {
      $layout_signin_form_link_text = $this->options->get('layout_signin_form_link_text');
      $template_vars['allow_classic_wp_login'] = false;
      $template_vars['button_text'] = !empty($layout_signin_form_link_text) ? $layout_signin_form_link_text : esc_html__('...or use the Authentiq ID app', AUTHENTIQ_LANG);
      $template_vars['text_only_link'] = true;

      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);
    }
  }

  function render_register_form() {
    $layout_registration_form_mode = $this->options->get('layout_registration_form');
    
    // admin doesn't want Authentiq to handle default WP login form
    if ($layout_registration_form_mode == 3) {
      return;
    }

    $is_configured = $this->options->is_configured();
    $show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);

    if (is_user_logged_in() || !$is_configured || $show_wp_password) {
      return;
    }

    $button_text = __('Register', AUTHENTIQ_LANG);
    $allow_classic_wp_login = $this->options->allow_classic_wp_login();
    $authorize_url = Authentiq_Provider::get_authorize_url();

    $template_vars = array(
      'authorize_url' => $authorize_url,
      'allow_classic_wp_login' => $allow_classic_wp_login,
      'button_text' => $button_text,
      'button_color_scheme' => $this->options->get('button_color_scheme'),
    );

    // replace WP register form with Authentiq
    if ($layout_registration_form_mode == 0) {
      $template_vars['is_registration'] = true;
      
      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);
      
      // add Authentiq button in the WP register form
    } else if ($layout_registration_form_mode == 1) {
      $template_vars['allow_classic_wp_login'] = false;
      
      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);
      
      // add Authentiq text link in the WP register form
    } else if ($layout_registration_form_mode == 2) {
      $layout_registration_form_link_text = $this->options->get('layout_registration_form_link_text');
      $template_vars['allow_classic_wp_login'] = false;
      $template_vars['button_text'] = !empty($layout_registration_form_link_text) ? $layout_registration_form_link_text : esc_html__('...or use the Authentiq ID app', AUTHENTIQ_LANG);
      $template_vars['text_only_link'] = true;

      echo Authentiq_Helpers::render_template('public/partials/login-form.php', $template_vars);
    }
  }

  function render_login_button($sign_in_text = null, $linking_text = null, $sign_out_text = null, $color_scheme = null) {
    if (!$this->options->is_configured()) {
      $msg = __('Please visit the Authentiq plugin settings and configure the client.', AUTHENTIQ_LANG);
      $msg = '<p class="message">' . $msg . '</p>';

      return $msg;
    }

    $authorize_url = Authentiq_Provider::get_authorize_url();

    if (is_user_logged_in()) {
      $current_user = wp_get_current_user();

      // if account linking is possible
      if (!Authentiq_User::has_authentiq_id($current_user->ID)) {
        return Authentiq_Helpers::render_template('public/partials/authentiq-button.php', array(
          'authorize_url' => $authorize_url,
          'button_text' => !empty($linking_text) ? $linking_text : __('Link your account', AUTHENTIQ_LANG),
          'button_color_scheme' => !empty($color_scheme) ? $color_scheme : $this->options->get('button_color_scheme'),
        ));
      }

      return $this->render_logged_in_state($sign_out_text);
    }

    $button_fallback_text = __('Sign in or register', AUTHENTIQ_LANG);
    if (!$this->options->is_wp_registration_enabled()) {
      $button_fallback_text = __('Sign in', AUTHENTIQ_LANG);
    }

    return Authentiq_Helpers::render_template('public/partials/authentiq-button.php', array(
      'authorize_url' => $authorize_url,
      'button_text' => !empty($sign_in_text) ? $sign_in_text : $button_fallback_text,
      'button_color_scheme' => !empty($color_scheme) ? $color_scheme : $this->options->get('button_color_scheme'),
    ));
  }

  function shortcode_render_login_button($atts) {
    $sign_in_text = !empty($atts['sign_in_text']) ? $atts['sign_in_text'] : null;
    $linking_text = !empty($atts['linking_text']) ? $atts['linking_text'] : null;
    $sign_out_text = !empty($atts['sign_out_text']) ? $atts['sign_out_text'] : null;
    $color_scheme = !empty($atts['color_scheme']) ? $atts['color_scheme'] : null;

    return $this->render_login_button($sign_in_text, $linking_text, $sign_out_text, $color_scheme);
  }

  function render_logged_in_state($sign_out_text = null) {
    global $wp;

    if (!$this->options->is_configured()) {
      $msg = __('Please visit the Authentiq plugin settings and configure the client.', AUTHENTIQ_LANG);
      $msg = '<p class="message">' . $msg . '</p>';

      return $msg;
    }

    $redirect_to = home_url(add_query_arg(array(),$wp->request));
    $logout_url = wp_logout_url($redirect_to);

    return Authentiq_Helpers::render_template('public/partials/logged-in-state.php', array(
      'logout_url' => $logout_url,
      'button_text' => $sign_out_text,
      'button_color_scheme' => $this->options->get('button_color_scheme'),
    ));
  }
}
