<p>
	<?php esc_html_e('There was a problem with your log in.', AUTHENTIQ_LANG); ?>

    <br/>

	<?php echo $msg; ?>

    <hr />

    <a href="<?php echo wp_login_url(); ?>"><?php esc_html_e('← Login with Authentiq', AUTHENTIQ_LANG); ?></a>
</p>
