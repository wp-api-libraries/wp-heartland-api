<div class="securesubmit-header clearfix">
    <div class="secure"></div>
</div>

<fieldset>
    <!-- Start Description -->
    <?php if ($this->description): ?>
        <div class="securesubmit-content">
            <p class="securesubmit-description">
                <?php echo $this->description; ?>
            </p>
        </div>
        <hr />
    <?php endif; ?>
    <!-- End Description -->

    <!-- Start "if card saving is allowed" -->
    <?php $checked = ''; ?>
    <?php if ($this->allow_card_saving): ?>
        <div class="securesubmit-content">
            <!-- Start LOGGED IN SAVED CARDS -->
            <?php $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false); ?>
            <?php if (is_user_logged_in() && isset($cards)): ?>
                <div class="saved-creditcards-list-header saved-creditcards-list form-row form-row-wide no-bottom-margin no-bottom-padding">
                    <div class="ss-section-header clearfix">
                        <h6>Select Payment Method</h6>
                        <a class="button" style="float:right;" href="<?php echo get_permalink(get_option('woocommerce_myaccount_page_id')); ?>#saved-cards">
                            <?php _e('Manage Cards', 'wc_securesubmit'); ?>
                        </a>
                    </div>
                </div>

                <!-- START SAVED CREDIT CARD LIST -->
                <div class="saved-creditcards-list form-row form-row-wide no-bottom-margin">
                    <?php
                    $checked = ' checked="checked" ';
                    $totalcards = 0;
                    ?>
                    <?php foreach ($cards as $i => $card): ?>
                        <div class="clearfix saved-card saved-card-<?php echo strtolower($card['card_type']); ?><?php echo ($checked != '' ? ' active' : '');?>">
                            <div class="saved-card-selector">
                                <input <?php echo $checked; ?> class="saved-selector" type="radio" id="secure_submit_card_<?php echo $i; ?>" name="secure_submit_card" style="width:auto;" value="<?php echo $i; ?>" />
                            </div>
                            <div class="saved-card-info">
                                <label for="secure_submit_card_<?php echo $i; ?>">
                                    <p>
                                        <?php echo $card['card_type']; ?> ending in
                                        <?php echo $card['last_four']; ?>
                                    </p>
                                    <p><span>Expires on <?php echo $card['exp_month'] . '/' . $card['exp_year']; ?></span></p>
                                </label>
                            </div>
                           <div class="card-type-logo"></div>
                        </div>
                        <?php $checked = ""; ?>
                    <?php endforeach; ?>

                    <!-- START NEW CARD -->
                    <div class="clearfix saved-card saved-card-new<?php echo ($checked != '' ? ' active' : '');?>">
                        <div class="saved-card-selector">
                            <input <?php echo $checked; ?> type="radio" class="saved-selector" id="secure_submit_card_new" name="secure_submit_card" style="width:auto;" value="new" />
                        </div>
                        <div class="saved-card-info-new">
                            <label for="secure_submit_card_new">
                                <p>
                                    Pay with a new credit card
                                </p>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php $styletag = 'display:none;'; ?>
    <?php $newClass = ''; ?>
    <?php if (!isset($cards) || empty($cards)): ?>
      <?php $styletag = 'display:block;'; ?>
      <?php $newClass .= !is_user_logged_in() ? ' no-saved-cards' : ''; ?>
      <?php $newClass .= is_user_logged_in() ? ' logged-in-no-saved-cards' : ''; ?>
    <?php endif; ?>
    <div class="securesubmit-content new-card-content <?php echo $newClass;?>" style="<?php echo $styletag; ?>">
        <div class="securesubmit_new_card">
            <div class="securesubmit_new_card_info">
                <div class="form-row form-row-wide no-bottom-margin no-bottom-padding hideable">
                    <label for="securesubmit_card_number">
                        <?php _e("Credit Card number", 'wc_securesubmit') ?>
                        <span class="required">*</span>
                    </label>
                    <?php if ($this->use_iframes): ?>
                        <div id="securesubmit_card_number"></div>
                    <?php else: ?>
                    <div class="cc-number">
                      <input id="securesubmit_card_number" type="tel" autocomplete="off" class="input-text card-number" placeholder="•••• •••• •••• ••••" />
                      <div class="card-type-logo"></div>
                   </div>
                    <?php endif; ?>
                </div>
                <div class="clear"></div>
                <div class="form-row hideable no-bottom-margin">
                    <div class="form-row-first half-row">
                        <label for="securesubmit_card_expiration">
                            <?php _e("Expiration date", 'wc_securesubmit') ?>
                            <span class="required">*</span>
                        </label>
                        <?php if ($this->use_iframes): ?>
                            <div id="securesubmit_card_expiration"></div>
                        <?php else: ?>
                            <input id="securesubmit_card_expiration" type="tel" autocomplete="off" class="input-text expiry-date" placeholder="MM / YYYY" />
                        <?php endif; ?>
                    </div>
                    <div class="form-row-last half-row">
                        <label for="securesubmit_card_cvv">
                            <?php _e("Security code", 'wc_securesubmit') ?>
                            <span class="required">*</span>
                        </label>
                        <?php if ($this->use_iframes): ?>
                            <div id="securesubmit_card_cvv"></div>
                        <?php else: ?>
                           <div class="ss-cvv">
                            <input type="tel" id="securesubmit_card_cvv" maxlength="4" autocomplete="off" class="input-text card-cvc" placeholder="CVV" />
                             <div class="ss-cvv-icon"></div>
                           </div>
                        <?php endif; ?>
                        <span class="help securesubmit_card_csc_description"></span>
                    </div>
                </div>

                <?php if ($this->allow_card_saving == 'yes'): ?>
                    <div class="form-row form-row-wide no-top-margin no-top-padding no-bottom-paddig no-bottom-margin">
                        <p class="form-row form-row-wide securesubmit-save-cards">
                            <input type="checkbox" autocomplete="off" id="save_card" name="save_card" value="true" style="display:inline" />
                            <label for="save_card" style="display: inline;">
                                <?php _e("Save Credit Card for Future Use", 'wc_securesubmit') ?>
                            </label>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="clear"></div>
        </div>
    </div>
    <input type="hidden" name="securesubmit_token" id="securesubmit_token" />
