<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ۱. ایجاد منو و زیرمنوها در پیشخوان وردپرس
add_action('admin_menu', 'lcm_admin_menu_setup');
function lcm_admin_menu_setup() {
    add_menu_page(
        'داشبورد کافه',
        '☕ کافه لایو منو',
        'manage_options',
        'live-cafe-menu',
        'lcm_admin_page_dashboard',
        'dashicons-coffee',
        25
    );
    
    add_submenu_page(
        'live-cafe-menu',
        'تنظیمات عمومی و برندینگ',
        '⚙️ تنظیمات عمومی',
        'manage_options',
        'live-cafe-menu' 
    );

    add_submenu_page(
        'live-cafe-menu',
        'نمایشگر آشپزخانه (KDS)',
        '🍳 نمایشگر آشپزخانه',
        'manage_options',
        'lcm-kds-settings',
        'lcm_admin_page_kds_settings'
    );

    add_submenu_page(
        'live-cafe-menu',
        'کدهای QR میزهای کافه',
        '📱 کدهای QR میزها',
        'manage_options',
        'lcm-table-qrcodes',
        'lcm_admin_page_table_qrcodes'
    );

    add_submenu_page(
        'live-cafe-menu',
        'رزروهای آنلاین میز',
        '🗓️ رزروهای میز',
        'manage_options',
        'lcm-reservations',
        'lcm_admin_page_reservations'
    );
}

// ثبت تنظیمات در دیتابیس
add_action( 'admin_init', 'lcm_register_settings' );
function lcm_register_settings() {
    register_setting( 'lcm_settings_group', 'lcm_cafe_name' );
    register_setting( 'lcm_settings_group', 'lcm_cafe_logo' );
    register_setting( 'lcm_settings_group', 'lcm_menu_theme' );
    register_setting( 'lcm_settings_group', 'lcm_max_calories' );
    register_setting( 'lcm_settings_group', 'lcm_table_count' );
    register_setting( 'lcm_settings_group', 'lcm_business_hours', array(
        'sanitize_callback' => function( $raw ) {
            // وردپرس چک‌باکس‌هایی که تیک ندارند را اصلاً در $_POST نمی‌فرستد.
            // بدون این sanitize_callback، کلید 'closed' برای روزهای باز اصلاً
            // ذخیره نمی‌شود و ساختار آرایه می‌تواند خراب شود.
            if ( ! is_array( $raw ) ) { return array(); }
            $clean = array();
            for ( $i = 0; $i <= 6; $i++ ) {
                $day = isset( $raw[ $i ] ) ? $raw[ $i ] : array();
                $clean[ $i ] = array(
                    'open'   => isset( $day['open'] )   ? sanitize_text_field( $day['open'] )   : '08:00',
                    'close'  => isset( $day['close'] )  ? sanitize_text_field( $day['close'] )  : '22:00',
                    'closed' => ! empty( $day['closed'] ) ? 1 : 0,
                );
            }
            return $clean;
        }
    ) );
    register_setting( 'lcm_settings_group', 'lcm_lucky_item_enabled' );
    register_setting( 'lcm_settings_group', 'lcm_lucky_item_discount' );
    register_setting( 'lcm_settings_group', 'lcm_lucky_item_cutoff_hour' );
    register_setting( 'lcm_kds_settings_group', 'lcm_kds_pin' );
    register_setting( 'lcm_kds_settings_group', 'lcm_kds_timer_minutes' );
}

// لود کردن ابزارهای آپلود رسانه وردپرس
add_action('admin_enqueue_scripts', 'lcm_admin_media_uploader');
function lcm_admin_media_uploader($hook) {
    if ('toplevel_page_live-cafe-menu' !== $hook) return;
    wp_enqueue_media();
}

// قلاب پاک‌سازی خودکار کش هنگام تغییر وضعیت سفارش (برای KDS و داشبورد استفاده می‌شود)
add_action('woocommerce_order_status_changed', function() { delete_transient('lcm_radar_latest_orders'); delete_transient('lcm_kds_orders_cache'); });

