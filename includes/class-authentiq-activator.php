<?php

/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Activator
{
	public static function activate() {
		self::import_wsl_config();
	}

	public static function import_wsl_config() {
		$options = Authentiq_Options::Instance();

		// If plugin is not configured yet
		if (!$options->is_configured()) {

			// Check if WordPress Social Login - Authentiq has been used
			// and import the client_id and client_secret
			$wsl_client_id = get_option('wsl_settings_Authentiq_app_id');
			$wsl_client_secret = get_option('wsl_settings_Authentiq_app_secret');

			if (!empty($wsl_client_id) && !empty($wsl_client_secret)) {
				$options->set('client_id', $wsl_client_id);
				$options->set('client_secret', $wsl_client_secret);

				// This will block user until she configures the redirect_uri in our dashboard
				$options->set('wsl_migration', 1);
			}
		}
	}
}
