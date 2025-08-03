<?php
/**
 * Base Payment Gateway Class
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
    }
    
    /**
     * Initialize gateway
     */
    abstract protected function init();
    
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
     * Check if gateway is available
     * 
     * @return bool
     */
    public function is_available() {
        return true;
    }
}