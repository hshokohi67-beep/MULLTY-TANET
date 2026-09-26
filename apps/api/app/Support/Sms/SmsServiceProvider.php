<?php

namespace App\Support\Sms;

use App\Support\Sms\Providers\ArraySmsProvider;
use App\Support\Sms\Providers\KavenegarSmsProvider;
use App\Support\Sms\Providers\LogSmsProvider;
use App\Support\Sms\Providers\RayganSmsProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LogicException;

final class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Café-to-customer messages: nothing unless the Messaging module binds its implementation.
        $this->app->bindIf(CafeMessenger::class, NullCafeMessenger::class);

        $this->app->singleton(SmsProvider::class, function ($app): SmsProvider {
            $driver = (string) config('sms.default');

            if (in_array($driver, ['log', 'array'], true) && ! $app->environment(['local', 'testing'])) {
                throw new LogicException(sprintf('SMS driver [%s] is not allowed in the [%s] environment.', $driver, $app->environment()));
            }

            return match ($driver) {
                'log' => new LogSmsProvider,
                'array' => new ArraySmsProvider,
                'raygan' => new RayganSmsProvider(
                    $app->make(HttpFactory::class),
                    (string) config('sms.providers.raygan.username') ?: throw new LogicException('RAYGAN_USERNAME is not configured.'),
                    (string) config('sms.providers.raygan.password'),
                    config('sms.providers.raygan.sender'),
                    (string) config('sms.providers.raygan.code_template'),
                ),
                'kavenegar' => new KavenegarSmsProvider(
                    $app->make(HttpFactory::class),
                    (string) config('sms.providers.kavenegar.api_key') ?: throw new LogicException('KAVENEGAR_API_KEY is not configured.'),
                    config('sms.providers.kavenegar.sender'),
                    (string) config('sms.providers.kavenegar.verify_template'),
                    (string) config('sms.providers.kavenegar.base_url'),
                    (int) config('sms.providers.kavenegar.timeout'),
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown SMS driver [%s].', $driver)),
            };
        });
    }
}
