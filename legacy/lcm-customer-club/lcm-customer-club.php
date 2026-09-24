<?php
/**
 * Plugin Name: LCM Customer Club
 * Description: افزونه باشگاه مشتریان و مدیریت لید، دارای موتور قلقلک فروش فیتنس برای کافه‌داران.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: lcm-customer-club
 */

// جلوگیری از دسترسی مستقیم به فایل برای امنیت بیشتر
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function lcm_club_is_licensed() {
    $allowed_domains = array(
        'dentall-clinic.local',   // محیط توسعه لوکال
        // 'submsg.ir',      // دامنه‌ی واقعی خود را اینجا اضافه کنید
    );
    $current = strtolower( parse_url( home_url(), PHP_URL_HOST ) );
    $current = preg_replace('/^www\./', '', $current);
    foreach ( $allowed_domains as $domain ) {
        $domain = strtolower( preg_replace('/^www\./', '', $domain) );
        if ( $current === $domain ) { return true; }
    }
    return false;
}
if ( ! lcm_club_is_licensed() ) { return; } // بدون پیام خطا، فقط لود نمیشه

// تعریف ثابت‌ها برای دسترسی راحت به مسیر و آدرس افزونه
define( 'LCM_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCM_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ۱. فراخوانی کلاس دیتابیس
require_once LCM_CLUB_PLUGIN_DIR . 'includes/class-lcm-db.php';
register_activation_hook( __FILE__, array( 'LCM_DB', 'create_table' ) );
// آپدیت خودکار ساختار جدول برای سایت‌هایی که افزونه از قبل روی آن‌ها نصب بوده
add_action( 'plugins_loaded', array( 'LCM_DB', 'maybe_upgrade' ) );

// ۱.۵ موتور گروه‌های تخفیف پویا (جایگزین سیستم قدیمی ۳ گروه ثابت)
require_once LCM_CLUB_PLUGIN_DIR . 'includes/class-lcm-discounts.php';

// ۱.۶ موتور مشارکت مشتری: نشان‌های افتخار، چالش هفتگی، امتیاز وفاداری
require_once LCM_CLUB_PLUGIN_DIR . 'includes/class-lcm-engagement.php';

/**
 * ثبت هر تغییر کیف پول در جدول ریز تراکنش‌ها (قبلاً فقط عدد نهایی موجودی
 * ذخیره می‌شد و هیچ تاریخچه‌ای برای نمایش در پنل کاربری وجود نداشت).
 * $amount مثبت یعنی شارژ/کش‌بک، منفی یعنی کسر بابت سفارش.
 */
function lcm_log_wallet_transaction( $phone, $amount, $balance_after, $reason ) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'lcm_wallet_ledger',
        array(
            'phone'         => $phone,
            'amount'        => intval( $amount ),
            'balance_after' => intval( $balance_after ),
            'reason'        => $reason,
        ),
        array( '%s', '%d', '%d', '%s' )
    );
}

/**
 * ساخت یک کد معرف کوتاه و یکتا برای عضو جدید باشگاه
 */
function lcm_generate_referral_code( $phone ) {
    global $wpdb;
    do {
        $code = 'CAFE' . rand( 1000, 9999 );
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}lcm_club_members WHERE referral_code = %s", $code ) );
    } while ( $exists );
    return $code;
}

// ۲. فراخوانی کلاس پردازش Ajax
require_once LCM_CLUB_PLUGIN_DIR . 'includes/class-lcm-ajax.php';

