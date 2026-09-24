<?php
/**
 * Template Name: Live Cafe Checkout
 * برگه‌ی تسویه‌حساب و رهگیری سفارش - هم‌رنگ و هم‌برند با منوی زنده کافه
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$lcm_theme     = get_option( 'lcm_menu_theme', 'dark' );
$lcm_cafe_name = get_option( 'lcm_cafe_name', 'کافه لایو منو' );
$lcm_cafe_logo = get_option( 'lcm_cafe_logo', '' );

// متغیرهای رنگی مشترک با صفحه‌ی منو، تا برگه‌ی تسویه‌حساب هم‌برند و یکدست باشد
$lcm_bg        = $lcm_theme === 'dark' ? '#0b090a' : '#fcfbf9';
$lcm_surface   = $lcm_theme === 'dark' ? 'rgba(22, 26, 29, 0.9)' : 'rgba(255, 255, 255, 0.95)';
$lcm_text      = $lcm_theme === 'dark' ? '#f5f3f4' : '#1c1917';
$lcm_border    = $lcm_theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(46,196,182,0.2)';
$lcm_glass     = $lcm_theme === 'dark' ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.03)';
$lcm_accent    = '#2ec4b6';

/* ==========================================================================
   بخش اول: صفحه‌ی رهگیری زنده‌ی سفارش (بعد از ثبت/پرداخت سفارش)
   نکته‌ی امنیتی مهم: قبلاً این صفحه فقط با شماره‌ی سفارش در URL باز می‌شد،
   یعنی هرکسی با حدس‌زدن شماره سفارش می‌توانست سفارش دیگران را ببیند.
   حالا کلید امنیتی سفارش (order key) هم بررسی می‌شود.
========================================================================== */
if ( is_wc_endpoint_url( 'order-received' ) ) {
    global $wp;
    $order_id  = absint( $wp->query_vars['order-received'] );
    $order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
    $order     = $order_id ? wc_get_order( $order_id ) : false;

    $is_authorized = $order && ( $order->get_order_key() === $order_key || current_user_can( 'manage_options' ) );

    get_header(); ?>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .lcm-co-wrap * { box-sizing: border-box; }
        .lcm-co-wrap {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            direction: rtl;
            background: <?php echo esc_html($lcm_bg); ?>;
            color: <?php echo esc_html($lcm_text); ?>;
            min-height: 60vh;
            padding: 30px 15px 60px;
        }
        .lcm-co-card {
            max-width: 480px;
            margin: 0 auto;
            background: <?php echo esc_html($lcm_surface); ?>;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid <?php echo esc_html($lcm_border); ?>;
            border-radius: 26px;
            padding: 28px 22px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        .lcm-co-brand { display:flex; flex-direction:column; align-items:center; gap:8px; margin-bottom: 18px; }
        .lcm-co-brand img { max-height: 46px; border-radius: 10px; }
        .lcm-co-brand span { font-weight: 900; font-size: 1rem; opacity: 0.85; }
        .lcm-co-icon { width: 68px; height: 68px; border-radius: 50%; background: rgba(46,196,182,0.12); display:flex; align-items:center; justify-content:center; margin: 0 auto 12px; font-size: 2rem; }
        .lcm-co-title { font-size: 1.5rem; font-weight: 900; margin: 0; }
        .lcm-co-sub { opacity: 0.6; font-size: 0.85rem; margin-top: 6px; }
        .lcm-co-meta { display:flex; justify-content:space-between; font-size:0.8rem; opacity:0.7; margin-top:14px; border-top:1px solid <?php echo esc_html($lcm_border); ?>; padding-top:12px; }
        .lcm-co-steps { margin-top: 26px; position: relative; text-align:right; }
        .lcm-co-steps::before { content:''; position:absolute; right: 23px; top: 5px; bottom: 5px; width: 2px; background: <?php echo esc_html($lcm_glass); ?>; }
        .lcm-co-step { position:relative; display:flex; align-items:center; gap:14px; padding: 10px 0; opacity: 0.35; transition: opacity .3s; }
        .lcm-co-step.active { opacity: 1; }
        .lcm-co-step-icon { width: 48px; height:48px; min-width:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.3rem; background: <?php echo esc_html($lcm_glass); ?>; position:relative; z-index:2; }
        .lcm-co-step.active .lcm-co-step-icon { background: <?php echo esc_html($lcm_accent); ?>; box-shadow: 0 6px 16px rgba(46,196,182,0.35); }
        .lcm-co-step h4 { margin:0; font-size: 0.95rem; }
        .lcm-co-step p { margin: 2px 0 0; font-size:0.72rem; opacity:0.6; }
        .lcm-co-items { text-align:right; margin-top: 22px; border-top: 1px solid <?php echo esc_html($lcm_border); ?>; padding-top: 14px; }
        .lcm-co-item-row { display:flex; justify-content:space-between; font-size:0.85rem; padding: 6px 0; border-bottom: 1px dashed <?php echo esc_html($lcm_glass); ?>; }
        .lcm-co-total-row { display:flex; justify-content:space-between; font-weight:900; font-size:1rem; margin-top:12px; padding-top:12px; border-top: 1px solid <?php echo esc_html($lcm_border); ?>; }
        .lcm-co-btn { display:block; width:100%; margin-top: 22px; padding: 13px; border-radius: 14px; text-decoration:none; font-weight:900; text-align:center; background: <?php echo esc_html($lcm_accent); ?>; color:#fff; }
        .lcm-co-error { padding: 30px 15px; }
    </style>

    <div class="lcm-co-wrap">
        <?php if ( ! $is_authorized ) : ?>
            <div class="lcm-co-card lcm-co-error">
                <div class="lcm-co-icon">🔒</div>
                <h1 class="lcm-co-title">دسترسی امکان‌پذیر نیست</h1>
                <p class="lcm-co-sub">این لینک معتبر نیست یا متعلق به این سفارش نبود.</p>
                <a href="<?php echo esc_url( home_url('/live-menu/') ); ?>" class="lcm-co-btn">بازگشت به منو</a>
            </div>
        <?php else :
            $status       = $order->get_status();
            $table_meta   = $order->get_meta('_lcm_table_id');
            $location_txt = $table_meta ? ( is_numeric($table_meta) ? 'میز شماره ' . esc_html($table_meta) : esc_html($table_meta) ) : 'سالن';
            $steps_done   = in_array( $status, array('pending','on-hold','processing','completed') );
            $steps_cook   = in_array( $status, array('processing','completed') );
            $steps_ready  = ( $status === 'completed' );
        ?>
        <div class="lcm-co-card" id="lcmOrderCard" data-order-id="<?php echo esc_attr($order_id); ?>" data-order-key="<?php echo esc_attr($order_key); ?>">
            <div class="lcm-co-brand">
                <?php if ( $lcm_cafe_logo ) : ?><img src="<?php echo esc_url($lcm_cafe_logo); ?>" alt="<?php echo esc_attr($lcm_cafe_name); ?>"><?php endif; ?>
                <span><?php echo esc_html($lcm_cafe_name); ?></span>
            </div>

            <div class="lcm-co-icon">🎉</div>
            <h1 class="lcm-co-title">سفارش شما ثبت شد!</h1>
            <p class="lcm-co-sub" id="lcmStatusName"><?php echo esc_html( wc_get_order_status_name($status) ); ?></p>

            <div class="lcm-co-meta">
                <span>شماره فاکتور: #<?php echo esc_html($order_id); ?></span>
                <span>📍 <?php echo $location_txt; ?></span>
            </div>

            <?php if ( $order->get_billing_address_1() ) : ?>
                <div style="text-align:right; font-size:0.78rem; opacity:0.75; margin-top:10px; background: <?php echo esc_html($lcm_glass); ?>; border-radius:12px; padding:10px 12px;">
                    <i data-lucide="truck" style="width:14px; height:14px; vertical-align:-2px;"></i> ارسال به آدرس: <?php echo esc_html( $order->get_billing_city() . ' - ' . $order->get_billing_address_1() ); ?>
                </div>
            <?php endif; ?>

            <?php $lcm_kitchen_note = $order->get_meta('_lcm_kitchen_note'); if ( $lcm_kitchen_note ) : ?>
                <div style="text-align:right; font-size:0.78rem; color:#ffb703; margin-top:10px; background: rgba(255,183,3,0.1); border: 1px dashed #ffb703; border-radius:12px; padding:10px 12px;">
                    <i data-lucide="sticky-note" style="width:14px; height:14px; vertical-align:-2px;"></i> یادداشت شما: <?php echo esc_html( $lcm_kitchen_note ); ?>
                </div>
            <?php endif; ?>

            <?php $lcm_requested_time = $order->get_meta('_lcm_requested_time'); if ( $lcm_requested_time ) : ?>
                <div style="text-align:right; font-size:0.85rem; color:#a78bfa; font-weight:800; margin-top:10px; background: rgba(167,139,250,0.1); border: 1px dashed #a78bfa; border-radius:12px; padding:10px 12px;">
                    <i data-lucide="clock" style="width:14px; height:14px; vertical-align:-2px;"></i> پیش‌سفارش شما برای ساعت <?php echo esc_html( $lcm_requested_time ); ?> ثبت شد
                </div>
            <?php endif; ?>

            <div class="lcm-co-steps" id="lcmSteps">
                <div class="lcm-co-step <?php echo $steps_done ? 'active' : ''; ?>" data-step="received">
                    <div class="lcm-co-step-icon">📝</div>
                    <div><h4>سفارش دریافت شد</h4><p>منتظر تایید آشپزخانه</p></div>
                </div>
                <div class="lcm-co-step <?php echo $steps_cook ? 'active' : ''; ?>" data-step="cooking">
                    <div class="lcm-co-step-icon">🍳</div>
                    <div><h4>در حال آماده‌سازی</h4><p>باریستا در حال تهیه سفارش شماست</p></div>
                </div>
                <div class="lcm-co-step <?php echo $steps_ready ? 'active' : ''; ?>" data-step="ready">
                    <div class="lcm-co-step-icon">🛎️</div>
                    <div><h4>آماده تحویل!</h4><p>نوش جان! سفارش شما آماده است.</p></div>
                </div>
            </div>

            <div class="lcm-co-items">
                <?php foreach ( $order->get_items() as $item ) : ?>
                    <div class="lcm-co-item-row">
                        <span><?php echo esc_html( $item->get_name() ); ?></span>
                        <span><?php echo number_format( $item->get_total() ); ?> ت</span>
                    </div>
                <?php endforeach; ?>
                <div class="lcm-co-total-row">
                    <span>جمع کل</span>
                    <span><?php echo number_format( $order->get_total() ); ?> ت</span>
                </div>

                <?php
                // ریز پرداخت: چقدر از کیف پول کسر شد و چقدر باقی مانده (و از چه طریقی تسویه می‌شود)
                $wallet_deducted   = floatval( $order->get_meta('_lcm_wallet_deducted') );
                $remaining_amount  = floatval( $order->get_meta('_lcm_remaining_amount') );
                $remaining_method  = $order->get_meta('_lcm_remaining_payment_method');
                if ( $wallet_deducted > 0 ) : ?>
                    <div class="lcm-co-item-row" style="color:#43e97b; font-weight:700;">
                        <span>💳 کسر از کیف پول</span>
                        <span>- <?php echo number_format( $wallet_deducted ); ?> ت</span>
                    </div>
                    <?php if ( $remaining_amount > 0 ) :
                        $remaining_label = ( $remaining_method === 'online' ) ? '🏦 پرداخت آنلاین' : '🪑 نقدی در سالن';
                    ?>
                        <div class="lcm-co-item-row" style="color:#ffb703; font-weight:700;">
                            <span>مانده (<?php echo $remaining_label; ?>)</span>
                            <span><?php echo number_format( $remaining_amount ); ?> ت</span>
                        </div>
                    <?php else: ?>
                        <div class="lcm-co-item-row" style="color:#43e97b; font-weight:700;">
                            <span>✅ کاملاً از کیف پول تسویه شد</span>
                            <span>۰ ت</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ( function_exists('lcm_push_is_supported') && lcm_push_is_supported() ) : ?>
                <button id="lcmEnablePushBtn" class="lcm-co-btn" style="background: #ffb703; margin-top:10px;" onclick="lcmEnablePushNotifications()">🔔 وقتی آماده شد بهم خبر بده</button>
            <?php endif; ?>

            <a href="<?php echo esc_url( home_url('/live-menu/') ); ?>" class="lcm-co-btn">بازگشت به منو</a>
        </div>

        <?php endif; ?>
    </div>

    <?php if ( $is_authorized ) : ?>
    <script>
        try { if (typeof lucide !== 'undefined') { lucide.createIcons(); } } catch(e) {}

        <?php $lcm_vapid_keys_for_js = function_exists('lcm_get_vapid_keys') ? lcm_get_vapid_keys() : null; ?>
        const LCM_VAPID_PUBLIC_KEY = "<?php echo $lcm_vapid_keys_for_js ? esc_js($lcm_vapid_keys_for_js['public_raw_b64']) : ''; ?>";
        const LCM_CUSTOMER_PHONE = "<?php echo esc_js( $order->get_billing_phone() ); ?>";

        function lcmUrlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
        }

        function lcmEnablePushNotifications() {
            const btn = document.getElementById('lcmEnablePushBtn');
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('متاسفانه مرورگر شما از اعلان پشتیبانی نمی‌کند.');
                return;
            }
            if (!LCM_VAPID_PUBLIC_KEY || !LCM_CUSTOMER_PHONE) {
                alert('این قابلیت فعلاً در دسترس نیست.');
                return;
            }

            if (btn) { btn.innerText = '⏳ در حال فعال‌سازی...'; btn.disabled = true; }

            navigator.serviceWorker.register('/lcm-push-sw.js').then(function(registration) {
                return Notification.requestPermission().then(function(permission) {
                    if (permission !== 'granted') { throw new Error('اجازه داده نشد'); }
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: lcmUrlBase64ToUint8Array(LCM_VAPID_PUBLIC_KEY)
                    });
                });
            }).then(function(subscription) {
                const subJson = subscription.toJSON();
                let formData = new FormData();
                formData.append('action', 'lcm_save_push_subscription');
                formData.append('phone', LCM_CUSTOMER_PHONE);
                formData.append('endpoint', subJson.endpoint);
                formData.append('p256dh', subJson.keys.p256dh);
                formData.append('auth', subJson.keys.auth);

                return fetch("<?php echo esc_url( admin_url('admin-ajax.php') ); ?>", { method: 'POST', body: formData });
            }).then(() => {
                if (btn) { btn.innerText = '✅ اعلان فعال شد'; }
            }).catch(function(err) {
                console.error('lcm push error:', err);
                if (btn) { btn.innerText = '🔔 وقتی آماده شد بهم خبر بده'; btn.disabled = false; }
                let reason = err && err.message ? err.message : 'خطای نامشخص';
                if (reason.includes('اجازه داده نشد') || (typeof Notification !== 'undefined' && Notification.permission === 'denied')) {
                    alert('اجازه‌ی نوتیفیکیشن رد شد. از تنظیمات مرورگر (نه حالت Incognito) دوباره اجازه بدهید.');
                } else {
                    alert('فعال‌سازی اعلان ممکن نشد. جزئیات: ' + reason + '\n\nنکته: در حالت Incognito/ناشناس، نوتیفیکیشن معمولاً کار نمی‌کند — از یک پنجره‌ی عادی امتحان کنید.');
                }
            });
        }

        (function(){
            var card = document.getElementById('lcmOrderCard');
            if(!card) return;
            var orderId = card.getAttribute('data-order-id');
            var orderKey = card.getAttribute('data-order-key');
            var ajaxUrl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";

            function applyStatus(status, statusName){
                var doneSteps = ['pending','on-hold','processing','completed'].indexOf(status) > -1;
                var cookSteps = ['processing','completed'].indexOf(status) > -1;
                var readySteps = (status === 'completed');

                var stepsEl = document.getElementById('lcmSteps');
                if(stepsEl){
                    stepsEl.querySelector('[data-step="received"]').classList.toggle('active', doneSteps);
                    stepsEl.querySelector('[data-step="cooking"]').classList.toggle('active', cookSteps);
                    stepsEl.querySelector('[data-step="ready"]').classList.toggle('active', readySteps);
                }
                var nameEl = document.getElementById('lcmStatusName');
                if(nameEl) nameEl.innerText = statusName;
            }

            function poll(){
                var body = new URLSearchParams();
                body.append('action', 'lcm_get_order_status');
                body.append('order_id', orderId);
                body.append('order_key', orderKey);

                fetch(ajaxUrl, { method: 'POST', body: body })
                    .then(function(res){ return res.json(); })
                    .then(function(res){
                        if(res && res.success && res.data){
                            applyStatus(res.data.status, res.data.status_name);
                        }
                    })
                    .catch(function(){ /* شبکه موقتاً در دسترس نیست، در تلاش بعدی دوباره امتحان می‌شود */ });
            }

            let lcmStatusTimer = setInterval(poll, 8000);
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    clearInterval(lcmStatusTimer);
                } else {
                    poll();
                    lcmStatusTimer = setInterval(poll, 8000);
                }
            });
        })();
    </script>
    <?php endif; ?>

    <?php get_footer();
    exit;
}

