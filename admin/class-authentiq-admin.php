<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/admin
 * @author     The Authentiq Team <hello@authentiq.com>
 */

class Authentiq_Admin
{
	const UNLINK_AUTHENTIQ_ID_USER_ACTION = 'unlink_authentiq_id_user';
	const COMPLETE_WSL_MIGRATION_ACTION = 'complete_wsl_migration';
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
	 * @param      string $plugin_name The name of this plugin.
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
	 * Initializes WP hooks and filters
	 *
	 * @since    1.0.0
	 */
	public function init() {
		add_action('admin_menu', array($this, 'register_settings_page'));
		add_action('admin_init', array($this, 'register_settings'));

		add_filter('plugin_action_links_' . AUTHENTIQ_PLUGIN_BASENAME, array($this, 'add_plugin_settings_link'));

		add_action('admin_notices', array($this, 'show_admin_notices'));

		add_action('show_user_profile', array($this, 'authentiq_user_profile_fields'));
		add_action('edit_user_profile', array($this, 'authentiq_user_profile_fields'));

		add_filter('show_password_fields', array($this, 'profile_show_password_fields'), 10, 2);

		// handle AJAX actions
		add_action('wp_ajax_' . self::UNLINK_AUTHENTIQ_ID_USER_ACTION, array($this, 'ajax_unlink_authentiq_id_user'), 10, 1);
		add_action('wp_ajax_' . self::COMPLETE_WSL_MIGRATION_ACTION, array($this, 'ajax_complete_wsl_migration'), 10, 1);
	}

	/**
	 * Adds a link to settings page under our plugin in the WordPress plugins page
	 */
	function add_plugin_settings_link($links) {
		$settings_link = '<a href="options-general.php?page=' . $this->plugin_name . '">' . __('Settings', AUTHENTIQ_LANG) . '</a>';
		array_unshift($links, $settings_link);

		return $links;
	}

	/**
	 * Register the settings page for the admin area.
	 *
	 * @since    1.0.0
	 */
	function register_settings_page() {
		add_submenu_page(
			'options-general.php',
			__('Authentiq', 'authentiq'),
			__('Authentiq', 'authentiq'),
			'manage_options',
			'authentiq',
			array($this, 'display_settings_page')
		);
	}

	/**
	 * Display the settings page content for the page we have created.
	 *
	 * @since    1.0.0
	 */
	function display_settings_page() {
		require_once AUTHENTIQ_PLUGIN_DIR . 'admin/partials/authentiq-settings.php';
	}

	/**
	 * Register the settings for our settings page.
	 *
	 * @since    1.0.0
	 */
	function register_settings() {
		register_setting(
			$this->plugin_name . '_settings',
			$this->plugin_name . '_settings',
			array($this, 'sanitize_registered_settings')
		);
	}

	/**
	 * Sanitize our settings.
	 *
	 * @since    1.0.0
	 */
	function sanitize_registered_settings($input, $recursive_call = false) {
		$new_input = array();

		if (isset($input)) {
			// Loop trough each input and sanitize the value
			foreach ($input as $key => $value) {

				if ($key == 'client_id' && empty($value)) {
					add_settings_error('authentiq_settings', 'client_id', 'ClientID is required');
				}

				if ($key == 'client_scopes') {
					$new_input[$key] = $value;

				} else if ($key == 'filter_user_domains') {
					// Check our textbox option field contains no HTML tags - if so strip them out
					$new_input[$key] = wp_filter_nohtml_kses($value);

				} else if ($key == 'deferred_admin_notices') {
					$new_input[$key] = $value;

				} else if (is_array($value)) {
					$new_input[$key] = array_map('sanitize_text_field', $this->sanitize_registered_settings($value, true));

				} else {
					$new_input[$key] = sanitize_text_field($value);
				}
			}
		}

		if (!$recursive_call) {
			// For checkboxes always pass `0` (disabled state),
			// in order we can set this state when options defaults are set to enabled
			$boolean_fields = array('classic_wp_login', 'auto_login', 'requires_verified_email', 'wsl_migration');
			foreach ($boolean_fields as $key) {
				if (!isset($new_input[$key])) {
					$new_input[$key] = 0;
				}
			}
		}

		return $new_input;
	}

