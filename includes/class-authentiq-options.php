<?php

/**
 * The Authentiq options class.
 *
 * This is used to get/set plugin options.
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Options
{
	protected $options_name = AUTHENTIQ_NAME . '_settings';
	private $_opt = null;
	protected static $instance = null;

	public static function Instance() {
		if (self::$instance === null) {
			self::$instance = new Authentiq_Options;
		}

		return self::$instance;
	}

	public function get_options_name() {
		return $this->options_name;
	}

	public function get_options() {
		if (empty($this->_opt)) {
			$options = get_option($this->options_name, array());

			if (!is_array($options)) {
				$options = $this->defaults();
			}

			$options = array_merge($this->defaults(), $options);

			$this->_opt = $options;
		}

		return $this->_opt;
	}

	public function get($key, $default = null) {
		$options = $this->get_options();

		if (!isset($options[$key])) {
			return apply_filters('authentiq_get_option', $default, $key);
		}

		/**
		 * Filters Authentiq option value.
		 *
		 * @since 1.0.0
		 *
		 * @param string     $value       Option value
		 * @param string     $key         Option key
		 */
		return apply_filters('authentiq_get_option', $options[$key], $key);
	}

	public function set($key, $value, $should_update = true) {
		$options = $this->get_options();
		$options[$key] = $value;
		$this->_opt = $options;

		if ($should_update) {
			update_option($this->options_name, $options);
		}
	}

	public function save() {
		$options = $this->get_options();
		update_option($this->options_name, $options);
	}

	public function delete($key) {
		$defaults = $this->defaults();
		$this->set($key, $defaults[$key]);
	}

	public function get_default($key) {
		$defaults = $this->defaults();

		return $defaults[$key];
	}

	protected function defaults() {
		return array(
			'version' => AUTHENTIQ_VERSION,

			'client_id' => '',
			'client_secret' => '',
			'client_scopes' => array(),

			'requires_verified_email' => 1,

			// Allows users to use the WP username/password form
			'classic_wp_login' => 1,

			// Accepts certain users authenticating based on this rule
			// 0: all users, 1: users without Authentiq ID, 2: no users
			'classic_wp_login_for' => 1,

			// Automatic redirects to Authentiq ID, without going to WP login page
			'auto_login' => 0,

			// Filter users while authenticating based on the email domain (one domain per line)
			'filter_user_domains' => '',

			// 0: whitelist domains, 1: blacklist domains
			'filter_user_domains_condition' => 1,

			// Default redirect_to after a sign-in for Authentiq plugin
			'default_login_redirection' => home_url(),

			// Used for showing messages in the admin area
			'deferred_admin_notices' => array(),

			// Asks user to correctly configure the client in our dashboard, if a WSL integration has been performed
			'wsl_migration' => 0,
		);
	}

	protected function _get_boolean($value) {
		return 1 === (int)$value || strtolower($value) === 'true';
	}

	public function is_configured() {
		return trim($this->get('client_id')) !== '' && trim($this->get('client_secret')) !== ''
			&& !$this->_get_boolean($this->get('wsl_migration'));
	}

	public function allow_classic_wp_login() {
		return $this->_get_boolean($this->get('classic_wp_login'));
	}

	// WordPress helper options
	public function is_wp_registration_enabled() {
		if (is_multisite()) {
			return users_can_register_signup_filter();
		}

		return get_site_option('users_can_register', 0) == 1;
	}
}
