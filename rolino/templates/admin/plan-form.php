<?php
/**
 * Plan Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plan_id = isset($plan) ? $plan->id : 0;
$is_edit = $plan_id > 0;
$form_title = $is_edit ? __('ویرایش طرح', 'rolino') : __('افزودن طرح جدید', 'rolino');
$submit_text = $is_edit ? __('به‌روزرسانی طرح', 'rolino') : __('ایجاد طرح', 'rolino');

// Default values
$defaults = array(
    'plan_name' => '',
    'credits' => 0,
    'duration' => 30,
    'price' => 0,
    'active_sessions' => 1,
    'status' => 1
);

$plan_data = isset($plan) ? (array) $plan : $defaults;
?>

<div class="wrap">
    <h1><?php echo $form_title; ?></h1>
    
    <form method="post" id="rolino-plan-form">
        <?php wp_nonce_field('rolino_plan_nonce', '_wpnonce'); ?>
        <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="plan_name"><?php _e('نام طرح', 'rolino'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                           id="plan_name" 
                           name="plan_name" 
                           value="<?php echo esc_attr($plan_data['plan_name']); ?>" 
                           class="regular-text" 
                           required>
                    <p class="description"><?php _e('نام منحصر به فرد برای طرح', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="credits"><?php _e('تعداد اعتبار', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="credits" 
                           name="credits" 
                           value="<?php echo esc_attr($plan_data['credits']); ?>" 
                           class="small-text" 
                           min="0">
                    <p class="description"><?php _e('تعداد اعتبارهای قابل استفاده در این طرح', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="duration"><?php _e('مدت اعتبار (روز)', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="duration" 
                           name="duration" 
                           value="<?php echo esc_attr($plan_data['duration']); ?>" 
                           class="small-text" 
                           min="1" 
                           max="3650">
                    <p class="description"><?php _e('مدت زمان اعتبار طرح به روز', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="price"><?php _e('قیمت (تومان)', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="price" 
                           name="price" 
                           value="<?php echo esc_attr($plan_data['price']); ?>" 
                           class="regular-text" 
                           min="0" 
                           step="1000">
                    <p class="description"><?php _e('قیمت طرح به تومان', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="active_sessions"><?php _e('تعداد نشست‌های همزمان', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="active_sessions" 
                           name="active_sessions" 
                           value="<?php echo esc_attr($plan_data['active_sessions']); ?>" 
                           class="small-text" 
                           min="1" 
                           max="10">
                    <p class="description"><?php _e('تعداد نشست‌های همزمان مجاز', 'rolino'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="status"><?php _e('وضعیت', 'rolino'); ?></label>
                </th>
                <td>
                    <select id="status" name="status">
                        <option value="1" <?php selected($plan_data['status'], 1); ?>><?php _e('فعال', 'rolino'); ?></option>
                        <option value="0" <?php selected($plan_data['status'], 0); ?>><?php _e('غیرفعال', 'rolino'); ?></option>
                    </select>
                    <p class="description"><?php _e('وضعیت فعال یا غیرفعال بودن طرح', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="save-plan">
                <?php echo $submit_text; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=rolino-plans'); ?>" class="button">
                <?php _e('انصراف', 'rolino'); ?>
            </a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#rolino-plan-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=rolino_save_plan';
        
        $('#save-plan').prop('disabled', true).text('<?php _e('در حال ذخیره...', 'rolino'); ?>');
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = '<?php echo admin_url('admin.php?page=rolino-plans'); ?>';
                } else {
                    alert(response.data.message || '<?php _e('خطا در ذخیره طرح', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            },
            complete: function() {
                $('#save-plan').prop('disabled', false).text('<?php echo $submit_text; ?>');
            }
        });
    });
});
</script>