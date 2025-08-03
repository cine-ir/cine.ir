<?php
/**
 * Rolino Frontend Class
 * 
 * Handles frontend functionality and user interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Frontend {
    
    private $shortcodes;
    
    public function __construct() {
        $this->shortcodes = new Rolino_Shortcodes();
        
        // Add frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'handle_purchase_actions'));
        add_action('wp_head', array($this, 'add_meta_tags'));
        
        // AJAX handlers
        add_action('wp_ajax_rolino_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_nopriv_rolino_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_rolino_buy_plan', array($this, 'ajax_buy_plan'));
        add_action('wp_ajax_nopriv_rolino_buy_plan', array($this, 'ajax_buy_plan'));
        add_action('wp_ajax_rolino_consume_credit', array($this, 'ajax_consume_credit'));
        add_action('wp_ajax_nopriv_rolino_consume_credit', array($this, 'ajax_consume_credit'));
        
        // User authentication hooks
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'on_user_logout'));
        
        // Add user profile fields
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('rolino-frontend', ROLINO_PLUGIN_URL . 'assets/css/menus.css', array(), ROLINO_VERSION);
        wp_enqueue_script('rolino-frontend', ROLINO_PLUGIN_URL . 'assets/js/menus.js', array('jquery'), ROLINO_VERSION, true);
        
        // Enqueue Alpine.js for interactive components
        wp_enqueue_script('rolino-alpine', ROLINO_PLUGIN_URL . 'assets/js/alpine.min.js', array(), ROLINO_VERSION, true);
        
        // Localize script
        wp_localize_script('rolino-frontend', 'rolinoFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rolino_frontend_nonce'),
            'strings' => array(
                'loading' => __('در حال پردازش...', 'rolino'),
                'error' => __('خطایی رخ داد', 'rolino'),
                'success' => __('عملیات با موفقیت انجام شد', 'rolino'),
                'confirmPurchase' => __('آیا از خرید این طرح مطمئن هستید؟', 'rolino'),
                'loginRequired' => __('برای این عملیات باید وارد شوید', 'rolino')
            ),
            'user' => array(
                'isLoggedIn' => is_user_logged_in(),
                'id' => get_current_user_id()
            )
        ));
    }
    
    /**
     * Handle purchase actions
     */
    public function handle_purchase_actions() {
        if (!isset($_GET['action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'buy_plan':
                $this->handle_plan_purchase();
                break;
                
            case 'buy_single':
                $this->handle_single_buy();
                break;
        }
    }
    
    /**
     * Handle plan purchase
     */
    private function handle_plan_purchase() {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(add_query_arg($_GET, home_url('/'))));
            exit;
        }
        
        $plan_id = intval($_GET['plan_id'] ?? 0);
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        
        if (!$plan_id || !wp_verify_nonce($nonce, 'rolino_buy_plan_' . $plan_id)) {
            wp_die(__('پارامترهای نامعتبر', 'rolino'));
        }
        
        $this->process_plan_purchase($plan_id);
    }
    
    /**
     * Handle single buy
     */
    private function handle_single_buy() {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(add_query_arg($_GET, home_url('/'))));
            exit;
        }
        
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'rolino_buy_single')) {
            wp_die(__('پارامترهای نامعتبر', 'rolino'));
        }
        
        $this->process_single_buy();
    }
    
    /**
     * Process plan purchase
     * 
     * @param int $plan_id
     */
    private function process_plan_purchase($plan_id) {
        $user_id = get_current_user_id();
        $coupon_code = sanitize_text_field($_GET['coupon'] ?? '');
        $gateway = sanitize_text_field($_GET['gateway'] ?? 'zarinpal');
        
        $transactions = new Rolino_Transactions();
        
        $result = $transactions->create_transaction($user_id, $plan_id, $coupon_code, $gateway);
        
        if ($result['success']) {
            wp_redirect($result['redirect_url']);
            exit;
        } else {
            wp_die($result['message']);
        }
    }
    
    /**
     * Process single buy
     */
    private function process_single_buy() {
        $user_id = get_current_user_id();
        $gateway = sanitize_text_field($_GET['gateway'] ?? 'zarinpal');
        
        $single_buy_active = get_option('rolino_single_buy_active', 0);
        
        if (!$single_buy_active) {
            wp_die(__('خرید تکی در حال حاضر امکان‌پذیر نیست', 'rolino'));
        }
        
        $transactions = new Rolino_Transactions();
        
        $result = $transactions->create_transaction($user_id, 0, '', $gateway);
        
        if ($result['success']) {
            wp_redirect($result['redirect_url']);
            exit;
        } else {
            wp_die($result['message']);
        }
    }
    
    /**
     * AJAX apply coupon
     */
    public function ajax_apply_coupon() {
        check_ajax_referer('rolino_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('برای استفاده از کد تخفیف وارد شوید', 'rolino')));
        }
        
        $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
        $plan_id = intval($_POST['plan_id'] ?? 0);
        
        if (empty($coupon_code)) {
            wp_send_json_error(array('message' => __('کد تخفیف را وارد کنید', 'rolino')));
        }
        
        $coupons = new Rolino_Coupons();
        $plans = new Rolino_Plans();
        
        // If plan_id is 0, we're applying to all plans (percentage coupon)
        if ($plan_id == 0) {
            $coupon = $coupons->get_coupon_by_code($coupon_code);
            
            if (!$coupon) {
                wp_send_json_error(array('message' => __('کد تخفیف یافت نشد', 'rolino')));
            }
            
            if ($coupon->status != 1) {
                wp_send_json_error(array('message' => __('کد تخفیف غیرفعال است', 'rolino')));
            }
            
            // Check if coupon is expired
            if (strtotime($coupon->end_date) < current_time('timestamp')) {
                wp_send_json_error(array('message' => __('کد تخفیف منقضی شده است', 'rolino')));
            }
            
            // Check if user has already applied this coupon
            if ($coupons->is_coupon_applied_by_user($coupon->id, get_current_user_id())) {
                wp_send_json_error(array('message' => __('این کد تخفیف قبلاً اعمال شده است', 'rolino')));
            }
            
            // Get all active plans to find the discount percentage
            $all_plans = $plans->get_active_plans();
            $discount_percent = 0;
            
            foreach ($all_plans as $plan) {
                $plan_discount = $coupons->get_plan_discount_percent($coupon->id, $plan->id);
                if ($plan_discount > 0) {
                    $discount_percent = $plan_discount;
                    break;
                }
            }
            
            if ($discount_percent <= 0) {
                wp_send_json_error(array('message' => __('این کد تخفیف برای هیچ طرحی اعمال نمی‌شود', 'rolino')));
            }
            
            // Mark coupon as applied by user
            $coupons->mark_coupon_applied($coupon->id, get_current_user_id());
            
            wp_send_json_success(array(
                'message' => sprintf(__('کد تخفیف %d%% اعمال شد', 'rolino'), $discount_percent),
                'discount_percent' => $discount_percent,
                'applied_to_all' => true
            ));
        } else {
            // Apply to specific plan
            $validation_result = $coupons->validate_coupon($coupon_code, $plan_id, get_current_user_id());
            
            if (!$validation_result['valid']) {
                wp_send_json_error(array('message' => $validation_result['message']));
            }
            
            $plan = $plans->get_plan($plan_id);
            if (!$plan) {
                wp_send_json_error(array('message' => __('طرح یافت نشد', 'rolino')));
            }
            
            $discount_percent = $validation_result['discount_percent'];
            $original_price = floatval($plan->price);
            $discounted_price = $original_price * (1 - $discount_percent / 100);
            $savings = $original_price - $discounted_price;
            
            // Mark coupon as applied by user
            $coupon = $coupons->get_coupon_by_code($coupon_code);
            if ($coupon) {
                $coupons->mark_coupon_applied($coupon->id, get_current_user_id());
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('کد تخفیف %d%% اعمال شد', 'rolino'), $discount_percent),
                'discount_percent' => $discount_percent,
                'original_price' => $original_price,
                'discounted_price' => $discounted_price,
                'savings' => $savings,
                'formatted_savings' => number_format($savings, 0, '', ',')
            ));
        }
    }
    
    /**
     * AJAX buy plan
     */
    public function ajax_buy_plan() {
        check_ajax_referer('rolino_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('برای خرید طرح وارد شوید', 'rolino')));
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
        $gateway = sanitize_text_field($_POST['gateway'] ?? 'zarinpal');
        
        if (!$plan_id) {
            wp_send_json_error(array('message' => __('طرح انتخاب نشده است', 'rolino')));
        }
        
        $user_id = get_current_user_id();
        
        // Check if user can purchase this plan
        $can_purchase = $this->shortcodes->can_user_purchase_plan($plan_id, $user_id);
        if (!$can_purchase['can_purchase']) {
            wp_send_json_error(array('message' => $can_purchase['reason']));
        }
        
        $transactions = new Rolino_Transactions();
        
        $result = $transactions->create_transaction($user_id, $plan_id, $coupon_code, $gateway);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'redirect_url' => $result['redirect_url']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * AJAX consume credit
     */
    public function ajax_consume_credit() {
        check_ajax_referer('rolino_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('برای مصرف اعتبار وارد شوید', 'rolino')));
        }
        
        $amount = intval($_POST['amount'] ?? 1);
        $item_id = sanitize_text_field($_POST['item_id'] ?? '');
        
        if ($amount < 1) {
            wp_send_json_error(array('message' => __('مقدار اعتبار نامعتبر است', 'rolino')));
        }
        
        $user_id = get_current_user_id();
        $credits = new Rolino_Credits();
        
        $result = $credits->consume_credit($user_id, $amount, $item_id);
        
        if ($result) {
            $remaining_credits = $credits->get_user_credits($user_id);
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d اعتبار مصرف شد', 'rolino'), $amount),
                'remaining_credits' => $remaining_credits
            ));
        } else {
            wp_send_json_error(array('message' => __('اعتبار کافی ندارید', 'rolino')));
        }
    }
    
    /**
     * Handle user login
     * 
     * @param string $user_login
     * @param WP_User $user
     */
    public function on_user_login($user_login, $user) {
        // Update last login time
        update_user_meta($user->ID, 'rolino_last_login', current_time('mysql'));
        
        // Check for reserve subscriptions that should be activated
        $credits = new Rolino_Credits();
        $active_subscription = $credits->get_active_subscription($user->ID);
        
        if (!$active_subscription) {
            $reserve_subscription = $credits->get_reserve_subscription($user->ID);
            if ($reserve_subscription) {
                // Activate reserve subscription
                $credits->add_credit(
                    $user->ID,
                    $reserve_subscription->plan_id,
                    $reserve_subscription->credit,
                    0, // Duration already calculated
                    false, // Not reserve anymore
                    $reserve_subscription->start_time,
                    $reserve_subscription->end_time
                );
                
                // Remove from reserve
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'rolino_credits',
                    array('id' => $reserve_subscription->id),
                    array('%d')
                );
                
                // Trigger SMS
                do_action('rolino_main_subscription_activated', $user->ID, $reserve_subscription->plan_id, 0);
            }
        }
    }
    
    /**
     * Handle user logout
     */
    public function on_user_logout() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            update_user_meta($user_id, 'rolino_last_logout', current_time('mysql'));
        }
    }
    
    /**
     * Add user profile fields
     * 
     * @param WP_User $user
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('manage_options') && get_current_user_id() != $user->ID) {
            return;
        }
        
        $credits_obj = new Rolino_Credits();
        $user_credits = $credits_obj->get_user_credits($user->ID);
        $subscription_info = $credits_obj->get_user_subscription_info($user->ID);
        $phone = get_user_meta($user->ID, 'phone', true);
        
        ?>
        <h3><?php _e('اطلاعات رولینو', 'rolino'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="phone"><?php _e('شماره تلفن', 'rolino'); ?></label></th>
                <td>
                    <input type="text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
                    <p class="description"><?php _e('برای دریافت پیامک‌های رولینو', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('مجموع اعتبار', 'rolino'); ?></th>
                <td><strong><?php echo number_format($user_credits, 0, '', ','); ?></strong> <?php _e('اعتبار', 'rolino'); ?></td>
            </tr>
            <?php if (!empty($subscription_info['remaining_time'])): ?>
            <tr>
                <th><?php _e('زمان باقی‌مانده', 'rolino'); ?></th>
                <td><strong><?php echo $subscription_info['remaining_time']; ?></strong> <?php _e('روز', 'rolino'); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($subscription_info['plan_name'])): ?>
            <tr>
                <th><?php _e('طرح فعال', 'rolino'); ?></th>
                <td><strong><?php echo esc_html($subscription_info['plan_name']); ?></strong></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     * 
     * @param int $user_id
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        if (isset($_POST['phone'])) {
            update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        }
    }
    
    /**
     * Add meta tags for SEO and social sharing
     */
    public function add_meta_tags() {
        // Add meta tags for pages with rolino shortcodes
        if (has_shortcode(get_post()->post_content ?? '', 'rolino_plans')) {
            echo '<meta name="description" content="' . esc_attr(__('مشاهده و خرید طرح‌های اشتراک', 'rolino')) . '">' . "\n";
        }
    }
    
    /**
     * Get user's current page access
     * 
     * @param int $user_id
     * @param string $content_id
     * @return bool
     */
    public function user_has_access($user_id, $content_id = '') {
        if (!$user_id) {
            return false;
        }
        
        $credits = new Rolino_Credits();
        $user_credits = $credits->get_user_credits($user_id);
        
        // Simple access check - user needs at least 1 credit
        return $user_credits > 0;
    }
    
    /**
     * Display access restriction message
     * 
     * @return string
     */
    public function get_access_restriction_message() {
        if (!is_user_logged_in()) {
            return '<div class="rolino-access-message">' . 
                   '<p>' . __('برای دسترسی به این محتوا ابتدا وارد شوید', 'rolino') . '</p>' .
                   '<a href="' . wp_login_url() . '" class="rolino-login-btn">' . __('ورود', 'rolino') . '</a>' .
                   '</div>';
        }
        
        return '<div class="rolino-access-message">' . 
               '<p>' . __('برای دسترسی به این محتوا نیاز به اشتراک فعال دارید', 'rolino') . '</p>' .
               '<a href="' . home_url('/plans/') . '" class="rolino-plans-btn">' . __('مشاهده طرح‌ها', 'rolino') . '</a>' .
               '</div>';
    }
    
    /**
     * Protect content with shortcode
     * 
     * @param array $atts
     * @param string $content
     * @return string
     */
    public function protect_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'required_credits' => 1,
            'consume_credits' => 'yes',
            'content_id' => ''
        ), $atts);
        
        if (!is_user_logged_in()) {
            return $this->get_access_restriction_message();
        }
        
        $user_id = get_current_user_id();
        $credits = new Rolino_Credits();
        $user_credits = $credits->get_user_credits($user_id);
        
        $required_credits = intval($atts['required_credits']);
        
        if ($user_credits < $required_credits) {
            return $this->get_access_restriction_message();
        }
        
        // Consume credits if specified
        if ($atts['consume_credits'] === 'yes') {
            $credits->consume_credit($user_id, $required_credits, $atts['content_id']);
        }
        
        return do_shortcode($content);
    }
}