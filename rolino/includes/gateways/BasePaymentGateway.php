<?php
/**
 * Base Payment Gateway Class
 * 
 * Provides common functionality for payment gateways
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class BasePaymentGateway implements PaymentGatewayInterface {
    
    protected $id;
    protected $name;
    protected $settings;
    
    public function __construct() {
        $this->init();
        $this->load_settings();
    }
    
    /**
     * Initialize gateway
     */
    abstract protected function init();
    
    /**
     * Load gateway settings
     */
    protected function load_settings() {
        $this->settings = get_option("rolino_gateway_{$this->id}_settings", array());
    }
    
    /**
     * Save gateway settings
     * 
     * @param array $settings
     */
    protected function save_settings($settings) {
        update_option("rolino_gateway_{$this->id}_settings", $settings);
        $this->settings = $settings;
    }
    
    /**
     * Get gateway ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get gateway name
     * 
     * @return string
     */
    public function get_name() {
        return $this->name;
    }
    
    /**
     * Get gateway settings
     * 
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get specific setting value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Check if gateway is available
     * 
     * @return bool
     */
    public function is_available() {
        return $this->get_setting('enabled', false) && $this->validate_settings($this->settings);
    }
    
    /**
     * Format amount for gateway
     * 
     * @param float $amount
     * @return int Amount in smallest currency unit (e.g., cents)
     */
    protected function format_amount($amount) {
        return intval($amount * 10); // Convert Toman to Rial
    }
    
    /**
     * Generate transaction reference
     * 
     * @param int $transaction_id
     * @return string
     */
    protected function generate_reference($transaction_id) {
        return 'ROL_' . $transaction_id . '_' . time();
    }
    
    /**
     * Log gateway activity
     * 
     * @param string $message
     * @param string $level
     * @param array $context
     */
    protected function log($message, $level = 'info', $context = array()) {
        if ($this->get_setting('debug', false)) {
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'gateway' => $this->id,
                'level' => $level,
                'message' => $message,
                'context' => $context
            );
            
            $log_file = ROLINO_PLUGIN_PATH . 'logs/gateway-' . $this->id . '.log';
            
            // Ensure logs directory exists
            wp_mkdir_p(dirname($log_file));
            
            file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Make HTTP request
     * 
     * @param string $url
     * @param array $data
     * @param string $method
     * @param array $headers
     * @return array|WP_Error
     */
    protected function make_request($url, $data = array(), $method = 'POST', $headers = array()) {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array_merge(array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ), $headers)
        );
        
        if (!empty($data)) {
            if ($method === 'POST') {
                $args['body'] = json_encode($data);
            } else {
                $url = add_query_arg($data, $url);
            }
        }
        
        $this->log("Making {$method} request to {$url}", 'debug', array('data' => $data));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log("Request failed: " . $response->get_error_message(), 'error');
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        $this->log("Response received", 'debug', array(
            'code' => $code,
            'body' => $body
        ));
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("Invalid JSON response", 'error', array('body' => $body));
            return new WP_Error('invalid_json', 'Invalid JSON response from gateway');
        }
        
        return array(
            'code' => $code,
            'data' => $data
        );
    }
    
    /**
     * Validate required settings
     * 
     * @param array $settings
     * @param array $required_keys
     * @return bool
     */
    protected function validate_required_settings($settings, $required_keys) {
        foreach ($required_keys as $key) {
            if (empty($settings[$key])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get callback URL
     * 
     * @param int $transaction_id
     * @return string
     */
    protected function get_callback_url($transaction_id) {
        return add_query_arg(array(
            'rolino_payment_callback' => '1',
            'gateway' => $this->id,
            'transaction_id' => $transaction_id
        ), home_url('/'));
    }
    
    /**
     * Parse callback data
     * 
     * @param array $callback_data
     * @return array
     */
    protected function parse_callback_data($callback_data) {
        // Default implementation - can be overridden by specific gateways
        return $callback_data;
    }
    
    /**
     * Get error message from response
     * 
     * @param array $response
     * @return string
     */
    protected function get_error_message($response) {
        // Default implementation - can be overridden by specific gateways
        if (isset($response['data']['message'])) {
            return $response['data']['message'];
        }
        
        if (isset($response['data']['error'])) {
            return $response['data']['error'];
        }
        
        return __('خطای ناشناخته در درگاه پرداخت', 'rolino');
    }
    
    /**
     * Format currency amount for display
     * 
     * @param float $amount
     * @return string
     */
    protected function format_currency($amount) {
        return number_format($amount, 0, '', ',') . ' ' . __('تومان', 'rolino');
    }
    
    /**
     * Sanitize gateway settings
     * 
     * @param array $settings
     * @return array
     */
    protected function sanitize_settings($settings) {
        $sanitized = array();
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'enabled':
                case 'debug':
                case 'test_mode':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                case 'api_key':
                case 'secret_key':
                case 'merchant_id':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                    
                case 'title':
                case 'description':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get default settings
     * 
     * @return array
     */
    protected function get_default_settings() {
        return array(
            'enabled' => false,
            'debug' => false,
            'test_mode' => true,
            'title' => $this->name,
            'description' => ''
        );
    }
    
    /**
     * Process refund (if supported)
     * 
     * @param object $transaction
     * @param float $amount
     * @return array
     */
    public function process_refund($transaction, $amount = null) {
        return array(
            'success' => false,
            'message' => __('این درگاه از بازگشت وجه پشتیبانی نمی‌کند', 'rolino')
        );
    }
}