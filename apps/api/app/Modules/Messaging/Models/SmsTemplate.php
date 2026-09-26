<?php

namespace App\Modules\Messaging\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A café's version of an automatic message (see TemplateCatalog for the keys and defaults).
 *
 * @property string $id
 * @property string $key
 * @property bool $is_enabled
 * @property string $body
 */
#[Fillable(['key', 'is_enabled', 'body'])]
class SmsTemplate extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
