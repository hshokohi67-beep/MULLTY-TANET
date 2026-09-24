<?php
/*
Plugin Name: Live Cafe Menu
Description: منوی لایو و هوشمند کافه با شبیه‌ساز سه‌بعدی، بارکد میزها و منشی صوتی
Version: 4.1
Author: Shokohi
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ==========================================================================
   🔒 چک مجوز دامنه
========================================================================== */
function lcm_menu_is_licensed() {
    $allowed_domains = array(
        'dentall-clinic.local',
        // 'yourdomain.com',
    );
    $current = strtolower( parse_url( home_url(), PHP_URL_HOST ) );
    $current = preg_replace('/^www\./', '', $current);
    foreach ( $allowed_domains as $domain ) {
        $domain = strtolower( preg_replace('/^www\./', '', $domain) );
        if ( $current === $domain ) { return true; }
    }
    return false;
}
if ( ! lcm_menu_is_licensed() ) { return; }

define( 'LCM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ۱. لود کردن پنل مدیریت آشپزخانه
require_once LCM_PLUGIN_DIR . 'admin/lcm-admin-panel.php';
require_once LCM_PLUGIN_DIR . 'includes/lcm-push-helpers.php';

/**
 * جدول ذخیره‌ی اشتراک‌های اعلان مرورگر (Push). هر ردیف یعنی «این شماره تلفن،
 * روی این مرورگر/دستگاه خاص، اجازه‌ی اعلان داده است».
 */
function lcm_create_push_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'lcm_push_subscriptions';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        phone varchar(50) NOT NULL,
        endpoint varchar(500) NOT NULL,
        p256dh varchar(255) NOT NULL,
        auth varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY phone (phone)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    update_option( 'lcm_push_db_version', '1.0' );
}
register_activation_hook( __FILE__, 'lcm_create_push_table' );
add_action( 'plugins_loaded', function() {
    if ( get_option( 'lcm_push_db_version' ) !== '1.0' ) { lcm_create_push_table(); }
    if ( get_option( 'lcm_reservations_db_version' ) !== '1.0' ) { lcm_create_reservations_table(); }
});

function lcm_create_reservations_table() {
    global $wpdb;
    $table           = $wpdb->prefix . 'lcm_reservations';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        phone varchar(50) NOT NULL,
        guests tinyint(3) NOT NULL DEFAULT 2,
        reserved_at datetime NOT NULL,
        note varchar(500) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY status (status),
        KEY reserved_at (reserved_at)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'lcm_reservations_db_version', '1.0' );
}
register_activation_hook( __FILE__, 'lcm_create_reservations_table' );

// ۲. ساخت برگه منو
register_activation_hook( __FILE__, 'lcm_create_menu_page' );
function lcm_create_menu_page() {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    $page_slug = 'live-menu';
    $page_check = get_page_by_path($page_slug);
    if(!isset($page_check->ID)){
        wp_insert_post(array(
            'post_type' => 'page',
            'post_title' => 'منوی اختصاصی لایو کافه',
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_name' => $page_slug
        ));
    }

    // برگه‌ی نمایشگر آشپزخانه (KDS)
    $kds_slug = 'kitchen-display';
    $kds_check = get_page_by_path($kds_slug);
    if(!isset($kds_check->ID)){
        wp_insert_post(array(
            'post_type' => 'page', 'post_title' => 'نمایشگر آشپزخانه',
            'post_content' => '', 'post_status' => 'publish',
            'post_author' => 1, 'post_name' => $kds_slug
        ));
    }

    // برگه‌ی رزرو آنلاین میز
    $res_slug = 'reservation';
    $res_check = get_page_by_path($res_slug);
    if(!isset($res_check->ID)){
        wp_insert_post(array(
            'post_type' => 'page', 'post_title' => 'رزرو میز',
            'post_content' => '', 'post_status' => 'publish',
            'post_author' => 1, 'post_name' => $res_slug
        ));
    }

    flush_rewrite_rules();
}

/* ==========================================================================
   سرو کردن فایل Service Worker از ریشه‌ی سایت (نه از پوشه‌ی پلاگین)، چون
   محدوده‌ی (scope) این فایل باید کل سایت را پوشش دهد تا اعلان حتی وقتی
   تب مرورگر بسته است هم به دست مشتری برسد.
========================================================================== */
add_action( 'init', 'lcm_register_sw_rewrite' );
function lcm_register_sw_rewrite() {
    add_rewrite_rule( '^lcm-manifest\.json$', 'index.php?lcm_manifest=1', 'top' );
}
add_filter( 'query_vars', function( $vars ) { $vars[] = 'lcm_manifest'; return $vars; } );

