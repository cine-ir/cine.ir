<?php
/**
 * Rolino Shortcodes Class
 * 
 * Handles all frontend shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Shortcodes {
    
    public function __construct() {
        add_shortcode('rolino_plans', array($this, 'render_plans_shortcode'));
        add_shortcode('rolino_credits', array($this, 'render_credits_shortcode'));
        add_shortcode('rolino_user_subscription', array($this, 'render_user_subscription_shortcode'));
        add_shortcode('rolino_single_buy', array($this, 'render_single_buy_shortcode'));
    }
    
    /**
     * Render plans shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function render_plans_shortcode($atts) {
        $atts = shortcode_atts(array(
            'group' => '',
            'limit' => -1,
            'show_single_buy' => 'yes',
            'columns' => 3,
            'show_coupon_form' => 'yes'
        ), $atts, 'rolino_plans');
        
        if (!is_user_logged_in()) {
            return '<div class="rolino-message">' . __('برای مشاهده طرح‌ها وارد شوید', 'rolino') . '</div>';
        }
        
        ob_start();
        
        $plans_obj = new Rolino_Plans();
        $credits_obj = new Rolino_Credits();
        
        // Get user's current subscription info
        $user_id = get_current_user_id();
        $user_credits = $credits_obj->get_user_credits($user_id);
        $active_subscription = $credits_obj->get_active_subscription($user_id);
        $reserve_subscription = $credits_obj->get_reserve_subscription($user_id);
        
        // Get plans
        if (!empty($atts['group'])) {
            $plans = $plans_obj->get_plans_by_group($atts['group']);
        } else {
            $args = array(
                'status' => 1, // Only show active plans in frontend
                'limit' => intval($atts['limit'])
            );
            $plans = $plans_obj->get_plans($args);
        }
        
        // Debug: Check if plans exist
        if (empty($plans)) {
            // Try to get all plans regardless of status
            $all_plans = $plans_obj->get_plans(array('status' => 'all'));
            if (!empty($all_plans)) {
                // Filter active plans manually
                $plans = array_filter($all_plans, function($plan) {
                    return is_object($plan) && isset($plan->status) && $plan->status == 1;
                });
            }
        }
        
        // Ensure plans are objects and have required properties
        if (!empty($plans)) {
            $plans = array_filter($plans, function($plan) {
                return is_object($plan) && isset($plan->status) && $plan->status == 1;
            });
        }
        
        // Get plan groups for display (only active plans)
        $grouped_plans = $plans_obj->get_grouped_plans();
        $ungrouped_plans = $plans_obj->get_ungrouped_plans();
        
        // Filter out inactive plans from grouped and ungrouped plans
        if (!empty($grouped_plans)) {
            foreach ($grouped_plans as $group_name => &$group_plans) {
                $group_plans = array_filter($group_plans, function($plan) {
                    return isset($plan->status) && $plan->status == 1;
                });
            }
            $grouped_plans = array_filter($grouped_plans, function($plans) {
                return !empty($plans);
            });
        }
        
        if (!empty($ungrouped_plans)) {
            $ungrouped_plans = array_filter($ungrouped_plans, function($plan) {
                return isset($plan->status) && $plan->status == 1;
            });
        }
        
        // Get single buy settings
        $single_buy_active = get_option('rolino_single_buy_active', 0);
        $single_buy_settings = array(
            'duration' => intval(get_option('rolino_single_buy_duration', 30)),
            'credits' => intval(get_option('rolino_single_buy_credits', 5)),
            'price' => floatval(get_option('rolino_single_buy_price', 10000))
        );
        

        
        // Calculate subscription status for template
        $has_two_subscriptions = $active_subscription && $reserve_subscription;
        
        // Pass shortcodes instance to template
        $shortcodes = $this;
        
        include ROLINO_PLUGIN_PATH . 'templates/public/plans-display.php';
        
        return ob_get_clean();
    }
    
    /**
     * Render credits shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function render_credits_shortcode($atts) {
        $atts = shortcode_atts(array(
            'format' => 'number', // number, detailed
            'show_expiry' => 'no'
        ), $atts, 'rolino_credits');
        
        if (!is_user_logged_in()) {
            return '<span class="rolino-credits">' . __('۰ اعتبار', 'rolino') . '</span>';
        }
        
        $credits_obj = new Rolino_Credits();
        $user_id = get_current_user_id();
        $total_credits = $credits_obj->get_user_credits($user_id);
        
        ob_start();
        
        if ($atts['format'] === 'detailed') {
            $subscription_info = $credits_obj->get_user_subscription_info($user_id);
            include ROLINO_PLUGIN_PATH . 'templates/public/credits-detailed.php';
        } else {
            include ROLINO_PLUGIN_PATH . 'templates/public/credits-simple.php';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render user subscription shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function render_user_subscription_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_reserve' => 'yes',
            'show_history' => 'no'
        ), $atts, 'rolino_user_subscription');
        
        if (!is_user_logged_in()) {
            return '<div class="rolino-message">' . __('برای مشاهده اطلاعات اشتراک وارد شوید', 'rolino') . '</div>';
        }
        
        ob_start();
        
        $credits_obj = new Rolino_Credits();
        $user_id = get_current_user_id();
        
        $active_subscription = $credits_obj->get_active_subscription($user_id);
        $reserve_subscription = $credits_obj->get_reserve_subscription($user_id);
        $subscription_info = $credits_obj->get_user_subscription_info($user_id);
        
        if ($atts['show_history'] === 'yes') {
            $credit_history = $credits_obj->get_user_credit_history($user_id);
        }
        
        include ROLINO_PLUGIN_PATH . 'templates/public/user-subscription.php';
        
        return ob_get_clean();
    }
    
    /**
     * Render single buy shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function render_single_buy_shortcode($atts) {
        $atts = shortcode_atts(array(
            'button_text' => __('خرید تکی', 'rolino')
        ), $atts, 'rolino_single_buy');
        
        if (!is_user_logged_in()) {
            return '<div class="rolino-message">' . __('برای خرید تکی وارد شوید', 'rolino') . '</div>';
        }
        
        $single_buy_active = get_option('rolino_single_buy_active', 0);
        
        if (!$single_buy_active) {
            return '<div class="rolino-message">' . __('خرید تکی در حال حاضر امکان‌پذیر نیست', 'rolino') . '</div>';
        }
        
        ob_start();
        
        $single_buy_settings = array(
            'duration' => intval(get_option('rolino_single_buy_duration', 30)),
            'credits' => intval(get_option('rolino_single_buy_credits', 5)),
            'price' => floatval(get_option('rolino_single_buy_price', 10000))
        );
        
        include ROLINO_PLUGIN_PATH . 'templates/public/single-buy.php';
        
        return ob_get_clean();
    }
    
    /**
     * Calculate plan value ratio
     * 
     * @param object $plan
     * @return float
     */
    public function calculate_value_ratio($plan) {
        if ($plan->credits == 0) {
            return 0;
        }
        return round($plan->price / $plan->credits, 0);
    }
    
    /**
     * Format price
     * 
     * @param float $price
     * @return string
     */
    public function format_price($price) {
        return number_format($price, 0, '', ',');
    }
    
    /**
     * Get plan discount info
     * 
     * @param int $plan_id
     * @param string $coupon_code
     * @return array
     */
    public function get_plan_discount_info($plan_id, $coupon_code = '') {
        if (empty($coupon_code)) {
            return array(
                'has_discount' => false,
                'discount_percent' => 0,
                'original_price' => 0,
                'discounted_price' => 0
            );
        }
        
        $coupons = new Rolino_Coupons();
        $plans = new Rolino_Plans();
        
        $plan = $plans->get_plan($plan_id);
        if (!$plan) {
            return array('has_discount' => false);
        }
        
        $validation_result = $coupons->validate_coupon($coupon_code, $plan_id, get_current_user_id());
        
        if (!$validation_result['valid']) {
            return array('has_discount' => false);
        }
        
        $discount_percent = $validation_result['discount_percent'];
        $original_price = floatval($plan->price ?? 0);
        $discounted_price = $original_price * (1 - $discount_percent / 100);
        
        return array(
            'has_discount' => true,
            'discount_percent' => $discount_percent,
            'original_price' => $original_price,
            'discounted_price' => $discounted_price,
            'savings' => $original_price - $discounted_price
        );
    }
    
    /**
     * Get single buy discount info
     * 
     * @param string $coupon_code
     * @return array
     */
    public function get_single_buy_discount_info($coupon_code = '') {
        if (empty($coupon_code)) {
            return array(
                'has_discount' => false,
                'discount_percent' => 0,
                'original_price' => 0,
                'discounted_price' => 0
            );
        }
        
        $coupons = new Rolino_Coupons();
        $coupon = $coupons->get_coupon_by_code($coupon_code);
        
        if (!$coupon || $coupon->status != 1) {
            return array('has_discount' => false);
        }
        
        // Check if coupon is expired
        if (strtotime($coupon->end_date) < current_time('timestamp')) {
            return array('has_discount' => false);
        }
        
        // Check if user has already applied this coupon
        if (get_current_user_id() && $coupons->is_coupon_applied_by_user($coupon->id, get_current_user_id())) {
            return array('has_discount' => false);
        }
        
        // Get single buy discount
        $discount_percent = $coupons->get_single_buy_discount($coupon->id);
        if (!$discount_percent || $discount_percent <= 0) {
            return array('has_discount' => false);
        }
        
        // Get single buy settings
        $single_buy_settings = get_option('rolino_single_buy_settings', array(
            'price' => 10000,
            'credits' => 100,
            'duration' => 30
        ));
        
        $original_price = floatval($single_buy_settings['price']);
        $discounted_price = $original_price * (1 - $discount_percent / 100);
        
        return array(
            'has_discount' => true,
            'discount_percent' => $discount_percent,
            'original_price' => $original_price,
            'discounted_price' => $discounted_price,
            'savings' => $original_price - $discounted_price
        );
    }
    
    /**
     * Check if user can purchase plan
     * 
     * @param int $plan_id
     * @param int $user_id
     * @return array
     */
    public function can_user_purchase_plan($plan_id, $user_id) {
        $credits_obj = new Rolino_Credits();
        $plans_obj = new Rolino_Plans();
        
        $plan = $plans_obj->get_plan($plan_id);
        if (!$plan || $plan->status != 1) {
            return array(
                'can_purchase' => false,
                'reason' => __('طرح مورد نظر در دسترس نیست', 'rolino')
            );
        }
        
        $active_subscription = $credits_obj->get_active_subscription($user_id);
        $reserve_subscription = $credits_obj->get_reserve_subscription($user_id);
        
        // Check if user already has 2 subscriptions (active + reserve)
        if ($active_subscription && $reserve_subscription) {
            return array(
                'can_purchase' => false,
                'reason' => __('شما حداکثر تعداد مجاز اشتراک (2) را دارید', 'rolino')
            );
        }
        
        // Check active sessions limit
        if ($active_subscription && $plan->active_sessions > 0) {
            try {
                $current_sessions = $credits_obj->get_user_active_sessions($user_id);
                if ($current_sessions >= $plan->active_sessions) {
                    return array(
                        'can_purchase' => false,
                        'reason' => sprintf(__('شما به حد مجاز جلسات همزمان (%d) رسیده‌اید', 'rolino'), $plan->active_sessions)
                    );
                }
            } catch (Exception $e) {
                // If there's an error getting sessions, allow purchase
            }
        }
        
        return array(
            'can_purchase' => true,
            'will_be_reserve' => !empty($active_subscription)
        );
    }
    
    /**
     * Get user's remaining time text
     * 
     * @param int $user_id
     * @return string
     */
    public function get_user_remaining_time_text($user_id) {
        $credits_obj = new Rolino_Credits();
        $remaining_days = $credits_obj->get_remaining_time($user_id);
        
        if ($remaining_days <= 0) {
            return __('اشتراکی ندارید', 'rolino');
        }
        
        if ($remaining_days == 1) {
            return __('۱ روز باقی‌مانده', 'rolino');
        }
        
        return sprintf(__('%d روز باقی‌مانده', 'rolino'), $remaining_days);
    }
    
    /**
     * Check if plan is recommended
     * 
     * @param object $plan
     * @return bool
     */
    public function is_plan_recommended($plan) {
        // Mark plans with best value ratio as recommended
        // This is a simple logic - you can enhance it
        if (!isset($plan->credits) || $plan->credits == 0) {
            return false;
        }
        
        if (!isset($plan->price) || $plan->price == 0) {
            return false;
        }
        
        $value_ratio = $plan->price / $plan->credits;
        
        // If value ratio is less than 200 (price per credit), mark as recommended
        return $value_ratio < 200;
    }
    
    /**
     * Get plan badge HTML
     * 
     * @param object $plan
     * @return string
     */
    public function get_plan_badge($plan) {
        $badges = array();
        
        if ($this->is_plan_recommended($plan)) {
            $badges[] = '<span class="plan-badge badge-recommended">' . __('پیشنهادی', 'rolino') . '</span>';
        }
        
        if ($plan->duration >= 365) {
            $badges[] = '<span class="plan-badge badge-annual">' . __('سالانه', 'rolino') . '</span>';
        } elseif ($plan->duration >= 90) {
            $badges[] = '<span class="plan-badge badge-quarterly">' . __('فصلی', 'rolino') . '</span>';
        } elseif ($plan->duration >= 30) {
            $badges[] = '<span class="plan-badge badge-monthly">' . __('ماهانه', 'rolino') . '</span>';
        }
        
        return implode('', $badges);
    }
    
    /**
     * Generate plan purchase URL
     * 
     * @param int $plan_id
     * @return string
     */
    public function get_plan_purchase_url($plan_id) {
        return add_query_arg(array(
            'action' => 'buy_plan',
            'plan_id' => $plan_id,
            'nonce' => wp_create_nonce('rolino_buy_plan_' . $plan_id)
        ), home_url('/'));
    }
    
    /**
     * Generate single buy purchase URL
     * 
     * @return string
     */
    public function get_single_buy_url() {
        return add_query_arg(array(
            'action' => 'buy_single',
            'nonce' => wp_create_nonce('rolino_buy_single')
        ), home_url('/'));
    }
}