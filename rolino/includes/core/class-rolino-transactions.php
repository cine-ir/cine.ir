<?php
/**
 * Rolino Transactions Core Class
 * 
 * Handles all transaction-related operations including payment processing and status management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Transactions {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'rolino_transactions';
        
        // Add payment callback handlers
        add_action('init', array($this, 'handle_payment_callbacks'));
        add_action('wp_ajax_rolino_verify_payment', array($this, 'verify_payment_ajax'));
        add_action('wp_ajax_nopriv_rolino_verify_payment', array($this, 'verify_payment_ajax'));
    }
    
    /**
     * Create new transaction
     * 
     * @param int $user_id
     * @param int $plan_id Plan ID (0 for single buy)
     * @param string $coupon_code
     * @param string $gateway
     * @param array $additional_data
     * @return array
     */
    public function create_transaction($user_id, $plan_id, $coupon_code = '', $gateway = 'zarinpal', $additional_data = array()) {
        global $wpdb;
        
        // Calculate amount
        $amount_data = $this->calculate_transaction_amount($plan_id, $coupon_code);
        
        if (isset($amount_data['error'])) {
            return array('success' => false, 'message' => $amount_data['error']);
        }
        
        // Handle single buy
        if ($plan_id == 0) {
            $single_buy_settings = $this->get_single_buy_settings();
            $amount_data['amount'] = $single_buy_settings['price'];
            $amount_data['description'] = sprintf(__('خرید تکی %d اعتبار', 'rolino'), $single_buy_settings['credits']);
        }
        
        // Create transaction record
        $transaction_data = array(
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'amount' => $amount_data['amount'],
            'gateway' => $gateway,
            'status' => 'pending',
            'description' => $amount_data['description']
        );
        
        $result = $wpdb->insert(
            $this->table_name,
            $transaction_data,
            array('%d', '%d', '%f', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('خطا در ایجاد تراکنش', 'rolino'));
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Store coupon code in meta if provided
        if (!empty($coupon_code)) {
            $this->add_transaction_meta($transaction_id, 'coupon_code', $coupon_code);
            $this->add_transaction_meta($transaction_id, 'discount_percent', $amount_data['discount_percent']);
            $this->add_transaction_meta($transaction_id, 'original_amount', $amount_data['original_amount']);
        }
        
        // Store additional data
        foreach ($additional_data as $key => $value) {
            $this->add_transaction_meta($transaction_id, $key, $value);
        }
        
        // Process payment through gateway
        $payment_result = $this->process_payment($transaction_id, $gateway);
        
        if ($payment_result['success']) {
            return array(
                'success' => true,
                'transaction_id' => $transaction_id,
                'redirect_url' => $payment_result['redirect_url'],
                'message' => __('در حال انتقال به درگاه پرداخت...', 'rolino')
            );
        } else {
            // Update transaction status to failed
            $this->update_transaction_status($transaction_id, 'failed');
            
            return array(
                'success' => false,
                'message' => $payment_result['message']
            );
        }
    }
    
    /**
     * Calculate transaction amount with discounts
     * 
     * @param int $plan_id
     * @param string $coupon_code
     * @return array
     */
    private function calculate_transaction_amount($plan_id, $coupon_code = '') {
        if ($plan_id == 0) {
            // Single buy - will be handled separately
            return array('amount' => 0, 'description' => '');
        }
        
        $plans = new Rolino_Plans();
        $plan = $plans->get_plan($plan_id);
        
        if (!$plan) {
            return array('error' => __('طرح مورد نظر یافت نشد', 'rolino'));
        }
        
        if ($plan->status != 1) {
            return array('error' => __('طرح مورد نظر فعال نیست', 'rolino'));
        }
        
        $original_amount = floatval($plan->price);
        $final_amount = $original_amount;
        $discount_percent = 0;
        
        // Apply coupon discount
        if (!empty($coupon_code)) {
            $coupons = new Rolino_Coupons();
            $coupon_result = $coupons->validate_coupon($coupon_code, $plan_id, get_current_user_id());
            
            if ($coupon_result['valid']) {
                $discount_percent = $coupon_result['discount_percent'];
                $final_amount = $original_amount * (1 - $discount_percent / 100);
            }
        }
        
        return array(
            'amount' => $final_amount,
            'original_amount' => $original_amount,
            'discount_percent' => $discount_percent,
            'description' => sprintf(__('خرید طرح %s', 'rolino'), $plan->plan_name)
        );
    }
    
    /**
     * Process payment through gateway
     * 
     * @param int $transaction_id
     * @param string $gateway
     * @return array
     */
    private function process_payment($transaction_id, $gateway) {
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction) {
            return array('success' => false, 'message' => __('تراکنش یافت نشد', 'rolino'));
        }
        
        // Load payment gateway
        $gateway_class = $this->get_gateway_class($gateway);
        
        if (!$gateway_class) {
            return array('success' => false, 'message' => __('درگاه پرداخت نامعتبر است', 'rolino'));
        }
        
        $gateway_instance = new $gateway_class();
        
        // Prepare payment data
        $payment_data = array(
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'callback_url' => $this->get_payment_callback_url($gateway),
            'transaction_id' => $transaction_id,
            'user_email' => get_userdata($transaction->user_id)->user_email ?? '',
            'user_phone' => get_user_meta($transaction->user_id, 'phone', true) ?? ''
        );
        
        // Process payment
        $result = $gateway_instance->process_payment($payment_data);
        
        if ($result['success']) {
            // Store gateway transaction ID
            $this->add_transaction_meta($transaction_id, 'gateway_transaction_id', $result['transaction_id']);
            $this->add_transaction_meta($transaction_id, 'gateway_data', json_encode($result['data']));
        }
        
        return $result;
    }
    
    /**
     * Verify payment
     * 
     * @param int $transaction_id
     * @param array $callback_data
     * @return bool
     */
    public function verify_payment($transaction_id, $callback_data) {
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction) {
            return false;
        }
        
        // Load payment gateway
        $gateway_class = $this->get_gateway_class($transaction->gateway);
        
        if (!$gateway_class) {
            return false;
        }
        
        $gateway_instance = new $gateway_class();
        
        // Verify payment
        $verification_result = $gateway_instance->verify_payment($callback_data, $transaction);
        
        if ($verification_result['success']) {
            // Update transaction status
            $this->update_transaction_status($transaction_id, 'completed');
            
            // Store verification data
            $this->add_transaction_meta($transaction_id, 'verification_data', json_encode($verification_result['data']));
            $this->add_transaction_meta($transaction_id, 'payment_date', current_time('mysql'));
            
            // Process successful payment
            $this->process_successful_payment($transaction_id);
            
            return true;
        } else {
            // Update transaction status
            $this->update_transaction_status($transaction_id, 'failed');
            
            // Store failure reason
            $this->add_transaction_meta($transaction_id, 'failure_reason', $verification_result['message']);
            
            return false;
        }
    }
    
    /**
     * Process successful payment
     * 
     * @param int $transaction_id
     */
    private function process_successful_payment($transaction_id) {
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction || $transaction->status !== 'completed') {
            return;
        }
        
        $credits = new Rolino_Credits();
        
        if ($transaction->plan_id > 0) {
            // Subscription purchase
            $plans = new Rolino_Plans();
            $plan = $plans->get_plan($transaction->plan_id);
            
            if ($plan) {
                $credits->add_credit(
                    $transaction->user_id,
                    $transaction->plan_id,
                    $plan->credits,
                    $plan->duration
                );
            }
        } else {
            // Single buy purchase
            $single_buy_settings = $this->get_single_buy_settings();
            
            $credits->add_credit(
                $transaction->user_id,
                0,
                $single_buy_settings['credits'],
                $single_buy_settings['duration']
            );
        }
        
        // Apply coupon if used
        $coupon_code = $this->get_transaction_meta($transaction_id, 'coupon_code');
        if (!empty($coupon_code)) {
            $coupons = new Rolino_Coupons();
            $coupon = $coupons->get_coupon_by_code($coupon_code);
            
            if ($coupon && $coupon->type == 3) {
                // For exclusive coupons, mark as used
                $coupons->activate_exclusive_coupon($coupon->id, $transaction->user_id);
            }
        }
        
        // Trigger hooks
        do_action('rolino_payment_completed', $transaction_id, $transaction);
        do_action('rolino_transaction_completed', $transaction_id);
        
        // Send completion email/SMS if configured
        $this->send_payment_confirmation($transaction_id);
    }
    
    /**
     * Handle payment callbacks
     */
    public function handle_payment_callbacks() {
        if (!isset($_GET['rolino_payment_callback'])) {
            return;
        }
        
        $gateway = sanitize_text_field($_GET['gateway'] ?? '');
        $transaction_id = intval($_GET['transaction_id'] ?? 0);
        
        if (!$gateway || !$transaction_id) {
            wp_die(__('پارامترهای پرداخت نامعتبر است', 'rolino'));
        }
        
        $verification_result = $this->verify_payment($transaction_id, $_GET);
        
        if ($verification_result) {
            $redirect_url = add_query_arg(
                array(
                    'payment_status' => 'success',
                    'transaction_id' => $transaction_id
                ),
                home_url('/payment-result/')
            );
        } else {
            $redirect_url = add_query_arg(
                array(
                    'payment_status' => 'failed',
                    'transaction_id' => $transaction_id
                ),
                home_url('/payment-result/')
            );
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Get transaction by ID
     * 
     * @param int $transaction_id
     * @return object|null
     */
    public function get_transaction($transaction_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $transaction_id)
        );
    }
    
    /**
     * Get transactions
     * 
     * @param array $args
     * @return array
     */
    public function get_transactions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => null,
            'status' => 'all',
            'gateway' => 'all',
            'plan_id' => null,
            'date_from' => null,
            'date_to' => null,
            'orderby' => 'transaction_date',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT t.*, p.plan_name, u.display_name as user_name 
                FROM {$this->table_name} t 
                LEFT JOIN {$wpdb->prefix}rolino_plans p ON t.plan_id = p.id 
                LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID";
        
        $where_conditions = array();
        
        if ($args['user_id']) {
            $where_conditions[] = $wpdb->prepare("t.user_id = %d", $args['user_id']);
        }
        
        if ($args['status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("t.status = %s", $args['status']);
        }
        
        if ($args['gateway'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("t.gateway = %s", $args['gateway']);
        }
        
        if ($args['plan_id'] !== null) {
            $where_conditions[] = $wpdb->prepare("t.plan_id = %d", $args['plan_id']);
        }
        
        if ($args['date_from']) {
            $where_conditions[] = $wpdb->prepare("DATE(t.transaction_date) >= %s", $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where_conditions[] = $wpdb->prepare("DATE(t.transaction_date) <= %s", $args['date_to']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $sql .= $wpdb->prepare(" ORDER BY t.%s %s LIMIT %d OFFSET %d", 
            $args['orderby'], $args['order'], $args['limit'], $args['offset']);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Update transaction status
     * 
     * @param int $transaction_id
     * @param string $status
     * @return bool
     */
    public function update_transaction_status($transaction_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('id' => $transaction_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('rolino_transaction_status_updated', $transaction_id, $status);
            return true;
        }
        
        return false;
    }
    
    /**
     * Add transaction meta
     * 
     * @param int $transaction_id
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function add_transaction_meta($transaction_id, $key, $value) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rolino_transaction_meta';
        
        // Create meta table if it doesn't exist
        $this->maybe_create_meta_table();
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'transaction_id' => $transaction_id,
                'meta_key' => $key,
                'meta_value' => maybe_serialize($value)
            ),
            array('%d', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get transaction meta
     * 
     * @param int $transaction_id
     * @param string $key
     * @return mixed
     */
    public function get_transaction_meta($transaction_id, $key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rolino_transaction_meta';
        
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$table_name} WHERE transaction_id = %d AND meta_key = %s",
                $transaction_id,
                $key
            )
        );
        
        return maybe_unserialize($value);
    }
    
    /**
     * Get gateway class name
     * 
     * @param string $gateway
     * @return string|false
     */
    private function get_gateway_class($gateway) {
        $gateways = array(
            'zarinpal' => 'ZarrinPalGateway',
            'sample' => 'SampleGateway'
        );
        
        return isset($gateways[$gateway]) ? $gateways[$gateway] : false;
    }
    
    /**
     * Get payment callback URL
     * 
     * @param string $gateway
     * @return string
     */
    private function get_payment_callback_url($gateway) {
        return add_query_arg(
            array(
                'rolino_payment_callback' => '1',
                'gateway' => $gateway
            ),
            home_url('/')
        );
    }
    
    /**
     * Get single buy settings
     * 
     * @return array
     */
    private function get_single_buy_settings() {
        return array(
            'duration' => intval(get_option('rolino_single_buy_duration', 30)),
            'credits' => intval(get_option('rolino_single_buy_credits', 5)),
            'price' => floatval(get_option('rolino_single_buy_price', 10000)),
            'active' => intval(get_option('rolino_single_buy_active', 1))
        );
    }
    
    /**
     * Send payment confirmation
     * 
     * @param int $transaction_id
     */
    private function send_payment_confirmation($transaction_id) {
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction) {
            return;
        }
        
        // Trigger SMS or email confirmation
        do_action('rolino_send_payment_confirmation', $transaction_id, $transaction);
    }
    
    /**
     * Maybe create meta table
     */
    private function maybe_create_meta_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rolino_transaction_meta';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            UNIQUE KEY unique_meta (transaction_id, meta_key)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX verify payment handler
     */
    public function verify_payment_ajax() {
        check_ajax_referer('rolino_frontend_nonce', 'nonce');
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        
        if (!$transaction_id) {
            wp_send_json_error(array('message' => __('شناسه تراکنش نامعتبر است', 'rolino')));
        }
        
        $transaction = $this->get_transaction($transaction_id);
        
        if (!$transaction) {
            wp_send_json_error(array('message' => __('تراکنش یافت نشد', 'rolino')));
        }
        
        wp_send_json_success(array(
            'status' => $transaction->status,
            'message' => $this->get_status_message($transaction->status)
        ));
    }
    
    /**
     * Get status message
     * 
     * @param string $status
     * @return string
     */
    private function get_status_message($status) {
        $messages = array(
            'pending' => __('در انتظار پرداخت', 'rolino'),
            'completed' => __('پرداخت با موفقیت انجام شد', 'rolino'),
            'failed' => __('پرداخت ناموفق', 'rolino')
        );
        
        return isset($messages[$status]) ? $messages[$status] : __('وضعیت نامشخص', 'rolino');
    }
    
    /**
     * Get transaction statistics
     * 
     * @return array
     */
    public function get_transaction_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total transactions
        $stats['total_transactions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Completed transactions
        $stats['completed_transactions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'");
        
        // Total revenue
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$this->table_name} WHERE status = 'completed'") ?: 0;
        
        // Today's revenue
        $stats['today_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$this->table_name} WHERE status = 'completed' AND DATE(transaction_date) = CURDATE()") ?: 0;
        
        // This month's revenue
        $stats['month_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$this->table_name} WHERE status = 'completed' AND MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())") ?: 0;
        
        return $stats;
    }
}