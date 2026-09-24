<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LCM_DB {

    // هر وقت ساختار جدول عوض شود این عدد را افزایش بده تا آپدیت خودکار روی سایت‌های نصب‌شده هم اجرا شود
    const DB_VERSION = '1.4';

    /**
     * متد ساخت جدول دیتابیس اختصاصی باشگاه مشتریان
     */
    public static function create_table() {
        global $wpdb;

        // نام جدول با در نظر گرفتن پیشوند متغیر دیتابیس سایت
        $table_name = $wpdb->prefix . 'lcm_club_members';
        $ledger_table = $wpdb->prefix . 'lcm_wallet_ledger';
        $ratings_table = $wpdb->prefix . 'lcm_order_ratings';
        $likes_table = $wpdb->prefix . 'lcm_liked_items';
        $points_ledger_table = $wpdb->prefix . 'lcm_points_ledger';
        $challenge_claims_table = $wpdb->prefix . 'lcm_challenge_claims';

        // دریافت کاراکترست استاندارد سایت برای پشتیبانی درست از زبان فارسی
        $charset_collate = $wpdb->get_charset_collate();

        // دستور SQL ساخت جدول طبق فیلدهای تایید شده
        // نکته‌ی مهم: ستون wallet_balance قبلاً این‌جا تعریف نشده بود ولی در تمام کدهای
        // کیف پول استفاده می‌شد؛ یعنی روی نصب‌های جدید این ستون اصلاً وجود نداشت.
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            birth_day tinyint(2) DEFAULT NULL,
            birth_month tinyint(2) DEFAULT NULL,
            user_group varchar(100) DEFAULT NULL,
            wallet_balance bigint(20) NOT NULL DEFAULT 0,
            loyalty_points bigint(20) NOT NULL DEFAULT 0,
            avatar varchar(10) DEFAULT NULL,
            saved_address varchar(500) DEFAULT NULL,
            referral_code varchar(20) DEFAULT NULL,
            referred_by_code varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY phone (phone),
            UNIQUE KEY referral_code (referral_code)
        ) $charset_collate;";

        // جدول ریز تراکنش‌های کیف پول (قبلاً فقط عدد نهایی موجودی ذخیره می‌شد، بدون هیچ تاریخچه‌ای)
        $sql .= "CREATE TABLE $ledger_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(50) NOT NULL,
            amount bigint(20) NOT NULL,
            balance_after bigint(20) NOT NULL,
            reason varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY phone (phone)
        ) $charset_collate;";

        // جدول امتیازدهی مشتری به سفارش‌ها (ستون updated_at برای پنجره‌ی ویرایش ۲۴ ساعته اضافه شد)
        $sql .= "CREATE TABLE $ratings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            phone varchar(50) NOT NULL,
            stars tinyint(1) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id)
        ) $charset_collate;";

        // جدول جدید: پسندیدن آیتم‌های خاص از یک سفارش (مستقل از امتیاز کلی سفارش)
        $sql .= "CREATE TABLE $likes_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            phone varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_product (order_id, product_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        // ریز تراکنش‌های امتیاز وفاداری (مشابه دفتر کل کیف پول، برای جایزه‌ی چالش‌های هفتگی و نشان‌ها)
        $sql .= "CREATE TABLE $points_ledger_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(50) NOT NULL,
            amount bigint(20) NOT NULL,
            balance_after bigint(20) NOT NULL,
            reason varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY phone (phone)
        ) $charset_collate;";

        // ثبت اینکه هر مشتری برای کدام هفته‌ی تقویمی، جایزه‌ی چالش هفتگی را گرفته
        // (جلوگیری از گرفتن جایزه‌ی یک چالش، بیشتر از یک‌بار در هفته)
        $sql .= "CREATE TABLE $challenge_claims_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(50) NOT NULL,
            week_key varchar(20) NOT NULL,
            claimed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY phone_week (phone, week_key)
        ) $charset_collate;";

        // فراخوانی فایل داخلی وردپرس که تابع dbDelta را در خود دارد
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // اجرای دستور ساخت یا آپدیت جدول (dbDelta ستون‌های جدید را هم به جدول موجود اضافه می‌کند)
        dbDelta( $sql );

        update_option( 'lcm_club_db_version', self::DB_VERSION );
    }

    /**
     * روی سایت‌هایی که افزونه از قبل نصب بوده (پس activation hook دوباره اجرا نمی‌شود)
     * این متد چک می‌کند نسخه‌ی دیتابیس عقب نیست، و اگر بود دوباره dbDelta را اجرا می‌کند
     * تا ستون‌های جدید بدون از دست رفتن داده اضافه شود.
     */
    public static function maybe_upgrade() {
        if ( get_option( 'lcm_club_db_version' ) !== self::DB_VERSION ) {
            self::create_table();
        }
    }
}