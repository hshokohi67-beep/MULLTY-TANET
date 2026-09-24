<?php
/**
 * موتور اعلان واقعی مرورگر (Web Push) — بدون هیچ سرویس واسط بیرونی.
 *
 * ⚠️ نکته‌ی مهم و صادقانه: این بخش رمزنگاری واقعی پروتکل Web Push (طبق
 * RFC 8291 و RFC 8292) را مستقیماً در PHP پیاده‌سازی می‌کند، چون امکان
 * نصب کتابخانه‌ی خارجی (Composer) در یک پلاگین تک‌فایلی معمولاً وجود ندارد.
 * این کد بر اساس مستندات رسمی نوشته شده ولی روی یک سرویس Push واقعی
 * (مرورگر واقعی + HTTPS واقعی) توسط من تست نشده — لطفاً حتماً روی
 * نسخه‌ی واقعی سایت امتحانش کنید و اگر پیامی نرسید، خبر بدید تا دیباگ کنیم.
 *
 * ⚠️ نیازمند PHP نسخه‌ی ۸٫۱ یا بالاتر است (به‌خاطر تابع openssl_pkey_derive).
 * روی نسخه‌های قدیمی‌تر PHP، این قابلیت خودکار غیرفعال می‌ماند.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function lcm_push_is_supported() {
    return function_exists( 'openssl_pkey_derive' ) && function_exists( 'openssl_pkey_new' );
}

function lcm_base64url_encode( $data ) {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function lcm_base64url_decode( $data ) {
    $data = strtr( $data, '-_', '+/' );
    $padded = str_pad( $data, strlen( $data ) % 4 === 0 ? strlen( $data ) : strlen( $data ) + ( 4 - strlen( $data ) % 4 ), '=' );
    return base64_decode( $padded );
}

/**
 * تولید (یا خواندن) جفت‌کلید VAPID که هویت سرور شما را برای سرویس‌های
 * Push (گوگل/موزیلا/...) تایید می‌کند. فقط یک‌بار ساخته و ذخیره می‌شود.
 */
function lcm_get_vapid_keys() {
    $keys = get_option( 'lcm_vapid_keys' );
    if ( is_array( $keys ) && ! empty( $keys['private_pem'] ) && ! empty( $keys['public_raw_b64'] ) ) {
        return $keys;
    }
    if ( ! lcm_push_is_supported() ) { return null; }

    $res = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
    if ( ! $res ) { return null; }

    openssl_pkey_export( $res, $private_pem );
    $details = openssl_pkey_get_details( $res );
    if ( empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) ) { return null; }

    $public_raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];

    $keys = array(
        'private_pem'    => $private_pem,
        'public_raw_b64' => lcm_base64url_encode( $public_raw ),
    );
    update_option( 'lcm_vapid_keys', $keys, false );
    return $keys;
}

/**
 * تبدیل یک نقطه‌ی خام EC (۶۵ بایتی، فرمت فشرده‌نشده) به یک کلید عمومی PEM
 * قابل استفاده در openssl_pkey_get_public / openssl_pkey_derive.
 */
function lcm_raw_ec_point_to_pem( $raw_point ) {
    // پیشوند ثابت DER برای SubjectPublicKeyInfo منحنی P-256 (prime256v1)
    $der_prefix = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' );
    $der = $der_prefix . $raw_point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
}

/**
 * تبدیل امضای ECDSA از فرمت DER (خروجی پیش‌فرض openssl_sign) به فرمت خام
 * r||s با طول ثابت که استاندارد JWT (RFC 8292) آن را می‌خواهد.
 */
function lcm_der_to_raw_ecdsa( $der, $part_len = 32 ) {
    if ( strlen( $der ) < 8 || ord( $der[0] ) !== 0x30 ) { return false; }
    $offset = 2;
    if ( ord( $der[ $offset ] ) !== 0x02 ) { return false; }
    $offset++;
    $r_len = ord( $der[ $offset ] ); $offset++;
    $r = substr( $der, $offset, $r_len ); $offset += $r_len;
    if ( ord( $der[ $offset ] ) !== 0x02 ) { return false; }
    $offset++;
    $s_len = ord( $der[ $offset ] ); $offset++;
    $s = substr( $der, $offset, $s_len );

    $r = ltrim( $r, "\x00" ); $s = ltrim( $s, "\x00" );
    $r = str_pad( $r, $part_len, "\x00", STR_PAD_LEFT );
    $s = str_pad( $s, $part_len, "\x00", STR_PAD_LEFT );
    return $r . $s;
}

/**
 * ساخت JWT امضاشده‌ی VAPID برای هدر Authorization درخواست Push (RFC 8292)
 */
function lcm_build_vapid_jwt( $audience, $private_pem ) {
    $header  = lcm_base64url_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
    $payload = lcm_base64url_encode( wp_json_encode( array(
        'aud' => $audience,
        'exp' => time() + 12 * HOUR_IN_SECONDS,
        'sub' => 'mailto:admin@' . parse_url( home_url(), PHP_URL_HOST ),
    ) ) );
    $signing_input = $header . '.' . $payload;

    openssl_sign( $signing_input, $der_signature, $private_pem, OPENSSL_ALGO_SHA256 );
    $raw_signature = lcm_der_to_raw_ecdsa( $der_signature, 32 );
    if ( ! $raw_signature ) { return false; }

    return $signing_input . '.' . lcm_base64url_encode( $raw_signature );
}

