<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_MasterPass_Payment
{
    protected $masterpass = null;

    public function __construct(&$masterpass = null)
    {
        $this->masterpass = $masterpass;
    }

    public function call($orderId)
    {
        $order = wc_get_order($orderId);
        $cart = WC()->cart;
        $checkoutForm = maybe_unserialize(WC()->session->checkout_form);

        try {
            $authenticate = WC()->session->get('masterpass_authenticate');
            if (!$authenticate) {
                throw new Exception(__('Error:', 'wc_securesubmit') . ' Invalid MasterPass session');
            }

            $service = $this->masterpass->getService();

            // Create an authorization
            $response = null;
            if ('sale' === $this->masterpass->paymentAction) {
                $response = $service->sale(
                    $authenticate->orderId,
                    $cart->total,
                    strtolower(get_woocommerce_currency()),
                    $this->masterpass->getBuyerData($checkoutForm),
                    $this->masterpass->getPaymentData($cart),
                    $this->masterpass->getShippingInfo($checkoutForm),
                    $this->masterpass->getLineItems($cart)
                );
            } else {
                $response = $service->authorize(
                    $authenticate->orderId,
                    $cart->total,
                    strtolower(get_woocommerce_currency()),
                    $this->masterpass->getBuyerData($checkoutForm),
                    $this->masterpass->getPaymentData($cart),
                    $this->masterpass->getShippingInfo($checkoutForm),
                    $this->masterpass->getLineItems($cart)
                );
            }

            $transactionId = null;
            if (property_exists($response, 'capture')) {
                $transactionId = $response->capture->transactionId;
            } else {
                $transactionId = $response->transactionId;
            }

            $order->add_order_note(__('MasterPass payment completed', 'wc_securesubmit') . ' (Transaction ID: ' . $transactionId . ')');
            $order->payment_complete($transactionId);
            $cart->empty_cart();

            $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');

            update_post_meta($orderId, '_transaction_id', $transactionId);
            update_post_meta($orderId, '_masterpass_order_id', $authenticate->orderId);
            update_post_meta($orderId, '_masterpass_payment_status', 'sale' === $this->masterpass->paymentAction ? 'captured' : 'authorized');

            return array(
                'result'   => 'success',
                'redirect' => $this->masterpass->get_return_url($order),
            );
        } catch (Exception $e) {
            $error = __('Error:', 'wc_securesubmit') . ' "' . (string)$e->getMessage() . '"';
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error, 'error');
            } else if (method_exists(WC(), 'add_error')) {
                WC()->add_error($error);
            }

            return array(
                'result'   => 'fail',
                'redirect' => $cart->get_checkout_url(),
            );
        }
    }
}
