<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Models\Tenant;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Support\OtpService;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;

final class LoginCustomerWithOtp
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{customer: Customer, token: string, is_new: bool}
     */
    public function handle(Tenant $tenant, string $phoneE164, string $code, ?string $deviceName = null): array
    {
        $this->otp->verify($tenant, $phoneE164, $code);

        try {
            $customer = Customer::query()->firstOrCreate(['phone_e164' => $phoneE164]);
        } catch (UniqueConstraintViolationException) {
            // Two concurrent first logins for the same phone: the other request won the insert.
            $customer = Customer::query()->where('phone_e164', $phoneE164)->firstOrFail();
        }

        $isNew = $customer->wasRecentlyCreated;
        $customer->forceFill(['last_login_at' => now()])->save();

        $token = $customer->createToken(
            mb_substr($deviceName ?: 'storefront', 0, 60),
            ['customer'],
            now()->addDays((int) config('otp.customer_token_days')),
        )->plainTextToken;

        $this->audit->record($isNew ? 'customer.registered' : 'customer.logged_in', $customer, null, $customer);

        return ['customer' => $customer, 'token' => $token, 'is_new' => $isNew];
    }
}
