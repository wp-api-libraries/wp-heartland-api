<?php
if (!defined('ABSPATH')) {
    exit();
}
?>

<h3><?php _e('MasterPass', 'wc_securesubmit'); ?></h3>
<p><?php _e('MasterPass description'); ?></p>
<?php if (in_array(get_woocommerce_currency(), array('USD'))): ?>
    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
<?php else: ?>
    <div class="inline error">
        <p>
            <strong><?php _e('Gateway Disabled', 'wc_securesubmit'); ?></strong>
            <?php echo __('Choose US Dollars as your store currency to enable MasterPass.', 'wc_securesubmit'); ?>
        </p>
    </div>
<?php endif; ?>
