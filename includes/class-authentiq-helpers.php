<?php

/**
 * Authentiq helpers class.
 *
 * Helper methods for UI
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */

class Authentiq_Helpers
{
	public static function query_vars($key) {
		global $wp_query;
		if (isset($wp_query->query_vars[$key])) return $wp_query->query_vars[$key];
		if (isset($_REQUEST[$key])) return $_REQUEST[$key];

		return null;
	}

	/**
	 * Render the template passing access to variables
	 *
	 * @param      $path
	 * @param null $args
	 *
	 * @return string
	 */
	public static function render_template($path, $args = null) {
		if ($args) {
			extract($args, EXTR_SKIP);
		}
		ob_start();
		require(AUTHENTIQ_PLUGIN_DIR . $path);

		return ob_get_clean();
	}

	public static function render_back_to_authentiq() {
		return self::render_template('public/partials/render-back-to-authentiq.php');
	}

	/**
	 * Shows a warning in the admin area if user migrated from WSL plugin and haven't finished the configuration
	 *
	 * @return array
	 */
	public static function get_wsl_migration_warning() {
		$notice = __('The Authentiq plugin successfully imported settings from WordPress Social Login plugin.</br>' .
			'Please visit the Authentiq plugin %s to finish configuration.', AUTHENTIQ_LANG);
		$link = '<a href="' . admin_url('options-general.php?page=' . AUTHENTIQ_NAME) . '">' . __('settings page', AUTHENTIQ_LANG) . '</a >';

		return array('info', sprintf($notice, $link), 'wsl_migration'); // notice level, text to display, group_key
	}
}
