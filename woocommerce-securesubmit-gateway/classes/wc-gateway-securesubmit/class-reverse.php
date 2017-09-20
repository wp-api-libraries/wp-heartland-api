<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_Reverse
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

        $originalAmount = wc_format_decimal($order->get_total(), 2);
        $totalRefunded = wc_format_decimal($order->get_total_refunded(), 2);
        $newAmount = wc_format_decimal($originalAmount - $order->get_total_refunded(), 2);

        if ($newAmount < 0) {
            // total reversed is more than original auth amount
            return false;
        }

        $details = new HpsTransactionDetails();
        $details->memo = $reason;

        try {
            $chargeService = $this->parent->getCreditService();
            try {
                $response = $chargeService->reverse()
                    ->withTransactionId($transactionId)
                    ->withAmount($originalAmount)
                    ->withCurrency(strtolower(get_woocommerce_currency()))
                    ->withDetails($details)
                    ->withAuthAmount($newAmount)
                    ->execute();
                $order->add_order_note(
                    __('SecureSubmit payment reversed', 'wc_securesubmit')
                    . ' (Transaction ID: ' . $response->transactionId . ')'
                    . ' to ' . wc_price($newAmount)
                );
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
