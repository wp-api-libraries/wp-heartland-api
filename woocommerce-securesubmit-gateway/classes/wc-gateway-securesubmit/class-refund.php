<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_Refund
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call($orderId, $amount, $reason)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            return false;
        }

        $transactionId = $this->parent->getOrderTransactionId($order);

        if (!$transactionId) {
            return false;
        }

        try {
            $chargeService = $this->parent->getCreditService();
            try {
                $response = $chargeService->refund()
                    ->withAmount(wc_format_decimal($amount, 2))
                    ->withCurrency(strtolower(get_woocommerce_currency()))
                    ->withTransactionId($transactionId)
                    ->execute();
                $order->add_order_note(__('SecureSubmit payment refunded', 'wc_securesubmit') . ' (Transaction ID: ' . $response->transactionId . ')');
                return true;
            } catch (HpsException $e) {
                $this->parent->throwUserError($e->getMessage());
            }
        } catch (Exception $e) {
            $error = __('Error:', 'wc_securesubmit') . ' "' . $e->getMessage() . '"';
            $this->parent->displayUserError($error);
            return false;
        }
    }
}
