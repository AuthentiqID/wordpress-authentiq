<?php

use \Firebase\JWT\JWT;

/**
 * The Authentiq Provider class.
 *
 * Handles the OAuth2 handshakes, OpenID and WP user login/registration.
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */
class Authentiq_Provider
{
	/**
	 * This cookie is used in the Authentiq OAuth2 handshake for the state parameter.
	 */
	public static $cookie_name = 'wp_authentiq_state';
	protected $options;
	private $authentiq_sid = null;

	public function __construct($options = null) {
		if ($options instanceof Authentiq_Options) {
			$this->options = $options;
		} else {
			$this->options = Authentiq_Options::Instance();
		}
	}

	public function init() {
		// Initialize Authentiq state for OAuth2 handshake
		add_action('init', array($this, 'initialize_state'));

		// Auto-login with Authentiq when set in options
		add_action('login_init', array($this, 'auto_login_with_authentiq'));

		// Authenticate with Authentiq if there is a valid OAuth2 code
		add_action('authenticate', array($this, 'handle_authentiq_redirect'), 10, 3);

		// Disable WP password authentication based on Authentiq plugin settings
		add_action('wp_authenticate_user', array($this, 'disable_wp_password_login'));

		// Disable WP password reset based on Authentiq plugin settings
		add_filter('allow_password_reset', array($this, 'allow_password_reset'), 10, 2);

		// Custom Authentiq plugin actions for logout
		add_action('wp_logout', array($this, 'logout'));
		add_action('wp_login', array($this, 'end_wp_session'));
	}

	public static function get_redirect_url() {
		$url = add_query_arg(array(
			AUTHENTIQ_OP_REDIRECT_QUERY_PARAM => 1,
		), wp_login_url());

		return $url;
	}

	public static function get_authorize_url($extra_scopes = array()) {
		global $wp;

		$options = Authentiq_Options::Instance();

		$client_id = urlencode($options->get('client_id'));

		// Always request
		// 1. `openid` scope for fetching an id_token
		// 2. `aq:push` for push notifications support on subsequent logins
		// 3. `email~rs` this is used to create/link a WP user, for this reason email has to be signed
		// 4. rest are being used for WP profile
		$predefined_scopes = array('openid', 'aq:push', 'email~rs', 'aq:name', 'aq:username');

		// WP site requested scopes
		$scopes = $options->get('client_scopes');
		$scopes = array_merge($scopes, $predefined_scopes);

		if (!empty($extra_scopes) && is_array($extra_scopes)) {
			$scopes = array_merge($scopes, $extra_scopes);
		}

		/**
		 * Filters the requested scopes from Authentiq
		 *
		 * @param array $scopes Scopes that will be requested from Authentiq
		 */
		$scopes = apply_filters('authentiq_requested_scopes', $scopes);

		$scope = urlencode(implode(' ', $scopes));

		$state_obj = array(
			'state' => self::get_state(),
		);

		// Pass redirect_to with state, in order we can redirect back after sign-in
		$redirect_to = home_url(add_query_arg(array(), $wp->request));
		if (!empty($redirect_to)) {
			$state_obj['redirect_to'] = $redirect_to;
		}

		$state = base64_encode(json_encode($state_obj));

		$url = add_query_arg(array(
			'client_id' => $client_id,
			'response_mode' => 'query',
			'response_type' => 'code',
			'redirect_uri' => urlencode(Authentiq_Provider::get_redirect_url()),
			'scope' => $scope,
			'prompt' => 'login',
			'nonce' => wp_create_nonce(),
			'state' => urlencode($state),
		), AUTHENTIQ_PROVIDER_AUTHORIZE_URL);

		return $url;
	}

	/**
	 * It forces user to the Authentiq Provider using a redirect when allowed from plugin options.
	 */
	function auto_login_with_authentiq() {
		if (Authentiq_Helpers::query_vars(AUTHENTIQ_OP_REDIRECT_QUERY_PARAM) !== null) {
			return;
		}

		$auto_login = $this->options->get('auto_login');

		if ($auto_login
			&& (!isset($_GET['action']) || 'logout' !== $_GET['action'])
			&& !isset($_GET[AUTHENTIQ_LOGIN_FORM_QUERY_PARAM])
			&& strtolower($_SERVER['REQUEST_METHOD']) === 'get') {

			wp_redirect(Authentiq_Provider::get_authorize_url());
			die();
		}
	}

