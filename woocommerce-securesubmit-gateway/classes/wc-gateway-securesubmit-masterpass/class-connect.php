<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_MasterPass_Connect
{
    protected $masterpass = null;

    public function __construct(&$masterpass = null)
    {
        $this->masterpass = $masterpass;
    }

    public function call()
    {
        $forgetFlag = isset($_POST['forget_masterpass']) && wp_verify_nonce($_POST['_wpnonce'], 'masterpass_remove_long_access_token');

        $action = isset($_GET['mp_action']) ? $_GET['mp_action'] : '';
        $status = isset($_GET['mpstatus']) ? $_GET['mpstatus'] : '';
        $pairingVerifier = isset($_GET['pairing_verifier']) ? $_GET['pairing_verifier'] : '';
        $pairingToken = isset($_GET['pairing_token']) ? $_GET['pairing_token'] : '';

        // Grab parameters sent by us from lookup
        $payload = get_transient('_masterpass_payload');
        $orderId = get_transient('_masterpass_order_id');
        $orderNumber = get_transient('_masterpass_order_number');

        if ($forgetFlag) {
            delete_user_meta(get_current_user_id(), '_masterpass_long_access_token');
        }

        if ('pair' === $action && 'success' === $status) {
            try {
                $service = $this->masterpass->getService();

                $orderData = new HpsOrderData();
                $orderData->transactionStatus = $status;
                $orderData->checkoutType = HpsCentinelCheckoutType::PAIRING;
                $orderData->pairingToken = $pairingToken;
                $orderData->pairingVerifier = $pairingVerifier;

                // Authenticate the request with the information we've gathered
                $response = $service->authenticate(
                    $orderId,
                    null,
                    null,
                    $payload,
                    null,
                    $orderData
                );

                delete_user_meta(get_current_user_id(), '_masterpass_long_access_token');
                add_user_meta(
                    get_current_user_id(),
                    '_masterpass_long_access_token',
                    $response->longAccessToken,
                    true
                );
            } catch (Exception $e) { }
        }

        $path = plugin_dir_path(dirname(dirname(__FILE__)));
        include $path . 'templates/masterpass-connect.php';
    }
}
