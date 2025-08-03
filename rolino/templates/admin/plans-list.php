<?php
/**
 * Plans List Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plans_admin = new Rolino_Plans_Admin();
$columns = $plans_admin->get_table_columns();
$sortable_columns = $plans_admin->get_sortable_columns();

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('طرح‌ها', 'rolino'); ?>
        <a href="<?php echo admin_url('admin.php?page=rolino-plans&action=add'); ?>" class="page-title-action">
            <?php _e('افزودن طرح جدید', 'rolino'); ?>
        </a>
    </h1>
    
    <hr class="wp-header-end">
    
    <form method="post" id="plans-filter">
        <?php wp_nonce_field('rolino_plans_bulk', '_wpnonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('عملیات گروهی', 'rolino'); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('عملیات گروهی', 'rolino'); ?></option>
                    <option value="activate"><?php _e('فعال کردن', 'rolino'); ?></option>
                    <option value="deactivate"><?php _e('غیرفعال کردن', 'rolino'); ?></option>
                    <option value="delete"><?php _e('حذف', 'rolino'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e('اعمال', 'rolino'); ?>">
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php echo sprintf(__('%s مورد', 'rolino'), number_format($total_plans)); ?>
                </span>
                
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;', 'rolino'),
                    'next_text' => __('&raquo;', 'rolino'),
                    'total' => ceil($total_plans / $per_page),
                    'current' => $page
                ));
                
                if ($page_links) {
                    echo '<span class="pagination-links">' . $page_links . '</span>';
                }
                ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped plans">
            <thead>
                <tr>
                    <?php foreach ($columns as $column_key => $column_display_name): ?>
                        <?php if ($column_key === 'cb'): ?>
                            <td id="cb" class="manage-column column-cb check-column">
                                <label class="screen-reader-text" for="cb-select-all-1"><?php _e('انتخاب همه', 'rolino'); ?></label>
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                        <?php else: ?>
                            <th scope="col" class="manage-column column-<?php echo esc_attr($column_key); ?>">
                                <?php if (isset($sortable_columns[$column_key])): ?>
                                    <a href="<?php echo esc_url(add_query_arg(array('orderby' => $column_key, 'order' => ($_GET['order'] ?? 'asc') === 'asc' ? 'desc' : 'asc'))); ?>">
                                        <span><?php echo $column_display_name; ?></span>
                                        <span class="sorting-indicator"></span>
                                    </a>
                                <?php else: ?>
                                    <?php echo $column_display_name; ?>
                                <?php endif; ?>
                            </th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            
            <tbody id="the-list">
                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <tr id="plan-<?php echo $plan->id; ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="plan_ids[]" value="<?php echo $plan->id; ?>">
                            </th>
                            
                            <td class="plan-name column-plan-name">
                                <strong>
                                    <a href="<?php echo admin_url("admin.php?page=rolino-plans&action=edit&plan_id={$plan->id}"); ?>">
                                        <?php echo esc_html($plan->plan_name); ?>
                                    </a>
                                </strong>
                                
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url("admin.php?page=rolino-plans&action=edit&plan_id={$plan->id}"); ?>">
                                            <?php _e('ویرایش', 'rolino'); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="delete-plan" data-plan-id="<?php echo $plan->id; ?>" data-plan-name="<?php echo esc_attr($plan->plan_name); ?>">
                                            <?php _e('حذف', 'rolino'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            
                            <td class="credits column-credits">
                                <strong><?php echo number_format($plan->credits); ?></strong>
                                <span class="credits-text"><?php _e('اعتبار', 'rolino'); ?></span>
                            </td>
                            
                            <td class="duration column-duration">
                                <?php echo number_format($plan->duration); ?> <?php _e('روز', 'rolino'); ?>
                            </td>
                            
                            <td class="price column-price">
                                <strong><?php echo $plans_admin->format_price($plan->price); ?></strong>
                            </td>
                            
                            <td class="active-sessions column-active-sessions">
                                <?php echo number_format($plan->active_sessions); ?>
                            </td>
                            
                            <td class="status column-status">
                                <label class="switch">
                                    <input type="checkbox" class="toggle-plan-status" 
                                           data-plan-id="<?php echo $plan->id; ?>" 
                                           <?php checked($plan->status, 1); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <?php echo $plans_admin->get_status_badge($plan->status); ?>
                            </td>
                            
                            <td class="created-at column-created-at">
                                <?php echo date_i18n('Y/m/d', strtotime($plan->created_at)); ?>
                            </td>
                            
                            <td class="actions column-actions">
                                <a href="<?php echo admin_url("admin.php?page=rolino-plans&action=edit&plan_id={$plan->id}"); ?>" 
                                   class="button button-small">
                                    <?php _e('ویرایش', 'rolino'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="<?php echo count($columns); ?>">
                            <?php _e('هیچ طرحی یافت نشد', 'rolino'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('عملیات گروهی', 'rolino'); ?></label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('عملیات گروهی', 'rolino'); ?></option>
                    <option value="activate"><?php _e('فعال کردن', 'rolino'); ?></option>
                    <option value="deactivate"><?php _e('غیرفعال کردن', 'rolino'); ?></option>
                    <option value="delete"><?php _e('حذف', 'rolino'); ?></option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="<?php _e('اعمال', 'rolino'); ?>">
            </div>
        </div>
    </form>
</div>

<script>
// Localize script for AJAX
var rolinoAdmin = {
    nonce: '<?php echo wp_create_nonce('rolino_admin_nonce'); ?>'
};

jQuery(document).ready(function($) {
    // Toggle plan status
    $('.toggle-plan-status').on('change', function() {
        var planId = $(this).data('plan-id');
        var status = $(this).is(':checked') ? 1 : 0;
        var $row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rolino_toggle_plan_status',
                plan_id: planId,
                status: status,
                nonce: rolinoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status badge
                    var badge = status ? 
                        '<span class="status-badge status-active"><?php echo esc_js(__('فعال', 'rolino')); ?></span>' :
                        '<span class="status-badge status-inactive"><?php echo esc_js(__('غیرفعال', 'rolino')); ?></span>';
                    
                    $row.find('.status .status-badge').replaceWith(badge);
                } else {
                    // Revert toggle on error
                    $(this).prop('checked', !status);
                    alert(response.data.message || '<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
                }
            }.bind(this),
            error: function() {
                // Revert toggle on error
                $(this).prop('checked', !status);
                alert('<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
            }.bind(this)
        });
    });
    
    // Delete plan
    $('.delete-plan').on('click', function(e) {
        e.preventDefault();
        
        var planId = $(this).data('plan-id');
        var planName = $(this).data('plan-name');
        var $row = $(this).closest('tr');
        
        if (confirm('<?php echo esc_js(__('آیا از حذف طرح', 'rolino')); ?> "' + planName + '" <?php echo esc_js(__('مطمئن هستید؟', 'rolino')); ?>')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rolino_delete_plan',
                    plan_id: planId,
                    nonce: rolinoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('خطایی رخ داد', 'rolino')); ?>');
                }
            });
        }
    });
    
    // Select all checkbox
    $('#cb-select-all-1').on('change', function() {
        $('input[name="plan_ids[]"]').prop('checked', $(this).is(':checked'));
    });
});
</script>

<style>
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
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
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
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
    border-radius: 34px;
}

.slider.round:before {
    border-radius: 50%;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-right: 10px;
}

.status-active {
    background: #46b450;
    color: white;
}

.status-inactive {
    background: #dc3232;
    color: white;
}

.credits-text {
    color: #666;
    font-size: 12px;
}

.row-actions {
    visibility: hidden;
}

tr:hover .row-actions {
    visibility: visible;
}
</style>