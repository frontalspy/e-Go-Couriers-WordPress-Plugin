jQuery(document).ready(function () {
  jQuery(document).ajaxComplete(function() { 
    if (jQuery('#shipping_method_0_ego_shipping').is(':checked')) {
      if(!jQuery('#shipping_method_0_ego_shipping').siblings().find('.amount').length) {
        jQuery('#place_order').attr('disabled', true);
      }
    }
  });
});