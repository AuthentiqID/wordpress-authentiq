<?php

/**
 * Authentiq User class.
 *
 * Helper methods for User manipulation
 *
 * @since      1.0.0
 * @package    Authentiq
 * @subpackage Authentiq/includes
 * @author     The Authentiq Team <hello@authentiq.com>
 */

class Authentiq_User
{
	/**
	 * Parse WP user info from Authentiq userinfo.
	 *
	 * @param $userinfo
	 *
	 * @return array
	 */
	private static function get_user_data_from_userinfo($userinfo, $update = false) {
		// Create the user data array for updating WP user info
		$userdata = array();

		if (isset($userinfo->email) && is_email($userinfo->email)) {
			$email = sanitize_email($userinfo->email);
			$userdata['user_email'] = $email;
		}

		// Try to get username from Authentiq ID, when set
		if (isset($userinfo->preferred_username)) {
			$preferred_username = trim($userinfo->preferred_username);

			// only set nickname on user creation
			if (!$update) {
				$userdata['nickname'] = $preferred_username;
			}

			// default user_login is the $preferred_username
			$userdata['user_login'] = $preferred_username;
		}

		// if no user_login set so far, try to set one using the email
		if (!isset($userdata['user_login']) && $userdata['user_email']) {
			$email_parts = explode('@', $userdata['user_email']);
			$userdata['user_login'] = $email_parts[0];
		}

		if (isset($userinfo->given_name)) {
			$first_name = trim($userinfo->given_name);
			$userdata['first_name'] = $first_name;

			// if no user_login set, then use the first_name
			$userdata['user_login'] = !empty($userdata['user_login']) ? $userdata['user_login'] : strtolower($first_name);
		}

		if (isset($userinfo->family_name)) {
			$userdata['last_name'] = $userinfo->family_name;
		}

		// WP doesn't allow user_login to contain non english chars
		// as a fallback remove non english chars, so as email name can be used
		// with //translit you get a meaningful conversion to ASCII (e.g. ß -> ss)
		if ($userdata['user_login']) {
			$userdata['user_login'] = iconv('UTF-8', 'ASCII//TRANSLIT', $userdata['user_login']);
		}

		return $userdata;
	}

	/**
	 * Parse Authentiq userinfo that doesn't exist in a WP user.
	 *
	 * @param $userinfo
	 *
	 * @return array
	 */
	private static function get_authentiq_userinfo($userinfo, $update = false) {
		$userdata = array();

		if (isset($userinfo->phone_number)) {
			$userdata['phone_number'] = trim($userinfo->phone_number);

			if (isset($userinfo->phone_number_verified)) {
				$userdata['phone_number_verified'] = $userinfo->phone_number_verified;
			}

			if (isset($userinfo->phone_type)) {
				$userdata['phone_type'] = trim($userinfo->phone_type);
			}
		}

		if (isset($userinfo->address)) {
			$userdata['address'] = (array)$userinfo->address;
		}

		$twitter_scope = 'aq:social:twitter';
		if (isset($userinfo->$twitter_scope)) {
			$userdata['twitter'] = (array)$userinfo->$twitter_scope;
		}

		$facebook_scope = 'aq:social:facebook';
		if (isset($userinfo->$facebook_scope)) {
			$userdata['facebook'] = (array)$userinfo->$facebook_scope;
		}

		$linkedin_scope = 'aq:social:linkedin';
		if (isset($userinfo->$linkedin_scope)) {
			$userdata['linkedin'] = (array)$userinfo->$linkedin_scope;
		}

		return $userdata;
	}

	/**
	 * Create a new WP user from an Authentiq signin.
	 *
	 * @param      $userinfo
	 * @param null $role
	 *
	 * @return int|WP_Error
	 */
	public static function create_user($userinfo) {
		// FIXME: check if email is required
		if (empty($userinfo->email)) {
			$msg = __('Email is required by your site administrator.', AUTHENTIQ_LANG);
			throw new Authentiq_User_Creation_Failed_Exception($msg);
		}

		// Get WP user info from Authentiq userinfo
		$user_data = Authentiq_User::get_user_data_from_userinfo($userinfo);

		// Generate a random password, otherwise account creation fails
		$password = wp_generate_password(22);
		$user_data['user_pass'] = $password;

		// Check if username is already taken, and use another
		while (username_exists($user_data['user_login'])) {
			$user_data['user_login'] .= rand(1, 99);
		}

		/**
		 * Filters user data before the record is created or updated.
		 *
		 * It only includes data in the wp_users table wp_user, not any user metadata.
		 *
		 * @since 1.0.1
		 *
		 * @param array    $data          {
		 *                                Values and keys for the user.
		 *
		 * @type string    $user_login    The user's login. Only included if $update == false
		 * @type string    $user_pass     The user's password.
		 * @type string    $user_email    The user's email.
		 * @type string    $user_url      The user's url.
		 * @type string    $user_nickname The user's nickname.
		 * @type string    $display_name  The user's display name.
		 * }
		 *
		 * @param bool     $update        Whether the user is being updated rather than created.
		 * @param int|null $id            ID of the user to be updated, or NULL if the user is being created.
		 */
		$user_data = apply_filters('authentiq_pre_insert_user_data', $user_data, false, null);

		/**
		 * Filters if we can create this user
		 *
		 * @param bool $allow
		 * @param int  $userinfo
		 */
		$valid_user = apply_filters('authentiq_should_create_user', true, $user_data);
		if (!$valid_user) {
			return -2;
		}

		// Create the user
		$user_id = wp_insert_user($user_data);

		// Link Authentiq ID profile sub to WP user
		Authentiq_User::update_authentiq_id($user_id, $userinfo);

		// Add Authentiq extra info to WP user profile
		$authentiq_userinfo = Authentiq_User::get_authentiq_userinfo($userinfo);
		if (!empty($authentiq_userinfo)) {
			Authentiq_User::update_userinfo($user_id, $authentiq_userinfo);
		}

		if (!is_numeric($user_id)) {
			return $user_id;
		}

		/**
		 * Fires after a WP user is created from an Authentiq signin
		 *
		 * @param int    $user_id
		 * @param object $user_data WP User data
		 */
		do_action('authentiq_user_created', $user_id, $user_data);

		return $user_id;
	}

