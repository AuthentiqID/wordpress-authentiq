<?php if (!$show_wp_password_form) : ?>

    <div id="customer-login-authentiq" class="authentiq-form-wrapper wp-passwords-hidden">
		<?php require(AUTHENTIQ_PLUGIN_DIR . 'public/partials/authentiq-button.php'); ?>

		<?php if ($allow_classic_wp_login) { ?>
            <div class="other-methods">
                <a href="<?php echo add_query_arg(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM, true); ?>">
					<?php esc_html_e('or login with your WordPress user', AUTHENTIQ_LANG) ?>
                </a>
            </div>
		<?php } ?>
    </div>

<?php else : ?>

    <p id="customer-login-authentiq" class="authentiq-form-wrapper">
		<?php require(AUTHENTIQ_PLUGIN_DIR . 'public/partials/render-back-to-authentiq.php'); ?>
    </p>

<?php endif; ?>