add_action( 'template_redirect', 'lcm_maybe_serve_manifest' );
function lcm_maybe_serve_manifest() {
    if ( ! get_query_var( 'lcm_manifest' ) ) { return; }

    $cafe_name = get_option( 'lcm_cafe_name', 'کافه لایو منو' );
    $cafe_logo = get_option( 'lcm_cafe_logo', '' );
    $theme = get_option( 'lcm_menu_theme', 'dark' );

    $manifest = array(
        'name'             => $cafe_name,
        'short_name'       => $cafe_name,
        'description'      => 'منوی آنلاین و هوشمند ' . $cafe_name,
        'start_url'        => home_url( '/live-menu/' ),
        'display'          => 'standalone',
        'background_color' => $theme === 'dark' ? '#0b090a' : '#fcfbf9',
        'theme_color'      => '#2ec4b6',
        'orientation'      => 'portrait',
        'dir'              => 'rtl',
        'lang'             => 'fa',
        'icons'            => array(),
    );

    // 📝 نکته: برای بهترین نتیجه، یک لوگوی مربعی (حداقل ۵۱۲×۵۱۲ پیکسل) در تنظیمات آپلود کنید.
    // اگر لوگویی آپلود نشده باشد، آیکون پیش‌فرض مرورگر استفاده می‌شود.
    if ( $cafe_logo ) {
        $manifest['icons'][] = array( 'src' => $cafe_logo, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' );
        $manifest['icons'][] = array( 'src' => $cafe_logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' );
    }

    header( 'Content-Type: application/manifest+json; charset=utf-8' );
    echo wp_json_encode( $manifest );
    exit;
}

/* ==========================================================================
   سرو کردن مستقیم فایل Service Worker — بدون rewrite rule
   🔒 نکته‌ی مهم: نسخه‌ی قبلی از rewrite rule + query var استفاده می‌کرد، ولی
   وردپرس در فرآیند خودش (redirect_canonical) این آدرس را قبل از رسیدن به کد ما
   ریدایرکت می‌کرد. مرورگرها Service Worker پشت ریدایرکت را قبول نمی‌کنند
   (خطای "the script resource is behind a redirect"). الان مستقیماً و خیلی
   زودتر از پردازش کامل وردپرس، آدرس درخواستی را چک می‌کنیم و بدون اجازه‌دادن
   به وردپرس برای ریدایرکت، خودمان فایل را serve می‌کنیم.
========================================================================== */
add_action( 'init', 'lcm_maybe_serve_sw', 0 ); // اولویت ۰ یعنی خیلی زود، قبل از هر پردازش دیگر
function lcm_maybe_serve_sw() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    if ( trim($request_uri, '/') !== 'lcm-push-sw.js' ) { return; }

    header( 'Content-Type: application/javascript; charset=utf-8' );
    header( 'Service-Worker-Allowed: /' );
    ?>
self.addEventListener('push', function(event) {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }

    const title = data.title || 'کافه';
    const options = {
        body: data.body || 'به‌روزرسانی جدیدی دارید.',
        icon: '<?php echo esc_js( get_option('lcm_cafe_logo', '') ); ?>',
        badge: '<?php echo esc_js( get_option('lcm_cafe_logo', '') ); ?>',
        data: { order_id: data.order_id || 0 },
        dir: 'rtl',
        lang: 'fa',
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const orderId = event.notification.data && event.notification.data.order_id;
    const targetUrl = orderId ? '<?php echo esc_js( home_url('/live-menu/') ); ?>' : '<?php echo esc_js( home_url('/live-menu/') ); ?>';

    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(function(clientList) {
            for (const client of clientList) {
                if (client.url.indexOf('<?php echo esc_js( home_url() ); ?>') === 0 && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) { return clients.openWindow(targetUrl); }
        })
    );
});
    <?php
    exit;
}

// ۳. هدایت برگه ساختار به پوسته اختصاصی منو مشتری
add_filter( 'page_template', 'lcm_load_menu_page_template' );
function lcm_load_menu_page_template( $page_template ) {
    if ( is_page( 'live-menu' ) || get_query_var('name') == 'live-menu' ) {
        $custom_template = LCM_PLUGIN_DIR . 'public/menu-template.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    if ( is_page( 'kitchen-display' ) || get_query_var('name') == 'kitchen-display' ) {
        $kds_template = LCM_PLUGIN_DIR . 'public/kds-template.php';
        if ( file_exists( $kds_template ) ) {
            return $kds_template;
        }
    }
    if ( is_page( 'reservation' ) || get_query_var('name') == 'reservation' ) {
        $res_template = LCM_PLUGIN_DIR . 'public/reservation-template.php';
        if ( file_exists( $res_template ) ) {
            return $res_template;
        }
    }
    return $page_template;
}

// ۴. فعال‌سازی سشن ایمن
add_action('init', 'lcm_start_session_for_table', 1);
function lcm_start_session_for_table() {
    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    if ( isset($_GET['table_id']) ) {
        $table_id = intval($_GET['table_id']);
        $_SESSION['lcm_table_id'] = $table_id;
        setcookie('lcm_table_id', $table_id, time() + (86400 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
}

// بقیه توابع پایه ووکامرس (ثبت میز، متاباکس و ...)
add_action('woocommerce_checkout_create_order', 'lcm_save_table_id_to_order_meta', 10, 2);
function lcm_save_table_id_to_order_meta($order, $data) {
    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    if (isset($_SESSION['lcm_table_id']) && intval($_SESSION['lcm_table_id']) > 0) {
        $order->update_meta_data('_lcm_table_id', intval($_SESSION['lcm_table_id']));
    } elseif (isset($_COOKIE['lcm_table_id']) && intval($_COOKIE['lcm_table_id']) > 0) {
        $order->update_meta_data('_lcm_table_id', intval($_COOKIE['lcm_table_id']));
    }
}

add_action('woocommerce_admin_order_data_after_billing_address', 'lcm_display_table_id_in_admin', 10, 1);
function lcm_display_table_id_in_admin($order){
    $table_id = $order->get_meta('_lcm_table_id');
    if ($table_id) { echo '<p><strong>📍 موقعیت سفارش:</strong> میز شماره ' . esc_html($table_id) . '</p>'; } 
    else { echo '<p><strong>📍 موقعیت سفارش:</strong> سالن / بیرون‌بر</p>'; }
}

function lcm_backend_is_product_fitness($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;
    $search_text = $product->get_name();
    $product_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
    if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) { $search_text .= ' ' . implode( ' ', $product_cats ); }
    return (bool) preg_match('/(سالاد|رژیمی|گوشت|استیک|مرغ|پروتئین|فیله|بوقلمون|کباب|رژیم)/ui', $search_text);
}

// توابع سبد خرید و سینک میز (حفظ شده از قبل)
add_action('wp_ajax_lcm_add_custom_product_to_cart', 'lcm_add_custom_product_to_cart_callback');
add_action('wp_ajax_nopriv_lcm_add_custom_product_to_cart', 'lcm_add_custom_product_to_cart_callback');

function lcm_add_custom_product_to_cart_callback() {
    try {
        lcm_add_custom_product_to_cart_inner();
    } catch ( \Throwable $e ) {
        // 🔒 اگر هر بخشی از فرآیند (مثلاً ایمیل خودکار سفارش‌که هاست SMTP ندارد)
        // خطای غیرمنتظره داد، به‌جای کرش کامل صفحه، پیام مناسب برمی‌گردانیم.
        error_log( 'LCM Order Error: ' . $e->getMessage() );
        wp_send_json_error( array( 'message' => 'سفارش شما ممکن است ثبت شده باشد، اما در ارسال اعلان خطایی رخ داد. لطفاً با کافه تماس بگیرید یا صفحه را رفرش کنید.' ) );
    }
}

function lcm_add_custom_product_to_cart_inner() {
    if ( ! isset($_POST['lcm_nonce']) || ! wp_verify_nonce( sanitize_text_field( $_POST['lcm_nonce'] ), 'lcm_public_actions' ) ) {
        wp_send_json_error( array( 'message' => 'نشست شما منقضی شده، لطفاً صفحه را رفرش کنید.' ) );
    }

    // 🔒 Rate limiting: حداکثر ۱۰ سفارش از هر IP در ۵ دقیقه
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rate_key = 'lcm_order_rate_' . md5( $ip );
    $order_count = intval( get_transient( $rate_key ) );
    if ( $order_count >= 10 ) {
        wp_send_json_error( array( 'message' => 'تعداد سفارش‌های شما در این بازه زمانی زیاد است. کمی صبر کنید.' ) );
    }
    set_transient( $rate_key, $order_count + 1, 5 * MINUTE_IN_SECONDS );
    $main_id      = isset($_POST['main_id']) ? intval($_POST['main_id']) : 0;
    $addons       = isset($_POST['addons']) ? sanitize_text_field($_POST['addons']) : '';
    $order_type   = isset($_POST['order_type']) ? sanitize_text_field($_POST['order_type']) : 'salon';
    $table_id     = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
    $user_phone   = isset($_POST['user_phone']) ? sanitize_text_field($_POST['user_phone']) : '';
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'آنلاین';
    $remaining_payment_method = isset($_POST['remaining_payment_method']) ? sanitize_text_field($_POST['remaining_payment_method']) : 'salon';
    $remaining_payment_method = in_array($remaining_payment_method, array('online', 'salon')) ? $remaining_payment_method : 'salon';
    $order_note_text = isset($_POST['order_note']) ? sanitize_textarea_field( wp_unslash( $_POST['order_note'] ) ) : '';
    $order_note_text = mb_substr( $order_note_text, 0, 200 ); // محدودیت طول برای جلوگیری از سوءاستفاده
    $requested_time_raw = isset($_POST['requested_time']) ? sanitize_text_field( $_POST['requested_time'] ) : '';
    $requested_time = ( preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $requested_time_raw) ) ? $requested_time_raw : '';

    if ($main_id <= 0) {
        wp_send_json_error(array('message' => 'شناسه محصول معتبر نیست.'));
    }

    $order = wc_create_order();
    $viewer_group_slug = '';

    global $wpdb;
    $club_table = $wpdb->prefix . 'lcm_club_members';

    // تنظیم اطلاعات مشتری از باشگاه
    if (!empty($user_phone)) {
        $club_member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $club_table WHERE phone = %s", $user_phone));
        if ($club_member) {
            $order->set_billing_phone($user_phone);
            if (!empty($club_member->name)) {
                $name_parts = explode(' ', trim($club_member->name), 2);
                $order->set_billing_first_name(sanitize_text_field($name_parts[0]));
                if (isset($name_parts[1])) {
                    $order->set_billing_last_name(sanitize_text_field($name_parts[1]));
                }
            }
            if (!empty($club_member->user_group)) {
                $viewer_group_slug = $club_member->user_group;
            }

            $wp_user = get_user_by('login', $user_phone);
            if ($wp_user) {
                $order->set_customer_id($wp_user->ID);

                // آدرس ارسال سفارش: قبلاً هیچ‌جا روی خود سفارش ثبت نمی‌شد، یعنی مسئول
                // ارسال هیچ راهی برای دیدن آدرس مشتری روی فاکتور/سفارش نداشت.
                if ($order_type === 'takeaway') {
                    $saved_city = get_user_meta($wp_user->ID, 'billing_city', true);
                    $saved_address = get_user_meta($wp_user->ID, 'billing_address_1', true);
                    if (!empty($saved_address)) {
                        $order->set_billing_city($saved_city);
                        $order->set_billing_address_1($saved_address);
                        $order->add_order_note('📍 ارسال به این آدرس: ' . $saved_city . ' - ' . $saved_address);
                    }
                }
            }
        }
    }

    if (empty($viewer_group_slug) && isset($_COOKIE['lcm_user_group'])) {
        $viewer_group_slug = sanitize_text_field($_COOKIE['lcm_user_group']);
    }

    /**
     * محاسبه‌ی قیمت با تخفیفِ گروه مشتری (در صورت وجود)، با استفاده از موتور
     * گروه‌های تخفیف پویا در افزونه‌ی باشگاه مشتریان (در صورت نصب و فعال بودن آن).
     */
    $lcm_calc_discounted_price = function( $product_id, $base_price ) use ( $viewer_group_slug ) {
        $best_percent = 0;

        if ( ! empty( $viewer_group_slug ) && function_exists( 'lcm_get_applicable_discount' ) ) {
            $rule = lcm_get_applicable_discount( $product_id, $viewer_group_slug );
            if ( $rule && floatval( $rule['percent'] ) > $best_percent ) {
                $best_percent = floatval( $rule['percent'] );
            }
        }

        // آیتم شانسی امروز (اگر تخفیفش بیشتر باشد، جایگزین می‌شود؛ روی هم جمع نمی‌شوند)
        if ( function_exists( 'lcm_get_daily_lucky_item' ) ) {
            $lucky_item = lcm_get_daily_lucky_item();
            if ( $lucky_item && intval( $lucky_item['id'] ) === intval( $product_id ) && floatval( $lucky_item['discount_percent'] ) > $best_percent ) {
                $best_percent = floatval( $lucky_item['discount_percent'] );
            }
        }

        if ( $best_percent > 0 ) {
            return $base_price - ( ( $base_price * $best_percent ) / 100 );
        }
        return $base_price;
    };

    // افزودن محصول اصلی
    $main_product = wc_get_product($main_id);
    if ($main_product) {
        $item_price = $lcm_calc_discounted_price( $main_id, floatval($main_product->get_price()) );
        $item_id = $order->add_product($main_product, 1);
        if ($item_id) {
            $item = $order->get_item($item_id);
            $item->set_subtotal($item_price);
            $item->set_total($item_price);
            $item->save();
        }
    }

    // افزودن افزودنی‌ها
    if (!empty($addons)) {
        $addon_ids = array_map('intval', explode(',', $addons));
        foreach ($addon_ids as $addon_id) {
            if ($addon_id > 0) {
                $addon_product = wc_get_product($addon_id);
                if ($addon_product) {
                    $addon_price = $lcm_calc_discounted_price( $addon_id, floatval($addon_product->get_price()) );
                    $item_id = $order->add_product($addon_product, 1);
                    if ($item_id) {
                        $item = $order->get_item($item_id);
                        $item->set_subtotal($addon_price);
                        $item->set_total($addon_price);
                        $item->save();
                    }
                }
            }
        }
    }

    // متاهای سفارش
    $order_type = in_array($order_type, array('salon', 'takeaway')) ? $order_type : 'salon';
    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    $_SESSION['lcm_order_type'] = $order_type;
    $order->update_meta_data('_lcm_order_type', $order_type);
    $order->update_meta_data('_lcm_table_id', ($order_type === 'takeaway') ? 'بیرون‌بر 🛍️' : ($table_id > 0 ? $table_id : 'سالن'));
    $order->update_meta_data('_lcm_payment_method', $payment_method);
    if ( ! empty( $order_note_text ) ) {
        $order->update_meta_data('_lcm_kitchen_note', $order_note_text);
        $order->add_order_note('📝 یادداشت مشتری: ' . $order_note_text);
    }
    if ( ! empty( $requested_time ) ) {
        $order->update_meta_data('_lcm_requested_time', $requested_time);
        $order->add_order_note('⏰ پیش‌سفارش؛ مشتری درخواست تحویل در ساعت ' . $requested_time . ' را دارد (کافه هنگام ثبت این سفارش تعطیل بوده است).');
    }

    $order->calculate_totals();

    // ======================== کسر کیف پول ========================
    if (($payment_method === 'wallet' || $payment_method === 'کیف پول باشگاه') && !empty($user_phone)) {
        $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $club_table WHERE phone = %s", $user_phone));
        if ($member) {
            $current_balance = intval($member->wallet_balance);
            $order_total = floatval($order->get_total());

            if ($current_balance > 0) {
                $amount_to_deduct = min($current_balance, $order_total);
                $new_balance = $current_balance - $amount_to_deduct;
                $remaining = $order_total - $amount_to_deduct;

                $wpdb->update(
                    $club_table,
                    ['wallet_balance' => $new_balance],
                    ['phone' => $user_phone],
                    ['%d'],
                    ['%s']
                );

                $note = sprintf(
                    '✅ مبلغ %s تومان از کیف پول کسر شد. موجودی جدید: %s تومان',
                    number_format($amount_to_deduct),
                    number_format($new_balance)
                );

                if ($remaining > 0) {
                    $remaining_label = ($remaining_payment_method === 'online') ? 'پرداخت آنلاین' : 'پرداخت نقدی در سالن';
                    $note .= sprintf(' | مابقی مبلغ (%s تومان) از طریق %s تسویه می‌شود.', number_format($remaining), $remaining_label);
                    $order->update_meta_data('_lcm_remaining_amount', $remaining);
                    $order->update_meta_data('_lcm_remaining_payment_method', $remaining_payment_method);
                    $order->update_meta_data('_lcm_payment_type', 'partial_wallet');
                    $order->update_meta_data('_payment_method_title', 'کیف پول باشگاه + ' . $remaining_label);
                } else {
                    $order->update_meta_data('_lcm_remaining_amount', 0);
                    $order->update_meta_data('_lcm_payment_type', 'full_wallet');
                    $order->update_meta_data('_payment_method_title', 'کیف پول باشگاه');
                }

                $order->add_order_note($note);
                $order->update_meta_data('_lcm_wallet_deducted', $amount_to_deduct);
            } else {
                $order->add_order_note('موجودی کیف پول صفر بود.');
                $order->update_meta_data('_lcm_wallet_deducted', 0);
                $order->update_meta_data('_lcm_remaining_amount', $order_total);
                $order->update_meta_data('_lcm_remaining_payment_method', $remaining_payment_method);
            }
        }
    }

    /* ==========================================================================
       وضعیت سفارش
       نکته‌ی مهم: سفارش فقط زمانی خودکار «در حال آماده‌سازی» می‌شود که واقعاً
       تسویه شده باشد (کیف پول کامل، یا کیف پول + پرداخت نقدی در سالن).
       اگر بخشی از مبلغ قرار است «آنلاین» پرداخت شود، سفارش در وضعیت «در انتظار
       پرداخت» می‌ماند و مشتری به صفحه‌ی پرداخت واقعی ووکامرس هدایت می‌شود (پایین‌تر).
       ✅ برای فعال شدن پرداخت واقعی، کافیست یک درگاه پرداخت (مثلاً زرین‌پال) را
       از طریق پلاگین مخصوصش نصب و در تنظیمات ووکامرس فعال کنید — نیازی به
       تغییر این کد نیست، چون از فرآیند استاندارد پرداخت ووکامرس استفاده می‌کند.
    ========================================================================== */
    $remaining_amount = floatval($order->get_meta('_lcm_remaining_amount'));
    $has_pending_online_balance = ($remaining_amount > 0 && $remaining_payment_method === 'online');

    if ($payment_method === 'salon') {
        $order->update_status('processing', 'پرداخت نقدی در سالن هنگام تحویل');
    } elseif (($payment_method === 'wallet' || $payment_method === 'کیف پول باشگاه') && ! $has_pending_online_balance) {
        $order->update_status('processing', 'پرداخت با کیف پول (کامل یا همراه با نقدی در سالن)');
    } else {
        $order->update_status('pending', 'در انتظار تسویه (آنلاین) — نیازمند تایید دستی یا درگاه پرداخت');
    }

    $order->save();
    delete_transient('lcm_radar_latest_orders');

    /* ==========================================================================
       آدرس بازگشت مشتری:
       - اگر بخشی/همه‌ی مبلغ باید «آنلاین» پرداخت شود، مشتری باید به صفحه‌ی
         پرداخت واقعی ووکامرس فرستاده شود (که با نصب هر درگاه پرداخت واقعی،
         خودکار همان درگاه را نشان می‌دهد) — نه مستقیم به صفحه‌ی «سفارش ثبت شد».
       - در غیر این صورت (پرداخت نقدی/کیف پول کامل)، مستقیم به صفحه‌ی رهگیری سفارش.
    ========================================================================== */
    $is_pure_online_payment = in_array( $payment_method, array('online', 'آنلاین'), true );

    if ( $has_pending_online_balance || $is_pure_online_payment ) {
        $redirect_url = $order->get_checkout_payment_url();
    } else {
        $redirect_url = $order->get_checkout_order_received_url();
    }

    wp_send_json_success(array('redirect_url' => $redirect_url));
}

add_action('wp_ajax_lcm_sync_table_cart', 'lcm_ajax_sync_table_cart');
add_action('wp_ajax_nopriv_lcm_sync_table_cart', 'lcm_ajax_sync_table_cart');
function lcm_ajax_sync_table_cart() {
    if ( ! isset($_POST['lcm_nonce']) || ! wp_verify_nonce( sanitize_text_field( $_POST['lcm_nonce'] ), 'lcm_public_actions' ) ) {
        wp_send_json_error( array( 'message' => 'نشست شما منقضی شده، لطفاً صفحه را رفرش کنید.' ) );
    }
    $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
    $cart_data = isset($_POST['cart_data']) ? wp_unslash($_POST['cart_data']) : '';

    if ( isset($_POST['order_type']) ) {
        if ( ! session_id() && ! headers_sent() ) { session_start(); }
        $posted_type = sanitize_text_field($_POST['order_type']);
        $_SESSION['lcm_order_type'] = in_array($posted_type, array('salon', 'takeaway')) ? $posted_type : 'salon';
    }

    if ($table_id <= 0) { wp_send_json_error(); }
    $transient_key = 'lcm_table_cart_' . $table_id;

    if (!empty($cart_data)) {
        $parsed_cart = json_decode($cart_data, true);
        if (is_array($parsed_cart)) {
            $sanitized_cart = array();
            foreach ($parsed_cart as $item) {
                $sanitized_cart[] = array(
                    'combo_key'    => isset($item['combo_key']) ? sanitize_text_field($item['combo_key']) : '',
                    'main_id'      => isset($item['main_id']) ? intval($item['main_id']) : 0,
                    'var_id'       => isset($item['var_id']) ? intval($item['var_id']) : 0,
                    'title'        => isset($item['title']) ? sanitize_text_field($item['title']) : '',
                    'cat_slug'     => isset($item['cat_slug']) ? sanitize_title($item['cat_slug']) : '',
                    'size_label'   => isset($item['size_label']) ? sanitize_text_field($item['size_label']) : '',
                    'addons'       => isset($item['addons']) && is_array($item['addons']) ? array_map('intval', $item['addons']) : array(),
                    'addon_titles' => isset($item['addon_titles']) && is_array($item['addon_titles']) ? array_map('sanitize_text_field', $item['addon_titles']) : array(),
                    'price'        => isset($item['price']) ? floatval($item['price']) : 0.0
                );
            }
            set_transient($transient_key, $sanitized_cart, 43200);
            wp_send_json_success($sanitized_cart);
        } else { wp_send_json_error(array('message' => 'ساختار سبد نامعتبر است.')); }
    } else {
        $current_table_cart = get_transient($transient_key);
        wp_send_json_success($current_table_cart ? $current_table_cart : array());
    }
}


/* ==========================================================================
   دریافت اطلاعات لایو پنل کاربری (کاملاً ایمن و بهینه‌شده برای چند دستگاهی)
========================================================================== */
add_action('wp_ajax_lcm_get_user_panel_data', 'lcm_ajax_get_user_panel_data');
add_action('wp_ajax_nopriv_lcm_get_user_panel_data', 'lcm_ajax_get_user_panel_data');

/**
 * تبدیل ساده‌ی تاریخ میلادی به شمسی، بدون نیاز به هیچ کلاس یا کتابخانه‌ی بیرونی
 * (جایگزین وابستگی قبلی به کلاس jDateTime که اگر نصب نبود، کل روزشمار تولد
 * ساکت از کار می‌افتاد و «تاریخ تولدی ثبت نشده است» نشان می‌داد، حتی اگر
 * تاریخ تولد واقعاً در باشگاه مشتریان ثبت شده بود).
 */
/**
 * آیا کافه همین الان (بر اساس ساعات کاری تنظیم‌شده) باز است؟
 * اگر هیچ ساعتی تنظیم نشده باشد، به‌صورت پیش‌فرض «باز» در نظر گرفته می‌شود
 * (تا نصب‌های قدیمی‌تر که این تنظیمات را ندارند، دچار مشکل نشوند).
 */
function lcm_is_cafe_currently_open() {
    $hours = get_option( 'lcm_business_hours', array() );
    if ( empty( $hours ) || ! is_array( $hours ) ) { return true; }

    $today_index = intval( current_time('w') ); // 0=یکشنبه ... 6=شنبه
    if ( ! isset( $hours[ $today_index ] ) ) { return true; }

    $today = $hours[ $today_index ];

    // بعد از رفع باگ sanitize، مقدار 'closed' می‌تواند 0 (باز) یا 1 (تعطیل) باشد
    if ( ! empty( $today['closed'] ) && $today['closed'] != 0 ) { return false; }

    $open_str  = isset( $today['open']  ) ? $today['open']  : '00:00';
    $close_str = isset( $today['close'] ) ? $today['close'] : '23:59';

    // جلوگیری از باز-ماندن همیشگی در صورت خالی بودن ساعت
    if ( empty( $open_str ) || empty( $close_str ) ) { return true; }

    $now_minutes   = intval( current_time('H') ) * 60 + intval( current_time('i') );
    $open_minutes  = lcm_time_str_to_minutes( $open_str );
    $close_minutes = lcm_time_str_to_minutes( $close_str );

    return ( $now_minutes >= $open_minutes && $now_minutes <= $close_minutes );
}

function lcm_time_str_to_minutes( $time_str ) {
    $parts = explode( ':', $time_str );
    return ( intval( $parts[0] ?? 0 ) * 60 ) + intval( $parts[1] ?? 0 );
}

/**
 * متن دوستانه‌ی «کِی باز می‌کنیم» برای وقتی کافه فعلاً تعطیل است.
 */
function lcm_get_next_reopen_text() {
    $hours = get_option( 'lcm_business_hours', array() );
    if ( empty( $hours ) || ! is_array( $hours ) ) { return ''; }

    $day_names = array(0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه');
    $today_index = intval( current_time('w') );

    // تا ۷ روز آینده را چک کن تا اولین روز باز را پیدا کنی
    for ( $i = 0; $i < 7; $i++ ) {
        $check_index = ( $today_index + $i ) % 7;
        if ( empty( $hours[ $check_index ]['closed'] ) ) {
            $open_time = $hours[ $check_index ]['open'] ?? '08:00';
            if ( $i === 0 ) { return 'امروز ساعت ' . $open_time; }
            if ( $i === 1 ) { return 'فردا ساعت ' . $open_time; }
            return $day_names[ $check_index ] . ' ساعت ' . $open_time;
        }
    }
    return '';
}

/**
 * انتخاب قطعی و ثابت «آیتم شانسی» امروز — همه‌ی بازدیدکنندگان در یک روز
 * دقیقاً همان یک آیتم را می‌بینند، و فردا خودکار عوض می‌شود.
 */
function lcm_get_daily_lucky_item() {
    if ( ! get_option( 'lcm_lucky_item_enabled', '0' ) ) { return null; }
    if ( ! class_exists( 'WooCommerce' ) ) { return null; }

    $cutoff_hour = intval( get_option( 'lcm_lucky_item_cutoff_hour', 14 ) );
    $current_hour = intval( current_time( 'H' ) );
    if ( $current_hour >= $cutoff_hour ) { return null; } // امروز دیگر دیر شده؛ فردا دوباره فعال می‌شود

    $today_key = current_time( 'Y-m-d' );
    $cached = get_transient( 'lcm_lucky_item_' . $today_key );
    if ( false !== $cached ) { return $cached; }

    $product_ids = get_posts( array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'publish',
    ) );
    if ( empty( $product_ids ) ) { return null; }

    sort( $product_ids ); // ترتیب همیشه یکسان باشد تا انتخاب واقعاً قطعی/تکرارپذیر باشد
    $seed = crc32( $today_key );
    $chosen_id = $product_ids[ $seed % count( $product_ids ) ];

    $product = wc_get_product( $chosen_id );
    if ( ! $product ) { return null; }

    $discount_percent = floatval( get_option( 'lcm_lucky_item_discount', 20 ) );
    $base_price = floatval( $product->get_price() );
    $result = array(
        'id'               => $chosen_id,
        'title'            => $product->get_name(),
        'image'            => wp_get_attachment_url( $product->get_image_id() ),
        'base_price'       => $base_price,
        'discounted_price' => $base_price - ( ( $base_price * $discount_percent ) / 100 ),
        'discount_percent' => $discount_percent,
        'cutoff_hour'      => $cutoff_hour,
    );

    // تا پایان همین روز کش می‌شود تا هر بار محاسبه نشود
    $seconds_until_midnight = strtotime( 'tomorrow', current_time( 'timestamp' ) ) - current_time( 'timestamp' );
    set_transient( 'lcm_lucky_item_' . $today_key, $result, $seconds_until_midnight );

    return $result;
}

/**
 * محصولات پرفروش بر اساس داده‌ی واقعی سفارش‌های ووکامرس.
 * نتیجه ۶ ساعت کش میشه تا کوئری سنگین هر بار اجرا نشه.
 * برمیگردونه: آرایه‌ای از ['product_id' => X, 'order_count' => Y]
 */
function lcm_get_top_products( $limit = 10 ) {
    $cached = get_transient( 'lcm_top_products_cache' );
    if ( false !== $cached ) { return $cached; }

    global $wpdb;
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT oi.product_id, COUNT(DISTINCT o.order_id) as order_count
         FROM (
             SELECT id AS order_id FROM {$wpdb->prefix}wc_orders WHERE status IN ('wc-processing','wc-completed') 
             UNION ALL
             SELECT ID as order_id FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status IN ('wc-processing','wc-completed')
         ) o
         INNER JOIN (
             SELECT order_id, order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_type = 'line_item'
         ) oim ON oim.order_id = o.order_id
         INNER JOIN (
             SELECT order_item_id, meta_value AS product_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_product_id'
         ) oi ON oi.order_item_id = oim.order_item_id
         WHERE oi.product_id > 0
         GROUP BY oi.product_id
         ORDER BY order_count DESC
         LIMIT %d",
        intval( $limit )
    ), ARRAY_A );

    if ( empty( $results ) ) { return array(); }

    $top = array();
    foreach ( $results as $row ) {
        $top[ intval($row['product_id']) ] = intval( $row['order_count'] );
    }

    set_transient( 'lcm_top_products_cache', $top, 6 * HOUR_IN_SECONDS );
    return $top;
}

