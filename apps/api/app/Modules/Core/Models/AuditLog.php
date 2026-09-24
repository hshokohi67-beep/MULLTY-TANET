<?php

namespace App\Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit trail. Rows are never updated or deleted by the application.
 *
 * @property ?string $tenant_id
 * @property string $action
 * @property ?array<string, mixed> $changes
 */
#[Fillable(['tenant_id', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip', 'user_agent'])]
class AuditLog extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }
}
