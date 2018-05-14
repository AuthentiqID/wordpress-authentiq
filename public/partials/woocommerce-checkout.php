<div id="customer-login-authentiq" class="authentiq-form-wrapper">
  <div>
    <?php require(AUTHENTIQ_PLUGIN_DIR . 'public/partials/authentiq-button.php'); ?>
  </div>

  <?php if (!empty($is_form_filling)) : ?>
    <div class="desc">
      <?php _e('Tired of typing your details again? Get Authentiq ID on your phone and fill forms like this in seconds.', AUTHENTIQ_LANG); ?>
    </div>
  <?php endif; ?>
</div>