</fieldset>

<?php
$gift_cards = new WC_Gateway_SecureSubmit_GiftCards();
$gift_cards_allowed = $gift_cards->giftCardsAllowed();

if ($this->allow_gift_cards && $gift_cards_allowed) : // Allow customers to pay with Heartland gift cards ?>
    <fieldset>
          <!-- Start Gift Card -->
          <div class="securesubmit-content gift-card-content">
                <div class="form-row form-row-wide" id="gift-card-row">
                      <label id="gift-card-label" for="gift-card-number"><?php _e('Use a gift card', 'wc_securesubmit'); ?></label>
                      <div id="gift-card-input">
                            <input type="tel" placeholder="Gift card" id="gift-card-number" value="" class="input-text">
                            <input type="tel" placeholder="PIN" id="gift-card-pin" value="" class="input-text">
                            <p id="gift-card-error"></p>
                            <p id="gift-card-success"></p>
                      </div>
                      <button id="apply-gift-card" class="button"><?php _e('Apply', 'wc_securesubmit'); ?></button>
                      <script>
                          jQuery("#apply-gift-card").on('click', function (event) {
                              event.preventDefault();
                              window.applyGiftCard();
                          });
                      </script>
                </div>
                <div class="clear"></div>
          </div>
          <!-- End Gift Card -->
    </fieldset>
<?php endif; ?>

<?php if ($this->use_iframes): // Create the iframes when WC refreshes the payment fields ?>
    <script>
        window.securesubmitLoadIframes = window.securesubmitLoadIframes || function () {};
        window.securesubmitLoadIframes();
    </script>
<?php endif; ?>
<?php // Attach the field event handlers when WC refreshes the payment fields ?>
<script>
    window.securesubmitLoadEvents = window.securesubmitLoadEvents || function () {};
    window.securesubmitLoadEvents();
</script>
