<?php

namespace App\Modules\Messaging\Models;

use App\Support\Sms\SmsDrivers;
use App\Support\Sms\SmsProvider;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The café's own SMS panel (one per café). Credentials are encrypted at rest and never returned
 * by the API (only masked hints).
 *
 * @property string $id
 * @property string $provider
 * @property array<string, string> $credentials
 * @property bool $is_active
 * @property ?Carbon $verified_at
 * @property ?string $last_error
 */
#[Fillable(['provider', 'credentials', 'is_active', 'verified_at', 'last_error'])]
class SmsAccount extends Model
{
    use BelongsToTenant, HasUlids;

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_active' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function driver(): SmsProvider
    {
        return SmsDrivers::make($this->provider, $this->credentials);
    }

    public function sender(): string
    {
        return (string) ($this->credentials['sender'] ?? '');
    }
}
