<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class StaffUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone_e164 ? PhoneNormalizer::toLocal($this->phone_e164) : null,
            'is_platform_admin' => $this->is_platform_admin,
        ];
    }
}
