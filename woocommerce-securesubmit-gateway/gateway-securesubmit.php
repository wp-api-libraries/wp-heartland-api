<?php
/*
Plugin Name: WooCommerce SecureSubmit Gateway
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
Description: Heartland Payment Systems gateway for WooCommerce.
Version: 1.11.13
Author: SecureSubmit
Author URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
*/

class WooCommerceSecureSubmitGateway
{
    const SECURESUBMIT_GATEWAY_CLASS = 'WC_Gateway_SecureSubmit';

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'), 0);
        add_action('woocommerce_load', array($this, 'activate'));
        add_action('wp_enqueue_scripts', array($this, 'loadScripts'));
    }

    public function init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        load_plugin_textdomain('wc_securesubmit', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $this->loadClasses();

        $securesubmit = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS, 'instance'));
        add_filter('woocommerce_payment_gateways', array($this, 'addGateways'));
        add_action('woocommerce_after_my_account', array($this, 'savedCards'));
        add_action('woocommerce_order_actions', array($securesubmit->capture, 'addOrderAction'));
        add_action('woocommerce_order_action_' . $securesubmit->id . '_capture', array($securesubmit, 'process_capture'));

        // MasterPass
        $masterpass = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass', 'instance'));
        add_action('wp_ajax_securesubmit_masterpass_lookup', array($masterpass, 'lookupCallback'));
        add_action('wp_ajax_nopriv_securesubmit_masterpass_lookup', array($masterpass, 'lookupCallback'));
        add_shortcode('woocommerce_masterpass_review_order', array($masterpass, 'reviewOrderShortcode'));

        add_action('woocommerce_order_actions', array($masterpass->capture, 'addOrderAction'));
        add_action('woocommerce_order_action_' . $masterpass->id . '_capture', array($masterpass, 'process_capture'));
        add_action('woocommerce_after_my_account', array($masterpass, 'myaccountConnect'));
        add_action('wp_loaded', array($masterpass->reviewOrder, 'processCheckout'));

        // PayPal
        $paypal = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_PayPal', 'instance'));
        remove_action('init', 'woocommerce_paypal_express_review_order_page') ;
        remove_shortcode('woocommerce_review_order');
        add_action('wp_loaded', array($paypal, 'checkUrlForParams'));
        add_action('woocommerce_after_cart', array($paypal, 'maybeAddExpressButtonToCartPage'));
        add_shortcode('woocommerce_review_order', array($paypal->reviewOrder, 'addShortcode'));
        add_action('wp_enqueue_scripts', array($paypal->reviewOrder, 'setScripts'), 12);
        add_action('wp_ajax_wc_securesubmit_paypal_start_incontext', array($paypal, 'startIncontext'));
        add_action('wp_ajax_nopriv_wc_securesubmit_paypal_start_incontext', array($paypal, 'startIncontext'));
        $paypalCredit = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_PayPal_Credit', 'instance'));
        add_action('woocommerce_after_cart', array($paypalCredit, 'maybeAddExpressButtonToCartPage'));

        $giftCards         = new WC_Gateway_SecureSubmit_GiftCards;
        $giftCardPlacement = new giftCardOrderPlacement;

        if ($giftCards->allow_gift_cards) {
            add_filter('woocommerce_gateway_title',                   array($giftCards, 'update_gateway_title_checkout'), 10, 2);
            add_filter('woocommerce_gateway_description',             array($giftCards, 'update_gateway_description_checkout'), 10, 2);
            add_action('wp_head',                                     array($giftCards, 'set_ajax_url'));
            add_action('wp_ajax_nopriv_use_gift_card',                array($giftCards, 'applyGiftCard'));
            add_action('wp_ajax_use_gift_card',                       array($giftCards, 'applyGiftCard'));
            add_action('wp_ajax_nopriv_remove_gift_card',             array($giftCards, 'removeGiftCard'));
            add_action('wp_ajax_remove_gift_card',                    array($giftCards, 'removeGiftCard'));
            add_action('woocommerce_review_order_before_order_total', array($giftCards, 'addGiftCards'));
            add_action('woocommerce_cart_totals_before_order_total',  array($giftCards, 'addGiftCards'));
            add_filter('woocommerce_calculated_total',                array($giftCards, 'updateOrderTotal'), 10, 2);
            add_action('wp_enqueue_scripts',                          array($giftCards, 'removeGiftCardCode'));

            // Process checkout with gift cards
            add_filter('woocommerce_get_order_item_totals',    array( $giftCardPlacement, 'addItemsToOrderDisplay'),PHP_INT_MAX, 2);
            add_action('woocommerce_checkout_order_processed', array( $giftCardPlacement, 'processGiftCardsZeroTotal'), PHP_INT_MAX, 2);

            // Display gift cards used after checkout and on email
            add_filter('woocommerce_get_order_item_totals', array( $giftCardPlacement, 'addItemsToPostOrderDisplay'), PHP_INT_MAX, 2);
        }
    }

    /**
     * Handle behaviors that only should occur at plugin activation.
     */
    public function activate()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        $this->loadClasses();
        call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass', 'createOrderReviewPage'));

        add_filter('woocommerce_payment_gateways', array($this, 'addGateway'));
        add_action('woocommerce_after_my_account', array($this, 'savedCards'));

        // PayPal
        $paypal = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_PayPal', 'instance'));
        remove_action('init', 'woocommerce_paypal_express_review_order_page');
        remove_shortcode('woocommerce_review_order');
        add_action('wp_loaded', array($paypal, 'checkUrlForParams'));
        add_action('woocommerce_after_cart', array($paypal, 'maybeAddExpressButtonToCartPage'));
        add_shortcode('woocommerce_review_order', array($paypal->reviewOrder, 'addShortcode'));
        add_action('wp_enqueue_scripts', array($paypal->reviewOrder, 'setScripts'), 12);
        $paypalCredit = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_PayPal_Credit', 'instance'));
        add_action('woocommerce_after_cart', array($paypalCredit, 'maybeAddExpressButtonToCartPage'));
    }

    /**
     * Adds payment options to WooCommerce to be enabled by store admin.
     *
     * @param array $methods
     *
     * @return array
     */
    public function addGateways($methods)
    {
        $methods[] = self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass';
        if (class_exists('WC_Subscriptions_Order')) {
            $klass = self::SECURESUBMIT_GATEWAY_CLASS . '_Subscriptions';
            if (!function_exists('wcs_create_renewal_order')) {
                $klass .= '_Deprecated';
            }
            $methods[] = $klass;
        } else {
            $methods[] = self::SECURESUBMIT_GATEWAY_CLASS;
        }
        $methods[] = 'WC_Gateway_SecureSubmit_PayPal';
        $methods[] = 'WC_Gateway_SecureSubmit_PayPal_Credit';
        return $methods;
    }

    /**
     * Handles "Manage saved cards" interface to user.
     */
    public function savedCards()
    {
        $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);

        if (!$cards) {
            return;
        }

        if (isset($_POST['delete_card']) && wp_verify_nonce($_POST['_wpnonce'], "secure_submit_del_card")) {
            $card = $cards[(int)$_POST['delete_card']];
            delete_user_meta(get_current_user_id(), '_secure_submit_card', $card);
            unset($cards[(int)$_POST['delete_card']]);
        }

        if (!$cards) {
            return;
        }

        $path = plugin_dir_path(__FILE__);
        include $path . 'templates/saved-cards.php';
    }

    public function loadScripts()
    {
        if (!is_account_page()) {
            return;
        }
        // SecureSubmit custom CSS
        wp_enqueue_style('woocommerce_securesubmit', plugins_url('assets/css/securesubmit.css', __FILE__), array(), '1.0');
    }

    protected function loadClasses()
    {
        include_once('classes/class-util.php');
        include_once('classes/class-wc-gateway-securesubmit.php');
        include_once('classes/class-wc-gateway-securesubmit-paypal.php');
        include_once('classes/class-wc-gateway-securesubmit-paypal-credit.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions-deprecated.php');
        include_once('classes/class-wc-gateway-securesubmit-masterpass.php');
        include_once('classes/class-wc-gateway-securesubmit-giftcards.php');
        include_once('classes/class-giftcard-order-placement.php');
    }
}
new WooCommerceSecureSubmitGateway();
