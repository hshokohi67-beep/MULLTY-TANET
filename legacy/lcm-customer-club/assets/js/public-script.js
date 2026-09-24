jQuery(document).ready(function($) {
    $('#lcm-open-popup-btn').on('click', function() { $('#lcm-glass-popup').fadeIn(); });
    $('#lcm-close-popup').on('click', function() { $('#lcm-glass-popup').fadeOut(); });

    if (!localStorage.getItem('lcm_user_phone')) {
        $('#lcm-glass-popup').fadeIn();
    } else if (!document.cookie.includes('lcm_user_phone=') && !sessionStorage.getItem('lcm_cookie_healed')) {
        // کاربر قبلاً وارد شده بود ولی این کوکی (که برای اعمال تخفیف در رندر سرور لازم است) هنوز ست نشده
        // بدون یک رفرش، همین بازدید فعلی هنوز تخفیف را نمی‌بیند؛ پس یک‌بار خودکار رفرش می‌کنیم.
        const existingPhone = localStorage.getItem('lcm_user_phone');
        document.cookie = "lcm_user_phone=" + encodeURIComponent(existingPhone) + ";path=/;max-age=" + (60 * 60 * 24 * 180);
        sessionStorage.setItem('lcm_cookie_healed', '1');
        location.reload();
    }

    const ajaxUrl = (typeof lcmData !== 'undefined') ? lcmData.ajax_url : '/wp-admin/admin-ajax.php';
    let otpCountdownTimer = null;
    const OTP_LIFETIME_SECONDS = 120; // باید با مقدار واقعی در سمت سرور (۲ دقیقه) یکی باشد

    // ===================== تایمر شمارش معکوس اعتبار کد =====================
    function startOtpCountdown() {
        clearInterval(otpCountdownTimer);
        let secondsLeft = OTP_LIFETIME_SECONDS;
        $('#lcm-otp-countdown').show();
        $('#lcm-resend-otp-link').hide();
        $('#lcm-otp-seconds').text(secondsLeft.toLocaleString('fa-IR'));

        otpCountdownTimer = setInterval(function () {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(otpCountdownTimer);
                $('#lcm-otp-countdown').hide();
                $('#lcm-resend-otp-link').show();
            } else {
                $('#lcm-otp-seconds').text(secondsLeft.toLocaleString('fa-IR'));
            }
        }, 1000);
    }

    // ===================== بازگشت به مرحله‌ی وارد کردن شماره (رفع مشکل «راه برگشت ندارد») =====================
    function resetToPhoneStep() {
        clearInterval(otpCountdownTimer);
        $('#lcmOtpFieldContainer').slideUp();
        $('#lcm-new-user-fields').slideUp();
        $('#lcm-phone-locked-row').hide();
        $('#lcm_otp_code').val('');
        // 🔒 فیلد نام موقع نمایش فرم «عضو جدید» به‌صورت پویا required می‌شود؛ اگر این‌جا
        // required را برنداریم، فرم مخفی‌شده هنوز اعتبارسنجی HTML5 را رد نمی‌کند و دکمه‌ی
        // ارسال بدون هیچ خطای قابل‌مشاهده‌ای کار نمی‌کند (چون فیلد الزامی دیگر دیده نمی‌شود).
        $('#lcm_name').prop('required', false);
        $('#lcm_phone').prop('readonly', false).focus();
        $('#lcmSubmitClubForm').text('ادامه و دریافت کد 🚀').prop('disabled', false);
    }

    $('#lcm-edit-phone-link').on('click', function (e) {
        e.preventDefault();
        resetToPhoneStep();
    });

    // ارسال مجدد کد برای همان شماره (بدون نیاز به تایپ دوباره‌ی شماره)
    $('#lcm-resend-otp-link').on('click', function (e) {
        e.preventDefault();
        $('#lcm_otp_code').val('');
        $('#lcm-club-form').trigger('submit');
    });

    $('#lcm-club-form').on('submit', function(e) {
        e.preventDefault();

        const phone = $('#lcm_phone').val();
        const otp   = $('#lcm_otp_code').val();
        const name  = $('#lcm_name').val();
        const day   = $('#lcm_birth_day').val();
        const month = $('#lcm_birth_month').val();
        const group = $('#lcm_user_group').val();
        const referredByCode = $('#lcm_referred_by_code').val();
        const btn   = $('#lcmSubmitClubForm');
        
        btn.text('⏳').prop('disabled', true);

        let formData = new FormData();
        formData.append('action', 'lcm_save_club_member');
        formData.append('phone', phone);
        formData.append('otp', otp || '');
        formData.append('name', name);
        formData.append('birth_day', day);
        formData.append('birth_month', month);
        formData.append('user_group', group);
        formData.append('referred_by_code', referredByCode || '');

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                if (resData.data.step === 'ask_otp') {
                    alert(resData.data.message);

                    // شماره قفل می‌شود تا اشتباهی در همین مرحله عوض نشود؛ اگر اشتباه بود
                    // کاربر با «ویرایش شماره» به مرحله‌ی قبل برمی‌گردد.
                    $('#lcm_phone').prop('readonly', true);
                    $('#lcm-locked-phone-display').text(phone);
                    $('#lcm-phone-locked-row').css('display', 'flex');

                    $('#lcmOtpFieldContainer').slideDown();
                    startOtpCountdown();

                    if (resData.data.user_type === 'new') {
                        $('#lcm-new-user-fields').slideDown();
                        $('#lcm_name').prop('required', true);
                    }
                    btn.text('🚀 تایید نهایی و ورود').prop('disabled', false);
                } 
                else if (resData.data.step === 'completed') {
                    clearInterval(otpCountdownTimer);
                    alert(resData.data.message);
                    localStorage.setItem('lcm_user_phone', phone);
                    if (resData.data.name) { localStorage.setItem('lcm_user_name', resData.data.name); }
                    if (resData.data.referral_code) { localStorage.setItem('lcm_referral_code', resData.data.referral_code); }

                    // 🍪 کوکی سمت سرور (نه فقط localStorage) تا صفحه‌ی منو در همان بار اول
                    // بارگذاری (رندر PHP) بتواند گروه/تخفیف مشتری را تشخیص دهد.
                    document.cookie = "lcm_user_phone=" + encodeURIComponent(phone) + ";path=/;max-age=" + (60 * 60 * 24 * 180);
                    
                    let authData = new FormData();
                    authData.append('action', 'lcm_create_wp_user');
                    authData.append('phone', phone);
                    
                    fetch(ajaxUrl, { method: 'POST', body: authData })
                    .then(() => {
                        location.reload();
                    });
                }
            } else {
                const errMsg = (resData.data && resData.data.message) ? resData.data.message : 'خطای نامشخص';
                alert('خطا: ' + errMsg);

                // اگر کد اشتباه/منقضی بود، به‌جای گیر افتادن در همین مرحله، امکان اصلاح شماره یا
                // دریافت کد تازه را واضح نشان بده (رفع مشکل دوم: راه برگشتی نداشتن)
                if (otp) {
                    $('#lcm_otp_code').val('').focus();
                }
                btn.text('🚀 تایید نهایی و ورود').prop('disabled', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.text('ادامه و دریافت کد 🚀').prop('disabled', false);
        });
    }); // این براکت برای بستنِ on('submit') است
}); // این براکت برای بستنِ ready است