	/**
	 * Handle Authentiq Provider redirects.
	 */
	function handle_authentiq_redirect() {
		if (Authentiq_Helpers::query_vars(AUTHENTIQ_OP_REDIRECT_QUERY_PARAM) === null) {
			return;
		}

		try {
			$this->handle_oauth_flow();

		} catch (Exception $e) {
			return new WP_Error('login_error', $e->getMessage());
		}

		// } catch (Authentiq_Login_Redirect_Exception $e) {
		// 	return new WP_Error('login_error', $e->getMessage());
		//
		// } catch (Exception $e) {
		//
		// 	$msg = '';
		//
		// 	if ($e instanceof Authentiq_Login_Flow_Validation_Exception) {
		// 		$msg .= '<h4>' . __('You failed to log in, because of invalid session.', AUTHENTIQ_LANG) . '</h4>';
		//
		// 	} else {
		// 		$msg .= '<h4>' . __('Ooops.', AUTHENTIQ_LANG) . '</h4>';
		// 	}
		// 	$msg .= $e->getMessage();
		// 	$msg .= '<br/><br/>';
		// 	$msg .= '<a href="' . home_url() . '">' . __('← Back to site', AUTHENTIQ_LANG) . '</a>';
		// 	$msg .= ' | <a href="' . wp_login_url() . '">' . __('Login with Authentiq', AUTHENTIQ_LANG) . '</a>';
		//
		// 	wp_die($msg);
		// }
	}

	/**
	 * Handle OAuth 2.0 errors from Authentiq Provider
	 *
	 * @throws Authentiq_Login_Redirect_Exception
	 */
	static function check_oauth_flow_error() {
		if (!empty(Authentiq_Helpers::query_vars('error_description'))) {
			throw new Authentiq_Login_Redirect_Exception(Authentiq_Helpers::query_vars('error_description'));
		}

		if (!empty(Authentiq_Helpers::query_vars('error'))) {
			throw new Authentiq_Login_Redirect_Exception(Authentiq_Helpers::query_vars('error'));
		}
	}

