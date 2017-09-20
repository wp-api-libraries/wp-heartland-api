<img src="https://www.paypalobjects.com/webstatic/mktg/logo/AM_mc_vs_dc_ae.jpg"
     alt="<?php echo __('PayPal Acceptance Mark', 'wc_securesubmit'); ?>" />
<?php $url = esc_url('https://www.paypal.com/us/webapps/mpp/paypal-popup'); ?>
<a href="<?php echo $url; ?>" class="about_paypal"
   onclick="javascript:window.open('<?php echo $url; ?>','WIPaypal','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;"
   title="<?php echo esc_attr__('What is PayPal?', 'wc_securesubmit'); ?>">
    <?php echo esc_attr__('What is PayPal?', 'wc_securesubmit'); ?>
</a>
