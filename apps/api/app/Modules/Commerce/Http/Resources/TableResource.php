<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Commerce\Models\TableQrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes QR tokens: only whether a code exists, its last 4 characters and its date.
 *
 * @mixin RestaurantTable
 */
final class TableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TableQrCode|null $qr */
        $qr = $this->relationLoaded('qrCodes') ? $this->qrCodes->firstWhere('is_active', true) : null;
        /** @var OrderSession|null $session */
        $session = $this->relationLoaded('sessions') ? $this->sessions->first(fn (OrderSession $s) => $s->isUsable()) : null;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'label' => $this->label,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'qr' => $qr ? ['hint' => $qr->token_hint, 'issued_at' => $qr->created_at?->toIso8601String()] : null,
            'open_session' => $session ? ['id' => $session->id, 'opened_at' => $session->opened_at->toIso8601String()] : null,
        ];
    }
}