	/**
	 * Update a WP user after an Authentiq signin.
	 *
	 * @param $user
	 * @param $userinfo
	 *
	 * @return int|WP_Error
	 * @throws Authentiq_User_Exception
	 */
	public static function update_user($user, $userinfo) {
		if (is_null($user)) {
			$msg = __('No user set to be updated.', AUTHENTIQ_LANG);
			throw new Authentiq_User_Exception($msg);
		}

		// Get WP user info from Authentiq userinfo
		$user_data = Authentiq_User::get_user_data_from_userinfo($userinfo, true);

		/**
		 * Filters user data before the record is created or updated.
		 *
		 * It only includes data in the wp_users table wp_user, not any user metadata.
		 *
		 * @since 1.0.1
		 *
		 * @param array    $data          {
		 *                                Values and keys for the user.
		 *
		 * @type string    $user_login    The user's login. Only included if $update == false
		 * @type string    $user_pass     The user's password.
		 * @type string    $user_email    The user's email.
		 * @type string    $user_url      The user's url.
		 * @type string    $user_nickname The user's nickname.
		 * @type string    $display_name  The user's display name.
		 * }
		 *
		 * @param bool     $update        Whether the user is being updated rather than created.
		 * @param int|null $id            ID of the user to be updated, or NULL if the user is being created.
		 */
		$user_data = apply_filters('authentiq_pre_insert_user_data', $user_data, true, $user->data->ID);

		$user_data['ID'] = $user->data->ID;

		// Update the WP user
		$user_id = wp_update_user($user_data);

		// Link Authentiq ID profile sub to WP user
		Authentiq_User::update_authentiq_id($user_id, $userinfo);

		// Add Authentiq extra info to WP user profile
		$authentiq_userinfo = Authentiq_User::get_authentiq_userinfo($userinfo, false);
		if (!empty($authentiq_userinfo)) {
			Authentiq_User::update_userinfo($user_id, $authentiq_userinfo);
		}

		if (!is_numeric($user_id)) {
			return $user_id;
		}

		/**
		 * Fires after a WP user is updated from an Authentiq signin
		 *
		 * @param int    $user_id
		 * @param object $user_data WP User data
		 */
		do_action('authentiq_user_updated', $user_id, $user_data);

		return $user_id;
	}

	/**
	 * Get a WP user by email
	 *
	 * @param $email
	 *
	 * @return false|null|WP_User
	 */
	public static function get_user_by_email($email) {
		global $wpdb;

		if (empty($email)) {
			return null;
		}

		$user = get_user_by('email', $email);

		if ($user instanceof WP_Error) {
			return null;
		}

		return $user;
	}

	/**
	 * Get a WP user by Authentiq ID sub
	 *
	 * @param $id
	 *
	 * @return null
	 */
	public static function get_user_by_sub($id) {
		global $wpdb;

		// TODO: throw error if no query

		if (empty($id)) {
			return null;
		}

		$query = array(
			'meta_key' => $wpdb->prefix . 'authentiq_id',
			'meta_value' => $id,
			'blog_id' => false,
			'number' => 1,
			'count_total' => false,
		);

		$users = get_users($query);

		if ($users instanceof WP_Error) {
			return null;
		}

		if (!empty($users)) {
			return $users[0];
		}

		return null;
	}

	public static function has_authentiq_id($user_id) {
		return !empty(self::get_authentiq_id($user_id));
	}

	public static function get_authentiq_id($user_id) {
		global $wpdb;

		return get_user_meta($user_id, $wpdb->prefix . 'authentiq_id', true);
	}

	public static function update_authentiq_id($user_id, $userinfo) {
		global $wpdb;
		update_user_meta($user_id, $wpdb->prefix . 'authentiq_id', $userinfo->sub);
	}

	public static function delete_authentiq_id($user_id) {
		global $wpdb;
		delete_user_meta($user_id, $wpdb->prefix . 'authentiq_id');
	}

	public static function get_userinfo($user_id) {
		global $wpdb;

		return get_user_meta($user_id, $wpdb->prefix . 'authentiq_obj', true);
	}

	public static function update_userinfo($user_id, $data) {
		global $wpdb;
		update_user_meta($user_id, $wpdb->prefix . 'authentiq_obj', $data);
	}

	public static function delete_userinfo($user_id) {
		global $wpdb;
		delete_user_meta($user_id, $wpdb->prefix . 'authentiq_obj');
	}
}