// هر بار که سفارش جدید تکمیل بشه، کش پاک میشه تا داده‌ها بروز باشن
add_action( 'woocommerce_order_status_completed',  function() { delete_transient( 'lcm_top_products_cache' ); } );
add_action( 'woocommerce_order_status_processing', function() { delete_transient( 'lcm_top_products_cache' ); } );

function lcm_gregorian_to_jalali( $g_y, $g_m, $g_d ) {
    $g_days_in_month = array(31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;

    $g_day_no = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
    for ($i = 0; $i < $gm; ++$i) { $g_day_no += $g_days_in_month[$i]; }
    if ($gm > 1 && (($g_y % 4 == 0 && $g_y % 100 != 0) || ($g_y % 400 == 0))) { $g_day_no++; }
    $g_day_no += $gd;

    $j_day_no = $g_day_no - 79;
    $j_np = intdiv($j_day_no, 12053);
    $j_day_no = $j_day_no % 12053;

    $jy = 979 + 33 * $j_np + 4 * intdiv($j_day_no, 1461);
    $j_day_no %= 1461;

    if ($j_day_no >= 366) {
        $jy += intdiv($j_day_no - 1, 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }

    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
        $j_day_no -= $j_days_in_month[$i];
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;

    return array( $jy, $jm, $jd );
}

function lcm_ajax_get_user_panel_data() {
    // پاک کردن کش و ارورهای قبلی PHP تا JSON خراب نشود
    if (ob_get_length()) ob_clean();

    $phone = '';
    
    // ۱. بررسی لاگین بودن در وردپرس (برای کارکرد صحیح در همه دیوایس‌ها)
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $phone = $current_user->user_login; // فرض بر این است که نام کاربری همان شماره موبایل است
    }
    
    // ۲. بک‌آپ: خواندن از متغیر ارسال شده توسط مرورگر
    if ( empty($phone) && isset($_POST['phone']) ) {
        $phone = sanitize_text_field($_POST['phone']);
    }
    
    if(empty($phone)) {
        wp_send_json_error(array('message' => 'شماره موبایل یافت نشد. لطفاً مجدداً وارد شوید.'));
        exit;
    }

    global $wpdb;
    $club_table = $wpdb->prefix . 'lcm_club_members';
    $club_member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $club_table WHERE phone = %s", $phone));

    if (!$club_member) {
        wp_send_json_error(array('message' => 'کاربری با این شماره در باشگاه یافت نشد.'));
        exit;
    }

    $name = !empty($club_member->name) ? $club_member->name : 'مشتری عزیز';
    if(is_numeric(trim($name))) { $name = 'مشتری عزیز'; }

    $wallet_balance = isset($club_member->wallet_balance) ? intval($club_member->wallet_balance) : 0;
    $group = !empty($club_member->user_group) ? $club_member->user_group : 'normal';

    $b_day = intval($club_member->birth_day);
    $b_month = intval($club_member->birth_month);
    $birthday_text = "🎂 تاریخ تولدی ثبت نشده است.";

    if ($b_day > 0 && $b_month > 0) {
        // تبدیل مستقل و بدون‌وابستگی میلادی به شمسی، تا این قابلیت به کلاس بیرونی jDateTime
        // (که قبلاً اگر نصب نبود، کل این بخش را بی‌سروصدا غیرفعال می‌کرد) وابسته نباشد.
        list($current_j_year, $current_j_month, $current_j_day) = lcm_gregorian_to_jalali( (int) current_time('Y'), (int) current_time('n'), (int) current_time('j') );

        if ($b_month == $current_j_month && $b_day == $current_j_day) {
            $birthday_text = "🎉 تولدتان مبارک! هدیه شما در صندوق آماده است! 🎂🥳";
        } else {
            $diff_months = $b_month - $current_j_month;
            $diff_days = $b_day - $current_j_day;
            if ($diff_months < 0 || ($diff_months == 0 && $diff_days < 0)) {
                $diff_months += 12;
            }
            $total_days_left = ($diff_months * 30) + $diff_days;
            $birthday_text = "فقط <strong>{$total_days_left} روز</strong> تا تولد شما باقیست! 🥳";
        }
    }

    $orders_html = '<p style="text-align:center; color:#888; font-size:0.8rem; padding:10px;">سفارشی ثبت نشده است.</p>';
    $total_spent = 0;
    
    // واکشی امن اطلاعات از ووکامرس
    if (class_exists('WooCommerce')) {
        $wp_user = get_user_by('login', $phone);
        if ($wp_user) {
            $user_id = $wp_user->ID;
            $customer = new WC_Customer($user_id);
            $total_spent = $customer->get_total_spent();

            $customer_orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'limit' => 5,
                'status' => array('completed', 'processing', 'on-hold')
            ));

            if (!empty($customer_orders)) {
                $orders_html = '';
                $likes_table = $wpdb->prefix . 'lcm_liked_items';
                foreach ($customer_orders as $order) {
                    $items = array();
                    $product_ids = array();
                    $item_pairs = array(); // [ [id, name], ... ] برای دکمه‌های پسندیدن
                    foreach ($order->get_items() as $item) {
                        $items[] = $item->get_name();
                        if ($item->get_product_id()) {
                            $product_ids[] = $item->get_product_id();
                            $item_pairs[] = array($item->get_product_id(), $item->get_name());
                        }
                    }
                    $can_rate = ($order->get_status() === 'completed');
                    $within_window = lcm_is_within_rating_window($order);
                    $already_rated = $wpdb->get_var($wpdb->prepare("SELECT stars FROM {$wpdb->prefix}lcm_order_ratings WHERE order_id = %d", $order->get_id()));
                    $liked_product_ids = $wpdb->get_col($wpdb->prepare("SELECT product_id FROM $likes_table WHERE order_id = %d AND phone = %s", $order->get_id(), $phone));

                    $orders_html .= '<div class="order-history-item" style="flex-direction:column; align-items:stretch; gap:6px;">';
                    $orders_html .= '<div style="display:flex; justify-content:space-between; align-items:center;">';
                    $orders_html .= '<div><strong>' . implode(' + ', $items) . '</strong><br><small style="color:#888;">' . esc_html(wc_get_order_status_name($order->get_status())) . '</small></div>';
                    $orders_html .= '<div style="font-weight:bold; color:var(--accent-color);">' . number_format($order->get_total()) . ' ت</div>';
                    $orders_html .= '</div>';
                    if (!empty($product_ids)) {
                        $orders_html .= '<button class="lcm-reorder-btn" data-product-ids="' . esc_attr(implode(',', $product_ids)) . '" onclick="lcmReorderFromHistory(this)" style="align-self:flex-start; font-size:0.7rem; padding:5px 12px; border-radius:10px; border:1px solid var(--accent-color); background:transparent; color:var(--accent-color); cursor:pointer; font-weight:700;">🔁 سفارش مجدد</button>';
                    }

                    if ($can_rate) {
                        // ❤️ دکمه‌ی پسندیدن برای هرکدام از آیتم‌های همین سفارش (اختیاری، چندتایی)
                        if (!empty($item_pairs) && ($within_window || !empty($liked_product_ids))) {
                            $orders_html .= '<div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:2px;">';
                            foreach ($item_pairs as $pair) {
                                list($pid, $pname) = $pair;
                                $is_liked = in_array($pid, $liked_product_ids);
                                if ($within_window) {
                                    $orders_html .= '<button class="lcm-like-item-btn" data-order-id="' . esc_attr($order->get_id()) . '" data-product-id="' . esc_attr($pid) . '" onclick="lcmToggleItemLike(this)" style="font-size:0.68rem; padding:4px 10px; border-radius:20px; border:1px solid ' . ($is_liked ? '#e5383b' : 'var(--border-color)') . '; background:' . ($is_liked ? 'rgba(229,56,59,0.12)' : 'transparent') . '; color:' . ($is_liked ? '#e5383b' : 'var(--text-color)') . '; cursor:pointer;">' . ($is_liked ? '❤️' : '🤍') . ' ' . esc_html($pname) . '</button>';
                                } elseif ($is_liked) {
                                    $orders_html .= '<span style="font-size:0.68rem; padding:4px 10px; border-radius:20px; border:1px solid #e5383b; background:rgba(229,56,59,0.12); color:#e5383b;">❤️ ' . esc_html($pname) . '</span>';
                                }
                            }
                            $orders_html .= '</div>';
                        }

                        // ⭐ امتیاز کلی سفارش؛ تا ۲۴ ساعت قابل ثبت/ویرایش است
                        if ($within_window) {
                            $orders_html .= '<div class="lcm-rate-row" data-order-id="' . esc_attr($order->get_id()) . '" style="display:flex; align-items:center; gap:4px; font-size:1rem; margin-top:4px;">';
                            for ($s = 1; $s <= 5; $s++) {
                                $filled = ($already_rated && $s <= intval($already_rated));
                                $orders_html .= '<span onclick="lcmRateOrder(this,' . $s . ')" style="cursor:pointer; opacity:' . ($filled ? '1' : '0.35') . ';">⭐</span>';
                            }
                            if ($already_rated) {
                                $orders_html .= '<small style="color:#888; font-size:0.65rem; margin-right:6px;">(قابل ویرایش تا ۲۴ ساعت بعد از سفارش)</small>';
                            }
                            $orders_html .= '</div>';
                        } elseif ($already_rated) {
                            $orders_html .= '<div style="font-size:0.75rem; color:#ffb703; margin-top:4px;">' . str_repeat('⭐', intval($already_rated)) . ' (ثبت نهایی شد)</div>';
                        }
                    }
                    $orders_html .= '</div>';
                }
            }
        }
    }

    // ریز تراکنش‌های کیف پول (۸ مورد آخر) — قبلاً هیچ تاریخچه‌ای برای این وجود نداشت
    $wallet_ledger_html = '<p style="text-align:center; color:#888; font-size:0.75rem;">تراکنشی ثبت نشده است.</p>';
    $ledger_table = $wpdb->prefix . 'lcm_wallet_ledger';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$ledger_table'") === $ledger_table ) {
        $ledger_rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $ledger_table WHERE phone = %s ORDER BY id DESC LIMIT 8", $phone) );
        if ( ! empty( $ledger_rows ) ) {
            $wallet_ledger_html = '';
            foreach ( $ledger_rows as $row ) {
                $is_positive = $row->amount >= 0;
                $color = $is_positive ? '#43e97b' : '#e5383b';
                $sign  = $is_positive ? '+' : '';
                $wallet_ledger_html .= '<div style="display:flex; justify-content:space-between; font-size:0.75rem; padding:6px 0; border-bottom:1px dashed rgba(255,255,255,0.08);">';
                $wallet_ledger_html .= '<span>' . esc_html($row->reason) . '</span>';
                $wallet_ledger_html .= '<span style="color:' . $color . '; font-weight:700;">' . $sign . number_format($row->amount) . ' ت</span>';
                $wallet_ledger_html .= '</div>';
            }
        }
    }

    // آدرس ذخیره‌شده‌ی قبلی برای پرکردن خودکار (دفترچه آدرس ساده)
    $saved_city = '';
    $saved_address = '';
    if ( class_exists('WooCommerce') ) {
        $wp_user_for_address = get_user_by('login', $phone);
        if ( $wp_user_for_address ) {
            $saved_city = get_user_meta( $wp_user_for_address->ID, 'billing_city', true );
            $saved_address = get_user_meta( $wp_user_for_address->ID, 'billing_address_1', true );
        }
    }

    // محاسبه‌ی سطح فعلی و سطح بعدی بر اساس آستانه‌های واقعی خرید که در تب
    // «گروه‌های تخفیف» تعریف شده‌اند (جایگزین سه سطح ثابت قبلی)
    $current_tier_label = 'مشتری';
    $next_tier_label = '';
    $next_tier_target = 0;
    $current_tier_min = 0;

    if ( function_exists('lcm_get_discount_groups') ) {
        $all_groups = lcm_get_discount_groups();
        $tiered_groups = array_filter( $all_groups, function($g) { return isset($g['min_spend']) && floatval($g['min_spend']) > 0; } );
        usort( $tiered_groups, function($a, $b) { return floatval($a['min_spend']) <=> floatval($b['min_spend']); } );
        $tiered_groups = array_values( $tiered_groups );

        foreach ( $tiered_groups as $idx => $tg ) {
            if ( $tg['slug'] === $group ) {
                $current_tier_label = $tg['label'];
                $current_tier_min = floatval($tg['min_spend']);
                if ( isset($tiered_groups[$idx + 1]) ) {
                    $next_tier_label = $tiered_groups[$idx + 1]['label'];
                    $next_tier_target = floatval($tiered_groups[$idx + 1]['min_spend']);
                }
                break;
            }
        }

        // اگر مشتری هنوز در هیچ سطحی نیست، سطح بعدی همان اولین سطح تعریف‌شده است
        if ( empty($next_tier_label) && $current_tier_min === 0 && ! empty($tiered_groups) ) {
            $next_tier_label = $tiered_groups[0]['label'];
            $next_tier_target = floatval($tiered_groups[0]['min_spend']);
        }
    }

    $progress = 100;
    $remaining = 0;
    if ( $next_tier_target > 0 && $total_spent < $next_tier_target ) {
        $progress = ($total_spent / $next_tier_target) * 100;
        $remaining = $next_tier_target - $total_spent;
    } elseif ( $next_tier_target === 0 && $current_tier_min > 0 ) {
        // مشتری در بالاترین سطح تعریف‌شده قرار دارد
        $progress = 100;
        $remaining = 0;
    }

    // اطلاعات گروه تخفیف فعال این مشتری (در صورت نصب افزونه‌ی باشگاه مشتریان)
    $discount_group_label = '';
    $discount_group_percent = 0;
    if ( function_exists('lcm_get_discount_group_by_slug') ) {
        $active_group = lcm_get_discount_group_by_slug( $group );
        if ( $active_group && floatval($active_group['percent']) > 0 ) {
            $discount_group_label   = $active_group['label'];
            $discount_group_percent = floatval($active_group['percent']);
        }
    }

    // نشان‌های افتخار (محاسبه‌ی زنده بر اساس تاریخچه‌ی واقعی سفارش‌ها)
    $badges = function_exists('lcm_compute_user_badges') ? lcm_compute_user_badges( $phone ) : array();

    // امتیاز وفاداری فعلی
    $loyalty_points = intval( $club_member->loyalty_points );

    // چالش هفتگی فعال
    $challenge_data = null;
    if ( function_exists('lcm_get_active_weekly_challenge') ) {
        $active_challenge = lcm_get_active_weekly_challenge();
        if ( $active_challenge ) {
            $challenge_data = array(
                'label'    => $active_challenge['label'],
                'target'   => intval( $active_challenge['target_count'] ),
                'progress' => lcm_get_challenge_progress( $phone, $active_challenge ),
            );
        }
    }

    // تحلیل شخصی ۳۰ روز اخیر («قهوه من در طول زمان»)
    $analytics = array( 'order_count' => 0, 'total_spent' => 0, 'caffeine_mg' => 0, 'weekly_counts' => array(0, 0, 0, 0) );
    if ( $wp_user ) {
        $thirty_days_ago = strtotime( '-30 days' );
        $recent_orders = wc_get_orders( array(
            'customer_id'  => $wp_user->ID,
            'status'       => array( 'processing', 'completed' ),
            'date_created' => '>=' . $thirty_days_ago,
            'limit'        => -1,
        ) );

        $coffee_item_count = 0;
        foreach ( $recent_orders as $r_order ) {
            $analytics['order_count']++;
            $analytics['total_spent'] += floatval( $r_order->get_total() );

            $created_ts = $r_order->get_date_created() ? $r_order->get_date_created()->getTimestamp() : time();
            $weeks_ago = intdiv( time() - $created_ts, WEEK_IN_SECONDS );
            if ( $weeks_ago >= 0 && $weeks_ago < 4 ) {
                $analytics['weekly_counts'][3 - $weeks_ago]++;
            }

            foreach ( $r_order->get_items() as $r_item ) {
                $r_cats = wp_get_post_terms( $r_item->get_product_id(), 'product_cat', array( 'fields' => 'slugs' ) );
                if ( ! is_wp_error( $r_cats ) && array_intersect( $r_cats, array( 'coffee', 'قهوه', 'hot-drinks', 'گرم' ) ) ) {
                    $coffee_item_count++;
                }
            }
        }
        // تخمین تقریبی کافئین: هر آیتم قهوه/گرم را معادل ~۸۰ میلی‌گرم کافئین در نظر می‌گیریم (فقط یک تخمین سرگرم‌کننده، نه دقیق پزشکی)
        $analytics['caffeine_mg'] = $coffee_item_count * 80;
    }

    // ارسال پاسخ صحیح و پاکسازی شده
    wp_send_json_success(array(
        'name' => $name,
        'phone' => $phone,
        'wallet' => number_format($wallet_balance),
        'group' => $group,
        'orders' => $orders_html,
        'wallet_ledger' => $wallet_ledger_html,
        'referral_code' => $club_member->referral_code,
        'saved_city' => $saved_city,
        'saved_address' => $saved_address,
        'birthday_countdown' => $birthday_text,
        'total_spent' => $total_spent,
        'progress_percent' => min(100, max(5, $progress)),
        'remaining_to_next' => $remaining,
        'current_tier_label' => $current_tier_label,
        'next_tier_label' => $next_tier_label,
        'discount_group_label' => $discount_group_label,
        'discount_group_percent' => $discount_group_percent,
        'badges' => $badges,
        'points' => $loyalty_points,
        'challenge' => $challenge_data,
        'analytics' => $analytics,
    ));
    exit;
}

