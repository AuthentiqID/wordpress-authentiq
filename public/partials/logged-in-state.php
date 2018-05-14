<div class="authentiq-form-wrapper">
	<a href="<?php echo $logout_url; ?>" class="<?php echo !empty($text_only_link) && $text_only_link ? 'authentiq-link' : 'authentiq-button'; ?> color-scheme-<?php echo $button_color_scheme; ?>">
		<?php echo !empty($button_text) ? $button_text : esc_html_e('Sign out', AUTHENTIQ_LANG); ?>
	</a>
</div>
