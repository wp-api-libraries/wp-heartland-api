(function(window, document, Heartland, wc_securesubmit_params) {
    var addHandler = window.Heartland ? Heartland.Events.addHandler : function () {};

    function addClass(element, klass) {
      if (element.className.indexOf(klass) === -1) {
        element.className = element.className + ' ' + klass;
      }
    }

    function removeClass(element, klass) {
      if (element.className.indexOf(klass) === -1) return;
      element.className = element.className.replace(klass, '');
    }

    function toAll(elements, fun) {
        var i = 0;
        var length = elements.length;
        for (i; i < length; i++) {
            fun(elements[i]);
        }
    }

    function filter(elements, fun) {
        var i = 0;
        var length = elements.length;
        var result = [];
        for (i; i < length; i++) {
            if (fun(elements[i]) === true) {
                result.push(elements[i]);
            }
        }
        return result;
    }

    function clearFields() {
        toAll(document.querySelectorAll('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message'), function(element) {
            element.remove();
        });
    }

    window.__secureSubmitFrameInit = window.__secureSubmitFrameInit || false;
    function cca() {
        if (document.getElementById('securesubmit_cca_data')) {
            return;
        }
        if (!window.__secureSubmitFrameInit) {
            Cardinal.setup('init', {
                jwt: wc_securesubmit_params.cca.jwt
            });
            Cardinal.on('payments.validated', function (data, jwt) {
                var token = document.getElementById('securesubmit_cardinal_token');
                var form = document.querySelector('form.checkout, form#order_review');
                var cca = document.createElement('input');
                data.jwt = jwt;
                cca.type = 'hidden';
                cca.id = 'securesubmit_cca_data';
                cca.name = 'securesubmit_cca_data';
                cca.value = Heartland.JSON.stringify(data);
                form.appendChild(cca);

                if ((!token || !token.value) && data.Token && data.Token.Token) {
                    createCardinalTokenNode(form, data.Token.Token);
                }

                jQuery(form).submit();
            });
            window.__secureSubmitFrameInit = true;
        }
        Cardinal.trigger('jwt.update', wc_securesubmit_params.cca.jwt);
        var options = {
            OrderDetails: {
                OrderNumber: wc_securesubmit_params.cca.orderNumber + 'cca'
            }
        };
        if (wc_securesubmit_params.use_iframes) {
            var token = document.getElementById('securesubmit_cardinal_token').value;
            options.Token = {
                Token: token,
                ExpirationMonth: document.getElementById('exp_month').value,
                ExpirationYear: document.getElementById('exp_year').value
            };
        } else {
            var card = document.getElementById('securesubmit_card_number');
            var cvv = document.getElementById('securesubmit_card_cvv');
            var expiration = document.getElementById('securesubmit_card_expiration');

            if (!expiration || !expiration.value) {
                return false;
            }

            var split = expiration.value.split(' / ');
            var month = split[0].replace(/^\s+|\s+$/g, '');
            var year = split[1].replace(/^\s+|\s+$/g, '');
            options.Consumer = {
                Account: {
                    AccountNumber: card.value.replace(/\D/g, ''),
                    ExpirationMonth: month.replace(/\D/g, ''),
                    ExpirationYear: year.replace(/\D/g, ''),
                    CardCode: cvv.value.replace(/\D/g, '')
                }
            };
        }
        Cardinal.start('cca', options);
    }

    function createCardinalTokenNode(form, value) {
        var cardinalToken = document.createElement('input');
        cardinalToken.type = 'hidden';
        cardinalToken.id = 'securesubmit_cardinal_token';
        cardinalToken.name = 'securesubmit_cardinal_token';
        cardinalToken.value = value;
        form.appendChild(cardinalToken);
    }

    // Handles form submission when not using iframes
    function formHandler(e) {
        var securesubmitMethod = document.getElementById('payment_method_securesubmit');
        var storedCards = document.querySelectorAll('input[name=secure_submit_card]');
        var storedCardsChecked = filter(storedCards, function(el) {
            return el.checked;
        });
        var token = document.getElementById('securesubmit_token');
        var cardinalCcaData = document.getElementById('securesubmit_cca_data');

        var securesubmitEnabled = securesubmitMethod && securesubmitMethod.checked;
        var newCardUsed = storedCardsChecked.length === 0 || (storedCardsChecked[0] && storedCardsChecked[0].value === 'new');
        var ccaEnabled = !!wc_securesubmit_params.cca;
        var securesubmitTokenObtained = token.value !== '';
        var cardinalCcaDataObtained = cardinalCcaData && cardinalCcaData.value !== '';

        if (!securesubmitEnabled) {
            return true;
        }

        if (newCardUsed && !securesubmitTokenObtained) {
            var card = document.getElementById('securesubmit_card_number');
            var cvv = document.getElementById('securesubmit_card_cvv');
            var expiration = document.getElementById('securesubmit_card_expiration');

            if (!expiration || !expiration.value) {
                return false;
            }

            var split = expiration.value.split(' / ');
            var month = split[0].replace(/^\s+|\s+$/g, '');
            var year = split[1].replace(/^\s+|\s+$/g, '');

            var options = {
                publicKey: wc_securesubmit_params.key,
                cardNumber: card.value.replace(/\D/g, ''),
                cardCvv: cvv.value.replace(/\D/g, ''),
                cardExpMonth: month.replace(/\D/g, ''),
                cardExpYear: year.replace(/\D/g, ''),
                success: responseHandler,
                error: responseHandler
            };

            (new Heartland.HPS(options)).tokenize();

            return false;
        }

        if (ccaEnabled && !cardinalCcaDataObtained) {
            cca();
            return false;
        }

        return true;
    }

    // Handles form submission when using iframes
    function iframeFormHandler(e) {
        var securesubmitMethod = document.getElementById('payment_method_securesubmit');
        var storedCards = document.querySelectorAll('input[name=secure_submit_card]');
        var storedCardsChecked = filter(storedCards, function(el) {
            return el.checked;
        });
        var token = document.getElementById('securesubmit_token');
        var cardinalToken = document.getElementById('securesubmit_cardinal_token');
        var cardinalCcaData = document.getElementById('securesubmit_cca_data');

        var securesubmitEnabled = securesubmitMethod && securesubmitMethod.checked;
        var newCardUsed = storedCardsChecked.length === 0 || (storedCardsChecked[0] && storedCardsChecked[0].value === 'new');
        var ccaEnabled = !!wc_securesubmit_params.cca;
        var securesubmitTokenObtained = token.value !== '';
        var cardinalTokenObtained = cardinalToken && cardinalToken.value !== '';
        var cardinalCcaDataObtained = cardinalCcaData && cardinalCcaData.value !== '';

        if (!securesubmitEnabled) {
            return true;
        }

        if (newCardUsed && !securesubmitTokenObtained) {
            wc_securesubmit_params.hps.Messages.post({
                    accumulateData: true,
                    action: 'tokenize',
                    data: wc_securesubmit_params.hps.options
                },
                'cardNumber'
            );
            return false;
        }

        if (ccaEnabled && cardinalTokenObtained && !cardinalCcaDataObtained) {
            cca();
            return false;
        }

        return true;
    }

    // Handles tokenization response
    function responseHandler(response) {
        var form = document.querySelector('form.checkout, form#order_review');

        if (response.error || (response.heartland && response.heartland.error)) {
            var ul = document.createElement('ul');
            var li = document.createElement('li');
            clearFields();

            addClass(ul, 'woocommerce_error');
            addClass(ul, 'woocommerce-error');
            li.appendChild(document.createTextNode(response.error.message));
            ul.appendChild(li);

            document.querySelector('.securesubmit_new_card').insertBefore(
                ul,
                document.querySelector('.securesubmit_new_card_info')
            );
        } else {
            var heartland = response.heartland || response;
            var cardinal = response.cardinal;
            var token = document.getElementById('securesubmit_token');
            var last4 = document.createElement('input');
            var cType = document.createElement('input');
            var expMo = document.createElement('input');
            var expYr = document.createElement('input');

            token.value = heartland.token_value;

            last4.type = 'hidden';
            last4.id = 'last_four';
            last4.name = 'last_four';
            last4.value = heartland.last_four;

            cType.type = 'hidden';
            cType.id = 'card_type';
            cType.name = 'card_type';
            cType.value = heartland.card_type;

            expMo.type = 'hidden';
            expMo.id = 'exp_month';
            expMo.name = 'exp_month';
            expMo.value = heartland.exp_month;

            expYr.type = 'hidden';
            expYr.id = 'exp_year';
            expYr.name = 'exp_year';
            expYr.value = heartland.exp_year;

            form.appendChild(last4);
            form.appendChild(cType);
            form.appendChild(expMo);
            form.appendChild(expYr);

            if (cardinal) {
                createCardinalTokenNode(form, cardinal.token_value);
                cca();
                return;
            }

            jQuery(form).submit();
        }

        setTimeout(function () {
            document.getElementById('securesubmit_token').value = '';
        }, 500);
    }

    // Load function to attach event handlers when WC refreshes payment fields
    window.securesubmitLoadEvents = function() {
        if (!Heartland) {
            return;
        }

        toAll(document.querySelectorAll('.card-number, .card-cvc, .expiry-date'), function(element) {
            addHandler(element, 'change', clearFields);
        });

        toAll(document.querySelectorAll('.saved-selector'), function(element) {
            addHandler(element, 'click', function(e) {
                var display = 'none';
                if (document.getElementById('secure_submit_card_new').checked) {
                    display = 'block';
                }
                toAll(document.querySelectorAll('.new-card-content'), function (el) {
                    el.style.display = display;
                });

                // Set active flag
                toAll(document.querySelectorAll('.saved-card'), function (el) {
                  removeClass(el, 'active');
                });
                addClass(element.parentNode.parentNode, 'active');
            });
        });

        if (document.querySelector('.securesubmit_new_card .card-number')) {
            Heartland.Card.attachNumberEvents('.securesubmit_new_card .card-number');
            Heartland.Card.attachExpirationEvents('.securesubmit_new_card .expiry-date');
            Heartland.Card.attachCvvEvents('.securesubmit_new_card .card-cvc');
        }
    };
    window.securesubmitLoadEvents();

    // Load function to build iframes when WC refreshes payment fields
    window.securesubmitLoadIframes = function() {
        if (!wc_securesubmit_params || !wc_securesubmit_params.use_iframes) {
            return;
        }
        var options = {
            publicKey: wc_securesubmit_params.key,
            type: 'iframe',
            fields: {
                cardNumber: {
                    target: 'securesubmit_card_number',
                    placeholder: '•••• •••• •••• ••••'
                },
                cardExpiration: {
                    target: 'securesubmit_card_expiration',
                    placeholder: 'MM / YYYY'
                },
                cardCvv: {
                    target: 'securesubmit_card_cvv',
                    placeholder: 'CVV'
                }
            },
            style: {
                'input': {
                    'background': '#fff',
                    'border': '1px solid #666',
                    'border-color': '#bbb3b9 #c7c1c6 #c7c1c6',
                    'box-sizing': 'border-box',
                    'font-family': 'Arial, Helvetica Neue, Helvetica, sans-serif',
                    'font-size': '18px !important',
                    'line-height': '18px !important',
                    'margin': '0 .5em 0 0',
                    'max-width': '100%',
                    'outline': '0',
                    'padding': '15px 13px 13px 13px',
                    'vertical-align': 'middle',
                    'width': '100%'
                },
                '#heartland-field-body': {
                    'width': '100%'
                },
                '#heartland-field-wrapper': {
                    'position': 'relative'
                },
                // Card Number
                '#heartland-field[name="cardNumber"] + .extra-div-1': {
                    'display': 'block',
                    'width': '56px',
                    'height': '44px',
                    'position': 'absolute',
                    'top': '4px',
                    'right': '10px',
                    'background-position': 'bottom',
                    'background-repeat': 'no-repeat',
                    'background-size': '56px auto'
                },
                '#heartland-field[name="cardNumber"].valid + .extra-div-1': {
                    'background-position': 'top'
                },
                '#heartland-field.card-type-visa + .extra-div-1': {
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-inputcard-visa@2x.png")'
                },
                '#heartland-field.card-type-jcb + .extra-div-1': {
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-inputcard-jcb@2x.png")'
                },
                '#heartland-field.card-type-discover + .extra-div-1': {
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-inputcard-discover@2x.png")'
                },
                '#heartland-field.card-type-amex + .extra-div-1': {
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-inputcard-amex@2x.png")'
                },
                '#heartland-field.card-type-mastercard + .extra-div-1': {
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-inputcard-mastercard@2x.png")'
                },
                '@media only screen and (max-width : 290px)': {
                    '#heartland-field[name="cardNumber"] + .extra-div-1': {
                        'display': 'none'
                    }
                },
                // Card CVV
                '#heartland-field[name="cardCvv"] + .extra-div-1': {
                    'display': 'block',
                    'width': '59px',
                    'height': '39px',
                    'background-image': 'url("' + wc_securesubmit_params.images_dir + '/ss-cvv@2x.png")',
                    'background-size': '59px auto',
                    'background-position': 'top',
                    'position': 'absolute',
                    'top': '6px',
                    'right': '7px'
                }
            },
            onTokenSuccess: responseHandler,
            onTokenError: responseHandler
        };

        if (wc_securesubmit_params.cca) {
            options.cca = {
                jwt: wc_securesubmit_params.cca.jwt,
                orderNumber: wc_securesubmit_params.cca.orderNumber
            };
        }

        wc_securesubmit_params.hps = new Heartland.HPS(options);
        if (!wc_securesubmit_params.hpsReadyHandler) {
            wc_securesubmit_params.hpsReadyHandler = function () {
                setTimeout(function () {
                    document.getElementById('heartland-frame-cardNumber').style.height = '52px';
                    document.getElementById('heartland-frame-cardExpiration').style.height = '52px';
                    document.getElementById('heartland-frame-cardCvv').style.height = '52px';
                }, 500);
            };
        }
        Heartland.Events.removeHandler(document, 'securesubmitIframeReady', wc_securesubmit_params.hpsReadyHandler);
        Heartland.Events.addHandler(document, 'securesubmitIframeReady', wc_securesubmit_params.hpsReadyHandler);
    };
    window.securesubmitLoadIframes();

    function paypalShowIncontext(isCredit) {
        var data = {
            action: 'wc_securesubmit_paypal_start_incontext',
            paypalexpress_initiated: 'true'
        };

        paypal.checkout.initXO();
        isCredit = isCredit
            || jQuery('[name="payment_method"][value^="heartland_paypal"]:checked').length !== 0
            && jQuery('[name="payment_method"][value^="heartland_paypal"]:checked').attr('value').indexOf('credit') !== -1;

        if (isCredit) {
            data.paypalexpress_credit = 'true';
        }

        jQuery.ajax({
            type: 'POST',
            url: wc_securesubmit_paypal_params.ajaxUrl,
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.result == 'fail') {
                    paypal.checkout.closeFlow();
                    return;
                }
                paypal.checkout.startFlow(response.redirect);
            },
            error: function (response) {
                alert('Error starting PayPal checkout');
                paypal.checkout.closeFlow();
            }
        });
    }

    function paypalPreventCheckoutSubmit(e) {
        if (jQuery('[name="payment_method"][value^="heartland_paypal"]').length !== 0
            && !jQuery('[name="payment_method"][value^="heartland_paypal"]').is(':checked')
        ) {
            return true;
        }

        e.preventDefault();
        paypalShowIncontext();
        return false;
    }

    function paypalIncontextReady() {
        var buttons = [];
        var checkoutSubmit = jQuery('#place_order');
        if (checkoutSubmit && checkoutSubmit[0]) {
            buttons.push(checkoutSubmit[0]);
        } else {
            paypal.checkout.closeFlow();
            return;
        }

        paypal.checkout.setup('undefined', {
            environment: wc_securesubmit_paypal_params.env,
            button: buttons,
            click: function (e) {
                var isCredit = (jQuery('[name="payment_method"][value^="heartland_paypal_credit"]').length !== 0
                    && jQuery('[name="payment_method"][value^="heartland_paypal_credit"]').is(':checked'));
                var checkoutPageNotUsed = wc_securesubmit_paypal_params.isCheckout === 'true'
                    && (jQuery('[name="payment_method"][value^="heartland_paypal"]').length === 0
                    || !jQuery('[name="payment_method"][value^="heartland_paypal"]').is(':checked'));

                if (checkoutPageNotUsed) {
                    return true;
                }

                e.preventDefault();
                paypalShowIncontext(isCredit);
                return false;
            }
        });

        return false;
    }

    addHandler(document, 'DOMContentLoaded', function() {
        if (window.wc_securesubmit_params) {
            if (!wc_securesubmit_params.handler) {
                var handler = formHandler;
                if (wc_securesubmit_params.use_iframes) {
                    handler = iframeFormHandler;
                }
                wc_securesubmit_params.handler = handler;
            }

            jQuery('form#order_review')
                .off('submit', wc_securesubmit_params.handler)
                .on('submit', wc_securesubmit_params.handler);
            jQuery('form.checkout')
                .off('checkout_place_order_securesubmit', wc_securesubmit_params.handler)
                .on('checkout_place_order_securesubmit', wc_securesubmit_params.handler);
        }

        if (window.wc_securesubmit_paypal_params) {
            if (!wc_securesubmit_paypal_params.handler) {
                wc_securesubmit_paypal_params.handler = paypalPreventCheckoutSubmit;
            }

            jQuery('form.checkout')
                .on('checkout_place_order_heartland_paypal', wc_securesubmit_paypal_params.handler)
                .on('checkout_place_order_heartland_paypal_credit', wc_securesubmit_paypal_params.handler);

            window.paypalCheckoutReady = paypalIncontextReady;

            if (wc_securesubmit_paypal_params.isCheckout != 'true') {
                var paypalData = {
                    action: 'wc_securesubmit_paypal_start_incontext',
                    paypalexpress_initiated: 'true'
                };
                paypal.Button.render({
                    env: wc_securesubmit_paypal_params.env,
                    payment: function () {
                        return paypal.request.post(wc_securesubmit_paypal_params.ajaxUrl, paypalData)
                            .then(function (resp) {
                                if (resp.result == 'fail') {
                                    paypal.checkout.closeFlow();
                                    return;
                                }
                                return resp.token;
                            });
                    },
                    onAuthorize: function (resp, actions) {
                        return actions.redirect();
                    },
                    onCancel: function (resp, actions) {
                        return actions.redirect();
                    }
                }, '#hps_paypal_shortcut_express_button');

                var paypalCreditData = {
                    action: 'wc_securesubmit_paypal_start_incontext',
                    paypalexpress_initiated: 'true',
                    paypalexpress_credit: 'true'
                };
                paypal.Button.render({
                    env: wc_securesubmit_paypal_params.env,
                    style: { label: 'credit' },
                    payment: function () {
                        return paypal.request.post(wc_securesubmit_paypal_params.ajaxUrl, paypalCreditData)
                            .then(function (resp) {
                                if (resp.result == 'fail') {
                                    paypal.checkout.closeFlow();
                                    return;
                                }
                                return resp.token;
                            });
                    },
                    onAuthorize: function (resp, actions) {
                        return actions.redirect();
                    },
                    onCancel: function (resp, actions) {
                        return actions.redirect();
                    }
                }, '#hps_paypal_shortcut_express_button_credit');
            }
        }
    });

    function processGiftCardResponse(msg) {
        var giftCardResponse = JSON.parse(msg);

        if (giftCardResponse.error === 1) {
            jQuery('#gift-card-error').text(giftCardResponse.message).show('fast');
        } else if (giftCardResponse.error === 0) {
            jQuery('#gift-card-success').text("Your gift card was applied to the order.").show('fast');
            jQuery('body').trigger('update_checkout');
        }
    }

    window.removeGiftCards = function (clickedElement) {
        var removedCardID = jQuery(clickedElement).attr('id');

        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: 'remove_gift_card',
                securesubmit_card_id: removedCardID
            }
        }).done(function () {
            jQuery('body').trigger('update_checkout');
        });
    };

    window.applyGiftCard = function () {
        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: 'use_gift_card',
                gift_card_number: jQuery('#gift-card-number').val(),
                gift_card_pin: jQuery('#gift-card-pin').val()
            }
        }).success(processGiftCardResponse);
    };
}(window, document, window.Heartland, window.wc_securesubmit_params));