/* ==========================================================================
   واکشی HTML گرید محصولات یک دسته‌بندی خاص، بدون رفرش کامل صفحه.
   دقیقاً از همان پارشیالی استفاده می‌کند که بار اول صفحه هم از آن استفاده
   می‌کند، تا هیچ منطقی (قیمت/تخفیف/موجودی) دوبار و به‌صورت جدا نوشته نشود.
========================================================================== */
/* ==========================================================================
   جستجوی محصولات — هم روی عنوان، هم روی توضیحات محصول جستجو می‌کند.
   از $wpdb مستقیم استفاده می‌شود (نه WP_Query) تا:
   ۱) حروف عربی/فارسی (ي/ی و ك/ک) که ظاهراً یکسان ولی از نظر کد متفاوتند، یکسان در نظر گرفته شوند
   ۲) به‌جای «همه‌ی کلمات باید match شوند» (رفتار پیش‌فرض WordPress)، هر بخشی از عبارت جستجو کافی باشد
========================================================================== */
add_action('wp_ajax_lcm_search_products', 'lcm_ajax_search_products');
add_action('wp_ajax_nopriv_lcm_search_products', 'lcm_ajax_search_products');
function lcm_ajax_search_products() {
    $query_str = isset($_POST['query']) ? sanitize_text_field( wp_unslash($_POST['query']) ) : '';
    if ( mb_strlen($query_str) < 2 ) { wp_send_json_success(array()); }

    // یکسان‌سازی حروف عربی/فارسی که ظاهراً یکسانند ولی کد یونیکد متفاوت دارند
    $normalize = function( $str ) {
        $str = str_replace( array('ي', 'ك', 'ة', 'ۀ'), array('ی', 'ک', 'ه', 'ه'), $str );
        return $str;
    };
    $normalized_query = $normalize( $query_str );

    global $wpdb;
    $like = '%' . $wpdb->esc_like( $normalized_query ) . '%';

    // عنوان و محتوا/خلاصه را با نسخه‌ی یکسان‌سازی‌شده مقایسه می‌کنیم تا حروف عربی/فارسی فرقی نکند
    $product_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'product' AND post_status = 'publish'
         AND (
             REPLACE(REPLACE(REPLACE(REPLACE(post_title, 'ي','ی'), 'ك','ک'), 'ة','ه'), 'ۀ','ه') LIKE %s
             OR REPLACE(REPLACE(REPLACE(REPLACE(post_content, 'ي','ی'), 'ك','ک'), 'ة','ه'), 'ۀ','ه') LIKE %s
             OR REPLACE(REPLACE(REPLACE(REPLACE(post_excerpt, 'ي','ی'), 'ك','ک'), 'ة','ه'), 'ۀ','ه') LIKE %s
         )
         ORDER BY post_title ASC
         LIMIT 15",
        $like, $like, $like
    ) );

    if ( empty($product_ids) ) { wp_send_json_success(array()); }

    $results = array();
    foreach ( $product_ids as $pid ) {
        $p_woo = wc_get_product( $pid );
        if ( ! $p_woo ) { continue; }

        $post_item = get_post( $pid );
        $terms = get_the_terms( $pid, 'product_cat' );
        $cat_id = ( $terms && ! is_wp_error($terms) && ! empty($terms) ) ? $terms[0]->term_id : 0;

        $desc = wp_strip_all_tags( $post_item->post_excerpt ?: $post_item->post_content );
        $snippet = mb_substr( $desc, 0, 60 ) . ( mb_strlen($desc) > 60 ? '...' : '' );

        $results[] = array(
            'id'       => $pid,
            'title'    => $post_item->post_title,
            'snippet'  => $snippet,
            'price'    => number_format( floatval($p_woo->get_price()) ),
            'image'    => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '',
            'cat_id'   => $cat_id,
        );
    }

    wp_send_json_success( $results );
}

