<?php

namespace App\Support\Push;

use RuntimeException;

/**
 * The cryptography of the Web Push standard, with ext-openssl only:
 *  - RFC 8291 message encryption ("aes128gcm": ephemeral P-256 ECDH, HKDF-SHA-256, AES-128-GCM);
 *  - RFC 8292 VAPID (an ES256 JWT that proves the push comes from this platform).
 * Keys travel as base64url; P-256 public keys are 65-byte uncompressed points (0x04 || X || Y).
 */
final class WebPushCrypto
{
    /** DER prefix of a SubjectPublicKeyInfo for an uncompressed P-256 point. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Record size advertised in the aes128gcm header (one record carries the whole payload). */
    private const RECORD_SIZE = 4096;

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url.');
        }

        return $decoded;
    }

    /** PEM public key for a raw uncompressed P-256 point. */
    public static function publicPem(string $point): string
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new RuntimeException('Not an uncompressed P-256 point.');
        }

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode((string) hex2bin(self::SPKI_PREFIX).$point), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    /** @return array{private: \OpenSSLAsymmetricKey, public: string} a new P-256 key pair (public as a raw point) */
    public static function newKeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + self::opensslConfig());
        if ($key === false) {
            throw new RuntimeException('Could not create an EC key.');
        }

        return ['private' => $key, 'public' => self::pointOf($key)];
    }

    /** The private key as PEM (for WEBPUSH_PRIVATE_KEY). */
    public static function exportPrivate(\OpenSSLAsymmetricKey $key): string
    {
        if (! openssl_pkey_export($key, $pem, null, self::opensslConfig())) {
            throw new RuntimeException('Could not export the key.');
        }

        return $pem;
    }

    /**
     * Windows PHP builds ship without a default openssl.cnf, which key generation needs: use the
     * bundled one when OPENSSL_CONF isn't set. Elsewhere the system default applies.
     *
     * @return array<string, string>
     */
    private static function opensslConfig(): array
    {
        $bundled = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        return getenv('OPENSSL_CONF') === false && PHP_OS_FAMILY === 'Windows' && is_file($bundled) ? ['config' => $bundled] : [];
    }

    public static function pointOf(\OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Not an EC key.');
        }

        return "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** ECDH shared secret between our private key and a raw peer point. */
    public static function sharedSecret(\OpenSSLAsymmetricKey $private, string $peerPoint): string
    {
        $peer = openssl_pkey_get_public(self::publicPem($peerPoint));
        $secret = $peer === false ? false : openssl_pkey_derive($peer, $private, 32);
        if ($secret === false) {
            throw new RuntimeException('ECDH failed.');
        }

        return $secret;
    }

    /**
     * Encrypts a payload for one subscription (RFC 8291). Returns the request body:
     * salt(16) || record size(4) || key id length(1) || our ephemeral public key(65) || ciphertext+tag.
     */
    public static function encrypt(string $payload, string $userAgentPublic, string $authSecret): string
    {
        if (strlen($authSecret) !== 16) {
            throw new RuntimeException('The auth secret must be 16 bytes.');
        }
        ['private' => $ephemeral, 'public' => $serverPublic] = self::newKeyPair();
        $salt = random_bytes(16);

        $ecdh = self::sharedSecret($ephemeral, $userAgentPublic);
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$userAgentPublic.$serverPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 marks the last (and only) record; no extra padding.
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return $salt.pack('N', self::RECORD_SIZE).chr(65).$serverPublic.$cipher.$tag;
    }

    /** The "Authorization: vapid t=…, k=…" value for a push service origin. */
    public static function vapidHeader(string $audience, string $subject, string $privatePem, string $publicPoint, int $ttlSeconds = 43200): string
    {
        $input = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.self::b64((string) json_encode(['aud' => $audience, 'exp' => time() + $ttlSeconds, 'sub' => $subject], JSON_UNESCAPED_SLASHES));

        if (! openssl_sign($input, $der, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID signing failed.');
        }

        return 'vapid t='.$input.'.'.self::b64(self::derToRaw($der)).', k='.self::b64($publicPoint);
    }

    /** An ECDSA DER signature (SEQUENCE of two INTEGERs) as the 64-byte r||s that JWT wants. */
    public static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if (($der[$offset] ?? '') !== "\x02") {
                throw new RuntimeException('Malformed signature.');
            }
            $length = ord($der[$offset + 1]);
            $int = ltrim(substr($der, $offset + 2, $length), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $out;
    }
}
