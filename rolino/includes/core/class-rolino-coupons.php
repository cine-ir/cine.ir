<?php
/**
 * Rolino Coupons Core Class
 * 
 * Handles all coupon-related operations including validation, activation, and discount calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Coupons {
    
    private $table_name;
    private $discounts_table;
    private $activations_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'rolino_coupons';
        $this->discounts_table = $wpdb->prefix . 'rolino_coupon_plan_discounts';
        $this->activations_table = $wpdb->prefix . 'rolino_coupon_user_activations';
    }
    
    /**
     * Create new coupon
     * 
     * @param array $data Coupon data
     * @return int|false Coupon ID on success, false on failure
     */
    public function create_coupon($data) {
        global $wpdb;
        
        $data = $this->sanitize_coupon_data($data);
        
        if (!$this->validate_coupon_data($data)) {
            return false;
        }
        
        // Check if code already exists
        if ($this->coupon_code_exists($data['code'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'code' => $data['code'],
                'type' => $data['type'],
                'duration_days' => $data['duration_days'],
                'status' => $data['status']
            ),
            array('%s', '%d', '%d', '%d')
        );
        
        if ($result !== false) {
            $coupon_id = $wpdb->insert_id;
            
            // Add plan discounts
            if (!empty($data['plan_discounts'])) {
                $this->add_plan_discounts($coupon_id, $data['plan_discounts']);
            }
            
            do_action('rolino_coupon_created', $coupon_id, $data);
            return $coupon_id;
        }
        
        return false;
    }
    
    /**
     * Update coupon
     * 
     * @param int $coupon_id
     * @param array $data
     * @return bool
     */
    public function update_coupon($coupon_id, $data) {
        global $wpdb;
        
        $data = $this->sanitize_coupon_data($data);
        
        if (!$this->validate_coupon_data($data)) {
            return false;
        }
        
        // Check if code exists for other coupons
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE code = %s AND id != %d",
                $data['code'],
                $coupon_id
            )
        );
        
        if ($existing) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            array(
                'code' => $data['code'],
                'type' => $data['type'],
                'duration_days' => $data['duration_days'],
                'status' => $data['status']
            ),
            array('id' => $coupon_id),
            array('%s', '%d', '%d', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update plan discounts
            $this->remove_plan_discounts($coupon_id);
            if (!empty($data['plan_discounts'])) {
                $this->add_plan_discounts($coupon_id, $data['plan_discounts']);
            }
            
            do_action('rolino_coupon_updated', $coupon_id, $data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete coupon
     * 
     * @param int $coupon_id
     * @return bool
     */
    public function delete_coupon($coupon_id) {
        global $wpdb;
        
        // Remove related records
        $wpdb->delete($this->activations_table, array('coupon_id' => $coupon_id), array('%d'));
        $wpdb->delete($this->discounts_table, array('coupon_id' => $coupon_id), array('%d'));
        
        // Delete the coupon
        $result = $wpdb->delete($this->table_name, array('id' => $coupon_id), array('%d'));
        
        if ($result !== false) {
            do_action('rolino_coupon_deleted', $coupon_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get coupon by ID
     * 
     * @param int $coupon_id
     * @return object|null
     */
    public function get_coupon($coupon_id) {
        global $wpdb;
        
        $coupon = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $coupon_id)
        );
        
        if ($coupon) {
            $coupon->plan_discounts = $this->get_coupon_plan_discounts($coupon_id);
        }
        
        return $coupon;
    }
    
    /**
     * Get coupon by code
     * 
     * @param string $code
     * @return object|null
     */
    public function get_coupon_by_code($code) {
        global $wpdb;
        
        $coupon = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE code = %s", $code)
        );
        
        if ($coupon) {
            $coupon->plan_discounts = $this->get_coupon_plan_discounts($coupon->id);
        }
        
        return $coupon;
    }
    
    /**
     * Get all coupons
     * 
     * @param array $args
     * @return array
     */
    public function get_coupons($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'all',
            'type' => 'all',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->table_name}";
        $where_conditions = array();
        
        if ($args['status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("status = %d", $args['status']);
        }
        
        if ($args['type'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("type = %d", $args['type']);
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
     * Validate coupon for user and plan
     * 
     * @param string $coupon_code
     * @param int $plan_id
     * @param int $user_id
     * @return array
     */
    public function validate_coupon($coupon_code, $plan_id, $user_id = null) {
        $coupon = $this->get_coupon_by_code($coupon_code);
        
        if (!$coupon) {
            return array(
                'valid' => false,
                'message' => __('کد تخفیف نامعتبر است', 'rolino')
            );
        }
        
        // Check if coupon is active
        if ($coupon->status != 1) {
            return array(
                'valid' => false,
                'message' => __('کد تخفیف غیرفعال است', 'rolino')
            );
        }
        
        // Check coupon type and validity
        $validation_result = $this->validate_coupon_type($coupon, $user_id);
        if (!$validation_result['valid']) {
            return $validation_result;
        }
        
        // Check if coupon applies to this plan
        $discount_percent = $this->get_plan_discount_percent($coupon->id, $plan_id);
        if ($discount_percent === false) {
            return array(
                'valid' => false,
                'message' => __('این کد تخفیف برای این طرح قابل استفاده نیست', 'rolino')
            );
        }
        
        return array(
            'valid' => true,
            'coupon_id' => $coupon->id,
            'discount_percent' => $discount_percent,
            'message' => sprintf(__('تخفیف %d%% اعمال شد', 'rolino'), $discount_percent)
        );
    }
    
    /**
     * Validate coupon type specific rules
     * 
     * @param object $coupon
     * @param int $user_id
     * @return array
     */
    private function validate_coupon_type($coupon, $user_id = null) {
        switch ($coupon->type) {
            case 1: // Global - active site-wide after creation date
                $expiry_date = date('Y-m-d H:i:s', strtotime($coupon->created_at . ' + ' . $coupon->duration_days . ' days'));
                if (current_time('mysql') > $expiry_date) {
                    return array(
                        'valid' => false,
                        'message' => __('کد تخفیف منقضی شده است', 'rolino')
                    );
                }
                break;
                
            case 2: // Public - available for all after manual activation
                // This type is manually activated by admin, so just check if it's active
                break;
                
            case 3: // Exclusive - activated for specific users via SMS
                if (!$user_id) {
                    return array(
                        'valid' => false,
                        'message' => __('برای استفاده از این کد باید وارد حساب کاربری شوید', 'rolino')
                    );
                }
                
                $activation = $this->get_user_coupon_activation($coupon->id, $user_id);
                if (!$activation) {
                    return array(
                        'valid' => false,
                        'message' => __('این کد تخفیف برای شما فعال نشده است', 'rolino')
                    );
                }
                
                if (current_time('mysql') > $activation->end_time) {
                    return array(
                        'valid' => false,
                        'message' => __('کد تخفیف شما منقضی شده است', 'rolino')
                    );
                }
                break;
        }
        
        return array('valid' => true);
    }
    
    /**
     * Activate exclusive coupon for user
     * 
     * @param int $coupon_id
     * @param int $user_id
     * @return bool
     */
    public function activate_exclusive_coupon($coupon_id, $user_id) {
        global $wpdb;
        
        $coupon = $this->get_coupon($coupon_id);
        
        if (!$coupon || $coupon->type != 3) {
            return false;
        }
        
        // Check if already activated
        $existing = $this->get_user_coupon_activation($coupon_id, $user_id);
        if ($existing) {
            return true;
        }
        
        $start_time = current_time('mysql');
        $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' + ' . $coupon->duration_days . ' days'));
        
        $result = $wpdb->insert(
            $this->activations_table,
            array(
                'coupon_id' => $coupon_id,
                'user_id' => $user_id,
                'start_time' => $start_time,
                'end_time' => $end_time
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Extend coupon validity for user
     * 
     * @param int $coupon_id
     * @param int $user_id
     * @param int $days
     * @return bool
     */
    public function extend_coupon_validity($coupon_id, $user_id, $days) {
        global $wpdb;
        
        $activation = $this->get_user_coupon_activation($coupon_id, $user_id);
        
        if (!$activation) {
            return false;
        }
        
        $new_end_time = date('Y-m-d H:i:s', strtotime($activation->end_time . ' + ' . $days . ' days'));
        
        $result = $wpdb->update(
            $this->activations_table,
            array('end_time' => $new_end_time),
            array('id' => $activation->id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get user's active coupons
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_active_coupons($user_id) {
        global $wpdb;
        
        $sql = "SELECT c.code, ca.end_time
                FROM {$this->table_name} c
                INNER JOIN {$this->activations_table} ca ON c.id = ca.coupon_id
                WHERE ca.user_id = %d 
                AND ca.end_time > NOW()
                AND c.status = 1";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * Get user's coupon activation
     * 
     * @param int $coupon_id
     * @param int $user_id
     * @return object|null
     */
    public function get_user_coupon_activation($coupon_id, $user_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->activations_table} 
                 WHERE coupon_id = %d AND user_id = %d 
                 ORDER BY end_time DESC LIMIT 1",
                $coupon_id,
                $user_id
            )
        );
    }
    
    /**
     * Get coupon plan discounts
     * 
     * @param int $coupon_id
     * @return array
     */
    public function get_coupon_plan_discounts($coupon_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->discounts_table} WHERE coupon_id = %d",
                $coupon_id
            )
        );
    }
    
    /**
     * Get discount percentage for specific plan
     * 
     * @param int $coupon_id
     * @param int $plan_id
     * @return int|false
     */
    public function get_plan_discount_percent($coupon_id, $plan_id) {
        global $wpdb;
        
        $discount = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT discount_percent FROM {$this->discounts_table} 
                 WHERE coupon_id = %d AND plan_id = %d",
                $coupon_id,
                $plan_id
            )
        );
        
        return $discount !== null ? intval($discount) : false;
    }
    
    /**
     * Add plan discounts to coupon
     * 
     * @param int $coupon_id
     * @param array $plan_discounts
     * @return bool
     */
    public function add_plan_discounts($coupon_id, $plan_discounts) {
        global $wpdb;
        
        foreach ($plan_discounts as $plan_id => $discount_percent) {
            $wpdb->insert(
                $this->discounts_table,
                array(
                    'coupon_id' => $coupon_id,
                    'plan_id' => intval($plan_id),
                    'discount_percent' => intval($discount_percent)
                ),
                array('%d', '%d', '%d')
            );
        }
        
        return true;
    }
    
    /**
     * Remove plan discounts from coupon
     * 
     * @param int $coupon_id
     * @return bool
     */
    public function remove_plan_discounts($coupon_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->discounts_table,
            array('coupon_id' => $coupon_id),
            array('%d')
        ) !== false;
    }
    
    /**
     * Check if coupon code exists
     * 
     * @param string $code
     * @return bool
     */
    public function coupon_code_exists($code) {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE code = %s",
                $code
            )
        );
        
        return $count > 0;
    }
    
    /**
     * Generate random coupon code
     * 
     * @param int $length
     * @return string
     */
    public function generate_coupon_code($length = 6) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while ($this->coupon_code_exists($code));
        
        return $code;
    }
    
    /**
     * Sanitize coupon data
     * 
     * @param array $data
     * @return array
     */
    private function sanitize_coupon_data($data) {
        return array(
            'code' => strtoupper(sanitize_text_field($data['code'] ?? '')),
            'type' => intval($data['type'] ?? 1),
            'duration_days' => intval($data['duration_days'] ?? 30),
            'status' => intval($data['status'] ?? 1),
            'plan_discounts' => is_array($data['plan_discounts'] ?? null) ? $data['plan_discounts'] : array()
        );
    }
    
    /**
     * Validate coupon data
     * 
     * @param array $data
     * @return bool
     */
    public function validate_coupon_data($data) {
        if (empty($data['code']) || strlen($data['code']) < 4 || strlen($data['code']) > 8) {
            return false;
        }
        
        if (!in_array($data['type'], array(1, 2, 3))) {
            return false;
        }
        
        if ($data['duration_days'] <= 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get coupon statistics
     * 
     * @return array
     */
    public function get_coupon_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total coupons
        $stats['total_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Active coupons
        $stats['active_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 1");
        
        // Coupons by type
        $stats['global_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE type = 1 AND status = 1");
        $stats['public_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE type = 2 AND status = 1");
        $stats['exclusive_coupons'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE type = 3 AND status = 1");
        
        // Total activations
        $stats['total_activations'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->activations_table}");
        
        return $stats;
    }
    
    /**
     * Get user coupon info for SMS variables
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_coupon_info($user_id) {
        $active_coupons = $this->get_user_active_coupons($user_id);
        
        $info = array(
            'off_code' => '',
            'off_code_remaining_time' => 0
        );
        
        if (!empty($active_coupons)) {
            $codes = array();
            $latest_end_time = null;
            
            foreach ($active_coupons as $coupon) {
                $codes[] = $coupon->code;
                
                if (!$latest_end_time || strtotime($coupon->end_time) > strtotime($latest_end_time)) {
                    $latest_end_time = $coupon->end_time;
                }
            }
            
            $info['off_code'] = implode(', ', $codes);
            
            if ($latest_end_time) {
                $end_time = strtotime($latest_end_time);
                $current_time = current_time('timestamp');
                $remaining_seconds = $end_time - $current_time;
                $info['off_code_remaining_time'] = max(0, ceil($remaining_seconds / DAY_IN_SECONDS));
            }
        }
        
        return $info;
    }
}