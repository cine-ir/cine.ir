<?php
/**
 * ZarinPal Payment Gateway
 * 
 * Integration with ZarinPal payment service
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZarrinPalGateway extends BasePaymentGateway {
    
    const SANDBOX_REQUEST_URL = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentRequest.json';
    const LIVE_REQUEST_URL = 'https://api.zarinpal.com/pg/rest/WebGate/PaymentRequest.json';
    
    const SANDBOX_VERIFY_URL = 'https://sandbox.zarinpal.com/pg/rest/WebGate/PaymentVerification.json';
    const LIVE_VERIFY_URL = 'https://api.zarinpal.com/pg/rest/WebGate/PaymentVerification.json';
    
    const SANDBOX_PAYMENT_URL = 'https://sandbox.zarinpal.com/pg/StartPay/';
    const LIVE_PAYMENT_URL = 'https://www.zarinpal.com/pg/StartPay/';
    
    /**
     * Initialize gateway
     */
    protected function init() {
        $this->id = 'zarinpal';
        $this->name = __('زرین‌پال', 'rolino');
    }
    
    /**
     * Process payment request
     * 
     * @param array $payment_data
     * @return array
     */
    public function process_payment($payment_data) {
        $this->log('Starting payment process', 'info', $payment_data);
        
        $request_url = $this->get_setting('test_mode', true) ? self::SANDBOX_REQUEST_URL : self::LIVE_REQUEST_URL;
        
        $request_data = array(
            'MerchantID' => $this->get_setting('merchant_id'),
            'Amount' => $this->format_amount($payment_data['amount']),
            'Description' => $payment_data['description'],
            'CallbackURL' => $this->get_callback_url($payment_data['transaction_id']),
            'Email' => $payment_data['user_email'] ?? '',
            'Mobile' => $this->format_mobile($payment_data['user_phone'] ?? '')
        );
        
        $response = $this->make_request($request_url, $request_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        if ($response['code'] !== 200) {
            $this->log('Payment request failed', 'error', $response);
            return array(
                'success' => false,
                'message' => $this->get_error_message($response)
            );
        }
        
        $data = $response['data'];
        
        if (empty($data['Status']) || $data['Status'] !== 100) {
            $error_message = $this->get_zarinpal_error_message($data['Status'] ?? -1);
            $this->log('Payment request rejected', 'error', array('status' => $data['Status'], 'message' => $error_message));
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        $authority = $data['Authority'];
        $payment_url = $this->get_setting('test_mode', true) ? self::SANDBOX_PAYMENT_URL : self::LIVE_PAYMENT_URL;
        $redirect_url = $payment_url . $authority;
        
        $this->log('Payment request successful', 'info', array('authority' => $authority));
        
        return array(
            'success' => true,
            'redirect_url' => $redirect_url,
            'transaction_id' => $authority,
            'data' => $data
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
        $this->log('Starting payment verification', 'info', $callback_data);
        
        $authority = sanitize_text_field($callback_data['Authority'] ?? '');
        $status = sanitize_text_field($callback_data['Status'] ?? '');
        
        if (empty($authority)) {
            $this->log('Missing authority in callback', 'error', $callback_data);
            return array(
                'success' => false,
                'message' => __('کد تراکنش از درگاه دریافت نشد', 'rolino')
            );
        }
        
        if ($status !== 'OK') {
            $this->log('Payment cancelled by user', 'info', array('status' => $status));
            return array(
                'success' => false,
                'message' => __('پرداخت توسط کاربر لغو شد', 'rolino')
            );
        }
        
        $verify_url = $this->get_setting('test_mode', true) ? self::SANDBOX_VERIFY_URL : self::LIVE_VERIFY_URL;
        
        $verify_data = array(
            'MerchantID' => $this->get_setting('merchant_id'),
            'Authority' => $authority,
            'Amount' => $this->format_amount($transaction->amount)
        );
        
        $response = $this->make_request($verify_url, $verify_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        if ($response['code'] !== 200) {
            $this->log('Verification request failed', 'error', $response);
            return array(
                'success' => false,
                'message' => $this->get_error_message($response)
            );
        }
        
        $data = $response['data'];
        $verification_status = $data['Status'] ?? -1;
        
        if ($verification_status === 100 || $verification_status === 101) {
            // 100: Successful payment
            // 101: Already verified (duplicate verification)
            
            $ref_id = $data['RefID'] ?? '';
            
            $this->log('Payment verified successfully', 'info', array(
                'authority' => $authority,
                'ref_id' => $ref_id,
                'status' => $verification_status
            ));
            
            return array(
                'success' => true,
                'data' => array(
                    'authority' => $authority,
                    'ref_id' => $ref_id,
                    'status' => $verification_status,
                    'gateway_response' => $data
                )
            );
        } else {
            $error_message = $this->get_zarinpal_error_message($verification_status);
            
            $this->log('Payment verification failed', 'error', array(
                'status' => $verification_status,
                'message' => $error_message
            ));
            
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }
    
    /**
     * Validate gateway settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_settings($settings) {
        return $this->validate_required_settings($settings, array('merchant_id'));
    }
    
    /**
     * Get ZarinPal error message
     * 
     * @param int $status
     * @return string
     */
    private function get_zarinpal_error_message($status) {
        $error_messages = array(
            -1 => __('اطلاعات ارسال شده ناقص است', 'rolino'),
            -2 => __('IP یا مرچنت کد پذیرنده صحیح نیست', 'rolino'),
            -3 => __('با توجه به محدودیت‌های شاپرک امکان پردازش با موفقیت میسر نمی‌باشد', 'rolino'),
            -4 => __('سطح تایید پذیرنده پایین‌تر از سطح نقره‌ای است', 'rolino'),
            -11 => __('درخواست مورد نظر یافت نشد', 'rolino'),
            -12 => __('امکان ویرایش درخواست میسر نمی‌باشد', 'rolino'),
            -21 => __('هیچ نوع عملیات مالی برای این تراکنش یافت نشد', 'rolino'),
            -22 => __('تراکنش ناموفق یا لغو شده', 'rolino'),
            -33 => __('رقم تراکنش با رقم پرداخت شده مطابقت ندارد', 'rolino'),
            -34 => __('سقف تقسیم تراکنش از لحاظ تعداد یا مبلغ عبور کرده است', 'rolino'),
            -40 => __('اجازه دسترسی به متد مورد نظر وجود ندارد', 'rolino'),
            -41 => __('اطلاعات ارسال شده مربوط به AdditionalData غیرمعتبر است', 'rolino'),
            -42 => __('مدت زمان معتبر طول عمر شناسه پرداخت باید بین ۳۰ دقیقه تا ۴۵ روز باشد', 'rolino'),
            -54 => __('درخواست مورد نظر آرشیو شده است', 'rolino'),
            100 => __('پرداخت با موفقیت انجام شد', 'rolino'),
            101 => __('پرداخت انجام شده است اما قبلاً PaymentVerification بر روی این تراکنش انجام شده است', 'rolino')
        );
        
        return isset($error_messages[$status]) ? $error_messages[$status] : sprintf(__('خطای ناشناخته (%d)', 'rolino'), $status);
    }
    
    /**
     * Format mobile number for ZarinPal
     * 
     * @param string $mobile
     * @return string
     */
    private function format_mobile($mobile) {
        if (empty($mobile)) {
            return '';
        }
        
        // Remove all non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        // Format as Iranian mobile number
        if (strlen($mobile) == 11 && substr($mobile, 0, 2) == '09') {
            return $mobile;
        } elseif (strlen($mobile) == 10 && substr($mobile, 0, 1) == '9') {
            return '0' . $mobile;
        } elseif (strlen($mobile) == 13 && substr($mobile, 0, 3) == '989') {
            return '0' . substr($mobile, 2);
        }
        
        return $mobile;
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
                'description' => __('فعال‌سازی درگاه زرین‌پال', 'rolino'),
                'default' => false
            ),
            'test_mode' => array(
                'title' => __('حالت تست', 'rolino'),
                'type' => 'checkbox',
                'description' => __('استفاده از محیط تست زرین‌پال', 'rolino'),
                'default' => true
            ),
            'merchant_id' => array(
                'title' => __('کد پذیرنده', 'rolino'),
                'type' => 'text',
                'description' => __('کد پذیرنده (Merchant ID) دریافتی از زرین‌پال', 'rolino'),
                'required' => true
            ),
            'title' => array(
                'title' => __('عنوان', 'rolino'),
                'type' => 'text',
                'description' => __('عنوان نمایشی درگاه پرداخت', 'rolino'),
                'default' => __('زرین‌پال', 'rolino')
            ),
            'description' => array(
                'title' => __('توضیحات', 'rolino'),
                'type' => 'textarea',
                'description' => __('توضیحات نمایشی برای کاربران', 'rolino'),
                'default' => __('پرداخت امن از طریق زرین‌پال', 'rolino')
            ),
            'debug' => array(
                'title' => __('حالت دیباگ', 'rolino'),
                'type' => 'checkbox',
                'description' => __('ذخیره لاگ‌های تراکنش‌ها', 'rolino'),
                'default' => false
            )
        );
    }
    
    /**
     * Process refund
     * 
     * @param object $transaction
     * @param float $amount
     * @return array
     */
    public function process_refund($transaction, $amount = null) {
        // ZarinPal doesn't support automatic refunds via API
        return array(
            'success' => false,
            'message' => __('بازگشت وجه باید از طریق پنل زرین‌پال انجام شود', 'rolino')
        );
    }
    
    /**
     * Get supported currencies
     * 
     * @return array
     */
    public function get_supported_currencies() {
        return array('IRR', 'IRT'); // Iranian Rial and Toman
    }
    
    /**
     * Check if currency is supported
     * 
     * @param string $currency
     * @return bool
     */
    public function supports_currency($currency) {
        return in_array($currency, $this->get_supported_currencies());
    }
}