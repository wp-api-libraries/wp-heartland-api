<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_MasterPass_Lookup
{
    protected $masterpass = null;

    public function __construct(&$masterpass = null)
    {
        $this->masterpass = $masterpass;
    }

    public function call()
    {
        if (!is_ajax()) {
            return;
        }
        header('Content-Type: application/json; charset=UTF-8');

        $pair = isset($_POST['pair']) && 'true' === $_POST['pair'];

        $payload = array();
        $cart = WC()->cart;
        $checkoutForm = $_POST;
        WC()->session->set('checkout_form', $checkoutForm);

        try {
            $cardId = isset($checkoutForm['masterpass_card_id']) ? $checkoutForm['masterpass_card_id'] : '';
            $preCheckoutTransactionId = get_transient('_masterpass_pre_checkout_transaction_id');
            $walletName = get_transient('_masterpass_wallet_name');
            $walletId = get_transient('_masterpass_wallet_id');

            $service = $this->masterpass->getService();

            $orderData = new HpsOrderData();
            $orderData->orderNumber = str_shuffle('abcdefghijklmnopqrstuvwxyz');
            $orderData->ipAddress = $_SERVER['REMOTE_ADDR'];
            $orderData->browserHeader = $_SERVER['HTTP_ACCEPT'];
            $orderData->userAgent = $_SERVER['HTTP_USER_AGENT'];
            $orderData->originUrl = $cart->get_checkout_url();
            $orderData->termUrl = $cart->get_checkout_url();
            $orderData->checkoutType = HpsCentinelCheckoutType::LIGHTBOX;

            if ($pair) {
                $orderData->checkoutType = HpsCentinelCheckoutType::PAIRING;
            }

            $buyer = !$pair ? $this->masterpass->getBuyerData($checkoutForm) : null;
            $payment = $this->masterpass->getPaymentData($cart);
            $shipping = !$pair ? $this->masterpass->getShippingInfo($checkoutForm) : null;
            $lines = !$pair ? $this->masterpass->getLineItems($cart) : array();
            $response = $service->createSession(
                $cart->total,
                strtolower(get_woocommerce_currency()),
                $buyer,
                $payment,
                $shipping,
                $lines,
                $orderData
            );

            $returnUrl = null;
            if ($pair) {
                $returnUrl = add_query_arg(
                    array('mp_action' => 'pair'),
                    get_permalink(wc_get_page_id('myaccount'))
                );
            } else {
                $returnUrl = add_query_arg(
                    array('mp_action' => 'review_order'),
                    // ensure MasterPass order review page is created. if exists, the
                    // page id is pulled from the option
                    get_permalink(call_user_func(array(get_class($this->masterpass), 'createOrderReviewPage')))
                );
            }

            set_transient('_masterpass_order_number', $response->orderNumber, HOUR_IN_SECONDS / 4);
            set_transient('_masterpass_payload', urldecode($response->payload), HOUR_IN_SECONDS / 4);
            set_transient('_masterpass_order_id', $response->orderId, HOUR_IN_SECONDS / 4);

            $payload = array(
                'result' => 'success',
                'data'   => array(
                  'processorTransactionId' => $response->processorTransactionId,
                  'returnUrl'          => $returnUrl,
                  'merchantCheckoutId' => $this->masterpass->merchantCheckoutId,
                ),
            );

            if (null !== $response->processorTransactionIdPairing) {
                $payload['data']['processorTransactionIdPairing'] = $response->processorTransactionIdPairing;
            }
            if ('' !== $cardId) {
                $payload['data']['cardId'] = $cardId;
            }
            if (false !== $preCheckoutTransactionId) {
                $payload['data']['preCheckoutTransactionId'] = $preCheckoutTransactionId;
            }
            if (false !== $walletName) {
                $payload['data']['walletName'] = $walletName;
            }
            if (false !== $walletId) {
                $payload['data']['walletId'] = $walletId;
            }
        } catch (Exception $e) {
            $payload = array(
                'result' => 'error',
                'data'   => 'message',
            );
        }

        wp_die(json_encode($payload));
    }
}