/* ==========================================================================
   بخش دوم: خود برگه‌ی تسویه‌حساب (فرم پرداخت ووکامرس) هم‌برند با منو
   شامل ریز فاکتور زنده‌ی سفارش کنار فرم، به‌جای ترفند قدیمیِ مخفی‌کردن
   فیلدها با CSS و پر کردن اجباری آن‌ها با جاوااسکریپت.
========================================================================== */
$lcm_summary = function_exists('lcm_get_checkout_cart_summary') ? lcm_get_checkout_cart_summary() : array('items' => array(), 'total' => 0, 'source' => 'none');

get_header(); ?>

<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <script src="https://unpkg.com/lucide@latest"></script>
<style>
    .lcm-checkout-page * { box-sizing: border-box; }
    .lcm-checkout-page {
        font-family: 'Vazirmatn', Tahoma, sans-serif;
        direction: rtl;
        background: <?php echo esc_html($lcm_bg); ?>;
        color: <?php echo esc_html($lcm_text); ?>;
        padding: 25px 15px 60px;
    }
    .lcm-checkout-header {
        max-width: 900px; margin: 0 auto 20px; text-align:center;
        background: <?php echo esc_html($lcm_accent); ?>; color:#fff;
        padding: 16px; border-radius: 18px; box-shadow: 0 10px 25px rgba(46,196,182,0.25);
        display:flex; align-items:center; justify-content:center; gap:10px;
    }
    .lcm-checkout-header img { max-height: 32px; border-radius:6px; }
    .lcm-checkout-header h2 { margin:0; font-size:1.1rem; font-weight:900; }

    .lcm-checkout-layout { max-width: 900px; margin: 0 auto; display:flex; gap:20px; flex-wrap: wrap-reverse; align-items:flex-start; }
    .lcm-checkout-form-box, .lcm-checkout-summary-box {
        background: <?php echo esc_html($lcm_surface); ?>;
        border: 1px solid <?php echo esc_html($lcm_border); ?>;
        border-radius: 20px; padding: 20px;
    }
    .lcm-checkout-form-box { flex: 2; min-width: 280px; }
    .lcm-checkout-summary-box { flex: 1; min-width: 250px; }
    .lcm-checkout-summary-box h3 { margin-top:0; font-size:1rem; }
    .lcm-summary-row { display:flex; justify-content:space-between; font-size:0.85rem; padding:6px 0; border-bottom: 1px dashed <?php echo esc_html($lcm_glass); ?>; }
    .lcm-summary-total { display:flex; justify-content:space-between; font-weight:900; font-size:1rem; margin-top:10px; padding-top:10px; border-top:1px solid <?php echo esc_html($lcm_border); ?>; }
    .lcm-summary-empty { text-align:center; opacity:0.6; font-size:0.85rem; padding: 15px 5px; }

    /* استایل‌دهی فرم استاندارد ووکامرس هم‌رنگ با برند، بدون دستکاری اجباری فیلدها */
    .lcm-checkout-form-box .form-row input.input-text,
    .lcm-checkout-form-box .form-row select,
    .lcm-checkout-form-box .form-row textarea {
        width: 100%; padding: 10px 12px; border-radius: 10px;
        border: 1px solid <?php echo esc_html($lcm_border); ?>;
        background: <?php echo esc_html($lcm_glass); ?>;
        color: <?php echo esc_html($lcm_text); ?>;
    }
    .lcm-checkout-form-box label { font-size: 0.85rem; opacity: 0.85; }
    .lcm-checkout-form-box #place_order {
        width: 100%; padding: 14px; border-radius: 14px; border:none;
        background: <?php echo esc_html($lcm_accent); ?>; color:#fff; font-weight:900; font-size:1rem; cursor:pointer;
    }
    .lcm-checkout-form-box #place_order:disabled { opacity: 0.6; cursor: not-allowed; }
    /* فیلدهایی که برای یک سفارش کافه‌ای معمولاً لازم نیستند (اختیاری شده‌اند، نه فقط مخفی) */
    #billing_company_field, #billing_country_field { display: none; }
