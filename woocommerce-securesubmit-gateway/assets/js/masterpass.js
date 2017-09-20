(function (window, document, $) {
    var ajaxUrl = wc_securesubmit_masterpass_params.ajaxUrl;

    // Checkout success handler
    function startMasterPassCheckout(payload) {
      var data = {
          requestToken: payload.processorTransactionId,
          callbackUrl: payload.returnUrl,
          merchantCheckoutId: payload.merchantCheckoutId,
          allowedCardTypes: ['master','amex','diners','discover','visa'],
          version: 'v6'
      };

      if (payload.cardId) {
          data.cardId = payload.cardId;
      }
      if (payload.shipId) {
          data.shippingId = payload.shipId;
      }
      if (payload.preCheckoutTransactionId) {
          data.precheckoutTransactionId = payload.preCheckoutTransactionId;
      }
      if (payload.walletName) {
          data.walletName = payload.walletName;
      }
      if (payload.walletId) {
          data.consumerwalletId = payload.walletId;
      }

      MasterPass.client.checkout(data);
    }

    // Connect success handler
    function startMasterPassConnect(payload) {
        MasterPass.client.connect({
            pairingRequestToken: payload.processorTransactionIdPairing,
            callbackUrl: payload.returnUrl,
            merchantCheckoutId: payload.merchantCheckoutId,
            requestedDataTypes: '[CARD]',
            requestPairing: true,
            version: 'v6'
        });
    }

    function clickHandler(data, callback) {
        return function (e) {
            e.preventDefault();
            $.ajax(
                {
                    url: ajaxUrl,
                    method: 'post',
                    data: data,
                    success: function (response) {
                        callback(response.data);
                    },
                    error: function (response) {
                        alert(response.data);
                    }
                }
            );
        };
    }

    // Handles lightbox creation by clicking 'Buy with MasterPass' button
    window.securesubmitMasterPassLookup = function () {
        var data = $('form.checkout').serialize();
        $('#securesubmit-buy-with-masterpass')
            .unbind('click')
            .click(clickHandler(data, startMasterPassCheckout));
    };
    window.securesubmitMasterPassLookup();

    // Handles lightbox creation by clicking 'Connect with MasterPass' button
    window.securesubmitMasterPassConnectLookup = function () {
        var data = {
            action: 'securesubmit_masterpass_lookup',
            pair: true
        };
        $('#securesubmit-connect-with-masterpass')
            .unbind('click')
            .click(clickHandler(data, startMasterPassConnect));
    };
    window.securesubmitMasterPassConnectLookup();

    // Handles lightbox creation by clicking 'Place Order'/submitting form
    function formHandler(e) {
        e.preventDefault();
        $('#securesubmit-buy-with-masterpass').click();
        return false;
    }

    jQuery('form.checkout').on('checkout_place_order_securesubmit_masterpass', formHandler);
}(window, document, jQuery));
