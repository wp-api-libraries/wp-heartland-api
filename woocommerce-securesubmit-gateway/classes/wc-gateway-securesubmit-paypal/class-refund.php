<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_PayPal_Refund
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call($orderId, $amount, $reason)
    {
        $order = wc_get_order($orderId);

        if (!$this->parent->can_refund_order($order)) {
            return false;
        }

        $response = null;
        try {
            $porticoService = $this->parent->getPorticoService();
            $isPartial = isset($amount) && $amount > 0;
            $response = $porticoService->refund(
                $order->get_transaction_id(),
                $isPartial,
                wc_format_decimal($amount, 2)
            );
        } catch (Exception $e) {
            $error = __('Error processing refund:', 'wc_securesubmit') . ' "' . $e->getMessage() . '"';
            if (function_exists('wc_add_notice')) {
                wc_add_notice($error, 'error');
            } else {
                WC()->add_error($error);
            }
            return false;
        }

        if ($response->responseCode == "00") {
            $reason = $reason == '' ? '' : '. Reason for refund: '.$reason;
            $order->add_order_note(
                __('Heartland PayPal refund completed. Transaction id: '
                    . $response->transactionId . $reason, 'wc_securesubmit')
            );
            return true;
        }

        return false;
    }
}
