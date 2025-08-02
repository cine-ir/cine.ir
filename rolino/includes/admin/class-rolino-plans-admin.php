<?php
/**
 * Rolino Plans Admin Class
 * 
 * Handles plans management in admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Plans_Admin {
    
    private $plans;
    
    public function __construct() {
        add_action('wp_ajax_rolino_toggle_plan_status', array($this, 'ajax_toggle_plan_status'));
        add_action('wp_ajax_rolino_delete_plan', array($this, 'ajax_delete_plan'));
        add_action('wp_ajax_rolino_save_plan', array($this, 'ajax_save_plan'));
    }
    
    /**
     * Get plans instance
     */
    private function get_plans() {
        if (!isset($this->plans)) {
            $this->plans = new Rolino_Plans();
        }
        return $this->plans;
    }
    
    /**
     * Display plans page
     */
    public function display_page() {
        $action = $_GET['action'] ?? 'list';
        $plan_id = intval($_GET['plan_id'] ?? 0);
        
        switch ($action) {
            case 'add':
                $this->display_add_plan_form();
                break;
                
            case 'edit':
                $this->display_edit_plan_form($plan_id);
                break;
                
            default:
                $this->display_plans_list();
                break;
        }
    }
    
    /**
     * Display plans list
     */
    private function display_plans_list() {
        $this->handle_bulk_actions();
        
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => $_GET['orderby'] ?? 'id',
            'order' => $_GET['order'] ?? 'DESC'
        );
        
        $plans = $this->get_plans()->get_plans($args);
        $total_plans = $this->get_plans()->get_plans_count();
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/plans-list.php';
    }
    
    /**
     * Display add plan form
     */
    private function display_add_plan_form() {
        $plan = (object) array(
            'id' => 0,
            'plan_name' => '',
            'credits' => 0,
            'duration' => 30,
            'price' => 0,
            'active_sessions' => 1,
            'status' => 1
        );
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/plan-form.php';
    }
    
    /**
     * Display edit plan form
     * 
     * @param int $plan_id
     */
    private function display_edit_plan_form($plan_id) {
        $plan = $this->get_plans()->get_plan($plan_id);
        
        if (!$plan) {
            wp_die(__('طرح یافت نشد', 'rolino'));
        }
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/plan-form.php';
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['plan_ids'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'rolino_plans_bulk')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $plan_ids = array_map('intval', $_POST['plan_ids']);
        
        switch ($action) {
            case 'activate':
                foreach ($plan_ids as $plan_id) {
                    $this->get_plans()->update_plan($plan_id, array('status' => 1));
                }
                $this->add_admin_notice(__('طرح‌های انتخابی فعال شدند', 'rolino'), 'success');
                break;
                
            case 'deactivate':
                foreach ($plan_ids as $plan_id) {
                    $this->get_plans()->update_plan($plan_id, array('status' => 0));
                }
                $this->add_admin_notice(__('طرح‌های انتخابی غیرفعال شدند', 'rolino'), 'success');
                break;
                
            case 'delete':
                foreach ($plan_ids as $plan_id) {
                    $this->get_plans()->delete_plan($plan_id);
                }
                $this->add_admin_notice(__('طرح‌های انتخابی حذف شدند', 'rolino'), 'success');
                break;
        }
    }
    
    /**
     * AJAX toggle plan status
     */
    public function ajax_toggle_plan_status() {
        check_ajax_referer('rolino_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        
        if (!$plan_id) {
            wp_send_json_error(array('message' => __('شناسه طرح نامعتبر است', 'rolino')));
        }
        
        $result = $this->get_plans()->update_plan($plan_id, array('status' => $status));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $status ? __('طرح فعال شد', 'rolino') : __('طرح غیرفعال شد', 'rolino'),
                'status' => $status
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در به‌روزرسانی وضعیت', 'rolino')));
        }
    }
    
    /**
     * AJAX delete plan
     */
    public function ajax_delete_plan() {
        check_ajax_referer('rolino_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        
        if (!$plan_id) {
            wp_send_json_error(array('message' => __('شناسه طرح نامعتبر است', 'rolino')));
        }
        
        // Check if plan has active subscriptions
        global $wpdb;
        $active_subscriptions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_credits 
             WHERE plan_id = %d AND end_time > NOW()",
            $plan_id
        ));
        
        if ($active_subscriptions > 0) {
            wp_send_json_error(array(
                'message' => sprintf(__('این طرح دارای %d اشتراک فعال است و قابل حذف نیست', 'rolino'), $active_subscriptions)
            ));
        }
        
        $result = $this->get_plans()->delete_plan($plan_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('طرح با موفقیت حذف شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف طرح', 'rolino')));
        }
    }
    
    /**
     * AJAX save plan
     */
    public function ajax_save_plan() {
        check_ajax_referer('rolino_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $plan_data = array(
            'plan_name' => sanitize_text_field($_POST['plan_name'] ?? ''),
            'credits' => intval($_POST['credits'] ?? 0),
            'duration' => intval($_POST['duration'] ?? 30),
            'price' => floatval($_POST['price'] ?? 0),
            'active_sessions' => intval($_POST['active_sessions'] ?? 1),
            'status' => intval($_POST['status'] ?? 1)
        );
        
        // Validation
        $validation_result = $this->get_plans()->validate_plan_data($plan_data);
        if (!$validation_result['valid']) {
            wp_send_json_error(array('message' => $validation_result['message']));
        }
        
        if ($plan_id > 0) {
            // Update existing plan
            $result = $this->get_plans()->update_plan($plan_id, $plan_data);
            $message = __('طرح با موفقیت به‌روزرسانی شد', 'rolino');
        } else {
            // Create new plan
            $result = $this->get_plans()->create_plan($plan_data);
            $plan_id = $result;
            $message = __('طرح جدید با موفقیت ایجاد شد', 'rolino');
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $message,
                'plan_id' => $plan_id,
                'redirect' => admin_url('admin.php?page=rolino-plans')
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره طرح', 'rolino')));
        }
    }
    
    /**
     * Get plans table columns
     * 
     * @return array
     */
    public function get_table_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'plan_name' => __('نام طرح', 'rolino'),
            'credits' => __('اعتبار', 'rolino'),
            'duration' => __('مدت (روز)', 'rolino'),
            'price' => __('قیمت (تومان)', 'rolino'),
            'active_sessions' => __('جلسات همزمان', 'rolino'),
            'status' => __('وضعیت', 'rolino'),
            'created_at' => __('تاریخ ایجاد', 'rolino'),
            'actions' => __('عملیات', 'rolino')
        );
    }
    
    /**
     * Get sortable columns
     * 
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'plan_name' => array('plan_name', false),
            'credits' => array('credits', false),
            'duration' => array('duration', false),
            'price' => array('price', false),
            'created_at' => array('created_at', true)
        );
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
     * Format price for display
     * 
     * @param float $price
     * @return string
     */
    public function format_price($price) {
        return number_format($price, 0, '', ',') . ' ' . __('تومان', 'rolino');
    }
    
    /**
     * Get status badge HTML
     * 
     * @param int $status
     * @return string
     */
    public function get_status_badge($status) {
        if ($status == 1) {
            return '<span class="status-badge status-active">' . __('فعال', 'rolino') . '</span>';
        } else {
            return '<span class="status-badge status-inactive">' . __('غیرفعال', 'rolino') . '</span>';
        }
    }
    
    /**
     * Get plan statistics for dashboard
     * 
     * @return array
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total plans
        $stats['total_plans'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_plans");
        
        // Active plans
        $stats['active_plans'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_plans WHERE status = 1");
        
        // Plans with active subscriptions
        $stats['plans_with_subscriptions'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT plan_id) FROM {$wpdb->prefix}rolino_credits 
             WHERE plan_id > 0 AND end_time > NOW()"
        );
        
        // Most popular plan
        $popular_plan = $wpdb->get_row(
            "SELECT p.plan_name, COUNT(c.id) as subscription_count 
             FROM {$wpdb->prefix}rolino_plans p 
             LEFT JOIN {$wpdb->prefix}rolino_credits c ON p.id = c.plan_id 
             WHERE c.end_time > NOW() 
             GROUP BY p.id 
             ORDER BY subscription_count DESC 
             LIMIT 1"
        );
        
        $stats['popular_plan'] = $popular_plan ? $popular_plan->plan_name : __('ندارد', 'rolino');
        
        return $stats;
    }
}