	/**
	 * Handle OAuth 2.0 flow after redirect back from Authentiq Provider
	 *
	 * 1. Check if OP returned an error
	 * 2. Verify `state` returned from Authentiq Provider
	 * 3. Exchange code for tokens with Authentiq Provider
	 * 4. If no id_token, fetch userinfo from Authentiq Provider
	 * 5. If id_token exists, use id_token to get userinfo
	 * 6. Handle errors
	 *
	 * @throws Authentiq_Login_Flow_Validation_Exception
	 * @throws Authentiq_Login_Redirect_Exception
	 * @throws Exception
	 */
	function handle_oauth_flow() {

		// 1. Check if OP returned an error
		self::check_oauth_flow_error();

		// 2. Verify `state` returned from Authentiq Provider
		$request_state = Authentiq_Helpers::query_vars('state');
		$state_obj = (array)json_decode(base64_decode($request_state));
		$state = isset($state_obj['state']) ? $state_obj['state'] : '';
		self::verify_state($state);

		// Get client info from plugin settings
		$client_id = $this->options->get('client_id');
		$client_secret = $this->options->get('client_secret');

		if (empty($client_id)) {
			throw new Authentiq_Login_Redirect_Exception(__('Error: Your Authentiq Client ID has not set in the Authentiq plugin settings.', AUTHENTIQ_LANG));
		}

		if (empty($client_secret)) {
			throw new Authentiq_Login_Redirect_Exception(__('Error: Your Authentiq Client Secret has not set in the Authentiq plugin settings.', AUTHENTIQ_LANG));
		}

		// 3. Exchange code for tokens with Authentiq Provider
		$code = Authentiq_Helpers::query_vars('code');

		$token_response = $this->get_token($client_id, $client_secret, 'authorization_code', array(
			'redirect_uri' => Authentiq_Provider::get_redirect_url(),
			'code' => $code,
		));

		$data = json_decode($token_response['body']);

		// if tokens found
		if (isset($data->access_token) || isset($data->id_token)) {

			// 4. If no id_token, fetch userinfo from Authentiq Provider
			if (!isset($data->id_token)) {
				$data->id_token = null;
				$userinfo_response = $this->get_user_info($data->access_token);
				$userinfo = json_decode($userinfo_response['body']);

				// 5. If id_token exists, use id_token to get userinfo
			} else {
				try {
					$decoded_token = JWT::decode($data->id_token, $client_secret, array('HS256'));

				} catch (Exception $e) {
					$msg = __('Error: There was an issue decoding the id_token.', AUTHENTIQ_LANG);
					$msg .= '<br />' . $e->getMessage();

					throw new Authentiq_Login_Flow_Validation_Exception($msg);
				}

				// Validate that this JWT was made for us
				if ($client_id !== $decoded_token->aud) {
					throw new Exception('This token is not intended for us.');
				}

				// Validate that nonce is correct, when set
				// TODO: nonce has to be set
				if (!empty($decoded_token->nonce) && !wp_verify_nonce($decoded_token->nonce)) {
					throw new Exception('This token is not correct.');
				}

				$userinfo = $decoded_token;
			}

			$user = $this->handle_wp_user($userinfo, $data->id_token, $data->access_token);

			// Perform user login to WP
			if ($user) {
				// check if a redirect URL is set and user role meets the condition to redirect the page
				$default_login_redirection_applies_to = $this->options->get('default_login_redirection_applies_to');
				if ($default_login_redirection_applies_to === 'all'
					|| in_array($default_login_redirection_applies_to, (array)$user->roles)) {
					$redirect_to = $this->options->get('default_login_redirection');
				}

				if (empty($redirect_to)) {
					// Default redirect URL to frontend home page
					$redirect_to = home_url();

					// if user is admin, then move to the admin area
					if (is_super_admin($user->ID)) {
						$redirect_to = admin_url();
					}

					if (isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to'])) {
						$redirect_to = $_REQUEST['redirect_to'];

					} else if (isset($state_obj['redirect_to']) && !empty($state_obj['redirect_to'])) {
						$redirect_to = $state_obj['redirect_to'];
					}
				}

				/**
				 * Filters Authentiq redirect_to page after a user sign in.
				 *
				 * @since 1.0.0
				 *
				 * @param string  $redirect_to Page where user will be redirected to
				 * @param WP_User $user        WP_User object
				 */
				$redirect_to = apply_filters('authentiq_redirect_to_after_signin', $redirect_to, $user);

				wp_safe_redirect($redirect_to);
				die();

			} else {
				throw new Exception(__('Failed to login using Authentiq.', AUTHENTIQ_LANG));
			}

			// 6. Handle errors
		} elseif (is_array($token_response['response']) && 401 === (int)$token_response['response']['code']) {
			$msg = __('Error: the Client Secret configured on the Authentiq plugin is wrong. Make sure to copy the right one from the Authentiq dashboard.', AUTHENTIQ_LANG);

			throw new Authentiq_Login_Flow_Validation_Exception($msg);

		} else {
			if (!empty($data->error_description)) {
				throw new Exception($data->error_description);
			}

			if (!empty($data->error)) {
				throw new Exception($data->error);
			}

			throw new Exception(__('Failed to login using Authentiq.', AUTHENTIQ_LANG));
		}
		die();
	}

