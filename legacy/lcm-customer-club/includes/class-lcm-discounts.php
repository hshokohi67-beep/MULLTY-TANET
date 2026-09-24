<?php
/**
 * موتور «گروه‌های تخفیف پویا» — جایگزین سیستم قدیمی که فقط ۳ گروه ثابت
 * (بدنساز/رژیمی/عادی) داشت و تخفیف را با حدس‌زدن کلمه در اسم محصول اعمال می‌کرد.
 *
 * حالا مدیر کافه می‌تواند هر تعداد گروه دلخواه بسازد، برای هرکدام یک رنگ و
 * درصد تخفیف مشخص کند، و دقیقاً انتخاب کند این تخفیف روی کدام دسته‌بندی‌ها
 * یا کدام محصولات مشخص اعمال شود.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * خواندن لیست گروه‌های تخفیف. اولین بار که فراخوانی می‌شود، اگر تنظیمات
 * قدیمی (۳ گروه ثابت) مقداری داشتند، به‌صورت خودکار به ساختار جدید منتقل می‌کند
 * تا تنظیمات قبلی مدیر کافه از بین نرود.
 */
function lcm_get_discount_groups() {
    $groups = get_option( 'lcm_discount_groups', null );

    if ( is_array( $groups ) ) {
        return $groups;
    }

    // مهاجرت یک‌بارِ خودکار از سیستم قدیمی (۳ درصد ثابت بدون هدف‌گذاری محصول)
    $legacy_fitness = floatval( get_option( 'lcm_fitness_discount', 0 ) );
    $legacy_diet     = floatval( get_option( 'lcm_diet_discount', 0 ) );
    $legacy_normal   = floatval( get_option( 'lcm_normal_discount', 0 ) );

    $migrated = array(
        array(
            'slug' => 'bodybuilder', 'label' => '🏋️‍♂️ بدنساز / ورزشکار', 'color' => '#43e97b',
            'percent' => $legacy_fitness, 'categories' => array(), 'products' => array(),
        ),
        array(
            'slug' => 'diet', 'label' => '🥗 رژیم / کتوژنیک', 'color' => '#ffb703',
            'percent' => $legacy_diet, 'categories' => array(), 'products' => array(),
        ),
        array(
            'slug' => 'normal', 'label' => '☕ علاقه‌مند سلامت', 'color' => '#0ea5e9',
            'percent' => $legacy_normal, 'categories' => array(), 'products' => array(),
        ),
    );

    update_option( 'lcm_discount_groups', $migrated );
    return $migrated;
}

function lcm_get_discount_group_by_slug( $slug ) {
    if ( empty( $slug ) ) { return null; }
    foreach ( lcm_get_discount_groups() as $group ) {
        if ( isset( $group['slug'] ) && $group['slug'] === $slug ) {
            return $group;
        }
    }
    return null;
}

/**
 * آیا این محصول در فهرست هدف این گروه تخفیف قرار دارد؟
 * (یا مستقیماً با آیدی محصول انتخاب شده، یا از طریق دسته‌بندی)
 */
function lcm_product_matches_discount_group( $product_id, $group ) {
    if ( empty( $group ) ) { return false; }

    $products = isset( $group['products'] ) && is_array( $group['products'] ) ? array_map( 'intval', $group['products'] ) : array();
    if ( in_array( intval( $product_id ), $products, true ) ) {
        return true;
    }

    $categories = isset( $group['categories'] ) && is_array( $group['categories'] ) ? $group['categories'] : array();
    if ( ! empty( $categories ) ) {
        $product_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
        if ( ! is_wp_error( $product_cats ) && array_intersect( $categories, $product_cats ) ) {
            return true;
        }
    }

    return false;
}

/**
 * تابع اصلی: برای یک محصول مشخص و گروهِ کاربریِ بازدیدکننده،
 * قانون تخفیف قابل‌اعمال را برمی‌گرداند (یا null اگر تخفیفی به این محصول تعلق نگیرد).
 */
function lcm_get_applicable_discount( $product_id, $user_group_slug ) {
    if ( empty( $user_group_slug ) ) { return null; }

    $group = lcm_get_discount_group_by_slug( $user_group_slug );
    if ( ! $group || floatval( $group['percent'] ) <= 0 ) { return null; }

    if ( lcm_product_matches_discount_group( $product_id, $group ) ) {
        return $group;
    }
    return null;
}

/**
 * بر اساس مجموع خرید مشتری (که ووکامرس خودش حساب می‌کند)، بالاترین گروهی که
 * برایش «حداقل مبلغ خرید» تعریف شده و مشتری واجد شرایطش شده را پیدا می‌کند
 * و در صورت نیاز گروه مشتری را ارتقا می‌دهد. هرگز خودکار تنزل نمی‌دهد —
 * تنزل فقط با تغییر دستی توسط مدیر کافه ممکن است.
 */
function lcm_maybe_auto_upgrade_group( $phone ) {
    global $wpdb;
    $table = $wpdb->prefix . 'lcm_club_members';
    $member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", $phone ) );
    if ( ! $member ) return;

    $wp_user = get_user_by( 'login', $phone );
    if ( ! $wp_user || ! class_exists( 'WC_Customer' ) ) return;

    $customer = new WC_Customer( $wp_user->ID );
    $total_spent = floatval( $customer->get_total_spent() );

    $groups = lcm_get_discount_groups();
    $eligible_groups = array_filter( $groups, function( $g ) use ( $total_spent ) {
        return isset( $g['min_spend'] ) && floatval( $g['min_spend'] ) > 0 && $total_spent >= floatval( $g['min_spend'] );
    });
    if ( empty( $eligible_groups ) ) return;

    // بالاترین آستانه‌ای که مشتری واجد شرایطش هست
    $eligible_groups = array_values( $eligible_groups );
    usort( $eligible_groups, function( $a, $b ) { return floatval( $b['min_spend'] ) <=> floatval( $a['min_spend'] ); } );
    $best_group = $eligible_groups[0];

    $current_group = lcm_get_discount_group_by_slug( $member->user_group );
    $current_min_spend = $current_group && isset( $current_group['min_spend'] ) ? floatval( $current_group['min_spend'] ) : 0;

    if ( $best_group['slug'] !== $member->user_group && floatval( $best_group['min_spend'] ) > $current_min_spend ) {
        $wpdb->update( $table, array( 'user_group' => $best_group['slug'] ), array( 'phone' => $phone ), array('%s'), array('%s') );
    }
}