	/**
	 * Add warning in the admin area
	 *
	 * @param $notice array (0: notice level, 1: text to display, 2: group_key)
	 */
	public static function add_admin_notice($notice) {
		$options = Authentiq_Options::Instance();

		if (empty($notice) || !is_array($notice)) {
			return;
		}

		$existing_notices = $options->get('deferred_admin_notices', array());

		// Check if a group_key is set for this message
		if (!empty($notice[2])) {

			// Find existing notice for this group_key
			$found_array_key = array_search($notice[2], array_column($existing_notices, 2));

			// Update text in notice
			if ($found_array_key !== false) {
				$existing_notices[$found_array_key] = $notice;

				// Add new notice
			} else {
				$existing_notices[] = $notice;
			}

			// Add new notice
		} else {
			$existing_notices[] = $notice;
		}

		$options->set('deferred_admin_notices', $existing_notices);
	}

	/**
	 * Displays wanrings from plugin in the admin area
	 */
	function show_admin_notices() {
		// Ask user to configure the Authentiq client, if hasn't finished with WSL migration
		if (!$this->options->is_configured() && $this->options->get('wsl_migration')) {
			Authentiq_Admin::add_admin_notice(Authentiq_Helpers::get_wsl_migration_warning());
		}

		$notices = $this->options->get('deferred_admin_notices', array());

		if (sizeof($notices) > 0) {
			foreach ($notices as $notice) {
				// $notice array (0: notice level, 1: text to display, 2: group_key)
				echo '<div class="notice notice-' . $notice[0] . ' is-dismissible"><p>' . $notice[1] . '</p></div>';
			}
			$this->options->delete('deferred_admin_notices');
		}
	}

	/**
	 * Filters the display of the password fields in user profile page.
	 *
	 * @param bool    $show        Whether to show the password fields. Default true.
	 * @param WP_User $profileuser User object for the current user to edit.
	 *
	 * @return bool
	 */
	function profile_show_password_fields($show, $profileuser) {
		global $wpdb;

		$allow_wp_password_login = $this->options->allow_classic_wp_login();

		if ($allow_wp_password_login) {
			// accepts certain users authenticating based on this rule
			// 0: all users, 1: users without Authentiq ID, 2: no users
			$allow_classic_wp_login_for = $this->options->get('classic_wp_login_for');

			if ($allow_classic_wp_login_for == 1) {
				// check if user has already linked with Authentiq ID
				$authentiq_id = get_user_meta($profileuser->ID, $wpdb->prefix . 'authentiq_id', true);

				return !$authentiq_id;
			}

			return $allow_classic_wp_login_for < 2;
		}

		return $show;
	}

	/**
	 * Show Authentiq user profile fields.
	 *
	 * @param  obj $user The user object.
	 *
	 * @return void
	 */
	function authentiq_user_profile_fields($user) {
		$authentiq_id = Authentiq_User::get_authentiq_id($user->ID);
		$userinfo = Authentiq_User::get_userinfo($user->ID);
		$current_user = wp_get_current_user();
		$is_profile_page = $user->ID == $current_user->ID;

		echo Authentiq_Helpers::render_template('admin/partials/authentiq-user-profile.php', array(
			'authentiq_id' => $authentiq_id,
			'userinfo' => $userinfo,
			'user' => $user,
			'is_profile_page' => $is_profile_page,
		));
	}

	/**
	 * Allows Authentiq ID unlink for user through WP AJAX call
	 */
	function ajax_unlink_authentiq_id_user() {
		check_ajax_referer(self::UNLINK_AUTHENTIQ_ID_USER_ACTION, 'aq_nonce');

		$current_user_id = get_current_user_id();
		$user_id_to_unlink = intval(Authentiq_Helpers::query_vars('unlink_user_id'));
		$current_user_unlinked = $current_user_id === $user_id_to_unlink;

		// allow only administrators to unlink other users
		// also a user can unlink herself
		if (!is_admin() && (!is_super_admin() || !$current_user_unlinked)) {
			wp_send_json_error(__('Only an administrator can unlink an Authentiq user.', AUTHENTIQ_LANG));
		}

		Authentiq_User::delete_authentiq_id($user_id_to_unlink);
		Authentiq_User::delete_userinfo($user_id_to_unlink);

		$response = array(
			'success' => true,
			'current_user_unlinked' => $current_user_unlinked,
		);
		wp_send_json($response);
	}

	/**
	 * Completes WSL migration through WP AJAX call
	 */
	function ajax_complete_wsl_migration() {
		check_ajax_referer(self::COMPLETE_WSL_MIGRATION_ACTION, 'aq_nonce');

		if (is_admin() && is_super_admin()) {
			// set wsl_migration as completed
			$this->options->delete('wsl_migration');

			$response = array(
				'success' => true,
			);
			wp_send_json($response);
		}

		wp_send_json_error(__('Only an administrator can configure the Authentiq plugin.', AUTHENTIQ_LANG));
	}
}
