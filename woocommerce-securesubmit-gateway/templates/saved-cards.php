<h2>Saved Credit Cards</h2>

<div class="saved-creditcards-list form-row form-row-wide account-saved-cards-list">
    
    <?php $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false); ?>
    
    <?php foreach ($cards as $i => $card): ?>

        <div class="clearfix saved-card saved-card-<?php esc_html_e( strtolower($card['card_type']) ); ?>">
            <div class="account-saved-card-logo">
                <div class="card-type-logo"></div>
            </div>
            <div class="saved-card-info">
                <label for="secure_submit_card_0">
                    <p><?php esc_html_e($card['card_type']); ?> ending in <?php esc_html_e($card['last_four']); ?></p>
                    <p><span>Expires on <?php esc_html_e($card['exp_month']); ?>/<?php esc_html_e($card['exp_year']); ?></span></p>
                </label>
            </div>
            
            <form action="#saved-cards" method="POST">
                <?php wp_nonce_field('secure_submit_del_card'); ?>
                <input type="hidden" name="delete_card" value="<?php esc_attr($i); ?>">
                <input type="submit" value="Delete Card">
            </form>
           
        </div>
    
    <?php endforeach; ?>
    
</div>