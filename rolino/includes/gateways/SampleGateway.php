<?php
/**
 * Sample Payment Gateway
 * 
 * A simple test gateway for development and testing purposes
 */

if (!defined('ABSPATH')) {
    exit;
}

class SampleGateway extends BasePaymentGateway {
    
    /**
     * Initialize gateway
     */
    protected function init() {
        $this->id = 'sample';
        $this->name = __('درگاه نمونه', 'rolino');
    }
    
    /**
     * Process payment request
     * 
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data) {
        $this->log('Starting sample payment process', 'info', $payment_data);
        
        // Simulate processing delay
        sleep(1);
        
        // Generate a fake transaction ID
        $transaction_id = 'SAMPLE_' . time() . '_' . rand(1000, 9999);
        
        // Create a redirect URL to simulate payment gateway
        $redirect_url = add_query_arg(array(
            'rolino_sample_payment' => '1',
            'transaction_id' => $payment_data['transaction_id'],
            'amount' => $payment_data['amount'],
            'sample_tx_id' => $transaction_id
        ), home_url('/'));
        
        $this->log('Sample payment request created', 'info', array(
            'sample_tx_id' => $transaction_id,
            'redirect_url' => $redirect_url
        ));
        
        return array(
            'success' => true,
            'redirect_url' => $redirect_url,
            'transaction_id' => $transaction_id,
            'data' => array(
                'sample_tx_id' => $transaction_id,
                'status' => 'pending'
            )
        );
    }
    
    /**
     * Verify payment
     * 
     * @param array $callback_data
     * @param object $transaction
     * @return array
     */
    public function verify_payment($callback_data, $transaction) {
        $this->log('Starting sample payment verification', 'info', $callback_data);
        
        $sample_tx_id = sanitize_text_field($callback_data['sample_tx_id'] ?? '');
        $status = sanitize_text_field($callback_data['status'] ?? '');
        
        if (empty($sample_tx_id)) {
            $this->log('Missing sample transaction ID', 'error', $callback_data);
            return array(
                'success' => false,
                'message' => __('شناسه تراکنش نمونه یافت نشد', 'rolino')
            );
        }
        
        // Simulate different payment outcomes based on transaction amount
        $amount = floatval($transaction->amount);
        
        if ($amount < 1000) {
            // Amounts under 1000 Toman fail
            $this->log('Sample payment failed (amount too low)', 'info', array('amount' => $amount));
            return array(
                'success' => false,
                'message' => __('مبلغ تراکنش کمتر از حد مجاز است', 'rolino')
            );
        }
        
        if ($status === 'cancel') {
            $this->log('Sample payment cancelled by user', 'info');
            return array(
                'success' => false,
                'message' => __('پرداخت توسط کاربر لغو شد', 'rolino')
            );
        }
        
        // Simulate successful payment
        $ref_id = 'REF_' . time() . '_' . rand(100000, 999999);
        
        $this->log('Sample payment verified successfully', 'info', array(
            'sample_tx_id' => $sample_tx_id,
            'ref_id' => $ref_id
        ));
        
        return array(
            'success' => true,
            'data' => array(
                'sample_tx_id' => $sample_tx_id,
                'ref_id' => $ref_id,
                'status' => 'completed',
                'gateway_response' => array(
                    'sample_gateway' => true,
                    'verification_time' => current_time('mysql')
                )
            )
        );
    }
    
    /**
     * Validate gateway settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_settings($settings) {
        // Sample gateway doesn't require any specific settings
        return true;
    }
    
    /**
     * Check if gateway is available
     * 
     * @return bool
     */
    public function is_available() {
        // Only available in test mode or for admins
        return $this->get_setting('enabled', false) && 
               ($this->get_setting('test_mode', true) || current_user_can('manage_options'));
    }
    
