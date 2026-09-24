<?php

namespace App\Modules\Payments\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only log of a gateway call (request/verify). Never contains credentials.
 *
 * @property string $id
 * @property string $payment_id
 * @property string $action
 * @property bool $success
 * @property ?string $gateway_code
 * @property ?int $http_status
 * @property ?array<string, mixed> $request
 * @property ?array<string, mixed> $response
 * @property int $duration_ms
 * @property Carbon $created_at
 */
#[Fillable(['payment_id', 'action', 'success', 'gateway_code', 'http_status', 'request', 'response', 'duration_ms'])]
class PaymentTransaction extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['success' => 'boolean', 'http_status' => 'integer', 'request' => 'array', 'response' => 'array', 'duration_ms' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment transactions are append-only.'));
    }
}
