<?php
if (!defined('ABSPATH')) {
    exit();
}
?>

<div class="masterpass-checkout">
  <input type="hidden"
         name="action"
         value="securesubmit_masterpass_lookup" />

  <?php if (!empty($cards)): ?>
    <h6>Credit Cards</h6>
    <?php foreach ($cards as $card): ?>
      <label>
        <input type="radio"
               name="masterpass_card_id"
               value="<?= $card->CardId; ?>"
               <?php if ($card->SelectedAsDefault == 'true'): ?> checked="checked"<?php endif;?> />
        <?= $card->BrandName; ?>
        ending in <?= $card->LastFour; ?>
        expiring <?= $card->ExpiryMonth; ?>/<?= $card->ExpiryYear; ?>
        <?php if ($card->SelectedAsDefault == 'true'): ?>(default)<?php endif; ?>
      </label><br />
    <?php endforeach; ?>
  <?php endif; ?>

  <button type="button" class="button" id="securesubmit-buy-with-masterpass">
    <span class="masterpass-logo">
      <img src="https://www.mastercard.com/mc_us/wallet/img/en/US/mcpp_wllt_btn_chk_147x034px.png"
           alt="<?= __('Buy with MasterPass'); ?>" />
    </span>
    <span class="sr-only"><?= __('Buy with MasterPass'); ?></span>
  </button>

  <a href="http://www.mastercard.com/mc_us/wallet/learnmore/en"
     class="masterpass-learn-more"
     target="_blank"
     title="<?= __('Learn more about MasterPass', 'wc_securesubmit'); ?>">
    <?= __('Learn more', 'wc_securesubmit'); ?>
  </a>

  <script type="text/javascript">
    window.securesubmitMasterPassLookup = window.securesubmitMasterPassLookup || function () {};
    window.securesubmitMasterPassLookup();
  </script>
</div>
