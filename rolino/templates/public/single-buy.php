<?php
/**
 * Single Buy Template
 * 
 * Template for displaying single buy option
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rolino-single-buy">
    <div class="single-buy-card">
        <div class="card-header">
            <h3><?php _e('خرید تکی اعتبار', 'rolino'); ?></h3>
        </div>
        
        <div class="card-body">
            <div class="buy-details">
                <div class="detail-item">
                    <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                    <span class="value"><?php echo number_format($single_buy_settings['credits']); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                    <span class="value"><?php echo number_format($single_buy_settings['duration']); ?> <?php _e('روز', 'rolino'); ?></span>
                </div>
                
                <div class="detail-item">
                    <span class="label"><?php _e('قیمت:', 'rolino'); ?></span>
                    <span class="value price"><?php echo number_format($single_buy_settings['price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                </div>
            </div>
            
            <div class="buy-actions">
                <button type="button" class="rolino-buy-credit rolino-btn rolino-btn-primary" 
                        data-credit-amount="<?php echo $single_buy_settings['credits']; ?>">
                    <?php echo $atts['button_text']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.rolino-single-buy {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
}

.single-buy-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background: #007bff;
    color: white;
    padding: 15px 20px;
    text-align: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.card-body {
    padding: 20px;
}

.buy-details {
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.detail-item .label {
    color: #666;
    font-weight: bold;
}

.detail-item .value {
    color: #333;
    font-weight: bold;
}

.detail-item .value.price {
    color: #28a745;
    font-size: 1.1em;
}

.buy-actions {
    text-align: center;
}

.rolino-btn {
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1em;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.rolino-btn-primary {
    background: #007bff;
    color: white;
}

.rolino-btn-primary:hover {
    background: #0056b3;
}

@media (max-width: 768px) {
    .rolino-single-buy {
        padding: 10px;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>