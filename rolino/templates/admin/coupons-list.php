<?php
/**
 * Coupons List Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$coupons_admin = new Rolino_Coupons_Admin();
$coupon_types = $coupons_admin->get_coupon_types();

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('کدهای تخفیف', 'rolino'); ?>
        <a href="<?php echo admin_url('admin.php?page=rolino-coupons&action=add'); ?>" class="page-title-action">
            <?php _e('افزودن کد تخفیف جدید', 'rolino'); ?>
        </a>
    </h1>
    
    <hr class="wp-header-end">
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('کد تخفیف', 'rolino'); ?></th>
                <th><?php _e('نوع', 'rolino'); ?></th>
                <th><?php _e('تاریخ شروع', 'rolino'); ?></th>
                <th><?php _e('تاریخ پایان', 'rolino'); ?></th>
                <th><?php _e('وضعیت', 'rolino'); ?></th>
                <th><?php _e('استفاده', 'rolino'); ?></th>
                <th><?php _e('عملیات', 'rolino'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($coupons)): ?>
                <?php foreach ($coupons as $coupon): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($coupon->code); ?></strong>
                        </td>
                        <td>
                            <?php echo $coupons_admin->get_type_badge($coupon->type); ?>
                        </td>
                        <td><?php echo isset($coupon->start_date) ? date_i18n('Y/m/d', strtotime($coupon->start_date)) : '-'; ?></td>
                        <td><?php echo isset($coupon->end_date) ? date_i18n('Y/m/d', strtotime($coupon->end_date)) : '-'; ?></td>
                        <td>
                            <?php echo $coupons_admin->get_status_badge($coupon->status); ?>
                            <?php echo $coupons_admin->get_expiry_badge($coupon); ?>
                        </td>
                        <td>
                            <?php echo number_format($coupon->used_count); ?>
                            <?php if (isset($coupon->usage_limit) && $coupon->usage_limit > 0): ?>
                                / <?php echo number_format($coupon->usage_limit); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url("admin.php?page=rolino-coupons&action=edit&coupon_id={$coupon->id}"); ?>" 
                               class="button button-small">
                                <?php _e('ویرایش', 'rolino'); ?>
                            </a>
                            <button type="button" 
                                    class="button button-small button-link-delete delete-coupon" 
                                    data-coupon-id="<?php echo $coupon->id; ?>"
                                    data-coupon-code="<?php echo esc_attr($coupon->code); ?>">
                                <?php _e('حذف', 'rolino'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7"><?php _e('هیچ کد تخفیفی یافت نشد', 'rolino'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('.delete-coupon').on('click', function() {
        var couponId = $(this).data('coupon-id');
        var couponCode = $(this).data('coupon-code');
        
        if (confirm('<?php _e('آیا از حذف کد تخفیف', 'rolino'); ?> "' + couponCode + '" <?php _e('مطمئن هستید؟', 'rolino'); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rolino_delete_coupon',
                    coupon_id: couponId,
                    _wpnonce: '<?php echo wp_create_nonce('rolino_coupon_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('خطا در حذف کد تخفیف', 'rolino'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
                }
            });
        }
    });
});
</script>