add_action('wp_ajax_lcm_get_menu_grid', 'lcm_ajax_get_menu_grid');
add_action('wp_ajax_nopriv_lcm_get_menu_grid', 'lcm_ajax_get_menu_grid');
function lcm_ajax_get_menu_grid() {
    $current_cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;

    $cat_products = array();
    if ($current_cat_id > 0) {
        $cat_products = get_posts(array(
            'post_type' => 'product',
            'numberposts' => 30,
            'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $current_cat_id))
        ));
    }

    $current_cat_obj = get_term($current_cat_id, 'product_cat');
    $cat_slug_for_js = ($current_cat_obj && !is_wp_error($current_cat_obj)) ? $current_cat_obj->slug : 'all';

    ob_start();
    include LCM_PLUGIN_DIR . 'public/menu-grid-partial.php';
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html, 'cat_slug' => $cat_slug_for_js));
}

add_action('wp_ajax_lcm_get_products_basic_info', 'lcm_ajax_get_products_basic_info');
add_action('wp_ajax_nopriv_lcm_get_products_basic_info', 'lcm_ajax_get_products_basic_info');
function lcm_ajax_get_products_basic_info() {
    $ids_str = isset($_POST['ids']) ? sanitize_text_field($_POST['ids']) : '';
    $ids = array_filter(array_map('intval', explode(',', $ids_str)));
    if (empty($ids)) { wp_send_json_error(); }

    $viewer_group_slug = '';
    if ( isset($_COOKIE['lcm_user_phone']) ) {
        global $wpdb;
        $phone = sanitize_text_field($_COOKIE['lcm_user_phone']);
        $viewer_group_slug = $wpdb->get_var($wpdb->prepare("SELECT user_group FROM {$wpdb->prefix}lcm_club_members WHERE phone = %s", $phone));
    }

    $results = array();
    foreach ($ids as $pid) {
        $product = wc_get_product($pid);
        if (!$product) continue;

        $price = floatval($product->get_price());
        if ( ! empty($viewer_group_slug) && function_exists('lcm_get_applicable_discount') ) {
            $rule = lcm_get_applicable_discount($pid, $viewer_group_slug);
            if ($rule) { $price = $price - (($price * floatval($rule['percent'])) / 100); }
        }

        $cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
        $results[] = array(
            'id' => $pid,
            'title' => $product->get_name(),
            'price' => $price,
            'cat_slug' => !empty($cats) && !is_wp_error($cats) ? $cats[0] : 'all',
        );
    }

    wp_send_json_success($results);
}

