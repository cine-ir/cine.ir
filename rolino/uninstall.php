<?php
/**
 * Uninstall Rolino Plugin
 * 
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'rolino_sms_queue',
    $wpdb->prefix . 'rolino_sms_logs',
    $wpdb->prefix . 'rolino_sms_scenarios',
    $wpdb->prefix . 'rolino_coupon_user_activations',
    $wpdb->prefix . 'rolino_coupon_plan_discounts',
    $wpdb->prefix . 'rolino_coupons',
    $wpdb->prefix . 'rolino_credits',
    $wpdb->prefix . 'rolino_transactions',
    $wpdb->prefix . 'rolino_plan_group_items',
    $wpdb->prefix . 'rolino_plan_groups',
    $wpdb->prefix . 'rolino_plans'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Delete plugin options
$options = array(
    'rolino_db_version',
    'rolino_activated',
    'rolino_single_buy_duration',
    'rolino_single_buy_credits',
    'rolino_single_buy_active',
    'rolino_sms_enabled',
    'rolino_sms_api_key',
    'rolino_sms_sender'
);

foreach ($options as $option) {
    delete_option($option);
}

// Clear scheduled events
wp_clear_scheduled_hook('rolino_sms_cron');

// Clear any cached data
wp_cache_flush();