<?php
/**
 * Rolino Revenue Admin Class
 * 
 * Handles revenue management and statistics in admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Revenue_Admin {
    
    public function __construct() {
        add_action('wp_ajax_rolino_get_revenue_stats', array($this, 'ajax_get_revenue_stats'));
        add_action('wp_ajax_rolino_get_revenue_chart', array($this, 'ajax_get_revenue_chart'));
    }
    
    /**
     * Display revenue page
     */
    public function display_page() {
        include ROLINO_PLUGIN_PATH . 'templates/admin/revenue-main.php';
    }
    

    
    /**
     * Get revenue statistics
     * 
     * @param string $period
     * @return array
     */
    public function get_revenue_stats($period = 'today') {
        global $wpdb;
        
        $date_range = $this->get_date_range($period);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        
        // Get completed transactions
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_transactions,
                SUM(amount) as total_revenue,
                AVG(amount) as avg_transaction,
                COUNT(DISTINCT user_id) as unique_customers
             FROM {$wpdb->prefix}rolino_transactions 
             WHERE status = 'completed' 
             AND created_at >= %s 
             AND created_at <= %s",
            $start_date,
            $end_date
        );
        
        $stats = $wpdb->get_row($sql);
        
        // Get revenue by plan
        $plan_revenue_sql = $wpdb->prepare(
            "SELECT 
                p.plan_name,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_amount
             FROM {$wpdb->prefix}rolino_transactions t
             LEFT JOIN {$wpdb->prefix}rolino_plans p ON t.plan_id = p.id
             WHERE t.status = 'completed' 
             AND t.created_at >= %s 
             AND t.created_at <= %s
             GROUP BY t.plan_id
             ORDER BY total_amount DESC",
            $start_date,
            $end_date
        );
        
        $plan_revenue = $wpdb->get_results($plan_revenue_sql);
        
        // Get revenue by gateway
        $gateway_revenue_sql = $wpdb->prepare(
            "SELECT 
                gateway,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
             FROM {$wpdb->prefix}rolino_transactions 
             WHERE status = 'completed' 
             AND created_at >= %s 
             AND created_at <= %s
             GROUP BY gateway
             ORDER BY total_amount DESC",
            $start_date,
            $end_date
        );
        
        $gateway_revenue = $wpdb->get_results($gateway_revenue_sql);
        
        return array(
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_transactions' => intval($stats->total_transactions ?? 0),
            'total_revenue' => floatval($stats->total_revenue ?? 0),
            'avg_transaction' => floatval($stats->avg_transaction ?? 0),
            'unique_customers' => intval($stats->unique_customers ?? 0),
            'plan_revenue' => $plan_revenue,
            'gateway_revenue' => $gateway_revenue
        );
    }
    
    /**
     * Get revenue chart data
     * 
     * @param string $period
     * @return array
     */
    public function get_revenue_chart_data($period = 'today') {
        global $wpdb;
        
        $date_range = $this->get_date_range($period);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        
        switch ($period) {
            case 'today':
                return $this->get_hourly_revenue($start_date, $end_date);
            case 'yesterday':
            case 'day_before_yesterday':
                return $this->get_hourly_revenue($start_date, $end_date);
            case 'this_week':
            case 'last_week':
            case 'week_before_last':
                return $this->get_daily_revenue($start_date, $end_date);
            case 'this_month':
            case 'last_month':
            case 'month_before_last':
                return $this->get_daily_revenue($start_date, $end_date);
            case 'this_year':
            case 'last_year':
            case 'year_before_last':
                return $this->get_monthly_revenue($start_date, $end_date);
            default:
                return $this->get_hourly_revenue($start_date, $end_date);
        }
    }
    
    /**
     * Get hourly revenue
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    private function get_hourly_revenue($start_date, $end_date) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as transactions,
                SUM(amount) as revenue
             FROM {$wpdb->prefix}rolino_transactions 
             WHERE status = 'completed' 
             AND created_at >= %s 
             AND created_at <= %s
             GROUP BY HOUR(created_at)
             ORDER BY hour",
            $start_date,
            $end_date
        );
        
        $results = $wpdb->get_results($sql);
        
        $chart_data = array();
        for ($i = 0; $i < 24; $i++) {
            $chart_data[] = array(
                'label' => sprintf('%02d:00', $i),
                'transactions' => 0,
                'revenue' => 0
            );
        }
        
        foreach ($results as $result) {
            $chart_data[$result->hour]['transactions'] = intval($result->transactions);
            $chart_data[$result->hour]['revenue'] = floatval($result->revenue);
        }
        
        return $chart_data;
    }
    
    /**
     * Get daily revenue
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    private function get_daily_revenue($start_date, $end_date) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as transactions,
                SUM(amount) as revenue
             FROM {$wpdb->prefix}rolino_transactions 
             WHERE status = 'completed' 
             AND created_at >= %s 
             AND created_at <= %s
             GROUP BY DATE(created_at)
             ORDER BY date",
            $start_date,
            $end_date
        );
        
        $results = $wpdb->get_results($sql);
        
        $chart_data = array();
        foreach ($results as $result) {
            $chart_data[] = array(
                'label' => date_i18n('Y/m/d', strtotime($result->date)),
                'transactions' => intval($result->transactions),
                'revenue' => floatval($result->revenue)
            );
        }
        
        return $chart_data;
    }
    
    /**
     * Get monthly revenue
     * 
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    private function get_monthly_revenue($start_date, $end_date) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                COUNT(*) as transactions,
                SUM(amount) as revenue
             FROM {$wpdb->prefix}rolino_transactions 
             WHERE status = 'completed' 
             AND created_at >= %s 
             AND created_at <= %s
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
             ORDER BY month",
            $start_date,
            $end_date
        );
        
        $results = $wpdb->get_results($sql);
        
        $chart_data = array();
        foreach ($results as $result) {
            $chart_data[] = array(
                'label' => date_i18n('Y/m', strtotime($result->month . '-01')),
                'transactions' => intval($result->transactions),
                'revenue' => floatval($result->revenue)
            );
        }
        
        return $chart_data;
    }
    
    /**
     * Get date range for period
     * 
     * @param string $period
     * @return array
     */
    private function get_date_range($period) {
        $now = current_time('mysql');
        
        switch ($period) {
            case 'today':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now)),
                    'end' => date('Y-m-d 23:59:59', strtotime($now))
                );
            case 'yesterday':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now . ' -1 day')),
                    'end' => date('Y-m-d 23:59:59', strtotime($now . ' -1 day'))
                );
            case 'day_before_yesterday':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now . ' -2 days')),
                    'end' => date('Y-m-d 23:59:59', strtotime($now . ' -2 days'))
                );
            case 'this_week':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now . ' -' . (date('w', strtotime($now)) - 1) . ' days')),
                    'end' => date('Y-m-d 23:59:59', strtotime($now))
                );
            case 'last_week':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now . ' -' . (date('w', strtotime($now)) + 6) . ' days')),
                    'end' => date('Y-m-d 23:59:59', strtotime($now . ' -' . (date('w', strtotime($now))) . ' days'))
                );
            case 'week_before_last':
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now . ' -' . (date('w', strtotime($now)) + 13) . ' days')),
                    'end' => date('Y-m-d 23:59:59', strtotime($now . ' -' . (date('w', strtotime($now)) + 7) . ' days'))
                );
            case 'this_month':
                return array(
                    'start' => date('Y-m-01 00:00:00', strtotime($now)),
                    'end' => date('Y-m-t 23:59:59', strtotime($now))
                );
            case 'last_month':
                return array(
                    'start' => date('Y-m-01 00:00:00', strtotime($now . ' -1 month')),
                    'end' => date('Y-m-t 23:59:59', strtotime($now . ' -1 month'))
                );
            case 'month_before_last':
                return array(
                    'start' => date('Y-m-01 00:00:00', strtotime($now . ' -2 months')),
                    'end' => date('Y-m-t 23:59:59', strtotime($now . ' -2 months'))
                );
            case 'this_year':
                return array(
                    'start' => date('Y-01-01 00:00:00', strtotime($now)),
                    'end' => date('Y-12-31 23:59:59', strtotime($now))
                );
            case 'last_year':
                return array(
                    'start' => date('Y-01-01 00:00:00', strtotime($now . ' -1 year')),
                    'end' => date('Y-12-31 23:59:59', strtotime($now . ' -1 year'))
                );
            case 'year_before_last':
                return array(
                    'start' => date('Y-01-01 00:00:00', strtotime($now . ' -2 years')),
                    'end' => date('Y-12-31 23:59:59', strtotime($now . ' -2 years'))
                );
            default:
                return array(
                    'start' => date('Y-m-d 00:00:00', strtotime($now)),
                    'end' => date('Y-m-d 23:59:59', strtotime($now))
                );
        }
    }
    
    /**
     * AJAX get revenue stats
     */
    public function ajax_get_revenue_stats() {
        check_ajax_referer('rolino_revenue_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        $stats = $this->get_revenue_stats($period);
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX get revenue chart
     */
    public function ajax_get_revenue_chart() {
        check_ajax_referer('rolino_revenue_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی ندارید', 'rolino')));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        $chart_data = $this->get_revenue_chart_data($period);
        
        wp_send_json_success($chart_data);
    }
    
    /**
     * Format currency
     * 
     * @param float $amount
     * @return string
     */
    public function format_currency($amount) {
        return number_format($amount, 0, '.', ',') . ' تومان';
    }
    
    /**
     * Get period title
     * 
     * @param string $period
     * @return string
     */
    public function get_period_title($period) {
        $titles = array(
            'today' => __('امروز', 'rolino'),
            'yesterday' => __('دیروز', 'rolino'),
            'day_before_yesterday' => __('پریروز', 'rolino'),
            'this_week' => __('این هفته', 'rolino'),
            'last_week' => __('هفته پیش', 'rolino'),
            'week_before_last' => __('هفته پیشش', 'rolino'),
            'this_month' => __('این ماه', 'rolino'),
            'last_month' => __('ماه پیش', 'rolino'),
            'month_before_last' => __('ماه قبلش', 'rolino'),
            'this_year' => __('امسال', 'rolino'),
            'last_year' => __('پارسال', 'rolino'),
            'year_before_last' => __('سال پیشش', 'rolino')
        );
        
        return $titles[$period] ?? $period;
    }
}