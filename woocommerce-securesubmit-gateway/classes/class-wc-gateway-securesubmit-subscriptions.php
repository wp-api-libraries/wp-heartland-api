<?php

class WC_Gateway_SecureSubmit_Subscriptions extends WC_Gateway_SecureSubmit
{
    public function __construct()
    {
        parent::__construct();

        $this->supports = array(
                            'products',
                            'refunds',
                            'subscriptions',
                            'subscription_cancellation',
                            'subscription_reactivation',
                            'subscription_suspension',
                            'subscription_amount_changes',
                            'subscription_payment_method_change',
                            'subscription_payment_method_change_admin',
                            'subscription_payment_method_change_customer',
                            'subscription_date_changes',
                            'multiple_subscriptions',
                          );

        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduledSubscriptionPayment'), 10, 2);
            add_action('woocommerce_subscription_failing_payment_method_updated_' . $this->id, array($this, 'updateFailingPaymentMethod'), 10, 2);
            add_action('wcs_resubscribe_order_created', array($this, 'deleteResubscribeMeta'), 10);
            add_filter('woocommerce_subscription_payment_meta', array($this, 'addSubscriptionPaymentMeta'), 10, 2);
            add_filter('woocommerce_subscription_validate_payment_meta', array($this, 'validateSubscriptionPaymentMeta'), 10, 2);
        }
    }

    public function process_payment($orderId)
    {
        if (($this->orderHasSubscription($orderId)
                || (function_exists('wcs_is_subscription') && wcs_is_subscription($orderId)))
        ) {
            return $this->processSubscription($orderId);
        } else {
            return parent::process_payment($orderId);
        }
    }

    protected function orderHasSubscription($orderId)
    {
        return function_exists('wcs_order_contains_subscription')
            && (wcs_order_contains_subscription($orderId) || wcs_order_contains_renewal($orderId));
    }

    protected function orderGetTotal($order)
    {
        if (method_exists($order, 'get_total')) {
            return $order->get_total();
        }
        return WC_Subscriptions_Order::get_total_initial_payment($order);
    }

    public function processSubscription($orderId)
    {
        global $woocommerce;

        $order = new WC_Order($orderId);
        $securesubmitToken = isset($_POST['securesubmit_token']) ? $this->cleanValue($_POST['securesubmit_token']) : '';
        $useStoredCard = false;

        // used for card saving:
        $last_four = isset($_POST['last_four']) ? $this->cleanValue($_POST['last_four']) : '';
        $exp_month = isset($_POST['exp_month']) ? $this->cleanValue($_POST['exp_month']) : '';
        $exp_year = isset($_POST['exp_year']) ? $this->cleanValue($_POST['exp_year']) : '';
        $card_type = isset($_POST['card_type']) ? $this->cleanValue($_POST['card_type']) : '';
        $saveCard = isset($_POST['save_card']) ? $this->cleanValue($_POST['save_card']) : '';

        try {
            if (empty($securesubmitToken)) {
                if (isset($_POST['secure_submit_card']) && $_POST['secure_submit_card'] === 'new') {
                    throw new Exception(__('Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'wc_securesubmit'));
                }
            }

            $hpstoken = new HpsTokenData();

            if (is_user_logged_in() && isset($_POST['secure_submit_card']) && $_POST['secure_submit_card'] !== 'new') {
                $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);

                if (isset($cards[$_POST['secure_submit_card']]['token_value'])) {
                    $hpstoken->tokenValue = (string)$cards[(int)$_POST['secure_submit_card']]['token_value'];
                    $useStoredCard = true;
                } else {
                    throw new Exception(__('Invalid saved card.', 'wc_securesubmit'));
                }
            } else {
                $hpstoken->tokenValue = $securesubmitToken;
            }

            try {
                $saveCardToCustomer = !$useStoredCard;
                $initialPayment = $this->orderGetTotal($order);
                $response = null;
                if ($initialPayment >= 0) {
                    $response = $this->processSubscriptionPayment($order, $initialPayment, $hpstoken, $saveCardToCustomer);
                }

                if (isset($response) && is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                if ($saveCardToCustomer) {
                    if (is_user_logged_in()) {
                        $tokenval = (string)$response->tokenData->tokenValue;
                        $saveToOrder = false;

                        if ($response->tokenData->responseCode == '0' && !$useStoredCard) {
                            try {
                                $uteResponse = $this->getCreditService()->updateTokenExpiration()
                                    ->withToken($tokenval)
                                    ->withExpMonth($exp_month)
                                    ->withExpYear($exp_year)
                                    ->execute();
                                $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);
                                foreach ($cards as $card) {
                                    if ($card['token_value'] === (string)$tokenval) {
                                        delete_user_meta(get_current_user_id(), '_secure_submit_card', $card);
                                        break;
                                    }
                                }
                            } catch (Exception $e) {
                                /** om nom nom */
                            }
                            add_user_meta(get_current_user_id(), '_secure_submit_card', array(
                                'last_four' => $last_four,
                                'exp_month' => $exp_month,
                                'exp_year' => $exp_year,
                                'token_value' => $tokenval,
                                'card_type' => $card_type,
                            ));
                            $saveToOrder = true;
                        }
                    }
                }

                if ($useStoredCard) {
                    $tokenval = $hpstoken->tokenValue;
                    $saveToOrder = true;
                }

                if ($saveToOrder) {
                    $this->saveTokenMeta($order, $tokenval);
                }

                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } catch (HpsException $e) {
                throw new Exception(__((string)$e->getMessage(), 'wc_securesubmit'));
            }
        } catch (Exception $e) {
            $error = __('Error:', 'wc_securesubmit') . ' "' . (string)$e->getMessage() . '"';
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error, 'error');
            } else {
                $woocommerce->add_error($error);
            }
            return;
        }
    }

    protected function saveTokenMeta($order, $token)
    {
        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');
        $order = wc_get_order($orderId);
        update_post_meta($orderId, '_securesubmit_card_token', $token);

        if (method_exists($order, 'set_payment_method')) {
            $order->set_payment_method($this->id, array(
                'post_meta' => array(
                    '_securesubmit_card_token' => $token,
                ),
            ));
        }

        // save to subscriptions in order
        foreach(wcs_get_subscriptions_for_order($orderId) as $subscription) {
            $subscriptionId = WC_SecureSubmit_Util::getData($subscription, 'get_id', 'id');
            update_post_meta($subscriptionId, '_securesubmit_card_token', $token);

            if (method_exists($order, 'set_payment_method')) {
                $order->set_payment_method($this->id, array(
                    'post_meta' => array(
                        '_securesubmit_card_token' => $token,
                    ),
                ));
            }
        }
    }

    public function scheduledSubscriptionPayment($amount, $order, $productId = null)
    {
        $orderPostStatus = WC_SecureSubmit_Util::getData($order, 'get_post_status', 'post_status');
        if (empty($orderPostStatus)) {
            $orderPostStatus = get_post_status(WC_SecureSubmit_Util::getData($order, 'get_id', 'id'));
        }

        // TODO: why is this necessary to prevent double authorization?
        if ($orderPostStatus !== 'wc-pending' && $orderPostStatus !== 'pending') {
            return;
        }

        $result = $this->processSubscriptionPayment($order, wc_format_decimal($amount, 2));

        if (is_wp_error($result)) {
            $order->update_status('failed', sprintf(__('SecureSubmit transaction failed: %s', 'wc_securesubmit'), $result->get_error_message()));
        }
    }

    public function processSubscriptionPayment($order, $initialPayment, $tokenData = null, $requestMulti = false)
    {
        global $woocommerce;

        $order = wc_get_order($order);
        $amount = wc_format_decimal($order->get_total(), 2);

        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');

        $tokenValue = get_post_meta($orderId, '_securesubmit_card_token', true);
        $token = new HpsTokenData();
        $token->tokenValue = $tokenValue;

        if (!isset($tokenValue) && $tokenData == null) {
            return new WP_Error('securesubmit_error', __('SecureSubmit payment token not found', 'wc_securesubmit'));
        }

        if ($tokenData != null) {
            $token = $tokenData;
        }

        try {
            $chargeService = $this->getCreditService();
            $hpsaddress = $this->getOrderAddress($order);
            $cardHolder = $this->getOrderCardHolder($order, $hpsaddress);

            $details = new HpsTransactionDetails();
            $details->invoiceNumber = $orderId;

            $response = null;
            if ($amount == 0 || (method_exists($order, 'needs_payment') && !$order->needs_payment())) {
                $response = $chargeService->verify()
                    ->withToken($token)
                    ->withCardHolder($cardHolder)
                    ->withRequestMultiUseToken($requestMulti)
                    ->execute();
            } else {
                $response = $chargeService->charge()
                    ->withAmount($amount)
                    ->withCurrency(strtolower(get_woocommerce_currency()))
                    ->withToken($token)
                    ->withCardHolder($cardHolder)
                    ->withRequestMultiUseToken($requestMulti)
                    ->withDetails($details)
                    ->execute();
            }

            $order->payment_complete($response->transactionId);
            $order->add_order_note(sprintf(
                __('SecureSubmit %s completed (Transaction ID: %s)', 'wc_securesubmit'),
                ($amount == 0 ? 'verify' : 'payment'),
                $response->transactionId
            ));
            add_post_meta($orderId, '_transaction_id', $response->transactionId, true);

            return $response;
        } catch (Exception $e) {
            return new WP_Error('securesubmit_error', sprintf(__('SecureSubmit payment error: %s', 'wc_securesubmit'), (string)$e->getMessage()));
        }
    }

    public function updateFailingPaymentMethod($old, $new, $key = null)
    {
        $oldOrderId = WC_SecureSubmit_Util::getData($old, 'get_id', 'id');
        $newOrderId = WC_SecureSubmit_Util::getData($new, 'get_id', 'id');

        update_post_meta($oldOrderId, '_securesubmit_card_token', get_post_meta($newOrderId, '_securesubmit_card_token', true));
    }

    public function addSubscriptionPaymentMeta($meta, $subscription)
    {
        $subscriptionId = WC_SecureSubmit_Util::getData($subscription, 'get_id', 'id');

        $meta[$this->id] = array(
            'post_meta' => array(
                '_securesubmit_card_token' => array(
                    'value' => get_post_meta($subscriptionId, '_securesubmit_card_token', true),
                    'label' => 'SecureSubmit payment token',
                ),
            ),
        );
        return $meta;
    }

    public function validateSubscriptionPaymentMeta($methodId, $meta)
    {
        if ($this->id === $methodId) {
            $token = $meta['post_meta']['_securesubmit_card_token'];
            if (!isset($token) || empty($token)) {
                throw new Exception(__('A SecureSubmit payment token is required.', 'wc_securesubmit'));
            }
        }
    }

    public function deleteResubscribeMeta($order)
    {
        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');
        delete_user_meta($orderId, '_securesubmit_card_token');
    }
}
new WC_Gateway_SecureSubmit_Subscriptions();
