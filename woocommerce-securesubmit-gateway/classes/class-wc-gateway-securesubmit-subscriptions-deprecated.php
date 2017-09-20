<?php

class WC_Gateway_SecureSubmit_Subscriptions_Deprecated extends WC_Gateway_SecureSubmit_Subscriptions
{
    public function init()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            add_action('scheduled_subscription_payment_' . $this->id, array($this, 'scheduledSubscriptionPayment'), 10, 3);
            add_action('woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array($this, 'updateFailingPaymentMethod'), 10, 3);
            add_filter('woocommerce_subscriptions_renewal_order_meta_query', array($this, 'removeRenewalOrderMeta'), 10, 4);
            add_filter('woocommerce_my_subscriptions_recurring_payment_method', array($this, 'maybeRenderSubscriptionPaymentMethod'), 10, 3);
        }
    }

    protected function orderHasSubscription($order)
    {
        if (function_exists('wcs_order_contains_subscription')) {
            return wcs_order_contains_subscription($order);
        }
        return WC_Subscriptions_Order::order_contains_subscription($order);
    }

    public function processSubscription($orderId)
    {
        $result = parent::processSubscription($orderId);
        $order = new WC_Order($orderId);
        $order->payment_complete();
        WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
        return $result;
    }

    public function scheduledSubscriptionPayment($amount, $order, $productId = null)
    {
        $result = $this->processSubscriptionPayment($order, wc_format_decimal($amount, 2));

        if (is_wp_error($result)) {
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order, $productId);
        } else {
            WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
        }
    }

    public function updateFailingPaymentMethod($old, $new, $subscriptionKey = null)
    {
        update_post_meta($old->id, '_securesubmit_card_token', get_post_meta($new->id, '_securesubmit_card_token', true));
    }

    public function removeRenewalOrderMeta($orderMetaQuery, $originalOrderId, $renewalOrderId, $newOrderRole)
    {
        if ('parent' == $newOrderRole) {
            $orderMetaQuery .= " AND `meta_key` <> '_securesubmit_card_token' ";
        }
        return $orderMetaQuery;
    }

    public function maybeRenderSubscriptionPaymentMethod($paymentMethodToDisplay, $subscriptionDetails, WC_Order $order)
    {
        $orderRecurringPaymentMethod = WC_SecureSubmit_Util::getData($order, 'get_recurring_payment_method', 'recurring_payment_method');
        $orderCustomerUser = WC_SecureSubmit_Util::getData($object, 'get_customer_user', 'customer_user');

        if ($this->id !== $orderRecurringPaymentMethod || !$orderCustomerUser) {
            return $paymentMethodToDisplay;
        }

        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');

        $userId = $orderCustomerUser;
        $token  = get_post_meta($orderId, '_securesubmit_card_token', true);
        $cards  = get_user_meta($userId, '_secure_submit_card', false);

        if ($cards) {
            $foundCard = false;
            foreach ($cards as $card) {
                if ($card['token_value'] === $token) {
                    $foundCard              = true;
                    $paymentMethodToDisplay = sprintf(__('Via %s card ending in %s', 'wc_securesubmit'), $card['card_type'], $card['last_four']);
                    break;
                }
            }
            if (!$foundCard) {
                $paymentMethodToDisplay = sprintf(__('Via %s card ending in %s', 'wc_securesubmit'), $card[0]['card_type'], $card[0]['last_four']);
            }
        }

        return $paymentMethodToDisplay;
    }

    protected function saveTokenMeta($order, $token)
    {
        $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');
        add_post_meta($orderId, '_securesubmit_card_token', $token, true);
    }
}
new WC_Gateway_SecureSubmit_Subscriptions();
