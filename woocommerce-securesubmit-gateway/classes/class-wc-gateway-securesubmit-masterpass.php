<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_MasterPass extends WC_Payment_Gateway
{
    private static $_instance = null;
    public $capture     = null;
    public $connect     = null;
    public $data        = null;
    public $lookup      = null;
    public $payment     = null;
    public $refund      = null;
    public $reviewOrder = null;

    public function __construct()
    {
        // includes
        require_once 'includes/Hps.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-capture.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-connect.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-data.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-lookup.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-payment.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-refund.php';
        require_once 'wc-gateway-securesubmit-masterpass/class-revieworder.php';

        // properties
        $this->id                   = 'securesubmit_masterpass';
        $this->method_title         = __('MasterPass', 'wc_securesubmit');
        $this->icon                 = 'https://www.mastercard.com/mc_us/wallet/img/en/US/mp_mc_acc_030px_gif.gif';
        $this->has_fields           = true;
        $this->initFormFields();
        $this->init_settings();
        $this->title                = $this->getSetting('title');
        // $this->description          = $this->getSetting('description');
        $this->enabled              = $this->getSetting('enabled');
        $this->merchantId           = $this->getSetting('merchantId');
        $this->transactionPwd       = $this->getSetting('transactionPwd');
        $this->merchantCheckoutId   = $this->getSetting('merchantCheckoutId');
        $this->environment          = $this->getSetting('environment');
        $this->customError          = $this->getSetting('customError');
        $this->paymentAction        = $this->getSetting('paymentAction');
        $this->supports             = array(
                                        'products',
                                        'refunds',
                                     );

        // actions
        add_action('wp_enqueue_scripts', array($this, 'paymentScripts'));
        add_action('admin_notices', array($this, 'checks'));
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // class references
        self::$_instance   = $this;
        $this->capture     = new WC_Gateway_SecureSubmit_MasterPass_Capture($this);
        $this->connect     = new WC_Gateway_SecureSubmit_MasterPass_Connect($this);
        $this->data        = new WC_Gateway_SecureSubmit_MasterPass_Data($this);
        $this->lookup      = new WC_Gateway_SecureSubmit_MasterPass_Lookup($this);
        $this->payment     = new WC_Gateway_SecureSubmit_MasterPass_Payment($this);
        $this->refund      = new WC_Gateway_SecureSubmit_MasterPass_Refund($this);
        $this->reviewOrder = new WC_Gateway_SecureSubmit_MasterPass_ReviewOrder($this);
    }

    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function checks()
    {
        if ('no' === $this->enabled) {
            return;
        }

        $settingsPage = admin_url('admin.php?page=woocommerce&tab=payment_gateways&subtab=gateway-securesubmit');
        $messageFmt = '';
        if (!$this->transactionPwd) {
            $messageFmt = 'SecureSubmit error: Please enter your transaction password <a href="%s">here</a>';
        } elseif (!$this->merchantId) {
            $messageFmt = 'SecureSubmit error: Please enter your merchant ID <a href="%s">here</a>';
        } elseif (!$this->merchantCheckoutId) {
            $messageFmt = 'SecureSubmit error: Please enter your merchant checkout ID <a href="%s">here</a>';
        }

        if ('' !== $messageFmt) {
            echo '<div class="error"><p>' . sprintf(__($messageFmt, 'wc_securesubmit'), $settingsPage) . '</p></div>';
        }
    }

    public function is_available()
    {
        if ('yes' === $this->enabled) {
            if (WC()->version < '1.5.8') {
                return false;
            }

            // we will be adding more currencies in the near future, but today we are bound to USD
            if (!in_array(get_option('woocommerce_currency'), array('USD'))) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function initFormFields()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        $this->form_fields = include $path . '/etc/masterpass-options.php';
    }

    public function admin_options()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        include $path . '/templates/masterpass-options.php';
    }

    public function payment_fields()
    {
        $longAccessToken = get_user_meta(get_current_user_id(), '_masterpass_long_access_token', true);

        if ('' !== $longAccessToken) {
            delete_user_meta(get_current_user_id(), '_masterpass_long_access_token');

            $cards = get_transient('_masterpass_connected_cards');
            if (false === $cards) {
                try {
                    $service = $this->getService();
                    $result = $service->preApproval($longAccessToken);
                    $cards = $result->preCheckoutData->Cards->Card;

                    add_user_meta(get_current_user_id(), '_masterpass_long_access_token', (string)$result->longAccessToken);
                    set_transient('_masterpass_wallet_name', (string)$result->preCheckoutData->WalletName, HOUR_IN_SECONDS / 4);
                    set_transient('_masterpass_wallet_id', (string)$result->preCheckoutData->ConsumerWalletId, HOUR_IN_SECONDS / 4);
                    set_transient('_masterpass_pre_checkout_transaction_id', (string)$result->preCheckoutData->preCheckoutTransactionId, HOUR_IN_SECONDS / 4);
                    set_transient('_masterpass_connected_cards', $cards, HOUR_IN_SECONDS / 4);
                } catch (Exception $e) { }
            }
        }

        $path = dirname(plugin_dir_path(__FILE__));
        include $path . '/templates/masterpass-fields.php';
    }

    public function paymentScripts()
    {
        if (!is_checkout()) {
            return;
        }

        if ($this->enabled === 'no') {
            return;
        }

        if ('production' === $this->environment) {
            $masterpassClient = 'https://www.masterpass.com/lightbox/Switch/integration/MasterPass.client.js';
        } else {
            $masterpassClient = 'https://sandbox.masterpass.com/lightbox/Switch/integration/MasterPass.client.js';
        }

        // MasterPass client library
        wp_enqueue_script('securesubmit_masterpass', $masterpassClient, array('jquery'), '6.0', true);
        // MasterPass js controller for WooCommerce
        wp_enqueue_script('woocommerce_securesubmit_masterpass', plugins_url('assets/js/masterpass.js', dirname(__FILE__)), array('jquery'), '1.0', true);

        $masterpassParams = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
        );

        wp_localize_script('woocommerce_securesubmit_masterpass', 'wc_securesubmit_masterpass_params', $masterpassParams);
    }

    /**
     * Creates a new MasterPass session.
     */
    public function lookupCallback()
    {
        $this->lookup->call();
    }

    /**
     * Handles "Connect with MasterPass" interface.
     */
    public function myaccountConnect()
    {
        if ('yes' === $this->enabled) {
            $this->connect->call();
        }
    }

    /**
     * Handles the `woocommerce_masterpass_review_order` shortcode used in the
     * "Order Review" page.
     *
     * @param array $atts
     *
     * @return string
     */
    public function reviewOrderShortcode($atts)
    {
        return WC_Shortcodes::shortcode_wrapper(
            array($this->reviewOrder, 'call'),
            $atts
        );
    }

    /**
     * Handles transactions initiated by the consumer
     *
     * @param int    $orderId
     * @param string $amount
     * @param string $reason
     *
     * @return boolean
     */
    public function process_payment($orderId)
    {
        return $this->payment->call($orderId);
    }

    /**
     * Captures an active Authorization
     *
     * @param WC_Order $order
     */
    public function process_capture($order)
    {
        return $this->capture->call($order);
    }

    /**
     * Handles refund/return transactions initiated by the merchant
     *
     * @param int    $orderId
     * @param string $amount
     * @param string $reason
     *
     * @return boolean
     */
    public function process_refund($orderId, $amount = null, $reason = '')
    {
        if ($amount !== null) {
            $amount = wc_format_decimal($amount, 2);
        }
        return $this->refund->call($orderId, $amount, $reason);
    }

    /**
     * Gets a configured service
     *
     * @return HpsMasterPassService
     */
    public function getService()
    {
        return $this->data->getService();
    }

    /**
     * Used in `masterpass-review-order.php` to format `HpsBuyerData` and
     * `HpsShippingInfo` as HTML.
     *
     * @param mixed $data
     *
     * @return string
     */
    public function getFormattedAddress($data)
    {
        return $this->data->getFormattedAddress($data);
    }

    /**
     * Gets generic address array from `HpsBuyerData`/`HpsShippingInfo`
     *
     * @param mixed $data
     *
     * @return array
     */
    public function getWCAddress($data)
    {
        return $this->data->getWCAddress($data);
    }

    /**
     * Gets a mapped `HpsBuyerData` object
     *
     * @param array $checkoutForm
     *
     * @return HpsBuyerData
     */
    public function getBuyerData($checkoutForm)
    {
        return $this->data->getBuyerData($checkoutForm);
    }

    /**
     * Gets a mapped `HpsPaymentData` object
     *
     * @param WC_Cart $cart
     *
     * @return HpsPaymentData
     */
    public function getPaymentData(WC_Cart $cart)
    {
        return $this->data->getPaymentData($cart);
    }

    /**
     * Gets a mapped `HpsShippingInfo` object
     *
     * @param array $checkoutForm
     *
     * @return HpsShippingInfo
     */
    public function getShippingInfo($checkoutForm)
    {
        return $this->data->getShippingInfo($checkoutForm);
    }

    /**
     * Gets a mapped set of `HpsLineItem` objects
     *
     * @return array
     */
    public function getLineItems($cart)
    {
        return $this->data->getLineItems($cart);
    }

    /**
     * Creates the "Order Review" page for MasterPass if it does not exist
     *
     * @return int
     */
    public static function createOrderReviewPage()
    {
        return wc_create_page(
            // $slug
            esc_sql(_x('masterpass-review-order','page_slug','woocommerce')),
            // $option
            'woocommerce_masterpass_review_order_page_id',
            // $title
            __('Checkout &rarr; Review Order','wc_securesubmit'),
            // $content
            '[woocommerce_masterpass_review_order]',
            // $parent
            wc_get_page_id('checkout')
        );
    }

    /**
     * Gets a single setting
     *
     * @param string $setting
     *
     * @return string
     */
    protected function getSetting($setting)
    {
        $value = null;
        if (isset($this->settings[$setting])) {
            $value = $this->settings[$setting];
        }
        return $value;
    }

    /**
     * Throws a generic `Exception` using the merchant supplied custom error
     * option as the format string.
     *
     * @param string $error
     *
     * @raises Exception
     */
    protected function throwUserError($error) {
        if ($customMessage = $this->customError) {
            $error = sprintf($customMessage, $error);
        }

        throw new Exception(__($error, 'wc_securesubmit'));
    }
}
