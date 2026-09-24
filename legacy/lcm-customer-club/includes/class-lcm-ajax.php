<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LCM_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_lcm_save_club_member', array( $this, 'save_member' ) );
        add_action( 'wp_ajax_nopriv_lcm_save_club_member', array( $this, 'save_member' ) ); 
        
        add_action( 'wp_ajax_lcm_get_live_wallet', array( $this, 'get_live_wallet' ) );
        add_action( 'wp_ajax_nopriv_lcm_get_live_wallet', array( $this, 'get_live_wallet' ) );
        add_action( 'wp_ajax_lcm_redeem_points_to_wallet', array( $this, 'redeem_points_to_wallet' ) );
        add_action( 'wp_ajax_nopriv_lcm_redeem_points_to_wallet', array( $this, 'redeem_points_to_wallet' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'add_cashback_on_order' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'deduct_wallet_on_order' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_upgrade_member_tier' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_upgrade_member_tier' ) );
    }

    /**
     * بعد از هر سفارش، بررسی می‌کند آیا مجموع خرید مشتری از آستانه‌ی یک سطح
     * وفاداری بالاتر (که در تب «گروه‌های تخفیف» تعریف شده) عبور کرده یا نه؛
     * اگر بله، گروه مشتری را خودکار ارتقا می‌دهد (هرگز خودکار تنزل نمی‌دهد).
     */
    public function maybe_upgrade_member_tier( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return;

        if ( function_exists( 'lcm_maybe_auto_upgrade_group' ) ) {
            lcm_maybe_auto_upgrade_group( $phone );
        }
        if ( function_exists( 'lcm_maybe_claim_weekly_challenge' ) ) {
            lcm_maybe_claim_weekly_challenge( $phone );
        }
    }

    /**
     * تبدیل امتیاز وفاداری به اعتبار کیف پول (پل ساده برای «خرج کردن امتیاز»
     * بدون نیاز به یک روش پرداخت کاملاً جدا و جدید در صفحه‌ی تسویه‌حساب)
     */
    public function redeem_points_to_wallet() {
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        if ( empty( $phone ) ) { wp_send_json_error( array( 'message' => 'شماره نامعتبر' ) ); }

        global $wpdb;
        $table = $wpdb->prefix . 'lcm_club_members';
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", $phone ) );
        if ( ! $member ) { wp_send_json_error( array( 'message' => 'کاربر پیدا نشد' ) ); }

        $points_per_toman = max( 1, intval( get_option( 'lcm_points_conversion_rate', 10 ) ) ); // هر ۱۰ امتیاز = ۱۰۰۰ تومان (پیش‌فرض)
        $available_points = intval( $member->loyalty_points );
        $min_redeem = $points_per_toman; // حداقل یک واحد قابل تبدیل
        if ( $available_points < $min_redeem ) {
            wp_send_json_error( array( 'message' => 'امتیاز کافی برای تبدیل ندارید.' ) );
        }

        $toman_value = intval( floor( $available_points / $points_per_toman ) * 1000 );
        $points_to_use = intval( floor( $available_points / $points_per_toman ) * $points_per_toman );
        $new_points_balance = $available_points - $points_to_use;
        $new_wallet_balance = intval( $member->wallet_balance ) + $toman_value;

        $wpdb->update( $table, array( 'loyalty_points' => $new_points_balance, 'wallet_balance' => $new_wallet_balance ), array( 'phone' => $phone ), array( '%d', '%d' ), array( '%s' ) );

        if ( function_exists( 'lcm_log_points_transaction' ) ) {
            lcm_log_points_transaction( $phone, -$points_to_use, $new_points_balance, 'تبدیل امتیاز به کیف پول' );
        }
        if ( function_exists( 'lcm_log_wallet_transaction' ) ) {
            lcm_log_wallet_transaction( $phone, $toman_value, $new_wallet_balance, 'تبدیل امتیاز وفاداری' );
        }

        wp_send_json_success( array(
            'message'        => 'تبدیل شد! ' . number_format( $toman_value ) . ' تومان به کیف پول شما اضافه شد 🎉',
            'new_points'     => $new_points_balance,
            'new_wallet'     => $new_wallet_balance,
        ) );
    }

    public function get_live_wallet() {
        if ( empty( $_POST['phone'] ) ) { wp_send_json_error(); }
        global $wpdb;
        $table_name = $wpdb->prefix . 'lcm_club_members';
        // ایمن‌سازی کوئری
        $member = $wpdb->get_row( $wpdb->prepare( "SELECT wallet_balance, user_group FROM $table_name WHERE phone = %s", sanitize_text_field($_POST['phone']) ) );

        if ( $member ) {
            $group = function_exists('lcm_get_discount_group_by_slug') ? lcm_get_discount_group_by_slug( $member->user_group ) : null;
            wp_send_json_success( array(
                'wallet' => intval( $member->wallet_balance ),
                'discount_label'   => $group ? $group['label'] : '',
                'discount_percent' => $group ? floatval( $group['percent'] ) : 0,
            ) );
        }
        wp_send_json_error();
    }

    public function save_member() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lcm_club_members';

    if ( empty( $_POST['phone'] ) ) {
        wp_send_json_error( array( 'message' => 'شماره موبایل الزامی است.' ) );
    }

    $phone = sanitize_text_field( $_POST['phone'] );
    $otp   = isset( $_POST['otp'] ) ? sanitize_text_field( $_POST['otp'] ) : '';

    // بررسی وجود کاربر
    $existing_member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE phone = %s", $phone ) );

    // ===================== مرحله ۱: درخواست کد تایید =====================
    if ( empty( $otp ) ) {

        // 🔒 محدودیت درخواست: حداکثر یک کد جدید هر ۴۵ ثانیه برای هر شماره (جلوگیری از اسپم/هزینه‌ی پیامک)
        if ( false !== get_transient( 'lcm_otp_cooldown_' . $phone ) ) {
            wp_send_json_error( array( 'message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.' ) );
        }

        // 🔒 محدودیت روزانه: حداکثر ۵ درخواست کد در روز برای هر شماره
        $daily_count = intval( get_transient( 'lcm_otp_daily_' . $phone ) );
        if ( $daily_count >= 5 ) {
            wp_send_json_error( array( 'message' => 'تعداد درخواست کد برای این شماره امروز به حد مجاز رسیده. فردا دوباره تلاش کنید.' ) );
        }

        // تولید کد ۴ رقمی تصادفی
        $generated_otp = str_pad( rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );

        // ذخیره کد با انقضا ۲ دقیقه (با استفاده از Transient)
        set_transient( 'lcm_otp_' . $phone, $generated_otp, 2 * MINUTE_IN_SECONDS );
        set_transient( 'lcm_otp_cooldown_' . $phone, 1, 45 );
        set_transient( 'lcm_otp_daily_' . $phone, $daily_count + 1, DAY_IN_SECONDS );

        // ======================== تشخیص محیط (لوکال یا واقعی) ========================
        $is_local = ( strpos( $_SERVER['HTTP_HOST'], 'localhost' ) !== false ||
                      strpos( $_SERVER['HTTP_HOST'], '.local' ) !== false ||
                      strpos( $_SERVER['HTTP_HOST'], '127.0.0.1' ) !== false );

        if ( $is_local ) {
            // روی لوکال: کد ۴ رقمی تولید و نشون می‌دیم
            $generated_otp = str_pad( rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
            set_transient( 'lcm_otp_' . $phone, $generated_otp, 2 * MINUTE_IN_SECONDS );
            wp_send_json_success( array(
                'step'         => 'ask_otp',
                'user_type'    => $existing_member ? 'old' : 'new',
                'message'      => 'کد تایید شما: ' . $generated_otp . ' (فقط برای تست لوکال)',
                'otp_for_test' => $generated_otp
            ) );
        } else {
            // روی سایت واقعی: سرویس Trez.ir کد رو خودش تولید و می‌فرسته
            $sent = $this->send_otp_via_sms( $phone, '' );
            if ( ! $sent ) {
                // اگه سرویس در دسترس نبود، به روش قدیمی برگرد
                $generated_otp = str_pad( rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
                set_transient( 'lcm_otp_' . $phone, $generated_otp, 2 * MINUTE_IN_SECONDS );
                if ( class_exists('LCM_Admin') ) {
                    LCM_Admin::send_sms( $phone, 'کد تایید باشگاه مشتریان: ' . $generated_otp );
                }
            }
            wp_send_json_success( array(
                'step'      => 'ask_otp',
                'user_type' => $existing_member ? 'old' : 'new',
                'message'   => 'کد تایید برای شما ارسال شد. لطفاً کد را وارد کنید.'
            ) );
        }
    }

    // ===================== مرحله ۲: بررسی کد تایید =====================
    $is_local = ( strpos( $_SERVER['HTTP_HOST'], 'localhost' ) !== false ||
                  strpos( $_SERVER['HTTP_HOST'], '.local' ) !== false ||
                  strpos( $_SERVER['HTTP_HOST'], '127.0.0.1' ) !== false );

    $otp_valid = false;
    if ( $is_local ) {
        // لوکال: از transient چک می‌کنیم (سرویس پیامک در دسترس نیست)
        $stored_otp = get_transient( 'lcm_otp_' . $phone );
        $otp_valid = ( $stored_otp && $otp === $stored_otp );
    } else {
        // سرور واقعی: از سرویس Trez.ir چک می‌کنیم
        $sms_result = $this->verify_otp_via_sms( $phone, $otp );
        if ( $sms_result === null ) {
            // سرویس پیامک در دسترس نیست یا SOAP نداریم — به transient fallback می‌کنیم
            $stored_otp = get_transient( 'lcm_otp_' . $phone );
            $otp_valid = ( $stored_otp && $otp === $stored_otp );
        } else {
            $otp_valid = $sms_result;
        }
    }

    if ( ! $otp_valid ) {
        wp_send_json_error( array( 'message' => 'کد تایید اشتباه است یا منقضی شده.' ) );
    }

    // کد درست بود → پاک‌سازی
    delete_transient( 'lcm_otp_' . $phone );

    // 🔒 ثبت «تایید شده» موقت برای این شماره تا مرحله‌ی ورود/ساخت کاربر وردپرس
    // (بدون این پرچم، هرکسی می‌توانست با صرفاً فرستادن یک شماره دلخواه به
    // اکشن جداگانه‌ی ساخت کاربر، بدون وارد کردن کد تایید، به‌جای آن شخص وارد شود)
    set_transient( 'lcm_otp_verified_' . $phone, 1, 2 * MINUTE_IN_SECONDS );

    $name        = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $birth_day   = isset( $_POST['birth_day'] ) ? absint( $_POST['birth_day'] ) : null;
    $birth_month = isset( $_POST['birth_month'] ) ? absint( $_POST['birth_month'] ) : null;

    // 🔒 گروه هیچ‌وقت از ورودی کاربر خوانده نمی‌شود (قبلاً می‌شد مستقیم درخواست AJAX
    // فرستاد و یک گروه پرتخفیف دلخواه را برای عضو جدید ثبت کرد). عضو جدید همیشه از
    // سطح ورودی واقعی (کمترین آستانه‌ی خرید، یا اولین گروه در صورت نبود آستانه) شروع می‌کند.
    $all_groups = function_exists('lcm_get_discount_groups') ? lcm_get_discount_groups() : array();
    $tiered_groups = array_filter( $all_groups, function($g) { return isset($g['min_spend']) && floatval($g['min_spend']) > 0; } );
    if ( ! empty( $tiered_groups ) ) {
        usort( $tiered_groups, function($a, $b) { return floatval($a['min_spend']) <=> floatval($b['min_spend']); } );
        $entry_group = reset( $tiered_groups );
    } else {
        $entry_group = ! empty( $all_groups ) ? $all_groups[0] : null;
    }
    $user_group = $entry_group ? $entry_group['slug'] : 'normal';

    if ( $existing_member ) {
        wp_send_json_success( array( 
            'step'    => 'completed',
            'name'    => $existing_member->name,
            'wallet'  => intval( $existing_member->wallet_balance ),
            'referral_code' => $existing_member->referral_code,
            'message' => 'خوش آمدید! حساب کاربری شما همگام‌سازی شد.'
        ) );
    } else {
        $my_referral_code = function_exists('lcm_generate_referral_code') ? lcm_generate_referral_code( $phone ) : '';
        $referred_by_code  = isset( $_POST['referred_by_code'] ) ? strtoupper( sanitize_text_field( $_POST['referred_by_code'] ) ) : '';
        $referrer = null;
        if ( ! empty( $referred_by_code ) ) {
            $referrer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE referral_code = %s", $referred_by_code ) );
        }

        $wpdb->insert( $table_name, array(
            'name'             => $name,
            'phone'            => $phone,
            'birth_day'        => $birth_day,
            'birth_month'      => $birth_month,
            'user_group'       => $user_group,
            'wallet_balance'   => 0,
            'referral_code'    => $my_referral_code,
            'referred_by_code' => $referrer ? $referred_by_code : null,
        ));

        $welcome_message = 'ثبت‌نام شما با موفقیت انجام شد!';

        // 🎁 پاداش معرفی: هم به معرفی‌کننده و هم به عضو جدید، در صورت تعریف مقدار پاداش در تنظیمات
        $referral_reward = floatval( get_option( 'lcm_referral_reward', 0 ) );
        $new_wallet_balance = 0;
        if ( $referrer && $referral_reward > 0 ) {
            $new_wallet_balance = $referral_reward;
            $wpdb->update( $table_name, array( 'wallet_balance' => $new_wallet_balance ), array( 'phone' => $phone ), array('%d'), array('%s') );
            if ( function_exists('lcm_log_wallet_transaction') ) {
                lcm_log_wallet_transaction( $phone, $referral_reward, $new_wallet_balance, 'هدیه‌ی خوش‌آمد معرفی' );
            }

            $referrer_new_balance = intval( $referrer->wallet_balance ) + $referral_reward;
            $wpdb->update( $table_name, array( 'wallet_balance' => $referrer_new_balance ), array( 'phone' => $referrer->phone ), array('%d'), array('%s') );
            if ( function_exists('lcm_log_wallet_transaction') ) {
                lcm_log_wallet_transaction( $referrer->phone, $referral_reward, $referrer_new_balance, 'پاداش معرفی دوست' );
            }

            $welcome_message = sprintf( 'ثبت‌نام شما با موفقیت انجام شد! %s تومان هدیه‌ی خوش‌آمد به کیف پولتان اضافه شد 🎁', number_format($referral_reward) );
        }

        wp_send_json_success( array( 
            'step'    => 'completed',
            'name'    => $name,
            'wallet'  => $new_wallet_balance,
            'referral_code' => $my_referral_code,
            'message' => $welcome_message
        ) );
    }
}

    private function send_otp_via_sms( $phone, $otp_code ) {
        // این سرویس از SOAP استفاده می‌کند — اگر PHP SOAP موجود نباشد، چیزی ارسال نمی‌شود
        if ( ! class_exists( 'SoapClient' ) ) { return false; }

        $username = get_option( 'lcm_sms_api_key' );    // توی تنظیمات: نام کاربری سامانه
        $password = get_option( 'lcm_sms_sender_num' ); // توی تنظیمات: رمز عبور سامانه

        if ( empty($username) || empty($password) ) { return false; }

        try {
            $client = new SoapClient(
                'http://smspanel.trez.ir/FastSend.asmx?WSDL',
                array( 'cache_wsdl' => WSDL_CACHE_NONE, 'connection_timeout' => 10 )
            );
            $client->AutoSendCode(array(
                'Username'         => $username,
                'Password'         => $password,
                'ReciptionNumber'  => $phone,
                'Footer'           => '',
            ));
            return true;
        } catch ( Exception $e ) {
            error_log( 'LCM SMS Error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * تایید کد OTP از طریق سرویس Trez.ir
     * این سرویس کد را خودش ذخیره کرده، پس چک‌کردن را هم به خودش می‌سپاریم.
     */
    private function verify_otp_via_sms( $phone, $otp_code ) {
        if ( ! class_exists( 'SoapClient' ) ) { return null; } // null = نمی‌دانیم

        $username = get_option( 'lcm_sms_api_key' );
        $password = get_option( 'lcm_sms_sender_num' );
        if ( empty($username) || empty($password) ) { return null; }

        try {
            $client = new SoapClient(
                'http://smspanel.trez.ir/FastSend.asmx?WSDL',
                array( 'cache_wsdl' => WSDL_CACHE_NONE, 'connection_timeout' => 10 )
            );
            $result = $client->CheckSendCode(array(
                'Username'        => $username,
                'Password'        => $password,
                'ReciptionNumber' => $phone,
                'Code'            => $otp_code,
            ));
            return (bool) $result->CheckSendCodeResult;
        } catch ( Exception $e ) {
            error_log( 'LCM OTP Verify Error: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * کسر خودکار کیف پول وقتی سفارش با روش «کیف پول باشگاه» ثبت می‌شه
     */
public function deduct_wallet_on_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // 🔒 جلوگیری از کسر دوباره: اگر کیف پول قبلاً برای همین سفارش کسر شده
    // (مثلاً همان لحظه‌ی ساخت سفارش در افزونه‌ی منو)، دوباره کسر نکن.
    if ( $order->get_meta('_lcm_wallet_deducted') !== '' ) {
        return;
    }

    $payment_method = $order->get_meta('_lcm_payment_method');

    if ( $payment_method !== 'wallet' && $payment_method !== 'کیف پول باشگاه' ) {
        return;
    }

    $phone = $order->get_billing_phone();
    if ( empty($phone) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'lcm_club_members';

    $member = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE phone = %s", $phone) );
    if ( ! $member ) return;

    $current_balance = intval($member->wallet_balance);
    $order_total     = floatval($order->get_total());

    if ($current_balance <= 0) {
        $order->add_order_note('موجودی کیف پول صفر بود.');
        return;
    }

    $amount_to_deduct = min($current_balance, $order_total);
    $remaining        = $order_total - $amount_to_deduct;
    $new_balance      = $current_balance - $amount_to_deduct;

    // کسر از کیف پول
    $wpdb->update(
        $table,
        ['wallet_balance' => $new_balance],
        ['phone' => $phone],
        ['%d'],
        ['%s']
    );

    if ( function_exists('lcm_log_wallet_transaction') ) {
        lcm_log_wallet_transaction( $phone, -$amount_to_deduct, $new_balance, 'کسر بابت سفارش #' . $order_id );
    }

    // یادداشت واضح
    $note = sprintf(
        '✅ مبلغ %s تومان از کیف پول کسر شد. موجودی جدید: %s تومان',
        number_format($amount_to_deduct),
        number_format($new_balance)
    );

    if ($remaining > 0) {
        $note .= sprintf(' | مابقی مبلغ (%s تومان) باید از طریق پرداخت آنلاین تسویه شود.', number_format($remaining));
        $order->update_meta_data('_lcm_remaining_amount', $remaining);
        $order->update_meta_data('_lcm_payment_type', 'partial_wallet');
        
        // تغییر روش پرداخت برای نمایش بهتر در فاکتور
        $order->update_meta_data('_payment_method_title', 'کیف پول باشگاه + پرداخت آنلاین');
    } else {
        $order->update_meta_data('_lcm_payment_type', 'full_wallet');
        $order->update_meta_data('_payment_method_title', 'کیف پول باشگاه');
    }

    $order->add_order_note($note);
    $order->update_meta_data('_lcm_wallet_deducted', $amount_to_deduct);

    // تغییر وضعیت به Processing (حتی اگر جزئی باشد)
    if ($order->get_status() === 'pending') {
        $order->update_status('processing', 'پرداخت جزئی با کیف پول انجام شد');
    }

    $order->save();
}
/**
 * اضافه کردن خودکار کش‌بک به کیف پول بعد از سفارش
 */
public function add_cashback_on_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    // 🔒 جلوگیری از اعطای چندباره‌ی کش‌بک برای یک سفارش. بدون این چک، اگر
    // وضعیت سفارش بیش از یک‌بار به «در حال آماده‌سازی» تغییر کند (مثلاً با
    // درگاه پرداخت واقعی که ممکن است این هوک را دوباره صدا بزند)، کش‌بک
    // هر بار دوباره به کیف پول اضافه می‌شد.
    if ( $order->get_meta('_lcm_cashback_given') === 'yes' ) {
        return;
    }

    $phone = $order->get_billing_phone();
    if ( empty( $phone ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lcm_club_members';

    $member = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_name WHERE phone = %s",
        $phone
    ) );

    if ( ! $member ) {
        return;
    }

    // گرفتن قوانین کش‌بک
    $rules = get_option( 'lcm_cashback_multi_rules', array() );
    if ( empty( $rules ) || ! is_array( $rules ) ) {
        return;
    }

    // 🔒 رفع باگ اصلی: قبلاً مبلغ هر آیتم به‌تنهایی با «حداقل مبلغ» مقایسه می‌شد.
    // چون هر محصول در این سیستم به‌صورت جداگانه (هر عدد، یک ردیف با تعداد ۱) به
    // سفارش اضافه می‌شود، قیمت یک اسموتی یا شیک تنها تقریباً هیچ‌وقت به یک آستانه‌ی
    // معقول (مثلاً «۱۰۰ هزار تومان خرید از این دسته») نمی‌رسید و کش‌بک عملاً هیچ‌وقت
    // فعال نمی‌شد. الان مجموع خرید مشتری در هر دسته‌بندی، در کل همین سفارش، جمع
    // می‌شود و با آستانه مقایسه می‌شود.
    $spend_per_category = array();
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
        if ( is_wp_error( $categories ) ) { continue; }

        foreach ( $categories as $cat_slug ) {
            if ( ! isset( $spend_per_category[ $cat_slug ] ) ) { $spend_per_category[ $cat_slug ] = 0; }
            $spend_per_category[ $cat_slug ] += floatval( $item->get_total() );
        }
    }

    $total_cashback = 0;
    foreach ( $rules as $rule ) {
        if ( empty( $rule['category_slug'] ) || empty( $rule['min_spend'] ) || empty( $rule['reward'] ) ) {
            continue;
        }

        $spent_in_this_category = isset( $spend_per_category[ $rule['category_slug'] ] ) ? $spend_per_category[ $rule['category_slug'] ] : 0;
        if ( $spent_in_this_category >= floatval( $rule['min_spend'] ) ) {
            $total_cashback += floatval( $rule['reward'] );
        }
    }

    if ( $total_cashback <= 0 ) {
        return;
    }

    // اضافه کردن کش‌بک به کیف پول
    $new_balance = intval( $member->wallet_balance ) + $total_cashback;

    $wpdb->update(
        $table_name,
        array( 'wallet_balance' => $new_balance ),
        array( 'phone' => $phone ),
        array( '%d' ),
        array( '%s' )
    );

    if ( function_exists('lcm_log_wallet_transaction') ) {
        lcm_log_wallet_transaction( $phone, $total_cashback, $new_balance, 'کش‌بک سفارش #' . $order_id );
    }

    // ثبت یادداشت در سفارش
    $order->add_order_note( sprintf(
        '🎁 کش‌بک به مبلغ %s تومان به کیف پول اضافه شد. موجودی جدید: %s تومان',
        number_format( $total_cashback ),
        number_format( $new_balance )
    ) );

    $order->update_meta_data( '_lcm_cashback_given', 'yes' );
    $order->save();
}

} // ← این } آخر کلاس LCM_Ajax هست

// اجرای کلاس Ajax
new LCM_Ajax();