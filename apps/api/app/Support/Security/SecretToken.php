<?php

namespace App\Support\Security;

/**
 * Unguessable bearer tokens (QR codes, table sessions, guest carts, order tracking).
 * The raw value is shown once and never stored. The database keeps only its SHA-256,
 * so a leaked database cannot be turned back into working QR codes or cart links.
 */
final class SecretToken
{
    /** 32 random bytes, URL-safe base64 (43 chars). */
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Cheap shape check before touching the database. */
    public static function looksValid(?string $token): bool
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }
}
