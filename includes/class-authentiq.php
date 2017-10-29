<?php

use \Firebase\JWT\JWT;

/**
 * Authentiq Core plugin class
 *
 * Functions used across both the public-facing side of the site and the admin area.
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/includes
 */
class Authentiq
{
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $plugin_name The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;
	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	public function __construct() {

		$this->plugin_name = AUTHENTIQ_NAME;
		$this->version = AUTHENTIQ_VERSION;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_widgets();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Authentiq_i18n. Defines internationalization functionality.
	 * - Authentiq_options. Defines plugin options.
	 * - Authentiq_Admin. Defines all hooks for the admin area.
	 * - Authentiq_Public. Defines all hooks for the public side of the site.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		// Load Authentiq Exceptions
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/exceptions/Authentiq_Before_Login_Exception.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/exceptions/Authentiq_Login_Redirect_Exception.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/exceptions/Authentiq_User_Exception.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/exceptions/Authentiq_Login_Flow_Validation_Exception.php';

		// Handles all the Authentiq plugin options
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-options.php';
		$this->options = Authentiq_Options::Instance();

		// Helper functions for plugin
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-helpers.php';

		// Handling Authentiq functionality for a WP user
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-user.php';

		// Handles the OAuth 2.0 and OpenID handshakes
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-provider.php';

		// Handles backchannel logout from the Authentiq ID App
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-backchannel-logout.php';

		// Class responsible for defining internationalization functionality
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-i18n.php';

		if (is_admin()) {
			// Handles all the admin area functionality
			require_once AUTHENTIQ_PLUGIN_DIR . 'admin/class-authentiq-admin.php';
		}

		// Handles all the public-facing area functionality
		require_once AUTHENTIQ_PLUGIN_DIR . 'public/class-authentiq-public.php';

		// Handles WooCommerce plugin forms
		require_once AUTHENTIQ_PLUGIN_DIR . 'public/class-authentiq-woocommerce.php';

		// Handles the Authentiq Widget
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-widget.php';

		// Load JWT 3rd party library
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/libraries/php-jwt/JWT.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/libraries/php-jwt/BeforeValidException.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/libraries/php-jwt/ExpiredException.php';
		require_once AUTHENTIQ_PLUGIN_DIR . 'includes/libraries/php-jwt/SignatureInvalidException.php';

		// Give 2 minutes JWT leeway for iat, nbf, exp checks
		JWT::$leeway = 120;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Authentiq_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Authentiq_i18n();
		$plugin_i18n->init();
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		// no need to run any code for admin area, if not in admin pages
		if (!is_admin()) {
			return;
		}

		$plugin_admin = new Authentiq_Admin($this->get_plugin_name(), $this->get_version());
		$plugin_admin->init();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$provider = new Authentiq_Provider($this->options);
		$provider->init();

		$plugin_public = new Authentiq_Public($this->get_plugin_name(), $this->get_version());
		$plugin_public->init();

		$backchannel_logout = new Authentiq_Backchannel_Logout($this->options);
		$backchannel_logout->init();

		$woocommerce = new Authentiq_Woocommerce($this->get_plugin_name(), $this->get_version(), $this->options);
		$woocommerce->init();
	}

	/**
	 * Register and load the widgets.
	 */
	private function define_widgets() {
		add_action('widgets_init', array($this, 'authentiq_load_widget'));
	}

	/**
	 * Adds the Authentiq widget.
	 */
	function authentiq_load_widget() {
		register_widget('Authentiq_Widget');
	}

	/**
	 * Plugin name used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
