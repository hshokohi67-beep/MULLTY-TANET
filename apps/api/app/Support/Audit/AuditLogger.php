<?php

namespace App\Support\Audit;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes append-only audit entries. Secret values are always redacted before storage.
 */
final class AuditLogger
{
    public const REDACTED = '[REDACTED]';

    private const SECRET_MARKERS = ['password', 'secret', 'api_key', 'apikey', 'token', 'private_key', 'credential'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function record(string $action, ?Model $subject = null, ?array $changes = null, ?Authenticatable $actor = null): AuditLog
    {
        $actor ??= $this->request->user();

        $entry = new AuditLog([
            'tenant_id' => $this->context->id(),
            'actor_type' => match (true) {
                $actor instanceof User => 'user',
                $actor instanceof Customer => 'customer',
                default => 'system',
            },
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === null ? null : self::redact($changes),
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
        ]);

        $this->context->has()
            ? $entry->save()
            : $this->context->bypass(fn () => $entry->save());

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redact($value);
            } elseif (self::isSecretKey((string) $key)) {
                $data[$key] = $value === null ? null : self::REDACTED;
            }
        }

        return $data;
    }

    public static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SECRET_MARKERS as $marker) {
            if (str_contains($key, $marker)) {
                return true;
            }
        }

        return false;
    }
}
