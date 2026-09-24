<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value tenant configuration. Values of secret keys are encrypted at rest
 * and hidden from serialisation. Read them only through {@see self::plainValue()}.
 *
 * @property string $key
 * @property ?string $value
 * @property bool $is_encrypted
 */
#[Fillable(['key', 'value', 'is_encrypted'])]
#[Hidden(['value'])]
class TenantSetting extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    public function plainValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        return $this->is_encrypted ? Crypt::decryptString($this->value) : $this->value;
    }

    public function storeValue(?string $plain, bool $encrypt): void
    {
        $this->is_encrypted = $encrypt;
        $this->value = ($plain !== null && $encrypt) ? Crypt::encryptString($plain) : $plain;
    }
}