</style>

<div class="lcm-checkout-page">
    <div class="lcm-checkout-header">
        <?php if ( $lcm_cafe_logo ) : ?><img src="<?php echo esc_url($lcm_cafe_logo); ?>" alt=""><?php endif; ?>
        <h2>تکمیل و پرداخت سفارش — <?php echo esc_html($lcm_cafe_name); ?></h2>
    </div>

    <div class="lcm-checkout-layout">
        <div class="lcm-checkout-form-box">
            <?php
            if ( WC()->cart && WC()->cart->is_empty() && empty($lcm_summary['items']) ) {
                echo '<p style="text-align:center; padding: 30px 10px;">سبد خرید شما خالی است. <a href="' . esc_url( home_url('/live-menu/') ) . '">بازگشت به منو</a></p>';
            } else {
                echo do_shortcode('[woocommerce_checkout]');
            }
            ?>
        </div>

        <div class="lcm-checkout-summary-box">
            <h3>🧾 ریز فاکتور سفارش</h3>
            <?php if ( empty($lcm_summary['items']) ) : ?>
                <div class="lcm-summary-empty">آیتمی برای نمایش یافت نشد.</div>
            <?php else : ?>
                <?php foreach ($lcm_summary['items'] as $line) : ?>
                    <div class="lcm-summary-row">
                        <span><?php echo esc_html($line['title']); ?></span>
                        <span><?php echo number_format($line['price']); ?> ت</span>
                    </div>
                <?php endforeach; ?>
                <div class="lcm-summary-total">
                    <span>جمع کل</span>
                    <span><?php echo number_format($lcm_summary['total']); ?> ت</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // فقط وضعیت لودینگ دکمه‌ی ثبت سفارش هنگام ارسال فرم؛ هیچ فیلدی به‌زور پر نمی‌شود
    jQuery(document.body).on('checkout_place_order', function(){
        var btn = document.getElementById('place_order');
        if(btn){ btn.innerText = '⏳ در حال ثبت سفارش...'; }
        return true;
    });
</script>

<?php get_footer(); ?>
