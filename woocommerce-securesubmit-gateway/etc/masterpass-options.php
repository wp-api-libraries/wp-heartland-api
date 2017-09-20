<?php

if (!defined('ABSPATH')) {
    exit();
}

return array(
    'enabled' => array(
        'title'       => __('Enable/Disable', 'wc_securesubmit'),
        'label'       => __('Enable MasterPass', 'wc_securesubmit'),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
    ),
    'title' => array(
        'title'       => __('Title', 'wc_securesubmit'),
        'type'        => 'text',
        'description' => __('This controls the title the user sees during checkout.', 'wc_securesubmit'),
        'default'     => __('MasterPass', 'wc_securesubmit')
    ),
    'description' => array(
        'title'       => __('Description', 'wc_securesubmit'),
        'type'        => 'textarea',
        'description' => __('This controls the description the user sees during checkout.', 'wc_securesubmit'),
        'default'     => 'Pay with your credit card via SecureSubmit.'
    ),
    'merchantId' => array(
        'title'       => __('Merchant ID', 'wc_securesubmit'),
        'type'        => 'text',
        'description' => __('Get your connection settings from your Heartland merchant account.', 'wc_securesubmit'),
        'default'     => ''
    ),
    'transactionPwd' => array(
        'title'       => __('Transaction Password', 'wc_securesubmit'),
        'type'        => 'text',
        'description' => __('Get your connection settings from your Heartland merchant account.', 'wc_securesubmit'),
        'default'     => ''
    ),
    'merchantCheckoutId' => array(
        'title'       => __('Merchant Checkout ID', 'wc_securesubmit'),
        'type'        => 'text',
        'description' => __('Get your connection settings from your Heartland merchant account.', 'wc_securesubmit'),
        'default'     => ''
    ),
    'environment' => array(
        'title'       => __('Environment', 'wc_securesubmit'),
        'type'        => 'select',
        'description' => __('Choose whether you wish to run transactions in sandbox or production.', 'wc_securesubmit'),
        'default'     => 'sandbox',
        'desc_tip'    => true,
        'options'     => array(
            'sandbox'    => __('Sandbox', 'wc_securesubmit'),
            'production' => __('Production', 'wc_securesubmit'),
        ),
    ),
    'customError' => array(
        'title'       => __('Custom Error', 'wc_securesubmit'),
        'type'        => 'textarea',
        'description' => __('To use the default MasterPass error message use %s in the custom message text, ex. My message. %s -> will be displayed as: My message. Original MasterPass message.', 'wc_securesubmit'),
        'default'     => '%s'
    ),
    'paymentAction' => array(
        'title'       => __('Payment Action', 'wc_securesubmit'),
        'type'        => 'select',
        'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'wc_securesubmit'),
        'default'     => 'sale',
        'desc_tip'    => true,
        'options'     => array(
            'sale'          => __('Capture', 'wc_securesubmit'),
            'authorization' => __('Authorize', 'wc_securesubmit'),
        ),
    ),
);
