<?php
/**
 * Payment Gateway Interface
 * 
 * Defines the contract that all payment gateways must implement
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PaymentGatewayInterface {
    
    /**
     * Process payment request
     * 
     * @param array $payment_data Payment information
     * @return array Result with success status and redirect URL or error message
     */
    public function process_payment($payment_data);
    
    /**
     * Verify payment callback
     * 
     * @param array $callback_data Callback data from payment gateway
     * @param object $transaction Transaction object
     * @return array Result with success status and verification data
     */
    public function verify_payment($callback_data, $transaction);
    
    /**
     * Get gateway display name
     * 
     * @return string
     */
    public function get_name();
    
    /**
     * Get gateway ID
     * 
     * @return string
     */
    public function get_id();
    
    /**
     * Check if gateway is available
     * 
     * @return bool
     */
    public function is_available();
    
    /**
     * Get gateway settings
     * 
     * @return array
     */
    public function get_settings();
    
    /**
     * Validate gateway settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_settings($settings);
}