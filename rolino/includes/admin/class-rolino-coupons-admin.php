<?php
/**
 * Rolino Coupons Admin Class
 * 
 * Handles coupon management in admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Coupons_Admin {
    
    private $coupons;
    
    public function __construct() {
        add_action('wp_ajax_rolino_save_coupon', array($this, 'ajax_save_coupon'));
        add_action('wp_ajax_rolino_delete_coupon', array($this, 'ajax_delete_coupon'));
        add_action('wp_ajax_rolino_generate_coupon_code', array($this, 'ajax_generate_coupon_code'));
    }
    
    /**
     * Get coupons instance
     */
    private function get_coupons() {
        if (!isset($this->coupons)) {
            $this->coupons = new Rolino_Coupons();
        }
        return $this->coupons;
    }
    
    /**
     * Display coupons page
     */
    public function display_page() {
        $action = $_GET['action'] ?? 'list';
        $coupon_id = intval($_GET['coupon_id'] ?? 0);
        
        switch ($action) {
            case 'add':
                $this->display_add_coupon_form();
                break;
                
            case 'edit':
                $this->display_edit_coupon_form($coupon_id);
                break;
                
            default:
                $this->display_coupons_list();
                break;
        }
    }
    
    /**
     * Display coupons list
     */
    private function display_coupons_list() {
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
        
        // Add filters
        if (!empty($_GET['type'])) {
            $args['type'] = intval($_GET['type']);
        }
        
        if (!empty($_GET['status'])) {
            $args['status'] = intval($_GET['status']);
        }
        
        $coupons = $this->get_coupons()->get_coupons($args);
        $total_coupons = $this->get_coupons_count($args);
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/coupons-list.php';
    }
    
    /**
     * Display add coupon form
     */
    private function display_add_coupon_form() {
        $coupon = (object) array(
            'id' => 0,
            'code' => '',
            'type' => 1,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 month')),
            'usage_limit' => 0,
            'used_count' => 0,
            'status' => 1
        );
        
        $plans = new Rolino_Plans();
        $all_plans = $plans->get_active_plans();
        $coupon_discounts = array();
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/coupon-form.php';
    }
    
    /**
     * Display edit coupon form
     * 
     * @param int $coupon_id
     */
    private function display_edit_coupon_form($coupon_id) {
        $coupon = $this->get_coupons()->get_coupon($coupon_id);
        
        if (!$coupon) {
            wp_die(__('کد تخفیف یافت نشد', 'rolino'));
        }
        
        $plans = new Rolino_Plans();
        $all_plans = $plans->get_active_plans();
        $coupon_discounts = $this->get_coupons()->get_coupon_plan_discounts($coupon_id);
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/coupon-form.php';
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['coupon_ids'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'rolino_coupons_bulk')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $coupon_ids = array_map('intval', $_POST['coupon_ids']);
        
        switch ($action) {
            case 'activate':
                foreach ($coupon_ids as $coupon_id) {
                    $this->get_coupons()->update_coupon($coupon_id, array('status' => 1));
                }
                $this->add_admin_notice(__('کدهای تخفیف انتخابی فعال شدند', 'rolino'), 'success');
                break;
                
            case 'deactivate':
                foreach ($coupon_ids as $coupon_id) {
                    $this->get_coupons()->update_coupon($coupon_id, array('status' => 0));
                }
                $this->add_admin_notice(__('کدهای تخفیف انتخابی غیرفعال شدند', 'rolino'), 'success');
                break;
                
            case 'delete':
                foreach ($coupon_ids as $coupon_id) {
                    $this->get_coupons()->delete_coupon($coupon_id);
                }
                $this->add_admin_notice(__('کدهای تخفیف انتخابی حذف شدند', 'rolino'), 'success');
                break;
        }
    }
    
    /**
     * AJAX save coupon
     */
    public function ajax_save_coupon() {
        check_ajax_referer('rolino_coupon_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        $coupon_data = array(
            'code' => sanitize_text_field($_POST['code'] ?? ''),
            'type' => intval($_POST['type'] ?? 1),
            'duration_days' => intval($_POST['duration_days'] ?? 30),
            'status' => intval($_POST['status'] ?? 1)
        );
        
        $plan_discounts = $_POST['plan_discounts'] ?? array();
        
        // Validation
        $validation_result = $this->get_coupons()->validate_coupon_data($coupon_data);
        if (!$validation_result['valid']) {
            wp_send_json_error(array('message' => $validation_result['message']));
        }
        
        // Check if code already exists (for new coupons or different coupon)
        if ($coupon_id == 0 && $this->get_coupons()->coupon_code_exists($coupon_data['code'])) {
            wp_send_json_error(array('message' => __('این کد تخفیف قبلاً استفاده شده است', 'rolino')));
        }
        
        if ($coupon_id > 0) {
            // Update existing coupon
            $result = $this->get_coupons()->update_coupon($coupon_id, $coupon_data);
            $message = __('کد تخفیف با موفقیت به‌روزرسانی شد', 'rolino');
        } else {
            // Create new coupon
            $result = $this->get_coupons()->create_coupon($coupon_data);
            $coupon_id = $result;
            $message = __('کد تخفیف جدید با موفقیت ایجاد شد', 'rolino');
        }
        
        if ($result) {
            // Update plan discounts
            $this->get_coupons()->remove_plan_discounts($coupon_id);
            $this->get_coupons()->add_plan_discounts($coupon_id, $plan_discounts);
            
            wp_send_json_success(array(
                'message' => $message,
                'coupon_id' => $coupon_id,
                'redirect' => admin_url('admin.php?page=rolino-coupons')
            ));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره کد تخفیف', 'rolino')));
        }
    }
    
    /**
     * AJAX delete coupon
     */
    public function ajax_delete_coupon() {
        check_ajax_referer('rolino_coupon_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        
        if (!$coupon_id) {
            wp_send_json_error(array('message' => __('شناسه کد تخفیف نامعتبر است', 'rolino')));
        }
        
        // Check if coupon has been used
        global $wpdb;
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_transactions t
             INNER JOIN {$wpdb->prefix}rolino_transaction_meta tm ON t.id = tm.transaction_id
             WHERE tm.meta_key = 'coupon_code' AND tm.meta_value = (
                 SELECT code FROM {$wpdb->prefix}rolino_coupons WHERE id = %d
             ) AND t.status = 'completed'",
            $coupon_id
        ));
        
        if ($usage_count > 0) {
            wp_send_json_error(array(
                'message' => sprintf(__('این کد تخفیف %d بار استفاده شده و قابل حذف نیست', 'rolino'), $usage_count)
            ));
        }
        
        $result = $this->get_coupons()->delete_coupon($coupon_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('کد تخفیف با موفقیت حذف شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف کد تخفیف', 'rolino')));
        }
    }
    
    /**
     * AJAX generate coupon code
     */
    public function ajax_generate_coupon_code() {
        check_ajax_referer('rolino_coupon_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $code = $this->get_coupons()->generate_coupon_code();
        
        wp_send_json_success(array('code' => $code));
    }
    
    /**
     * Get coupons count
     * 
     * @param array $args
     * @return int
     */
    private function get_coupons_count($args = array()) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_coupons WHERE 1=1";
        $where_conditions = array();
        
        if (isset($args['type'])) {
            $where_conditions[] = $wpdb->prepare("type = %d", $args['type']);
        }
        
        if (isset($args['status'])) {
            $where_conditions[] = $wpdb->prepare("status = %d", $args['status']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " AND " . implode(' AND ', $where_conditions);
        }
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Get coupon type names
     * 
     * @return array
     */
    public function get_coupon_types() {
        return array(
            1 => __('فعال سایت‌گستر', 'rolino'),
            2 => __('قابل استفاده برای همه', 'rolino'),
            3 => __('انحصاری (ارسال SMS)', 'rolino')
        );
    }
    
    /**
     * Get coupon type badge
     * 
     * @param int $type
     * @return string
     */
    public function get_type_badge($type) {
        $types = $this->get_coupon_types();
        $type_name = $types[$type] ?? __('نامشخص', 'rolino');
        
        $class = '';
        switch ($type) {
            case 1:
                $class = 'type-global';
                break;
            case 2:
                $class = 'type-public';
                break;
            case 3:
                $class = 'type-exclusive';
                break;
        }
        
        return '<span class="type-badge ' . $class . '">' . $type_name . '</span>';
    }
    
    /**
     * Get status badge
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
     * Check if coupon is expired
     * 
     * @param object $coupon
     * @return bool
     */
    public function is_expired($coupon) {
        return strtotime($coupon->end_date) < time();
    }
    
    /**
     * Get expiry status badge
     * 
     * @param object $coupon
     * @return string
     */
    public function get_expiry_badge($coupon) {
        if ($this->is_expired($coupon)) {
            return '<span class="status-badge status-expired">' . __('منقضی', 'rolino') . '</span>';
        } else {
            $days_left = ceil((strtotime($coupon->end_date) - time()) / DAY_IN_SECONDS);
            if ($days_left <= 7) {
                return '<span class="status-badge status-warning">' . sprintf(__('%d روز مانده', 'rolino'), $days_left) . '</span>';
            } else {
                return '<span class="status-badge status-valid">' . __('معتبر', 'rolino') . '</span>';
            }
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
     * Get table columns
     * 
     * @return array
     */
    public function get_table_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'code' => __('کد تخفیف', 'rolino'),
            'type' => __('نوع', 'rolino'),
            'start_date' => __('تاریخ شروع', 'rolino'),
            'end_date' => __('تاریخ پایان', 'rolino'),
            'usage_limit' => __('حد استفاده', 'rolino'),
            'used_count' => __('تعداد استفاده', 'rolino'),
            'status' => __('وضعیت', 'rolino'),
            'actions' => __('عملیات', 'rolino')
        );
    }
    
    /**
     * Get coupon statistics for dashboard
     * 
     * @return array
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total coupons
        $stats['total_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_coupons");
        
        // Active coupons
        $stats['active_coupons'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_coupons 
             WHERE status = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()"
        );
        
        // Used coupons today
        $stats['used_today'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_transaction_meta tm
             INNER JOIN {$wpdb->prefix}rolino_transactions t ON tm.transaction_id = t.id
             WHERE tm.meta_key = 'coupon_code' AND DATE(t.transaction_date) = CURDATE() AND t.status = 'completed'"
        );
        
        // Expiring soon (within 7 days)
        $stats['expiring_soon'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_coupons 
             WHERE status = 1 AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
}