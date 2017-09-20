<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_SecureSubmit_PayPal_Credit extends WC_Gateway_SecureSubmit_PayPal
{
    private static $_instance = null;

    const ID = 'heartland_paypal_credit';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        parent::__construct();

        // properties
        $this->id           = self::ID;
        $this->method_title = __('Heartland Paypal Credit', 'wc_securesubmit');
        $this->enabled      =
            WC_Gateway_SecureSubmit_PayPal::instance()->enabled === 'yes' &&
            WC_Gateway_SecureSubmit_PayPal::instance()->enable_credit ? 'yes' : 'no';
        $this->title        = apply_filters(
            'wc_securesubmit_paypal_credit_title',
            $this->title . sprintf(' %s', __('Credit', 'wc_securesubmit'))
        );

        // hooks
        add_filter('woocommerce_get_sections_checkout', array(__CLASS__, 'removeFromCheckoutSettingsMenu'));
    }

    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function is_available()
    {
        return parent::is_available();
    }

    public function maybeAddExpressButtonToCartPage()
    {
        if ($this->enabled != 'yes') {
            return;
        }

        $this->setSession('checkout_form', $_POST);

        if (isset($_POST["paypalexpress_initiated"])) {
            $this->startExpressCheckout();
        } else {
            $path = dirname(plugin_dir_path(__FILE__));
            include $path . '/templates/paypal-express-button-credit.php';
        }
    }

    /**
     * get_icon function.
     *
     * @return string
     */
    public function get_icon()
    {
        ob_start();
        $path = dirname(plugin_dir_path(__FILE__));
        include $path . '/templates/paypal-icon.php';
        return apply_filters('woocommerce_gateway_icon', ob_get_clean(), $this->id);
    }

    /**
     * Process the payment and return the result
     *
     * @param int $orderId
     * @return array
     */
    public function process_payment($orderId)
    {
        $isExpressCheckout = WC()->session->get('ss-paypal-express-checkout-inprogress');
        if (!isset($expressCheckout) || false === $isExpressCheckout) {
            return $this->createSession->call($orderId, true);
        }

        $this->setSession('ss-paypal-express-checkout-inprogress', null);
    }

    /**
     * Removes the payment method from being a section for configuration
     *
     * All settings should be set through the parent class.
     *
     * @param array $sections
     * @return array
     */
    public static function removeFromCheckoutSettingsMenu($sections)
    {
        if (isset($sections[self::ID])) {
            unset($sections[self::ID]);
        }
        return $sections;
    }
}
