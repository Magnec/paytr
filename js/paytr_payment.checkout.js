(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.offsiteForm = {
    attach: function (context) {
      let data = JSON.parse(drupalSettings.paytr_payment_iframe);
      $($(context).find('#paytr-payment-checkout')).append('<script>iFrameResize({},\'#paytriframe\');</script>');
    }
  };

}(jQuery, Drupal, drupalSettings));
