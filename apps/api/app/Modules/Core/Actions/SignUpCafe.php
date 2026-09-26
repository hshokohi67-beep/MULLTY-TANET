<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Data\CreateTenantData;
use App\Modules\Core\Exceptions\SignupException;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\SlugSuggester;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OtpService;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Self-service signup for a new café: the owner proves their mobile with a code from the platform
 * line, then the café is provisioned like a platform-created one (trial, first branch, subdomain,
 * default roles) and the owner is signed in. A phone that already has an account signs in instead.
 */
final class SignUpCafe
{
    public const OTP_SCOPE = 'signup';

    public function __construct(
        private readonly OtpService $otp,
        private readonly CreateTenant $createTenant,
    ) {}

    /** @return array{expires_in: int, resend_after: int} */
    public function sendCode(string $phoneE164): array
    {
        $this->ensureOpen();
        if (User::query()->where('phone_e164', $phoneE164)->exists()) {
            throw SignupException::phoneTaken();
        }

        return $this->otp->issueFor(self::OTP_SCOPE, $phoneE164);
    }

    /**
     * @param  array{cafe_name: string, slug: string, owner_name: string, phone: string, password: string, code: string}  $data
     * @return array{tenant: Tenant, user: User, token: string}
     */
    public function handle(array $data): array
    {
        $this->ensureOpen();
        if (! SlugSuggester::available($data['slug'])) {
            throw SignupException::slugTaken();
        }
        if (User::query()->where('phone_e164', $data['phone'])->exists()) {
            throw SignupException::phoneTaken();
        }

        // Last: a wrong code costs an attempt, so check everything cheap first.
        $this->otp->verifyFor(self::OTP_SCOPE, $data['phone'], $data['code']);

        try {
            $tenant = $this->createTenant->handle(new CreateTenantData(
                name: $data['cafe_name'],
                slug: $data['slug'],
                ownerName: $data['owner_name'],
                ownerEmail: null,
                ownerPhoneE164: $data['phone'],
                ownerPassword: $data['password'],
                subdomainBase: config('tenancy.subdomain_base'),
            ));
        } catch (UniqueConstraintViolationException) {
            throw SignupException::slugTaken(); // someone took the address a moment ago
        }

        $user = User::query()->where('phone_e164', $data['phone'])->firstOrFail();
        $token = $user->createToken('dashboard', ['staff'], now()->addHours((int) config('otp.staff_token_hours')))->plainTextToken;

        return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
    }

    private function ensureOpen(): void
    {
        if (! config('tenancy.self_signup')) {
            throw SignupException::closed();
        }
    }
}
