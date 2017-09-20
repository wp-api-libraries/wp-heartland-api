<h3><?php _e('SecureSubmit', 'wc_securesubmit'); ?></h3>
<p><?php _e('Secure Submit submits the credit card data directly to Heartland Payment Systems which responds with a token. That token is later charged.', 'wc_securesubmit'); ?></p>
<?php if (in_array(get_option('woocommerce_currency'), array('USD'))): ?>
    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
    <script>
        (function (window, document, $) {
            if (!$) { return; }

            var groups = ['anti-fraud', 'gift', 'threedsecure'];
            var groupsLength = groups.length;
            var i = 0;

            $(document).ready(function () {
                for (i = 0; i < groupsLength; i++) {
                    var group = groups[i];
                    var $groupSwitch = $('.enable-' + group);
                    var $groupOptions = $('.' + group).closest('tr');

                    if ($groupSwitch.is(':checked')) {
                        $groupOptions.show();
                    } else {
                        $groupOptions.hide();
                    }
                }
            });

            for (i = 0; i < groupsLength; i++) {
                $('.enable-' + groups[i]).click((function (group) {
                    return function () {
                        $('.' + group).closest('tr').toggle();
                    };
                }(groups[i])));
            }

        }(window, document, jQuery));
    </script>
<?php else: ?>
    <div class="inline error">
        <p>
            <strong><?php _e('Gateway Disabled', 'wc_securesubmit'); ?></strong>
            <?php echo __('Choose US Dollars as your store currency to enable SecureSubmit.', 'wc_securesubmit'); ?>
        </p>
    </div>
<?php endif; ?>
