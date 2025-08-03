<?php
/**
 * Plugin Name: Rolino - Advanced Subscription & Credit System
 * Plugin URI: https://cine.ir
 * Description: پلاگین پیشرفته مدیریت اشتراک و اعتبار با امکانات کامل گروه‌بندی، کد تخفیف و SMS
 * Version: 2.0.0
 * Author: Cinema.ir Team
 * Author URI: https://cine.ir
 * Text Domain: rolino
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ROLINO_VERSION', '2.0.0');
define('ROLINO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ROLINO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ROLINO_PLUGIN_FILE', __FILE__);

// Main Rolino class
class Rolino {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('init', array($this, 'initialize'));
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('rolino', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        $this->create_database_tables();
        $this->set_default_options();
        
        // Set activation flag
        update_option('rolino_activated', true);
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('rolino_sms_cron');
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    public function initialize() {
        // Create database tables if they don't exist
        $this->create_database_tables();
        
        // Set default options if they don't exist
        $this->set_default_options();
        
        // Load required files
        $this->load_dependencies();
        
        // Initialize core classes
        $this->init_core_classes();
        
        // Initialize admin
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize frontend
        $this->init_frontend();
        
        // Setup cron jobs
        $this->setup_cron_jobs();
    }
    
    private function load_dependencies() {
        // Core classes
        require_once ROLINO_PLUGIN_PATH . 'includes/core/class-rolino-plans.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/core/class-rolino-credits.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/core/class-rolino-coupons.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/core/class-rolino-transactions.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/core/class-rolino-sms.php';
        
        // Payment gateways
        require_once ROLINO_PLUGIN_PATH . 'includes/gateways/PaymentGatewayInterface.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/gateways/BasePaymentGateway.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/gateways/SampleGateway.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/gateways/ZarrinPalGateway.php';
        
        // Ensure gateway classes are loaded
        if (!class_exists('ZarrinPalGateway')) {
            require_once ROLINO_PLUGIN_PATH . 'includes/gateways/ZarrinPalGateway.php';
        }
        if (!class_exists('SampleGateway')) {
            require_once ROLINO_PLUGIN_PATH . 'includes/gateways/SampleGateway.php';
        }
        
        // Admin classes
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-admin-menu.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-plans-admin.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-coupons-admin.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-members-admin.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-sms-admin.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/admin/class-rolino-revenue-admin.php';
        
        // Public classes
        require_once ROLINO_PLUGIN_PATH . 'includes/public/class-rolino-shortcodes.php';
        require_once ROLINO_PLUGIN_PATH . 'includes/public/class-rolino-frontend.php';
    }
    
    private function init_core_classes() {
        new Rolino_Plans();
        new Rolino_Credits();
        new Rolino_Coupons();
        new Rolino_Transactions();
        new Rolino_SMS();
    }
    
    private function init_admin() {
        new Rolino_Admin_Menu();
        new Rolino_Plans_Admin();
        new Rolino_Coupons_Admin();
        new Rolino_Members_Admin();
        new Rolino_SMS_Admin();
        new Rolino_Revenue_Admin();
    }
    
    private function init_frontend() {
        new Rolino_Shortcodes();
        new Rolino_Frontend();
    }
    
    private function setup_cron_jobs() {
        if (!wp_next_scheduled('rolino_sms_cron')) {
            wp_schedule_event(time(), 'hourly', 'rolino_sms_cron');
        }
        
        add_action('rolino_sms_cron', array('Rolino_SMS', 'process_scheduled_messages'));
    }
    
    public function admin_enqueue_scripts($hook) {
        // Only load on Rolino admin pages
        if (strpos($hook, 'rolino') === false) {
            return;
        }
        
        wp_enqueue_style('rolino-admin-css', ROLINO_PLUGIN_URL . 'assets/css/menus.css', array(), ROLINO_VERSION);
        wp_enqueue_script('rolino-alpine', ROLINO_PLUGIN_URL . 'assets/js/alpine.min.js', array(), ROLINO_VERSION, true);
        wp_enqueue_script('rolino-admin-js', ROLINO_PLUGIN_URL . 'assets/js/menus.js', array('jquery'), ROLINO_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('rolino-admin-js', 'rolino_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rolino_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟', 'rolino'),
                'success' => __('عملیات با موفقیت انجام شد', 'rolino'),
                'error' => __('خطایی در انجام عملیات رخ داد', 'rolino')
            )
        ));
    }
    
    public function frontend_enqueue_scripts() {
        wp_enqueue_style('rolino-frontend-css', ROLINO_PLUGIN_URL . 'assets/css/menus.css', array(), ROLINO_VERSION);
        wp_enqueue_script('rolino-frontend-js', ROLINO_PLUGIN_URL . 'assets/js/menus.js', array('jquery'), ROLINO_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('rolino-frontend-js', 'rolino_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rolino_frontend_nonce'),
            'strings' => array(
                'processing' => __('در حال پردازش...', 'rolino'),
                'error' => __('خطایی رخ داد', 'rolino')
            )
        ));
    }
    
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Plans table
        $plans_table = $wpdb->prefix . 'rolino_plans';
        $plans_sql = "CREATE TABLE $plans_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            plan_name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            credits int(11) NOT NULL,
            duration int(11) NOT NULL,
            active_sessions int(11) DEFAULT 1,
            status tinyint(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Credits table
        $credits_table = $wpdb->prefix . 'rolino_credits';
        $credits_sql = "CREATE TABLE $credits_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plan_id mediumint(9) DEFAULT 0,
            credit int(11) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Transactions table
        $transactions_table = $wpdb->prefix . 'rolino_transactions';
        $transactions_sql = "CREATE TABLE $transactions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plan_id mediumint(9) DEFAULT 0,
            amount decimal(10,2) NOT NULL,
            gateway varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            transaction_id varchar(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Transaction meta table
        $transaction_meta_table = $wpdb->prefix . 'rolino_transaction_meta';
        $transaction_meta_sql = "CREATE TABLE $transaction_meta_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_id mediumint(9) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (id),
            KEY transaction_id (transaction_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        // Coupons table
        $coupons_table = $wpdb->prefix . 'rolino_coupons';
        $coupons_sql = "CREATE TABLE $coupons_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            type tinyint(1) DEFAULT 1,
            duration_days int(11) NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        // Coupon plan discounts table
        $coupon_discounts_table = $wpdb->prefix . 'rolino_coupon_plan_discounts';
        $coupon_discounts_sql = "CREATE TABLE $coupon_discounts_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            coupon_id mediumint(9) NOT NULL,
            plan_id mediumint(9) NOT NULL,
            discount_percent int(11) NOT NULL,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY plan_id (plan_id)
        ) $charset_collate;";
        
        // Coupon single buy discounts table
        $coupon_single_buy_table = $wpdb->prefix . 'rolino_coupon_single_buy_discounts';
        $coupon_single_buy_sql = "CREATE TABLE $coupon_single_buy_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            coupon_id mediumint(9) NOT NULL,
            discount_percent int(11) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY coupon_id (coupon_id)
        ) $charset_collate;";
        
        // Coupon user activations table
        $coupon_activations_table = $wpdb->prefix . 'rolino_coupon_user_activations';
        $coupon_activations_sql = "CREATE TABLE $coupon_activations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            coupon_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // SMS scenarios table
        $sms_scenarios_table = $wpdb->prefix . 'rolino_sms_scenarios';
        $sms_scenarios_sql = "CREATE TABLE $sms_scenarios_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            scenario_name varchar(255) NOT NULL,
            template text NOT NULL,
            variables text,
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Plan groups table
        $plan_groups_table = $wpdb->prefix . 'rolino_plan_groups';
        $plan_groups_sql = "CREATE TABLE $plan_groups_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            description text,
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Plan group relationships table
        $plan_group_relations_table = $wpdb->prefix . 'rolino_plan_group_relations';
        $plan_group_relations_sql = "CREATE TABLE $plan_group_relations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_id mediumint(9) NOT NULL,
            plan_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY group_plan (group_id, plan_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($plans_sql);
        dbDelta($credits_sql);
        dbDelta($transactions_sql);
        dbDelta($transaction_meta_sql);
        dbDelta($coupons_sql);
        dbDelta($coupon_discounts_sql);
        dbDelta($coupon_single_buy_sql);
        dbDelta($coupon_activations_sql);
        dbDelta($sms_scenarios_sql);
        dbDelta($plan_groups_sql);
        dbDelta($plan_group_relations_sql);
    }
    
    private function update_existing_tables() {
        global $wpdb;
        
        // Update rolino_coupons table
        $coupons_table = $wpdb->prefix . 'rolino_coupons';
        
        // Check if start_date column exists
        $start_date_exists = $wpdb->get_var("SHOW COLUMNS FROM $coupons_table LIKE 'start_date'");
        if (!$start_date_exists) {
            $wpdb->query("ALTER TABLE $coupons_table ADD COLUMN start_date DATETIME NOT NULL AFTER duration_days");
        }
        
        // Check if end_date column exists
        $end_date_exists = $wpdb->get_var("SHOW COLUMNS FROM $coupons_table LIKE 'end_date'");
        if (!$end_date_exists) {
            $wpdb->query("ALTER TABLE $coupons_table ADD COLUMN end_date DATETIME NOT NULL AFTER start_date");
        }
        
        // Update existing coupons with default dates
        $wpdb->query("UPDATE $coupons_table SET start_date = created_at, end_date = DATE_ADD(created_at, INTERVAL duration_days DAY) WHERE start_date = '0000-00-00 00:00:00' OR end_date = '0000-00-00 00:00:00'");
    }
    
    private function insert_default_sms_scenarios() {
        global $wpdb;
        
        // Check if scenarios already exist
        $scenarios_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rolino_sms_scenarios");
        if ($scenarios_count > 0) {
            return; // Scenarios already exist
        }
        
        $default_scenarios = array(
            array(
                'scenario_type' => 1,
                'days_offset' => 3,
                'message_template' => 'سلام! اشتراک شما با نام {{plan_name}} تنها {{remaining_time}} روز دیگر اعتبار دارد.',
                'status' => 1
            ),
            array(
                'scenario_type' => 2,
                'days_offset' => 1,
                'message_template' => 'اشتراک شما منقضی شده است اما ما سورپرایز داریم! {{add_remaining_time=5}} فعال شد.',
                'status' => 1
            ),
            array(
                'scenario_type' => 3,
                'days_offset' => 2,
                'message_template' => 'کدهای تخفیف شما {{off_code}} تا {{off_code_remaining_time}} روز اعتبار دارند.',
                'status' => 1
            ),
            array(
                'scenario_type' => 4,
                'days_offset' => null,
                'message_template' => 'اشتراک {{plan_name}} شما فعال شد. شما {{remaining_time}} روز فرصت دارید.',
                'status' => 1
            ),
            array(
                'scenario_type' => 5,
                'days_offset' => null,
                'message_template' => 'اشتراک رزرو {{reserve_plan_name}} ثبت شد و بعد از پایان اشتراک فعلی به مدت {{reserve_remaining_time}} روز فعال می‌شود.',
                'status' => 1
            )
        );
        
        foreach ($default_scenarios as $scenario) {
            $wpdb->insert(
                $wpdb->prefix . 'rolino_sms_scenarios',
                $scenario
            );
        }
    }
    
    private function set_default_options() {
        // Single buy settings
        if (get_option('rolino_single_buy_duration') === false) {
            add_option('rolino_single_buy_duration', 30);
        }
        if (get_option('rolino_single_buy_credits') === false) {
            add_option('rolino_single_buy_credits', 5);
        }
        if (get_option('rolino_single_buy_active') === false) {
            add_option('rolino_single_buy_active', 1);
        }
        
        // SMS settings
        if (get_option('rolino_sms_enabled') === false) {
            add_option('rolino_sms_enabled', 1);
        }
        if (get_option('rolino_sms_api_key') === false) {
            add_option('rolino_sms_api_key', '');
        }
        if (get_option('rolino_sms_sender') === false) {
            add_option('rolino_sms_sender', '');
        }
    }
}

// Initialize the plugin
function rolino_init() {
    return Rolino::getInstance();
}

// Start the plugin
add_action('plugins_loaded', 'rolino_init');

// AJAX handlers for frontend
add_action('wp_ajax_rolino_apply_coupon', 'rolino_apply_coupon_ajax');
add_action('wp_ajax_nopriv_rolino_apply_coupon', 'rolino_apply_coupon_ajax');

function rolino_apply_coupon_ajax() {
    check_ajax_referer('rolino_frontend_nonce', 'nonce');
    
    $coupon_code = sanitize_text_field($_POST['coupon_code']);
    $plan_id = intval($_POST['plan_id']);
    
    $coupons = new Rolino_Coupons();
    $result = $coupons->validate_coupon($coupon_code, $plan_id);
    
    wp_send_json($result);
}

// AJAX handlers for buying plans
add_action('wp_ajax_rolino_buy_plan', 'rolino_buy_plan_ajax');
add_action('wp_ajax_nopriv_rolino_buy_plan', 'rolino_buy_plan_ajax');

function rolino_buy_plan_ajax() {
    check_ajax_referer('rolino_frontend_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('برای خرید باید وارد حساب کاربری خود شوید', 'rolino')));
        return;
    }
    
    $plan_id = intval($_POST['plan_id']);
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $gateway = sanitize_text_field($_POST['gateway'] ?? 'zarinpal');
    
    try {
        $transactions = new Rolino_Transactions();
        $result = $transactions->create_transaction(get_current_user_id(), $plan_id, $coupon_code, $gateway);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}