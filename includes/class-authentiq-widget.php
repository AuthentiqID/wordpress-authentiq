<?php

/**
 * Authentiq widget class.
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Widget extends WP_Widget
{
  protected $widget_id = 'wp_authentiq_widget';

  function __construct() {
    parent::__construct(
      $this->widget_id,
      __('Authentiq', AUTHENTIQ_LANG),
      array('description' => __('Allows user login using Authentiq.', AUTHENTIQ_LANG))
    );
  }

  /**
   * Widget Backend
   *
   * @param array $instance
   */
  public function form($instance) {
    if (isset($instance['title'])) {
      $title = $instance['title'];
    } else {
      $title = __('Login to site', AUTHENTIQ_LANG);
    }

    if (isset($instance['sign_in_text'])) {
      $sign_in_text = $instance['sign_in_text'];
    } else {
      $sign_in_text = __('Sign in', AUTHENTIQ_LANG);
    }

    if (isset($instance['linking_text'])) {
      $linking_text = $instance['linking_text'];
    } else {
      $linking_text = __('Link your account', AUTHENTIQ_LANG);
    }

    if (isset($instance['sign_out_text'])) {
      $sign_out_text = $instance['sign_out_text'];
    } else {
      $sign_out_text = __('Sign out', AUTHENTIQ_LANG);
    }

    if (isset($instance['button_color_scheme'])) {
      $button_color_scheme = $instance['button_color_scheme'];
    } else {
      $button_color_scheme = 0;
    }

    // Widget admin form
    ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>"/>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('sign_in_text'); ?>"><?php _e('Sign in text:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('sign_in_text'); ?>"
                   name="<?php echo $this->get_field_name('sign_in_text'); ?>" type="text"
                   value="<?php echo esc_attr($sign_in_text); ?>"/>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('linking_text'); ?>"><?php _e('Account linking text:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('linking_text'); ?>"
                   name="<?php echo $this->get_field_name('linking_text'); ?>" type="text"
                   value="<?php echo esc_attr($linking_text); ?>"/>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('sign_out_text'); ?>"><?php _e('Sign out text:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('sign_out_text'); ?>"
                   name="<?php echo $this->get_field_name('sign_out_text'); ?>" type="text"
                   value="<?php echo esc_attr($sign_out_text); ?>"/>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('button_color_scheme'); ?>"><?php _e('Color scheme:'); ?></label>
            <select id="<?php echo $this->get_field_id('button_color_scheme'); ?>"
                    name="<?php echo $this->get_field_name('button_color_scheme'); ?>"
                    class="widefat">
                <option value="0" <?php selected($button_color_scheme, 0); ?>><?php esc_html_e('Default', AUTHENTIQ_LANG) ?></option>
                <option value="1" <?php selected($button_color_scheme, 1); ?>><?php esc_html_e('Purple', AUTHENTIQ_LANG) ?></option>
                <option value="2" <?php selected($button_color_scheme, 2); ?>><?php esc_html_e('Orange', AUTHENTIQ_LANG) ?></option>
                <option value="3" <?php selected($button_color_scheme, 3); ?>><?php esc_html_e('Grey', AUTHENTIQ_LANG) ?></option>
                <option value="4" <?php selected($button_color_scheme, 4); ?>><?php esc_html_e('White', AUTHENTIQ_LANG) ?></option>
            </select>
        </p>
    <?php
  }

  /**
   * Widget Frontend
   *
   * @param array $args
   * @param array $instance
   */
  public function widget($args, $instance) {
    $title = apply_filters('authentiq_widget_title', $instance['title']);

    // before and after widget arguments are defined by themes
    echo $args['before_widget'];
    if (!empty($title)) {
      echo $args['before_title'] . $title . $args['after_title'];
    }

    if (is_user_logged_in()) {
      $current_user = wp_get_current_user();

      // Show account link button
      if (!Authentiq_User::has_authentiq_id($current_user->ID)) {
        echo $this->get_account_linking_template($instance);

        // Show logout button
      } else {
        echo $this->get_logged_in_template($instance);
      }

      // Show login button
    } else {
      echo $this->get_login_template($instance);
    }

    echo $args['after_widget'];
  }

  public function get_login_template($instance) {
    $authorize_url = Authentiq_Provider::get_authorize_url();

    return Authentiq_Helpers::render_template('public/partials/authentiq-button.php', array(
      'authorize_url' => $authorize_url,
      'button_text' => !empty($instance['sign_in_text']) ? $instance['sign_in_text'] : __('Sign in', AUTHENTIQ_LANG),
      'button_color_scheme' => !empty($instance['button_color_scheme']) ? $instance['button_color_scheme'] : 0,
    ));
  }

  public function get_account_linking_template($instance) {
    $authorize_url = Authentiq_Provider::get_authorize_url();

    return Authentiq_Helpers::render_template('public/partials/authentiq-button.php', array(
      'authorize_url' => $authorize_url,
      'button_text' => !empty($instance['linking_text']) ? $instance['linking_text'] : __('Link your account', AUTHENTIQ_LANG),
      'button_color_scheme' => !empty($instance['button_color_scheme']) ? $instance['button_color_scheme'] : 0,
    ));
  }

  public function get_logged_in_template($instance) {
    global $wp;

    $redirect_to = home_url(add_query_arg(array(), $wp->request));
    $logout_url = wp_logout_url($redirect_to);

    return Authentiq_Helpers::render_template('public/partials/logged-in-state.php', array(
      'logout_url' => $logout_url,
      'button_text' => !empty($instance['sign_out_text']) ? $instance['sign_out_text'] : __('Sign out', AUTHENTIQ_LANG),
      'button_color_scheme' => !empty($instance['button_color_scheme']) ? $instance['button_color_scheme'] : 0,
    ));
  }

  /**
   * Update widget replacing old instance with new
   *
   * @param array $new_instance
   * @param array $old_instance
   *
   * @return array
   */
  public function update($new_instance, $old_instance) {
    $instance = array();
    $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
    $instance['sign_in_text'] = (!empty($new_instance['sign_in_text'])) ? strip_tags($new_instance['sign_in_text']) : '';
    $instance['linking_text'] = (!empty($new_instance['linking_text'])) ? strip_tags($new_instance['linking_text']) : '';
    $instance['sign_out_text'] = (!empty($new_instance['sign_out_text'])) ? strip_tags($new_instance['sign_out_text']) : '';
    $instance['button_color_scheme'] = (!empty($new_instance['button_color_scheme'])) ? strip_tags($new_instance['button_color_scheme']) : 0;

    return $instance;
  }
}
