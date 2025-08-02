jQuery(document).ready(function($) {
    
    // Admin functionality
    if (typeof rolino_ajax !== 'undefined' && rolino_ajax.ajax_url) {
        
        // Confirm delete buttons
        $('.rolino-btn-danger[data-confirm]').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(rolino_ajax.strings.confirm_delete)) {
                window.location.href = $(this).attr('href');
            }
        });
        
        // Plan group management
        $('.rolino-plan-group-toggle').on('change', function() {
            var planId = $(this).data('plan-id');
            var groupId = $(this).data('group-id');
            var isChecked = $(this).is(':checked');
            
            $.ajax({
                url: rolino_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rolino_toggle_plan_group',
                    plan_id: planId,
                    group_id: groupId,
                    checked: isChecked ? 1 : 0,
                    nonce: rolino_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(rolino_ajax.strings.success, 'success');
                    } else {
                        showMessage(response.data.message || rolino_ajax.strings.error, 'error');
                    }
                },
                error: function() {
                    showMessage(rolino_ajax.strings.error, 'error');
                }
            });
        });
        
        // SMS scenario management
        $('.rolino-scenario-type').on('change', function() {
            var scenarioType = $(this).val();
            var daysOffsetField = $(this).closest('tr').find('.rolino-days-offset-field');
            
            // Show/hide days offset field based on scenario type
            if (scenarioType == '1' || scenarioType == '2' || scenarioType == '3') {
                daysOffsetField.show();
            } else {
                daysOffsetField.hide();
            }
        });
        
        // Coupon code validation
        $('#rolino-apply-coupon').on('click', function(e) {
            e.preventDefault();
            
            var couponCode = $('#rolino-coupon-code').val().trim();
            if (!couponCode) {
                showMessage('لطفا کد تخفیف را وارد کنید', 'error');
                return;
            }
            
            var button = $(this);
            var originalText = button.text();
            
            // Show loading state
            button.text('در حال بررسی...').addClass('rolino-loading');
            
            $.ajax({
                url: rolino_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rolino_apply_coupon',
                    coupon_code: couponCode,
                    plan_id: button.data('plan-id') || 0,
                    nonce: rolino_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('کد تخفیف اعمال شد!', 'success');
                        updatePricesWithDiscount(response.data);
                    } else {
                        showMessage(response.data.message || 'کد تخفیف نامعتبر است', 'error');
                    }
                },
                error: function() {
                    showMessage('خطا در اتصال به سرور', 'error');
                },
                complete: function() {
                    button.text(originalText).removeClass('rolino-loading');
                }
            });
        });
        
        // Plan purchase
        $('.rolino-buy-plan').on('click', function(e) {
            e.preventDefault();
            
            var planId = $(this).data('plan-id');
            var couponCode = $('#rolino-coupon-code').val().trim();
            var gateway = $('input[name="payment_gateway"]:checked').val() || 'zarinpal';
            
            var button = $(this);
            var originalText = button.html();
            
            // Show loading state
            button.html('<span class="rolino-spinner"></span> در حال پردازش...').addClass('rolino-loading');
            
            $.ajax({
                url: rolino_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rolino_buy_plan',
                    plan_id: planId,
                    coupon_code: couponCode,
                    gateway: gateway,
                    nonce: rolino_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            showMessage('تراکنش با موفقیت ایجاد شد', 'success');
                        }
                    } else {
                        showMessage(response.data.message || 'خطا در ایجاد تراکنش', 'error');
                    }
                },
                error: function() {
                    showMessage('خطا در اتصال به سرور', 'error');
                },
                complete: function() {
                    button.html(originalText).removeClass('rolino-loading');
                }
            });
        });
        
        // Single buy credit
        $('.rolino-buy-credit').on('click', function(e) {
            e.preventDefault();
            
            var creditAmount = $(this).data('credit-amount');
            var gateway = $('input[name="payment_gateway"]:checked').val() || 'zarinpal';
            
            var button = $(this);
            var originalText = button.html();
            
            // Show loading state
            button.html('<span class="rolino-spinner"></span> در حال پردازش...').addClass('rolino-loading');
            
            $.ajax({
                url: rolino_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rolino_buy_plan',
                    plan_id: 0, // Single buy
                    credit_amount: creditAmount,
                    gateway: gateway,
                    nonce: rolino_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            showMessage('تراکنش با موفقیت ایجاد شد', 'success');
                        }
                    } else {
                        showMessage(response.data.message || 'خطا در ایجاد تراکنش', 'error');
                    }
                },
                error: function() {
                    showMessage('خطا در اتصال به سرور', 'error');
                },
                complete: function() {
                    button.html(originalText).removeClass('rolino-loading');
                }
            });
        });
        
        // Form validation
        $('form.rolino-form').on('submit', function(e) {
            var isValid = true;
            
            $(this).find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showMessage('لطفا تمام فیلدهای الزامی را پر کنید', 'error');
            }
        });
        
        // Auto-save draft for SMS templates
        $('.rolino-sms-template').on('input', debounce(function() {
            var template = $(this).val();
            var scenarioId = $(this).data('scenario-id');
            
            if (template && scenarioId) {
                $.ajax({
                    url: rolino_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rolino_save_sms_draft',
                        scenario_id: scenarioId,
                        template: template,
                        nonce: rolino_ajax.nonce
                    }
                });
            }
        }, 2000));
    }
    
    // Helper functions
    function showMessage(message, type) {
        var alertClass = type === 'success' ? 'rolino-alert-success' : 'rolino-alert-error';
        var alert = $('<div class="rolino-alert ' + alertClass + '">' + message + '</div>');
        
        // Remove existing alerts
        $('.rolino-alert').remove();
        
        // Add new alert
        if ($('.rolino-admin-container').length) {
            $('.rolino-admin-container').prepend(alert);
        } else if ($('.rolino-plans-container').length) {
            $('.rolino-plans-container').prepend(alert);
        } else {
            $('body').prepend(alert);
        }
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                alert.fadeOut();
            }, 3000);
        }
    }
    
    function updatePricesWithDiscount(discountData) {
        $('.rolino-plan-item').each(function() {
            var planId = $(this).data('plan-id');
            var planDiscount = discountData.plans[planId];
            
            if (planDiscount) {
                var priceContainer = $(this).find('.rolino-plan-price');
                var originalPrice = parseFloat($(this).data('original-price'));
                var discountedPrice = originalPrice * (1 - planDiscount.discount_percent / 100);
                
                // Update current price
                priceContainer.find('.price-now').text(formatPrice(discountedPrice));
                
                // Show discount percentage
                if (priceContainer.find('.discount-percent').length === 0) {
                    priceContainer.append('<p class="discount-percent">' + planDiscount.discount_percent + '% تخفیف</p>');
                }
                
                // Show old price
                var oldPriceContainer = $(this).find('.rolino-plan-old-price');
                if (oldPriceContainer.length === 0) {
                    priceContainer.after('<div class="rolino-plan-old-price"><p>' + formatPrice(originalPrice) + '</p></div>');
                } else {
                    oldPriceContainer.find('p').text(formatPrice(originalPrice));
                }
                
                // Update savings calculation
                var savings = originalPrice - discountedPrice;
                $(this).find('.rolino-plan-value p:last-child').text('شما صرفه‌جویی: ' + formatPrice(savings));
            }
        });
    }
    
    function formatPrice(price) {
        return new Intl.NumberFormat('fa-IR').format(Math.round(price)) + ' تومان';
    }
    
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    // Initialize Alpine.js components if available
    if (typeof Alpine !== 'undefined') {
        // Admin components can be added here
        Alpine.start();
    }
    
    // Auto-hide admin notices
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);
    
    // Enhanced form interactions
    $('.rolino-form-control').on('focus', function() {
        $(this).closest('.rolino-form-group').addClass('focused');
    }).on('blur', function() {
        $(this).closest('.rolino-form-group').removeClass('focused');
    });
    
    // Responsive table handling
    function makeTablesResponsive() {
        $('.rolino-form-table').each(function() {
            if ($(this).width() > $(window).width()) {
                $(this).addClass('responsive');
            }
        });
    }
    
    makeTablesResponsive();
    $(window).on('resize', debounce(makeTablesResponsive, 250));
    
    // Initialize tooltips if needed
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-tooltip]').tooltip();
    }
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 50
            }, 1000);
        }
    });
    
    // Handle dynamic content loading
    $(document).on('click', '.rolino-load-more', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var page = parseInt(button.data('page')) + 1;
        var container = $(button.data('container'));
        
        button.text('در حال بارگذاری...').prop('disabled', true);
        
        $.ajax({
            url: rolino_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'rolino_load_more_content',
                page: page,
                type: button.data('type'),
                nonce: rolino_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    container.append(response.data.content);
                    button.data('page', page);
                    
                    if (!response.data.has_more) {
                        button.hide();
                    }
                }
            },
            complete: function() {
                button.text('بارگذاری بیشتر').prop('disabled', false);
            }
        });
    });
});