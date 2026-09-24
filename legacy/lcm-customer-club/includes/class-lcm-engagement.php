<?php
/**
 * موتور «مشارکت مشتری»: نشان‌های افتخار، چالش هفتگی، و امتیاز وفاداری.
 * همه‌چیز بر پایه‌ی داده‌های واقعی سفارش‌ها محاسبه می‌شود، نه یک عدد ساختگی.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ثبت هر تغییر امتیاز وفاداری در جدول ریز تراکنش‌ها (مشابه دفتر کل کیف پول)
 */
function lcm_log_points_transaction( $phone, $amount, $balance_after, $reason ) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'lcm_points_ledger',
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
 * کلید هفته‌ی تقویمی جاری (برای جلوگیری از دریافت جایزه‌ی یک چالش بیش از یک‌بار در هفته)
 */
function lcm_get_current_week_key() {
    return date( 'o-\WW' ); // مثلاً 2026-W29
}

/**
 * محاسبه‌ی نشان‌های افتخار یک مشتری بر اساس تاریخچه‌ی واقعی سفارش‌ها.
 * چیزی ذخیره نمی‌شود؛ هر بار از نو و زنده محاسبه می‌شود.
 */
function lcm_compute_user_badges( $phone ) {
    if ( empty( $phone ) || ! class_exists( 'WooCommerce' ) ) { return array(); }

    $wp_user = get_user_by( 'login', $phone );
    if ( ! $wp_user ) { return array(); }

    $orders = wc_get_orders( array(
        'customer_id' => $wp_user->ID,
        'status'      => array( 'processing', 'completed' ),
        'limit'       => -1,
    ) );

    if ( empty( $orders ) ) { return array(); }

    $total_orders = count( $orders );
    $early_bird_count = 0;   // قبل از ساعت ۹ صبح
    $night_owl_count  = 0;   // بعد از ساعت ۹ شب
    $coffee_count     = 0;
    $protein_count    = 0;
    $cold_drink_count = 0;
    $birthday_order   = false;

    global $wpdb;
    $member = $wpdb->get_row( $wpdb->prepare( "SELECT birth_day, birth_month FROM {$wpdb->prefix}lcm_club_members WHERE phone = %s", $phone ) );

    foreach ( $orders as $order ) {
        $created = $order->get_date_created();
        if ( ! $created ) { continue; }

        $hour = intval( $created->date_i18n( 'G' ) );
        if ( $hour < 9 ) { $early_bird_count++; }
        if ( $hour >= 21 ) { $night_owl_count++; }

        if ( $member && $member->birth_day && $member->birth_month ) {
            if ( intval( $created->date_i18n( 'j' ) ) === intval( $member->birth_day ) && intval( $created->date_i18n( 'n' ) ) === intval( $member->birth_month ) ) {
                $birthday_order = true;
            }
        }

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $cats ) ) { continue; }

            if ( array_intersect( $cats, array( 'coffee', 'قهوه', 'hot-drinks', 'گرم' ) ) ) { $coffee_count++; }
            if ( array_intersect( $cats, array( 'protein', 'diet', 'رژیمی', 'پروتئین' ) ) ) { $protein_count++; }
            if ( array_intersect( $cats, array( 'cold-drinks', 'سرد', 'iced' ) ) ) { $cold_drink_count++; }
        }
    }

    $ratings_count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}lcm_order_ratings WHERE phone = %s", $phone ) ) );
    $referral_count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}lcm_club_members WHERE referred_by_code = (SELECT referral_code FROM {$wpdb->prefix}lcm_club_members WHERE phone = %s)", $phone ) ) );

    $badges = array();
    if ( $total_orders >= 20 )        { $badges[] = array( 'key' => 'loyal',    'icon' => '🥇', 'label' => 'مشتری وفادار',        'desc' => 'بیش از ۲۰ سفارش از کافه ما' ); }
    if ( $early_bird_count >= 5 )     { $badges[] = array( 'key' => 'early',    'icon' => '☀️', 'label' => 'سحرخیز',              'desc' => '۵ سفارش قبل از ساعت ۹ صبح' ); }
    if ( $night_owl_count >= 5 )      { $badges[] = array( 'key' => 'night',    'icon' => '🌙', 'label' => 'پرنده‌ی شب',           'desc' => '۵ سفارش بعد از ساعت ۹ شب' ); }
    if ( $coffee_count >= 10 )        { $badges[] = array( 'key' => 'coffee',   'icon' => '☕', 'label' => 'قهوه‌خور حرفه‌ای',      'desc' => '۱۰ سفارش قهوه/نوشیدنی گرم' ); }
    if ( $protein_count >= 5 )        { $badges[] = array( 'key' => 'protein',  'icon' => '🏋️', 'label' => 'عاشق پروتئین',        'desc' => '۵ سفارش از دسته‌ی پروتئینی/رژیمی' ); }
    if ( $cold_drink_count >= 5 )     { $badges[] = array( 'key' => 'cold',     'icon' => '🧊', 'label' => 'طرفدار نوشیدنی سرد',   'desc' => '۵ سفارش نوشیدنی سرد' ); }
    if ( $birthday_order )            { $badges[] = array( 'key' => 'birthday', 'icon' => '🎂', 'label' => 'جشن با ما',           'desc' => 'روز تولدتان را با ما جشن گرفتید' ); }
    if ( $ratings_count >= 5 )        { $badges[] = array( 'key' => 'critic',   'icon' => '🌟', 'label' => 'منتقد سازنده',        'desc' => '۵ نظر برای سفارش‌های خود ثبت کردید' ); }
    if ( $referral_count >= 1 )       { $badges[] = array( 'key' => 'friend',   'icon' => '💝', 'label' => 'دوست خوب',            'desc' => 'یک دوست را به کافه دعوت کردید' ); }

    return $badges;
}

