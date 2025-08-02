<?php
/**
 * Rolino Admin Menu Class
 * 
 * Handles the admin menu and submenus
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('wp_ajax_rolino_add_plan_group', array($this, 'ajax_add_plan_group'));
        add_action('wp_ajax_rolino_update_plan_group', array($this, 'ajax_update_plan_group'));
        add_action('wp_ajax_rolino_delete_plan_group', array($this, 'ajax_delete_plan_group'));
        add_action('wp_ajax_rolino_load_more_content', array($this, 'ajax_load_more_content'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu
        add_menu_page(
            __('رولینو', 'rolino'),
            __('رولینو', 'rolino'),
            $capability,
            'rolino',
            array($this, 'dashboard_page'),
            $this->get_menu_icon(),
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'rolino',
            __('داشبورد', 'rolino'),
            __('داشبورد', 'rolino'),
            $capability,
            'rolino',
            array($this, 'dashboard_page')
        );
        
        // Plans submenu
        add_submenu_page(
            'rolino',
            __('طرح‌ها', 'rolino'),
            __('طرح‌ها', 'rolino'),
            $capability,
            'rolino-plans',
            array($this, 'plans_page')
        );
        
        // Plan Groups submenu
        add_submenu_page(
            'rolino',
            __('گروه‌بندی طرح‌ها', 'rolino'),
            __('گروه‌بندی طرح‌ها', 'rolino'),
            $capability,
            'rolino-plan-groups',
            array($this, 'plan_groups_page')
        );
        
        // Transactions submenu
        add_submenu_page(
            'rolino',
            __('تراکنش‌ها', 'rolino'),
            __('تراکنش‌ها', 'rolino'),
            $capability,
            'rolino-transactions',
            array($this, 'transactions_page')
        );
        
        // Coupons submenu
        add_submenu_page(
            'rolino',
            __('کدهای تخفیف', 'rolino'),
            __('کدهای تخفیف', 'rolino'),
            $capability,
            'rolino-coupons',
            array($this, 'coupons_page')
        );
        
        // Members submenu
        add_submenu_page(
            'rolino',
            __('اعضا', 'rolino'),
            __('اعضا', 'rolino'),
            $capability,
            'rolino-members',
            array($this, 'members_page')
        );
        
        // Revenue submenu
        add_submenu_page(
            'rolino',
            __('مدیریت درآمد', 'rolino'),
            __('مدیریت درآمد', 'rolino'),
            $capability,
            'rolino-revenue',
            array($this, 'revenue_page')
        );
        
        // SMS Scenarios submenu
        add_submenu_page(
            'rolino',
            __('سناریوهای SMS', 'rolino'),
            __('سناریوهای SMS', 'rolino'),
            $capability,
            'rolino-sms-scenarios',
            array($this, 'sms_scenarios_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'rolino',
            __('تنظیمات', 'rolino'),
            __('تنظیمات', 'rolino'),
            $capability,
            'rolino-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Get menu icon
     * 
     * @return string
     */
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10 2C8.89543 2 8 2.89543 8 4V6H6C4.89543 6 4 6.89543 4 8V16C4 17.1046 4.89543 18 6 18H14C15.1046 18 16 17.1046 16 16V8C16 6.89543 15.1046 6 14 6H12V4C12 2.89543 11.1046 2 10 2ZM10 4H10V6H10V4ZM6 8H14V16H6V8ZM8 10V14H12V10H8Z" fill="#a7aaad"/>
</svg>');
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        // Get statistics
        $plans = new Rolino_Plans();
        $credits = new Rolino_Credits();
        $transactions = new Rolino_Transactions();
        $coupons = new Rolino_Coupons();
        
        $plan_stats = $plans->get_plan_statistics();
        $credit_stats = $credits->get_credit_statistics();
        $transaction_stats = $transactions->get_transaction_statistics();
        $coupon_stats = $coupons->get_coupon_statistics();
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Plans page
     */
    public function plans_page() {
        $plans_admin = new Rolino_Plans_Admin();
        $plans_admin->display_page();
    }
    
    /**
     * Plan Groups page
     */
    public function plan_groups_page() {
        include ROLINO_PLUGIN_PATH . 'templates/admin/plan-groups.php';
    }
    
    /**
     * Transactions page
     */
    public function transactions_page() {
        include ROLINO_PLUGIN_PATH . 'templates/admin/transactions.php';
    }
    
    /**
     * Coupons page
     */
    public function coupons_page() {
        $coupons_admin = new Rolino_Coupons_Admin();
        $coupons_admin->display_page();
    }
    
    /**
     * Members page
     */
    public function members_page() {
        $members_admin = new Rolino_Members_Admin();
        $members_admin->display_page();
    }
    
    /**
     * Revenue page
     */
    public function revenue_page() {
        $revenue_admin = new Rolino_Revenue_Admin();
        $revenue_admin->display_page();
    }
    
    /**
     * SMS Scenarios page
     */
    public function sms_scenarios_page() {
        $sms_admin = new Rolino_SMS_Admin();
        $sms_admin->display_page();
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $this->handle_settings_save();
        include ROLINO_PLUGIN_PATH . 'templates/admin/settings.php';
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $action = $_GET['action'] ?? '';
        $page = $_GET['page'] ?? '';
        
        // Handle plan group actions
        if ($page === 'rolino-plan-groups') {
            $this->handle_plan_group_actions($action);
        }
        
        // Handle transaction actions
        if ($page === 'rolino-transactions') {
            $this->handle_transaction_actions($action);
        }
    }
    
    /**
     * Handle plan group actions
     * 
     * @param string $action
     */
    private function handle_plan_group_actions($action) {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rolino_admin_action')) {
            return;
        }
        
        $plans = new Rolino_Plans();
        
        switch ($action) {
            case 'add_group':
                if (isset($_POST['group_name'])) {
                    $group_name = sanitize_text_field($_POST['group_name']);
                    if (!empty($group_name)) {
                        $group_id = $plans->create_plan_group($group_name);
                        if ($group_id) {
                            $this->add_admin_notice(__('گروه با موفقیت ایجاد شد', 'rolino'), 'success');
                        } else {
                            $this->add_admin_notice(__('خطا در ایجاد گروه', 'rolino'), 'error');
                        }
                    }
                }
                break;
                
            case 'delete_group':
                $group_id = intval($_GET['group_id'] ?? 0);
                if ($group_id) {
                    if ($plans->delete_plan_group($group_id)) {
                        $this->add_admin_notice(__('گروه با موفقیت حذف شد', 'rolino'), 'success');
                    } else {
                        $this->add_admin_notice(__('خطا در حذف گروه', 'rolino'), 'error');
                    }
                }
                break;
                
            case 'update_group_plans':
                $group_id = intval($_POST['group_id'] ?? 0);
                $plan_ids = $_POST['plan_ids'] ?? array();
                
                if ($group_id) {
                    // Remove all plans from group first
                    $current_plans = $plans->get_group_plan_ids($group_id);
                    foreach ($current_plans as $plan_id) {
                        $plans->remove_plan_from_group($plan_id, $group_id);
                    }
                    
                    // Add selected plans to group
                    foreach ($plan_ids as $plan_id) {
                        $plans->add_plan_to_group(intval($plan_id), $group_id);
                    }
                    
                    $this->add_admin_notice(__('گروه‌بندی طرح‌ها به‌روزرسانی شد', 'rolino'), 'success');
                }
                break;
        }
    }
    
    /**
     * Handle transaction actions
     * 
     * @param string $action
     */
    private function handle_transaction_actions($action) {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'rolino_admin_action')) {
            return;
        }
        
        $transactions = new Rolino_Transactions();
        
        switch ($action) {
            case 'update_status':
                $transaction_id = intval($_GET['transaction_id'] ?? 0);
                $status = sanitize_text_field($_GET['status'] ?? '');
                
                if ($transaction_id && in_array($status, array('pending', 'completed', 'failed'))) {
                    if ($transactions->update_transaction_status($transaction_id, $status)) {
                        $this->add_admin_notice(__('وضعیت تراکنش به‌روزرسانی شد', 'rolino'), 'success');
                    } else {
                        $this->add_admin_notice(__('خطا در به‌روزرسانی وضعیت', 'rolino'), 'error');
                    }
                }
                break;
        }
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'rolino_settings')) {
            // Single buy settings
            update_option('rolino_single_buy_duration', intval($_POST['single_buy_duration'] ?? 30));
            update_option('rolino_single_buy_credits', intval($_POST['single_buy_credits'] ?? 5));
            update_option('rolino_single_buy_price', floatval($_POST['single_buy_price'] ?? 10000));
            update_option('rolino_single_buy_active', intval($_POST['single_buy_active'] ?? 0));
            
            // SMS settings
            update_option('rolino_sms_enabled', intval($_POST['sms_enabled'] ?? 0));
            update_option('rolino_sms_api_key', sanitize_text_field($_POST['sms_api_key'] ?? ''));
            update_option('rolino_sms_sender', sanitize_text_field($_POST['sms_sender'] ?? ''));
            
            // Gateway settings
            $available_gateways = array('zarinpal', 'sample');
            foreach ($available_gateways as $gateway_id) {
                if (isset($_POST["gateway_{$gateway_id}"])) {
                    $gateway_settings = $_POST["gateway_{$gateway_id}"];
                    
                    // Sanitize settings
                    $sanitized_settings = array();
                    foreach ($gateway_settings as $key => $value) {
                        if (is_array($value)) {
                            $sanitized_settings[$key] = array_map('sanitize_text_field', $value);
                        } else {
                            $sanitized_settings[$key] = sanitize_text_field($value);
                        }
                    }
                    
                    update_option("rolino_gateway_{$gateway_id}_settings", $sanitized_settings);
                }
            }
            
            $this->add_admin_notice(__('تنظیمات ذخیره شد', 'rolino'), 'success');
        }
    }
    
    /**
     * Add admin notice
     * 
     * @param string $message
     * @param string $type
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            $class = 'notice notice-' . $type;
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
    
    /**
     * Get current user's permissions
     * 
     * @return array
     */
    public function get_user_permissions() {
        return array(
            'manage_plans' => current_user_can('manage_options'),
            'manage_transactions' => current_user_can('manage_options'),
            'manage_coupons' => current_user_can('manage_options'),
            'manage_sms' => current_user_can('manage_options'),
            'view_statistics' => current_user_can('manage_options')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'rolino') !== false) {
            wp_enqueue_style('rolino-admin', ROLINO_PLUGIN_URL . 'assets/css/admin.css', array(), ROLINO_VERSION);
            wp_enqueue_script('rolino-admin', ROLINO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ROLINO_VERSION, true);
            
            wp_localize_script('rolino-admin', 'rolinoAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rolino_admin_nonce'),
                'strings' => array(
                    'confirmDelete' => __('آیا مطمئن هستید؟', 'rolino'),
                    'saved' => __('ذخیره شد', 'rolino'),
                    'error' => __('خطایی رخ داد', 'rolino')
                )
            ));
        }
    }
    
    /**
     * Add help tabs
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'rolino') !== false) {
            $screen->add_help_tab(array(
                'id' => 'rolino-help',
                'title' => __('راهنما', 'rolino'),
                'content' => $this->get_help_content()
            ));
            
            $screen->set_help_sidebar($this->get_help_sidebar());
        }
    }
    
    /**
     * Get help content
     * 
     * @return string
     */
    private function get_help_content() {
        return '<p>' . __('برای دریافت راهنمای کامل پلاگین رولینو، به سایت سازنده مراجعه کنید.', 'rolino') . '</p>';
    }
    
    /**
     * Get help sidebar
     * 
     * @return string
     */
    private function get_help_sidebar() {
        return '<p><strong>' . __('پشتیبانی:', 'rolino') . '</strong></p>' .
               '<p><a href="https://cine.ir" target="_blank">' . __('وب‌سایت سازنده', 'rolino') . '</a></p>';
    }
    
    /**
     * AJAX add plan group
     */
    public function ajax_add_plan_group() {
        check_ajax_referer('rolino_plan_groups_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $group_name = sanitize_text_field($_POST['group_name'] ?? '');
        
        if (empty($group_name)) {
            wp_send_json_error(array('message' => __('نام گروه الزامی است', 'rolino')));
        }
        
        $plans = new Rolino_Plans();
        $result = $plans->create_plan_group($group_name);
        
        if ($result) {
            wp_send_json_success(array('message' => __('گروه جدید با موفقیت ایجاد شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در ایجاد گروه', 'rolino')));
        }
    }
    
    /**
     * AJAX update plan group
     */
    public function ajax_update_plan_group() {
        check_ajax_referer('rolino_plan_groups_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $group_id = intval($_POST['group_id'] ?? 0);
        $plan_ids = array_map('intval', $_POST['plan_ids'] ?? array());
        
        if (!$group_id) {
            wp_send_json_error(array('message' => __('شناسه گروه نامعتبر است', 'rolino')));
        }
        
        $plans = new Rolino_Plans();
        
        // Remove all plans from group
        $plans->remove_plan_from_group(0, $group_id);
        
        // Add selected plans to group
        foreach ($plan_ids as $plan_id) {
            $plans->add_plan_to_group($plan_id, $group_id);
        }
        
        wp_send_json_success(array('message' => __('گروه با موفقیت به‌روزرسانی شد', 'rolino')));
    }
    
    /**
     * AJAX delete plan group
     */
    public function ajax_delete_plan_group() {
        check_ajax_referer('rolino_plan_groups_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $group_id = intval($_POST['group_id'] ?? 0);
        
        if (!$group_id) {
            wp_send_json_error(array('message' => __('شناسه گروه نامعتبر است', 'rolino')));
        }
        
        $plans = new Rolino_Plans();
        $result = $plans->delete_plan_group($group_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('گروه با موفقیت حذف شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف گروه', 'rolino')));
        }
    }
    
    /**
     * AJAX load more content
     */
    public function ajax_load_more_content() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $type = sanitize_text_field($_POST['type'] ?? '');
        
        $content = '';
        $has_more = false;
        
        switch ($type) {
            case 'plans':
                $plans = new Rolino_Plans();
                $all_plans = $plans->get_plans(array('limit' => 10, 'offset' => ($page - 1) * 10));
                $total_plans = $plans->get_plans_count();
                
                foreach ($all_plans as $plan) {
                    $content .= '<tr>';
                    $content .= '<td>' . esc_html($plan->plan_name) . '</td>';
                    $content .= '<td>' . number_format($plan->credits) . '</td>';
                    $content .= '<td>' . number_format($plan->duration) . '</td>';
                    $content .= '<td>' . number_format($plan->price) . '</td>';
                    $content .= '<td>' . ($plan->status ? __('فعال', 'rolino') : __('غیرفعال', 'rolino')) . '</td>';
                    $content .= '</tr>';
                }
                
                $has_more = ($page * 10) < $total_plans;
                break;
                
            case 'coupons':
                $coupons = new Rolino_Coupons();
                $all_coupons = $coupons->get_coupons(array('limit' => 10, 'offset' => ($page - 1) * 10));
                $total_coupons = $coupons->get_coupons_count();
                
                foreach ($all_coupons as $coupon) {
                    $content .= '<tr>';
                    $content .= '<td>' . esc_html($coupon->code) . '</td>';
                    $content .= '<td>' . ($coupon->type == 1 ? __('درصدی', 'rolino') : __('مبلغ ثابت', 'rolino')) . '</td>';
                    $content .= '<td>' . number_format($coupon->usage_limit) . '</td>';
                    $content .= '<td>' . ($coupon->status ? __('فعال', 'rolino') : __('غیرفعال', 'rolino')) . '</td>';
                    $content .= '</tr>';
                }
                
                $has_more = ($page * 10) < $total_coupons;
                break;
                
            default:
                wp_send_json_error(array('message' => __('نوع محتوا نامعتبر است', 'rolino')));
                break;
        }
        
        wp_send_json_success(array(
            'content' => $content,
            'has_more' => $has_more
        ));
    }
}