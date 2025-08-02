<?php
/**
 * Rolino SMS Core Class
 * 
 * Handles SMS scenarios, variable replacement, and message sending
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_SMS {
    
    private $scenarios_table;
    private $logs_table;
    private $queue_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->scenarios_table = $wpdb->prefix . 'rolino_sms_scenarios';
        $this->logs_table = $wpdb->prefix . 'rolino_sms_logs';
        $this->queue_table = $wpdb->prefix . 'rolino_sms_queue';
        
        // Add action hooks for triggering SMS scenarios
        add_action('rolino_main_subscription_activated', array($this, 'trigger_main_activated'), 10, 3);
        add_action('rolino_reserve_subscription_added', array($this, 'trigger_reserve_activated'), 10, 3);
        
        // Add cron hooks
        add_action('init', array($this, 'schedule_reminder_checks'));
        add_action('rolino_check_subscription_reminders', array($this, 'check_subscription_reminders'));
        add_action('rolino_check_expired_subscriptions', array($this, 'check_expired_subscriptions'));
        add_action('rolino_check_coupon_reminders', array($this, 'check_coupon_reminders'));
    }
    
    /**
     * Send SMS message
     * 
     * @param int $user_id
     * @param int $scenario_id
     * @param array $variables Additional variables
     * @param bool $queue_if_failed Queue if sending fails
     * @return bool
     */
    public function send_sms($user_id, $scenario_id, $variables = array(), $queue_if_failed = true) {
        $scenario = $this->get_scenario($scenario_id);
        
        if (!$scenario || !$scenario->status) {
            return false;
        }
        
        // Get user phone number
        $user_phone = $this->get_user_phone($user_id);
        
        if (empty($user_phone)) {
            $this->log_sms($user_id, $scenario_id, 'No phone number', 0);
            return false;
        }
        
        // Prepare message with variable replacement
        $message = $this->prepare_message($user_id, $scenario->message_template, $variables);
        
        // Send SMS
        $sent_status = $this->send_sms_api($user_phone, $message);
        
        // Log the attempt
        $this->log_sms($user_id, $scenario_id, $message, $sent_status ? 1 : 0);
        
        // Queue for retry if failed and queue is enabled
        if (!$sent_status && $queue_if_failed) {
            $this->add_to_queue($user_id, $scenario_id, $variables);
        }
        
        return $sent_status;
    }
    
    /**
     * Prepare message with variable replacement
     * 
     * @param int $user_id
     * @param string $template
     * @param array $additional_variables
     * @return string
     */
    public function prepare_message($user_id, $template, $additional_variables = array()) {
        // Get all available variables
        $variables = $this->get_user_variables($user_id);
        
        // Merge with additional variables
        $variables = array_merge($variables, $additional_variables);
        
        // Process action variables (add_off_code=X, single_buy=X, etc.)
        $message = $this->process_action_variables($user_id, $template, $variables);
        
        // Replace display variables
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Process action variables that trigger actions
     * 
     * @param int $user_id
     * @param string $template
     * @param array $variables
     * @return string
     */
    private function process_action_variables($user_id, $template, $variables) {
        $message = $template;
        
        // Pattern to match action variables: variable=value
        preg_match_all('/{{(\w+)=(\w+)}}/', $template, $matches);
        
        if (!empty($matches[0])) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $full_match = $matches[0][$i];
                $action = $matches[1][$i];
                $value = $matches[2][$i];
                
                $replacement_text = '';
                
                switch ($action) {
                    case 'add_off_code':
                        $replacement_text = $this->process_add_off_code($user_id, $value);
                        break;
                        
                    case 'single_buy':
                        $replacement_text = $this->process_single_buy($user_id, $value);
                        break;
                        
                    case 'add_remaining_time':
                        $replacement_text = $this->process_add_remaining_time($user_id, $value);
                        break;
                        
                    case 'add_off_code_remaining_time':
                        $replacement_text = $this->process_add_off_code_remaining_time($user_id, $value);
                        break;
                        
                    case 'add_plan':
                        $replacement_text = $this->process_add_plan($user_id, $value);
                        break;
                }
                
                // Replace the action variable with the result text
                $message = str_replace($full_match, $replacement_text, $message);
            }
        }
        
        return $message;
    }
    
    /**
     * Process add_off_code=X action
     * 
     * @param int $user_id
     * @param int $coupon_id
     * @return string
     */
    private function process_add_off_code($user_id, $coupon_id) {
        $coupons = new Rolino_Coupons();
        $coupon = $coupons->get_coupon($coupon_id);
        
        if (!$coupon) {
            return '';
        }
        
        // Activate coupon for user
        $coupons->activate_exclusive_coupon($coupon_id, $user_id);
        
        return $coupon->code;
    }
    
    /**
     * Process single_buy=X action
     * 
     * @param int $user_id
     * @param int $credits
     * @return string
     */
    private function process_single_buy($user_id, $credits) {
        $credits_obj = new Rolino_Credits();
        $single_buy_settings = array(
            'duration' => intval(get_option('rolino_single_buy_duration', 30)),
            'credits' => intval($credits)
        );
        
        $credits_obj->add_credit($user_id, 0, $single_buy_settings['credits'], $single_buy_settings['duration']);
        
        return $credits;
    }
    
    /**
     * Process add_remaining_time=X action
     * 
     * @param int $user_id
     * @param int $days
     * @return string
     */
    private function process_add_remaining_time($user_id, $days) {
        $credits = new Rolino_Credits();
        $credits->extend_subscription($user_id, intval($days));
        
        return $days;
    }
    
    /**
     * Process add_off_code_remaining_time=X action
     * 
     * @param int $user_id
     * @param int $days
     * @return string
     */
    private function process_add_off_code_remaining_time($user_id, $days) {
        $coupons = new Rolino_Coupons();
        $user_coupons = $coupons->get_user_active_coupons($user_id);
        
        if (!empty($user_coupons)) {
            // Extend the latest coupon
            $latest_coupon = end($user_coupons);
            $coupon = $coupons->get_coupon_by_code($latest_coupon->code);
            
            if ($coupon) {
                $coupons->extend_coupon_validity($coupon->id, $user_id, intval($days));
            }
        }
        
        return $days;
    }
    
    /**
     * Process add_plan=X action
     * 
     * @param int $user_id
     * @param int $plan_id
     * @return string
     */
    private function process_add_plan($user_id, $plan_id) {
        $plans = new Rolino_Plans();
        $plan = $plans->get_plan($plan_id);
        
        if (!$plan) {
            return '';
        }
        
        $credits = new Rolino_Credits();
        $credits->add_credit($user_id, $plan_id, $plan->credits, $plan->duration);
        
        return $plan->plan_name;
    }
    
    /**
     * Get all variables for a user
     * 
     * @param int $user_id
     * @return array
     */
    private function get_user_variables($user_id) {
        $variables = array();
        
        // Get subscription info
        $credits = new Rolino_Credits();
        $subscription_info = $credits->get_user_subscription_info($user_id);
        $variables = array_merge($variables, $subscription_info);
        
        // Get coupon info
        $coupons = new Rolino_Coupons();
        $coupon_info = $coupons->get_user_coupon_info($user_id);
        $variables = array_merge($variables, $coupon_info);
        
        return $variables;
    }
    
    /**
     * Trigger main subscription activated scenario
     * 
     * @param int $user_id
     * @param int $plan_id
     * @param int $duration
     */
    public function trigger_main_activated($user_id, $plan_id, $duration) {
        $scenario = $this->get_scenario_by_type(4); // Main Activated
        
        if ($scenario) {
            $this->send_sms($user_id, $scenario->id);
        }
    }
    
    /**
     * Trigger reserve subscription activated scenario
     * 
     * @param int $user_id
     * @param int $plan_id
     * @param int $duration
     */
    public function trigger_reserve_activated($user_id, $plan_id, $duration) {
        $scenario = $this->get_scenario_by_type(5); // Reserve Activated
        
        if ($scenario) {
            $this->send_sms($user_id, $scenario->id);
        }
    }
    
    /**
     * Schedule reminder checks
     */
    public function schedule_reminder_checks() {
        if (!wp_next_scheduled('rolino_check_subscription_reminders')) {
            wp_schedule_event(time(), 'daily', 'rolino_check_subscription_reminders');
        }
        
        if (!wp_next_scheduled('rolino_check_expired_subscriptions')) {
            wp_schedule_event(time(), 'daily', 'rolino_check_expired_subscriptions');
        }
        
        if (!wp_next_scheduled('rolino_check_coupon_reminders')) {
            wp_schedule_event(time(), 'daily', 'rolino_check_coupon_reminders');
        }
    }
    
    /**
     * Check subscription reminders
     */
    public function check_subscription_reminders() {
        $scenario = $this->get_scenario_by_type(1); // Reminder
        
        if (!$scenario || !$scenario->days_offset) {
            return;
        }
        
        global $wpdb;
        
        // Find subscriptions that need reminders
        $sql = "SELECT DISTINCT user_id FROM {$wpdb->prefix}rolino_credits 
                WHERE plan_id > 0 
                AND start_time <= NOW() 
                AND end_time > NOW() 
                AND DATEDIFF(end_time, NOW()) = %d";
        
        $users = $wpdb->get_col($wpdb->prepare($sql, $scenario->days_offset));
        
        foreach ($users as $user_id) {
            // Check if user has reserve subscription (don't send reminder if they do)
            $credits = new Rolino_Credits();
            $reserve = $credits->get_reserve_subscription($user_id);
            
            if (!$reserve) {
                $this->send_sms($user_id, $scenario->id);
            }
        }
    }
    
    /**
     * Check expired subscriptions
     */
    public function check_expired_subscriptions() {
        $scenario = $this->get_scenario_by_type(2); // Expired
        
        if (!$scenario || !$scenario->days_offset) {
            return;
        }
        
        global $wpdb;
        
        // Find subscriptions that expired X days ago
        $sql = "SELECT DISTINCT user_id FROM {$wpdb->prefix}rolino_credits 
                WHERE plan_id > 0 
                AND end_time <= NOW() 
                AND DATEDIFF(NOW(), end_time) = %d";
        
        $users = $wpdb->get_col($wpdb->prepare($sql, $scenario->days_offset));
        
        foreach ($users as $user_id) {
            // Check if user has reserve subscription (don't send expired if they do)
            $credits = new Rolino_Credits();
            $reserve = $credits->get_reserve_subscription($user_id);
            
            if (!$reserve) {
                $this->send_sms($user_id, $scenario->id);
            }
        }
    }
    
    /**
     * Check coupon reminders
     */
    public function check_coupon_reminders() {
        $scenario = $this->get_scenario_by_type(3); // Coupon Reminder
        
        if (!$scenario || !$scenario->days_offset) {
            return;
        }
        
        global $wpdb;
        
        // Find coupon activations that need reminders
        $sql = "SELECT DISTINCT user_id FROM {$wpdb->prefix}rolino_coupon_user_activations 
                WHERE end_time > NOW() 
                AND DATEDIFF(end_time, NOW()) = %d";
        
        $users = $wpdb->get_col($wpdb->prepare($sql, $scenario->days_offset));
        
        foreach ($users as $user_id) {
            $this->send_sms($user_id, $scenario->id);
        }
    }
    
    /**
     * Get scenario by ID
     * 
     * @param int $scenario_id
     * @return object|null
     */
    public function get_scenario($scenario_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->scenarios_table} WHERE id = %d", $scenario_id)
        );
    }
    
    /**
     * Get scenario by type
     * 
     * @param int $type
     * @return object|null
     */
    public function get_scenario_by_type($type) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->scenarios_table} WHERE scenario_type = %d AND status = 1", $type)
        );
    }
    
    /**
     * Get all scenarios
     * 
     * @return array
     */
    public function get_scenarios() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$this->scenarios_table} ORDER BY scenario_type ASC");
    }
    
    /**
     * Add scenario
     * 
     * @param array $data
     * @return int|false
     */
    public function add_scenario($data) {
        global $wpdb;
        
        $insert_data = array(
            'scenario_type' => intval($data['scenario_type']),
            'days_offset' => $data['days_offset'] === '' ? null : intval($data['days_offset']),
            'message_template' => sanitize_textarea_field($data['message_template']),
            'status' => intval($data['status'] ?? 1)
        );
        
        $result = $wpdb->insert(
            $this->scenarios_table,
            $insert_data,
            array('%d', '%d', '%s', '%d')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Update scenario
     * 
     * @param int $scenario_id
     * @param array $data
     * @return bool
     */
    public function update_scenario($scenario_id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['days_offset'])) {
            $update_data['days_offset'] = $data['days_offset'] === '' ? null : intval($data['days_offset']);
        }
        
        if (isset($data['message_template'])) {
            $update_data['message_template'] = sanitize_textarea_field($data['message_template']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = intval($data['status']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->scenarios_table,
            $update_data,
            array('id' => $scenario_id),
            array('%d', '%s', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Log SMS attempt
     * 
     * @param int $user_id
     * @param int $scenario_id
     * @param string $message
     * @param int $sent_status
     * @return bool
     */
    private function log_sms($user_id, $scenario_id, $message, $sent_status) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->logs_table,
            array(
                'user_id' => $user_id,
                'scenario_id' => $scenario_id,
                'message_text' => $message,
                'sent_status' => $sent_status
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Add to SMS queue
     * 
     * @param int $user_id
     * @param int $scenario_id
     * @param array $variables
     * @param string $scheduled_time
     * @return bool
     */
    public function add_to_queue($user_id, $scenario_id, $variables = array(), $scheduled_time = null) {
        global $wpdb;
        
        if (!$scheduled_time) {
            $scheduled_time = current_time('mysql');
        }
        
        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'user_id' => $user_id,
                'scenario_id' => $scenario_id,
                'scheduled_time' => $scheduled_time,
                'status' => 0 // queued
            ),
            array('%d', '%d', '%s', '%d')
        );
        
        if ($result !== false && !empty($variables)) {
            // Store variables in meta (would need meta table)
            $queue_id = $wpdb->insert_id;
            update_option("rolino_queue_variables_{$queue_id}", $variables);
        }
        
        return $result !== false;
    }
    
    /**
     * Process scheduled messages
     */
    public static function process_scheduled_messages() {
        global $wpdb;
        
        $sms = new self();
        
        // Get messages ready to send
        $messages = $wpdb->get_results(
            "SELECT * FROM {$sms->queue_table} 
             WHERE status = 0 
             AND scheduled_time <= NOW() 
             AND try_count < 3 
             ORDER BY scheduled_time ASC 
             LIMIT 50"
        );
        
        foreach ($messages as $message) {
            // Get stored variables
            $variables = get_option("rolino_queue_variables_{$message->id}", array());
            
            // Try to send
            $sent = $sms->send_sms($message->user_id, $message->scenario_id, $variables, false);
            
            // Update queue status
            $new_status = $sent ? 1 : 2; // 1=sent, 2=failed
            $try_count = $message->try_count + 1;
            
            $wpdb->update(
                $sms->queue_table,
                array(
                    'status' => $new_status,
                    'try_count' => $try_count
                ),
                array('id' => $message->id),
                array('%d', '%d'),
                array('%d')
            );
            
            // Clean up variables if sent or max tries reached
            if ($sent || $try_count >= 3) {
                delete_option("rolino_queue_variables_{$message->id}");
            }
        }
    }
    
    /**
     * Send SMS via API
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    private function send_sms_api($phone, $message) {
        $api_key = get_option('rolino_sms_api_key', '');
        $sender = get_option('rolino_sms_sender', '');
        
        if (empty($api_key) || empty($sender)) {
            return false;
        }
        
        // This is a placeholder - implement your SMS API integration here
        $result = apply_filters('rolino_send_sms_api', false, $phone, $message, $api_key, $sender);
        
        // Default implementation (replace with your SMS provider)
        if ($result === false) {
            // Example implementation - replace with actual SMS API
            $response = wp_remote_post('https://your-sms-api.com/send', array(
                'body' => array(
                    'api_key' => $api_key,
                    'sender' => $sender,
                    'receptor' => $phone,
                    'message' => $message
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            return isset($data['status']) && $data['status'] == 'success';
        }
        
        return $result;
    }
    
    /**
     * Get user phone number
     * 
     * @param int $user_id
     * @return string
     */
    private function get_user_phone($user_id) {
        // Try different meta keys for phone
        $phone_keys = array('phone', 'mobile', 'cell_phone', 'user_phone');
        
        foreach ($phone_keys as $key) {
            $phone = get_user_meta($user_id, $key, true);
            if (!empty($phone)) {
                return $this->format_phone_number($phone);
            }
        }
        
        return '';
    }
    
    /**
     * Format phone number
     * 
     * @param string $phone
     * @return string
     */
    private function format_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert to international format for Iran
        if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            $phone = '98' . substr($phone, 1);
        } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
            $phone = '98' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get SMS logs
     * 
     * @param array $args
     * @return array
     */
    public function get_sms_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => null,
            'scenario_id' => null,
            'sent_status' => 'all',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT l.*, s.scenario_type, u.display_name as user_name 
                FROM {$this->logs_table} l 
                LEFT JOIN {$this->scenarios_table} s ON l.scenario_id = s.id 
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID";
        
        $where_conditions = array();
        
        if ($args['user_id']) {
            $where_conditions[] = $wpdb->prepare("l.user_id = %d", $args['user_id']);
        }
        
        if ($args['scenario_id']) {
            $where_conditions[] = $wpdb->prepare("l.scenario_id = %d", $args['scenario_id']);
        }
        
        if ($args['sent_status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("l.sent_status = %d", $args['sent_status']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $sql .= " ORDER BY l.sent_at DESC";
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get scenario type names
     * 
     * @return array
     */
    public function get_scenario_type_names() {
        return array(
            1 => __('یادآوری پایان اشتراک', 'rolino'),
            2 => __('اطلاع‌رسانی پس از پایان اشتراک', 'rolino'),
            3 => __('یادآوری انقضای کد تخفیف', 'rolino'),
            4 => __('فعال شدن اشتراک اصلی', 'rolino'),
            5 => __('فعال شدن اشتراک رزرو', 'rolino')
        );
    }
}