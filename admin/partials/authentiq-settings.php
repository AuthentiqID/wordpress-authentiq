<?php

/**
 * The admin area for the Authentiq plugin
 *
 * @link       https://www.authentiq.com
 * @since      1.0.0
 *
 * @package    Authentiq
 * @subpackage Authentiq/admin/partials
 */
?>

<div class="wrap">
    <h1>
        <div id="icon-themes" class="icon32"></div>
		<?php esc_html_e('Authentiq configuration page', AUTHENTIQ_LANG); ?>
    </h1>

    <hr/>

    <form method="post" action="options.php">
		<?php
		settings_fields('authentiq_settings');
		do_settings_sections('authentiq_settings');
		?>

        <h2><?php esc_html_e('Client', AUTHENTIQ_LANG); ?></h2>

        <p class="description">
			<?php _e('Authentiq client details, retrieved from <a href="https://dashboard.authentiq.com">Authentiq Dashboard</a>.', AUTHENTIQ_LANG) ?>
        </p>

		<?php if (!$this->options->is_configured()) : ?>
            <div class="authentiq-configuration-warning" style="background: #fff; margin-top: 15px; padding: 1px 12px;">

				<?php if ($this->options->get('wsl_migration')) {
					$instructions = __(<<<TXT
                        <h4>Update your existing client:</h4>
                        <ol>
                            <li>First go to: <a href="https://dashboard.authentiq.com">Authentiq Dashboard</a> and sign in.</li>
                            <li>Locate your existing client for this site.</li>
                            <li>Change the "Redirect URIs" for your application to:<br /><strong>%s</strong></li>
                            <li>Set the "Backchannel Logout URL" for your application (under advanced options):<br /><strong>%s</strong></li>
                            <li>Click "Save".</li>
                        </ol>
    
                        <p>
                          And you're all set!
                          <br />
                          %s
                        </p>
    
                        <p>
                            P.S.: If you were using WordPress Social Login plugin just for Authentiq, then feel free to deactivate it.
                        </p>
TXT
						, AUTHENTIQ_LANG);

					$configured_button = '<a href="#" id="authentiq-unlink" class="button button-primary">' . __('Done', AUTHENTIQ_LANG) . '</a>';

					printf(
						$instructions,
						Authentiq_Provider::get_redirect_url(),
						Authentiq_Backchannel_Logout::get_post_backchannel_logout_url(),
						$configured_button
					);

				} else {
					$instructions = __(<<<TXT
                        <h4>Register a new client:</h4>
                        <ol>
                            <li>First go to: <a href="https://dashboard.authentiq.com">Authentiq Dashboard</a> and sign in.</li>
                            <li>Create a new application.</li>
                            <li>Fill out any required fields such as the application name and description.</li>
                            <li>Set the "Redirect URIs" for your application:<br /><strong>%s</strong></li>
                            <li>Set the "Backchannel Logout URL" for your application (under advanced options):<br /><strong>%s</strong></li>
                            <li>Click "Save".</li>
                            <li>Once registered, paste the created application credentials into the boxes below.</li>
                        </ol>
TXT
						, AUTHENTIQ_LANG);

					printf(
						$instructions,
						Authentiq_Provider::get_redirect_url(),
						Authentiq_Backchannel_Logout::get_post_backchannel_logout_url()
					);
				} ?>

            </div>
		<?php endif; ?>

        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label
                            for="authentiq_settings[client_id]"><?php esc_html_e('Client ID', AUTHENTIQ_LANG) ?></label>
                </th>
                <td>
                    <input type="text" name="authentiq_settings[client_id]" id="authentiq_settings[client_id]"
                           value="<?php echo esc_attr($this->options->get('client_id')); ?>" class="regular-text">

                </td>
            </tr>
            <tr>
                <th scope="row"><label
                            for="authentiq_settings[client_secret]"><?php esc_html_e('Client Secret', AUTHENTIQ_LANG) ?></label>
                </th>
                <td>
                    <input type="password" name="authentiq_settings[client_secret]"
                           id="authentiq_settings[client_secret]"
                           value="<?php echo esc_attr($this->options->get('client_secret')); ?>"
                           class="regular-text">

                </td>
            </tr>
            <tr>
                <th scope="row"><label
                            for="client_scopes"><?php esc_html_e('Requested scopes', AUTHENTIQ_LANG) ?></label></th>
                <td>
                    <p class="description"><?php esc_html_e('Additional scopes requested from Authentiq ID on user sign in.', AUTHENTIQ_LANG) ?></p>

					<?php $client_scopes = $this->options->get('client_scopes', array()); ?>

                    <fieldset>
                        <label for="authentiq_settings[client_scopes][phone]">
                            <input type="checkbox" name="authentiq_settings[client_scopes][]"
                                   id="authentiq_settings[client_scopes][phone]" value="phone"
								<?php if (in_array('phone', $client_scopes)) echo ' checked="checked"'; ?>>
                            <span class="description"><?php esc_html_e('Phone number', AUTHENTIQ_LANG) ?></span>
                        </label>
                    </fieldset>

                    <fieldset>
                        <label for="authentiq_settings[client_scopes][address]">
                            <input type="checkbox" name="authentiq_settings[client_scopes][]"
                                   id="authentiq_settings[client_scopes][address]" value="address"
								<?php if (in_array('address', $client_scopes)) echo ' checked="checked"'; ?>>
                            <span class="description"><?php esc_html_e('Address', AUTHENTIQ_LANG) ?></span>
                        </label>
                    </fieldset>

                    <fieldset>
                        <label for="authentiq_settings[client_scopes][aq:social:twitter]">
                            <input type="checkbox" name="authentiq_settings[client_scopes][]"
                                   id="authentiq_settings[client_scopes][aq:social:twitter]"
                                   value="aq:social:twitter"
								<?php if (in_array('aq:social:twitter', $client_scopes)) echo ' checked="checked"'; ?>>
                            <span class="description"><?php esc_html_e('Twitter', AUTHENTIQ_LANG) ?></span>
                        </label>
                    </fieldset>

                    <fieldset>
                        <label for="authentiq_settings[client_scopes][aq:social:facebook]">
                            <input type="checkbox" name="authentiq_settings[client_scopes][]"
                                   id="authentiq_settings[client_scopes][aq:social:facebook]"
                                   value="aq:social:facebook"
								<?php if (in_array('aq:social:facebook', $client_scopes)) echo ' checked="checked"'; ?>>
                            <span class="description"><?php esc_html_e('Facebook', AUTHENTIQ_LANG) ?></span>
                        </label>
                    </fieldset>

                    <fieldset>
                        <label for="authentiq_settings[client_scopes][aq:social:linkedin]">
                            <input type="checkbox" name="authentiq_settings[client_scopes][]"
                                   id="authentiq_settings[client_scopes][aq:social:linkedin]"
                                   value="aq:social:linkedin"
								<?php if (in_array('aq:social:linkedin', $client_scopes)) echo ' checked="checked"'; ?>>
                            <span class="description"><?php esc_html_e('Linkedin', AUTHENTIQ_LANG) ?></span>
                        </label>
                    </fieldset>
                </td>
            </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Functionality', AUTHENTIQ_LANG) ?></h2>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="authentiq_settings[classic_wp_login]"><?php esc_html_e('Classic WordPress Login', AUTHENTIQ_LANG) ?></label>
                </th>
                <td>
                    <label for="authentiq_settings[classic_wp_login]">
                        <input type="checkbox" name="authentiq_settings[classic_wp_login]"
                               id="authentiq_settings[classic_wp_login]" value="1"
							<?php checked($this->options->get('classic_wp_login'), 1); ?>>

						<?php esc_html_e('Allow', AUTHENTIQ_LANG) ?>
                    </label>

					<?php $classic_wp_login_for = $this->options->get('classic_wp_login_for'); ?>

                    <label for="authentiq_settings[classic_wp_login_for]">
                        <select name="authentiq_settings[classic_wp_login_for]"
                                id="authentiq_settings[classic_wp_login_for]">
                            <option value="0" <?php selected($classic_wp_login_for, 0); ?>><?php esc_html_e('all users', AUTHENTIQ_LANG) ?></option>
                            <option value="1" <?php selected($classic_wp_login_for, 1); ?>><?php esc_html_e('users without Authentiq ID', AUTHENTIQ_LANG) ?></option>
                        </select>

						<?php esc_html_e('to sign in with username and password.', AUTHENTIQ_LANG) ?>
                    </label>

                    <br/>

                    <label for="authentiq_settings[auto_login]">
                        <input type="checkbox" name="authentiq_settings[auto_login]"
                               id="authentiq_settings[auto_login]" value="1"
							<?php checked($this->options->get('auto_login'), 1); ?>>

						<?php esc_html_e('Skip the WordPress login page, and proceed directly to Authentiq sign in.', AUTHENTIQ_LANG) ?>
                    </label>
                </td>
            </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Domain filter', AUTHENTIQ_LANG) ?></h2>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label
                            for="authentiq_settings[filter_user_domains]"><?php esc_html_e('Domains to filter', AUTHENTIQ_LANG) ?></label>
                </th>
                <td>

					<?php $filter_user_domains_condition = $this->options->get('filter_user_domains_condition'); ?>

                    <label for="authentiq_settings[filter_user_domains_condition]" class="description">
                        <select name="authentiq_settings[filter_user_domains_condition]"
                                id="authentiq_settings[filter_user_domains_condition]">
                            <option value="0" <?php selected($filter_user_domains_condition, 0); ?>><?php esc_html_e('Only', AUTHENTIQ_LANG) ?></option>
                            <option value="1" <?php selected($filter_user_domains_condition, 1); ?>><?php esc_html_e('No', AUTHENTIQ_LANG) ?></option>
                        </select>

						<?php esc_html_e('Users from the following domains are allowed.', AUTHENTIQ_LANG) ?>
                    </label>

                    <br/>

                    <textarea name="authentiq_settings[filter_user_domains]"
                              id="authentiq_settings[filter_user_domains]"
                              rows="4" cols="60" type="textarea"
                              placeholder="example.com"><?php echo esc_textarea($this->options->get('filter_user_domains')); ?></textarea>

                </td>
            </tr>
            </tbody>
        </table>

		<?php
		submit_button();
		?>
    </form>
</div>

<?php if (!$this->options->is_configured() && $this->options->get('wsl_migration')) : ?>
    <script>
      jQuery(document).ready(function () {
        jQuery('#authentiq-complete-wsl-migration').click(function (e) {
          e.preventDefault();

          var data = {
            'action': '<?php echo Authentiq_Admin::COMPLETE_WSL_MIGRATION_ACTION; ?>',
            'aq_nonce': '<?php echo wp_create_nonce(Authentiq_Admin::COMPLETE_WSL_MIGRATION_ACTION); ?>'
          };

          jQuery.post(ajaxurl, data)
                .done(function (data) {
                  if (typeof(data.success) === 'undefined' || data.success === false) {
                    return;
                  }

                  jQuery('.authentiq-configuration-warning').remove();
                })
                .fail(function (data, res) {
                  alert('<?php _e('Please try again.', AUTHENTIQ_LANG); ?>');
                });
        });
      });
    </script>
<?php endif; ?>
