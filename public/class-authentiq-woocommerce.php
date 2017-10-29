<?php

/**
 * The public-facing functionality for the WooCommerce plugin.
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/public
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Woocommerce
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
		// No need to initiate anything when WooCommerce is not enabled
		if (class_exists('WooCommerce')) {
			return;
		}

		// Add support for WooCommerce
		add_action('woocommerce_before_checkout_form', array($this, 'render_login_button_in_woocommerce_checkout'), 12);
		add_action('woocommerce_before_customer_login_form', array($this, 'render_login_button_in_woocommerce_account'));
		add_filter('woocommerce_checkout_get_value', array($this, 'woocommerce_checkout_prepopulate_fields_from_authentiq'), 10, 2);
	}

	function render_login_button_in_woocommerce_checkout($checkout) {
		$is_user_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();

		// Check if this user is already linked to Authentiq ID
		// then there is no reason to show the button
		if ($is_user_logged_in && Authentiq_User::has_authentiq_id($current_user->ID)) {
			return;
		}

		// enqueue login form CSS only when needed
		wp_enqueue_style($this->plugin_name . '-form',
			AUTHENTIQ_PLUGIN_URL . 'public/css/authentiq-login-form.min.css',
			array(),
			$this->version,
			'all');

		$user_must_login = !$checkout->is_registration_enabled() && $checkout->is_registration_required() && !$is_user_logged_in;

		// request `phone` and `address` scopes optionally, for pre-filling checkout form
		$extra_scopes_to_request = array('phone', 'address');
		$authorize_url = Authentiq_Provider::get_authorize_url($extra_scopes_to_request);
		$sign_in_text = $user_must_login ? __('Sign in', AUTHENTIQ_LANG) : __('Get my details', AUTHENTIQ_LANG);

		echo Authentiq_Helpers::render_template('public/partials/woocommerce-checkout.php', array(
			'authorize_url' => $authorize_url,
			'button_text' => $sign_in_text,
			'user_must_login' => $user_must_login,
		));
	}

	function render_login_button_in_woocommerce_account() {
		// enqueue login form CSS only when needed
		wp_enqueue_style($this->plugin_name . '-form',
			AUTHENTIQ_PLUGIN_URL . 'public/css/authentiq-login-form.min.css',
			array(),
			$this->version,
			'all');

		$show_wp_password_form = false;
		if ($this->options->allow_classic_wp_login() && Authentiq_Helpers::query_vars(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM)) {
			$show_wp_password_form = true;
		}

		$allow_classic_wp_login = $this->options->allow_classic_wp_login();

		// request `phone` and `address` scopes optionally, for pre-filling checkout form
		$extra_scopes_to_request = array('phone', 'address');
		$authorize_url = Authentiq_Provider::get_authorize_url($extra_scopes_to_request);

		$template_vars = array(
			'authorize_url' => $authorize_url,
			'allow_classic_wp_login' => $allow_classic_wp_login,
			'show_wp_password_form' => $show_wp_password_form,
		);

		if (get_option('woocommerce_enable_myaccount_registration') !== 'yes') {
			$template_vars['button_text'] = __('Sign in', AUTHENTIQ_LANG);
		}

		echo Authentiq_Helpers::render_template('public/partials/woocommerce-account.php', $template_vars);
	}

	private function get_woocommerce_country_code_from_authentiq($address_array) {
		if (!empty($address_array['country'])) {
			$countries = WC()->countries->get_countries();
			$found_array_key = array_search($address_array['country'], $countries);

			if ($found_array_key !== false) {
				return $found_array_key;
			}
		}

		return false;
	}

	private function get_woocommerce_state_code_from_authentiq($address_array) {
		if (!empty($address_array['country']) && !empty($address_array['region'])) {
			$country_code = $this->get_woocommerce_country_code_from_authentiq($address_array);
			$states_for_country = WC()->countries->get_states($country_code);

			$found_array_key = array_search($address_array['region'], $states_for_country);

			if ($found_array_key !== false) {
				return $found_array_key;
			}
		}

		return false;
	}

	/**
	 * Pre-populate Woocommerce checkout fields
	 */
	function woocommerce_checkout_prepopulate_fields_from_authentiq($input, $key) {
		global $current_user;

		$userinfo = Authentiq_User::get_userinfo($current_user->ID);

		switch ($key) :
			case 'billing_first_name':
			case 'shipping_first_name':
				return $current_user->first_name;
				break;

			case 'billing_last_name':
			case 'shipping_last_name':
				return $current_user->last_name;
				break;
			case 'billing_email':
				return $current_user->user_email;
				break;
			case 'billing_phone':
				if (!empty($userinfo['phone_number'])) {
					return $userinfo['phone_number'];
				}
				break;
			case 'billing_city':
				if (!empty($userinfo['address']['locality'])) {
					return $userinfo['address']['locality'];
				}
				break;
			case 'billing_postcode':
				if (!empty($userinfo['address']['postal_code'])) {
					return $userinfo['address']['postal_code'];
				}
				break;
			case 'billing_address_1':
				if (!empty($userinfo['address']['street_address'])) {
					return $userinfo['address']['street_address'];
				}
				break;
			case 'billing_state':
				if (!empty($userinfo['address']['region'])) {
					$state_code = $this->get_woocommerce_state_code_from_authentiq($userinfo['address']);

					if ($state_code !== false) {
						return $state_code;
					}
				}
				break;
			case 'billing_country':
			case 'shipping_country':
				if (!empty($userinfo['address']['country'])) {
					$country_code = $this->get_woocommerce_country_code_from_authentiq($userinfo['address']);

					if ($country_code !== false) {
						return $country_code;
					}
				}
				break;
		endswitch;
	}
}