/**
 * آیا هنوز داخل پنجره‌ی ۲۴ ساعته‌ی مجاز برای ثبت/ویرایش نظر هستیم؟
 * مبنا: زمان ایجاد سفارش (نه لحظه‌ی ثبت اولین نظر) تا هم برای سفارش‌های
 * قدیمی‌تر که هنوز نظر نداده‌اند، و هم برای ویرایش نظر قبلی، یکسان باشد.
 */
function lcm_is_within_rating_window( $order ) {
    $created = $order->get_date_created();
    if ( ! $created ) { return false; }
    return ( time() - $created->getTimestamp() ) <= DAY_IN_SECONDS;
}

/* ==========================================================================
   نمایشگر آشپزخانه (KDS) — تایید پین، واکشی زنده‌ی سفارش‌ها، و تغییر وضعیت
========================================================================== */
/* ==========================================================================
   رزرو آنلاین میز
========================================================================== */
add_action('wp_ajax_lcm_submit_reservation',        'lcm_submit_reservation');
add_action('wp_ajax_nopriv_lcm_submit_reservation', 'lcm_submit_reservation');
function lcm_submit_reservation() {
    $name     = isset($_POST['name'])     ? sanitize_text_field($_POST['name'])     : '';
    $phone    = isset($_POST['phone'])    ? sanitize_text_field($_POST['phone'])    : '';
    $guests   = isset($_POST['guests'])   ? intval($_POST['guests'])                : 2;
    $date     = isset($_POST['date'])     ? sanitize_text_field($_POST['date'])     : '';
    $time     = isset($_POST['time'])     ? sanitize_text_field($_POST['time'])     : '';
    $note     = isset($_POST['note'])     ? sanitize_textarea_field($_POST['note']) : '';

    if ( empty($name) || empty($phone) || empty($date) || empty($time) ) {
        wp_send_json_error( array('message' => 'لطفاً همه‌ی فیلدهای الزامی را پر کنید.') );
    }

    // 🔒 جلوگیری از اسپم: هر شماره حداکثر هر ۶۰ ثانیه یک رزرو می‌تواند ثبت کند
    $cooldown_key = 'lcm_reservation_cooldown_' . md5( $phone );
    if ( false !== get_transient( $cooldown_key ) ) {
        wp_send_json_error( array('message' => 'همین الان یک رزرو ثبت کردید؛ کمی صبر کنید.') );
    }

    if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time) ) {
        wp_send_json_error( array('message' => 'فرمت تاریخ یا ساعت نامعتبر است.') );
    }
    $reserved_at = $date . ' ' . $time . ':00';
    if ( strtotime($reserved_at) < time() ) {
        wp_send_json_error( array('message' => 'زمان رزرو باید در آینده باشد.') );
    }
    $guests = max(1, min(50, $guests));

    global $wpdb;
    $table = $wpdb->prefix . 'lcm_reservations';
    $wpdb->insert( $table, array(
        'name'        => $name,
        'phone'       => $phone,
        'guests'      => $guests,
        'reserved_at' => $reserved_at,
        'note'        => mb_substr($note, 0, 500),
        'status'      => 'pending',
    ), array('%s','%s','%d','%s','%s','%s') );

    // اعلان به مدیر (اختیاری — اگه ایمیل وردپرس تنظیم شده باشه)
    $admin_email = get_option('admin_email');
    $cafe_name   = get_option('lcm_cafe_name', 'کافه');
    wp_mail( $admin_email,
        "رزرو جدید — {$cafe_name}",
        "نام: {$name}\nشماره: {$phone}\nتعداد نفر: {$guests}\nزمان: {$reserved_at}\nیادداشت: {$note}"
    );

    set_transient( $cooldown_key, 1, 60 );
    wp_send_json_success( array('message' => 'رزرو شما با موفقیت ثبت شد! به زودی با شما تماس می‌گیریم 🙏') );
}

