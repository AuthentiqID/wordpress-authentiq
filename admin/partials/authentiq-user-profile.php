<?php

/**
 * Add Authentiq functionality in user profile page
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/admin/partials
 */
?>

<table class="form-table authentiq-info">
	<?php if ($authentiq_id || $is_profile_page) : ?>
        <tr>
            <th>
                <h2><?php esc_html_e('Authentiq', AUTHENTIQ_LANG); ?></h2>
            </th>
            <td>
				<?php if (!$authentiq_id && $is_profile_page) : ?>
                    <a href="<?php echo Authentiq_Provider::get_authorize_url(); ?>" id="authentiq-link"
                       class="button button-primary"><?php esc_html_e('Link your Authentiq ID', AUTHENTIQ_LANG); ?></a>
				<?php elseif ($authentiq_id) : ?>
                    <a href="#" id="authentiq-unlink"
                       class="button button-primary">
						<?php
						if ($is_profile_page) {
							esc_html_e('Unlink your Authentiq ID', AUTHENTIQ_LANG);
						} else {
							esc_html_e('Unlink Authentiq ID', AUTHENTIQ_LANG);
						}
						?>
                    </a>
				<?php endif; ?>
            </td>
        </tr>
	<?php endif; ?>

	<?php if (!empty($userinfo['phone_number'])) : ?>
        <tr>
            <th>
				<?php esc_html_e('Phone number', AUTHENTIQ_LANG); ?>
            </th>
            <td>
				<?php echo $userinfo['phone_number']; ?>

				<?php if (!empty($userinfo['phone_number_verified'])) : ?>
                    <span class="description">(<?php esc_html_e('verified', AUTHENTIQ_LANG); ?>)</span>
				<?php endif; ?>
            </td>
        </tr>
	<?php endif; ?>

	<?php if (!empty($userinfo['address']['formatted'])) : ?>
        <tr>
            <th>
				<?php esc_html_e('Address', AUTHENTIQ_LANG); ?>
            </th>
            <td>
				<?php echo preg_replace('/$\R?^/m', ', ', $userinfo['address']['formatted']); ?>
            </td>
        </tr>
	<?php endif; ?>

	<?php if (!empty($userinfo['twitter'])) : ?>
        <tr>
            <th>
				<?php esc_html_e('Twitter', AUTHENTIQ_LANG); ?>
            </th>
            <td>
                <a href="<?php echo esc_attr($userinfo['twitter']['profile']); ?>" target="_blank">
                    @<?php echo $userinfo['twitter']['username']; ?>
                </a>
            </td>
        </tr>
	<?php endif; ?>

	<?php if (!empty($userinfo['facebook'])) : ?>
        <tr>
            <th>
				<?php esc_html_e('Facebook', AUTHENTIQ_LANG); ?>
            </th>
            <td>
                <a href="<?php echo esc_attr($userinfo['facebook']['profile']); ?>" target="_blank">
					<?php echo $userinfo['facebook']['username']; ?>
                </a>
            </td>
        </tr>
	<?php endif; ?>

	<?php if (!empty($userinfo['linkedin'])) : ?>
        <tr>
            <th>
				<?php esc_html_e('LinkedIn', AUTHENTIQ_LANG); ?>
            </th>
            <td>
                <a href="<?php echo esc_attr($userinfo['linkedin']['profile']); ?>" target="_blank">
					<?php echo $userinfo['linkedin']['username']; ?>
                </a>
            </td>
        </tr>
	<?php endif; ?>
</table>

<?php if ($authentiq_id) : ?>
    <script>
      jQuery(document).ready(function () {
        jQuery('#authentiq-unlink').click(function (e) {
          e.preventDefault();

          var data = {
            'action': '<?php echo Authentiq_Admin::UNLINK_AUTHENTIQ_ID_USER_ACTION; ?>',
            'aq_nonce': '<?php echo wp_create_nonce(Authentiq_Admin::UNLINK_AUTHENTIQ_ID_USER_ACTION); ?>',
            'unlink_user_id': <?php echo $user->ID; ?>
          };

          jQuery.post(ajaxurl, data)
                .done(function (data) {
                  if (typeof(data.success) === 'undefined' || data.success === false) {
                    if (typeof(data.data) === 'string') {
                      alert(data.data);
                    }
                    return;
                  }

                  // when user unlinks herself, do a reload, so as password fields are being displayed
                  if (typeof(data.current_user_unlinked) === 'undefined' || data.current_user_unlinked === true) {
                    location.reload();
                  }

                  jQuery('.authentiq-info').remove();
                })
                .fail(function (data, res) {
                  alert('<?php _e('Please try again.', AUTHENTIQ_LANG); ?>');
                });
        });
      });
    </script>
<?php endif; ?>