// ۳. فراخوانی فایل‌های ادمین
// 🔒 نکته‌ی مهم: قبلاً این فایل فقط وقتی is_admin() درست باشد لود می‌شد. این برای
// درخواست‌های AJAX کار می‌کرد (چون admin-ajax.php خودش is_admin را true می‌کند)، ولی
// WP-Cron (کرون تولد) این‌طور نیست — یعنی موقع اجرای واقعی کرون تولد، کلاس LCM_Admin
// اصلاً وجود نداشت و ارسال پیامک تولد همیشه بی‌صدا نادیده گرفته می‌شد. لود بدون شرط
// این فایل کاملاً امن است چون هوک‌های admin_menu/admin_init آن، بیرون از پیشخوان
// وردپرس اصلاً اجرا نمی‌شوند.
require_once LCM_CLUB_PLUGIN_DIR . 'admin/class-lcm-admin.php';

// ۴. فراخوانی کلاس فرانت‌اند
require_once LCM_CLUB_PLUGIN_DIR . 'public/class-lcm-public.php';

// 🌟 فیکس نهایی: معرفی رسمی و بدون خطای آدرس Ajax به فرانت‌آند منوی آنلاین برای مشتریان
add_action( 'wp_enqueue_scripts', 'lcm_register_front_ajax_url', 99 );
function lcm_register_front_ajax_url() {
    // تزریق مستقیم آدرس پردازشگر وردپرس به کدهای اسکریپت منو جهت جلوگیری از خطای ارتباط با سرور
    wp_register_script( 'lcm-ajax-fixer', false );
    wp_enqueue_script( 'lcm-ajax-fixer' );
    wp_localize_script( 'lcm-ajax-fixer', 'lcm_ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' )
    ));
}

// پاک‌سازی کرون تولد هنگام غیرفعال‌سازی پلاگین (جلوگیری از باقی‌ماندن رویداد یتیم)
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'lcm_daily_birthday_check_hook' );
});

/* ==========================================================================
   خروج واقعی از حساب — قبلاً هیچ handler سمت سرور برای این عملیات وجود
   نداشت؛ دکمه‌ی «خروج» فقط localStorage مرورگر را پاک می‌کرد، ولی کوکی
   ورود وردپرس (wp_set_auth_cookie که هنگام تایید OTP ست می‌شود) هیچ‌وقت
   واقعاً پاک نمی‌شد — یعنی کاربر هنوز از نظر وردپرس وارد حساب بود.
========================================================================== */
add_action( 'wp_ajax_lcm_quick_logout',        'lcm_quick_logout' );
add_action( 'wp_ajax_nopriv_lcm_quick_logout', 'lcm_quick_logout' );
function lcm_quick_logout() {
    wp_logout();
    wp_send_json_success();
}

/* ==========================================================================
   ساخت کاربر وردپرس بعد از تایید OTP — اینجاست نه توی functions.php قالب
   چون پلاگین همیشه فعاله، مستقل از اینکه چه قالبی روی سایت نصبه.
========================================================================== */
add_action( 'wp_ajax_lcm_create_wp_user',        'lcm_create_wp_user' );
add_action( 'wp_ajax_nopriv_lcm_create_wp_user', 'lcm_create_wp_user' );
function lcm_create_wp_user() {
    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
    if ( empty( $phone ) ) { wp_send_json_error( 'شماره موبایل یافت نشد.' ); }

    // 🔒 بدون این چک، هر کسی می‌توانست بدون کد تایید وارد حساب دیگران شود
    if ( ! get_transient( 'lcm_otp_verified_' . $phone ) ) {
        wp_send_json_error( 'تایید هویت منقضی شده. لطفاً دوباره کد دریافت کنید.' );
    }
    delete_transient( 'lcm_otp_verified_' . $phone );

    $user = get_user_by( 'login', $phone );
    if ( ! $user ) {
        $user_id = wp_create_user( $phone, wp_generate_password(), $phone . '@example.com' );
        if ( is_wp_error( $user_id ) ) { wp_send_json_error( 'خطا در ساخت کاربر.' ); }
        ( new WP_User( $user_id ) )->set_role( 'subscriber' );
    } else {
        $user_id = $user->ID;
    }

    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id );
    wp_send_json_success( 'ورود با موفقیت انجام شد.' );
}