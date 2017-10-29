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
		if (!$this->options->is_configured()) {
			$msg = __('Please visit the Authentiq plugin settings and configure the client.', AUTHENTIQ_LANG);
			$messages .= '<p class="message">' . $msg . '</p>';

			return $messages;
		}

		$show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);
		$is_registration_form = Authentiq_Helpers::query_vars('action') === 'register';
		$is_lost_password_form = Authentiq_Helpers::query_vars('action') === 'lostpassword';
		if ($show_wp_password || $is_registration_form || $is_lost_password_form) {
			return Authentiq_Helpers::render_back_to_authentiq();
		}
	}

	function render_form() {
		$is_configured = $this->options->is_configured();
		$show_wp_password = Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM);

		if (is_user_logged_in() || !$is_configured || $show_wp_password) {
			return;
		}

		$allow_classic_wp_login = $this->options->allow_classic_wp_login();
		$authorize_url = Authentiq_Provider::get_authorize_url();

		echo Authentiq_Helpers::render_template('public/partials/login-form.php', array(
			'authorize_url' => $authorize_url,
			'allow_classic_wp_login' => $allow_classic_wp_login,
		));
	}

	function render_login_button($sign_in_text = null, $linking_text = null, $sign_out_text = null) {
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
				));
			}

			return $this->render_logged_in_state($sign_out_text);
		}

		return Authentiq_Helpers::render_template('public/partials/authentiq-button.php', array(
			'authorize_url' => $authorize_url,
			'button_text' => !empty($sign_in_text) ? $sign_in_text : __('Sign in or register', AUTHENTIQ_LANG),
		));
	}

	function shortcode_render_login_button($atts) {
		$sign_in_text = !empty($atts['sign_in_text']) ? $atts['sign_in_text'] : null;
		$linking_text = !empty($atts['linking_text']) ? $atts['linking_text'] : null;
		$sign_out_text = !empty($atts['sign_out_text']) ? $atts['sign_out_text'] : null;

		return $this->render_login_button($sign_in_text, $linking_text, $sign_out_text);
	}

	function render_logged_in_state($sign_out_text = null) {
		if (!$this->options->is_configured()) {
			$msg = __('Please visit the Authentiq plugin settings and configure the client.', AUTHENTIQ_LANG);
			$msg = '<p class="message">' . $msg . '</p>';

			return $msg;
		}

		$redirect_to = get_permalink();
		$logout_url = wp_logout_url($redirect_to);

		return Authentiq_Helpers::render_template('public/partials/logged-in-state.php', array(
			'logout_url' => $logout_url,
			'button_text' => $sign_out_text,
		));
	}
}
