<?php

namespace App\Modules\Kitchen\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something on this branch's kitchen board changed — refetch." Deliberately carries no order data,
 * so a subscriber learns nothing it couldn't already fetch with its own credentials.
 * Screens also poll (with ETag), so a missed broadcast only delays an update by one poll.
 */
final class KitchenBoardChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $branchId,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}.kds.{$this->branchId}")];
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['branch_id' => $this->branchId];
    }
}
