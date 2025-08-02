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
        
        // Check if tables already exist
        $plans_table = $wpdb->prefix . 'rolino_plans';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$plans_table'") == $plans_table;
        
        if ($table_exists) {
            return; // Tables already exist
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table definitions
        $tables = array();
        
        // 1. rolino_plans
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_name VARCHAR(255) NOT NULL,
            duration INT NOT NULL COMMENT 'Duration in days',
            price DECIMAL(10,2) NOT NULL,
            credits INT NOT NULL DEFAULT 0,
            active_sessions INT NOT NULL DEFAULT 1,
            status TINYINT NOT NULL DEFAULT 1 COMMENT '0=inactive, 1=active'
        ) $charset_collate;";
        
        // 2. rolino_plan_groups
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_plan_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(255) NOT NULL
        ) $charset_collate;";
        
        // 3. rolino_plan_group_items
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_plan_group_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            plan_id INT NOT NULL,
            FOREIGN KEY (group_id) REFERENCES {$wpdb->prefix}rolino_plan_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES {$wpdb->prefix}rolino_plans(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 4. rolino_transactions
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL DEFAULT 0 COMMENT '0=single buy',
            amount DECIMAL(10,2) NOT NULL,
            gateway VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/completed/failed',
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        // 5. rolino_credits
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_credits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_id INT NOT NULL DEFAULT 0 COMMENT '0=single buy',
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            credit INT NOT NULL DEFAULT 0 COMMENT 'Credit amount'
        ) $charset_collate;";
        
        // 6. rolino_coupons
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(8) NOT NULL UNIQUE COMMENT '4-8 characters',
            type TINYINT NOT NULL COMMENT '1=global, 2=public, 3=exclusive',
            duration_days INT NOT NULL COMMENT 'Duration in days',
            status TINYINT NOT NULL DEFAULT 1 COMMENT '0=inactive, 1=active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        // 7. rolino_coupon_plan_discounts
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_coupon_plan_discounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT NOT NULL,
            plan_id INT NOT NULL DEFAULT 0 COMMENT '0=single buy',
            discount_percent TINYINT NOT NULL,
            FOREIGN KEY (coupon_id) REFERENCES {$wpdb->prefix}rolino_coupons(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 8. rolino_coupon_user_activations
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_coupon_user_activations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT NOT NULL,
            user_id INT NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            FOREIGN KEY (coupon_id) REFERENCES {$wpdb->prefix}rolino_coupons(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 9. rolino_sms_scenarios
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_sms_scenarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scenario_type TINYINT NOT NULL COMMENT '1-5 scenarios',
            days_offset INT NULL COMMENT 'null for immediate scenarios',
            message_template TEXT NOT NULL COMMENT 'Includes variables',
            status TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        // 10. rolino_sms_logs
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scenario_id INT NOT NULL,
            message_text TEXT NOT NULL COMMENT 'After variable replacement',
            sent_status TINYINT NOT NULL COMMENT '0=fail, 1=success',
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (scenario_id) REFERENCES {$wpdb->prefix}rolino_sms_scenarios(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 11. rolino_sms_queue (optional)
        $tables[] = "CREATE TABLE {$wpdb->prefix}rolino_sms_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scenario_id INT NOT NULL,
            scheduled_time DATETIME NOT NULL,
            status TINYINT NOT NULL DEFAULT 0 COMMENT '0=queued, 1=sent, 2=failed',
            try_count INT NOT NULL DEFAULT 0,
            FOREIGN KEY (scenario_id) REFERENCES {$wpdb->prefix}rolino_sms_scenarios(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table) {
            dbDelta($table);
        }
        
        // Insert default SMS scenarios
        $this->insert_default_sms_scenarios();
        
        // Update database version
        update_option('rolino_db_version', ROLINO_VERSION);
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
    
    $transactions = new Rolino_Transactions();
    $result = $transactions->create_transaction(get_current_user_id(), $plan_id, $coupon_code, $gateway);
    
    wp_send_json($result);
}