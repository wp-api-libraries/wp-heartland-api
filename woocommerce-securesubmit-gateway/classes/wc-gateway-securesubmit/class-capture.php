<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_Capture
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call($orderId)
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
                $response = $chargeService->capture()
                    ->withTransactionId($transactionId)
                    ->withAmount(wc_format_decimal($order->get_total() - $order->get_total_refunded(), 2))
                    ->execute();
                $order->add_order_note(__('SecureSubmit payment captured', 'wc_securesubmit') . ' (Transaction ID: ' . $response->transactionId . ')');
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

    /**
     * Adds delayed capture functionality to the "Edit Order" screen
     *
     * @param array $actions
     *
     * @return array
     */
    public function addOrderAction($actions)
    {
        $actions[$this->parent->id . '_capture'] = __('Capture credit card authorization', 'wc_securesubmit');
        return $actions;
    }
}