// ۲. تابع رندر برگه اصلی (داشبورد فروش + تنظیمات برندینگ)
function lcm_admin_page_dashboard() {
    $today_start = date('Y-m-d 00:00:00');
    $today_end   = date('Y-m-d 23:59:59');

    $args = array(
        'date_created' => $today_start . '...' . $today_end,
        'limit'        => -1,
        'return'       => 'ids',
    );
    $today_order_ids = wc_get_orders($args);

    $total_sales = 0;
    $order_count = count($today_order_ids);
    $takeaway_count = 0;
    $table_stats = array();

    foreach ($today_order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
            $total_sales += $order->get_total();
        }

        if ($order->get_meta('_lcm_order_type') === 'takeaway') {
            $takeaway_count++;
        }

        $table_id = $order->get_meta('_lcm_table_id');
        if ($table_id && $table_id !== 'بیرون‌بر 🛍️' && $table_id !== 'سالن') {
            if (!isset($table_stats[$table_id])) { $table_stats[$table_id] = 0; }
            $table_stats[$table_id]++;
        }
    }

    $popular_table = '---';
    if (!empty($table_stats)) {
        arsort($table_stats);
        $popular_table = 'میز ' . array_key_first($table_stats);
    }
    ?>
    <div class="wrap lcm-admin-wrap" style="direction: rtl; text-align: right; font-family: Tahoma, sans-serif; padding-top: 10px;">
        <h1 class="lcm-main-heading">📊 داشبورد مدیریتی و گزارش فروش لایو</h1>
        <p class="lcm-sub-heading">گزارش عملکرد مانیتورینگ و میزان درآمد واقعی امروز کافه (<?php echo date_i18n('l d F Y'); ?>)</p>

        <div class="lcm-stats-grid">
            <div class="lcm-stat-card card-gold">
                <div class="stat-icon">💵</div>
                <div class="stat-info">
                    <h3>فروش خالص امروز</h3>
                    <div class="stat-value"><?php echo number_format($total_sales); ?> <span>تومان</span></div>
                </div>
            </div>
            <div class="lcm-stat-card card-blue">
                <div class="stat-icon">🧾</div>
                <div class="stat-info">
                    <h3>تعداد کل سفارشات</h3>
                    <div class="stat-value"><?php echo $order_count; ?> <span>سفارش</span></div>
                </div>
            </div>
            <div class="lcm-stat-card card-purple">
                <div class="stat-icon">🛍️</div>
                <div class="stat-info">
                    <h3>سفارشات بیرون‌بر</h3>
                    <div class="stat-value"><?php echo $takeaway_count; ?> <span>بسته</span></div>
                </div>
            </div>
            <div class="lcm-stat-card card-red">
                <div class="stat-icon">📍</div>
                <div class="stat-info">
                    <h3>میز پرکاربرد امروز</h3>
                    <div class="stat-value"><?php echo $popular_table; ?></div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 25px;">
            <div class="lcm-table-container" style="flex: 2;">
                <div class="table-header-bar"><h2>📋 آخرین فاکتورهای صادر شده امروز</h2></div>
                <table class="wp-list-table widefat fixed striped posts">
                    <thead>
                        <tr>
                            <th>شماره سفارش</th>
                            <th>میز</th>
                            <th>نوع سرو</th>
                            <th>اقلام سفارش</th>
                            <th>مبلغ فاکتور</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($today_order_ids)) {
                            $reversed_ids = array_reverse($today_order_ids);
                            foreach (array_slice($reversed_ids, 0, 8) as $order_id) {
                                $order = wc_get_order($order_id);
                                $table_id = $order->get_meta('_lcm_table_id');
                                $order_type = $order->get_meta('_lcm_order_type') === 'takeaway' ? '🛍️ بیرون‌بر' : '📍 سالن';
                                $items_summary = array();
                                foreach ($order->get_items() as $item) { $items_summary[] = $item->get_name(); }
                                
                                $status_tg = $order->get_status();
                                $status_label = 'در انتظار'; $status_class = 'status-pending';
                                if($status_tg == 'processing') { $status_label = 'آشپزخانه 🍳'; $status_class = 'status-processing'; }
                                if($status_tg == 'completed') { $status_label = 'تحویل شده ✅'; $status_class = 'status-completed'; }
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $order_id; ?></strong></td>
                                    <td><span class="table-badge"><?php echo $table_id ? $table_id : 'نامشخص'; ?></span></td>
                                    <td><?php echo $order_type; ?></td>
                                    <td><span class="items-text"><?php echo implode(' + ', $items_summary); ?></span></td>
                                    <td><span class="price-text"><?php echo number_format($order->get_total()); ?> ت</span></td>
                                    <td><span class="badge-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">سفارشی ثبت نشده است.☕</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div style="flex: 1; background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                <h2 style="font-size: 1.1rem; font-weight: bold; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">⚙️ تنظیمات برندینگ و ساختار منو</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'lcm_settings_group' ); ?>
                    <p><strong>📛 نام کافه شما:</strong></p>
                    <input type="text" name="lcm_cafe_name" value="<?php echo esc_attr(get_option('lcm_cafe_name', 'کافه آستوریا')); ?>" style="width: 100%; padding: 8px; margin-bottom: 15px; border-radius: 6px; border:1px solid #ccc;" />
                    
                    <p><strong>🖼️ لوگوی کافه:</strong></p>
                    <input type="text" name="lcm_cafe_logo" id="lcm_logo_url" value="<?php echo esc_url(get_option('lcm_cafe_logo')); ?>" style="width: 100%; padding: 8px; margin-bottom: 8px; border-radius: 6px; border:1px solid #ccc; direction: ltr;" />
                    <button type="button" id="lcm_upload_logo_btn" class="button button-secondary" style="width: 100%; margin-bottom: 15px;">آپلود یا انتخاب لوگو</button>
                    <div id="lcm_logo_preview" style="text-align: center; margin-bottom: 15px;">
                        <?php if(get_option('lcm_cafe_logo')): ?>
                            <img src="<?php echo esc_url(get_option('lcm_cafe_logo')); ?>" style="max-height: 60px; border-radius: 6px;" />
                        <?php endif; ?>
                    </div>

                    <p><strong>🎨 تم ظاهری منو:</strong></p>
                    <select name="lcm_menu_theme" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                        <option value="dark" <?php selected( get_option('lcm_menu_theme'), 'dark' ); ?>>تم دارک و لوکس</option>
                        <option value="light" <?php selected( get_option('lcm_menu_theme'), 'light' ); ?>>تم روشن و زنده</option>
                    </select>

                    <p><strong>🔥 سقف مجاز کالری روزانه:</strong></p>
                    <input type="number" name="lcm_max_calories" value="<?php echo esc_attr( get_option('lcm_max_calories', '500') ); ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" />

                    <p><strong>تعداد میزهای سالن کافه:</strong></p>
                    <input type="number" name="lcm_table_count" value="<?php echo esc_attr( get_option('lcm_table_count', '5') ); ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" />

                    <p><strong>🕐 ساعات کاری کافه:</strong></p>
                    <p style="font-size:0.75rem; color:#888; margin-top:0;">در ساعاتی که کافه تعطیل است، به مشتری پیشنهاد «پیش‌سفارش» داده می‌شود (بگوید چه زمانی می‌خواهد تحویل بگیرد).</p>
                    <?php
                    $lcm_hours = get_option('lcm_business_hours', array());
                    if (!is_array($lcm_hours)) { $lcm_hours = array(); }
                    // شناسه‌ی هر روز همان date('w') پی‌اچ‌پی است (۰=یکشنبه ... ۶=شنبه) که برای محاسبه لازم است،
                    // ولی ترتیب نمایش را طبق عرف هفته‌ی ایرانی (شنبه اول) مرتب می‌کنیم، نه ترتیب میلادی.
                    $lcm_day_names = array(0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه');
                    $lcm_display_order = array(6, 0, 1, 2, 3, 4, 5); // شنبه، یکشنبه، دوشنبه، ...
                    ?>
                    <table style="width:100%; margin-bottom:15px; border-collapse:collapse;">
                        <?php foreach ($lcm_display_order as $day_index) :
                            $day_label = $lcm_day_names[$day_index];
                            $day_data = isset($lcm_hours[$day_index]) ? $lcm_hours[$day_index] : array();
                            $open_time = $day_data['open'] ?? '08:00';
                            $close_time = $day_data['close'] ?? '22:00';
                            $is_closed = !empty($day_data['closed']);
                        ?>
                        <tr>
                            <td style="padding:4px 8px 4px 0; font-size:0.8rem; width:80px;"><?php echo esc_html($day_label); ?></td>
                            <td style="padding:4px;"><input type="time" name="lcm_business_hours[<?php echo $day_index; ?>][open]" value="<?php echo esc_attr($open_time); ?>" style="width:100%; padding:5px;"></td>
                            <td style="padding:4px; text-align:center; font-size:0.8rem;">تا</td>
                            <td style="padding:4px;"><input type="time" name="lcm_business_hours[<?php echo $day_index; ?>][close]" value="<?php echo esc_attr($close_time); ?>" style="width:100%; padding:5px;"></td>
                            <td style="padding:4px 0 4px 8px; font-size:0.75rem; white-space:nowrap;">
                                <label><input type="checkbox" name="lcm_business_hours[<?php echo $day_index; ?>][closed]" value="1" <?php checked($is_closed); ?>> تعطیل</label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>

                    <p><strong>🎁 آیتم شانسی روزانه:</strong></p>
                    <p style="font-size:0.75rem; color:#888; margin-top:0;">هر روز یک آیتم تصادفی از منو با تخفیف انتخاب می‌شود و تا ساعت مشخصی روی منو نمایش داده می‌شود.</p>
                    <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="lcm_lucky_item_enabled" value="1" <?php checked( get_option('lcm_lucky_item_enabled', '0'), '1' ); ?>> فعال باشد</label>
                    <div style="display:flex; gap:15px; margin-bottom:15px;">
                        <div style="flex:1;">
                            <label style="font-size:0.8rem;">درصد تخفیف:</label>
                            <input type="number" min="1" max="90" name="lcm_lucky_item_discount" value="<?php echo esc_attr( get_option('lcm_lucky_item_discount', 20) ); ?>" style="width:100%; padding:6px;">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:0.8rem;">تا ساعت (۲۴ ساعته):</label>
                            <input type="number" min="0" max="23" name="lcm_lucky_item_cutoff_hour" value="<?php echo esc_attr( get_option('lcm_lucky_item_cutoff_hour', 14) ); ?>" style="width:100%; padding:6px;">
                        </div>
                    </div>

                    <?php submit_button('💾 ذخیره تغییرات عمومی', 'primary', 'submit', true, array('style' => 'width:100%; padding:8px; font-size:0.95rem;')); ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($){
            $('#lcm_upload_logo_btn').click(function(e) {
                e.preventDefault();
                var custom_uploader = wp.media({
                    title: 'انتخاب لوگوی کافه',
                    button: { text: 'استفاده به عنوان لوگو' },
                    multiple: false
                }).on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#lcm_logo_url').val(attachment.url);
                    $('#lcm_logo_preview').html('<img src="'+attachment.url+'" style="max-height: 60px; border-radius: 6px;" />');
                }).open();
            });
        });
    </script>

    <style>
        .lcm-main-heading { font-size: 1.6rem; font-weight: 800; color: #1e293b; margin: 0 0 5px 0; }
        .lcm-sub-heading { font-size: 0.85rem; color: #64748b; margin: 0 0 20px 0; }
        .lcm-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .lcm-stat-card { background: #fff; border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; }
        .stat-icon { font-size: 1.8rem; background: #f8fafc; padding: 8px; border-radius: 10px; }
        .stat-info h3 { font-size: 0.8rem; color: #64748b; margin: 0 0 2px 0; }
        .stat-value { font-size: 1.4rem; font-weight: 800; color: #0f172a; }
        .stat-value span { font-size: 0.75rem; color: #64748b; font-weight: normal; }
        .card-gold { border-right: 4px solid #d4af37; } .card-blue { border-right: 4px solid #3b82f6; } .card-purple { border-right: 4px solid #a855f7; } .card-red { border-right: 4px solid #ef4444; }
        .lcm-table-container { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.02); overflow: hidden; }
        .table-header-bar { padding: 12px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .table-header-bar h2 { font-size: 0.9rem; margin: 0; color: #334155; }
        .wp-list-table th { font-weight: bold !important; color: #475569 !important; background: #f1f5f9 !important; padding: 10px !important; font-size: 0.8rem !important; }
        .wp-list-table td { padding: 10px !important; vertical-align: middle !important; font-size: 0.8rem; }
        .table-badge { background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 6px; font-weight: bold; }
        .price-text { font-weight: bold; color: #10b981; }
        .badge-status { padding: 3px 6px; border-radius: 5px; font-size: 0.7rem; font-weight: bold; }
        .status-pending { background: #fef3c7; color: #d97706; } .status-processing { background: #dbeafe; color: #2563eb; } .status-completed { background: #d1fae5; color: #059669; }
    </style>
    <?php
}

// 🍳 ۳. صفحه‌ی تنظیمات نمایشگر آشپزخانه (KDS) — خود KDS یک صفحه‌ی مستقل جلوی سایت است،
// این‌جا فقط لینک/پین/تنظیمات مربوط به آن نگه‌داری می‌شود.
function lcm_admin_page_kds_settings() {
    $kds_url = home_url('/kitchen-display/');
    ?>
    <div class="wrap" style="direction: rtl; text-align: right; font-family: Tahoma, sans-serif; padding-top: 10px;">
        <h1 style="color: #e5383b; font-size: 1.6rem; font-weight: bold; margin-bottom: 5px;">🍳 نمایشگر زنده آشپزخانه (KDS)</h1>
        <p style="font-size: 0.85rem; color:#666; margin-bottom: 20px;">صفحه‌ای مستقل و تمام‌صفحه که روی تبلت/مانیتور آشپزخانه باز می‌گذارید — کارت سفارش‌ها را زنده نشان می‌دهد و باریستا با یک لمس وضعیت را تغییر می‌دهد.</p>

        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-right: 4px solid #e5383b;">
            <h3 style="margin: 0 0 10px 0; color:#e5383b;">🔗 لینک نمایشگر آشپزخانه</h3>
            <p style="font-size:0.85rem; color:#555;">همین لینک را روی تبلت/مانیتور آشپزخانه باز کنید و به صفحه‌ی اصلی (Home Screen) اضافه کنید:</p>
            <input type="text" readonly value="<?php echo esc_url($kds_url); ?>" onclick="this.select();" style="width:100%; max-width:500px; padding:10px; font-size:0.9rem; border-radius:6px; border:1px solid #ccc; direction:ltr; text-align:left;">
            <a href="<?php echo esc_url($kds_url); ?>" target="_blank" class="button button-primary" style="margin-right:10px;">باز کردن 🔗</a>
        </div>

        <form method="post" action="options.php" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
            <?php settings_fields('lcm_kds_settings_group'); ?>
            <h3 style="margin-top:0;">⚙️ تنظیمات دسترسی و زمان‌بندی</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">پین‌کد ورود به نمایشگر آشپزخانه:</th>
                    <td>
                        <input type="text" name="lcm_kds_pin" maxlength="8" value="<?php echo esc_attr( get_option('lcm_kds_pin', '1234') ); ?>" style="width:150px; text-align:center; font-size:1.1rem; letter-spacing:3px;">
                        <p style="font-size:0.75rem; color:#888;">این پین را فقط به کارکنان آشپزخانه بدهید. هرکسی بدون این پین نمی‌تواند وضعیت سفارش‌ها را تغییر دهد.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">چند دقیقه بعد، کارت‌ها قرمز (فوری) شوند؟</th>
                    <td>
                        <input type="number" min="1" name="lcm_kds_timer_minutes" value="<?php echo esc_attr( get_option('lcm_kds_timer_minutes', 7) ); ?>" style="width:80px; text-align:center;"> دقیقه
                    </td>
                </tr>
            </table>
            <?php submit_button('💾 ذخیره تنظیمات'); ?>
        </form>
    </div>
    <?php
}

// 📱 ۴. صفحه‌ی مستقل کدهای QR میزها (قبلاً داخل صفحه‌ی رادار بود)
function lcm_admin_page_table_qrcodes() {
    ?>
    <div class="wrap" style="direction: rtl; text-align: right; font-family: Tahoma, sans-serif; padding-top: 10px;">
        <h1 style="color: #2ec4b6; font-size: 1.6rem; font-weight: bold; margin-bottom: 5px;">📱 کدهای QR میزهای کافه شما</h1>
        <p style="font-size: 0.85rem; color:#666; margin-bottom: 20px;">این کدها را چاپ و روی هر میز بگذارید تا مشتری با اسکن، مستقیم به منوی همان میز برسد.</p>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px;">
                <?php
                $tables = intval(get_option('lcm_table_count', 5));
                for ($i = 1; $i <= $tables; $i++) {
                    $menu_url = site_url('/live-menu/?table_id=' . $i);
                    $qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=' . urlencode($menu_url);
                    ?>
                    <div style="border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px; text-align: center; background: #f8fafc;">
                        <strong style="font-size:0.8rem; display: block; color:#0f172a; margin-bottom:6px;">میز شماره <?php echo $i; ?></strong>
                        <img src="<?php echo esc_url($qr_image_url); ?>" style="width: 110px; height: 110px; border: 1px solid #cbd5e1; border-radius:4px;" alt="QR">
                        <a href="<?php echo esc_url($menu_url); ?>" target="_blank" style="display: block; margin-top: 6px; font-size: 0.7rem; text-decoration: none; color: #0288d1;">لینک تست 🔗</a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

// 🗓️ صفحه‌ی مدیریت رزروهای آنلاین میز
function lcm_admin_page_reservations() {
    global $wpdb;
    $table = $wpdb->prefix . 'lcm_reservations';

    // تغییر وضعیت
    if ( isset($_POST['lcm_res_action'], $_POST['lcm_res_id']) && current_user_can('manage_options') ) {
        $rid    = intval($_POST['lcm_res_id']);
        $action = sanitize_text_field($_POST['lcm_res_action']);
        $nonce_ok = isset($_POST['lcm_res_nonce']) && wp_verify_nonce( sanitize_text_field($_POST['lcm_res_nonce']), 'lcm_res_action_' . $rid );
        if ( $nonce_ok && in_array($action, array('confirmed','cancelled','completed'), true) ) {
            $wpdb->update( $table, array('status' => $action), array('id' => $rid), array('%s'), array('%d') );
        }
    }

    // فیلتر
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    $filter_date   = isset($_GET['filter_date'])   ? sanitize_text_field($_GET['filter_date'])   : '';

    $where = "WHERE 1=1";
    if ( $filter_status !== 'all' ) { $where .= $wpdb->prepare(" AND status = %s", $filter_status); }
    if ( $filter_date )             { $where .= $wpdb->prepare(" AND DATE(reserved_at) = %s", $filter_date); }

    $reservations = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table
        ? $wpdb->get_results("SELECT * FROM $table $where ORDER BY reserved_at ASC LIMIT 200")
        : array();

    $status_labels = array(
        'pending'   => array('label' => 'در انتظار تایید', 'color' => '#ffb703'),
        'confirmed' => array('label' => 'تایید شده',       'color' => '#2ec4b6'),
        'cancelled' => array('label' => 'لغو شده',         'color' => '#e5383b'),
        'completed' => array('label' => 'انجام شد',        'color' => '#888'),
    );
    ?>
    <div class="wrap" style="direction:rtl; text-align:right; font-family:Tahoma,sans-serif; padding-top:10px;">
        <h1 style="color:#2ec4b6; font-size:1.6rem; font-weight:bold; margin-bottom:5px;">🗓️ رزروهای آنلاین میز</h1>

        <!-- فیلترها -->
        <form method="get" style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="page" value="lcm-reservations">
            <select name="filter_status" onchange="this.form.submit()" style="padding:8px; border-radius:8px; border:1px solid #ccc;">
                <option value="all" <?php selected($filter_status,'all'); ?>>همه وضعیت‌ها</option>
                <?php foreach ($status_labels as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php selected($filter_status,$k); ?>><?php echo $v['label']; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>" onchange="this.form.submit()" style="padding:8px; border-radius:8px; border:1px solid #ccc;">
        </form>

        <?php if ( empty($reservations) ): ?>
            <p style="color:#888;">رزروی یافت نشد.</p>
        <?php else: ?>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow-x:auto;">
            <table class="wp-list-table widefat striped" style="min-width:750px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام</th>
                        <th>شماره</th>
                        <th>تاریخ و ساعت رزرو</th>
                        <th>تعداد نفر</th>
                        <th>یادداشت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?php echo intval($r->id); ?></td>
                        <td><?php echo esc_html($r->name); ?></td>
                        <td><code><?php echo esc_html($r->phone); ?></code></td>
                        <td><?php echo esc_html($r->reserved_at); ?></td>
                        <td><?php echo intval($r->guests); ?> نفر</td>
                        <td><?php echo esc_html($r->note ?: '—'); ?></td>
                        <td>
                            <?php $s = $status_labels[$r->status] ?? array('label'=>$r->status,'color'=>'#888'); ?>
                            <span style="background:<?php echo $s['color']; ?>22; color:<?php echo $s['color']; ?>; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;">
                                <?php echo $s['label']; ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" style="display:inline-flex; gap:6px;">
                                <?php wp_nonce_field( 'lcm_res_action_' . $r->id, 'lcm_res_nonce' ); ?>
                                <input type="hidden" name="lcm_res_id" value="<?php echo intval($r->id); ?>">
                                <input type="hidden" name="page" value="lcm-reservations">
                                <?php if ($r->status === 'pending'): ?>
                                    <button name="lcm_res_action" value="confirmed" class="button button-primary" style="font-size:0.7rem;">✅ تایید</button>
                                    <button name="lcm_res_action" value="cancelled" class="button" style="font-size:0.7rem; color:#e5383b;">❌ لغو</button>
                                <?php elseif ($r->status === 'confirmed'): ?>
                                    <button name="lcm_res_action" value="completed" class="button" style="font-size:0.7rem;">☑️ انجام شد</button>
                                    <button name="lcm_res_action" value="cancelled" class="button" style="font-size:0.7rem; color:#e5383b;">❌ لغو</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
