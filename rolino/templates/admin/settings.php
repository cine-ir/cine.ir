<?php
/**
 * Settings Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$single_buy_duration = get_option('rolino_single_buy_duration', 30);
$single_buy_credits = get_option('rolino_single_buy_credits', 5);
$single_buy_price = get_option('rolino_single_buy_price', 10000);
$single_buy_active = get_option('rolino_single_buy_active', 0);

$sms_enabled = get_option('rolino_sms_enabled', 0);
$sms_api_key = get_option('rolino_sms_api_key', '');
$sms_sender = get_option('rolino_sms_sender', '');

// Get gateway settings
$zarinpal_settings = get_option('rolino_gateway_zarinpal_settings', array());
$zarinpal_enabled = isset($zarinpal_settings['enabled']) ? $zarinpal_settings['enabled'] : 0;
$zarinpal_test_mode = isset($zarinpal_settings['test_mode']) ? $zarinpal_settings['test_mode'] : 0;
$zarinpal_merchant_id = isset($zarinpal_settings['merchant_id']) ? $zarinpal_settings['merchant_id'] : '';

$sample_settings = get_option('rolino_gateway_sample_settings', array());
$sample_enabled = isset($sample_settings['enabled']) ? $sample_settings['enabled'] : 0;

?>

<div class="wrap">
    <h1><?php _e('تنظیمات رولینو', 'rolino'); ?></h1>
    
    <form method="post" action="<?php echo admin_url('admin.php?page=rolino-settings'); ?>">
        <?php wp_nonce_field('rolino_settings', '_wpnonce'); ?>
        <input type="hidden" name="save_settings" value="1">
        
        <!-- Single Buy Settings -->
        <h2><?php _e('تنظیمات خرید تکی', 'rolino'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="single_buy_active"><?php _e('فعال‌سازی خرید تکی', 'rolino'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="single_buy_active" name="single_buy_active" value="1" <?php checked($single_buy_active, 1); ?>>
                        <?php _e('خرید تکی را فعال کن', 'rolino'); ?>
                    </label>
                    <p class="description"><?php _e('اجازه خرید اعتبار بدون نیاز به طرح اشتراک', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="single_buy_credits"><?php _e('تعداد اعتبار', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" id="single_buy_credits" name="single_buy_credits" value="<?php echo esc_attr($single_buy_credits); ?>" min="1" class="small-text">
                    <p class="description"><?php _e('تعداد اعتباری که در خرید تکی داده می‌شود', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="single_buy_duration"><?php _e('مدت اعتبار (روز)', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" id="single_buy_duration" name="single_buy_duration" value="<?php echo esc_attr($single_buy_duration); ?>" min="1" class="small-text">
                    <p class="description"><?php _e('تعداد روزهایی که اعتبار خرید تکی معتبر است', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="single_buy_price"><?php _e('قیمت (تومان)', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="number" id="single_buy_price" name="single_buy_price" value="<?php echo esc_attr($single_buy_price); ?>" min="0" class="regular-text">
                    <p class="description"><?php _e('قیمت خرید تکی به تومان', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- SMS Settings -->
        <h2><?php _e('تنظیمات SMS', 'rolino'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sms_enabled"><?php _e('فعال‌سازی SMS', 'rolino'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="sms_enabled" name="sms_enabled" value="1" <?php checked($sms_enabled, 1); ?>>
                        <?php _e('سیستم SMS را فعال کن', 'rolino'); ?>
                    </label>
                    <p class="description"><?php _e('ارسال پیامک یادآوری و اطلاع‌رسانی', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sms_api_key"><?php _e('کلید API', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="text" id="sms_api_key" name="sms_api_key" value="<?php echo esc_attr($sms_api_key); ?>" class="regular-text">
                    <p class="description"><?php _e('کلید API دریافتی از سرویس SMS', 'rolino'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sms_sender"><?php _e('شماره فرستنده', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="text" id="sms_sender" name="sms_sender" value="<?php echo esc_attr($sms_sender); ?>" class="regular-text">
                    <p class="description"><?php _e('شماره یا نام فرستنده SMS', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Gateway Settings -->
        <h2><?php _e('تنظیمات درگاه‌های پرداخت', 'rolino'); ?></h2>
        
        <!-- ZarinPal Gateway -->
        <h3><?php _e('زرین‌پال', 'rolino'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="zarinpal_enabled"><?php _e('فعال‌سازی زرین‌پال', 'rolino'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="zarinpal_enabled" name="gateway_zarinpal[enabled]" value="1" <?php checked($zarinpal_enabled, 1); ?>>
                        <?php _e('درگاه زرین‌پال را فعال کن', 'rolino'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zarinpal_test_mode"><?php _e('حالت تست', 'rolino'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="zarinpal_test_mode" name="gateway_zarinpal[test_mode]" value="1" <?php checked($zarinpal_test_mode, 1); ?>>
                        <?php _e('استفاده از محیط تست (sandbox)', 'rolino'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zarinpal_merchant_id"><?php _e('کد پذیرنده', 'rolino'); ?></label>
                </th>
                <td>
                    <input type="text" id="zarinpal_merchant_id" name="gateway_zarinpal[merchant_id]" value="<?php echo esc_attr($zarinpal_merchant_id); ?>" class="regular-text">
                    <p class="description"><?php _e('کد پذیرنده (Merchant ID) دریافتی از زرین‌پال', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Sample Gateway -->
        <h3><?php _e('درگاه نمونه', 'rolino'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sample_enabled"><?php _e('فعال‌سازی درگاه نمونه', 'rolino'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="sample_enabled" name="gateway_sample[enabled]" value="1" <?php checked($sample_enabled, 1); ?>>
                        <?php _e('درگاه نمونه برای تست (فقط برای ادمین)', 'rolino'); ?>
                    </label>
                    <p class="description"><?php _e('این درگاه فقط برای تست و آزمایش سیستم است', 'rolino'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('ذخیره تنظیمات', 'rolino')); ?>
    </form>
    
    <!-- System Information -->
    <h2><?php _e('اطلاعات سیستم', 'rolino'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('نسخه پلاگین:', 'rolino'); ?></th>
            <td><?php echo ROLINO_VERSION; ?></td>
        </tr>
        <tr>
            <th scope="row"><?php _e('نسخه WordPress:', 'rolino'); ?></th>
            <td><?php echo get_bloginfo('version'); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php _e('نسخه PHP:', 'rolino'); ?></th>
            <td><?php echo phpversion(); ?></td>
        </tr>
        <tr>
            <th scope="row"><?php _e('تعداد طرح‌ها:', 'rolino'); ?></th>
            <td>
                <?php
                $plans_obj = new Rolino_Plans();
                echo $plans_obj->get_plans_count();
                ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('تعداد کاربران دارای اشتراک:', 'rolino'); ?></th>
            <td>
                <?php
                global $wpdb;
                $active_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}rolino_credits WHERE end_time > NOW()");
                echo number_format($active_users);
                ?>
            </td>
        </tr>
    </table>
    
</div>