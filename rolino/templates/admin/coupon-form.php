<?php
/**
 * Coupon Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$coupon_id = isset($coupon) ? $coupon->id : 0;
$is_edit = $coupon_id > 0;
$form_title = $is_edit ? __('ویرایش کد تخفیف', 'rolino') : __('افزودن کد تخفیف جدید', 'rolino');
$submit_text = $is_edit ? __('به‌روزرسانی کد تخفیف', 'rolino') : __('ایجاد کد تخفیف', 'rolino');

// Default values
$defaults = array(
    'code' => '',
    'type' => 1,
    'start_date' => '',
    'end_date' => '',
    'usage_limit' => 0,
    'status' => 1
);

$coupon_data = isset($coupon) ? (array) $coupon : $defaults;
$coupon_types = array(
    1 => __('درصدی', 'rolino'),
    2 => __('مبلغ ثابت', 'rolino'),
    3 => __('انحصاری', 'rolino')
);
?>

<div class="wrap">
    <h1><?php echo $form_title; ?></h1>
    
    <form method="post" id="rolino-coupon-form">
        <?php wp_nonce_field('rolino_coupon_nonce', '_wpnonce'); ?>
        <input type="hidden" name="coupon_id" value="<?php echo $coupon_id; ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="code"><?php _e('کد تخفیف', 'rolino'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                           id="code" 
                           name="code" 
                           value="<?php echo esc_attr($coupon_data['code']); ?>" 
                           class="regular-text" 
                           required>
                    <button type="button" id="generate-code" class="button">
                        <?php _e('تولید کد خودکار', 'rolino'); ?>
                    </button>
                    <p class="description"><?php _e('کد تخفیف منحصر به فرد', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="type"><?php _e('نوع تخفیف', 'rolino'); ?></label>
                </th>
                <td>
                    <select id="type" name="type">
                        <?php foreach ($coupon_types as $type_id => $type_name): ?>
                            <option value="<?php echo $type_id; ?>" <?php selected($coupon_data['type'], $type_id); ?>>
                                <?php echo $type_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('نوع تخفیف اعمال شده', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="start_date"><?php _e('تاریخ شروع', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="date" 
                           id="start_date" 
                           name="start_date" 
                           value="<?php echo esc_attr($coupon_data['start_date']); ?>" 
                           class="regular-text">
                    <p class="description"><?php _e('تاریخ شروع اعتبار کد تخفیف', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="end_date"><?php _e('تاریخ پایان', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="date" 
                           id="end_date" 
                           name="end_date" 
                           value="<?php echo esc_attr($coupon_data['end_date']); ?>" 
                           class="regular-text">
                    <p class="description"><?php _e('تاریخ پایان اعتبار کد تخفیف', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="usage_limit"><?php _e('محدودیت استفاده', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="usage_limit" 
                           name="usage_limit" 
                           value="<?php echo esc_attr($coupon_data['usage_limit']); ?>" 
                           class="small-text" 
                           min="0">
                    <p class="description"><?php _e('تعداد دفعات مجاز استفاده (0 = نامحدود)', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="status"><?php _e('وضعیت', 'rolino'); ?></label>
                </th>
                <td>
                    <select id="status" name="status">
                        <option value="1" <?php selected($coupon_data['status'], 1); ?>><?php _e('فعال', 'rolino'); ?></option>
                        <option value="0" <?php selected($coupon_data['status'], 0); ?>><?php _e('غیرفعال', 'rolino'); ?></option>
                    </select>
                    <p class="description"><?php _e('وضعیت فعال یا غیرفعال بودن کد تخفیف', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Plan Discounts Section -->
        <h3><?php _e('تخفیف‌های طرح‌ها', 'rolino'); ?></h3>
        <p class="description"><?php _e('برای هر طرح، درصد یا مبلغ تخفیف را مشخص کنید', 'rolino'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 200px;"><?php _e('نام طرح', 'rolino'); ?></th>
                    <th style="width: 150px;"><?php _e('درصد تخفیف', 'rolino'); ?></th>
                    <th style="width: 150px;"><?php _e('مبلغ تخفیف (تومان)', 'rolino'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_plans)): ?>
                    <?php foreach ($all_plans as $plan): ?>
                        <?php 
                        $plan_discount = isset($coupon_discounts[$plan->id]) ? $coupon_discounts[$plan->id] : array('percent' => 0, 'amount' => 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($plan->plan_name); ?></strong>
                                <p class="description"><?php echo number_format($plan->price); ?> تومان</p>
                            </td>
                            <td>
                                <input type="number" 
                                       name="plan_discounts[<?php echo $plan->id; ?>][percent]" 
                                       value="<?php echo esc_attr($plan_discount['percent']); ?>" 
                                       class="small-text" 
                                       min="0" 
                                       max="100" 
                                       step="0.1">
                                <span>%</span>
                            </td>
                            <td>
                                <input type="number" 
                                       name="plan_discounts[<?php echo $plan->id; ?>][amount]" 
                                       value="<?php echo esc_attr($plan_discount['amount']); ?>" 
                                       class="small-text" 
                                       min="0" 
                                       step="1000">
                                <span>تومان</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3"><?php _e('هیچ طرح فعالی یافت نشد', 'rolino'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="save-coupon">
                <?php echo $submit_text; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=rolino-coupons'); ?>" class="button">
                <?php _e('انصراف', 'rolino'); ?>
            </a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Generate coupon code
    $('#generate-code').on('click', function() {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var code = '';
        for (var i = 0; i < 8; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#code').val(code);
    });
    
    // Form submission
    $('#rolino-coupon-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=rolino_save_coupon&nonce=' + rolino_ajax.nonce;
        
        $('#save-coupon').prop('disabled', true).text('<?php _e('در حال ذخیره...', 'rolino'); ?>');
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = '<?php echo admin_url('admin.php?page=rolino-coupons'); ?>';
                } else {
                    alert(response.data.message || '<?php _e('خطا در ذخیره کد تخفیف', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            },
            complete: function() {
                $('#save-coupon').prop('disabled', false).text('<?php echo $submit_text; ?>');
            }
        });
    });
});
</script>