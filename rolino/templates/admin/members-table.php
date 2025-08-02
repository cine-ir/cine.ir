<?php
/**
 * Members Table Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$members_admin = new Rolino_Members_Admin();
$plans = new Rolino_Plans();
$all_plans = $plans->get_active_plans();
?>

<div class="wrap">
    <h1><?php _e('مدیریت اعضا', 'rolino'); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=active'); ?>" 
           class="nav-tab <?php echo $tab === 'active' ? 'nav-tab-active' : ''; ?>">
            <?php _e('اعضای فعال', 'rolino'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=expired'); ?>" 
           class="nav-tab <?php echo $tab === 'expired' ? 'nav-tab-active' : ''; ?>">
            <?php _e('منقضی شده', 'rolino'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=rolino-members&tab=single_buy'); ?>" 
           class="nav-tab <?php echo $tab === 'single_buy' ? 'nav-tab-active' : ''; ?>">
            <?php _e('خرید تکی', 'rolino'); ?>
        </a>
    </nav>
    
    <!-- Add Member Form -->
    <div class="postbox" style="margin: 20px 0;">
        <h2 class="hndle"><?php _e('افزودن عضو جدید', 'rolino'); ?></h2>
        <div class="inside">
            <form method="post" id="add-member-form">
                <?php wp_nonce_field('rolino_add_member', '_wpnonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_id"><?php _e('کاربر', 'rolino'); ?></label>
                        </th>
                        <td>
                            <select id="user_id" name="user_id" required>
                                <option value=""><?php _e('انتخاب کاربر', 'rolino'); ?></option>
                                <?php
                                $users = get_users(array('orderby' => 'display_name'));
                                foreach ($users as $user) {
                                    echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . ' (' . $user->user_email . ')</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="plan_id"><?php _e('طرح', 'rolino'); ?></label>
                        </th>
                        <td>
                            <select id="plan_id" name="plan_id" required>
                                <option value=""><?php _e('انتخاب طرح', 'rolino'); ?></option>
                                <?php foreach ($all_plans as $plan): ?>
                                    <option value="<?php echo $plan->id; ?>">
                                        <?php echo esc_html($plan->plan_name); ?> - <?php echo number_format($plan->price); ?> تومان
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                   value="1" 
                                   min="1" 
                                   class="small-text" 
                                   required>
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
                                   value="30" 
                                   min="1" 
                                   max="3650" 
                                   class="small-text" 
                                   required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('افزودن عضو', 'rolino'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Members Table -->
    <div class="postbox">
        <h2 class="hndle">
            <?php 
            if ($tab === 'active') {
                _e('اعضای فعال', 'rolino');
            } elseif ($tab === 'expired') {
                _e('اعضای منقضی شده', 'rolino');
            }
            ?>
        </h2>
        <div class="inside">
            <?php if (!empty($members)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('کاربر', 'rolino'); ?></th>
                            <th><?php _e('ایمیل', 'rolino'); ?></th>
                            <th><?php _e('آخرین انقضا', 'rolino'); ?></th>
                            <th><?php _e('مجموع اعتبار', 'rolino'); ?></th>
                            <th><?php _e('تعداد اشتراک', 'rolino'); ?></th>
                            <th><?php _e('وضعیت', 'rolino'); ?></th>
                            <th><?php _e('عملیات', 'rolino'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($member->display_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($member->user_email); ?></td>
                                <td><?php echo $members_admin->format_date($member->latest_end_time); ?></td>
                                <td><?php echo number_format($member->total_credits); ?></td>
                                <td><?php echo number_format($member->subscription_count); ?></td>
                                <td><?php echo $members_admin->get_status_badge($member->latest_end_time); ?></td>
                                <td>
                                    <button type="button" 
                                            class="button button-small view-details" 
                                            data-user-id="<?php echo $member->user_id; ?>">
                                        <?php _e('جزئیات', 'rolino'); ?>
                                    </button>
                                    
                                    <?php if ($tab === 'expired'): ?>
                                        <button type="button" 
                                                class="button button-small extend-membership" 
                                                data-user-id="<?php echo $member->user_id; ?>">
                                            <?php _e('تمدید', 'rolino'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" 
                                            class="button button-small button-link-delete delete-member" 
                                            data-user-id="<?php echo $member->user_id; ?>">
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
                            $current_url = admin_url('admin.php?page=rolino-members&tab=' . $tab);
                            
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
                <p><?php _e('هیچ عضوی یافت نشد', 'rolino'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Member Details Modal -->
<div id="member-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('جزئیات عضو', 'rolino'); ?></h2>
        <div id="member-details-content"></div>
    </div>
</div>

<!-- Extend Membership Modal -->
<div id="extend-membership-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><?php _e('تمدید عضویت', 'rolino'); ?></h2>
        <form id="extend-membership-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="extend_days"><?php _e('تعداد روز', 'rolino'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="extend_days" 
                               name="days" 
                               value="30" 
                               min="1" 
                               max="3650" 
                               class="small-text" 
                               required>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('تمدید', 'rolino'); ?>
                </button>
            </p>
        </form>
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
    // Add member form
    $('#add-member-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=rolino_save_member&nonce=' + rolino_ajax.nonce;
        
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
                    alert(response.data.message || '<?php _e('خطا در ذخیره عضو', 'rolino'); ?>');
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
    
    // View member details
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
    
    // Extend membership
    $('.extend-membership').on('click', function() {
        var userId = $(this).data('user-id');
        $('#extend-membership-form').data('user-id', userId);
        $('#extend-membership-modal').show();
    });
    
    $('#extend-membership-form').on('submit', function(e) {
        e.preventDefault();
        
        var userId = $(this).data('user-id');
        var days = $('#extend_days').val();
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rolino_extend_membership',
                user_id: userId,
                days: days,
                nonce: rolino_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا در تمدید عضویت', 'rolino'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'rolino'); ?>');
            }
        });
    });
    
    // Delete member
    $('.delete-member').on('click', function() {
        if (!confirm('<?php _e('آیا مطمئن هستید که می‌خواهید این عضو را حذف کنید؟', 'rolino'); ?>')) {
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
                    alert(response.data.message || '<?php _e('خطا در حذف عضو', 'rolino'); ?>');
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