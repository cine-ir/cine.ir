<?php
/**
 * SMS Scenarios Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$sms_admin = new Rolino_SMS_Admin();
$available_variables = $sms_admin->get_available_variables();

?>

<div class="wrap">
    <h1><?php _e('سناریوهای SMS', 'rolino'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('راهنما:', 'rolino'); ?></strong></p>
        <p><?php _e('برای استفاده از متغیرهای پویا در پیام‌ها، از فرمت {{variable_name}} استفاده کنید.', 'rolino'); ?></p>
        <details>
            <summary><?php _e('مشاهده متغیرهای موجود', 'rolino'); ?></summary>
            <div class="variables-help">
                <h4><?php _e('متغیرهای نمایشی:', 'rolino'); ?></h4>
                <ul>
                    <?php foreach ($available_variables['user_variables'] as $var => $desc): ?>
                        <li><code>{{<?php echo $var; ?>}}</code> - <?php echo $desc; ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <h4><?php _e('متغیرهای عملیاتی (انجام عمل):', 'rolino'); ?></h4>
                <ul>
                    <?php foreach ($available_variables['action_variables'] as $var => $desc): ?>
                        <li><code>{{<?php echo $var; ?>}}</code> - <?php echo $desc; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    </div>
    
    <!-- Add New Scenario Form -->
    <div class="postbox" style="margin-bottom: 20px;">
        <h2 class="hndle"><?php _e('افزودن سناریو جدید', 'rolino'); ?></h2>
        <div class="inside">
            <form method="post" action="" id="add-scenario-form">
                <?php wp_nonce_field('rolino_add_scenario', '_wpnonce'); ?>
                <input type="hidden" name="add_scenario" value="1">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="scenario_type"><?php _e('نوع سناریو', 'rolino'); ?></label>
                        </th>
                        <td>
                            <select id="scenario_type" name="scenario_type" required>
                                <option value=""><?php _e('انتخاب کنید', 'rolino'); ?></option>
                                <?php foreach ($scenario_types as $type_id => $type_name): ?>
                                    <option value="<?php echo $type_id; ?>"><?php echo $type_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" id="scenario-description"></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="days_offset"><?php _e('فاصله (روز)', 'rolino'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="days_offset" 
                                   name="days_offset" 
                                   value="0" 
                                   min="0" 
                                   max="365" 
                                   class="small-text">
                            <p class="description"><?php _e('تعداد روز قبل یا بعد از رویداد', 'rolino'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="message_template"><?php _e('قالب پیام', 'rolino'); ?></label>
                        </th>
                        <td>
                            <textarea id="message_template" 
                                      name="message_template" 
                                      rows="4" 
                                      style="width: 100%;"
                                      placeholder="<?php _e('متن پیام را وارد کنید...', 'rolino'); ?>"
                                      required></textarea>
                            <p class="description"><?php _e('از متغیرهای پویا استفاده کنید، مثال: {{user_name}}', 'rolino'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e('وضعیت', 'rolino'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="status" 
                                       name="status" 
                                       value="1" 
                                       checked>
                                <?php _e('فعال', 'rolino'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('افزودن سناریو', 'rolino'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Existing Scenarios -->
    <div class="postbox">
        <h2 class="hndle"><?php _e('سناریوهای موجود', 'rolino'); ?></h2>
        <div class="inside">
            <form method="post" action="">
                <?php wp_nonce_field('rolino_sms_scenarios', '_wpnonce'); ?>
                <input type="hidden" name="save_scenarios" value="1">
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 200px;"><?php _e('نوع سناریو', 'rolino'); ?></th>
                            <th style="width: 120px;"><?php _e('فاصله (روز)', 'rolino'); ?></th>
                            <th><?php _e('قالب پیام', 'rolino'); ?></th>
                            <th style="width: 100px;"><?php _e('وضعیت', 'rolino'); ?></th>
                            <th style="width: 80px;"><?php _e('عملیات', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($scenarios)): ?>
                            <?php foreach ($scenarios as $scenario): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($scenario_types[$scenario->scenario_type] ?? __('نامشخص', 'rolino')); ?></strong>
                                        <p class="description"><?php echo $sms_admin->get_scenario_description($scenario->scenario_type); ?></p>
                                    </td>
                                    <td>
                                        <?php if (in_array($scenario->scenario_type, [1, 2, 3])): ?>
                                            <input type="number" 
                                                   name="scenarios[<?php echo $scenario->id; ?>][days_offset]" 
                                                   value="<?php echo esc_attr($scenario->days_offset); ?>" 
                                                   min="0" 
                                                   max="365" 
                                                   class="small-text">
                                        <?php else: ?>
                                            <span class="description"><?php _e('فوری', 'rolino'); ?></span>
                                            <input type="hidden" name="scenarios[<?php echo $scenario->id; ?>][days_offset]" value="">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <textarea name="scenarios[<?php echo $scenario->id; ?>][message_template]" 
                                                  rows="3" 
                                                  style="width: 100%;"
                                                  placeholder="<?php _e('متن پیام را وارد کنید...', 'rolino'); ?>"><?php echo esc_textarea($scenario->message_template); ?></textarea>
                                    </td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   name="scenarios[<?php echo $scenario->id; ?>][status]" 
                                                   value="1" 
                                                   <?php checked($scenario->status, 1); ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=rolino-sms-scenarios&action=delete&scenario_id=' . $scenario->id . '&_wpnonce=' . wp_create_nonce('delete_scenario')); ?>" 
                                           class="button button-small button-link-delete"
                                           onclick="return confirm('<?php _e('آیا مطمئن هستید که می‌خواهید این سناریو را حذف کنید؟', 'rolino'); ?>')">
                                            <?php _e('حذف', 'rolino'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5"><?php _e('هیچ سناریویی یافت نشد', 'rolino'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php submit_button(__('ذخیره سناریوها', 'rolino')); ?>
            </form>
        </div>
    </div>
    
    <!-- Test SMS Form -->
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle"><?php _e('تست ارسال SMS', 'rolino'); ?></h2>
        <div class="inside">
            <form id="test-sms-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_phone"><?php _e('شماره تلفن', 'rolino'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="test_phone" name="test_phone" class="regular-text" placeholder="09123456789">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_message"><?php _e('متن پیام', 'rolino'); ?></label>
                        </th>
                        <td>
                            <textarea id="test_message" name="test_message" rows="3" class="large-text" placeholder="<?php _e('متن پیام آزمایشی', 'rolino'); ?>"></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-secondary">
                        <?php _e('ارسال پیام آزمایشی', 'rolino'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
}

input:checked + .slider {
    background-color: #2196F3;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.slider.round {
    border-radius: 24px;
}

.slider.round:before {
    border-radius: 50%;
}

.variables-help {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}

.variables-help ul {
    margin: 0;
    padding-right: 20px;
}

.variables-help code {
    background: #e1e1e1;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Scenario type descriptions
    var scenarioDescriptions = {
        '1': '<?php _e('ارسال پیام قبل از انقضای اعتبار', 'rolino'); ?>',
        '2': '<?php _e('ارسال پیام بعد از انقضای اعتبار', 'rolino'); ?>',
        '3': '<?php _e('ارسال پیام قبل از انقضای اعتبار گروه', 'rolino'); ?>',
        '4': '<?php _e('ارسال پیام هنگام خرید طرح', 'rolino'); ?>',
        '5': '<?php _e('ارسال پیام هنگام خرید تکی', 'rolino'); ?>',
        '6': '<?php _e('ارسال پیام هنگام فعال‌سازی کد تخفیف', 'rolino'); ?>'
    };
    
    // Update scenario description when type changes
    $('#scenario_type').on('change', function() {
        var type = $(this).val();
        var description = scenarioDescriptions[type] || '';
        $('#scenario-description').text(description);
        
        // Show/hide days offset field based on scenario type
        if ([1, 2, 3].indexOf(parseInt(type)) !== -1) {
            $('#days_offset').closest('tr').show();
        } else {
            $('#days_offset').closest('tr').hide();
        }
    });
    
    // Add scenario form submission
    $('#add-scenario-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=rolino_save_sms_scenario&_wpnonce=' + $('#_wpnonce').val();
        
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.text();
        
        submitBtn.prop('disabled', true).text('<?php _e('در حال ذخیره...', 'rolino'); ?>');
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا در ذخیره سناریو', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test SMS form
    $('#test-sms-form').on('submit', function(e) {
        e.preventDefault();
        
        var phone = $('#test_phone').val();
        var message = $('#test_message').val();
        
        if (!phone || !message) {
            alert('<?php echo esc_js(__('شماره تلفن و متن پیام الزامی است', 'rolino')); ?>');
            return;
        }
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rolino_test_sms',
                phone: phone,
                message: message,
                _wpnonce: $('#_wpnonce').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
            }
        });
    });
});
</script>