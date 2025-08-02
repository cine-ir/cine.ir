<?php
/**
 * Credits Detailed Template
 * 
 * Template for displaying detailed credits info
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rolino-credits-detailed">
    <div class="credits-summary">
        <span class="credits-label"><?php _e('اعتبار کل:', 'rolino'); ?></span>
        <span class="credits-value"><?php echo number_format($total_credits); ?></span>
    </div>
    
    <?php if (!empty($subscription_info)): ?>
        <div class="subscription-details">
            <?php if ($subscription_info['active_subscription']): ?>
                <div class="subscription-item">
                    <span class="label"><?php _e('اشتراک فعال:', 'rolino'); ?></span>
                    <span class="value"><?php echo esc_html($subscription_info['active_subscription']->plan_name); ?></span>
                    <span class="remaining"><?php echo $subscription_info['remaining_days']; ?> <?php _e('روز باقی‌مانده', 'rolino'); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($subscription_info['reserve_subscription']): ?>
                <div class="subscription-item">
                    <span class="label"><?php _e('اشتراک رزرو:', 'rolino'); ?></span>
                    <span class="value"><?php echo esc_html($subscription_info['reserve_subscription']->plan_name); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.rolino-credits-detailed {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.credits-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.credits-label {
    font-weight: bold;
    color: #495057;
}

.credits-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #28a745;
}

.subscription-details {
    font-size: 0.9em;
}

.subscription-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding: 5px 0;
}

.subscription-item .label {
    color: #6c757d;
    font-weight: bold;
}

.subscription-item .value {
    color: #495057;
    font-weight: bold;
}

.subscription-item .remaining {
    color: #28a745;
    font-size: 0.8em;
}
</style>