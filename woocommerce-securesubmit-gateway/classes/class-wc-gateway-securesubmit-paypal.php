<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_SecureSubmit_PayPal extends WC_Payment_Gateway
{
    private static $_instance = null;

    public $createSession = null;
    public $finalizeOrder = null;
    public $reviewOrder   = null;
    public $refund        = null;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // includes
        require_once 'wc-gateway-securesubmit-paypal/class-createsession.php';
        require_once 'wc-gateway-securesubmit-paypal/class-finalizeorder.php';
        require_once 'wc-gateway-securesubmit-paypal/class-revieworder.php';
        require_once 'wc-gateway-securesubmit-paypal/class-refund.php';

        // properties
        $this->id                 = 'heartland_paypal';
        $this->has_fields         = false;
        $this->order_button_text  = __('Proceed to PayPal', 'wc_securesubmit');
        $this->method_title       = __('Heartland Paypal', 'wc_securesubmit');
        $this->method_description = __('PayPal works by sending customers to PayPal where they can enter their payment information.', 'woocommerce');
        $this->supports           = array(
            'products',
            'refunds'
        );
        $this->init_form_fields();
        $this->init_settings();
        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->testmode       = 'yes' === $this->get_option('testmode', 'no');
        $this->debug          = 'yes' === $this->get_option('debug', 'no');
        $this->paymentaction  = $this->get_option('paymentaction');
        $this->public_key     = $this->get_option('public_key');
        $this->secret_key     = $this->get_option('secret_key');
        $this->enabled        = $this->get_option('enabled');
        $this->enable_credit  = 'yes' === $this->get_option('enable_credit', 'no');

        // actions
        add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'processPaypalCheckout'), 12);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'paymentScripts'));
        add_filter('script_loader_tag', array($this, 'utf8'), 10, 2);

        // class references
        $this->createSession = new WC_Gateway_SecureSubmit_PayPal_CreateSession($this);
        $this->finalizeOrder = new WC_Gateway_SecureSubmit_PayPal_FinalizeOrder($this);
        $this->reviewOrder   = new WC_Gateway_SecureSubmit_PayPal_ReviewOrder($this);
        $this->refund        = new WC_Gateway_SecureSubmit_PayPal_Refund($this);
    }

    public function is_available()
    {
        if ($this->enabled == "yes") {
            if (WC()->version < '1.5.8') {
                return false;
            }

            // we will be adding more currencies in the near future, but today we are bound to USD
            if (!in_array(get_option('woocommerce_currency'), array('USD'))) {
                return false;
            }

            if (!$this->secret_key) {
                return false;
            }

            if (!$this->public_key) {
                return false;
            }

            return true;
        }

        return false;
    }

    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function utf8($tag, $handle)
    {
        if (!in_array($handle, array('securesubmit', 'woocommerce_securesubmit'))) {
            return $tag;
        }
        return str_replace(' src', ' charset="utf-8" src', $tag);
    }

    public function paymentScripts()
    {
        if (!is_checkout() && !is_cart()) {
            return;
        }

        if ($this->enabled === 'no') {
            return;
        }

        $url = 'https://api.heartlandportico.com/SecureSubmit.v1/token/2.1/securesubmit.js';
        wp_enqueue_script('hps_wc_securesubmit_library', $url, array(), '2.1', true);

        wp_enqueue_script(
            'paypay_incontext_checkout',
            '//www.paypalobjects.com/api/checkout.js',
            array(),
            '1.0',
            true
        );

        // SecureSubmit js controller for WooCommerce
        wp_enqueue_script(
            'woocommerce_securesubmit',
            plugins_url('assets/js/securesubmit.js', dirname(__FILE__)),
            array('jquery', 'hps_wc_securesubmit_library', 'paypay_incontext_checkout'),
            '1.0',
            true
        );

        $params = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'env' => $this->testmode ? 'sandbox' : 'production',
            'isCheckout' => is_checkout() || is_checkout_pay_page() ? 'true' : 'false',
            'isCart' => is_cart() ? 'true' : 'false',
        );

        wp_localize_script('woocommerce_securesubmit', 'wc_securesubmit_paypal_params', $params);
    }

    public function startIncontext()
    {
        if (isset($_POST['paypalexpress_initiated'])) {
            $this->setSession('ss-paypal-express-checkout-inprogress', true);
            $this->setSession('checkout_form', $_POST);
        }

        $credit = isset($_POST['paypalexpress_credit']) || isset($_GET['paypalexpress_credit']);

        print json_encode($this->createSession->call(null, $credit));
        wp_die();
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
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        if ($this->settingsValidForUse()) {
            parent::admin_options();
        } else {
            $path = dirname(plugin_dir_path(__FILE__));
            include $path . '/templates/paypal-admin-not-available.php';
        }
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $path = dirname(plugin_dir_path(__FILE__));
        $this->form_fields = include $path . '/etc/settings-paypal.php';
    }

    public function checkUrlForParams()
    {
        if (empty($_GET['pp_action'])) {
            return;
        }

        $action = $_GET['pp_action'];

        if ($action == 'revieworder' || $action == 'payaction') {
            $this->processPaypalCheckout();
        }
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
            include $path . '/templates/paypal-express-button.php';
        }
    }

    public function startExpressCheckout()
    {
        $woocommerce = WC();
        $this->setSession('ss-paypal-express-checkout-inprogress', true);
        $this->setSession('checkout_form', $_POST);
        $credit = isset($_POST['paypalexpress_credit']) || isset($_GET['paypalexpress_credit']);

        $response = $this->createSession->call(null, $credit);
        $redirectUrl = $response['redirect'];

        if (isset($response['message'])) {
            $redirectUrl = wc_get_cart_url();
        }

        $this->softRedirect($redirectUrl);
        exit();
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
            return $this->createSession->call($orderId);
        }

        $this->setSession('ss-paypal-express-checkout-inprogress', null);
    }

    /**
     * Can the order be refunded via PayPal?
     * @param  WC_Order $order
     * @return bool
     */
    public function can_refund_order($order)
    {
        if (empty($order)) {
            return false;
        }

        $transactionId = $order->get_transaction_id();

        return !empty($transactionId);
    }

    /**
     * Process a refund if supported
     * @param  int $orderId
     * @param  float $amount
     * @param  string $reason
     * @return  boolean True or false based on success, or a WP_Error object
     */
    public function process_refund($orderId, $amount = null, $reason = '')
    {
        if ($amount !== null) {
            $amount = wc_format_decimal($amount, 2);
        }
        return $this->refund->call($orderId, $amount, $reason);
    }

     /**
     *  Process PayPal Checkout
     *
     *  Main action function that handles PPE actions:
     *  1. 'revieworder' - Customer has reviewed the order. Saves shipping info to order.
     *  2. 'payaction' - Customer has pressed "Place Order" on the review page.
     */
    public function processPaypalCheckout($posted = null)
    {
        if (isset($_GET['pp_action']) && $_GET['pp_action'] == 'revieworder') {
            $this->reviewOrder->call();
        } elseif (isset($_GET['pp_action']) && $_GET['pp_action'] == 'payaction') {
            $this->finalizeOrder->call();
        }
    }

    public function getPorticoService()
    {
        $config = new HpsServicesConfig();
        if ($this->testmode) {
            $config->username  = '30360021';
            $config->password  = '$Test1234';
            $config->deviceId  = '90911395';
            $config->licenseId = '20527';
            $config->siteId    = '20518';
            $config->versionNumber = '1510';
            $config->developerId = '002914';
            $config->soapServiceUri  = "https://api-uat.heartlandportico.com/paymentserver.v1/PosGatewayService.asmx";
        } else {
            $config->secretApiKey = $this->secret_key;
        }

        return new HpsPayPalService($config);
    }

    /**
     * @param $order
     * @return HpsBuyerData
     */
    public function getBuyerData($order)
    {
        $buyer = new HpsBuyerData();
        if ($order instanceof WC_Order) {
            $buyer->emailAddress = WC_SecureSubmit_Util::getData($order, 'get_billing_email', 'billing_email');
        }
        $buyer->cancelUrl = wc_get_cart_url();
        $buyer->returnUrl = add_query_arg(
            'pp_action',
            'revieworder',
            $this->reviewOrder->getPage()
        );
        return $buyer;
    }

    /**
     * @param WC_Order|WC_Cart $order
     * @return HpsPaymentData
     */
    public function getPaymentData($order)
    {
        $payment = new HpsPaymentData();
        if ($order instanceof WC_Order) {
            $payment->subtotal = $order->get_subtotal() - $order->get_total_discount();
            $payment->shippingAmount = $order->get_total_shipping();
            $payment->taxAmount = $order->get_total_tax();
        } else {
            $taxAmount = 0;

            foreach ($order->get_tax_totals() as $tax) {
                $taxAmount += $tax->amount;
            }

            $payment = new HpsPaymentData();
            $payment->subtotal = $order->subtotal_ex_tax - $order->get_cart_discount_total();
            $payment->shippingAmount = $order->shipping_total;
            $payment->taxAmount = $taxAmount;
        }

        $payment->paymentType = $this->paymentaction == 'authorization' ? 'Authorization' : 'Sale';
        $payment->subtotal = wc_format_decimal($payment->subtotal, 2);
        $payment->shippingAmount = wc_format_decimal($payment->shippingAmount, 2);
        $payment->taxAmount = wc_format_decimal($payment->taxAmount, 2);
        return $payment;
    }

    public function getShippingInfo($order)
    {
        if (null == $order || !$order instanceof WC_Order) {
            return null;
        }

        $shippingInfo = new HpsShippingInfo();
        $shippingInfo->name =
            WC_SecureSubmit_Util::getData($order, 'get_shipping_first_name', 'shipping_first_name') . ' ' .
            WC_SecureSubmit_Util::getData($order, 'get_shipping_last_name', 'shipping_last_name');
        $shippingInfo->address = new HpsAddress();
        $shippingInfo->address->address = WC_SecureSubmit_Util::getData($order, 'get_shipping_address_1', 'shipping_address_1');
        $shippingInfo->address->city = WC_SecureSubmit_Util::getData($order, 'get_shipping_city', 'shipping_city');
        $shippingInfo->address->state = WC_SecureSubmit_Util::getData($order, 'get_shipping_state', 'shipping_state');
        $shippingInfo->address->zip = WC_SecureSubmit_Util::getData($order, 'get_shipping_postcode', 'shipping_postcode');
        $shippingInfo->address->country = WC_SecureSubmit_Util::getData($order, 'get_shipping_country', 'shipping_country');

        return $shippingInfo;
    }

    public function getLineItems($order)
    {
        $lineItems = array();
        $calculated_total = 0;

        if ($order instanceof WC_Order) {
            foreach ($order->get_items(array('line_item', 'fee')) as $item) {
                if ('fee' === $item['type']) {
                    $lineItem          = $this->createLineItem($item['name'], 1, $item['line_total']);
                    $calculated_total += $item['line_total'];
                } else {
                    $product           = $order->get_product_from_item($item);
                    $lineItem          = $this->createLineItem(
                        $this->getOrderItemName($order, $item),
                        $item['qty'],
                        $order->get_item_subtotal($item, false),
                        $product->get_sku(),
                        $order->get_item_tax($item)
                    );
                    $calculated_total += $order->get_item_subtotal($item, false) * $item['qty'];
                }

                if (!$lineItem) {
                    continue;
                }

                $lineItems[] = $lineItem;
            }
        } else {
            foreach ($order->get_cart() as $item) {

                $lineItem = $this->createLineItem(
                    get_post(WC_SecureSubmit_Util::getData($item['data'], 'get_id', 'post'))->post_name,
                    $item['product_id'],
                    WC_SecureSubmit_Util::getData($item['data'], 'get_price', 'price'),
                    $item['quantity'],
                    $item['data']->get_sku()
                );

                if (!$lineItem) {
                    continue;
                }

                $hpsLineItems[] = $lineItem;
            }
        }

        if ($order->get_total_discount() > 0) {
            $discountItem = new HpsLineItem();
            $discountItem->name = 'Discount';
            $discountItem->number = 'discount';
            $discountItem->amount = wc_format_decimal(0 - $order->get_total_discount(), 2);
            $lineItems[] = $discountItem;
        }

        if (!$order instanceof WC_Order) {
            return $lineItems;
        }

        // Check for mismatched totals
        $calculatedTotal = wc_format_decimal(
            $calculated_total + $order->get_total_tax()
            + round($order->get_total_shipping(), 2)
            - round($order->get_total_discount(), 2),
            2
        );
        if ($calculatedTotal != wc_format_decimal($order->get_total(), 2)) {
            return false;
        }

        return $lineItems;
    }

    protected function createLineItem($name, $quantity = 1, $amount = 0, $number = '', $tax = 0)
    {
        if (!$name || $amount < 0 || $quantity < 0 || $tax < 0) {
            return false;
        }

        $item = new HpsLineItem();
        $item->name = html_entity_decode(wc_trim_string($name, 127), ENT_NOQUOTES, 'UTF-8');
        if (isset($number) && !empty($number)) {
            $item->number = html_entity_decode(wc_trim_string($number, 127), ENT_NOQUOTES, 'UTF-8');
        }
        $item->amount = number_format($amount, 2);
        $item->quantity = $quantity;
        $item->taxAmount = wc_format_decimal($tax, 2);

        return $item;
    }

    protected function getOrderItemName($order, $item)
    {
        $item_name = $item['name'];
        $item_meta = new WC_Order_Item_Meta($item['item_meta']);

        if ($meta = $item_meta->display(true, true)) {
            $item_name .= ' (' . $meta . ')';
        }

        return $item_name;
    }

    /**
     * Creates a "soft" redirect with JavaScript being output to the current buffer
     *
     * @param string $url
     */
    public function softRedirect($url)
    {
        ?>
        <script type="application/javascript">window.location.href="<?php echo $url; ?>";</script>
        <?php
    }

    public function setSession($key, $value)
    {
        WC()->session->$key = $value;
    }

    public function getSession($key)
    {
        return WC()->session->$key;
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @return bool
     */
    protected function settingsValidForUse()
    {
        return in_array(
            get_woocommerce_currency(),
            apply_filters(
                'woocommerce_paypal_supported_currencies',
                array('USD',)
            )
        );
    }
}