	/**
	 * Exchange code for tokens with Authentiq Provider
	 *
	 * @param        $client_id
	 * @param        $client_secret
	 * @param string $grantType
	 * @param array  $request_params
	 *
	 * @return array|bool|WP_Error
	 * @throws Authentiq_Login_Flow_Validation_Exception
	 */
	static function get_token($client_id, $client_secret = null, $grantType = 'client_credentials', $request_params = array()) {
		if (!is_array($request_params)) {
			$request_params = array();
		}

		$endpoint = AUTHENTIQ_PROVIDER_TOKEN_URL;

		$request_params['client_id'] = $client_id;
		$request_params['client_secret'] = is_null($client_secret) ? '' : $client_secret;
		$request_params['grant_type'] = $grantType;

		$headers = array(
			'content-type' => 'application/x-www-form-urlencoded',
		);

		$response = wp_remote_post($endpoint, array(
			'headers' => $headers,
			'body' => $request_params,
		));

		if ($response instanceof WP_Error) {
			$msg = $response->get_error_message();

			error_log($msg);

			throw new Authentiq_Login_Flow_Validation_Exception($msg);
		}

		return $response;
	}

	/**
	 * Get userinfo from Authentiq Provider
	 *
	 * @param $access_token
	 *
	 * @return array|WP_Error
	 * @throws Authentiq_Login_Flow_Validation_Exception
	 */
	static function get_user_info($access_token) {

		$endpoint = AUTHENTIQ_PROVIDER_USERINFO_URL;

		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
		);

		$response = wp_remote_get($endpoint, array(
			'headers' => $headers,
		));

		if ($response instanceof WP_Error) {
			error_log($response->get_error_message());

			throw new Authentiq_Login_Flow_Validation_Exception();
		}

