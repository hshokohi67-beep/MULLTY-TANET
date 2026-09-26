<?php

namespace Tests\Unit\Push;

use App\Support\Push\WebPushCrypto;
use App\Support\Push\WebPushSender;
use PHPUnit\Framework\TestCase;

/** RFC 8291 / 8292 checked from the other side: we decrypt as the browser would and verify the JWT. */
final class WebPushCryptoTest extends TestCase
{
    public function test_a_browser_can_decrypt_what_we_send(): void
    {
        ['private' => $browserKey, 'public' => $browserPublic] = WebPushCrypto::newKeyPair();
        $auth = random_bytes(16);
        $payload = '{"title":"سفارش #۱۲ آماده است","url":"/track"}';

        $body = WebPushCrypto::encrypt($payload, $browserPublic, $auth);

        // The browser's side of RFC 8291.
        $salt = substr($body, 0, 16);
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $this->assertSame(65, ord($body[20]));
        $serverPublic = substr($body, 21, 65);
        $cipher = substr($body, 86, -16);
        $tag = substr($body, -16);

        $ecdh = WebPushCrypto::sharedSecret($browserKey, $serverPublic);
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$browserPublic.$serverPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        $this->assertSame($payload."\x02", $plain);
        // A different auth secret can't read it.
        $wrong = hash_hkdf('sha256', hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$browserPublic.$serverPublic, random_bytes(16)), 16, "Content-Encoding: aes128gcm\0", $salt);
        $this->assertFalse(openssl_decrypt($cipher, 'aes-128-gcm', $wrong, OPENSSL_RAW_DATA, $nonce, $tag));
    }

    public function test_the_vapid_jwt_verifies_with_our_public_key(): void
    {
        ['private' => $key, 'public' => $public] = WebPushCrypto::newKeyPair();
        $pem = WebPushCrypto::exportPrivate($key);

        $header = WebPushCrypto::vapidHeader('https://fcm.googleapis.com', 'mailto:ops@cafeyar.ir', $pem, $public);
        $this->assertMatchesRegularExpression('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=([\w-]+)$/', $header);
        preg_match('/^vapid t=([\w-]+\.[\w-]+)\.([\w-]+), k=([\w-]+)$/', $header, $m);

        $claims = json_decode(WebPushCrypto::unb64(explode('.', $m[1])[1]), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertSame($public, WebPushCrypto::unb64($m[3]));

        // Rebuild DER from r||s and verify with the raw public point.
        $raw = WebPushCrypto::unb64($m[2]);
        $this->assertSame(64, strlen($raw));
        $int = fn (string $x) => (ord(ltrim($x, "\0")[0]) & 0x80 ? "\0" : '').ltrim($x, "\0");
        [$r, $s] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $this->assertSame(1, openssl_verify($m[1], $der, WebPushCrypto::publicPem($public), OPENSSL_ALGO_SHA256));
    }

    public function test_only_known_push_services_are_contacted(): void
    {
        foreach (['https://fcm.googleapis.com/fcm/send/abc', 'https://updates.push.services.mozilla.com/wpush/v2/x', 'https://web.push.apple.com/Q', 'https://db5p.notify.windows.com/w/?token=1'] as $ok) {
            $this->assertTrue(WebPushSender::allowedEndpoint($ok), $ok);
        }
        foreach (['http://fcm.googleapis.com/x', 'https://evil.com/fcm.googleapis.com', 'https://fcm.googleapis.com.evil.com/x', 'https://127.0.0.1/x', 'https://user@fcm.googleapis.com/x', 'https://fcm.googleapis.com:8443/x', 'https://apple.com.push.apple.com.evil/x'] as $bad) {
            $this->assertFalse(WebPushSender::allowedEndpoint($bad), $bad);
        }
    }
}
