<?php
/**
 * Transactions Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$transactions_obj = new Rolino_Transactions();
$transactions = $transactions_obj->get_transactions(array('limit' => 50));

?>

<div class="wrap">
    <h1><?php _e('تراکنش‌ها', 'rolino'); ?></h1>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('شناسه', 'rolino'); ?></th>
                <th><?php _e('کاربر', 'rolino'); ?></th>
                <th><?php _e('طرح', 'rolino'); ?></th>
                <th><?php _e('مبلغ', 'rolino'); ?></th>
                <th><?php _e('وضعیت', 'rolino'); ?></th>
                <th><?php _e('درگاه', 'rolino'); ?></th>
                <th><?php _e('تاریخ', 'rolino'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td>#<?php echo $transaction->id; ?></td>
                        <td><?php echo esc_html($transaction->user_name ?? __('کاربر حذف شده', 'rolino')); ?></td>
                        <td>
                            <?php if ($transaction->plan_id == 0): ?>
                                <span class="single-buy-badge"><?php _e('خرید تکی', 'rolino'); ?></span>
                            <?php else: ?>
                                <?php echo esc_html($transaction->plan_name ?? __('طرح حذف شده', 'rolino')); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($transaction->amount, 0, '', ','); ?> <?php _e('تومان', 'rolino'); ?></td>
                        <td>
                            <?php if ($transaction->status === 'completed'): ?>
                                <span class="status-completed"><?php _e('موفق', 'rolino'); ?></span>
                            <?php elseif ($transaction->status === 'pending'): ?>
                                <span class="status-pending"><?php _e('در انتظار', 'rolino'); ?></span>
                            <?php else: ?>
                                <span class="status-failed"><?php _e('ناموفق', 'rolino'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($transaction->gateway); ?></td>
                        <td><?php echo date_i18n('Y/m/d H:i', strtotime($transaction->transaction_date)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7"><?php _e('هیچ تراکنشی یافت نشد', 'rolino'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-completed { color: #46b450; font-weight: bold; }
.status-pending { color: #ffb900; font-weight: bold; }
.status-failed { color: #dc3232; font-weight: bold; }
.single-buy-badge { background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
</style>