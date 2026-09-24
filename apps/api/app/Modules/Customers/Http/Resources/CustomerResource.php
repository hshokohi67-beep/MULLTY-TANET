<?php

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\Customer;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer's own view of their profile.
 *
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => PhoneNormalizer::toLocal($this->phone_e164),
            'birth_month' => $this->birth_month,
            'birth_day' => $this->birth_day,
            'birthday_locked' => $this->birthday_locked,
            'referral_code' => $this->referral_code,
            'marketing_opt_in' => $this->marketing_opt_in,
        ];
    }
}