		return $response;
	}

	/**
	 * Filters if user is allowed to sign in based on the email domain
	 *
	 * @param $email
	 *
	 * @throws Authentiq_Login_Flow_Validation_Exception
	 */
	function filter_user_by_email_domain($email) {
		// Get domains from plugin settings
		$domains_filter = $this->options->get('filter_user_domains');

		// 0: whitelist domains, 1: blacklist domains
		$filter_user_domains_condition = $this->options->get('filter_user_domains_condition');

		// split domains per new line
		$domains_filter = preg_split('/$\R?^/m', $domains_filter);

		// remove empty values
		$domains_filter = array_filter($domains_filter);

		/**
		 * Filters if user is allowed to sign in based on the email domain
		 *
		 * @param array $domains_filter                Domains defined in Authentiq plugin settings
		 * @param int   $filter_user_domains_condition -> 0: whitelist domains, 1: blacklist domains
		 */
		$domains_filter = apply_filters('authentiq_domain_filter', $domains_filter, $filter_user_domains_condition);

		// remove empty values
		$domains_filter = array_filter($domains_filter);

		// no domains to parse
		if (empty($domains_filter)) {
			return;
		}

		list($current_email_user, $current_email_domain) = explode('@', $email);

		$current_email_domain = trim(strtolower($current_email_domain));

		// blacklist domains
		if ($filter_user_domains_condition == 1) {
			if (in_array($current_email_domain, $domains_filter)) {
				$msg = __('This email domain (%s) is not allowed by site administrator.', AUTHENTIQ_LANG);
				$msg = sprintf($msg, $current_email_domain);

				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}

			// whitelist domains
		} else {
			if (!in_array($current_email_domain, $domains_filter)) {
				$msg = __('This email domain (%s) is not whitelisted by site administrator.', AUTHENTIQ_LANG);
				$msg = sprintf($msg, $current_email_domain);

				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}
		}
	}

	/**
	 * Create new, Update or Link user
	 * and login this user to WordPress.
	 *
	 * returns true if login was successful, false otherwise
	 *
	 * @param $userinfo
	 * @param $id_token
	 * @param $access_token
	 *
	 * @return WP_User|false WP_User object on success, false on failure
	 * @throws Authentiq_Login_Flow_Validation_Exception
	 */
	function handle_wp_user($userinfo, $id_token, $access_token) {

		$email = $userinfo->email;
		$email_verified = $userinfo->email_verified;

		// If the userinfo has no email or an unverified email,
		// and in the options we require a verified email
		// notify the user she has to send a verified email from AuthentiqID App
		$requires_verified_email = $this->options->get('requires_verified_email');
		if ($requires_verified_email) {
			if (empty($email)) {
				$msg = __('Please set an email at AuthentiqID App.', AUTHENTIQ_LANG);
				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}

			if (empty($email_verified)) {
				$msg = __('Please verify the email used at AuthentiqID App.', AUTHENTIQ_LANG);
				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}
		}

		// Filter user based on email domain
		if (!empty($email)) {
			$this->filter_user_by_email_domain($email);
		}

		// Get existing user for this Authentiq sub (user_id)
		if (isset($userinfo->sub)) {
			$user = Authentiq_User::get_user_by_sub($userinfo->sub);

			if (!is_null($user) && $user) {
				$other_user_using_sub = $user;
			}

			// TODO: check if another WP exists with same email
			// allow user to merge both WP users if email is verified
		}

		// Check if the user was already signed in, which means that user wanted to link his account
		if (is_user_logged_in()) {
			if (!empty($other_user_using_sub)) {
				$msg = __('This Authentiq ID is already linked to another WordPress user.', AUTHENTIQ_LANG);
				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}

			$current_user = wp_get_current_user();

			// Check if this user is already linked to another Authentiq ID
			if (Authentiq_User::has_authentiq_id($current_user->ID)) {
				$msg = __('Current WordPress user is already linked with another Authentiq ID.', AUTHENTIQ_LANG);
				throw new Authentiq_Login_Flow_Validation_Exception($msg);
			}

			$user = wp_get_current_user();
		}

		// Link existing WP user using email if is verified
		if ((is_null($user) || !$user) && !empty($email) && $email_verified) {
			$user = Authentiq_User::get_user_by_email($email);
		}

		$user = apply_filters('authentiq_get_wp_user', $user, $userinfo);

		// If a WP user found
		if (!is_null($user) && $user) {

			// Update WP user with userinfo
			$user_id = Authentiq_User::update_user($user, $userinfo);

			// Check if user was created
			if (is_wp_error($user_id)) {
				throw new Authentiq_User_Exception($user_id->get_error_message());
			}

			// Store Authentiq session id sent from Provider,
			// in order we can logout this single session from the App
			if (!empty($userinfo->sid)) {
				$this->authentiq_sid = $userinfo->sid;
				add_filter('attach_session_information', array($this, 'authentiq_attach_session_information'));
			}

			$this->login_user_to_wp($user, $userinfo, false, $id_token, $access_token);

			return $user;

		} else {
			try {
				$allow_signup = $this->options->is_wp_registration_enabled();
				if ($allow_signup) {

					// Create a new WP user
					$user_id = Authentiq_User::create_user($userinfo);

					// Check if user was created
					if (is_wp_error($user_id)) {
						$msg = __('Failed to create WordPress user', AUTHENTIQ_LANG);
						$msg .= ': ' . $user_id->get_error_message();
						throw new Authentiq_User_Exception($msg);
					}

				} elseif (!$allow_signup) {
					throw new Authentiq_User_Exception(__('User registration is not allowed by site adminitrator.', AUTHENTIQ_LANG));
				}

				// Store Authentiq session id sent from Provider,
				// in order we can logout this single session from the App
				if (!empty($userinfo->sid)) {
					$this->authentiq_sid = $userinfo->sid;
					add_filter('attach_session_information', array($this, 'authentiq_attach_session_information'));
				}

				$user = get_user_by('id', $user_id);

				$this->login_user_to_wp($user, $userinfo, true, $id_token, $access_token);

				return $user;

			} catch (Authentiq_User_Exception $e) {
				throw new Authentiq_Login_Flow_Validation_Exception($e->getMessage());
			}
		}

		return false;
	}

	/**
	 * Add Authentiq session_id sent from Provider in WP session,
	 * in order we can logout this single session from the App.
	 *
	 * @param $session
	 *
	 * @return session object
	 */
	function authentiq_attach_session_information($session) {
		$session['authentiq_sid'] = $this->authentiq_sid;

		return $session;
	}

	/**
	 * Login the user to WordPress, using WP hooks and actions as appropriate
	 *
	 * @param $user
	 * @param $userinfo
	 * @param $is_new : `true` if the user was created on WordPress, `false` if not.
	 * @param $id_token
	 * @param $access_token
	 *
	 * @throws Authentiq_Before_Login_Exception
	 */
	private function login_user_to_wp($user, $userinfo, $is_new, $id_token, $access_token) {

		// Allow other hooks to run prior to login, and handle exception
		try {
			do_action('authentiq_before_login', $user);
		} catch (Exception $e) {
			throw new Authentiq_Before_Login_Exception($e->getMessage());
		}

		wp_set_auth_cookie($user->ID, true);

		/**
		 * Fires after the user has successfully logged in.
		 *
		 * @param string  $user_login Username.
		 * @param WP_User $user       WP_User object of the logged-in user.
		 */
		do_action('wp_login', $user->user_login, $user);

		do_action('authentiq_user_login', $user->ID, $userinfo, $is_new, $id_token, $access_token);
	}

	public function end_wp_session() {
		if (session_id()) {
			session_destroy();
		}
	}

	public function logout() {
		$this->end_wp_session();

		// TODO: handle logout at Authentiq Provider
	}

	function disable_wp_password_login($user) {
		global $wpdb;

		$allow_classic_wp_login = $this->options->allow_classic_wp_login();

		if (!$allow_classic_wp_login) {
			$msg = __('Passwords have been disabled. Try to login with Authentiq.', AUTHENTIQ_LANG);

			return new WP_Error('wp_passwords_disabled', $msg);

		} else {
			// accepts certain users authenticating based on this rule
			// 0: all users, 1: users without Authentiq ID, 2: no users
			$allow_classic_wp_login_for = $this->options->get('classic_wp_login_for');

			if ($allow_classic_wp_login_for == 1) {
				// check if user has already linked with Authentiq ID
				$authentiq_id = get_user_meta($user->ID, $wpdb->prefix . 'authentiq_id', true);

				if ($authentiq_id) {
					$msg = __('You have already linked your account with Authentiq ID. Please use Authentiq ID to sign in.', AUTHENTIQ_LANG);

					return new WP_Error('wp_login_failed_authentiq_linked', $msg);
				}

			} else if ($allow_classic_wp_login_for == 2) {
				$msg = __('Passwords have been disabled. Try to login with Authentiq.', AUTHENTIQ_LANG);

				return new WP_Error('wp_passwords_disabled', $msg);
			}

			return $user;
		}
	}

	function allow_password_reset($allow, $user_id) {
		global $wpdb;

		// if it's not allowed already by another plugin, then follow the rules set
		if (!$allow) {
			return false;
		}

		$allow_wp_password_login = $this->options->allow_classic_wp_login();

		if ($allow_wp_password_login) {
			// accepts certain users authenticating based on this rule
			// 0: all users, 1: users without Authentiq ID, 2: no users
			$allow_classic_wp_login_for = $this->options->get('classic_wp_login_for');

			if ($allow_classic_wp_login_for == 1) {
				// check if user has already linked with Authentiq ID
				$authentiq_id = get_user_meta($user_id, $wpdb->prefix . 'authentiq_id', true);

				// allow reset password only when user is not linked with Authentiq
				return !$authentiq_id;
			}

			return $allow_classic_wp_login_for < 2;
		}

		return false;
	}

	static function initialize_state($override = false) {
		if (!$override && isset($_COOKIE[self::$cookie_name]) && $_COOKIE[self::$cookie_name]) return;

		$state = wp_generate_password(24, false);
		// store it as a session cookie
		@setcookie(self::$cookie_name, $state, 0, '/', '', is_ssl(), true);
		$_COOKIE[self::$cookie_name] = $state;

		return $state;
	}

	static function get_state() {
		if (!isset($_COOKIE[self::$cookie_name]) || !$_COOKIE[self::$cookie_name]) self::initialize_state(true);

		return $_COOKIE[self::$cookie_name];
	}

	static function verify_state($request_state = '') {
		$current_state = self::get_state();

		if ($request_state && $current_state && $current_state == $request_state) {
			self::initialize_state(true);

			return true;
		} else {
			$msg = __('The Authentiq state parameter can not be verified. ' .
				'This may be due to this page being cached by another WordPress plugin. Please refresh your page and try again.', AUTHENTIQ_LANG);
			throw new Authentiq_Login_Flow_Validation_Exception($msg);
		}
	}
}
