<div class="other-methods">
    <a href="<?php echo remove_query_arg(AUTHENTIQ_LOGIN_FORM_QUERY_PARAM, false); ?>">
		<?php !empty($is_registration) ? esc_html_e('&larr; Register with Authentiq', AUTHENTIQ_LANG) : esc_html_e('&larr; Login with Authentiq', AUTHENTIQ_LANG); ?>
    </a>
</div>
