<?php

namespace App\Support\Entitlements;

use Illuminate\Http\Request;

/** Everything allowed: the default until a billing module binds a real gate. */
final class PermissiveEntitlementGate implements EntitlementGate
{
    public function enabled(string $feature): bool
    {
        return true;
    }

    public function limit(string $feature): ?int
    {
        return null;
    }

    public function ensureCanAdd(string $feature, int $currentCount): void {}

    public function assertWritable(Request $request): void {}
}
