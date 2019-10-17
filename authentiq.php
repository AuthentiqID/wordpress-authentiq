<?php
/**
 * Plugin Name:       Authentiq
 * Plugin URI:        https://wordpress.org/plugins/authentiq
 * Description:       Sign in (and sign up) to WordPress sites using the Authentiq ID app. Strong authentication, without the passwords.
 * Version:           1.0.6
 * Author:            The Authentiq Team
 * Author URI:        https://www.authentiq.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       authentiq
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
	exit();
}

define('AUTHENTIQ_PLUGIN_FILE', __FILE__);
define('AUTHENTIQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ));
define('AUTHENTIQ_PLUGIN_DIR', trailingslashit(plugin_dir_path(__FILE__)));
define('AUTHENTIQ_PLUGIN_URL', trailingslashit(plugin_dir_url(__FILE__)));
define('AUTHENTIQ_VERSION', '1.0.6');
define('AUTHENTIQ_NAME', 'authentiq');
define('AUTHENTIQ_LANG', AUTHENTIQ_NAME);

define('AUTHENTIQ_LOGIN_FORM_QUERY_PARAM', 'aqwpl');
define('AUTHENTIQ_OP_REDIRECT_QUERY_PARAM', 'authentiq');

if (!defined('AUTHENTIQ_PROVIDER_BASE_URL')) {
	define('AUTHENTIQ_PROVIDER_BASE_URL', 'https://connect.authentiq.io/');
}

define('AUTHENTIQ_PROVIDER_AUTHORIZE_URL', AUTHENTIQ_PROVIDER_BASE_URL . 'authorize');
define('AUTHENTIQ_PROVIDER_TOKEN_URL', AUTHENTIQ_PROVIDER_BASE_URL . 'token');
define('AUTHENTIQ_PROVIDER_USERINFO_URL', AUTHENTIQ_PROVIDER_BASE_URL . 'userinfo');

/**
 * Runs during plugin activation.
 */
function activate_authentiq() {
	require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-activator.php';
	Authentiq_Activator::activate();
}

register_activation_hook(__FILE__, 'activate_authentiq');

/**
 * Runs during plugin deactivation.
 */
function deactivate_authentiq() {
	require_once AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq-deactivator.php';
	Authentiq_Deactivator::deactivate();
}

register_deactivation_hook(__FILE__, 'deactivate_authentiq');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require AUTHENTIQ_PLUGIN_DIR . 'includes/class-authentiq.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_authentiq() {
	$plugin = new Authentiq();
}

run_authentiq();