/**
 * رمزنگاری پیام طبق RFC 8291 (aes128gcm) و ارسال واقعی به سرویس Push مرورگر مشترک.
 * $subscription باید شامل endpoint, p256dh, auth باشد.
 */
function lcm_send_web_push( $subscription, $payload_array ) {
    if ( ! lcm_push_is_supported() ) { return false; }

    $vapid = lcm_get_vapid_keys();
    if ( ! $vapid ) { return false; }

    $endpoint = $subscription['endpoint'];
    $ua_public_raw = lcm_base64url_decode( $subscription['p256dh'] );
    $auth_secret   = lcm_base64url_decode( $subscription['auth'] );
    if ( strlen( $ua_public_raw ) !== 65 || strlen( $auth_secret ) < 16 ) { return false; }

    // کلید موقت (Ephemeral) مخصوص همین یک پیام
    $eph = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
    if ( ! $eph ) { return false; }
    $eph_details = openssl_pkey_get_details( $eph );
    $as_public_raw = "\x04" . $eph_details['ec']['x'] . $eph_details['ec']['y'];

    $ua_pub_key = openssl_pkey_get_public( lcm_raw_ec_point_to_pem( $ua_public_raw ) );
    if ( ! $ua_pub_key ) { return false; }

    $ecdh_secret = openssl_pkey_derive( $ua_pub_key, $eph, 32 );
    if ( ! $ecdh_secret ) { return false; }

    $salt = random_bytes( 16 );

    // مرحله‌ی ۱: ترکیب راز ECDH با auth_secret (بخش ۳٫۳ سند RFC 8291)
    $prk_key = hash_hmac( 'sha256', $ecdh_secret, $auth_secret, true );
    $key_info = "WebPush: info\x00" . $ua_public_raw . $as_public_raw;
    $ikm = hash_hmac( 'sha256', $key_info . "\x01", $prk_key, true );

    // مرحله‌ی ۲: استخراج کلید نهایی رمزنگاری از IKM با نمک تصادفی
    $prk = hash_hmac( 'sha256', $ikm, $salt, true );
    $cek   = substr( hash_hmac( 'sha256', "Content-Encoding: aes128gcm\x00" . "\x01", $prk, true ), 0, 16 );
    $nonce = substr( hash_hmac( 'sha256', "Content-Encoding: nonce\x00" . "\x01", $prk, true ), 0, 12 );

    $plaintext = wp_json_encode( $payload_array ) . "\x02"; // بایت پایانی رکورد (بدون padding اضافه)
    $ciphertext = openssl_encrypt( $plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag );
    if ( false === $ciphertext ) { return false; }

    $body = $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_public_raw . $ciphertext . $tag;

    $audience = wp_parse_url( $endpoint, PHP_URL_SCHEME ) . '://' . wp_parse_url( $endpoint, PHP_URL_HOST );
    $jwt = lcm_build_vapid_jwt( $audience, $vapid['private_pem'] );
    if ( ! $jwt ) { return false; }

    $response = wp_remote_post( $endpoint, array(
        'headers' => array(
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'TTL'              => '86400',
            'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid['public_raw_b64'],
        ),
        'body'    => $body,
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) { return false; }
    $code = wp_remote_retrieve_response_code( $response );

    // کد ۴۰۴/۴۱۰ یعنی این اشتراک دیگر معتبر نیست (کاربر نوتیفیکیشن را در مرورگرش غیرفعال کرده)
    if ( in_array( $code, array( 404, 410 ), true ) ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'lcm_push_subscriptions', array( 'endpoint' => $endpoint ), array( '%s' ) );
    }

    if ( $code < 200 || $code >= 300 ) {
        error_log( 'LCM Push Send Failed: HTTP ' . $code . ' — ' . wp_remote_retrieve_body( $response ) );
    }

    return $code >= 200 && $code < 300;
}

/**
 * ارسال یک اعلان دلخواه به تمام دستگاه‌های ثبت‌شده‌ی یک شماره تلفن.
 * پایه‌ی مشترک برای «سفارش آماده شد»، «تبریک تولد»، و هر اعلان بعدی.
 */
function lcm_send_push_to_phone( $phone, $title, $body, $extra_data = array() ) {
    if ( empty( $phone ) || ! lcm_push_is_supported() ) { return 0; }

    global $wpdb;
    $table = $wpdb->prefix . 'lcm_push_subscriptions';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) { return 0; }

    $subs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE phone = %s", $phone ), ARRAY_A );
    if ( empty( $subs ) ) { return 0; }

    $payload = array_merge( array( 'title' => $title, 'body' => $body ), $extra_data );

    $success_count = 0;
    foreach ( $subs as $sub ) {
        if ( lcm_send_web_push( $sub, $payload ) ) { $success_count++; }
    }
    return $success_count;
}

/**
 * فرستادن اعلان «سفارش آماده شد» به تمام دستگاه‌های ثبت‌شده‌ی این شماره تلفن
 */
function lcm_notify_order_ready( $phone, $order_id ) {
    $cafe_name = get_option( 'lcm_cafe_name', 'کافه شما' );
    lcm_send_push_to_phone( $phone, $cafe_name, '☕ سفارش شما آماده‌ی تحویله! نوش جان 🎉', array( 'order_id' => $order_id ) );
}