/**
 * خواندن چالش هفتگی فعال (تعریف‌شده توسط مدیر کافه)
 */
function lcm_get_active_weekly_challenge() {
    $challenge = get_option( 'lcm_weekly_challenge', null );
    if ( ! is_array( $challenge ) || empty( $challenge['category_slug'] ) || empty( $challenge['target_count'] ) ) {
        return null;
    }
    return $challenge;
}

/**
 * پیشرفت مشتری در چالش هفتگی فعال (تعداد سفارش‌های واجد شرایط در همین هفته‌ی جاری)
 */
function lcm_get_challenge_progress( $phone, $challenge ) {
    $wp_user = get_user_by( 'login', $phone );
    if ( ! $wp_user || ! class_exists( 'WooCommerce' ) ) { return 0; }

    $week_start = date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );

    $orders = wc_get_orders( array(
        'customer_id'  => $wp_user->ID,
        'status'       => array( 'processing', 'completed' ),
        'date_created' => '>=' . strtotime( $week_start ),
        'limit'        => -1,
    ) );

    $count = 0;
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            $cats = wp_get_post_terms( $item->get_product_id(), 'product_cat', array( 'fields' => 'slugs' ) );
            if ( ! is_wp_error( $cats ) && in_array( $challenge['category_slug'], $cats, true ) ) {
                $count++;
                break; // هر سفارش حداکثر یک‌بار حساب شود، نه به تعداد آیتم‌های مشابه داخلش
            }
        }
    }
    return $count;
}

/**
 * اگر مشتری به هدف چالش این هفته رسیده و هنوز جایزه‌اش را نگرفته، امتیاز را اعطا کن.
 * برمی‌گرداند: true اگر همین الان جایزه داده شد (برای نمایش یک پیام تبریک لحظه‌ای)
 */
function lcm_maybe_claim_weekly_challenge( $phone ) {
    $challenge = lcm_get_active_weekly_challenge();
    if ( ! $challenge ) { return false; }

    $progress = lcm_get_challenge_progress( $phone, $challenge );
    if ( $progress < intval( $challenge['target_count'] ) ) { return false; }

    global $wpdb;
    $week_key = lcm_get_current_week_key();
    $claims_table = $wpdb->prefix . 'lcm_challenge_claims';

    $already_claimed = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $claims_table WHERE phone = %s AND week_key = %s", $phone, $week_key ) );
    if ( $already_claimed ) { return false; }

    $inserted = $wpdb->insert( $claims_table, array( 'phone' => $phone, 'week_key' => $week_key ), array( '%s', '%s' ) );
    if ( ! $inserted ) { return false; }

    $reward_points = intval( $challenge['points_reward'] );
    $member_table = $wpdb->prefix . 'lcm_club_members';
    $current_points = intval( $wpdb->get_var( $wpdb->prepare( "SELECT loyalty_points FROM $member_table WHERE phone = %s", $phone ) ) );
    $new_points = $current_points + $reward_points;

    $wpdb->update( $member_table, array( 'loyalty_points' => $new_points ), array( 'phone' => $phone ), array( '%d' ), array( '%s' ) );
    lcm_log_points_transaction( $phone, $reward_points, $new_points, 'جایزه‌ی چالش هفتگی: ' . $challenge['label'] );

    return true;
}

