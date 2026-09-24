<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;

final class AuthenticateStaff
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string  $identifier  email or Iranian mobile number
     * @return array{user: User, token: string}
     */
    public function handle(string $identifier, string $password, ?string $deviceName = null): array
    {
        $phone = PhoneNormalizer::tryNormalize($identifier);

        $user = User::query()
            ->when($phone, fn ($q) => $q->where('phone_e164', $phone), fn ($q) => $q->where('email', mb_strtolower(trim($identifier))))
            ->first();

        // Always hash-check (even without a user) so response time doesn't reveal which accounts exist.
        $valid = Hash::check($password, $user !== null ? $user->password : self::dummyHash());

        if (! $user || ! $valid) {
            throw new InvalidCredentialsException;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken(
            mb_substr($deviceName ?: 'dashboard', 0, 60),
            ['staff'],
            now()->addHours((int) config('otp.staff_token_hours')),
        )->plainTextToken;

        $this->audit->record('staff.logged_in', $user, null, $user);

        return ['user' => $user, 'token' => $token];
    }

    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('timing-equaliser');
    }
}