add_action('wp_ajax_nopriv_lcm_kds_check_pin', 'lcm_kds_check_pin');
add_action('wp_ajax_lcm_kds_check_pin', 'lcm_kds_check_pin');
add_action('wp_ajax_nopriv_lcm_kds_check_pin', 'lcm_kds_check_pin');
function lcm_kds_check_pin() {
    $entered_pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : '';
    $correct_pin = get_option('lcm_kds_pin', '1234');

    if ( ! session_id() && ! headers_sent() ) { session_start(); }

    // 🔒 محدودیت تلاش: بدون این محدودیت، یه اسکریپت ساده می‌تونست هزاران پین
    // رو در چند ثانیه امتحان کنه و یه پین ۴ رقمی رو به‌سرعت پیدا کنه.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $fail_key = 'lcm_kds_pin_fails_' . md5( $ip );
    $fails = intval( get_transient( $fail_key ) );
    if ( $fails >= 10 ) {
        wp_send_json_error( array( 'message' => 'تعداد تلاش‌های ناموفق زیاد است. ۱۵ دقیقه دیگر امتحان کنید.' ) );
    }

    if ( hash_equals( (string) $correct_pin, $entered_pin ) ) {
        $_SESSION['lcm_kds_unlocked'] = 1;
        delete_transient( $fail_key );
        wp_send_json_success();
    }

    set_transient( $fail_key, $fails + 1, 15 * MINUTE_IN_SECONDS );
    wp_send_json_error(array('message' => 'پین اشتباه است.'));
}

function lcm_kds_is_unlocked() {
    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    return ! empty( $_SESSION['lcm_kds_unlocked'] );
}

add_action('wp_ajax_lcm_kds_get_orders', 'lcm_kds_get_orders');
add_action('wp_ajax_nopriv_lcm_kds_get_orders', 'lcm_kds_get_orders');
/* ==========================================================================
   دکمه‌ی «صدا زدن گارسون» — مشتری از پشت میز کمک می‌خواهد، روی نمایشگر
   آشپزخانه (یا هرجایی که KDS باز باشد) به‌صورت زنده دیده می‌شود.
========================================================================== */
add_action('wp_ajax_lcm_call_waiter', 'lcm_call_waiter');
add_action('wp_ajax_nopriv_lcm_call_waiter', 'lcm_call_waiter');
function lcm_call_waiter() {
    $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
    if ( ! isset($_POST['lcm_nonce']) || ! wp_verify_nonce( sanitize_text_field( $_POST['lcm_nonce'] ), 'lcm_public_actions' ) ) {
        wp_send_json_error( array( 'message' => 'نشست شما منقضی شده، لطفاً صفحه را رفرش کنید.' ) );
    }
    if ( $table_id <= 0 ) { wp_send_json_error( array( 'message' => 'میز نامعتبر است.' ) ); }

    // 🔒 محدودیت درخواست: هر میز حداکثر هر ۶۰ ثانیه یک‌بار می‌تواند گارسون صدا بزند
    $cooldown_key = 'lcm_waiter_cooldown_' . $table_id;
    if ( false !== get_transient( $cooldown_key ) ) {
        wp_send_json_error( array( 'message' => 'همین الان یک درخواست فرستادید؛ کمی صبر کنید.' ) );
    }
    set_transient( $cooldown_key, 1, 60 );

    $calls = get_transient( 'lcm_active_waiter_calls' );
    if ( ! is_array( $calls ) ) { $calls = array(); }

    $calls[ $table_id ] = array(
        'table_id'   => $table_id,
        'created_ts' => time() * 1000,
    );
    set_transient( 'lcm_active_waiter_calls', $calls, 30 * MINUTE_IN_SECONDS );

    wp_send_json_success( array( 'message' => 'درخواست شما برای گارسون ارسال شد 🔔' ) );
}

add_action('wp_ajax_lcm_get_waiter_calls', 'lcm_get_waiter_calls');
add_action('wp_ajax_nopriv_lcm_get_waiter_calls', 'lcm_get_waiter_calls');
function lcm_get_waiter_calls() {
    if ( ! lcm_kds_is_unlocked() ) { wp_send_json_error(); }
    $calls = get_transient( 'lcm_active_waiter_calls' );
    if ( ! is_array( $calls ) ) { $calls = array(); }
    wp_send_json_success( array_values( $calls ) );
}

add_action('wp_ajax_lcm_ack_waiter_call', 'lcm_ack_waiter_call');
add_action('wp_ajax_nopriv_lcm_ack_waiter_call', 'lcm_ack_waiter_call');
function lcm_ack_waiter_call() {
    if ( ! lcm_kds_is_unlocked() ) { wp_send_json_error(); }
    $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;

    $calls = get_transient( 'lcm_active_waiter_calls' );
    if ( is_array( $calls ) && isset( $calls[ $table_id ] ) ) {
        unset( $calls[ $table_id ] );
        set_transient( 'lcm_active_waiter_calls', $calls, 30 * MINUTE_IN_SECONDS );
    }
    wp_send_json_success();
}

function lcm_kds_get_orders() {
    if ( ! lcm_kds_is_unlocked() ) { wp_send_json_error(array('message' => 'ابتدا پین را وارد کنید.')); }
    global $wpdb;

    // سفارش‌هایی که واقعاً برای آماده‌سازی به آشپزخانه رسیده‌اند (پرداخت‌شده) و هنوز تحویل داده نشده‌اند
    $orders = wc_get_orders(array(
        'limit'   => 40,
        'status'  => array('processing'),
        'orderby' => 'date',
        'order'   => 'ASC', // قدیمی‌ترین اول (FIFO)
    ));

    $formatted = array();
    foreach ($orders as $order) {
        // گروه‌بندی آیتم‌های یکسان برای خوانایی بهتر روی کارت (مثلاً «لاته ×۲» به‌جای دو ردیف جدا)
        $grouped_items = array();
        foreach ($order->get_items() as $item) {
            $name = $item->get_name();
            if (!isset($grouped_items[$name])) { $grouped_items[$name] = 0; }
            $grouped_items[$name]++;
        }
        $items_list = array();
        foreach ($grouped_items as $name => $count) {
            $items_list[] = $count > 1 ? ($name . ' ×' . $count) : $name;
        }

        $order_type = $order->get_meta('_lcm_order_type');
        $table_meta = $order->get_meta('_lcm_table_id');
        $is_takeaway = ($order_type === 'takeaway');

        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $kitchen_note = $order->get_meta('_lcm_kitchen_note');

        // نشان سطح وفاداری مشتری (اگر عضو باشگاه باشد) کنار نامش، برای اطلاع باریستا
        $customer_tier_label = '';
        $customer_phone = $order->get_billing_phone();
        if ( ! empty( $customer_phone ) && function_exists( 'lcm_get_discount_group_by_slug' ) ) {
            $member_group_slug = $wpdb->get_var( $wpdb->prepare( "SELECT user_group FROM {$wpdb->prefix}lcm_club_members WHERE phone = %s", $customer_phone ) );
            $member_group = lcm_get_discount_group_by_slug( $member_group_slug );
            if ( $member_group ) { $customer_tier_label = $member_group['label']; }
        }

        $formatted[] = array(
            'id'            => $order->get_id(),
            'items'         => $items_list,
            'is_takeaway'   => $is_takeaway,
            'location_label'=> $is_takeaway ? 'بیرون‌بر 🛍️' : ( is_numeric($table_meta) ? ('میز ' . $table_meta) : 'سالن' ),
            'customer_name' => $customer_name,
            'customer_tier' => $customer_tier_label,
            'note'          => $kitchen_note ? $kitchen_note : '',
            'requested_time'=> $order->get_meta('_lcm_requested_time') ?: '',
            'created_ts'    => $order->get_date_created() ? $order->get_date_created()->getTimestamp() * 1000 : 0,
            'kds_status'    => $order->get_meta('_lcm_kds_status') ?: 'new',
        );
    }

    wp_send_json_success(array(
        'orders'         => $formatted,
        'timer_minutes'  => intval(get_option('lcm_kds_timer_minutes', 7)),
    ));
}

add_action('wp_ajax_lcm_kds_update_status', 'lcm_kds_update_status');
add_action('wp_ajax_nopriv_lcm_kds_update_status', 'lcm_kds_update_status');
function lcm_kds_update_status() {
    if ( ! lcm_kds_is_unlocked() ) { wp_send_json_error(array('message' => 'ابتدا پین را وارد کنید.')); }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $new_status = isset($_POST['kds_status']) ? sanitize_text_field($_POST['kds_status']) : '';
    if (!$order_id || !in_array($new_status, array('preparing', 'ready'), true)) {
        wp_send_json_error(array('message' => 'درخواست نامعتبر.'));
    }

    $order = wc_get_order($order_id);
    if (!$order) { wp_send_json_error(array('message' => 'سفارش پیدا نشد.')); }

    $order->update_meta_data('_lcm_kds_status', $new_status);
    $order->save();

    // وقتی «آماده شد» زده می‌شود، وضعیت واقعی سفارش هم به «تکمیل‌شده» تغییر می‌کند
    // تا هم در گزارش‌ها ثبت شود و هم صفحه‌ی رهگیری مشتری بلافاصله باخبر شود.
    // 🔒 این خط باعث می‌شود ووکامرس ایمیل خودکار «سفارش تکمیل شد» بفرستد و همچنین
    // نوتیف Push ما فعال می‌شود — اگر هرکدام (مثلاً به‌خاطر نبود SMTP) خطا بدهند،
    // نباید کل عملیات KDS با کرش مواجه شود.
    if ($new_status === 'ready') {
        try {
            $order->update_status('completed', 'توسط باریستا در نمایشگر آشپزخانه آماده اعلام شد.');
        } catch ( \Throwable $e ) {
            error_log( 'LCM KDS Status/Notify Error (order_id=' . $order_id . '): ' . $e->getMessage() );
        }
    }

    delete_transient('lcm_kds_orders_cache');
    wp_send_json_success();
}

