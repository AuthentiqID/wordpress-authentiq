<?php

use \Firebase\JWT\JWT;

/**
 * The Authentiq backchannel logout class.
 *
 * Allows user to sign out in WordPress from Authentiq ID App.
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Backchannel_Logout
{
	protected $options;

	function __construct($options = null) {
		if ($options instanceof Authentiq_Options) {
			$this->options = $options;
		} else {
			$this->options = Authentiq_Options::Instance();
		}
	}

	function init() {
		add_filter('login_init', array($this, 'handle_backchannel_logout'), -1);
	}

	static function get_post_backchannel_logout_url() {
		$url = add_query_arg(array(
			AUTHENTIQ_OP_REDIRECT_QUERY_PARAM => 2,
		), wp_login_url());

		return $url;
	}

	function handle_backchannel_logout() {
		$logout_token = Authentiq_Helpers::query_vars('logout_token');

		if (Authentiq_Helpers::query_vars(AUTHENTIQ_OP_REDIRECT_QUERY_PARAM) != 2
			|| !$logout_token) {
			return;
		}

		$client_id = $this->options->get('client_id');
		$client_secret = $this->options->get('client_secret');

		// If logout succeeds
		$status_code = 200;

		try {
			$decoded_token = JWT::decode($logout_token, $client_secret, array('HS256'));

		} catch (Exception $e) {
			$msg = __('Error: There was an issue decoding the logout_token.', AUTHENTIQ_LANG);
			$msg .= '<br />' . $e->getMessage();

			error_log($msg);

			// If the logout request was invalid
			$status_code = 400;
		}

		// TODO: check if `iss` needs validation

		// Validate logout_token JWT
		if ($client_id !== $decoded_token->aud
			|| (!isset($decoded_token->sid) || !isset($decoded_token->sub))
			|| isset($decoded_token->nonce)
			|| !property_exists($decoded_token->events, 'http://schemas.openid.net/event/backchannel-logout')) {
			error_log('This isn\'t a valid logout_token.');

			// If the logout request was invalid
			$status_code = 400;
		}

		$user = Authentiq_User::get_user_by_sub($decoded_token->sub);
		if (!is_null($user)) {
			$user_id = $user->ID;
			$session_id = $decoded_token->sid;

			$has_signed_out_session = $this->logout_single_session($user_id, $session_id);

			// If we failed to find a single session for this user,
			// then logout all the user sessions
			if (!$has_signed_out_session) {
				$manager = WP_Session_Tokens::get_instance($user_id);
				$manager->destroy_all();
			}

		} else {
			// If the logout failed
			$status_code = 501;
		}

		status_header($status_code);
		header('Cache-Control: no-cache, no-store');
		header('Pragma: no-cache');
		// echo(json_encode(array()));
		exit;
	}

	function logout_single_session($user_id, $session_id) {

		$manager = WP_Session_Tokens::get_instance($user_id);

		if (empty($session_id)) {
			return false;
		}

		// Get all sessions for user
		$sessions = $manager->get_all();

		// Find users' WP session for this OP sid
		$current_session_string = null;
		foreach ($sessions as $session) {
			if (isset($session['authentiq_sid']) && $session['authentiq_sid'] === $session_id) {
				$current_session_string = implode(',', $session);
				break;
			}
		}

		if (!is_null($current_session_string)) {

			// Find WP session verifier for matched session so as we remove it
			$session_verifier = null;
			$user_sessions = get_user_meta($user_id, 'session_tokens', true);
			foreach ($user_sessions as $verifier => $sess) {
				$sess_string = implode(',', $sess);

				if ($current_session_string == $sess_string) {
					$session_verifier = $verifier;
					break;
				}
			}

			if (!is_null($session_verifier) && isset($user_sessions[$session_verifier])) {
				// Remove matched session, in order user signs out
				unset($user_sessions[$session_verifier]);

				if (!empty($user_sessions)) {
					update_user_meta($user_id, 'session_tokens', $user_sessions);
				} else {
					delete_user_meta($user_id, 'session_tokens');
				}

				return true;
			}
		}

		return false;
	}
}
