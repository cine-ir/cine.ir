<?php
/**
 * Plans Display Template
 * 
 * Template for displaying plans in frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function to render plan action button
function render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription) {
    $has_two_subscriptions = $active_subscription && $reserve_subscription;
    
    if ($has_two_subscriptions) {
        echo '<button type="button" class="rolino-btn you-have-2-sub" disabled>';
        _e('خرید طرح', 'rolino');
        echo '</button>';
    } elseif ($purchase_check['can_purchase']) {
        echo '<button type="button" class="rolino-buy-plan rolino-btn rolino-btn-primary" data-plan-id="' . ($plan->id ?? 0) . '">';
        if ($purchase_check['will_be_reserve']) {
            _e('رزرو طرح', 'rolino');
        } else {
            _e('خرید طرح', 'rolino');
        }
        echo '</button>';
    } else {
        echo '<div class="rolino-message rolino-message-error">';
        echo esc_html($purchase_check['reason']);
        echo '</div>';
    }
}

// Enqueue required scripts and styles
wp_enqueue_script('rolino-frontend-js', ROLINO_PLUGIN_URL . 'assets/js/menus.js', array('jquery'), ROLINO_VERSION, true);
wp_enqueue_style('rolino-frontend-css', ROLINO_PLUGIN_URL . 'assets/css/menus.css', array(), ROLINO_VERSION);

// Localize script for AJAX
wp_localize_script('rolino-frontend-js', 'rolino_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('rolino_frontend_nonce'),
    'strings' => array(
        'processing' => __('در حال پردازش...', 'rolino'),
        'error' => __('خطایی رخ داد', 'rolino'),
        'success' => __('عملیات با موفقیت انجام شد', 'rolino')
    )
));
?>

<div class="rolino-plans-container">
    
    <?php if (!empty($user_credits)): ?>
        <div class="rolino-user-info">
            <div class="rolino-user-details">
                <div class="rolino-current-subscription">
                    <span class="label"><?php _e('نام اشتراک فعلی:', 'rolino'); ?></span>
                    <span class="value"><?php echo $active_subscription ? esc_html($active_subscription->plan_name ?? '') : __('هیچ اشتراک فعالی ندارید', 'rolino'); ?></span>
                </div>
                
                <div class="rolino-current-time">
                    <span class="label"><?php _e('زمان اشتراک فعلی:', 'rolino'); ?></span>
                    <span class="value"><?php echo $shortcodes->get_user_remaining_time_text($user_id); ?></span>
                </div>
                
                <div class="rolino-current-credits">
                    <span class="label"><?php _e('اعتبار فعلی:', 'rolino'); ?></span>
                    <span class="value"><?php echo number_format($user_credits); ?></span>
                </div>
                
                <?php if ($reserve_subscription): ?>
                    <div class="rolino-reserve-subscription">
                        <span class="label"><?php _e('نام اشتراک رزرو:', 'rolino'); ?></span>
                        <span class="value"><?php echo esc_html($reserve_subscription->plan_name ?? ''); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($atts['show_coupon_form'] === 'yes'): ?>
        <div class="rolino-coupon-form">
            <form id="rolino-coupon-form">
                <input type="text" id="rolino-coupon-code" placeholder="<?php _e('کد تخفیف', 'rolino'); ?>" />
                <button type="button" id="rolino-apply-coupon" class="rolino-btn"><?php _e('اعمال کد تخفیف', 'rolino'); ?></button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="rolino-plans-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
        <?php if ($atts['show_single_buy'] === 'yes' && $single_buy_active): ?>
            <?php 
            $can_buy_single = !$active_subscription;
            $has_two_subscriptions = $active_subscription && $reserve_subscription;
            
            // Get single buy discount info
            $single_buy_discount_info = $shortcodes->get_single_buy_discount_info(isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
            ?>
            <div class="rolino-plan-item single-buy-plan" data-plan-id="0" data-original-price="<?php echo $single_buy_settings['price']; ?>">
                <div class="plan-header">
                    <h3 class="plan-name"><?php _e('خرید تکی', 'rolino'); ?></h3>
                    <?php echo $shortcodes->get_plan_badge((object)array('credits' => $single_buy_settings['credits'], 'duration' => $single_buy_settings['duration'])); ?>
                </div>
                
                <div class="plan-details">
                    <div class="plan-credits">
                        <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                        <span class="value"><?php echo number_format($single_buy_settings['credits']); ?></span>
                    </div>
                    
                    <div class="plan-duration">
                        <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                        <span class="value"><?php echo number_format($single_buy_settings['duration']); ?> <?php _e('روز', 'rolino'); ?></span>
                    </div>
                </div>
                
                <div class="plan-price">
                    <?php if ($single_buy_discount_info['has_discount']): ?>
                        <span class="price-old"><?php echo $shortcodes->format_price($single_buy_discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                        <span class="price-now"><?php echo $shortcodes->format_price($single_buy_discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                        <span class="discount-badge"><?php echo $single_buy_discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                    <?php else: ?>
                        <span class="price-now"><?php echo $shortcodes->format_price($single_buy_settings['price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="plan-actions">
                    <?php if ($has_two_subscriptions): ?>
                        <button type="button" class="rolino-btn you-have-2-sub" disabled>
                            <?php _e('خرید طرح', 'rolino'); ?>
                        </button>
                    <?php elseif (!$can_buy_single): ?>
                        <button type="button" class="rolino-btn single-buy" disabled>
                            <?php _e('خرید تکی', 'rolino'); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="rolino-buy-credit rolino-btn rolino-btn-primary" 
                                data-credit-amount="<?php echo $single_buy_settings['credits']; ?>">
                            <?php _e('خرید تکی', 'rolino'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plans)): ?>
            <?php foreach ($plans as $plan): ?>
                <?php 
                try {
                    $purchase_check = $shortcodes->can_user_purchase_plan($plan->id, $user_id);
                    $discount_info = $shortcodes->get_plan_discount_info($plan->id, isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
                } catch (Exception $e) {
                    $purchase_check = array('can_purchase' => false, 'reason' => 'خطا در بررسی طرح');
                    $discount_info = array('has_discount' => false);
                }
                ?>
                
                <div class="rolino-plan-item" data-plan-id="<?php echo $plan->id ?? 0; ?>" data-original-price="<?php echo $plan->price ?? 0; ?>">
                    <div class="plan-header">
                        <h3 class="plan-name"><?php echo esc_html($plan->plan_name ?? ''); ?></h3>
                        <?php echo $shortcodes->get_plan_badge($plan); ?>
                    </div>
                    
                    <div class="plan-details">
                        <div class="plan-credits">
                            <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->credits ?? 0); ?></span>
                        </div>
                        
                        <div class="plan-duration">
                            <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->duration ?? 0); ?> <?php _e('روز', 'rolino'); ?></span>
                        </div>
                        
                        <?php if (($plan->active_sessions ?? 0) > 1): ?>
                            <div class="plan-sessions">
                                <span class="label"><?php _e('جلسات همزمان:', 'rolino'); ?></span>
                                <span class="value"><?php echo number_format($plan->active_sessions ?? 0); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="plan-price">
                        <?php if ($discount_info['has_discount']): ?>
                            <span class="price-old"><?php echo $shortcodes->format_price($discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                            <span class="price-now"><?php echo $shortcodes->format_price($discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                            <span class="discount-badge"><?php echo $discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                        <?php else: ?>
                            <span class="price-now"><?php echo $shortcodes->format_price($plan->price ?? 0); ?> <?php _e('تومان', 'rolino'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="plan-value">
                        <span class="value-ratio"><?php echo $shortcodes->format_price($shortcodes->calculate_value_ratio($plan)); ?> <?php _e('تومان به ازای هر اعتبار', 'rolino'); ?></span>
                    </div>
                    
                    <div class="plan-actions">
                        <?php render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($plans) && !empty($grouped_plans)): ?>
            <?php foreach ($grouped_plans as $group_name => $group_plans): ?>
                <div class="rolino-plan-group">
                    <h3 class="group-title"><?php echo esc_html($group_name); ?></h3>
                    <div class="rolino-plans-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">

                        <?php foreach ($group_plans as $plan): ?>
                            <?php 
                            try {
                                $purchase_check = $shortcodes->can_user_purchase_plan($plan->id, $user_id);
                                $discount_info = $shortcodes->get_plan_discount_info($plan->id, isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
                            } catch (Exception $e) {
                                $purchase_check = array('can_purchase' => false, 'reason' => 'خطا در بررسی طرح');
                                $discount_info = array('has_discount' => false);
                            }
                            ?>
                            
                            <div class="rolino-plan-item" data-plan-id="<?php echo $plan->id ?? 0; ?>" data-original-price="<?php echo $plan->price ?? 0; ?>">
                                <div class="plan-header">
                                    <h3 class="plan-name"><?php echo esc_html($plan->plan_name ?? ''); ?></h3>
                                    <?php echo $shortcodes->get_plan_badge($plan); ?>
                                </div>
                                
                                <div class="plan-details">
                                    <div class="plan-credits">
                                        <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                                        <span class="value"><?php echo number_format($plan->credits ?? 0); ?></span>
                                    </div>
                                    
                                    <div class="plan-duration">
                                        <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                                        <span class="value"><?php echo number_format($plan->duration ?? 0); ?> <?php _e('روز', 'rolino'); ?></span>
                                    </div>
                                    
                                    <?php if (($plan->active_sessions ?? 0) > 1): ?>
                                        <div class="plan-sessions">
                                            <span class="label"><?php _e('جلسات همزمان:', 'rolino'); ?></span>
                                            <span class="value"><?php echo number_format($plan->active_sessions ?? 0); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="plan-price">
                                    <?php if ($discount_info['has_discount']): ?>
                                        <span class="price-old"><?php echo $shortcodes->format_price($discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                        <span class="price-now"><?php echo $shortcodes->format_price($discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                        <span class="discount-badge"><?php echo $discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                                    <?php else: ?>
                                        <span class="price-now"><?php echo $shortcodes->format_price($plan->price ?? 0); ?> <?php _e('تومان', 'rolino'); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="plan-value">
                                    <span class="value-ratio"><?php echo $shortcodes->format_price($shortcodes->calculate_value_ratio($plan)); ?> <?php _e('تومان به ازای هر اعتبار', 'rolino'); ?></span>
                                </div>
                                
                                <div class="plan-actions">
                                    <?php render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($plans) && empty($grouped_plans) && !empty($ungrouped_plans)): ?>
            <?php foreach ($ungrouped_plans as $plan): ?>
                <?php 
                try {
                    $purchase_check = $shortcodes->can_user_purchase_plan($plan->id, $user_id);
                    $discount_info = $shortcodes->get_plan_discount_info($plan->id, isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
                } catch (Exception $e) {
                    $purchase_check = array('can_purchase' => false, 'reason' => 'خطا در بررسی طرح');
                    $discount_info = array('has_discount' => false);
                }
                ?>
                
                <div class="rolino-plan-item" data-plan-id="<?php echo $plan->id ?? 0; ?>" data-original-price="<?php echo $plan->price ?? 0; ?>">
                    <div class="plan-header">
                        <h3 class="plan-name"><?php echo esc_html($plan->plan_name ?? ''); ?></h3>
                        <?php echo $shortcodes->get_plan_badge($plan); ?>
                    </div>
                    
                    <div class="plan-details">
                        <div class="plan-credits">
                            <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->credits ?? 0); ?></span>
                        </div>
                        
                        <div class="plan-duration">
                            <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->duration ?? 0); ?> <?php _e('روز', 'rolino'); ?></span>
                        </div>
                        
                        <?php if (($plan->active_sessions ?? 0) > 1): ?>
                            <div class="plan-sessions">
                                <span class="label"><?php _e('جلسات همزمان:', 'rolino'); ?></span>
                                <span class="value"><?php echo number_format($plan->active_sessions ?? 0); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="plan-price">
                        <?php if ($discount_info['has_discount']): ?>
                            <span class="price-old"><?php echo $shortcodes->format_price($discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                            <span class="price-now"><?php echo $shortcodes->format_price($discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                            <span class="discount-badge"><?php echo $discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                        <?php else: ?>
                            <span class="price-now"><?php echo $shortcodes->format_price($plan->price ?? 0); ?> <?php _e('تومان', 'rolino'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="plan-value">
                        <span class="value-ratio"><?php echo $shortcodes->format_price($shortcodes->calculate_value_ratio($plan)); ?> <?php _e('تومان به ازای هر اعتبار', 'rolino'); ?></span>
                    </div>
                    
                    <div class="plan-actions">
                        <?php render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($plans) && empty($grouped_plans) && empty($ungrouped_plans)): ?>
            <div class="rolino-no-plans">
                <p><?php _e('هیچ طرحی در دسترس نیست.', 'rolino'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($grouped_plans)): ?>
        <div class="rolino-grouped-plans">
            <?php foreach ($grouped_plans as $group_name => $group_plans): ?>
                <div class="rolino-plan-group">
                    <h4><?php echo esc_html($group_name); ?></h4>
                    <div class="rolino-plans-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
                        <?php foreach ($group_plans as $plan): ?>
                            <?php 
                            try {
                                $purchase_check = $shortcodes->can_user_purchase_plan($plan->id, $user_id);
                                $discount_info = $shortcodes->get_plan_discount_info($plan->id, isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
                            } catch (Exception $e) {
                                $purchase_check = array('can_purchase' => false, 'reason' => 'خطا در بررسی طرح');
                                $discount_info = array('has_discount' => false);
                            }
                            ?>
                            
                            <div class="rolino-plan-item" data-plan-id="<?php echo $plan->id ?? 0; ?>" data-original-price="<?php echo $plan->price ?? 0; ?>">
                                <div class="plan-header">
                                    <h3 class="plan-name"><?php echo esc_html($plan->plan_name ?? ''); ?></h3>
                                    <?php echo $shortcodes->get_plan_badge($plan); ?>
                                </div>
                                
                                <div class="plan-details">
                                    <div class="plan-credits">
                                        <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                                        <span class="value"><?php echo number_format($plan->credits ?? 0); ?></span>
                                    </div>
                                    
                                    <div class="plan-duration">
                                        <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                                        <span class="value"><?php echo number_format($plan->duration ?? 0); ?> <?php _e('روز', 'rolino'); ?></span>
                                    </div>
                                    
                                    <?php if (($plan->active_sessions ?? 0) > 1): ?>
                                        <div class="plan-sessions">
                                            <span class="label"><?php _e('جلسات همزمان:', 'rolino'); ?></span>
                                            <span class="value"><?php echo number_format($plan->active_sessions ?? 0); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="plan-price">
                                    <?php if ($discount_info['has_discount']): ?>
                                        <span class="price-old"><?php echo $shortcodes->format_price($discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                        <span class="price-now"><?php echo $shortcodes->format_price($discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                        <span class="discount-badge"><?php echo $discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                                    <?php else: ?>
                                        <span class="price-now"><?php echo $shortcodes->format_price($plan->price ?? 0); ?> <?php _e('تومان', 'rolino'); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="plan-value">
                                    <span class="value-ratio"><?php echo $shortcodes->format_price($shortcodes->calculate_value_ratio($plan)); ?> <?php _e('تومان به ازای هر اعتبار', 'rolino'); ?></span>
                                </div>
                                
                                <div class="plan-actions">
                                    <?php render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($ungrouped_plans)): ?>
        <div class="rolino-ungrouped-plans">
            <h3><?php _e('طرح‌های بدون گروه', 'rolino'); ?></h3>
            <div class="rolino-plans-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);">
                <?php foreach ($ungrouped_plans as $plan): ?>
                    <?php 
                    try {
                        $purchase_check = $shortcodes->can_user_purchase_plan($plan->id, $user_id);
                        $discount_info = $shortcodes->get_plan_discount_info($plan->id, isset($_GET['coupon_code']) ? $_GET['coupon_code'] : '');
                    } catch (Exception $e) {
                        $purchase_check = array('can_purchase' => false, 'reason' => 'خطا در بررسی طرح');
                        $discount_info = array('has_discount' => false);
                    }
                    ?>
                    
                    <div class="rolino-plan-item" data-plan-id="<?php echo $plan->id ?? 0; ?>" data-original-price="<?php echo $plan->price ?? 0; ?>">
                        <div class="plan-header">
                            <h3 class="plan-name"><?php echo esc_html($plan->plan_name ?? ''); ?></h3>
                            <?php echo $shortcodes->get_plan_badge($plan); ?>
                        </div>
                        
                        <div class="plan-details">
                                                    <div class="plan-credits">
                            <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->credits ?? 0); ?></span>
                        </div>
                        
                        <div class="plan-duration">
                            <span class="label"><?php _e('مدت:', 'rolino'); ?></span>
                            <span class="value"><?php echo number_format($plan->duration ?? 0); ?> <?php _e('روز', 'rolino'); ?></span>
                        </div>
                        
                        <?php if (($plan->active_sessions ?? 0) > 1): ?>
                            <div class="plan-sessions">
                                <span class="label"><?php _e('جلسات همزمان:', 'rolino'); ?></span>
                                <span class="value"><?php echo number_format($plan->active_sessions ?? 0); ?></span>
                            </div>
                        <?php endif; ?>
                        </div>
                        
                        <div class="plan-price">
                            <?php if ($discount_info['has_discount']): ?>
                                <span class="price-old"><?php echo $shortcodes->format_price($discount_info['original_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                <span class="price-now"><?php echo $shortcodes->format_price($discount_info['discounted_price']); ?> <?php _e('تومان', 'rolino'); ?></span>
                                <span class="discount-badge"><?php echo $discount_info['discount_percent']; ?>% <?php _e('تخفیف', 'rolino'); ?></span>
                            <?php else: ?>
                                <span class="price-now"><?php echo $shortcodes->format_price($plan->price ?? 0); ?> <?php _e('تومان', 'rolino'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="plan-value">
                            <span class="value-ratio"><?php echo $shortcodes->format_price($shortcodes->calculate_value_ratio($plan)); ?> <?php _e('تومان به ازای هر اعتبار', 'rolino'); ?></span>
                        </div>
                        
                        <div class="plan-actions">
                            <?php render_plan_action_button($purchase_check, $plan, $active_subscription, $reserve_subscription); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.rolino-plans-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.rolino-user-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.rolino-credits-info,
.rolino-subscription-info,
.rolino-reserve-info {
    display: flex;
    align-items: center;
    gap: 5px;
}

.rolino-coupon-form {
    margin-bottom: 20px;
    text-align: center;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.rolino-coupon-form input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 10px;
    min-width: 200px;
}

.rolino-coupon-form button {
    padding: 10px 20px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.rolino-coupon-form button:hover {
    background: #005a87;
}

.rolino-coupon-form button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.rolino-plans-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.rolino-plan-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.rolino-plan-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.plan-header {
    margin-bottom: 15px;
}

.plan-name {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.2em;
}

.plan-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    margin: 2px;
}

.badge-recommended {
    background: #28a745;
    color: white;
}

.badge-annual {
    background: #007bff;
    color: white;
}

.badge-quarterly {
    background: #17a2b8;
    color: white;
}

.badge-monthly {
    background: #6c757d;
    color: white;
}

.plan-details {
    margin-bottom: 15px;
}

.plan-details > div {
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.plan-details .label {
    color: #666;
    font-size: 0.9em;
}

.plan-details .value {
    font-weight: bold;
    color: #333;
}

.plan-price {
    margin-bottom: 15px;
    position: relative;
}

.price-now {
    font-size: 1.5em;
    font-weight: bold;
    color: #28a745;
    display: block;
}

.price-old {
    text-decoration: line-through;
    color: #999;
    font-size: 0.9em;
    display: block;
    margin-bottom: 5px;
}

.discount-badge {
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    display: inline-block;
    margin-top: 5px;
}

.savings-info {
    color: #28a745;
    font-weight: bold;
    margin-top: 5px;
}

.plan-value {
    margin-bottom: 20px;
    font-size: 0.9em;
    color: #666;
}

.plan-actions {
    text-align: center;
}

.rolino-btn {
    padding: 10px 20px;
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

.rolino-message {
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.rolino-message-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.rolino-no-plans {
    text-align: center;
    padding: 40px;
    color: #666;
}

.rolino-grouped-plans,
.rolino-ungrouped-plans {
    margin-top: 30px;
}

.rolino-grouped-plans h3,
.rolino-ungrouped-plans h3 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.rolino-plan-group {
    margin-bottom: 30px;
}

.rolino-plan-group h4 {
    margin-bottom: 15px;
    color: #555;
}

@media (max-width: 768px) {
    .rolino-plans-grid {
        grid-template-columns: 1fr !important;
    }
    
    .rolino-user-info {
        flex-direction: column;
        text-align: center;
    }
    
    .rolino-coupon-form input {
        width: 100%;
        margin-right: 0;
        margin-bottom: 10px;
    }
}
</style>