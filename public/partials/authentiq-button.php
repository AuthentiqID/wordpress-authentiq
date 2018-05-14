<a href="<?php echo $authorize_url; ?>" class="<?php echo !empty($text_only_link) && $text_only_link ? 'authentiq-link' : 'authentiq-button'; ?> color-scheme-<?php echo $button_color_scheme; ?>">
    <?php echo !empty($button_text) ? $button_text : __('Sign in or register', AUTHENTIQ_LANG); ?>
</a>
