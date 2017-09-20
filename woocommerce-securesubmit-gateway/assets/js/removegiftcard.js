jQuery(document).on( 'click', '.securesubmit-remove-gift-card', function (event) {
  event.preventDefault();

  var removedCardID = jQuery(this).attr('id');

  jQuery.ajax({
    url: ajaxurl,
    type: "POST",
    data: {
        action: 'remove_gift_card',
        securesubmit_card_id: removedCardID
    }
  }).done(function () {

    jQuery('body').trigger('update_checkout');
    jQuery(".button[name='update_cart']").trigger("click");

  });
});
