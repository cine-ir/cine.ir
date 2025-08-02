<?php
/**
 * User Subscription Template
 * 
 * Template for displaying user subscription info
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rolino-user-subscription">
    <div class="subscription-overview">
        <h3><?php _e('وضعیت اشتراک', 'rolino'); ?></h3>
        
        <?php if ($active_subscription): ?>
            <div class="subscription-card active">
                <div class="card-header">
                    <h4><?php _e('اشتراک فعال', 'rolino'); ?></h4>
                    <span class="status-badge active"><?php _e('فعال', 'rolino'); ?></span>
                </div>
                
                <div class="card-body">
                    <div class="plan-info">
                        <span class="label"><?php _e('طرح:', 'rolino'); ?></span>
                        <span class="value"><?php echo esc_html($active_subscription->plan_name); ?></span>
                    </div>
                    
                    <div class="credits-info">
                        <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                        <span class="value"><?php echo number_format($active_subscription->credit); ?></span>
                    </div>
                    
                    <div class="duration-info">
                        <span class="label"><?php _e('شروع:', 'rolino'); ?></span>
                        <span class="value"><?php echo date_i18n('Y/m/d', strtotime($active_subscription->start_time)); ?></span>
                    </div>
                    
                    <div class="expiry-info">
                        <span class="label"><?php _e('پایان:', 'rolino'); ?></span>
                        <span class="value"><?php echo date_i18n('Y/m/d', strtotime($active_subscription->end_time)); ?></span>
                    </div>
                    
                    <div class="remaining-info">
                        <span class="label"><?php _e('باقی‌مانده:', 'rolino'); ?></span>
                        <span class="value"><?php echo $this->get_user_remaining_time_text($user_id); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-subscription">
                <p><?php _e('شما هیچ اشتراک فعالی ندارید.', 'rolino'); ?></p>
                <a href="<?php echo home_url('/'); ?>" class="rolino-btn rolino-btn-primary"><?php _e('مشاهده طرح‌ها', 'rolino'); ?></a>
            </div>
        <?php endif; ?>
        
        <?php if ($reserve_subscription): ?>
            <div class="subscription-card reserve">
                <div class="card-header">
                    <h4><?php _e('اشتراک رزرو', 'rolino'); ?></h4>
                    <span class="status-badge reserve"><?php _e('رزرو', 'rolino'); ?></span>
                </div>
                
                <div class="card-body">
                    <div class="plan-info">
                        <span class="label"><?php _e('طرح:', 'rolino'); ?></span>
                        <span class="value"><?php echo esc_html($reserve_subscription->plan_name); ?></span>
                    </div>
                    
                    <div class="credits-info">
                        <span class="label"><?php _e('اعتبار:', 'rolino'); ?></span>
                        <span class="value"><?php echo number_format($reserve_subscription->credit); ?></span>
                    </div>
                    
                    <div class="start-info">
                        <span class="label"><?php _e('شروع:', 'rolino'); ?></span>
                        <span class="value"><?php echo date_i18n('Y/m/d', strtotime($reserve_subscription->start_time)); ?></span>
                    </div>
                    
                    <div class="end-info">
                        <span class="label"><?php _e('پایان:', 'rolino'); ?></span>
                        <span class="value"><?php echo date_i18n('Y/m/d', strtotime($reserve_subscription->end_time)); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($atts['show_history'] === 'yes' && !empty($credit_history)): ?>
        <div class="subscription-history">
            <h3><?php _e('تاریخچه اعتبار', 'rolino'); ?></h3>
            
            <div class="history-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('تاریخ', 'rolino'); ?></th>
                            <th><?php _e('طرح', 'rolino'); ?></th>
                            <th><?php _e('اعتبار', 'rolino'); ?></th>
                            <th><?php _e('وضعیت', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credit_history as $record): ?>
                            <tr>
                                <td><?php echo date_i18n('Y/m/d', strtotime($record->start_time)); ?></td>
                                <td><?php echo esc_html($record->plan_name); ?></td>
                                <td><?php echo number_format($record->credit); ?></td>
                                <td>
                                    <?php if (strtotime($record->end_time) > current_time('timestamp')): ?>
                                        <span class="status-active"><?php _e('فعال', 'rolino'); ?></span>
                                    <?php else: ?>
                                        <span class="status-expired"><?php _e('منقضی', 'rolino'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.rolino-user-subscription {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.subscription-overview h3,
.subscription-history h3 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.subscription-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.subscription-card.active {
    border-left: 4px solid #28a745;
}

.subscription-card.reserve {
    border-left: 4px solid #ffc107;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9ecef;
}

.card-header h4 {
    margin: 0;
    color: #333;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: bold;
}

.status-badge.active {
    background: #28a745;
    color: white;
}

.status-badge.reserve {
    background: #ffc107;
    color: #212529;
}

.card-body {
    padding: 20px;
}

.card-body > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.card-body > div:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.card-body .label {
    color: #666;
    font-weight: bold;
}

.card-body .value {
    color: #333;
    font-weight: bold;
}

.no-subscription {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #dee2e6;
}

.no-subscription p {
    margin-bottom: 20px;
    color: #666;
    font-size: 1.1em;
}

.subscription-history {
    margin-top: 30px;
}

.history-table {
    overflow-x: auto;
}

.history-table table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.history-table th,
.history-table td {
    padding: 12px 15px;
    text-align: right;
    border-bottom: 1px solid #e9ecef;
}

.history-table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #495057;
}

.history-table tr:hover {
    background: #f8f9fa;
}

.status-active {
    color: #28a745;
    font-weight: bold;
}

.status-expired {
    color: #dc3545;
    font-weight: bold;
}

.rolino-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1em;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.rolino-btn-primary {
    background: #007bff;
    color: white;
}

.rolino-btn-primary:hover {
    background: #0056b3;
}

@media (max-width: 768px) {
    .rolino-user-subscription {
        padding: 10px;
    }
    
    .card-body > div {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .history-table {
        font-size: 0.9em;
    }
    
    .history-table th,
    .history-table td {
        padding: 8px 10px;
    }
}
</style>