    /**
     * Get gateway settings fields
     * 
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'enabled' => array(
                'title' => __('فعال', 'rolino'),
                'type' => 'checkbox',
                'description' => __('فعال‌سازی درگاه نمونه (فقط برای تست)', 'rolino'),
                'default' => false
            ),
            'test_mode' => array(
                'title' => __('حالت تست', 'rolino'),
                'type' => 'checkbox',
                'description' => __('درگاه نمونه فقط در حالت تست قابل استفاده است', 'rolino'),
                'default' => true,
                'disabled' => true
            ),
            'title' => array(
                'title' => __('عنوان', 'rolino'),
                'type' => 'text',
                'description' => __('عنوان نمایشی درگاه پرداخت', 'rolino'),
                'default' => __('درگاه نمونه', 'rolino')
            ),
            'description' => array(
                'title' => __('توضیحات', 'rolino'),
                'type' => 'textarea',
                'description' => __('توضیحات نمایشی برای کاربران', 'rolino'),
                'default' => __('درگاه پرداخت نمونه برای تست (مبلغ کمتر از ۱۰۰۰ تومان ناموفق خواهد بود)', 'rolino')
            ),
            'debug' => array(
                'title' => __('حالت دیباگ', 'rolino'),
                'type' => 'checkbox',
                'description' => __('ذخیره لاگ‌های تراکنش‌ها', 'rolino'),
                'default' => true
            ),
            'simulate_failures' => array(
                'title' => __('شبیه‌سازی خطا', 'rolino'),
                'type' => 'checkbox',
                'description' => __('شبیه‌سازی خطاهای تصادفی (۲۰٪ احتمال)', 'rolino'),
                'default' => false
            )
        );
    }
    
    /**
     * Handle sample payment page
     */
    public static function handle_sample_payment_page() {
        if (!isset($_GET['rolino_sample_payment'])) {
            return;
        }
        
        $transaction_id = intval($_GET['transaction_id'] ?? 0);
        $amount = floatval($_GET['amount'] ?? 0);
        $sample_tx_id = sanitize_text_field($_GET['sample_tx_id'] ?? '');
        
        if (!$transaction_id || !$amount || !$sample_tx_id) {
            wp_die(__('پارامترهای تراکنش نامعتبر است', 'rolino'));
        }
        
        // Display sample payment page
        wp_head();
        ?>
        <div style="font-family: 'IRANYekan', Tahoma, Arial, sans-serif; direction: rtl; text-align: center; padding: 50px; background: #f5f5f5; min-height: 100vh;">
            <div style="background: white; max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h2 style="color: #333; margin-bottom: 30px;">درگاه پرداخت نمونه</h2>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                    <p><strong>مبلغ:</strong> <?php echo number_format($amount, 0, '', ','); ?> تومان</p>
                    <p><strong>شناسه تراکنش:</strong> <?php echo esc_html($sample_tx_id); ?></p>
                </div>
                
                <p style="color: #666; margin-bottom: 30px;">
                    این یک درگاه پرداخت نمونه است. برای تست سیستم استفاده می‌شود.
                </p>
                
                <div style="margin: 30px 0;">
                    <button onclick="processPayment('success')" style="background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; margin: 0 10px; cursor: pointer; font-size: 16px;">
                        پرداخت موفق
                    </button>
                    
                    <button onclick="processPayment('cancel')" style="background: #dc3545; color: white; padding: 15px 30px; border: none; border-radius: 5px; margin: 0 10px; cursor: pointer; font-size: 16px;">
                        لغو پرداخت
                    </button>
                </div>
                
                <p style="font-size: 12px; color: #999;">
                    نکته: مبلغ کمتر از ۱۰۰۰ تومان به صورت خودکار ناموفق خواهد بود.
                </p>
            </div>
        </div>
        
        <script>
        function processPayment(status) {
            var callbackUrl = '<?php echo home_url('/'); ?>?' + 
                'rolino_payment_callback=1&' +
                'gateway=sample&' +
                'transaction_id=<?php echo $transaction_id; ?>&' +
                'sample_tx_id=<?php echo esc_js($sample_tx_id); ?>&' +
                'status=' + status;
            
            window.location.href = callbackUrl;
        }
        </script>
        
        <?php
        wp_footer();
        exit;
    }
    
    /**
     * Process refund
     * 
     * @param object $transaction
     * @param float $amount
     * @return array
     */
    public function process_refund($transaction, $amount = null) {
        $this->log('Processing sample refund', 'info', array(
            'transaction_id' => $transaction->id,
            'amount' => $amount
        ));
        
        // Simulate refund processing
        if ($this->get_setting('simulate_failures', false) && rand(1, 5) === 1) {
            return array(
                'success' => false,
                'message' => __('بازگشت وجه ناموفق بود (خطای شبیه‌سازی شده)', 'rolino')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('بازگشت وجه با موفقیت انجام شد (شبیه‌سازی)', 'rolino'),
            'refund_id' => 'REFUND_' . time() . '_' . rand(1000, 9999)
        );
    }
    
    /**
     * Get supported currencies
     * 
     * @return array
     */
    public function get_supported_currencies() {
        return array('IRT', 'IRR', 'USD', 'EUR'); // Sample gateway supports multiple currencies
    }
    
    /**
     * Get test credentials
     * 
     * @return array
     */
    public function get_test_credentials() {
        return array(
            'message' => __('درگاه نمونه به تنظیمات خاصی نیاز ندارد. فقط آن را فعال کنید.', 'rolino')
        );
    }
}

// Initialize sample payment page handler
add_action('init', array('SampleGateway', 'handle_sample_payment_page'));