/* ==========================================================================
   ذخیره‌ی اشتراک اعلان مرورگر (Push Subscription) برای این شماره تلفن
========================================================================== */
add_action('wp_ajax_lcm_save_push_subscription', 'lcm_save_push_subscription');
add_action('wp_ajax_nopriv_lcm_save_push_subscription', 'lcm_save_push_subscription');
function lcm_save_push_subscription() {
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $endpoint = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';
    $p256dh = isset($_POST['p256dh']) ? sanitize_text_field($_POST['p256dh']) : '';
    $auth = isset($_POST['auth']) ? sanitize_text_field($_POST['auth']) : '';

    if ( empty($phone) || empty($endpoint) || empty($p256dh) || empty($auth) ) {
        wp_send_json_error( array('message' => 'اطلاعات ناقص است.') );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'lcm_push_subscriptions';
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE endpoint = %s", $endpoint ) );

    if ( $existing ) {
        $wpdb->update( $table, array('phone' => $phone, 'p256dh' => $p256dh, 'auth' => $auth), array('id' => $existing), array('%s','%s','%s'), array('%d') );
    } else {
        $wpdb->insert( $table, array('phone' => $phone, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth), array('%s','%s','%s','%s') );
    }

    wp_send_json_success();
}

// ارسال اعلان واقعی «سفارش آماده شد» به محض تغییر وضعیت سفارش
add_action('woocommerce_order_status_completed', 'lcm_trigger_order_ready_push');
function lcm_trigger_order_ready_push( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $phone = $order->get_billing_phone();
    if ( empty($phone) || ! function_exists('lcm_notify_order_ready') ) return;
    lcm_notify_order_ready( $phone, $order_id );
}

add_action('wp_ajax_lcm_rate_order', 'lcm_ajax_rate_order');
add_action('wp_ajax_nopriv_lcm_rate_order', 'lcm_ajax_rate_order');
function lcm_ajax_rate_order() {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $stars = isset($_POST['stars']) ? absint($_POST['stars']) : 0;
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    if (!$order_id || $stars < 1 || $stars > 5 || empty($phone)) {
        wp_send_json_error(array('message' => 'اطلاعات نامعتبر است.'));
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_billing_phone() !== $phone) {
        // اجازه نده کسی برای سفارش دیگران امتیاز ثبت کند
        wp_send_json_error(array('message' => 'دسترسی غیرمجاز.'));
    }

    if ( ! lcm_is_within_rating_window( $order ) ) {
        wp_send_json_error(array('message' => 'مهلت ۲۴ ساعته برای ثبت/ویرایش نظر این سفارش به پایان رسیده.'));
    }

    global $wpdb;
    $ratings_table = $wpdb->prefix . 'lcm_order_ratings';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ratings_table WHERE order_id = %d", $order_id));

    if ($existing) {
        $wpdb->update( $ratings_table, array( 'stars' => $stars, 'updated_at' => current_time('mysql') ), array( 'order_id' => $order_id ), array('%d','%s'), array('%d') );
        wp_send_json_success(array('message' => 'نظر شما به‌روزرسانی شد ✅', 'editable_until' => ( $order->get_date_created()->getTimestamp() + DAY_IN_SECONDS ) * 1000));
    } else {
        $wpdb->insert( $ratings_table, array(
            'order_id' => $order_id,
            'phone'    => $phone,
            'stars'    => $stars,
        ));
        wp_send_json_success(array('message' => 'ممنون از نظر شما! 🙏', 'editable_until' => ( $order->get_date_created()->getTimestamp() + DAY_IN_SECONDS ) * 1000));
    }
}

/**
 * پسندیدن/لغو پسندیدن یک آیتم مشخص از یک سفارش (مستقل از امتیاز کلی سفارش).
 * مشتری می‌تواند فقط چند آیتمی که واقعاً دوست داشته را علامت بزند، نه همه را.
 */
add_action('wp_ajax_lcm_toggle_item_like', 'lcm_ajax_toggle_item_like');
add_action('wp_ajax_nopriv_lcm_toggle_item_like', 'lcm_ajax_toggle_item_like');
function lcm_ajax_toggle_item_like() {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    if (!$order_id || !$product_id || empty($phone)) {
        wp_send_json_error(array('message' => 'اطلاعات نامعتبر است.'));
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_billing_phone() !== $phone) {
        wp_send_json_error(array('message' => 'دسترسی غیرمجاز.'));
    }

    if ( ! lcm_is_within_rating_window( $order ) ) {
        wp_send_json_error(array('message' => 'مهلت ۲۴ ساعته برای این سفارش به پایان رسیده.'));
    }

    // این محصول واقعاً باید جزو آیتم‌های همین سفارش باشد
    $order_product_ids = array();
    foreach ($order->get_items() as $item) { $order_product_ids[] = $item->get_product_id(); }
    if (!in_array($product_id, $order_product_ids, true)) {
        wp_send_json_error(array('message' => 'این آیتم در این سفارش وجود ندارد.'));
    }

    global $wpdb;
    $likes_table = $wpdb->prefix . 'lcm_liked_items';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $likes_table WHERE order_id = %d AND product_id = %d", $order_id, $product_id));

    if ($existing) {
        $wpdb->delete($likes_table, array('id' => $existing), array('%d'));
        wp_send_json_success(array('liked' => false));
    } else {
        $wpdb->insert($likes_table, array('order_id' => $order_id, 'product_id' => $product_id, 'phone' => $phone));
        wp_send_json_success(array('liked' => true));
    }
}

add_action('wp_ajax_lcm_update_user_profile', 'lcm_ajax_update_user_profile');
add_action('wp_ajax_nopriv_lcm_update_user_profile', 'lcm_ajax_update_user_profile');
function lcm_ajax_update_user_profile() {
    $phone = isset($_POST['user_phone']) ? sanitize_text_field($_POST['user_phone']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';

    if (empty($phone)) { wp_send_json_error(array('message' => 'شماره موبایل یافت نشد.')); }
    global $wpdb; $table_name = $wpdb->prefix . 'lcm_club_members';
    $wpdb->update($table_name, array('name' => $name), array('phone' => $phone));

    if(class_exists('WooCommerce')){
        $wp_user = get_user_by('login', $phone);
        if ($wp_user) {
            $user_id = $wp_user->ID;
            wp_update_user(array('ID' => $user_id, 'display_name' => $name, 'first_name' => $name));
            update_user_meta($user_id, 'billing_city', $city);
            update_user_meta($user_id, 'billing_address_1', $address);
        }
    }
    wp_send_json_success(array('name' => $name));
}

// --- جایگزینی خودکار و هوشمند صفحه پرداخت ---
add_filter( 'template_include', 'lcm_auto_load_checkout_template', 99 );
function lcm_auto_load_checkout_template( $template ) {
    if ( function_exists('is_checkout') && is_checkout() ) {
        $file = LCM_PLUGIN_DIR . 'public/checkout-template.php';
        if ( file_exists( $file ) ) {
            return $file;
        }
    }
    return $template;
}

/* ==========================================================================
   واکشی امن وضعیت زنده‌ی سفارش برای صفحه‌ی رهگیری (بدون رفرش کامل صفحه)
   دسترسی فقط با کلید صحیح سفارش (order key) مجاز است، نه صرفاً با شماره سفارش.
========================================================================== */
add_action('wp_ajax_lcm_get_order_status', 'lcm_ajax_get_order_status');
add_action('wp_ajax_nopriv_lcm_get_order_status', 'lcm_ajax_get_order_status');
function lcm_ajax_get_order_status() {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
    if ( ! $order_id || ! $order_key ) { wp_send_json_error(); }

    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_order_key() !== $order_key ) {
        wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
    }

    wp_send_json_success( array(
        'status'      => $order->get_status(),
        'status_name' => wc_get_order_status_name( $order->get_status() ),
    ) );
}

/* ==========================================================================
   ساده‌سازی واقعی فیلدهای پرداخت ووکامرس (جایگزین ترفند قدیمی مخفی‌سازی با CSS/JS)
   قبلاً فیلدهای غیرضروری با CSS مخفی و با جاوااسکریپت به‌زور پر می‌شدند که
   در صورت غیرفعال بودن جاوااسکریپت باعث خطای اعتبارسنجی و گیر کردن سفارش می‌شد.
========================================================================== */
add_filter( 'woocommerce_checkout_fields', 'lcm_simplify_checkout_fields' );
function lcm_simplify_checkout_fields( $fields ) {
    if ( isset( $fields['billing']['billing_last_name'] ) ) {
        $fields['billing']['billing_last_name']['required'] = false;
        $fields['billing']['billing_last_name']['label']    = 'نام خانوادگی (اختیاری)';
    }
    if ( isset( $fields['billing']['billing_postcode'] ) ) {
        $fields['billing']['billing_postcode']['required'] = false;
    }
    if ( isset( $fields['billing']['billing_company'] ) )  { unset( $fields['billing']['billing_company'] ); }
    if ( isset( $fields['billing']['billing_country'] ) )  { unset( $fields['billing']['billing_country'] ); }
    if ( isset( $fields['billing']['billing_email'] ) )    { $fields['billing']['billing_email']['required'] = false; }

    // فقط سفارش بیرون‌بر واقعاً به آدرس نیاز دارد؛ سفارش سالن نیازی به آدرس ندارد
    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    $order_type = isset($_SESSION['lcm_order_type']) ? sanitize_text_field($_SESSION['lcm_order_type']) : 'salon';
    if ( $order_type !== 'takeaway' ) {
        foreach ( array('billing_address_1', 'billing_city', 'billing_state') as $key ) {
            if ( isset( $fields['billing'][$key] ) ) { $fields['billing'][$key]['required'] = false; }
        }
    }
    return $fields;
}

/**
 * خواندن امن سبد زنده‌ی یک میز (یا سبد ووکامرس در صورت نبود میز) برای نمایش
 * در ریز فاکتور صفحه‌ی تسویه‌حساب. هرگز خطا throw نمی‌کند.
 */
function lcm_get_checkout_cart_summary() {
    $items = array();
    $total = 0;

    if ( ! session_id() && ! headers_sent() ) { session_start(); }
    $table_id = isset($_SESSION['lcm_table_id']) ? intval($_SESSION['lcm_table_id']) : (isset($_COOKIE['lcm_table_id']) ? intval($_COOKIE['lcm_table_id']) : 0);

    if ( $table_id > 0 ) {
        $table_cart = get_transient( 'lcm_table_cart_' . $table_id );
        if ( is_array( $table_cart ) ) {
            foreach ( $table_cart as $line ) {
                $title = isset($line['title']) ? $line['title'] : '';
                if ( ! empty($line['size_label']) ) { $title .= ' (' . $line['size_label'] . ')'; }
                if ( ! empty($line['addon_titles']) && is_array($line['addon_titles']) ) {
                    $title .= ' + ' . implode(' + ', $line['addon_titles']);
                }
                $items[] = array( 'title' => $title, 'price' => floatval($line['price']) );
                $total  += floatval($line['price']);
            }
            return array( 'items' => $items, 'total' => $total, 'source' => 'table' );
        }
    }

    // بدون میز (مثلاً بیرون‌بر بدون QR): استفاده از سبد استاندارد ووکامرس در صورت وجود
    if ( class_exists('WooCommerce') && WC()->cart && ! WC()->cart->is_empty() ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( ! $product ) continue;
            $items[] = array(
                'title' => $product->get_name() . ( $cart_item['quantity'] > 1 ? ' × ' . $cart_item['quantity'] : '' ),
                'price' => floatval( $cart_item['line_total'] ),
            );
            $total += floatval( $cart_item['line_total'] );
        }
        return array( 'items' => $items, 'total' => $total, 'source' => 'wc_cart' );
    }

    return array( 'items' => $items, 'total' => $total, 'source' => 'none' );
}
?>