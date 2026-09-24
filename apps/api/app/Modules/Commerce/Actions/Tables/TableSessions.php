<?php

namespace App\Modules\Commerce\Actions\Tables;

use App\Modules\Commerce\Enums\TableRequestType;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Table visits. The first scan opens a session; later scans at the same table join it.
 */
final class TableSessions
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function join(RestaurantTable $table): OrderSession
    {
        return DB::transaction(function () use ($table): OrderSession {
            RestaurantTable::query()->whereKey($table->getKey())->lockForUpdate()->first();

            $session = OrderSession::query()
                ->where('table_id', $table->getKey())
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            if ($session !== null && ! $session->isUsable()) {
                $this->close($session); // idle for too long: a new group of guests
                $session = null;
            }

            if ($session === null) {
                $session = OrderSession::query()->create([
                    'branch_id' => $table->branch_id,
                    'table_id' => $table->getKey(),
                    'status' => 'open',
                    'opened_at' => now(),
                    'last_activity_at' => now(),
                ]);
            } else {
                $session->update(['last_activity_at' => now()]);
            }

            return $session;
        });
    }

    /** Verifies a session access token ("{id}.{hmac}") and returns the open session. */
    public function fromToken(?string $token): OrderSession
    {
        $id = is_string($token) ? strstr($token, '.', true) : false;
        $session = $id ? OrderSession::query()->find($id) : null;

        if ($session === null || ! hash_equals($session->accessToken(), (string) $token) || ! $session->isUsable()) {
            throw CommerceException::sessionInvalid();
        }

        return $session;
    }

    public function close(OrderSession $session, ?User $actor = null): void
    {
        if ($session->status === 'closed') {
            return;
        }

        $session->update(['status' => 'closed', 'closed_at' => now()]);
        TableSessionRequest::query()->where('order_session_id', $session->getKey())->where('status', 'open')
            ->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);

        if ($actor !== null) {
            $this->audit->record('table.session_closed', $session, null, $actor);
        }
    }

    public function request(OrderSession $session, TableRequestType $type): TableSessionRequest
    {
        return DB::transaction(function () use ($session, $type): TableSessionRequest {
            $recent = TableSessionRequest::query()
                ->where('table_id', $session->table_id)
                ->where('type', $type)
                ->where('created_at', '>', now()->subSeconds(TableSessionRequest::COOLDOWN_SECONDS))
                ->lockForUpdate()
                ->latest('created_at')
                ->first();

            if ($recent !== null) {
                throw CommerceException::requestCooldown(max(1, TableSessionRequest::COOLDOWN_SECONDS - (int) $recent->created_at->diffInSeconds(now())));
            }

            $session->update(['last_activity_at' => now()]);

            return TableSessionRequest::query()->create([
                'order_session_id' => $session->getKey(),
                'table_id' => $session->table_id,
                'type' => $type,
                'status' => 'open',
            ]);
        });
    }

    public function acknowledge(TableSessionRequest $request, ?User $actor): TableSessionRequest
    {
        if ($request->status === 'open') {
            $request->update(['status' => 'acknowledged', 'acknowledged_by' => $actor?->getKey(), 'acknowledged_at' => now()]);
        }

        return $request;
    }
}
