<?php
/**
 * Rolino Members Admin Class
 * 
 * Handles members/subscribers management in admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Members_Admin {
    
    private $credits;
    private $plans;
    private $transactions;
    
    public function __construct() {
        add_action('wp_ajax_rolino_save_member', array($this, 'ajax_save_member'));
        add_action('wp_ajax_rolino_delete_member', array($this, 'ajax_delete_member'));
        add_action('wp_ajax_rolino_extend_membership', array($this, 'ajax_extend_membership'));
        add_action('wp_ajax_rolino_get_member_details', array($this, 'ajax_get_member_details'));
    }
    
    /**
     * Get credits instance
     */
    private function get_credits() {
        if (!isset($this->credits)) {
            $this->credits = new Rolino_Credits();
        }
        return $this->credits;
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
     * Get transactions instance
     */
    private function get_transactions() {
        if (!isset($this->transactions)) {
            $this->transactions = new Rolino_Transactions();
        }
        return $this->transactions;
    }
    
    /**
     * Display members page
     */
    public function display_page() {
        $tab = $_GET['tab'] ?? 'active';
        
        switch ($tab) {
            case 'active':
                $this->display_active_members();
                break;
                
            case 'expired':
                $this->display_expired_members();
                break;
                
            case 'single_buy':
                $this->display_single_buy_users();
                break;
                
            default:
                $this->display_active_members();
                break;
        }
    }
    
    /**
     * Display active members
     */
    private function display_active_members() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $members = $this->get_active_members($per_page, $offset);
        $total_members = $this->get_active_members_count();
        
        $this->display_members_table($members, $total_members, $page, $per_page, 'active');
    }
    
    /**
     * Display expired members
     */
    private function display_expired_members() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $members = $this->get_expired_members($per_page, $offset);
        $total_members = $this->get_expired_members_count();
        
        $this->display_members_table($members, $total_members, $page, $per_page, 'expired');
    }
    
    /**
     * Display single buy users
     */
    private function display_single_buy_users() {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $users = $this->get_single_buy_users($per_page, $offset);
        $total_users = $this->get_single_buy_users_count();
        
        $this->display_single_buy_table($users, $total_users, $page, $per_page);
    }
    
    /**
     * Display members table
     */
    private function display_members_table($members, $total_members, $page, $per_page, $tab) {
        $total_pages = ceil($total_members / $per_page);
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/members-table.php';
    }
    
    /**
     * Display single buy table
     */
    private function display_single_buy_table($users, $total_users, $page, $per_page) {
        $total_pages = ceil($total_users / $per_page);
        
        include ROLINO_PLUGIN_PATH . 'templates/admin/single-buy-table.php';
    }
    
    /**
     * Get active members
     */
    private function get_active_members($limit = 20, $offset = 0) {
        global $wpdb;
        
        $sql = "SELECT DISTINCT c.user_id, u.display_name, u.user_email, 
                       MAX(c.end_time) as latest_end_time,
                       SUM(c.credit) as total_credits,
                       COUNT(c.id) as subscription_count
                FROM {$wpdb->prefix}rolino_credits c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.end_time > NOW() AND c.plan_id > 0
                GROUP BY c.user_id
                ORDER BY latest_end_time DESC
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    /**
     * Get active members count
     */
    private function get_active_members_count() {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->prefix}rolino_credits 
                WHERE end_time > NOW() AND plan_id > 0";
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Get expired members
     */
    private function get_expired_members($limit = 20, $offset = 0) {
        global $wpdb;
        
        $sql = "SELECT DISTINCT c.user_id, u.display_name, u.user_email, 
                       MAX(c.end_time) as latest_end_time,
                       SUM(c.credit) as total_credits,
                       COUNT(c.id) as subscription_count
                FROM {$wpdb->prefix}rolino_credits c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.end_time <= NOW() AND c.plan_id > 0
                GROUP BY c.user_id
                ORDER BY latest_end_time DESC
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    /**
     * Get expired members count
     */
    private function get_expired_members_count() {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->prefix}rolino_credits 
                WHERE end_time <= NOW() AND plan_id > 0";
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Get single buy users
     */
    private function get_single_buy_users($limit = 20, $offset = 0) {
        global $wpdb;
        
        $sql = "SELECT DISTINCT c.user_id, u.display_name, u.user_email, 
                       MAX(c.end_time) as latest_end_time,
                       SUM(c.credit) as total_credits,
                       COUNT(c.id) as purchase_count
                FROM {$wpdb->prefix}rolino_credits c
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.plan_id = 0
                GROUP BY c.user_id
                ORDER BY latest_end_time DESC
                LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    /**
     * Get single buy users count
     */
    private function get_single_buy_users_count() {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->prefix}rolino_credits 
                WHERE plan_id = 0";
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Get user subscriptions
     */
    public function get_user_subscriptions($user_id) {
        global $wpdb;
        
        $sql = "SELECT c.*, p.plan_name 
                FROM {$wpdb->prefix}rolino_credits c
                LEFT JOIN {$wpdb->prefix}rolino_plans p ON c.plan_id = p.id
                WHERE c.user_id = %d
                ORDER BY c.start_time DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * Get user single purchases
     */
    public function get_user_single_purchases($user_id) {
        global $wpdb;
        
        $sql = "SELECT c.* 
                FROM {$wpdb->prefix}rolino_credits c
                WHERE c.user_id = %d AND c.plan_id = 0
                ORDER BY c.start_time DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * AJAX save member
     */
    public function ajax_save_member() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $credits = intval($_POST['credits'] ?? 0);
        $duration = intval($_POST['duration'] ?? 30);
        
        if (!$user_id || !$plan_id || $credits <= 0) {
            wp_send_json_error(array('message' => __('لطفاً تمام فیلدها را پر کنید', 'rolino')));
        }
        
        $result = $this->get_credits()->add_credits($user_id, $plan_id, $credits, $duration);
        
        if ($result) {
            wp_send_json_success(array('message' => __('اعتبار با موفقیت اضافه شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در اضافه کردن اعتبار', 'rolino')));
        }
    }
    
    /**
     * AJAX delete member
     */
    public function ajax_delete_member() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('شناسه کاربر نامعتبر است', 'rolino')));
        }
        
        $result = $this->get_credits()->delete_user_credits($user_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('اعتبارهای کاربر حذف شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در حذف اعتبارها', 'rolino')));
        }
    }
    
    /**
     * AJAX extend membership
     */
    public function ajax_extend_membership() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $days = intval($_POST['days'] ?? 30);
        
        if (!$user_id || $days <= 0) {
            wp_send_json_error(array('message' => __('لطفاً تعداد روزها را وارد کنید', 'rolino')));
        }
        
        $result = $this->get_credits()->extend_user_credits($user_id, $days);
        
        if ($result) {
            wp_send_json_success(array('message' => __('اعتبار تمدید شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در تمدید اعتبار', 'rolino')));
        }
    }
    
    /**
     * AJAX get member details
     */
    public function ajax_get_member_details() {
        check_ajax_referer('rolino_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('شناسه کاربر نامعتبر است', 'rolino')));
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('کاربر یافت نشد', 'rolino')));
        }
        
        $subscriptions = $this->get_user_subscriptions($user_id);
        $single_purchases = $this->get_user_single_purchases($user_id);
        
        ob_start();
        ?>
        <div class="member-details">
            <h3><?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</h3>
            
            <h4><?php _e('اشتراک‌ها', 'rolino'); ?></h4>
            <?php if (!empty($subscriptions)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('طرح', 'rolino'); ?></th>
                            <th><?php _e('شروع', 'rolino'); ?></th>
                            <th><?php _e('پایان', 'rolino'); ?></th>
                            <th><?php _e('اعتبار', 'rolino'); ?></th>
                            <th><?php _e('وضعیت', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <td><?php echo esc_html($subscription->plan_name ?? __('نامشخص', 'rolino')); ?></td>
                                <td><?php echo $this->format_date($subscription->start_time); ?></td>
                                <td><?php echo $this->format_date($subscription->end_time); ?></td>
                                <td><?php echo number_format($subscription->credit); ?></td>
                                <td><?php echo $this->get_status_badge($subscription->end_time); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('هیچ اشتراکی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
            
            <h4><?php _e('خریدهای تکی', 'rolino'); ?></h4>
            <?php if (!empty($single_purchases)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('تاریخ خرید', 'rolino'); ?></th>
                            <th><?php _e('انقضا', 'rolino'); ?></th>
                            <th><?php _e('اعتبار', 'rolino'); ?></th>
                            <th><?php _e('وضعیت', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($single_purchases as $purchase): ?>
                            <tr>
                                <td><?php echo $this->format_date($purchase->start_time); ?></td>
                                <td><?php echo $this->format_date($purchase->end_time); ?></td>
                                <td><?php echo number_format($purchase->credit); ?></td>
                                <td><?php echo $this->get_status_badge($purchase->end_time); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('هیچ خرید تکی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Get status badge
     */
    public function get_status_badge($end_time) {
        $now = current_time('mysql');
        $end = new DateTime($end_time);
        $current = new DateTime($now);
        
        if ($end > $current) {
            $diff = $current->diff($end);
            if ($diff->days <= 7) {
                return '<span class="badge badge-warning">' . sprintf(__('%d روز باقی', 'rolino'), $diff->days) . '</span>';
            } else {
                return '<span class="badge badge-success">' . __('فعال', 'rolino') . '</span>';
            }
        } else {
            return '<span class="badge badge-danger">' . __('منقضی شده', 'rolino') . '</span>';
        }
    }
    
    /**
     * Format date
     */
    public function format_date($date) {
        return date_i18n('Y/m/d H:i', strtotime($date));
    }
    
    /**
     * Get dashboard stats
     */
    public function get_dashboard_stats() {
        $active_count = $this->get_active_members_count();
        $expired_count = $this->get_expired_members_count();
        $single_buy_count = $this->get_single_buy_users_count();
        
        return array(
            'active' => $active_count,
            'expired' => $expired_count,
            'single_buy' => $single_buy_count,
            'total' => $active_count + $expired_count + $single_buy_count
        );
    }
}