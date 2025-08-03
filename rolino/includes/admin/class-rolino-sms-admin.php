<?php
/**
 * Rolino SMS Admin Class
 * 
 * Handles SMS scenarios management in admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_SMS_Admin {
    
    private $sms;
    
    public function __construct() {
        add_action('wp_ajax_rolino_save_sms_scenario', array($this, 'ajax_save_scenario'));
        add_action('wp_ajax_rolino_test_sms', array($this, 'ajax_test_sms'));
        add_action('wp_ajax_rolino_auto_save_template', array($this, 'ajax_auto_save_template'));
        add_action('wp_ajax_rolino_save_sms_draft', array($this, 'ajax_save_sms_draft'));
    }
    
    /**
     * Get SMS instance
     */
    private function get_sms() {
        if (!isset($this->sms)) {
            $this->sms = new Rolino_SMS();
        }
        return $this->sms;
    }
    
    /**
     * Display SMS scenarios page
     */
    public function display_page() {
        $tab = $_GET['tab'] ?? 'scenarios';
        
        switch ($tab) {
            case 'scenarios':
                $this->display_scenarios_tab();
                break;
                
            case 'logs':
                $this->display_logs_tab();
                break;
                
            case 'queue':
                $this->display_queue_tab();
                break;
                
            default:
                $this->display_scenarios_tab();
                break;
        }
    }
    
    /**
     * Display scenarios tab
     */
    private function display_scenarios_tab() {
        $this->handle_scenario_save();
        
        $scenarios = $this->get_sms()->get_scenarios();
        $scenario_types = $this->get_sms()->get_scenario_type_names();
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/sms-scenarios.php';
    }
    
    /**
     * Display logs tab
     */
    private function display_logs_tab() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset
        );
        
        // Add filters
        if (!empty($_GET['user_id'])) {
            $args['user_id'] = intval($_GET['user_id']);
        }
        
        if (!empty($_GET['scenario_id'])) {
            $args['scenario_id'] = intval($_GET['scenario_id']);
        }
        
        if (isset($_GET['sent_status']) && $_GET['sent_status'] !== '') {
            $args['sent_status'] = intval($_GET['sent_status']);
        }
        
        $logs = $this->get_sms()->get_sms_logs($args);
        $scenarios = $this->get_sms()->get_scenarios();
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/sms-logs.php';
    }
    
    /**
     * Display queue tab
     */
    private function display_queue_tab() {
        global $wpdb;
        
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $queue_table = $wpdb->prefix . 'rolino_sms_queue';
        
        $sql = "SELECT q.*, s.scenario_type, u.display_name as user_name 
                FROM {$queue_table} q 
                LEFT JOIN {$wpdb->prefix}rolino_sms_scenarios s ON q.scenario_id = s.id 
                LEFT JOIN {$wpdb->users} u ON q.user_id = u.ID 
                ORDER BY q.scheduled_time DESC 
                LIMIT %d OFFSET %d";
        
        $queue_items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
        
        $total_queue = $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table}");
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/sms-queue.php';
    }
    
    /**
     * Handle scenario save
     */
    private function handle_scenario_save() {
        // Handle adding new scenario
        if (isset($_POST['add_scenario']) && wp_verify_nonce($_POST['_wpnonce'], 'rolino_add_scenario')) {
            $scenario_data = array(
                'scenario_type' => intval($_POST['scenario_type'] ?? 0),
                'days_offset' => $_POST['days_offset'] === '' ? null : intval($_POST['days_offset']),
                'message_template' => sanitize_textarea_field($_POST['message_template'] ?? ''),
                'status' => intval($_POST['status'] ?? 0)
            );
            
            if ($scenario_data['scenario_type'] && !empty($scenario_data['message_template'])) {
                $result = $this->get_sms()->add_scenario($scenario_data);
                if ($result) {
                    $this->add_admin_notice(__('سناریو جدید اضافه شد', 'rolino'), 'success');
                } else {
                    $this->add_admin_notice(__('خطا در اضافه کردن سناریو', 'rolino'), 'error');
                }
            } else {
                $this->add_admin_notice(__('لطفاً نوع سناریو و متن پیام را وارد کنید', 'rolino'), 'error');
            }
            return;
        }
        
        // Handle updating existing scenarios
        if (!isset($_POST['save_scenarios']) || !wp_verify_nonce($_POST['_wpnonce'], 'rolino_sms_scenarios')) {
            return;
        }
        
        $scenarios_data = $_POST['scenarios'] ?? array();
        
        foreach ($scenarios_data as $scenario_id => $data) {
            $scenario_id = intval($scenario_id);
            
            $update_data = array(
                'days_offset' => $data['days_offset'] === '' ? null : intval($data['days_offset']),
                'message_template' => sanitize_textarea_field($data['message_template']),
                'status' => intval($data['status'] ?? 0)
            );
            
            $this->get_sms()->update_scenario($scenario_id, $update_data);
        }
        
        $this->add_admin_notice(__('سناریوهای SMS ذخیره شدند', 'rolino'), 'success');
    }
    
    /**
     * AJAX save scenario
     */
    public function ajax_save_scenario() {
        check_ajax_referer('rolino_add_scenario', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $scenario_id = intval($_POST['scenario_id'] ?? 0);
        $scenario_type = intval($_POST['scenario_type'] ?? 0);
        $data = array(
            'scenario_type' => $scenario_type,
            'days_offset' => $_POST['days_offset'] === '' ? null : intval($_POST['days_offset']),
            'message_template' => sanitize_textarea_field($_POST['message_template'] ?? ''),
            'status' => intval($_POST['status'] ?? 0)
        );
        
        if (!$scenario_type || empty($data['message_template'])) {
            wp_send_json_error(array('message' => __('لطفاً نوع سناریو و متن پیام را وارد کنید', 'rolino')));
        }
        
        if ($scenario_id) {
            // Update existing scenario
            $result = $this->get_sms()->update_scenario($scenario_id, $data);
            $message = __('سناریو به‌روزرسانی شد', 'rolino');
        } else {
            // Add new scenario
            $result = $this->get_sms()->add_scenario($data);
            $message = __('سناریو جدید اضافه شد', 'rolino');
        }
        
        if ($result) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره سناریو', 'rolino')));
        }
    }
    
    /**
     * AJAX test SMS
     */
    public function ajax_test_sms() {
        check_ajax_referer('rolino_sms_scenarios', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($phone) || empty($message)) {
            wp_send_json_error(array('message' => __('شماره تلفن و متن پیام الزامی است', 'rolino')));
        }
        
        // Test SMS sending
        $sent = $this->send_test_sms($phone, $message);
        
        if ($sent) {
            wp_send_json_success(array('message' => __('پیام آزمایشی ارسال شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در ارسال پیام آزمایشی', 'rolino')));
        }
    }
    
    /**
     * AJAX auto save template
     */
    public function ajax_auto_save_template() {
        check_ajax_referer('rolino_sms_scenarios', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $scenario_id = intval($_POST['scenario_id'] ?? 0);
        $template = sanitize_textarea_field($_POST['template'] ?? '');
        
        if (!$scenario_id) {
            wp_send_json_error(array('message' => __('شناسه سناریو نامعتبر است', 'rolino')));
        }
        
        $result = $this->get_sms()->update_scenario($scenario_id, array('message_template' => $template));
        
        if ($result) {
            wp_send_json_success(array('message' => __('قالب پیام ذخیره شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره قالب', 'rolino')));
        }
    }
    
    /**
     * Send test SMS
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    private function send_test_sms($phone, $message) {
        $api_key = get_option('rolino_sms_api_key', '');
        $sender = get_option('rolino_sms_sender', '');
        
        if (empty($api_key) || empty($sender)) {
            return false;
        }
        
        // Use the SMS API function (you'll need to implement this)
        $result = apply_filters('rolino_send_sms_api', false, $phone, $message, $api_key, $sender);
        
        return $result !== false;
    }
    
    /**
     * Get available variables for templates
     * 
     * @return array
     */
    public function get_available_variables() {
        return array(
            'user_variables' => array(
                'remaining_time' => __('زمان باقی‌مانده اشتراک اصلی (روز)', 'rolino'),
                'reserve_remaining_time' => __('زمان باقی‌مانده اشتراک رزرو (روز)', 'rolino'),
                'plan_name' => __('نام طرح اصلی', 'rolino'),
                'reserve_plan_name' => __('نام طرح رزرو', 'rolino'),
                'off_code' => __('کد تخفیف فعال کاربر', 'rolino'),
                'off_code_remaining_time' => __('زمان باقی‌مانده کد تخفیف (روز)', 'rolino'),
                'single_buy_number' => __('تعداد خرید تکی', 'rolino')
            ),
            'action_variables' => array(
                'add_off_code=X' => __('افزودن کد تخفیف انحصاری (X = شناسه کد تخفیف)', 'rolino'),
                'single_buy=X' => __('اعطای خرید تکی (X = تعداد اعتبار)', 'rolino'),
                'add_remaining_time=X' => __('افزودن زمان به اشتراک (X = تعداد روز)', 'rolino'),
                'add_off_code_remaining_time=X' => __('افزودن زمان به کد تخفیف (X = تعداد روز)', 'rolino'),
                'add_plan=X' => __('اعطای طرح رایگان (X = شناسه طرح)', 'rolino')
            )
        );
    }
    
    /**
     * Get scenario status badge
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
     * Get sent status badge
     * 
     * @param int $sent_status
     * @return string
     */
    public function get_sent_status_badge($sent_status) {
        if ($sent_status == 1) {
            return '<span class="status-badge status-success">' . __('ارسال شده', 'rolino') . '</span>';
        } else {
            return '<span class="status-badge status-failed">' . __('ناموفق', 'rolino') . '</span>';
        }
    }
    
    /**
     * Get queue status badge
     * 
     * @param int $status
     * @return string
     */
    public function get_queue_status_badge($status) {
        switch ($status) {
            case 0:
                return '<span class="status-badge status-pending">' . __('در صف', 'rolino') . '</span>';
            case 1:
                return '<span class="status-badge status-success">' . __('ارسال شده', 'rolino') . '</span>';
            case 2:
                return '<span class="status-badge status-failed">' . __('ناموفق', 'rolino') . '</span>';
            default:
                return '<span class="status-badge">' . __('نامشخص', 'rolino') . '</span>';
        }
    }
    
    /**
     * Format persian date
     * 
     * @param string $date
     * @return string
     */
    public function format_persian_date($date) {
        return date_i18n('Y/m/d H:i', strtotime($date));
    }
    
    /**
     * Get scenario description
     * 
     * @param int $scenario_type
     * @return string
     */
    public function get_scenario_description($scenario_type) {
        $descriptions = array(
            1 => __('ارسال پیام یادآوری قبل از پایان اشتراک', 'rolino'),
            2 => __('ارسال پیام اطلاع‌رسانی پس از پایان اشتراک', 'rolino'),
            3 => __('ارسال پیام یادآوری قبل از انقضای کد تخفیف', 'rolino'),
            4 => __('ارسال پیام بلافاصله پس از فعال شدن اشتراک اصلی', 'rolino'),
            5 => __('ارسال پیام بلافاصله پس از افزودن اشتراک رزرو', 'rolino')
        );
        
        return $descriptions[$scenario_type] ?? '';
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
     * Get SMS statistics for dashboard
     * 
     * @return array
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Active scenarios
        $stats['active_scenarios'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_sms_scenarios WHERE status = 1");
        
        // SMS sent today
        $stats['sent_today'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_sms_logs WHERE DATE(sent_at) = CURDATE() AND sent_status = 1");
        
        // SMS failed today
        $stats['failed_today'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_sms_logs WHERE DATE(sent_at) = CURDATE() AND sent_status = 0");
        
        // Queued messages
        $stats['queued_messages'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_sms_queue WHERE status = 0");
        
        return $stats;
    }
    
    /**
     * Get tab navigation
     * 
     * @param string $current_tab
     * @return array
     */
    public function get_tab_navigation($current_tab = 'scenarios') {
        return array(
            'scenarios' => array(
                'title' => __('سناریوها', 'rolino'),
                'url' => admin_url('admin.php?page=rolino-sms-scenarios&tab=scenarios'),
                'active' => $current_tab === 'scenarios'
            ),
            'logs' => array(
                'title' => __('گزارشات', 'rolino'),
                'url' => admin_url('admin.php?page=rolino-sms-scenarios&tab=logs'),
                'active' => $current_tab === 'logs'
            ),
            'queue' => array(
                'title' => __('صف ارسال', 'rolino'),
                'url' => admin_url('admin.php?page=rolino-sms-scenarios&tab=queue'),
                'active' => $current_tab === 'queue'
            )
        );
    }
    
    /**
     * AJAX save SMS draft
     */
    public function ajax_save_sms_draft() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $scenario_id = intval($_POST['scenario_id'] ?? 0);
        $template = sanitize_textarea_field($_POST['template'] ?? '');
        
        if (!$scenario_id) {
            wp_send_json_error(array('message' => __('شناسه سناریو نامعتبر است', 'rolino')));
        }
        
        $result = $this->get_sms()->update_scenario($scenario_id, array('message_template' => $template));
        
        if ($result) {
            wp_send_json_success(array('message' => __('پیش‌نویس ذخیره شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره پیش‌نویس', 'rolino')));
        }
    }
}