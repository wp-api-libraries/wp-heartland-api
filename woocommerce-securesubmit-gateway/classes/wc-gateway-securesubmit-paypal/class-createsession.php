<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_PayPal_CreateSession
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call($orderId, $isCredit = false)
    {
        $this->parent->setSession('ss_order_id', $orderId);
        $override = WC()->session->get('process_payment_override');
        if (isset($override) && $override == 1) {
            $this->parent->setSession('process_payment_override', 0);
            $this->parent->setSession('process_payment_override_order_id', $orderId);
            return;
        }

        $woocommerce = WC();
        $this->parent->setSession('checkout_form', $_POST);

        // create portico session if needed
        $isExpressCheckout = WC()->session->get('ss-paypal-express-checkout-inprogress');
        if (null != $orderId && !isset($expressCheckout) || false === $isExpressCheckout) {
            $order = wc_get_order($orderId);

            $orderTotal = WC_SecureSubmit_Util::getData($order, 'get_total', 'order_total');
            $shippingInfo = $this->parent->getShippingInfo($order);
            $buyer = $this->parent->getBuyerData($order);
            $payment = $this->parent->getPaymentData($order);
            if ( empty($payment->invoiceNumber) ) {
                $payment->invoiceNumber = $orderId;
            }
            $lineItems = $this->parent->getLineItems($order);
        } else {
            $orderTotal = $woocommerce->cart->total;
            $shippingInfo = $this->parent->getShippingInfo($woocommerce->cart);
            $buyer = $this->parent->getBuyerData($woocommerce->cart);
            $payment = $this->parent->getPaymentData($woocommerce->cart);
            $lineItems = $this->parent->getLineItems($woocommerce->cart);
        }

        $currency = strtolower(get_woocommerce_currency());
        $buyer->credit = $isCredit;

        //call portico to create session
        $response;
        try {
            $response = $this->parent->getPorticoService()->createSession(
                wc_format_decimal($orderTotal, 2),
                $currency,
                $buyer,
                $payment,
                $shippingInfo,
                $lineItems
            );
        } catch (Exception $e) {
            $error = __('Error creating PayPal Portico session:', 'wc_securesubmit')
                . ' "' . $e->getMessage() . '"';
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error, 'error');
            } else {
                $woocommerce->add_error($error);
            }
            return array(
                'result' => 'fail',
                'redirect' => '',
                'message' => $e->getMessage(),
            );
        }

        return array(
            'result' => 'success',
            'redirect' => $response->redirectUrl,
            'token' => $response->sessionId,
        );
    }
}
