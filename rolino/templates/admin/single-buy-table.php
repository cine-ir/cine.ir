<?php
/**
 * Single Buy Table Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$members_admin = new Rolino_Members_Admin();
?>

<div class="wrap">
    <h1><?php _e('خریدهای تکی', 'rolino'); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=active'); ?>" 
           class="nav-tab">
            <?php _e('اعضای فعال', 'rolino'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=expired'); ?>" 
           class="nav-tab">
            <?php _e('منقضی شده', 'rolino'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=single_buy'); ?>" 
           class="nav-tab nav-tab-active">
            <?php _e('خرید تکی', 'rolino'); ?>
        </a>
    </nav>
    
    <!-- Single Buy Table -->
    <div class="postbox">
        <h2 class="hndle"><?php _e('کاربران خرید تکی', 'rolino'); ?></h2>
        <div class="inside">
            <?php if (!empty($users)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('کاربر', 'rolino'); ?></th>
                            <th><?php _e('ایمیل', 'rolino'); ?></th>
                            <th><?php _e('آخرین خرید', 'rolino'); ?></th>
                            <th><?php _e('مجموع اعتبار', 'rolino'); ?></th>
                            <th><?php _e('تعداد خرید', 'rolino'); ?></th>
                            <th><?php _e('وضعیت', 'rolino'); ?></th>
                            <th><?php _e('عملیات', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo $members_admin->format_date($user->latest_end_time); ?></td>
                                <td><?php echo number_format($user->total_credits); ?></td>
                                <td><?php echo number_format($user->purchase_count); ?></td>
                                <td><?php echo $members_admin->get_status_badge($user->latest_end_time); ?></td>
                                <td>
                                    <button type="button" 
                                            class="button button-small view-details" 
                                            data-user-id="<?php echo $user->user_id; ?>">
                                        <?php _e('جزئیات', 'rolino'); ?>
                                    </button>
                                    
                                    <button type="button" 
                                            class="button button-small button-link-delete delete-member" 
                                            data-user-id="<?php echo $user->user_id; ?>">
                                        <?php _e('حذف', 'rolino'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $current_url = admin_url('admin.php?page=rolino-members&tab=single_buy');
                            
                            if ($page > 1) {
                                echo '<a href="' . add_query_arg('paged', $page - 1, $current_url) . '" class="prev page-numbers">' . __('قبلی', 'rolino') . '</a>';
                            }
                            
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if ($i == $page) {
                                    echo '<span class="page-numbers current">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . add_query_arg('paged', $i, $current_url) . '" class="page-numbers">' . $i . '</a>';
                                }
                            }
                            
                            if ($page < $total_pages) {
                                echo '<a href="' . add_query_arg('paged', $page + 1, $current_url) . '" class="next page-numbers">' . __('بعدی', 'rolino') . '</a>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?php _e('هیچ کاربر خرید تکی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Member Details Modal -->
<div id="member-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('جزئیات کاربر', 'rolino'); ?></h2>
        <div id="member-details-content"></div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 5px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

.badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.badge-success {
    background-color: #d4edda;
    color: #155724;
}

.badge-warning {
    background-color: #fff3cd;
    color: #856404;
}

.badge-danger {
    background-color: #f8d7da;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View user details
    $('.view-details').on('click', function() {
        var userId = $(this).data('user-id');
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rolino_get_member_details',
                user_id: userId,
                nonce: rolino_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#member-details-content').html(response.data.html);
                    $('#member-details-modal').show();
                } else {
                    alert(response.data.message || '<?php _e('خطا در دریافت جزئیات', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            }
        });
    });
    
    // Delete user
    $('.delete-member').on('click', function() {
        if (!confirm('<?php _e('آیا مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟', 'rolino'); ?>')) {
            return;
        }
        
        var userId = $(this).data('user-id');
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rolino_delete_member',
                user_id: userId,
                nonce: rolino_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا در حذف کاربر', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            }
        });
    });
    
    // Modal close
    $('.close').on('click', function() {
        $('.modal').hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });
});
</script>