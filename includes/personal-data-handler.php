<?php

/**
 * Handles personal data export and erase. GDPR related
 */

add_filter('wp_privacy_personal_data_exporters', 'register_authentiq_data_exporter', 10);
add_filter('wp_privacy_personal_data_erasers', 'register_authentiq_data_eraser', 10);

/**
 * Export Authentiq user profile fields. GDPR related.
 */
function register_authentiq_data_exporter($exporters) {
	$exporters[AUTHENTIQ_NAME] = array(
		'exporter_friendly_name' => __('Authentiq', AUTHENTIQ_LANG),
		'callback' => 'authentiq_data_exporter',
	);

	return $exporters;
}

/**
 * Export Authentiq user profile fields. GDPR related.
 *
 * @param  string $email_address The user's email address.
 *
 * @return void
 */
function authentiq_data_exporter($email_address, $page = 1) {
	$email_address = trim($email_address);

	$data_to_export = array();

	$user = Authentiq_User::get_user_by_email($email_address);

	if (!$user) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$authentiq_id = Authentiq_User::get_authentiq_id($user->ID);
	$authentiq_userinfo = Authentiq_User::get_userinfo($user->ID);

	$user_data_to_export = array(
		array(
			'name'  => __('Authentiq ID', AUTHENTIQ_LANG),
			'value' => $authentiq_id,
		)
	);

	foreach ( $authentiq_userinfo as $key => $value ) {
		if (!empty($value)) {
			$name = $key;

			switch ($key) {
				case 'phone_number':
					$name = __('Phone number', AUTHENTIQ_LANG);
					break;
				case 'phone_number_verified':
					$name = __('Phone number verified', AUTHENTIQ_LANG);
					break;
				case 'phone_type':
					$name = __('Phone number type', AUTHENTIQ_LANG);
					break;
				case 'address':
					$name = __('Address', AUTHENTIQ_LANG);
					$value = $value['formatted'];
					break;
				case 'twitter':
					$name = __('Twitter username', AUTHENTIQ_LANG);
					$value = $value['username'];
					break;
				case 'facebook':
					$name = __('Facebook username', AUTHENTIQ_LANG);
					$value = $value['username'];
					break;
				case 'linkedin':
					$name = __('LinkedIn username', AUTHENTIQ_LANG);
					$value = $value['username'];
					break;
			}

			$user_data_to_export[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}
	}

	$data_to_export[] = array(
		'group_id' => 'user',
		'group_label' => __('Authentiq', AUTHENTIQ_LANG),
		'item_id' => $authentiq_id,
		'data' => $user_data_to_export,
	);

	return array(
		'data' => $data_to_export,
		'done' => true,
	);
}

/**
 * Delete Authentiq user profile fields. GDPR related.
 */
function register_authentiq_data_eraser($erasers) {
	$erasers[AUTHENTIQ_NAME] = array(
		'eraser_friendly_name' => __('Authentiq', AUTHENTIQ_LANG),
		'callback'             => 'authentiq_data_eraser',
		);

	return $erasers;
}

/**
 * Delete Authentiq user profile fields. GDPR related.
 *
 * @param  string $email_address The user's email address.
 *
 * @return void
 */
function authentiq_data_eraser($email_address, $page = 1) {
	$email_address = trim($email_address);

	$data_to_export = array();

	$user = Authentiq_User::get_user_by_email($email_address);

	if (!$user) {
		return array(
			'items_removed' => false,
			'items_retained' => false,
			'messages' => array(),
			'done' => true,
		);
	}

	$items_removed = false;
	$messages = array();

	if (Authentiq_User::has_authentiq_id($user->ID)) {
		Authentiq_User::delete_authentiq_id($user->ID);
		Authentiq_User::delete_userinfo($user->ID);

		$items_removed = true;
		$messages[] = __('Authentiq user data has been removed.', AUTHENTIQ_LANG);
	}

	return array(
		'items_removed' => $items_removed,
		'items_retained' => false,
		'messages' => $messages,
		'done' => true,
	);
}
