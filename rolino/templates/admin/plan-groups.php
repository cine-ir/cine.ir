<?php
/**
 * Plan Groups Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plans_obj = new Rolino_Plans();
$all_groups = $plans_obj->get_plan_groups();
$all_plans = $plans_obj->get_plans(array('status' => 'all'));

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('گروه‌بندی طرح‌ها', 'rolino'); ?>
        <a href="#" id="add-new-group" class="page-title-action">
            <?php _e('افزودن گروه جدید', 'rolino'); ?>
        </a>
    </h1>
    
    <hr class="wp-header-end">
    
    <div class="rolino-plan-groups">
        
        <!-- Add New Group Form -->
        <div id="new-group-form" class="postbox" style="display: none;">
            <h2 class="hndle"><?php _e('گروه جدید', 'rolino'); ?></h2>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field('rolino_admin_action', '_wpnonce'); ?>
                    <input type="hidden" name="action" value="add_group">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="group_name"><?php _e('نام گروه', 'rolino'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="group_name" name="group_name" class="regular-text" required>
                                <p class="description"><?php _e('نام گروه برای نمایش در فرانت‌اند', 'rolino'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('ایجاد گروه', 'rolino'); ?>">
                        <a href="#" id="cancel-new-group" class="button"><?php _e('لغو', 'rolino'); ?></a>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Existing Groups -->
        <?php if (!empty($all_groups)): ?>
            <?php foreach ($all_groups as $group): ?>
                <div class="postbox group-<?php echo $group->id; ?>">
                    <h2 class="hndle">
                        <span><?php echo esc_html($group->group_name); ?></span>
                        <div class="group-actions">
                            <a href="#" class="delete-group" data-group-id="<?php echo $group->id; ?>" data-group-name="<?php echo esc_attr($group->group_name); ?>">
                                <?php _e('حذف', 'rolino'); ?>
                            </a>
                        </div>
                    </h2>
                    
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('rolino_admin_action', '_wpnonce'); ?>
                            <input type="hidden" name="action" value="update_group_plans">
                            <input type="hidden" name="group_id" value="<?php echo $group->id; ?>">
                            
                            <p class="description">
                                <?php _e('طرح‌هایی که می‌خواهید در این گروه قرار بگیرند را انتخاب کنید:', 'rolino'); ?>
                            </p>
                            
                            <div class="plans-checkboxes">
                                <?php
                                $group_plan_ids = $plans_obj->get_group_plan_ids($group->id);
                                ?>
                                
                                <?php foreach ($all_plans as $plan): ?>
                                    <label class="plan-checkbox">
                                        <input type="checkbox" 
                                               name="plan_ids[]" 
                                               value="<?php echo $plan->id; ?>"
                                               <?php checked(in_array($plan->id, $group_plan_ids)); ?>>
                                        <span class="plan-name"><?php echo esc_html($plan->plan_name); ?></span>
                                        <span class="plan-details">
                                            (<?php echo number_format($plan->credits); ?> اعتبار - 
                                            <?php echo number_format($plan->price); ?> تومان)
                                        </span>
                                        <span class="plan-status status-<?php echo $plan->status ? 'active' : 'inactive'; ?>">
                                            <?php echo $plan->status ? __('فعال', 'rolino') : __('غیرفعال', 'rolino'); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="submit">
                                <input type="submit" class="button-primary" value="<?php _e('به‌روزرسانی گروه', 'rolino'); ?>">
                            </p>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('هنوز گروهی ایجاد نشده است. برای شروع، گروه جدیدی ایجاد کنید.', 'rolino'); ?></p>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show new group form
    $('#add-new-group').on('click', function(e) {
        e.preventDefault();
        $('#new-group-form').slideDown();
        $('#group_name').focus();
    });
    
    // Hide new group form
    $('#cancel-new-group').on('click', function(e) {
        e.preventDefault();
        $('#new-group-form').slideUp();
        $('#group_name').val('');
    });
    
    // Delete group
    $('.delete-group').on('click', function(e) {
        e.preventDefault();
        
        var groupId = $(this).data('group-id');
        var groupName = $(this).data('group-name');
        
        if (confirm('<?php echo esc_js(__('آیا از حذف گروه', 'rolino')); ?> "' + groupName + '" <?php echo esc_js(__('مطمئن هستید؟ طرح‌های موجود در این گروه حذف نخواهند شد.', 'rolino')); ?>')) {
            window.location.href = '<?php echo admin_url('admin.php?page=rolino-plan-groups'); ?>&action=delete_group&group_id=' + groupId + '&_wpnonce=<?php echo wp_create_nonce('rolino_admin_action'); ?>';
        }
    });
});
</script>

<style>
.group-actions {
    float: left;
    margin-right: 10px;
}

.group-actions a {
    color: #a00;
    text-decoration: none;
    font-size: 12px;
}

.group-actions a:hover {
    color: #dc3232;
}

.plans-checkboxes {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: #f9f9f9;
}

.plan-checkbox {
    display: block;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.plan-checkbox:last-child {
    border-bottom: none;
}

.plan-checkbox:hover {
    background: #f0f0f0;
}

.plan-name {
    font-weight: bold;
    margin-left: 5px;
}

.plan-details {
    color: #666;
    font-size: 12px;
    margin-left: 10px;
}

.plan-status {
    float: left;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
}

.status-active {
    background: #46b450;
    color: white;
}

.status-inactive {
    background: #dc3232;
    color: white;
}
</style>