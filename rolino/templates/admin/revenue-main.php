<?php
/**
 * Revenue Management Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = $_GET['period'] ?? 'today';
$revenue_admin = new Rolino_Revenue_Admin();
$stats = $revenue_admin->get_revenue_stats($period);
$chart_data = $revenue_admin->get_revenue_chart_data($period);
?>

<div class="wrap">
    <h1><?php _e('مدیریت درآمد', 'rolino'); ?></h1>
    
    <!-- Period Navigation -->
    <div class="rolino-revenue-nav">
        <a href="?page=rolino-revenue&period=today" class="button <?php echo $period === 'today' ? 'button-primary' : ''; ?>">
            <?php _e('امروز', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=yesterday" class="button <?php echo $period === 'yesterday' ? 'button-primary' : ''; ?>">
            <?php _e('دیروز', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=day_before_yesterday" class="button <?php echo $period === 'day_before_yesterday' ? 'button-primary' : ''; ?>">
            <?php _e('پریروز', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=this_week" class="button <?php echo $period === 'this_week' ? 'button-primary' : ''; ?>">
            <?php _e('این هفته', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=last_week" class="button <?php echo $period === 'last_week' ? 'button-primary' : ''; ?>">
            <?php _e('هفته پیش', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=week_before_last" class="button <?php echo $period === 'week_before_last' ? 'button-primary' : ''; ?>">
            <?php _e('هفته پیشش', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=this_month" class="button <?php echo $period === 'this_month' ? 'button-primary' : ''; ?>">
            <?php _e('این ماه', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=last_month" class="button <?php echo $period === 'last_month' ? 'button-primary' : ''; ?>">
            <?php _e('ماه پیش', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=month_before_last" class="button <?php echo $period === 'month_before_last' ? 'button-primary' : ''; ?>">
            <?php _e('ماه قبلش', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=this_year" class="button <?php echo $period === 'this_year' ? 'button-primary' : ''; ?>">
            <?php _e('امسال', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=last_year" class="button <?php echo $period === 'last_year' ? 'button-primary' : ''; ?>">
            <?php _e('پارسال', 'rolino'); ?>
        </a>
        <a href="?page=rolino-revenue&period=year_before_last" class="button <?php echo $period === 'year_before_last' ? 'button-primary' : ''; ?>">
            <?php _e('سال پیشش', 'rolino'); ?>
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <div class="rolino-stats-grid">
        <div class="rolino-stat-card">
            <h3><?php _e('کل درآمد', 'rolino'); ?></h3>
            <div class="stat-value"><?php echo $revenue_admin->format_currency($stats['total_revenue']); ?></div>
        </div>
        
        <div class="rolino-stat-card">
            <h3><?php _e('تعداد تراکنش', 'rolino'); ?></h3>
            <div class="stat-value"><?php echo number_format($stats['total_transactions']); ?></div>
        </div>
        
        <div class="rolino-stat-card">
            <h3><?php _e('میانگین تراکنش', 'rolino'); ?></h3>
            <div class="stat-value"><?php echo $revenue_admin->format_currency($stats['avg_transaction']); ?></div>
        </div>
        
        <div class="rolino-stat-card">
            <h3><?php _e('مشتریان منحصر', 'rolino'); ?></h3>
            <div class="stat-value"><?php echo number_format($stats['unique_customers']); ?></div>
        </div>
    </div>
    
    <!-- Revenue Chart -->
    <div class="rolino-chart-container">
        <h2><?php _e('نمودار درآمد', 'rolino'); ?></h2>
        <canvas id="revenueChart" width="400" height="200"></canvas>
    </div>
    
    <!-- Revenue by Plan -->
    <div class="rolino-revenue-details">
        <div class="rolino-revenue-section">
            <h3><?php _e('درآمد بر اساس طرح', 'rolino'); ?></h3>
            <?php if (!empty($stats['plan_revenue'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('طرح', 'rolino'); ?></th>
                            <th><?php _e('تعداد تراکنش', 'rolino'); ?></th>
                            <th><?php _e('کل درآمد', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['plan_revenue'] as $plan): ?>
                            <tr>
                                <td><?php echo esc_html($plan->plan_name ?? __('خرید تکی', 'rolino')); ?></td>
                                <td><?php echo number_format($plan->transaction_count); ?></td>
                                <td><?php echo $revenue_admin->format_currency($plan->total_amount); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('هیچ تراکنشی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="rolino-revenue-section">
            <h3><?php _e('درآمد بر اساس درگاه پرداخت', 'rolino'); ?></h3>
            <?php if (!empty($stats['gateway_revenue'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('درگاه پرداخت', 'rolino'); ?></th>
                            <th><?php _e('تعداد تراکنش', 'rolino'); ?></th>
                            <th><?php _e('کل درآمد', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['gateway_revenue'] as $gateway): ?>
                            <tr>
                                <td><?php echo esc_html($gateway->gateway); ?></td>
                                <td><?php echo number_format($gateway->transaction_count); ?></td>
                                <td><?php echo $revenue_admin->format_currency($gateway->total_amount); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('هیچ تراکنشی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.rolino-revenue-nav {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.rolino-revenue-nav .button {
    margin: 0 5px 5px 0;
}

.rolino-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.rolino-stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    text-align: center;
}

.rolino-stat-card h3 {
    margin: 0 0 10px 0;
    color: #23282d;
    font-size: 14px;
}

.rolino-stat-card .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.rolino-chart-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.rolino-revenue-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.rolino-revenue-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.rolino-revenue-section h3 {
    margin-top: 0;
    color: #23282d;
}

@media (max-width: 768px) {
    .rolino-revenue-details {
        grid-template-columns: 1fr;
    }
    
    .rolino-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(document).ready(function($) {
    // Chart data
    var chartData = <?php echo json_encode($chart_data); ?>;
    
    // Prepare chart data
    var labels = chartData.map(function(item) { return item.label; });
    var revenueData = chartData.map(function(item) { return item.revenue; });
    var transactionData = chartData.map(function(item) { return item.transactions; });
    
    // Create chart
    var ctx = document.getElementById('revenueChart').getContext('2d');
    var revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '<?php _e('درآمد (تومان)', 'rolino'); ?>',
                data: revenueData,
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                borderWidth: 2,
                fill: true,
                yAxisID: 'y'
            }, {
                label: '<?php _e('تعداد تراکنش', 'rolino'); ?>',
                data: transactionData,
                borderColor: '#46b450',
                backgroundColor: 'rgba(70, 180, 80, 0.1)',
                borderWidth: 2,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '<?php _e('زمان', 'rolino'); ?>'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '<?php _e('درآمد (تومان)', 'rolino'); ?>'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: '<?php _e('تعداد تراکنش', 'rolino'); ?>'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
});
</script>