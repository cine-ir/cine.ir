<?php
/**
 * Rolino Plans Core Class
 * 
 * Handles all plan-related operations including CRUD, groups, and pricing calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Plans {
    
    private $table_name;
    private $groups_table;
    private $group_items_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'rolino_plans';
        $this->groups_table = $wpdb->prefix . 'rolino_plan_groups';
        $this->group_items_table = $wpdb->prefix . 'rolino_plan_group_items';
        
        // Add hooks
        add_action('wp_ajax_rolino_toggle_plan_group', array($this, 'ajax_toggle_plan_group'));
    }
    
    /**
     * Get all plans
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function get_plans($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 1,
            'orderby' => 'id',
            'order' => 'ASC',
            'limit' => null,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->table_name}";
        $where_conditions = array();
        
        if ($args['status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("status = %d", $args['status']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $sql .= $wpdb->prepare(" ORDER BY %s %s", $args['orderby'], $args['order']);
        
        if ($args['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get active plans
     * 
     * @return array
     */
    public function get_active_plans() {
        return $this->get_plans(array('status' => 1));
    }
    
    /**
     * Get plans count
     * 
     * @param array $args
     * @return int
     */
    public function get_plans_count($args = array()) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        $where_conditions = array();
        
        if (isset($args['status']) && $args['status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("status = %d", $args['status']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Get plan by ID
     * 
     * @param int $plan_id
     * @return object|null
     */
    public function get_plan($plan_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $plan_id)
        );
    }
    
    /**
     * Create new plan
     * 
     * @param array $data Plan data
     * @return int|false Plan ID on success, false on failure
     */
    public function create_plan($data) {
        global $wpdb;
        
        $data = $this->sanitize_plan_data($data);
        
        $validation_result = $this->validate_plan_data($data);
        if (!$validation_result['valid']) {
            return false;
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array('%s', '%d', '%f', '%d', '%d', '%d')
        );
        
        if ($result !== false) {
            do_action('rolino_plan_created', $wpdb->insert_id, $data);
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update plan
     * 
     * @param int $plan_id
     * @param array $data Plan data
     * @return bool
     */
    public function update_plan($plan_id, $data) {
        global $wpdb;
        
        $data = $this->sanitize_plan_data($data);
        
        $validation_result = $this->validate_plan_data($data);
        if (!$validation_result['valid']) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $plan_id),
            array('%s', '%d', '%f', '%d', '%d', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('rolino_plan_updated', $plan_id, $data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete plan
     * 
     * @param int $plan_id
     * @return bool
     */
    public function delete_plan($plan_id) {
        global $wpdb;
        
        // Check if plan is being used
        if ($this->is_plan_in_use($plan_id)) {
            return false;
        }
        
        // Remove from groups first
        $wpdb->delete($this->group_items_table, array('plan_id' => $plan_id), array('%d'));
        
        // Delete the plan
        $result = $wpdb->delete($this->table_name, array('id' => $plan_id), array('%d'));
        
        if ($result !== false) {
            do_action('rolino_plan_deleted', $plan_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if plan is being used in transactions or credits
     * 
     * @param int $plan_id
     * @return bool
     */
    private function is_plan_in_use($plan_id) {
        global $wpdb;
        
        $transactions_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_transactions WHERE plan_id = %d",
                $plan_id
            )
        );
        
        $credits_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rolino_credits WHERE plan_id = %d",
                $plan_id
            )
        );
        
        return ($transactions_count > 0 || $credits_count > 0);
    }
    
    /**
     * Calculate discounted price for a plan
     * 
     * @param int $plan_id
     * @param string $coupon_code
     * @return array
     */
    public function calculate_discounted_price($plan_id, $coupon_code = '') {
        $plan = $this->get_plan($plan_id);
        
        if (!$plan) {
            return array('error' => 'Plan not found');
        }
        
        $original_price = floatval($plan->price);
        $discounted_price = $original_price;
        $discount_percent = 0;
        
        if (!empty($coupon_code)) {
            $coupons = new Rolino_Coupons();
            $coupon_data = $coupons->validate_coupon($coupon_code, $plan_id);
            
            if ($coupon_data['valid']) {
                $discount_percent = $coupon_data['discount_percent'];
                $discounted_price = $original_price * (1 - $discount_percent / 100);
            }
        }
        
        return array(
            'original_price' => $original_price,
            'discounted_price' => $discounted_price,
            'discount_percent' => $discount_percent,
            'savings' => $original_price - $discounted_price,
            'value_ratio' => $this->calculate_value_ratio($plan, $discount_percent)
        );
    }
    
    /**
     * Calculate value ratio for a plan
     * 
     * @param object $plan
     * @param float $discount_percent
     * @return float
     */
    private function calculate_value_ratio($plan, $discount_percent = 0) {
        $credits_per_toman = $plan->credits / $plan->price;
        $base_ratio = $credits_per_toman * 1000; // Base calculation
        
        if ($discount_percent > 0) {
            $ratio_multiplier = 1 + ($discount_percent / 100);
            $base_ratio *= $ratio_multiplier;
        }
        
        return round($base_ratio, 1);
    }
    
    /**
     * Plan Groups Management
     */
    
    /**
     * Get all plan groups
     * 
     * @return array
     */
    public function get_plan_groups() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$this->groups_table} ORDER BY id ASC");
    }
    
    /**
     * Get plans by group
     * 
     * @param int $group_id
     * @return array
     */
    public function get_plans_by_group($group_id) {
        global $wpdb;
        
        $sql = "SELECT p.* FROM {$this->table_name} p 
                INNER JOIN {$this->group_items_table} gi ON p.id = gi.plan_id 
                WHERE gi.group_id = %d AND p.status = 1 
                ORDER BY p.id ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $group_id));
    }
    
    /**
     * Get grouped plans for frontend display
     * 
     * @return array
     */
    public function get_grouped_plans() {
        $groups = $this->get_plan_groups();
        $grouped_plans = array();
        
        foreach ($groups as $group) {
            $plans = $this->get_plans_by_group($group->id);
            if (!empty($plans)) {
                $grouped_plans[$group->group_name] = $plans;
            }
        }
        
        return $grouped_plans;
    }
    
    /**
     * Get ungrouped plans
     * 
     * @return array
     */
    public function get_ungrouped_plans() {
        global $wpdb;
        
        $sql = "SELECT p.* FROM {$this->table_name} p 
                LEFT JOIN {$this->group_items_table} gi ON p.id = gi.plan_id 
                WHERE gi.plan_id IS NULL AND p.status = 1 
                ORDER BY p.id ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Create plan group
     * 
     * @param string $group_name
     * @return int|false
     */
    public function create_plan_group($group_name) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->groups_table,
            array('group_name' => sanitize_text_field($group_name)),
            array('%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Update plan group
     * 
     * @param int $group_id
     * @param string $group_name
     * @return bool
     */
    public function update_plan_group($group_id, $group_name) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->groups_table,
            array('group_name' => sanitize_text_field($group_name)),
            array('id' => $group_id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete plan group
     * 
     * @param int $group_id
     * @return bool
     */
    public function delete_plan_group($group_id) {
        global $wpdb;
        
        // Remove all plans from this group first
        $wpdb->delete($this->group_items_table, array('group_id' => $group_id), array('%d'));
        
        // Delete the group
        $result = $wpdb->delete($this->groups_table, array('id' => $group_id), array('%d'));
        
        return $result !== false;
    }
    
    /**
     * Add plan to group
     * 
     * @param int $plan_id
     * @param int $group_id
     * @return bool
     */
    public function add_plan_to_group($plan_id, $group_id) {
        global $wpdb;
        
        // Check if already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->group_items_table} WHERE plan_id = %d AND group_id = %d",
                $plan_id,
                $group_id
            )
        );
        
        if ($exists) {
            return true;
        }
        
        $result = $wpdb->insert(
            $this->group_items_table,
            array('plan_id' => $plan_id, 'group_id' => $group_id),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove plan from group
     * 
     * @param int $plan_id
     * @param int $group_id
     * @return bool
     */
    public function remove_plan_from_group($plan_id, $group_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->group_items_table,
            array('plan_id' => $plan_id, 'group_id' => $group_id),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * AJAX handler for toggling plan group membership
     */
    public function ajax_toggle_plan_group() {
        check_ajax_referer('rolino_plan_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('شما اجازه انجام این عملیات را ندارید', 'rolino'));
        }
        
        $plan_id = intval($_POST['plan_id']);
        $group_id = intval($_POST['group_id']);
        $checked = intval($_POST['checked']);
        
        if ($checked) {
            $result = $this->add_plan_to_group($plan_id, $group_id);
        } else {
            $result = $this->remove_plan_from_group($plan_id, $group_id);
        }
        
        if ($result) {
            wp_send_json_success(array('message' => __('تغییرات ذخیره شد', 'rolino')));
        } else {
            wp_send_json_error(array('message' => __('خطا در ذخیره تغییرات', 'rolino')));
        }
    }
    
    /**
     * Get plans for a specific group (admin helper)
     * 
     * @param int $group_id
     * @return array Array of plan IDs
     */
    public function get_group_plan_ids($group_id) {
        global $wpdb;
        
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT plan_id FROM {$this->group_items_table} WHERE group_id = %d",
                $group_id
            )
        );
    }
    
    /**
     * Sanitize plan data
     * 
     * @param array $data
     * @return array
     */
    private function sanitize_plan_data($data) {
        return array(
            'plan_name' => sanitize_text_field($data['plan_name'] ?? ''),
            'duration' => intval($data['duration'] ?? 0),
            'price' => floatval($data['price'] ?? 0),
            'credits' => intval($data['credits'] ?? 0),
            'active_sessions' => intval($data['active_sessions'] ?? 1),
            'status' => intval($data['status'] ?? 1)
        );
    }
    
    /**
     * Validate plan data
     * 
     * @param array $data
     * @return array
     */
    public function validate_plan_data($data) {
        if (empty($data['plan_name'])) {
            return array('valid' => false, 'message' => __('نام طرح الزامی است', 'rolino'));
        }
        
        if ($data['duration'] <= 0) {
            return array('valid' => false, 'message' => __('مدت طرح باید بیشتر از صفر باشد', 'rolino'));
        }
        
        if ($data['price'] < 0) {
            return array('valid' => false, 'message' => __('قیمت طرح نمی‌تواند منفی باشد', 'rolino'));
        }
        
        if ($data['credits'] < 0) {
            return array('valid' => false, 'message' => __('اعتبار طرح نمی‌تواند منفی باشد', 'rolino'));
        }
        
        if ($data['active_sessions'] <= 0) {
            return array('valid' => false, 'message' => __('تعداد جلسات همزمان باید بیشتر از صفر باشد', 'rolino'));
        }
        
        return array('valid' => true, 'message' => '');
    }
    
    /**
     * Get plan statistics
     * 
     * @return array
     */
    public function get_plan_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total plans
        $stats['total_plans'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Active plans
        $stats['active_plans'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 1");
        
        // Total groups
        $stats['total_groups'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->groups_table}");
        
        // Most popular plan
        $popular_plan = $wpdb->get_row("
            SELECT p.plan_name, COUNT(t.id) as purchase_count 
            FROM {$this->table_name} p 
            LEFT JOIN {$wpdb->prefix}rolino_transactions t ON p.id = t.plan_id 
            WHERE t.status = 'completed' 
            GROUP BY p.id 
            ORDER BY purchase_count DESC 
            LIMIT 1
        ");
        
        $stats['most_popular_plan'] = $popular_plan ? $popular_plan->plan_name : __('هیچ', 'rolino');
        
        return $stats;
    }
}