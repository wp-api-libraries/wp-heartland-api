<?php

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_PayPal_ReviewOrder
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call()
    {
        wc_clear_notices();
        // The customer has logged into PayPal and approved order.
        // Retrieve the shipping details and present the order for completion.
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            define('WOOCOMMERCE_CHECKOUT', true);
        }

        if (!isset($_GET['token'])) {
            wc_add_notice(
                sprintf(__('We do not sell in your country, please try again with another address.', 'wc_securesubmit')),
                'error'
            );
            wp_redirect(get_permalink(wc_get_page_id('cart')));
            exit();
        }

        $this->parent->setSession('paypal_session_token', $_GET['token']);

        /**
          * Get sessioninfo from portico in case any changes are made on PayPal's site
          * @var HpsAltPaymentSessionInfo
          */
        $sessionInfo = $this->parent->getPorticoService()->sessionInfo($_GET['token']);
        $shippingInfo = $sessionInfo->shipping;

        if (empty($sessionInfo)) {
            return;
        }

        $this->parent->setSession('paypal_session_info', serialize($sessionInfo));


        if (!isset($shippingInfo->address->country)) {
            return;
        }

        if (!array_key_exists($shippingInfo->address->country, WC()->countries->get_allowed_countries())) {
            wc_add_notice(
                sprintf(__('We do not sell in your country, please try again with another address.', 'wc_securesubmit')),
                'error'
            );
            wp_redirect(get_permalink(wc_get_page_id('cart')));
            exit;
        };
        WC()->customer->set_shipping_country($shippingInfo->address->country);
    }

    /**
     * @return false|string
     */
    public function getPage($create = true)
    {
        $url = get_permalink(wc_get_page_id('review_order'));
        if (!$url && $create) {
            include_once(WC()->plugin_path() . '/includes/admin/wc-admin-functions.php');
            $pageId = wc_create_page(
                esc_sql(_x('review-order', 'page_slug', 'woocommerce')),
                'woocommerce_review_order_page_id',
                __('Checkout &rarr; Review Order', 'wc_securesubmit'),
                '[woocommerce_review_order]',
                wc_get_page_id('checkout')
            );
            $url = get_permalink($pageId);
        }
        return $url;
    }

    public function addShortcode($atts)
    {
        return WC_Shortcodes::shortcode_wrapper(
            array($this, 'displayPage'),
            $atts
        );
    }

    public function displayPage()
    {
        wc_print_notices();
        echo "
        <script>
        jQuery(document).ready(function($) {
            // Inputs/selects which update totals instantly
            $('form.checkout').unbind( 'submit' );
        });
        </script>
        ";

        $checkoutForm = $this->parent->getSession('checkout_form');
        if (isset($checkoutForm['terms'])) {
            $_POST['terms'] = $checkoutForm['terms'];
        }

        //Allow override in theme: <theme_name>/woocommerce/paypal-paypal-review-order.php
        $template = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        wc_get_template('paypal-review-order.php', array(), '', $template);
        do_action('woocommerce_ppe_checkout_order_review');
    }

    public function setScripts()
    {
        if (!is_page(wc_get_page_id('review_order'))) {
            return;
        }

        $path = str_replace(array('http:', 'https:'), '', WC()->plugin_url()) . '/assets/';
        wp_enqueue_script(
            'wc-checkout',
            plugins_url('/assets/js/checkout.js', __FILE__),
            array('jquery'),
            WC_VERSION,
            true
        );

        wp_localize_script('wc-checkout', 'wc_checkout_params', apply_filters('wc_checkout_params', array(
            'ajax_url' => WC()->ajax_url(),
            'ajax_loader_url' => apply_filters('woocommerce_ajax_loader_url', $path . 'images/ajax-loader@2x.gif'),
            'update_order_review_nonce' => wp_create_nonce("update-order-review"),
            'apply_coupon_nonce' => wp_create_nonce("apply-coupon"),
            'option_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
            'checkout_url' => add_query_arg('action', 'woocommerce_checkout', WC()->ajax_url()),
            'is_checkout' => 1
        )));
    }
}
