<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get statistics data passed from the admin menu class
$plan_stats = $plan_stats ?? array();
$credit_stats = $credit_stats ?? array();
$transaction_stats = $transaction_stats ?? array();
$coupon_stats = $coupon_stats ?? array();

?>

<div class="wrap rolino-admin-dashboard">
    <h1 class="wp-heading-inline">
        <?php _e('داشبورد رولینو', 'rolino'); ?>
        <span class="rolino-version">v<?php echo ROLINO_VERSION; ?></span>
    </h1>
    
    <div class="rolino-dashboard-grid">
        
        <!-- Statistics Cards -->
        <div class="rolino-stats-section">
            <div class="rolino-stats-grid">
                
                <!-- Plans Stats -->
                <div class="rolino-stat-card stat-plans">
                    <div class="stat-icon">
                        <i class="dashicons dashicons-list-view"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($plan_stats['total_plans'] ?? 0); ?></h3>
                        <p><?php _e('کل طرح‌ها', 'rolino'); ?></p>
                        <span class="stat-detail">
                            <?php echo number_format($plan_stats['active_plans'] ?? 0); ?> <?php _e('فعال', 'rolino'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Credits Stats -->
                <div class="rolino-stat-card stat-credits">
                    <div class="stat-icon">
                        <i class="dashicons dashicons-awards"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($credit_stats['total_credits'] ?? 0); ?></h3>
                        <p><?php _e('کل اعتبارات', 'rolino'); ?></p>
                        <span class="stat-detail">
                            <?php echo number_format($credit_stats['active_subscriptions'] ?? 0); ?> <?php _e('اشتراک فعال', 'rolino'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Transactions Stats -->
                <div class="rolino-stat-card stat-transactions">
                    <div class="stat-icon">
                        <i class="dashicons dashicons-money-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($transaction_stats['total_revenue'] ?? 0); ?></h3>
                        <p><?php _e('کل درآمد (تومان)', 'rolino'); ?></p>
                        <span class="stat-detail">
                            <?php echo number_format($transaction_stats['today_revenue'] ?? 0); ?> <?php _e('امروز', 'rolino'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Coupons Stats -->
                <div class="rolino-stat-card stat-coupons">
                    <div class="stat-icon">
                        <i class="dashicons dashicons-tickets-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($coupon_stats['active_coupons'] ?? 0); ?></h3>
                        <p><?php _e('کدهای تخفیف فعال', 'rolino'); ?></p>
                        <span class="stat-detail">
                            <?php echo number_format($coupon_stats['used_today'] ?? 0); ?> <?php _e('استفاده امروز', 'rolino'); ?>
                        </span>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="rolino-quick-actions">
            <h2><?php _e('عملیات سریع', 'rolino'); ?></h2>
            <div class="quick-actions-grid">
                
                <a href="<?php echo admin_url('admin.php?page=rolino-plans&action=add'); ?>" class="quick-action-btn">
                    <i class="dashicons dashicons-plus-alt"></i>
                    <span><?php _e('طرح جدید', 'rolino'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=rolino-coupons&action=add'); ?>" class="quick-action-btn">
                    <i class="dashicons dashicons-tag"></i>
                    <span><?php _e('کد تخفیف جدید', 'rolino'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=rolino-transactions'); ?>" class="quick-action-btn">
                    <i class="dashicons dashicons-list-view"></i>
                    <span><?php _e('مشاهده تراکنش‌ها', 'rolino'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=rolino-settings'); ?>" class="quick-action-btn">
                    <i class="dashicons dashicons-admin-settings"></i>
                    <span><?php _e('تنظیمات', 'rolino'); ?></span>
                </a>
                
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="rolino-recent-activity">
            <h2><?php _e('فعالیت‌های اخیر', 'rolino'); ?></h2>
            
            <?php
            // Get recent transactions
            $transactions = new Rolino_Transactions();
            $recent_transactions = $transactions->get_transactions(array(
                'limit' => 10,
                'status' => 'completed'
            ));
            ?>
            
            <?php if (!empty($recent_transactions)): ?>
                <div class="recent-transactions">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('کاربر', 'rolino'); ?></th>
                                <th><?php _e('طرح', 'rolino'); ?></th>
                                <th><?php _e('مبلغ', 'rolino'); ?></th>
                                <th><?php _e('تاریخ', 'rolino'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($transaction->user_name ?? __('کاربر حذف شده', 'rolino')); ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction->plan_id == 0): ?>
                                            <span class="single-buy-badge"><?php _e('خرید تکی', 'rolino'); ?></span>
                                        <?php else: ?>
                                            <?php echo esc_html($transaction->plan_name ?? __('طرح حذف شده', 'rolino')); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($transaction->amount, 0, '', ','); ?></strong>
                                        <span class="currency"><?php _e('تومان', 'rolino'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo date_i18n('Y/m/d H:i', strtotime($transaction->transaction_date)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-activity">
                    <p><?php _e('هنوز تراکنش موفقی ثبت نشده است', 'rolino'); ?></p>
                </div>
            <?php endif; ?>
            
        </div>
        
        <!-- System Status -->
        <div class="rolino-system-status">
            <h2><?php _e('وضعیت سیستم', 'rolino'); ?></h2>
            
            <div class="status-items">
                
                <!-- Database Status -->
                <div class="status-item">
                    <span class="status-label"><?php _e('پایگاه داده:', 'rolino'); ?></span>
                    <span class="status-value status-ok">
                        <i class="dashicons dashicons-yes-alt"></i>
                        <?php _e('متصل', 'rolino'); ?>
                    </span>
                </div>
                
                <!-- SMS Status -->
                <div class="status-item">
                    <span class="status-label"><?php _e('سیستم SMS:', 'rolino'); ?></span>
                    <?php $sms_enabled = get_option('rolino_sms_enabled', 0); ?>
                    <span class="status-value <?php echo $sms_enabled ? 'status-ok' : 'status-disabled'; ?>">
                        <i class="dashicons dashicons-<?php echo $sms_enabled ? 'yes-alt' : 'dismiss'; ?>"></i>
                        <?php echo $sms_enabled ? __('فعال', 'rolino') : __('غیرفعال', 'rolino'); ?>
                    </span>
                </div>
                
                <!-- Cron Status -->
                <div class="status-item">
                    <span class="status-label"><?php _e('وظایف برنامه‌ریزی شده:', 'rolino'); ?></span>
                    <?php $cron_active = wp_next_scheduled('rolino_check_subscription_reminders'); ?>
                    <span class="status-value <?php echo $cron_active ? 'status-ok' : 'status-warning'; ?>">
                        <i class="dashicons dashicons-<?php echo $cron_active ? 'yes-alt' : 'warning'; ?>"></i>
                        <?php echo $cron_active ? __('فعال', 'rolino') : __('غیرفعال', 'rolino'); ?>
                    </span>
                </div>
                
                <!-- Single Buy Status -->
                <div class="status-item">
                    <span class="status-label"><?php _e('خرید تکی:', 'rolino'); ?></span>
                    <?php $single_buy_active = get_option('rolino_single_buy_active', 0); ?>
                    <span class="status-value <?php echo $single_buy_active ? 'status-ok' : 'status-disabled'; ?>">
                        <i class="dashicons dashicons-<?php echo $single_buy_active ? 'yes-alt' : 'dismiss'; ?>"></i>
                        <?php echo $single_buy_active ? __('فعال', 'rolino') : __('غیرفعال', 'rolino'); ?>
                    </span>
                </div>
                
            </div>
        </div>
        
        <!-- Plugin Info -->
        <div class="rolino-plugin-info">
            <h2><?php _e('اطلاعات پلاگین', 'rolino'); ?></h2>
            
            <div class="plugin-details">
                <div class="detail-item">
                    <strong><?php _e('نسخه:', 'rolino'); ?></strong>
                    <span><?php echo ROLINO_VERSION; ?></span>
                </div>
                
                <div class="detail-item">
                    <strong><?php _e('سازنده:', 'rolino'); ?></strong>
                    <span><?php _e('Cinema.ir Team', 'rolino'); ?></span>
                </div>
                
                <div class="detail-item">
                    <strong><?php _e('پشتیبانی:', 'rolino'); ?></strong>
                    <a href="https://cine.ir" target="_blank"><?php _e('وب‌سایت سازنده', 'rolino'); ?></a>
                </div>
                
                <div class="detail-item">
                    <strong><?php _e('آخرین بررسی:', 'rolino'); ?></strong>
                    <span><?php echo date_i18n('Y/m/d H:i'); ?></span>
                </div>
            </div>
            
            <?php if (current_user_can('update_plugins')): ?>
                <div class="plugin-actions">
                    <a href="<?php echo admin_url('admin.php?page=rolino-settings'); ?>" class="button button-secondary">
                        <?php _e('تنظیمات کامل', 'rolino'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    
</div>

<style>
.rolino-admin-dashboard .rolino-version {
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 10px;
}

.rolino-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    margin-top: 20px;
}

.rolino-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.rolino-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
}

.rolino-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 24px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.stat-plans .stat-icon { background: #e3f2fd; color: #1976d2; }
.stat-credits .stat-icon { background: #f3e5f5; color: #7b1fa2; }
.stat-transactions .stat-icon { background: #e8f5e8; color: #388e3c; }
.stat-coupons .stat-icon { background: #fff3e0; color: #f57c00; }

.stat-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
    line-height: 1;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 14px;
}

.stat-detail {
    font-size: 12px;
    color: #999;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
    text-decoration: none;
}

.quick-action-btn i {
    font-size: 20px;
    color: #2271b1;
}

.status-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.status-value {
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-ok { color: #4caf50; }
.status-warning { color: #ff9800; }
.status-disabled { color: #757575; }

.no-activity {
    text-align: center;
    padding: 40px;
    color: #666;
}

.single-buy-badge {
    background: #ff9800;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

@media (max-width: 1200px) {
    .rolino-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .rolino-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>