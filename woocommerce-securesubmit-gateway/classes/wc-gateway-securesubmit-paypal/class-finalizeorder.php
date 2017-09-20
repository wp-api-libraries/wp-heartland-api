<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_PayPal_FinalizeOrder
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call()
    {
        //set token from session if available
        $token = $this->parent->getSession('paypal_session_token');
        if (is_null($token) && isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        $porticoSessionInfo = unserialize($this->parent->getSession('paypal_session_info'));
        $orderId = $this->parent->getSession('ss_order_id');
        if (!isset($orderId)) {
            $this->createOrder();
            $orderId = $this->parent->getSession('order_awaiting_payment');
        }

        $order = wc_get_order($orderId);

        if (!isset($order) || $order == false) {
            wc_add_notice('Order information was not found, unable to create order', 'error');
            wp_redirect(wc_get_cart_url());
            exit();
        }

        // cleanup paypal express dummy values
        $this->cleanupDummyValues($order);

        $payment = $porticoSessionInfo->payment;
        if ( empty($payment->invoiceNumber) ) {
            $payment->invoiceNumber = $orderId;
        }
        $orderTotal = wc_format_decimal($payment->subtotal + $payment->shippingAmount + $payment->taxAmount, 2);
        $currency = strtolower(get_woocommerce_currency());
        //call portico with sale
        $response = null;
        try {
            if ($this->parent->paymentaction == 'sale') {
                $response = $this->parent->getPorticoService()->sale(
                    $token,
                    $orderTotal,
                    $currency,
                    $porticoSessionInfo->buyer,
                    $porticoSessionInfo->payment,
                    $porticoSessionInfo->shipping,
                    $porticoSessionInfo->lineItems
                );
            } else {
                $response = $this->parent->getPorticoService()->authorize(
                    $token,
                    $orderTotal,
                    $currency,
                    $porticoSessionInfo->buyer,
                    $porticoSessionInfo->payment,
                    $porticoSessionInfo->shipping,
                    $porticoSessionInfo->lineItems
                );
            }
        } catch (Exception $e) {
            $error = __('Error finalizing PayPal Portico transaction:', 'wc_securesubmit')
                . ' "' . $e->getMessage() . '"';
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error, 'error');
            } else {
                $woocommerce = WC();
                $woocommerce->add_error($error, 'error');
            }
            return false;
        }

        if ($response->responseCode != '0') {
            wc_add_notice(sprintf(__('There was a problem paying with PayPal.  Please try another method.', 'wc_securesubmit')), 'error');
            wp_redirect(get_permalink(wc_get_page_id('cart')));
            exit();
        }

        $order->add_order_note(
            __('Heartland PayPal payment completed. Transaction id: ' . $response->transactionId, 'wc_securesubmit')
        );
        $order->payment_complete($response->transactionId);

        $this->parent->setSession('ss_order_id', null);
        $this->parent->setSession('ss_express_checkout_initiated', null);
        $this->parent->setSession('checkout_form', null);
        $this->parent->setSession('ss-paypal-express-checkout-inprogress', null);

        // Empty the Cart
        WC()->cart->empty_cart();

        wp_redirect($this->parent->get_return_url($order));
        exit();
    }

    private function cleanupDummyValues($order)
    {
        $billingAddress = $order->get_address();
        $shippingAddress = $order->get_address('shipping');
        $billingAddress['address_2'] = ($billingAddress['address_2']=='NA') ? '' : $billingAddress['address_2'];
        $shippingAddress['address_2'] = $shippingAddress['address_2']=='NA' ? '' : $shippingAddress['address_2'];
        $billingAddress['phone'] = $billingAddress['phone'] == '5555555555' ? '' : $billingAddress['phone'];
        $order->set_address($billingAddress);
        $order->set_address($shippingAddress, 'shipping');
    }

    /*
    Called when Paypal Express option is used. Here we create the order using the fields that
    would normally be filled out by the customer on the checkout page, but are skipped in the
    express payment flow.
    */
    private function createOrder()
    {
        $chosen_shipping_methods = maybe_unserialize($this->parent->getSession('chosen_shipping_methods'));

        $_POST['payment_method'] = $this->parent->id;
        $_POST['shipping_method'] =  $chosen_shipping_methods;
        $_POST['ship_to_different_address'] = true; // Paypal does not send billing addresses, only shipping
        $this->parent->setSession('chosen_shipping_methods', maybe_unserialize($this->parent->getSession('chosen_shipping_methods')));

        $result = maybe_unserialize($this->parent->getSession('paypal_session_info'));
        $hpsBuyerData = maybe_unserialize($result->buyer);
        $hpsShippingInfo = maybe_unserialize($result->shipping);
        $ship_name =  $unfiltered_name_parts = explode(" ", $hpsShippingInfo->name);

        $_POST['billing_first_name'] = $this->maybeGet($hpsBuyerData, 'firstName');
        $_POST['billing_last_name'] = $this->maybeGet($hpsBuyerData, 'lastName');
        // Paypal doesn't provide billing address so using shipping address
        $_POST['billing_address_1'] = $this->maybeGet($hpsShippingInfo->address, 'address');
        $_POST['billing_address_2'] = $this->maybeGet($hpsShippingInfo->address, 'address_1');
        $_POST['billing_city'] = $this->maybeGet($hpsShippingInfo->address, 'city');
        $_POST['billing_state'] = $this->maybeGet($hpsShippingInfo->address, 'state');
        $_POST['billing_postcode'] = $this->maybeGet($hpsShippingInfo->address, 'zip');
        $_POST['billing_country'] = $this->maybeGet($hpsBuyerData, 'countryCode');
        $_POST['billing_email'] = $this->maybeGet($hpsBuyerData, 'emailAddress');
        $_POST['billing_phone'] = $this->maybeGet($hpsBuyerData, 'phone', '5555555555');

        list($ship_first_name, $ship_middle_name, $ship_last_name) = $ship_name;
        if (!isset($ship_last_name)) {
            $ship_last_name = $ship_middle_name;
        }
        $_POST['shipping_first_name'] = $ship_first_name;
        $_POST['shipping_last_name'] = $ship_last_name;
        $_POST['shipping_address_1'] = $_POST['billing_address_1'];
        $_POST['shipping_address_2'] = $_POST['billing_address_2'];
        $_POST['shipping_city'] = $_POST['billing_city'];
        $_POST['shipping_state'] = $_POST['billing_state'];
        $_POST['shipping_postcode'] = $_POST['billing_postcode'];
        $_POST['shipping_country'] = $_POST['billing_country'];

        // If the user is logged in then use his woocommerce profile billing address information if entered
        if (is_user_logged_in()) {
            $_POST['billing_address_1'] = $this->maybeGet(WC()->customer, 'address_1', $_POST['billing_address_1']);
            $_POST['billing_address_2'] = $this->maybeGet(WC()->customer, 'address_2', $_POST['billing_address_2']);
            $_POST['billing_city'] = $this->maybeGet(WC()->customer, 'city', $_POST['billing_city']);
            $_POST['billing_state'] = $this->maybeGet(WC()->customer, 'state', $_POST['billing_state']);
            $_POST['billing_postcode'] = $this->maybeGet(WC()->customer, 'postcode', $_POST['billing_postcode']);
        }

        $checkoutForm = $this->parent->getSession('checkout_form');
        if (isset($checkoutForm['terms'])) {
            $_POST['terms'] = $checkoutForm['terms'];
        }

        $wpnonce = wp_create_nonce('woocommerce-process_checkout');
        $_POST['_wpnonce'] = $wpnonce;
        $this->parent->setSession('ppexpress_checkout_form', serialize($_POST));
        $all_notices = $this->parent->getSession('wc_notices');
        if (!$all_notices) {
            $all_notices = array();
        }
        $this->parent->setSession('process_payment_override', 1);
        WC()->checkout->process_checkout();
        return;
    }

    private function maybeGet($item, $key, $default = '')
    {
        $item = (array)$item;
        return isset($item[$key]) ? (string)$item[$key] : $default;
    }
}
