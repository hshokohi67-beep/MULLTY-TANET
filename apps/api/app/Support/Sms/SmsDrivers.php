<?php

namespace App\Support\Sms;

use App\Support\Sms\Providers\IppanelSmsProvider;
use App\Support\Sms\Providers\KavenegarSmsProvider;
use App\Support\Sms\Providers\MelipayamakSmsProvider;
use App\Support\Sms\Providers\RayganSmsProvider;
use App\Support\Sms\Providers\SmsIrSmsProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

/**
 * The SMS panels a café can connect, with the credential fields each one needs, and a factory
 * that builds the adapter. Field values are plain strings; secrets are marked for masking.
 */
final class SmsDrivers
{
    /** @var array<string, array{label: string, site: string, fields: array<string, array{label: string, secret: bool}>}> */
    public const DRIVERS = [
        'kavenegar' => ['label' => 'کاوه‌نگار', 'site' => 'kavenegar.com', 'fields' => [
            'api_key' => ['label' => 'کلید API', 'secret' => true],
            'sender' => ['label' => 'شماره‌ی خط ارسال', 'secret' => false],
        ]],
        'melipayamak' => ['label' => 'ملی‌پیامک', 'site' => 'melipayamak.com', 'fields' => [
            'username' => ['label' => 'نام کاربری', 'secret' => false],
            'password' => ['label' => 'رمز عبور (یا کلید API)', 'secret' => true],
            'sender' => ['label' => 'شماره‌ی خط ارسال', 'secret' => false],
        ]],
        'smsir' => ['label' => 'اس‌ام‌اس دات آی‌آر (SMS.ir)', 'site' => 'sms.ir', 'fields' => [
            'api_key' => ['label' => 'کلید API', 'secret' => true],
            'sender' => ['label' => 'شماره‌ی خط ارسال', 'secret' => false],
        ]],
        'ippanel' => ['label' => 'فراز اس‌ام‌اس (IPPanel)', 'site' => 'farazsms.com', 'fields' => [
            'api_key' => ['label' => 'کلید API', 'secret' => true],
            'sender' => ['label' => 'شماره‌ی خط ارسال', 'secret' => false],
        ]],
        'raygan' => ['label' => 'رایگان اس‌ام‌اس (ترز)', 'site' => 'raygansms.com', 'fields' => [
            'username' => ['label' => 'نام کاربری', 'secret' => false],
            'password' => ['label' => 'رمز عبور', 'secret' => true],
            'sender' => ['label' => 'شماره‌ی خط ارسال', 'secret' => false],
        ]],
    ];

    /** @param  array<string, string>  $c  credential fields of DRIVERS[$driver] */
    public static function make(string $driver, array $c): SmsProvider
    {
        $http = app(HttpFactory::class);
        $v = fn (string $k) => trim((string) ($c[$k] ?? ''));

        return match ($driver) {
            'kavenegar' => new KavenegarSmsProvider($http, $v('api_key'), $v('sender') ?: null, 'verify'),
            'melipayamak' => new MelipayamakSmsProvider($http, $v('username'), $v('password'), $v('sender')),
            'smsir' => new SmsIrSmsProvider($http, $v('api_key'), $v('sender')),
            'ippanel' => new IppanelSmsProvider($http, $v('api_key'), $v('sender')),
            'raygan' => new RayganSmsProvider($http, $v('username'), $v('password'), $v('sender') ?: null),
            default => throw new InvalidArgumentException("Unknown SMS driver [{$driver}]."),
        };
    }

    /** @return list<array{key: string, label: string, site: string, fields: list<array{key: string, label: string, secret: bool}>}> */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::DRIVERS as $key => $d) {
            $fields = [];
            foreach ($d['fields'] as $f => $spec) {
                $fields[] = ['key' => $f] + $spec;
            }
            $out[] = ['key' => $key, 'label' => $d['label'], 'site' => $d['site'], 'fields' => $fields];
        }

        return $out;
    }
}
