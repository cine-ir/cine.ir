<?php
/**
 * Payment Gateway Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PaymentGatewayInterface {
    
    /**
     * Process payment
     * 
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data);
    
    /**
     * Verify payment
     * 
     * @param array $callback_data
     * @param object $transaction
     * @return array
     */
    public function verify_payment($callback_data, $transaction);
}