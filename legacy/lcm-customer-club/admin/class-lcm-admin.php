<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LCM_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_lcm_send_bulk_sms', array( $this, 'handle_bulk_sms_sending' ) );
        add_action( 'wp_ajax_lcm_update_wallet_balance', array( $this, 'lcm_update_wallet_balance' ) );
        add_action( 'wp_ajax_lcm_admin_set_member_group', array( $this, 'admin_set_member_group' ) );
        add_action( 'wp_ajax_lcm_send_direct_message',     array( $this, 'send_direct_message' ) );
        add_action( 'wp_ajax_lcm_search_members_for_dm',   array( $this, 'search_members_for_dm' ) );
    }

    /**
     * جستجوی زنده‌ی مشتریان از دیتابیس باشگاه (برای autocomplete پیام مستقیم)
     */
    public function search_members_for_dm() {
        if ( ! current_user_can('manage_options') ) { wp_send_json_error('دسترسی غیرمجاز'); }
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'lcm_admin_actions' ) ) { wp_send_json_error('نشست منقضی شده؛ صفحه را رفرش کنید.'); }

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if ( strlen($query) < 2 ) { wp_send_json_success(array()); }

        global $wpdb;
        $table = $wpdb->prefix . 'lcm_club_members';
        $like  = '%' . $wpdb->esc_like($query) . '%';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, phone FROM $table WHERE name LIKE %s OR phone LIKE %s ORDER BY name ASC LIMIT 10",
            $like, $like
        ), ARRAY_A );

        wp_send_json_success( $results ?: array() );
    }

    /**
     * ارسال پیام مستقیم به یک مشتری خاص (هم Push + هم پیامک)
     */
    public function send_direct_message() {
        if ( ! current_user_can('manage_options') ) { wp_send_json_error('دسترسی غیرمجاز'); }
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'lcm_admin_actions' ) ) { wp_send_json_error('نشست منقضی شده؛ صفحه را رفرش کنید.'); }

        $phone   = isset($_POST['phone'])   ? sanitize_text_field($_POST['phone'])         : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message'])    : '';
        $channel = isset($_POST['channel']) ? sanitize_text_field($_POST['channel'])        : 'both';

        if ( empty($phone) || empty($message) ) { wp_send_json_error('شماره و متن پیام الزامی است.'); }

        // تایید اینکه این شماره واقعاً عضو باشگاه است
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}lcm_club_members WHERE phone = %s", $phone ) );
        if ( ! $exists ) { wp_send_json_error('این شماره در باشگاه ثبت نشده.'); }

        $cafe_name = get_option( 'lcm_cafe_name', 'کافه' );
        $results   = array();

        if ( in_array( $channel, array('push', 'both'), true ) && function_exists('lcm_send_push_to_phone') ) {
            $sent_count = lcm_send_push_to_phone( $phone, $cafe_name, $message );
            $results[] = $sent_count > 0
                ? '📲 اعلان مرورگر ارسال شد'
                : '⚠️ این مشتری هیچ‌وقت اعلان مرورگر را فعال نکرده — چیزی ارسال نشد';
        }

        if ( in_array( $channel, array('sms', 'both'), true ) ) {
            $sent = self::send_sms( $phone, "📍 {$cafe_name}: {$message}" );
            $results[] = $sent ? '✅ پیامک ارسال شد' : '⚠️ پیامک ارسال نشد (تنظیمات API را بررسی کنید)';
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    public function admin_set_member_group() {
        if ( ! current_user_can('manage_options') ) { wp_send_json_error('دسترسی غیرمجاز'); }
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'lcm_admin_actions' ) ) { wp_send_json_error('نشست منقضی شده؛ صفحه را رفرش کنید.'); }

        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
        $new_group = isset($_POST['group_slug']) ? sanitize_text_field($_POST['group_slug']) : '';
        if ( $member_id <= 0 || empty($new_group) ) { wp_send_json_error('اطلاعات نامعتبر'); }

        global $wpdb;
        $table_name = $wpdb->prefix . 'lcm_club_members';
        $updated = $wpdb->update( $table_name, array('user_group' => $new_group), array('id' => $member_id), array('%s'), array('%d') );

        if ( $updated !== false ) { wp_send_json_success(array('message' => 'گروه مشتری با موفقیت تغییر کرد.')); }
        else { wp_send_json_error('خطا در ذخیره'); }
    }

    public function add_plugin_page() {
        add_menu_page('باشگاه مشتریان کافه', 'باشگاه مشتریان', 'manage_options', 'lcm-club-settings', array( $this, 'create_admin_page' ), 'dashicons-groups', 25);
    }

    public function register_settings() {
        // 🔒 نکته‌ی مهم (رفع باگ اصلی): قبلاً همه‌ی این تنظیمات زیر یک گروه واحد ثبت می‌شدند
        // درحالی‌که در ۳ فرم جداگانه (تب‌های مختلف) ذخیره می‌شدند. وردپرس با ذخیره‌ی هر فرم،
        // تمام گزینه‌های ثبت‌شده‌ی همان گروه را پردازش می‌کند؛ چون فرم‌های دیگر فیلدهای گروه‌های
        // دیگر را ارسال نمی‌کردند، آن مقادیر با خالی/صفر بازنویسی می‌شدند. الان هر تب گروه
        // مستقل خودش را دارد تا ذخیره‌ی یک تب، تنظیمات تب‌های دیگر را پاک نکند.
        register_setting( 'lcm_general_settings_group', 'lcm_referral_reward' );
        register_setting( 'lcm_general_settings_group', 'lcm_target_pages' );
        register_setting( 'lcm_general_settings_group', 'lcm_sms_api_key' );
        register_setting( 'lcm_general_settings_group', 'lcm_sms_sender_num' );
        register_setting( 'lcm_general_settings_group', 'lcm_sms_dedicated_number' );

        register_setting( 'lcm_groups_settings_group', 'lcm_discount_groups' );

        register_setting( 'lcm_gamification_settings_group', 'lcm_weekly_challenge' );
        register_setting( 'lcm_gamification_settings_group', 'lcm_points_conversion_rate' );
        register_setting( 'lcm_gamification_settings_group', 'lcm_birthday_gift_enabled' );
        register_setting( 'lcm_gamification_settings_group', 'lcm_birthday_gift_amount' );

        register_setting( 'lcm_cashback_settings_group', 'lcm_cashback_cat' );
        register_setting( 'lcm_cashback_settings_group', 'lcm_cashback_min_spend' );
        register_setting( 'lcm_cashback_settings_group', 'lcm_cashback_reward' );
        register_setting( 'lcm_cashback_settings_group', 'lcm_cashback_multi_rules' );
    }

    public function get_jalali_today() {
        // (الگوریتم تاریخ شمسی شما عالی بود، حفظ شد)
        $g_y = intval(date('Y')); $g_m = intval(date('m')); $g_d = intval(date('d'));
        $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        if (($g_y % 4 == 0 && $g_y % 100 != 0) || $g_y % 400 == 0) { $g_days_in_month[1] = 29; }
        $g_day_no = 0; for ($i = 0; $i < $g_m - 1; $i++) { $g_day_no += $g_days_in_month[$i]; } $g_day_no += $g_d - 1;
        $j_day_no = $g_day_no - 79;
        if ($j_day_no < 0) {
            $j_day_no = $j_day_no + 365;
            if (($g_y - 1 % 4 == 0 && ($g_y - 1 % 100 != 0)) || ($g_y - 1 % 400 == 0)) { $j_day_no++; }
        }
        if ($j_day_no < 186) { $j_m = intval($j_day_no / 31) + 1; $j_d = ($j_day_no % 31) + 1; } 
        else { $j_day_no -= 186; $j_m = intval($j_day_no / 30) + 7; $j_d = ($j_day_no % 30) + 1; }
        return array('day' => $j_d, 'month' => $j_m);
    }

    public function get_days_until_birthday($b_day, $b_month) {
        $today = $this->get_jalali_today();
        $current_month = $today['month']; $current_day = $today['day'];
        $current_days_passed = 0; for ($m = 1; $m < $current_month; $m++) { $current_days_passed += ($m <= 6) ? 31 : 30; } $current_days_passed += $current_day;
        $birthday_days_passed = 0; for ($m = 1; $m < $b_month; $m++) { $birthday_days_passed += ($m <= 6) ? 31 : 30; } $birthday_days_passed += $b_day;
        $diff = $birthday_days_passed - $current_days_passed;
        if ($diff < 0) { $diff += 365; } 
        return $diff;
    }

    public function create_admin_page() {
        global $wpdb; 
        $table_name = $wpdb->prefix . 'lcm_club_members';
        
        $total_members = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name"));

        // 🔒 رفع باگ: این‌جا قبلاً همیشه فقط ۳ گروه ثابت (bodybuilder/diet/normal) شمارش می‌شد،
        // کاملاً مستقل از گروه‌هایی که مدیر کافه واقعاً در تب «گروه‌های تخفیف» تعریف کرده.
        // اگر گروهی حذف/اضافه/تغییرنام داده می‌شد، این کارت‌ها همچنان همان ۳ گروه قدیمی را نشان می‌دادند.
        $group_stats = array();
        if ( function_exists('lcm_get_discount_groups') ) {
            foreach ( lcm_get_discount_groups() as $grp ) {
                $count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE user_group = %s", $grp['slug'] ) ) );
                $group_stats[] = array( 'label' => $grp['label'], 'color' => $grp['color'], 'count' => $count, 'slug' => $grp['slug'] );
            }
        }

        // میانگین امتیاز مشتریان به سفارش‌ها (ستاره‌هایی که در پنل کاربری ثبت می‌کنند)
        $ratings_table = $wpdb->prefix . 'lcm_order_ratings';
        $avg_rating = 0;
        $ratings_count = 0;
        if ( $wpdb->get_var("SHOW TABLES LIKE '$ratings_table'") === $ratings_table ) {
            $avg_rating = round( floatval( $wpdb->get_var("SELECT AVG(stars) FROM $ratings_table") ), 1 );
            $ratings_count = intval( $wpdb->get_var("SELECT COUNT(*) FROM $ratings_table") );
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        if (isset($_GET['filter_group']) || isset($_GET['filter_month'])) { $active_tab = 'leads'; }

        $filter_group = isset($_GET['filter_group']) ? sanitize_text_field($_GET['filter_group']) : 'all'; 
        $filter_month = isset($_GET['filter_month']) ? intval($_GET['filter_month']) : 0;
        
        $query = "SELECT * FROM $table_name WHERE 1=1"; 
        $params = array();

        if ($filter_group !== 'all') { 
            $query .= " AND user_group = %s"; 
            $params[] = $filter_group;
        } 
        if ($filter_month > 0) { 
            $query .= " AND birth_month = %d"; 
            $params[] = $filter_month;
        } 
        $query .= " ORDER BY id DESC LIMIT 500"; // محدودیت برای جلوگیری از کرش ادمین
        
        if(!empty($params)){
            $members = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $members = $wpdb->get_results($query);
        }

        // داده‌های تب جدید «رضایت مشتریان»: کی چه امتیازی داده و چیا پسندیده شده
        $satisfaction_ratings = array();
        $satisfaction_likes = array();
        if ( $wpdb->get_var("SHOW TABLES LIKE '$ratings_table'") === $ratings_table ) {
            $satisfaction_ratings = $wpdb->get_results(
                "SELECT r.*, m.name as member_name FROM $ratings_table r
                 LEFT JOIN $table_name m ON m.phone = r.phone
                 ORDER BY r.updated_at DESC LIMIT 100"
            );
        }
        $likes_table = $wpdb->prefix . 'lcm_liked_items';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$likes_table'") === $likes_table ) {
            $satisfaction_likes = $wpdb->get_results(
                "SELECT l.*, m.name as member_name FROM $likes_table l
                 LEFT JOIN $table_name m ON m.phone = l.phone
                 ORDER BY l.created_at DESC LIMIT 100"
            );
        }

        require LCM_CLUB_PLUGIN_DIR . 'admin/views/view-settings.php';
    }

    public function lcm_update_wallet_balance() {
        // امنیت: بررسی دسترسی ادمین کل
        if (!current_user_can('manage_options')) { wp_send_json_error('دسترسی غیرمجاز'); }
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'lcm_admin_actions' ) ) { wp_send_json_error('نشست منقضی شده؛ صفحه را رفرش کنید.'); }
        
        $user_id = intval($_POST['user_id']); 
        $new_balance = intval($_POST['balance']);
        
        if ($user_id <= 0 || $new_balance < 0) { wp_send_json_error('دیتای نامعتبر'); }
        
        global $wpdb; 
        $table_name = $wpdb->prefix . 'lcm_club_members';
        $updated = $wpdb->update($table_name, array('wallet_balance' => $new_balance), array('id' => $user_id), array('%d'), array('%d'));
        
        if ($updated !== false) { wp_send_json_success(array('message' => 'موفقیت آمیز')); } 
        else { wp_send_json_error('خطا در ذخیره'); }
    }

    /**
     * ارسال پیامک متنی (پیام مستقیم و پیامک انبوه) از طریق سرویس Trez.ir
     * ⚠️ این سرویس با سرویس OTP («AutoSendCode») که برای کد تایید استفاده می‌شود
     * کاملاً متفاوت است — این یک وب‌سرویس جدا (trezsmswebservice.asmx) با اعتبار
     * جداگانه است که علاوه بر یوزرنیم/پسورد به یک «شماره اختصاصی» نیاز دارد.
     * نکته: نام پارامترهایی مثل 'Passwod' و 'SenderNumebr' در مستندات خودِ Trez
     * همین‌طور (با همین املا) آمده — دقیقاً همان‌ها استفاده می‌شوند.
     */
    public static function send_sms( $phone, $message ) {
        return self::send_sms_trez( array( $phone ), $message );
    }

    public static function send_sms_trez( $phones, $message ) {
        if ( ! class_exists( 'SoapClient' ) ) { return false; }

        $username      = get_option( 'lcm_sms_api_key' );
        $password      = get_option( 'lcm_sms_sender_num' );
        $sender_number = get_option( 'lcm_sms_dedicated_number' );
        if ( empty( $username ) || empty( $password ) || empty( $sender_number ) ) { return false; }

        $phones = array_filter( array_map( 'trim', (array) $phones ) );
        if ( empty( $phones ) ) { return false; }

        try {
            $client = new SoapClient(
                'http://smspanel.trez.ir/trezsmswebservice.asmx?WSDL',
                array( 'cache_wsdl' => WSDL_CACHE_NONE, 'connection_timeout' => 15 )
            );

            if ( count( $phones ) === 1 ) {
                $result = $client->SendOneMessage( array(
                    'Username'         => $username,
                    'Passwod'          => $password,
                    'SenderNumebr'     => $sender_number,
                    'MessageBody'      => $message,
                    'ReciptionNumbers' => $phones[0],
                    'Class'            => '1',
                    'UserMessageId'    => (string) rand( 100, 1000 ),
                ) );
                $status = isset( $result->SendOneMessageResult ) ? $result->SendOneMessageResult : 0;
            } else {
                $result = $client->SendMessageForPhp( array(
                    'Username'         => $username,
                    'Password'        => $password,
                    'SenderNumebr'     => $sender_number,
                    'MessageBody'      => $message,
                    'ReciptionNumbers' => implode( '-', $phones ),
                    'Class'            => '1',
                    'UserMessageId'    => '',
                ) );
                $status = isset( $result->SendMessageForPhpResult ) ? $result->SendMessageForPhpResult : 0;
            }

            // طبق مستندات Trez: مقدار بازگشتی بزرگ‌تر از ۱۰۰۰ یعنی ارسال بدون خطا
            return intval( $status ) > 1000;
        } catch ( \Throwable $e ) {
            error_log( 'LCM Bulk SMS Error: ' . $e->getMessage() );
            return false;
        }
    }

    public function handle_bulk_sms_sending() {
        if (!current_user_can('manage_options')) { wp_send_json_error('دسترسی غیرمجاز'); }
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field($_POST['nonce']), 'lcm_admin_actions' ) ) { wp_send_json_error('نشست منقضی شده؛ صفحه را رفرش کنید.'); }
        $target_group = sanitize_text_field($_POST['target_group']); 
        $sms_message  = sanitize_text_field($_POST['message']); 
        
        if(empty($sms_message)) { wp_send_json_error('متن پیامک خالی است.'); }
        
        global $wpdb; $table_name = $wpdb->prefix . 'lcm_club_members';
        
        if ($target_group === 'all') {
            $phones = $wpdb->get_col("SELECT phone FROM $table_name");
        } else {
            $phones = $wpdb->get_col($wpdb->prepare("SELECT phone FROM $table_name WHERE user_group = %s", $target_group));
        }
        
        if(empty($phones)) { wp_send_json_error('شماره‌ای یافت نشد.'); } 

        // ارسال گروهی در یک درخواست واحد (سریع‌تر از حلقه‌ی تک‌تک)
        $success = self::send_sms_trez( $phones, $sms_message );

        wp_send_json_success(array('count' => $success ? count($phones) : 0, 'total' => count($phones)));
    }
}

global $lcm_admin_instance;
$lcm_admin_instance = new LCM_Admin();