/* ==========================================================================
   تبریک و هدیه‌ی خودکار تولد
   هر روز یک‌بار (با WP-Cron) چک می‌کند کدام اعضا امروز تولدشان است،
   به کیف پولشان هدیه اضافه می‌کند، و از طریق پیامک/اعلان مرورگر خبرشان می‌کند.
========================================================================== */
add_action( 'init', 'lcm_schedule_birthday_cron' );
function lcm_schedule_birthday_cron() {
    if ( ! wp_next_scheduled( 'lcm_daily_birthday_check_hook' ) ) {
        // ساعت ۹ صبح به وقت سایت شروع می‌شود
        $tomorrow_9am = strtotime( 'tomorrow 09:00', current_time( 'timestamp' ) );
        wp_schedule_event( $tomorrow_9am, 'daily', 'lcm_daily_birthday_check_hook' );
    }
}
add_action( 'lcm_daily_birthday_check_hook', 'lcm_run_daily_birthday_check' );

function lcm_run_daily_birthday_check() {
    if ( ! get_option( 'lcm_birthday_gift_enabled', '0' ) ) { return; }
    if ( ! function_exists( 'lcm_gregorian_to_jalali' ) ) { return; } // از افزونه‌ی منو تامین می‌شود

    global $wpdb;
    $table = $wpdb->prefix . 'lcm_club_members';
    $members = $wpdb->get_results( "SELECT * FROM $table WHERE birth_day IS NOT NULL AND birth_month IS NOT NULL" );
    if ( empty( $members ) ) { return; }

    list( $today_jy, $today_jm, $today_jd ) = lcm_gregorian_to_jalali( (int) current_time('Y'), (int) current_time('n'), (int) current_time('j') );
    $this_year_key = $today_jy; // برای جلوگیری از تکرار در همان سال شمسی

    $gift_amount = intval( get_option( 'lcm_birthday_gift_amount', 0 ) );
    $cafe_name = get_option( 'lcm_cafe_name', 'کافه' );

    foreach ( $members as $member ) {
        if ( intval( $member->birth_day ) !== intval( $today_jd ) || intval( $member->birth_month ) !== intval( $today_jm ) ) {
            continue;
        }

        // جلوگیری از ارسال تکراری در همان سال
        $already_sent_key = 'lcm_birthday_sent_' . $member->phone . '_' . $this_year_key;
        if ( get_option( $already_sent_key ) ) { continue; }
        update_option( $already_sent_key, 1, false );

        $message = "🎉 {$cafe_name}: تولدت مبارک! ";

        if ( $gift_amount > 0 ) {
            $new_balance = intval( $member->wallet_balance ) + $gift_amount;
            $wpdb->update( $table, array( 'wallet_balance' => $new_balance ), array( 'phone' => $member->phone ), array( '%d' ), array( '%s' ) );
            if ( function_exists( 'lcm_log_wallet_transaction' ) ) {
                lcm_log_wallet_transaction( $member->phone, $gift_amount, $new_balance, 'هدیه‌ی تولد 🎂' );
            }
            $message .= number_format( $gift_amount ) . ' تومان هدیه به کیف پولت اضافه شد. منتظرتیم! ☕';
        } else {
            $message .= 'امروز رو با یه فنجون قهوه با ما جشن بگیر! ☕';
        }

        // اعلان مرورگر (در صورت نصب افزونه‌ی منو و داشتن اشتراک فعال)
        if ( function_exists( 'lcm_send_push_to_phone' ) ) {
            lcm_send_push_to_phone( $member->phone, $cafe_name, $message );
        }

        // پیامک (در صورت تنظیم بودن پنل پیامکی)
        if ( class_exists( 'LCM_Admin' ) ) {
            LCM_Admin::send_sms( $member->phone, $message );
        }
    }
}
