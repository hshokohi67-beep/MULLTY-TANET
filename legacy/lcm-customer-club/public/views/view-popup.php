<div id="lcm-glass-popup" class="lcm-popup-overlay" style="display: none;">
    <div class="lcm-popup-content">
        <span class="lcm-close-btn" id="lcm-close-popup">&times;</span>
        
        <div class="lcm-popup-header">
            <h3>باشگاه مشتریان کافه</h3>
            <p>شماره موبایل خود را وارد کنید تا وارد حساب کاربری یا کلوپ تخفیف شوید.</p>
        </div>
        
        <form id="lcm-club-form">
            <div class="lcm-input-group">
                <input type="tel" id="lcm_phone" name="phone" placeholder="شماره موبایل (الزامی)" required>
            </div>

            <div id="lcm-phone-locked-row" style="display:none; margin-top:8px; font-size:0.8rem; color:#aaa; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border-radius:8px; padding:6px 10px;">
                <span>کد برای شماره‌ی <strong id="lcm-locked-phone-display" style="color:var(--accent-color, #2ec4b6);"></strong> ارسال شد</span>
                <a href="#" id="lcm-edit-phone-link" style="color:#ffb703; font-weight:700; text-decoration:none;">✏️ ویرایش شماره</a>
            </div>

            <div id="lcm-new-user-fields" style="display: none; margin-top:15px;">
                <div class="lcm-input-group">
                    <input type="text" id="lcm_name" name="name" placeholder="نام و نام خانوادگی">
                </div>
                <div class="lcm-date-group">
                    <input type="number" id="lcm_birth_day" name="birth_day" placeholder="روز تولد" min="1" max="31">
                    <input type="number" id="lcm_birth_month" name="birth_month" placeholder="ماه تولد" min="1" max="12">
                </div>
                <?php
                $lcm_popup_groups = function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array();
                // گروه ورودی: پایین‌ترین سطحی که تعریف شده (یا اولین گروه، اگر آستانه‌ای تعریف نشده)
                $lcm_entry_group = null;
                if ( ! empty( $lcm_popup_groups ) ) {
                    $lcm_tiered = array_filter( $lcm_popup_groups, function($g) { return isset($g['min_spend']) && floatval($g['min_spend']) > 0; } );
                    if ( ! empty( $lcm_tiered ) ) {
                        usort( $lcm_tiered, function($a, $b) { return floatval($a['min_spend']) <=> floatval($b['min_spend']); } );
                        $lcm_entry_group = reset( $lcm_tiered );
                    } else {
                        $lcm_entry_group = $lcm_popup_groups[0];
                    }
                }
                $lcm_entry_slug = $lcm_entry_group ? $lcm_entry_group['slug'] : 'normal';
                $lcm_entry_label = $lcm_entry_group ? $lcm_entry_group['label'] : 'سبک زندگی عمومی / عادی';
                ?>
                <!-- 🔒 گروه دیگر توسط مشتری قابل انتخاب نیست؛ همه از سطح ورودی شروع می‌کنند و
                     بر اساس خرید واقعی خودکار ارتقا پیدا می‌کنند (قبلاً می‌شد مستقیم گروه پرتخفیف
                     را انتخاب کرد و بدون هیچ خریدی از تخفیف ویژه‌اش استفاده کرد). -->
                <input type="hidden" id="lcm_user_group" name="user_group" value="<?php echo esc_attr($lcm_entry_slug); ?>">
                <div style="background: rgba(46,196,182,0.08); border: 1px dashed #2ec4b6; border-radius: 10px; padding: 10px 12px; font-size: 0.75rem; color: #ccc; line-height:1.7;">
                    🌱 شما با سطح «<?php echo esc_html($lcm_entry_label); ?>» شروع می‌کنید.
                    با خریدهای بیشتر (یا خرید از دسته‌های خاص)، به‌طور خودکار به سطوح بالاتر ارتقا پیدا می‌کنید و از تخفیف‌های ویژه‌ی هر سطح بهره‌مند خواهید شد 🎁
                </div>
                <div class="lcm-input-group">
                    <input type="text" id="lcm_referred_by_code" name="referred_by_code" placeholder="کد معرف (اختیاری)" style="text-transform:uppercase;">
                </div>
            </div>

            <div id="lcmOtpFieldContainer" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05);">
                <input type="text" id="lcm_otp_code" placeholder="کد تایید پیامک‌شده را وارد کنید" style="width: 100%; padding: 10px; background: var(--ios-glass, #161a1d); border: 1px solid var(--accent-color, #2ec4b6); color: #fff; border-radius: 8px; text-align: center; font-weight: bold;" />
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:0.75rem;">
                    <span id="lcm-otp-countdown" style="color:#888;">کد تا <span id="lcm-otp-seconds">۱۲۰</span> ثانیه دیگر معتبر است</span>
                    <a href="#" id="lcm-resend-otp-link" style="display:none; color:var(--accent-color, #2ec4b6); font-weight:700; text-decoration:none;">ارسال مجدد کد 🔁</a>
                </div>
            </div>

            <button type="submit" class="lcm-submit-btn" id="lcmSubmitClubForm" style="margin-top:15px;">ادامه و دریافت کد 🚀</button>
        </form>
    </div>
</div>

<button id="lcm-open-popup-btn" class="lcm-floating-btn">عضویت در باشگاه</button>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const savedPhone = localStorage.getItem('lcm_user_phone');
    if (savedPhone) {
        document.getElementById('lcm-open-popup-btn').style.display = 'none';
    }
});
</script>